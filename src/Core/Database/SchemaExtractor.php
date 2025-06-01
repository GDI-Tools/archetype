<?php
namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class SchemaExtractor {
    private SchemaBuilder $schemaBuilder;
    private static array $activeTempTables = [];

    public function __construct(SchemaBuilder $schemaBuilder, bool $doctrineDbalAvailable = false) {
        $this->schemaBuilder = $schemaBuilder;

        // Register shutdown function to cleanup any remaining temp tables
        register_shutdown_function([$this, 'cleanupAllTempTables']);
    }

    public function extractFromModel(BaseModel $model): array {
        $schema = [];
        $tempTableName = $this->generateShortTempTableName(get_class($model));

        try {
            ArchetypeLogger::debug("Starting schema extraction for " . get_class($model), [
                'temp_table' => $tempTableName
            ]);

            // Check if temp table already exists and drop it
            if ($this->schemaBuilder->hasTable($tempTableName)) {
                ArchetypeLogger::warning("Temp table already exists, dropping it", [
                    'temp_table' => $tempTableName
                ]);
                $this->dropTempTableSafely($tempTableName);
            }

            // Create temporary table
            $this->createTempTableWithModel($tempTableName, $model);

            // Track this temp table for cleanup
            self::$activeTempTables[$tempTableName] = time();

            // Extract schema using modern Laravel methods
            $schema = $this->extractSchemaModern($tempTableName);

            ArchetypeLogger::debug("Successfully extracted schema for " . get_class($model), [
                'columns_found' => count($schema)
            ]);

        } catch (\Exception $e) {
            ArchetypeLogger::error("Schema extraction failed for " . get_class($model), [
                'temp_table' => $tempTableName,
                'error' => $e->getMessage()
            ]);

            // Return empty schema and let migration system handle it gracefully
            $schema = [];
        } finally {
            // Always attempt cleanup
            $this->cleanupTempTable($tempTableName);
        }

        return $schema;
    }

    /**
     * Generate a shorter temporary table name
     */
    private function generateShortTempTableName(string $modelClass): string {
        $className = basename(str_replace('\\', '/', $modelClass));
        $hash = substr(md5($modelClass . microtime(true)), 0, 8);
        return 'tmp_' . strtolower($className) . '_' . $hash;
    }

    /**
     * Create temporary table with model schema
     */
    private function createTempTableWithModel(string $tempTableName, BaseModel $model): void {
        try {
            $this->schemaBuilder->create($tempTableName, function ($table) use ($model) {
                // Add ID if the model uses auto-incrementing
                if ($model->incrementing) {
                    $table->id();
                }

                // Call model's schema definition
                if (method_exists($model, 'defineSchema')) {
                    $model->defineSchema($table);
                }

                // Add timestamps if the model uses them
                if ($model->timestamps) {
                    $table->timestamps();
                }
            });

            ArchetypeLogger::debug("Created temporary table successfully", [
                'temp_table' => $tempTableName
            ]);

        } catch (\Exception $e) {
            ArchetypeLogger::error("Failed to create temporary table", [
                'temp_table' => $tempTableName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract schema using modern Laravel schema inspection methods
     */
    private function extractSchemaModern(string $tableName): array {
        $schema = [];

        try {
            $connection = $this->schemaBuilder->getConnection();

            // Check if table exists
            if (!$this->schemaBuilder->hasTable($tableName)) {
                throw new \Exception("Temporary table does not exist: {$tableName}");
            }

            // Use modern Laravel Schema methods if available (Laravel 9+)
            if (method_exists($this->schemaBuilder, 'getColumns')) {
                ArchetypeLogger::debug("Using Laravel Schema::getColumns() method");
                return $this->extractWithSchemaGetColumns($tableName);
            }

            // Use Laravel Schema::getColumnListing() and detailed queries (Laravel 8+)
            if (method_exists($this->schemaBuilder, 'getColumnListing')) {
                ArchetypeLogger::debug("Using Laravel Schema::getColumnListing() method");
                return $this->extractWithColumnListing($tableName);
            }

            // Fallback to direct SQL queries
            ArchetypeLogger::debug("Using direct SQL queries fallback");
            return $this->extractWithDirectSQL($tableName);

        } catch (\Exception $e) {
            ArchetypeLogger::error("Modern schema extraction failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract schema using Laravel's getColumns() method (Laravel 9+)
     */
    private function extractWithSchemaGetColumns(string $tableName): array {
        $schema = [];

        try {
            $columns = $this->schemaBuilder->getColumns($tableName);

            foreach ($columns as $column) {
                $name = $column['name'];
                $type = $this->normalizeColumnType($column['type'] ?? $column['type_name'] ?? 'varchar');

                $schema[$name] = [
                    'name' => $name,
                    'type' => strtoupper($type),
                    'nullable' => $column['nullable'] ?? false,
                    'default' => $column['default'] ?? null,
                    'autoincrement' => $column['auto_increment'] ?? false,
                    'length' => $this->extractLength($column),
                    'precision' => $this->extractPrecision($column),
                    'scale' => $this->extractScale($column),
                    'unique' => false, // Will be determined separately
                ];
            }

            // Get indexes if method exists
            if (method_exists($this->schemaBuilder, 'getIndexes')) {
                $this->addIndexInformation($tableName, $schema);
            }

        } catch (\Exception $e) {
            ArchetypeLogger::warning("getColumns() method failed, falling back", [
                'error' => $e->getMessage()
            ]);
            return $this->extractWithColumnListing($tableName);
        }

        return $schema;
    }

    /**
     * Extract schema using getColumnListing() and detailed queries
     */
    private function extractWithColumnListing(string $tableName): array {
        $schema = [];

        try {
            $connection = $this->schemaBuilder->getConnection();
            $columns = $this->schemaBuilder->getColumnListing($tableName);

            foreach ($columns as $columnName) {
                // Get column details using SHOW COLUMNS
                $columnInfo = $this->getColumnDetails($tableName, $columnName);

                if ($columnInfo) {
                    $schema[$columnName] = $columnInfo;
                }
            }

            // Add index information
            $this->addIndexInformationSQL($tableName, $schema);

        } catch (\Exception $e) {
            ArchetypeLogger::warning("getColumnListing() method failed, falling back", [
                'error' => $e->getMessage()
            ]);
            return $this->extractWithDirectSQL($tableName);
        }

        return $schema;
    }

    /**
     * Extract schema using direct SQL queries (fallback)
     */
    private function extractWithDirectSQL(string $tableName): array {
        $schema = [];

        try {
            $connection = $this->schemaBuilder->getConnection();
            $prefix = $connection->getTablePrefix();
            $fullTableName = $prefix . $tableName;

            // Get columns using SHOW COLUMNS
            $columns = $connection->select("SHOW COLUMNS FROM `{$fullTableName}`");

            foreach ($columns as $column) {
                $field = $column->Field;
                $typeInfo = $this->parseColumnType($column->Type);

                $schema[$field] = [
                    'name' => $field,
                    'type' => strtoupper($typeInfo['type']),
                    'length' => $typeInfo['length'] ?? null,
                    'precision' => $typeInfo['precision'] ?? null,
                    'scale' => $typeInfo['scale'] ?? null,
                    'nullable' => strtoupper($column->Null) === 'YES',
                    'default' => $column->Default,
                    'unique' => $column->Key === 'UNI',
                    'primary' => $column->Key === 'PRI',
                    'autoincrement' => strpos(strtolower($column->Extra ?? ''), 'auto_increment') !== false,
                ];
            }

            // Get index information
            $this->addIndexInformationSQL($tableName, $schema);

        } catch (\Exception $e) {
            ArchetypeLogger::error("Direct SQL extraction failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $schema;
    }

    /**
     * Get detailed column information
     */
    private function getColumnDetails(string $tableName, string $columnName): ?array {
        try {
            $connection = $this->schemaBuilder->getConnection();
            $prefix = $connection->getTablePrefix();
            $fullTableName = $prefix . $tableName;

            $result = $connection->select(
                "SHOW COLUMNS FROM `{$fullTableName}` WHERE Field = ?",
                [$columnName]
            );

            if (empty($result)) {
                return null;
            }

            $column = $result[0];
            $typeInfo = $this->parseColumnType($column->Type);

            return [
                'name' => $column->Field,
                'type' => strtoupper($typeInfo['type']),
                'length' => $typeInfo['length'] ?? null,
                'precision' => $typeInfo['precision'] ?? null,
                'scale' => $typeInfo['scale'] ?? null,
                'nullable' => strtoupper($column->Null) === 'YES',
                'default' => $column->Default,
                'unique' => $column->Key === 'UNI',
                'primary' => $column->Key === 'PRI',
                'autoincrement' => strpos(strtolower($column->Extra ?? ''), 'auto_increment') !== false,
            ];

        } catch (\Exception $e) {
            ArchetypeLogger::warning("Failed to get column details for {$columnName}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Add index information using Laravel's getIndexes() method
     */
    private function addIndexInformation(string $tableName, array &$schema): void {
        try {
            $indexes = $this->schemaBuilder->getIndexes($tableName);

            foreach ($indexes as $index) {
                if (!empty($index['unique']) && count($index['columns']) === 1) {
                    $columnName = $index['columns'][0];
                    if (isset($schema[$columnName])) {
                        $schema[$columnName]['unique'] = true;
                    }
                }
            }
        } catch (\Exception $e) {
            ArchetypeLogger::warning("Failed to get index information using getIndexes()", [
                'error' => $e->getMessage()
            ]);
            // Fallback to SQL method
            $this->addIndexInformationSQL($tableName, $schema);
        }
    }

    /**
     * Add index information using SQL queries
     */
    private function addIndexInformationSQL(string $tableName, array &$schema): void {
        try {
            $connection = $this->schemaBuilder->getConnection();
            $prefix = $connection->getTablePrefix();
            $fullTableName = $prefix . $tableName;

            $indexes = $connection->select("SHOW INDEXES FROM `{$fullTableName}`");

            foreach ($indexes as $index) {
                $columnName = $index->Column_name;
                $isUnique = $index->Non_unique == 0;

                if ($isUnique && isset($schema[$columnName])) {
                    $schema[$columnName]['unique'] = true;
                }
            }
        } catch (\Exception $e) {
            ArchetypeLogger::warning("Failed to get index information using SQL", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Parse MySQL column type definition
     */
    private function parseColumnType(string $typeString): array {
        $type = $typeString;
        $length = null;
        $precision = null;
        $scale = null;

        if (preg_match('/^([a-z]+)\((\d+)(?:,(\d+))?\)/i', $typeString, $matches)) {
            $type = $matches[1];
            $length = (int)$matches[2];

            if (isset($matches[3])) {
                $scale = (int)$matches[3];
                $precision = $length;
            }
        } elseif (preg_match('/^([a-z]+)/i', $typeString, $matches)) {
            $type = $matches[1];
        }

        return [
            'type' => $type,
            'length' => $length,
            'precision' => $precision,
            'scale' => $scale
        ];
    }

    /**
     * Normalize column type names
     */
    private function normalizeColumnType(string $type): string {
        $type = strtolower($type);

        $mappings = [
            'int' => 'integer',
            'bool' => 'boolean',
            'varchar' => 'string',
        ];

        return $mappings[$type] ?? $type;
    }

    /**
     * Extract length from column array
     */
    private function extractLength(array $column): ?int {
        return $column['length'] ?? $column['size'] ?? null;
    }

    /**
     * Extract precision from column array
     */
    private function extractPrecision(array $column): ?int {
        return $column['precision'] ?? null;
    }

    /**
     * Extract scale from column array
     */
    private function extractScale(array $column): ?int {
        return $column['scale'] ?? null;
    }

    /**
     * Safely drop temporary table
     */
    private function dropTempTableSafely(string $tempTableName): void {
        try {
            if ($this->schemaBuilder->hasTable($tempTableName)) {
                $this->schemaBuilder->drop($tempTableName);
                ArchetypeLogger::debug("Dropped temporary table", [
                    'temp_table' => $tempTableName
                ]);
            }
        } catch (\Exception $e) {
            ArchetypeLogger::warning("Could not drop temporary table", [
                'temp_table' => $tempTableName,
                'error' => $e->getMessage()
            ]);

            // Try alternative drop method
            try {
                $conn = $this->schemaBuilder->getConnection();
                $conn->statement("DROP TABLE IF EXISTS `" . $conn->getTablePrefix() . $tempTableName . "`");
                ArchetypeLogger::debug("Force dropped temporary table", [
                    'temp_table' => $tempTableName
                ]);
            } catch (\Exception $e2) {
                ArchetypeLogger::error("Failed to force drop temporary table", [
                    'temp_table' => $tempTableName,
                    'error' => $e2->getMessage()
                ]);
            }
        }
    }

    /**
     * Cleanup temporary table
     */
    private function cleanupTempTable(string $tempTableName): void {
        $this->dropTempTableSafely($tempTableName);
        unset(self::$activeTempTables[$tempTableName]);
    }

    /**
     * Cleanup all remaining temporary tables
     */
    public function cleanupAllTempTables(): void {
        if (empty(self::$activeTempTables)) {
            return;
        }

        ArchetypeLogger::info("Cleaning up remaining temporary tables", [
            'count' => count(self::$activeTempTables)
        ]);

        foreach (array_keys(self::$activeTempTables) as $tempTableName) {
            $this->cleanupTempTable($tempTableName);
        }

        self::$activeTempTables = [];
    }

    /**
     * Get list of active temporary tables
     */
    public static function getActiveTempTables(): array {
        return self::$activeTempTables;
    }
}