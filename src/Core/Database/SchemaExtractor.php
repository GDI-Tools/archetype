<?php
namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class SchemaExtractor {
    private SchemaBuilder $schemaBuilder;
    private bool $doctrineDbalAvailable;
    private static array $activeTempTables = [];

    public function __construct(
        SchemaBuilder $schemaBuilder,
        bool $doctrineDbalAvailable
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->doctrineDbalAvailable = $doctrineDbalAvailable;

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

            // Create temporary table WITHOUT transaction to avoid nested transaction issues
            $this->createTempTableWithModel($tempTableName, $model);

            // Track this temp table for cleanup
            self::$activeTempTables[$tempTableName] = time();

            // Extract schema using appropriate method
            if ($this->doctrineDbalAvailable) {
                $schema = $this->extractSchemaWithDoctrine($tempTableName);
            } else {
                $schema = $this->extractSchemaBasic($tempTableName);
            }

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
     * Generate a shorter temporary table name to avoid MySQL 64-char limit for index names
     */
    private function generateShortTempTableName(string $modelClass): string {
        // Use only the class name without namespace
        $className = basename(str_replace('\\', '/', $modelClass));

        // Create a short hash from the full class name + microtime
        $hash = substr(md5($modelClass . microtime(true)), 0, 8);

        // Keep it short: tmp_[ClassName]_[8chars]
        return 'tmp_' . strtolower($className) . '_' . $hash;
    }

    /**
     * Create temporary table with model schema WITHOUT using transactions
     */
    private function createTempTableWithModel(string $tempTableName, BaseModel $model): void {
        try {
            // Create a custom blueprint that limits index name lengths
            $this->schemaBuilder->create($tempTableName, function ($table) use ($model, $tempTableName) {
                // Add ID if the model uses auto-incrementing
                if ($model->incrementing) {
                    $table->id();
                }

                // Call model's schema definition with custom table wrapper
                if (method_exists($model, 'defineSchema')) {
                    $wrappedTable = new IndexNameLimitingBlueprint($table, $tempTableName);
                    $model->defineSchema($wrappedTable);
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
     * Extract schema using Doctrine DBAL with improved error handling
     */
    private function extractSchemaWithDoctrine(string $tableName): array {
        $schema = [];

        try {
            $conn = $this->schemaBuilder->getConnection();

            // Verify connection is still valid
            if (!$this->verifyConnection($conn)) {
                throw new \Exception("Database connection is not valid");
            }

            $doctrineSchemaManager = $conn->getDoctrineSchemaManager();
            $platformTableName = $conn->getTablePrefix() . $tableName;

            // Check if table exists before trying to read it
            if (!$this->schemaBuilder->hasTable($tableName)) {
                throw new \Exception("Temporary table does not exist: {$tableName}");
            }

            // Get table columns with retry mechanism
            $columns = $this->executeWithRetry(function() use ($doctrineSchemaManager, $platformTableName) {
                return $doctrineSchemaManager->listTableColumns($platformTableName);
            }, 2);

            foreach ($columns as $column) {
                $schema[$column->getName()] = [
                    'name' => $column->getName(),
                    'type' => strtoupper($this->mapDoctrineType($column->getType()->getName())),
                    'nullable' => !$column->getNotnull(),
                    'default' => $column->getDefault(),
                    'length' => $column->getLength(),
                    'precision' => $column->getPrecision(),
                    'scale' => $column->getScale(),
                    'autoincrement' => $column->getAutoincrement(),
                    'comment' => $column->getComment(),
                    'unique' => false // Will be set below
                ];
            }

            // Get unique constraints with error handling
            try {
                $indices = $this->executeWithRetry(function() use ($doctrineSchemaManager, $platformTableName) {
                    return $doctrineSchemaManager->listTableIndexes($platformTableName);
                }, 2);

                foreach ($indices as $index) {
                    if ($index->isUnique() && count($index->getColumns()) === 1) {
                        $columnName = $index->getColumns()[0];
                        if (isset($schema[$columnName])) {
                            $schema[$columnName]['unique'] = true;
                        }
                    }
                }
            } catch (\Exception $e) {
                ArchetypeLogger::warning("Could not extract index information", [
                    'table' => $tableName,
                    'error' => $e->getMessage()
                ]);
                // Continue without index information
            }

        } catch (\Exception $e) {
            ArchetypeLogger::error("Doctrine schema extraction failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $schema;
    }

    /**
     * Extract schema using basic SQL queries
     */
    private function extractSchemaBasic(string $tableName): array {
        $schema = [];

        try {
            $conn = $this->schemaBuilder->getConnection();

            // Verify connection is still valid
            if (!$this->verifyConnection($conn)) {
                throw new \Exception("Database connection is not valid");
            }

            $prefix = $conn->getTablePrefix();
            $fullTableName = $prefix . $tableName;

            // Check if table exists
            if (!$this->schemaBuilder->hasTable($tableName)) {
                throw new \Exception("Temporary table does not exist: {$tableName}");
            }

            // Get columns with retry mechanism
            $columns = $this->executeWithRetry(function() use ($conn, $fullTableName) {
                return $conn->select("SHOW COLUMNS FROM `{$fullTableName}`");
            }, 3);

            if (empty($columns)) {
                throw new \Exception("No columns found for table: {$tableName}");
            }

            foreach ($columns as $column) {
                $typeInfo = $this->parseColumnType($column->Type);

                $schema[$column->Field] = [
                    'name' => $column->Field,
                    'type' => strtoupper($typeInfo['type']),
                    'length' => $typeInfo['length'] ?? null,
                    'precision' => $typeInfo['precision'] ?? null,
                    'scale' => $typeInfo['scale'] ?? null,
                    'nullable' => $column->Null === 'YES',
                    'default' => $column->Default,
                    'unique' => $column->Key === 'UNI',
                    'primary' => $column->Key === 'PRI',
                    'autoincrement' => strpos($column->Extra ?? '', 'auto_increment') !== false,
                ];
            }

            // Get additional index information with error handling
            try {
                $indices = $this->executeWithRetry(function() use ($conn, $fullTableName) {
                    return $conn->select("SHOW INDEXES FROM `{$fullTableName}`");
                }, 2);

                foreach ($indices as $index) {
                    if ($index->Non_unique == 0 && isset($schema[$index->Column_name])) {
                        $schema[$index->Column_name]['unique'] = true;
                    }
                }
            } catch (\Exception $e) {
                ArchetypeLogger::warning("Could not get index information", [
                    'table' => $tableName,
                    'error' => $e->getMessage()
                ]);
                // Continue without additional index information
            }

        } catch (\Exception $e) {
            ArchetypeLogger::error("Basic schema extraction failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $schema;
    }

    /**
     * Execute a function with retry mechanism
     */
    private function executeWithRetry(callable $function, int $maxRetries = 3, int $delayMs = 100) {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $function();
            } catch (\Exception $e) {
                $lastException = $e;

                ArchetypeLogger::warning("Execution attempt {$attempt}/{$maxRetries} failed", [
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $maxRetries) {
                    usleep($delayMs * 1000 * $attempt); // Exponential backoff
                }
            }
        }

        throw $lastException;
    }

    /**
     * Verify database connection is still valid
     */
    private function verifyConnection($connection): bool {
        try {
            $connection->select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            ArchetypeLogger::warning("Database connection verification failed", [
                'error' => $e->getMessage()
            ]);
            return false;
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

        // Handle complex type definitions
        if (preg_match('/^([a-z]+)\((\d+)(?:,(\d+))?\)/', strtolower($typeString), $matches)) {
            $type = $matches[1];
            $length = (int)$matches[2];

            if (isset($matches[3])) {
                $scale = (int)$matches[3];
                $precision = $length;
            }
        } elseif (preg_match('/^([a-z]+)/', strtolower($typeString), $matches)) {
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
     * Map Doctrine DBAL types to MySQL types
     */
    private function mapDoctrineType(string $doctrineType): string {
        $map = [
            'smallint' => 'SMALLINT',
            'integer' => 'INT',
            'bigint' => 'BIGINT',
            'decimal' => 'DECIMAL',
            'float' => 'FLOAT',
            'string' => 'VARCHAR',
            'text' => 'TEXT',
            'boolean' => 'TINYINT',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'datetimetz' => 'DATETIME',
            'time' => 'TIME',
            'blob' => 'BLOB',
            'binary' => 'BINARY',
            'uuid' => 'VARCHAR',
            'json' => 'JSON',
        ];

        return $map[$doctrineType] ?? 'VARCHAR';
    }

    /**
     * Safely drop temporary table with error handling
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
     * Cleanup temporary table and remove from tracking
     */
    private function cleanupTempTable(string $tempTableName): void {
        $this->dropTempTableSafely($tempTableName);
        unset(self::$activeTempTables[$tempTableName]);
    }

    /**
     * Cleanup all remaining temporary tables (called on shutdown)
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
     * Get list of active temporary tables (for debugging)
     */
    public static function getActiveTempTables(): array {
        return self::$activeTempTables;
    }
}

/**
 * Blueprint wrapper that limits index name lengths to avoid MySQL's 64-character limit
 */
class IndexNameLimitingBlueprint {
    private $blueprint;
    private string $tableName;

    public function __construct($blueprint, string $tableName) {
        $this->blueprint = $blueprint;
        $this->tableName = $tableName;
    }

    /**
     * Create an index with a shortened name
     */
    public function index($columns, $name = null, $algorithm = null) {
        if ($name === null) {
            // Generate a short index name to avoid MySQL's 64-character limit
            $columnString = is_array($columns) ? implode('_', $columns) : $columns;
            $name = 'idx_' . substr(md5($this->tableName . '_' . $columnString), 0, 8);
        }

        // Ensure name doesn't exceed MySQL's limit
        if (strlen($name) > 64) {
            $name = substr($name, 0, 60) . '_' . substr(md5($name), 0, 3);
        }

        return $this->blueprint->index($columns, $name, $algorithm);
    }

    /**
     * Create a unique index with a shortened name
     */
    public function unique($columns, $name = null, $algorithm = null) {
        if ($name === null) {
            $columnString = is_array($columns) ? implode('_', $columns) : $columns;
            $name = 'unq_' . substr(md5($this->tableName . '_' . $columnString), 0, 8);
        }

        // Ensure name doesn't exceed MySQL's limit
        if (strlen($name) > 64) {
            $name = substr($name, 0, 60) . '_' . substr(md5($name), 0, 3);
        }

        return $this->blueprint->unique($columns, $name, $algorithm);
    }

    /**
     * Delegate all other method calls to the original blueprint
     */
    public function __call($method, $arguments) {
        return call_user_func_array([$this->blueprint, $method], $arguments);
    }
}