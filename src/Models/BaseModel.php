<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * Base Model class extending Eloquent Model for WordPress plugin development.
 *
 * @package Archetype
 */

namespace Archetype\Models;

use Archetype\Logging\ArchetypeLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

abstract class BaseModel extends EloquentModel {
	/**
	 * Default to using timestamps (created_at and updated_at)
	 *
	 * @var bool
	 */
	public $timestamps = true;

    /**
     * Enable auto-incrementing primary keys
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

	/**
	 * Method for defining schema
	 * This should be implemented by model classes to define their schema
	 *
	 * @param Blueprint $table
	 * @return void
	 */
	abstract public function defineSchema(Blueprint $table);

	/**
	 * Get the table associated with the model.
	 * This version handles both prefixing and pluralization issues.
	 *
	 * @return string
	 */
	public function getTable() {
		// Check if the table property is explicitly set
		if (isset($this->table)) {
			$table = $this->table;

			// Log that we're using the explicit table name
			ArchetypeLogger::debug("Using explicit table name: {$table}");
		} else {
			// Use default Eloquent table name logic, but don't pluralize
			// Important: We're NOT using Str::plural here to avoid the pluralization issue
			$table = str_replace('\\', '', Str::snake(class_basename($this)));

			// Log that we're using the derived table name
			ArchetypeLogger::debug("Using derived table name (no pluralization): {$table}");
		}

		// Get the additional table prefix
		$tablePrefix = '';

		// Try to get the connection configuration to extract the table_prefix
		try {
			// Get connection from parent class
			$connection = parent::getConnection();
			if ($connection) {
				$config = $connection->getConfig();

				// Get the additional table prefix
				if (isset($config['table_prefix'])) {
					$tablePrefix = $config['table_prefix'];

					// Check if the table name already starts with the table prefix
					// This prevents the prefix from being added twice
					if (strpos($table, $tablePrefix) === 0) {
						ArchetypeLogger::debug("Table name already has prefix, not adding it again: {$table}");
						// Return the table name without adding the prefix again
						return $table;
					}
				}
			}
		} catch (\Exception $e) {
			ArchetypeLogger::warning("Could not get connection config: " . $e->getMessage());
		}

		// Add the table prefix to the base table name
		$finalTable = $tablePrefix . $table;

		// Log the table name construction
		ArchetypeLogger::debug("Table name construction: tablePrefix={$tablePrefix}, base table={$table}, final={$finalTable}");

		return $finalTable;
	}
}