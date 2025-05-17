<?php
namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class MigrationRecorder {
	private SchemaBuilder $schemaBuilder;
	private string $migrationTable;

	public function __construct(SchemaBuilder $schemaBuilder, string $tablePrefix = '') {
		$this->schemaBuilder = $schemaBuilder;
		$this->migrationTable = $tablePrefix . 'schema_migrations';
	}

	public function ensureMigrationTableExists(): void {
		try {
			if (!$this->schemaBuilder->hasTable($this->migrationTable)) {
				$this->schemaBuilder->create($this->migrationTable, function ($table) {
					$table->id();
					$table->string('model_class', 191)->unique();
					$table->string('schema_hash', 64);
					$table->longText('schema_definition')->nullable();
					$table->timestamp('last_migrated_at')->useCurrent();
					$table->integer('version')->default(1);
					$table->boolean('successful')->default(true);
					$table->text('log')->nullable();
				});

				ArchetypeLogger::info("Created migration tracking table: {$this->migrationTable}");
			}
		} catch (\Exception $e) {
			ArchetypeLogger::error("Failed to create migration table: " . $e->getMessage());
		}
	}

	public function calculateSchemaHash(array $schema): string {
		// Sort schema to ensure consistent hash
		if (empty($schema)) {
			return hash('sha256', '{}');
		}

		// Make a copy of the schema to avoid modifying the original
		$schemaCopy = $schema;

		// Sort keys for consistent hashing
		ksort($schemaCopy);

		// Normalize schema values for more reliable hashing
		foreach ($schemaCopy as &$column) {
			if (is_array($column)) {
				ksort($column);

				// Convert all values to strings for consistent hashing
				foreach ($column as $key => $value) {
					if (is_bool($value)) {
						$column[$key] = $value ? 'true' : 'false';
					} elseif (is_null($value)) {
						$column[$key] = 'null';
					} elseif (is_array($value)) {
						$column[$key] = json_encode($value);
					}
				}
			}
		}

		return hash('sha256', json_encode($schemaCopy));
	}

	public function getMigrationRecord(string $modelClass): ?object {
		try {
			$conn = $this->schemaBuilder->getConnection();
			return $conn->table($this->migrationTable)
			            ->where('model_class', $modelClass)
			            ->first();
		} catch (\Exception $e) {
			ArchetypeLogger::error("Failed to get migration record: " . $e->getMessage());
			return null;
		}
	}

	public function recordMigration(
		string $modelClass,
		array $schema,
		bool $successful,
		string $log = ''
	): bool {
		try {
			// Ensure migration table exists
			$this->ensureMigrationTableExists();

			$conn = $this->schemaBuilder->getConnection();
			$record = $this->getMigrationRecord($modelClass);

			$data = [
				'schema_hash' => $this->calculateSchemaHash($schema),
				'schema_definition' => json_encode($schema),
				'last_migrated_at' => date('Y-m-d H:i:s'),
				'successful' => $successful,
				'log' => $log
			];

			if ($record) {
				// Update existing record
				ArchetypeLogger::debug("Updating migration record for {$modelClass}");

				$result = $conn->table($this->migrationTable)
				               ->where('model_class', $modelClass)
				               ->update(array_merge($data, [
					               'version' => $record->version + 1
				               ]));

				if (!$result) {
					ArchetypeLogger::warning("Failed to update migration record for {$modelClass}");
					return false;
				}
			} else {
				// Create new record
				ArchetypeLogger::debug("Creating new migration record for {$modelClass}");

				$result = $conn->table($this->migrationTable)
				               ->insert(array_merge($data, [
					               'model_class' => $modelClass,
					               'version' => 1
				               ]));

				if (!$result) {
					ArchetypeLogger::warning("Failed to create migration record for {$modelClass}");
					return false;
				}
			}

			return true;
		} catch (\Exception $e) {
			ArchetypeLogger::error("Failed to update migration record: " . $e->getMessage());
			return false;
		}
	}
}