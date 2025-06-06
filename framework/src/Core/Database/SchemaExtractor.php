<?php
namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Archetype\Models\BaseModel;
use Archetype\Vendor\Illuminate\Database\Schema\Builder as SchemaBuilder;

class SchemaExtractor {
    private $schemaBuilder;
    private static array $activeTempTables = [];
    private static int $tableCounter = 0;

    public function __construct( $schemaBuilder, bool $doctrineDbalAvailable = false) {
        $this->schemaBuilder = $schemaBuilder;

        // Register shutdown function to cleanup any remaining temp tables
        register_shutdown_function([$this, 'cleanupAllTempTables']);
    }

    public function extractFromModel(BaseModel $model): array {
        $schema = [];
        $maxRetries = 3;
        $tempTableName = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $tempTableName = $this->generateShortTempTableName(get_class($model));

                ArchetypeLogger::debug("Starting schema extraction for " . get_class($model), [
                    'temp_table' => $tempTableName,
                    'attempt' => $attempt
                ]);

                // Force cleanup before creation to prevent collisions
                $this->forceCleanupTempTable($tempTableName);

                // Create temporary table with retry logic
                $this->createTempTableWithRetry($tempTableName, $model);

                // Track this temp table for cleanup
                self::$activeTempTables[$tempTableName] = time();

                // Extract schema using modern Laravel methods
                $schema = $this->extractSchemaModern($tempTableName);

                ArchetypeLogger::debug("Successfully extracted schema for " . get_class($model), [
                    'columns_found' => count($schema),
                    'attempt' => $attempt
                ]);

                // Success - break out of retry loop
                break;

            } catch (\Exception $e) {
                ArchetypeLogger::warning("Schema extraction attempt {$attempt} failed for " . get_class($model), [
                    'temp_table' => $tempTableName,
                    'error' => $e->getMessage()
                ]);

                // Clean up failed attempt
                if ($tempTableName) {
                    $this->cleanupTempTable($tempTableName);
                }

                // If this was the last attempt, log error and return empty schema
                if ($attempt === $maxRetries) {
                    ArchetypeLogger::error("Schema extraction failed after {$maxRetries} attempts for " . get_class($model), [
                        'error' => $e->getMessage()
                    ]);
                    $schema = [];
                }

                // For collision errors (error code 1050), try again with new name
                if (strpos($e->getMessage(), '1050') !== false) {
                    ArchetypeLogger::info("Table collision detected, retrying with new name", [
                        'attempt' => $attempt,
                        'next_attempt' => $attempt + 1
                    ]);
                    continue;
                }

                // For other errors, don't retry
                break;
            } finally {
                // Always attempt cleanup
                if ($tempTableName) {
                    $this->cleanupTempTable($tempTableName);
                }
            }
        }

        return $schema;
    }

    /**
     * Generate a shorter, safer temporary table name with collision resistance
     */
    private function generateShortTempTableName(string $modelClass): string {
        // Increment counter for uniqueness
        self::$tableCounter++;

        $className = basename(str_replace('\\', '/', $modelClass));
        $shortClass = substr(strtolower($className), 0, 12); // Limit class name to 12 chars

        // Create unique ID with multiple entropy sources
        $entropy = microtime(true) . getmypid() . random_int(1000, 9999) . self::$tableCounter;
        $uniqueId = substr(md5($entropy), 0, 10);

        $tableName = 'tmp_' . $shortClass . '_' . $uniqueId;

        // Extra safety check - ensure we don't exceed MySQL's 64-character limit
        if (strlen($tableName) > 50) { // Leave room for prefixes and indexes
            $tableName = 'tmp_' . substr(md5($modelClass . $entropy), 0, 20);
        }

        ArchetypeLogger::debug("Generated temp table name", [
            'model' => $modelClass,
            'table_name' => $tableName,
            'length' => strlen($tableName),
            'counter' => self::$tableCounter
        ]);

        return $tableName;
    }

    /**
     * Force cleanup of temporary table before creation
     */
    private function forceCleanupTempTable(string $tempTableName): void {
        try {
            // Method 1: Use Laravel schema builder
            if ($this->schemaBuilder->hasTable($tempTableName)) {
                ArchetypeLogger::debug("Force dropping existing temp table", [
                    'temp_table' => $tempTableName
                ]);
                $this->schemaBuilder->drop($tempTableName);
            }
        } catch (\Exception $e) {
            ArchetypeLogger::debug("Laravel drop failed, trying direct SQL", [
                'temp_table' => $tempTableName,
                'error' => $e->getMessage()
            ]);

            // Method 2: Direct SQL with proper escaping
            try {
                $conn = $this->schemaBuilder->getConnection();
                $prefix = $conn->getTablePrefix();
                $fullTableName = $prefix . $tempTableName;

                $conn->statement("DROP TABLE IF EXISTS `" . str_replace('`', '``', $fullTableName) . "`");

                ArchetypeLogger::debug("Force dropped temp table via SQL", [
                    'temp_table' => $tempTableName
                ]);
            } catch (\Exception $e2) {
                ArchetypeLogger::debug("SQL drop also failed, proceeding anyway", [
                    'temp_table' => $tempTableName,
                    'error' => $e2->getMessage()
                ]);
            }
        }
    }

    /**
     * Create temporary table with collision detection and retry
     */
    private function createTempTableWithRetry(string $tempTableName, BaseModel $model): void {
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
            // Check if this is a table collision error (MySQL error 1050)
            if (strpos($e->getMessage(), '1050') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {

                ArchetypeLogger::warning("Table collision detected during creation", [
                    'temp_table' => $tempTableName,
                    'error' => $e->getMessage()
                ]);

                throw new \Exception("Table collision: " . $e->getMessage());
            }

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
     * Safely drop temporary table with multiple methods
     */
    private function dropTempTableSafely(string $tempTableName): void {
        $methods = [
            'laravel_schema' => function() use ($tempTableName) {
                if ($this->schemaBuilder->hasTable($tempTableName)) {
                    $this->schemaBuilder->drop($tempTableName);
                    return true;
                }
                return false;
            },
            'direct_sql' => function() use ($tempTableName) {
                $conn = $this->schemaBuilder->getConnection();
                $prefix = $conn->getTablePrefix();
                $fullTableName = $prefix . $tempTableName;
                $conn->statement("DROP TABLE IF EXISTS `" . str_replace('`', '``', $fullTableName) . "`");
                return true;
            },
            'direct_sql_no_prefix' => function() use ($tempTableName) {
                $conn = $this->schemaBuilder->getConnection();
                $conn->statement("DROP TABLE IF EXISTS `" . str_replace('`', '``', $tempTableName) . "`");
                return true;
            }
        ];

        foreach ($methods as $methodName => $method) {
            try {
                if ($method()) {
                    ArchetypeLogger::debug("Successfully dropped temp table using {$methodName}", [
                        'temp_table' => $tempTableName
                    ]);
                    return;
                }
            } catch (\Exception $e) {
                ArchetypeLogger::debug("Method {$methodName} failed to drop temp table", [
                    'temp_table' => $tempTableName,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        ArchetypeLogger::warning("All methods failed to drop temporary table", [
            'temp_table' => $tempTableName
        ]);
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