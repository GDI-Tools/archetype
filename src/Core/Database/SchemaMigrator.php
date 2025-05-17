<?php
namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class SchemaMigrator {
	private SchemaBuilder $schemaBuilder;
	private SchemaExtractor $extractor;
	private TableCreator $tableCreator;
	private SchemaComparator $comparator;
	private MigrationRecorder $recorder;
	private bool $doctrineDbalAvailable = false;

	public function __construct(SchemaBuilder $schemaBuilder, string $tablePrefix = '') {
		$this->schemaBuilder = $schemaBuilder;
		$this->doctrineDbalAvailable = $this->checkDoctrineDbalAvailability();

		// Initialize components
		$this->extractor = new SchemaExtractor($schemaBuilder, $this->doctrineDbalAvailable);
		$this->tableCreator = new TableCreator($schemaBuilder);
		$this->comparator = new SchemaComparator();
		$this->recorder = new MigrationRecorder($schemaBuilder, $tablePrefix);

		if (!$this->doctrineDbalAvailable) {
			ArchetypeLogger::warning("Doctrine DBAL not available. Limited to table creation only.");
		}

		// Ensure migration table exists
		$this->recorder->ensureMigrationTableExists();
	}

	private function checkDoctrineDbalAvailability(): bool {
		return class_exists('Doctrine\DBAL\Driver\AbstractMySQLDriver');
	}

	// Main public methods
	public function needsMigration(BaseModel $model): bool {
		$tableName = $model->getTable();
		$modelClass = get_class($model);

		// If table doesn't exist, it definitely needs migration
		if (!$this->schemaBuilder->hasTable($tableName)) {
			ArchetypeLogger::debug("Table {$tableName} does not exist, needs migration");
			return true;
		}

		// Get the current model schema
		$currentSchema = $this->extractor->extractFromModel($model);
		$currentHash = $this->recorder->calculateSchemaHash($currentSchema);

		// Get the recorded schema hash if available
		$record = $this->recorder->getMigrationRecord($modelClass);

		if (!$record) {
			ArchetypeLogger::debug("No migration record for {$modelClass}, needs migration");
			return true;
		}

		// Compare hashes to detect changes
		$needsMigration = $record->schema_hash !== $currentHash;

		if ($needsMigration) {
			ArchetypeLogger::debug("Schema hash mismatch for {$modelClass}, needs migration");
		}

		return $needsMigration;
	}

	public function getModelsNeedingMigration(array $models): array {
		$needingMigration = [];

		foreach ($models as $model) {
			try {
				$instance = $model;
				// Handle both object instances and model info arrays
				if (is_array($model) && isset($model['instance'])) {
					$instance = $model['instance'];
				}

				if ($this->needsMigration($instance)) {
					$needingMigration[get_class($instance)] = $instance;
				}
			} catch (\Exception $e) {
				ArchetypeLogger::error("Error checking migration for model: " . $e->getMessage());
			}
		}

		return $needingMigration;
	}

	public function migrateModel(BaseModel $model): array {
		$modelClass = get_class($model);
		$tableName = $model->getTable();
		$log = [];

		try {
			// If table doesn't exist, create it
			if (!$this->schemaBuilder->hasTable($tableName)) {
				$log[] = "Table {$tableName} does not exist, creating...";
				list($success, $createLog) = $this->tableCreator->createTable($model);
				$log[] = $createLog;

				if (!$success) {
					return [false, implode("\n", $log)];
				}

				// Update migration record
				$schema = $this->extractor->extractFromModel($model);
				$this->recorder->recordMigration($modelClass, $schema, true, implode("\n", $log));
				return [true, implode("\n", $log)];
			}

			// For existing tables, we need to modify the schema
			$log[] = "Table {$tableName} exists, checking for changes...";

			// Get the current schema definition
			$currentSchema = $this->extractor->extractFromModel($model);

			// Get the recorded schema if available
			$record = $this->recorder->getMigrationRecord($modelClass);
			$oldSchema = [];

			if ($record && !empty($record->schema_definition)) {
				$oldSchema = json_decode($record->schema_definition, true);
			}
			// If we have both schemas, compare them to detect changes
			if (!empty($oldSchema) && !empty($currentSchema)) {
				$changes = $this->comparator->compareSchemas($oldSchema, $currentSchema, $modelClass);

				if (empty($changes)) {
					$log[] = "No schema changes detected for {$tableName}";
				} else {
					$log[] = "Detected " . count($changes) . " schema changes for {$tableName}";

					// Apply the changes using the Schema Builder
					$this->applySchemaChanges($tableName, $changes);

					foreach ($changes as $change) {
						$log[] = "- " . $change->getDescription();
					}
				}
			} else {
				$log[] = "No previous schema found, recording current schema";
			}

			// Record the migration with the current schema
			$this->recorder->recordMigration($modelClass, $currentSchema, true, implode("\n", $log));
			return [true, implode("\n", $log)];
		} catch (\Exception $e) {
			$errorMsg = "Error migrating {$modelClass}: " . $e->getMessage();
			ArchetypeLogger::error($errorMsg);
			$log[] = $errorMsg;

			// Record the failed migration
			if (isset($currentSchema)) {
				$this->recorder->recordMigration($modelClass, $currentSchema, false, implode("\n", $log));
			}

			return [false, implode("\n", $log)];
		}
	}

	/**
	 * Apply schema changes to an existing table
	 *
	 * @param string $tableName Table name
	 * @param array $changes Array of SchemaChange objects
	 * @return void
	 */
	private function applySchemaChanges(string $tableName, array $changes): void {
		if (empty($changes)) {
			return;
		}

		// Group changes by column for more efficient processing
		$columnChanges = [];
		foreach ($changes as $change) {
			$columnChanges[$change->columnName][] = $change;
		}

		$this->schemaBuilder->table($tableName, function($table) use ($columnChanges) {
			foreach ($columnChanges as $columnName => $changes) {
				$this->applyColumnChanges($table, $columnName, $changes);
			}
		});
	}

	/**
	 * Apply changes to a specific column
	 *
	 * @param Blueprint $table
	 * @param string $columnName
	 * @param array $changes
	 * @return void
	 */
	private function applyColumnChanges($table, string $columnName, array $changes): void {
		// Extract the column type change if present
		$typeChange = null;
		$nullabilityChange = null;
		$defaultChange = null;
		$uniqueChange = null;
		$isColumnAdded = false;
		$isColumnRemoved = false;

		foreach ($changes as $change) {
			switch ($change->type) {
				case SchemaChangeType::COLUMN_ADDED:
					$isColumnAdded = true;
					break;

				case SchemaChangeType::COLUMN_REMOVED:
					$isColumnRemoved = true;
					break;

				case SchemaChangeType::COLUMN_TYPE_CHANGED:
					$typeChange = $change;
					break;

				case SchemaChangeType::COLUMN_NULLABILITY_CHANGED:
					$nullabilityChange = $change;
					break;

				case SchemaChangeType::COLUMN_DEFAULT_CHANGED:
					$defaultChange = $change;
					break;

				case SchemaChangeType::COLUMN_UNIQUE_CHANGED:
					$uniqueChange = $change;
					break;
			}
		}

		// Handle column removal
		if ($isColumnRemoved) {
			$table->dropColumn($columnName);
			return;
		}

		// Handle column addition
		if ($isColumnAdded) {
			$newDefinition = null;

			// Find the new definition
			foreach ($changes as $change) {
				if ($change->type === SchemaChangeType::COLUMN_ADDED) {
					$newDefinition = $change->newDefinition;
					break;
				}
			}

			if (!$newDefinition) {
				ArchetypeLogger::warning("Cannot add column {$columnName}: missing definition");
				return;
			}

			// Add the column based on its type
			$this->addColumn($table, $columnName, $newDefinition);
			return;
		}

		// Handle column modifications (type, nullability, default, unique)
		if ($typeChange || $nullabilityChange || $defaultChange || $uniqueChange) {
			// Get the latest definition
			$latestDefinition = null;

			if ($typeChange) {
				$latestDefinition = $typeChange->newDefinition;
			} elseif ($nullabilityChange) {
				$latestDefinition = $nullabilityChange->newDefinition;
			} elseif ($defaultChange) {
				$latestDefinition = $defaultChange->newDefinition;
			} elseif ($uniqueChange) {
				$latestDefinition = $uniqueChange->newDefinition;
			}

			if (!$latestDefinition) {
				ArchetypeLogger::warning("Cannot modify column {$columnName}: missing definition");
				return;
			}

			// Modify the column
			$this->modifyColumn($table, $columnName, $latestDefinition);
		}
	}

	/**
	 * Add a column to a table
	 *
	 * @param Blueprint $table
	 * @param string $columnName
	 * @param array $definition
	 * @return void
	 */
	private function addColumn($table, string $columnName, array $definition): void {
		$type = strtolower($definition['type']);

		// Map SQL types to Blueprint methods
		switch ($type) {
			case 'integer':
			case 'int':
				$column = $table->integer($columnName);
				break;

			case 'bigint':
				$column = $table->bigInteger($columnName);
				break;

			case 'tinyint':
				if (isset($definition['length']) && $definition['length'] == 1) {
					$column = $table->boolean($columnName);
				} else {
					$column = $table->tinyInteger($columnName);
				}
				break;

			case 'smallint':
				$column = $table->smallInteger($columnName);
				break;

			case 'varchar':
			case 'string':
				$length = $definition['length'] ?? 255;
				$column = $table->string($columnName, $length);
				break;

			case 'text':
				$column = $table->text($columnName);
				break;

			case 'mediumtext':
				$column = $table->mediumText($columnName);
				break;

			case 'longtext':
				$column = $table->longText($columnName);
				break;

			case 'float':
				$column = $table->float($columnName);
				break;

			case 'double':
				$column = $table->double($columnName);
				break;

			case 'decimal':
				$precision = $definition['precision'] ?? 8;
				$scale = $definition['scale'] ?? 2;
				$column = $table->decimal($columnName, $precision, $scale);
				break;

			case 'date':
				$column = $table->date($columnName);
				break;

			case 'datetime':
				$column = $table->dateTime($columnName);
				break;

			case 'timestamp':
				$column = $table->timestamp($columnName);
				break;

			case 'time':
				$column = $table->time($columnName);
				break;

			case 'json':
				$column = $table->json($columnName);
				break;

			default:
				ArchetypeLogger::warning("Unknown column type: {$type}, defaulting to string");
				$column = $table->string($columnName);
				break;
		}

		// Set additional properties
		if (isset($definition['nullable']) && $definition['nullable']) {
			$column->nullable();
		} else {
			$column->nullable(false);
		}

		if (isset($definition['default'])) {
			$column->default($definition['default']);
		}

		if (isset($definition['unique']) && $definition['unique']) {
			$column->unique();
		}
	}

	/**
	 * Modify an existing column
	 *
	 * @param Blueprint $table
	 * @param string $columnName
	 * @param array $definition
	 * @return void
	 */
	private function modifyColumn($table, string $columnName, array $definition): void {
		// For modifying columns, we use the change() method which requires doctrine/dbal
		if (!$this->doctrineDbalAvailable) {
			ArchetypeLogger::warning("Cannot modify column {$columnName}: Doctrine DBAL not available");
			return;
		}

		$type = strtolower($definition['type']);

		// Similar to addColumn but with ->change() at the end
		switch ($type) {
			case 'integer':
			case 'int':
				$column = $table->integer($columnName);
				break;

			case 'bigint':
				$column = $table->bigInteger($columnName);
				break;

			case 'tinyint':
				if (isset($definition['length']) && $definition['length'] == 1) {
					$column = $table->boolean($columnName);
				} else {
					$column = $table->tinyInteger($columnName);
				}
				break;

			case 'smallint':
				$column = $table->smallInteger($columnName);
				break;

			case 'varchar':
			case 'string':
				$length = $definition['length'] ?? 255;
				$column = $table->string($columnName, $length);
				break;

			case 'text':
				$column = $table->text($columnName);
				break;

			case 'mediumtext':
				$column = $table->mediumText($columnName);
				break;

			case 'longtext':
				$column = $table->longText($columnName);
				break;

			case 'float':
				$column = $table->float($columnName);
				break;

			case 'double':
				$column = $table->double($columnName);
				break;

			case 'decimal':
				$precision = $definition['precision'] ?? 8;
				$scale = $definition['scale'] ?? 2;
				$column = $table->decimal($columnName, $precision, $scale);
				break;

			case 'date':
				$column = $table->date($columnName);
				break;

			case 'datetime':
				$column = $table->dateTime($columnName);
				break;

			case 'timestamp':
				$column = $table->timestamp($columnName);
				break;

			case 'time':
				$column = $table->time($columnName);
				break;

			case 'json':
				$column = $table->json($columnName);
				break;

			default:
				ArchetypeLogger::warning("Unknown column type: {$type}, defaulting to string");
				$column = $table->string($columnName);
				break;
		}

		// Set additional properties
		if (isset($definition['nullable']) && $definition['nullable']) {
			$column->nullable();
		} else {
			$column->nullable(false);
		}

		if (isset($definition['default'])) {
			$column->default($definition['default']);
		} else {
			$column->default(null);
		}

		if (isset($definition['unique']) && $definition['unique']) {
			$column->unique();
		}

		// Add the change() call to modify the existing column
		$column->change();
	}

	public function migrateAll(array $models): array {
		$results = [];

		foreach ($models as $model) {
			$modelClass = get_class($model);
			$results[$modelClass] = $this->migrateModel($model);
		}

		return $results;
	}
}