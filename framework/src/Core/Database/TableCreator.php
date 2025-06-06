<?php
namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Archetype\Models\BaseModel;
use Archetype\Vendor\Illuminate\Database\Schema\Builder as SchemaBuilder;

class TableCreator {
	private SchemaBuilder $schemaBuilder;

	public function __construct(SchemaBuilder $schemaBuilder) {
		$this->schemaBuilder = $schemaBuilder;
	}

	public function createTable(BaseModel $model): array {
		$tableName = $model->getTable();
		$log = [];

		try {
			ArchetypeLogger::info("Creating new table: {$tableName}");

			// Check if table already exists
			if ($this->schemaBuilder->hasTable($tableName)) {
				$log[] = "Table {$tableName} already exists, skipping creation";
				return [true, implode("\n", $log)];
			}

			// Create the table
			$this->schemaBuilder->create($tableName, function ($table) use ($model) {
				// Add ID if the model uses auto-incrementing
				if ($model->incrementing) {
					$table->id();
					ArchetypeLogger::debug("Added auto-incrementing ID field to {$model->getTable()}");
				}

				// Let the model define its schema
				ArchetypeLogger::debug("Calling defineSchema() on model " . get_class($model));
				$model->defineSchema($table);

				// Add timestamps if the model uses them
				if ($model->timestamps) {
					$table->timestamps();
					ArchetypeLogger::debug("Added timestamp fields to {$model->getTable()}");
				}
			});

			$log[] = "Successfully created table {$tableName}";
			return [true, implode("\n", $log)];
		} catch (\Exception $e) {
			$errorMsg = "Failed to create table {$tableName}: " . $e->getMessage();
			ArchetypeLogger::error($errorMsg);
			$log[] = $errorMsg;
			return [false, implode("\n", $log)];
		}
	}
}
