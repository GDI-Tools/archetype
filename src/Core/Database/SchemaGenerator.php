<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * SchemaGenerator creates database tables based on model definitions.
 *
 * @package Archetype\Core\Database
 */

namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use ReflectionClass;

class SchemaGenerator {
	/**
	 * Schema builder
	 *
	 * @var SchemaBuilder
	 */
	private $schema;

	/**
	 * Constructor
	 *
	 * @param SchemaBuilder $schema
	 */
	public function __construct(SchemaBuilder $schema) {
		$this->schema = $schema;
	}

	/**
	 * Create tables for all discovered models
	 *
	 * @param array $models Array of model information
	 * @return void
	 */
	public function createTables(array $models): void {
		foreach ($models as $model) {
			$this->createTableFromModel($model);
		}
	}

	/**
	 * Create a table for a specific model
	 *
	 * @param array $model Model information
	 * @return void
	 */
	private function createTableFromModel(array $model): void {
		$className = $model['class'];
		$instance = $model['instance'];

		// Get the actual table name directly from the model instance
		// This ensures we use the correct prefixed table name
		try {
			$tableName = $instance->getTable();
		} catch (\Exception $e) {
			// Fallback to the table name from the model info if getTable() fails
			$tableName = $model['table'];
		}

		// Skip if table already exists
		try {
			if ($this->schema->hasTable($tableName)) {
				return;
			}
		} catch (\Exception $e) {
			ArchetypeLogger::error("Error checking if table '{$tableName}' exists: " . $e->getMessage());
			// Continue anyway to try creating the table
		}

		try {
			$this->schema->create($tableName, function($table) use ($instance, $model) {
				// Add default ID if model uses auto-incrementing
				if (property_exists($instance, 'incrementing') ? $instance->incrementing : true) {
					$table->id();
				}

				// Call the model's schema definition method
				if (method_exists($instance, 'defineSchema')) {
					$instance->defineSchema($table);
				} else {
					ArchetypeLogger::warning("Model {$model['class']} does not implement defineSchema() method.");
				}

				// Add timestamps if model uses them
				if ($model['timestamps']) {
					$table->timestamps();
				}
			});
		} catch (\Exception $e) {
			ArchetypeLogger::error("Failed to create table '{$tableName}': " . $e->getMessage());
		}
	}
}