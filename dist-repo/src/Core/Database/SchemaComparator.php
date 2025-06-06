<?php
/**
 * Schema Comparison Utility
 *
 * @package Archetype\Core\Database
 */

namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;

/**
 * Compares database schemas to detect changes
 */
class SchemaComparator {
	/**
	 * Compare two schemas and detect changes
	 *
	 * @param array $oldSchema Old schema definition
	 * @param array $newSchema New schema definition
	 * @return array Array of SchemaChange objects
	 */
	public function compareSchemas(array $oldSchema, array $newSchema, string $modelClass): array {
		$changes = [];

		ArchetypeLogger::debug("Comparing schemas: " . count($oldSchema) . " columns in old schema, " . count($newSchema) . " columns in new schema for " . $modelClass);

		// Detect added columns
		$addedColumns = array_diff(
			array_keys($newSchema),
			array_keys($oldSchema)
		);

		foreach ($addedColumns as $columnName) {
			$changes[] = new SchemaChange(
				SchemaChangeType::COLUMN_ADDED,
				$columnName,
				null,
				$newSchema[$columnName]
			);

			ArchetypeLogger::info("Detected added column: {$columnName} in {$modelClass}");
		}

		// Detect removed columns
		$removedColumns = array_diff(
			array_keys($oldSchema),
			array_keys($newSchema)
		);

		foreach ($removedColumns as $columnName) {
			$changes[] = new SchemaChange(
				SchemaChangeType::COLUMN_REMOVED,
				$columnName,
				$oldSchema[$columnName],
				null
			);

			ArchetypeLogger::info("Detected removed column: {$columnName} in {$modelClass}");
		}

		// Detect modified columns
		$commonColumns = array_intersect(
			array_keys($oldSchema),
			array_keys($newSchema)
		);

		foreach ($commonColumns as $columnName) {
			$columnChanges = $this->compareColumn(
				$oldSchema[$columnName],
				$newSchema[$columnName],
				$columnName
			);

			if (!empty($columnChanges)) {
				$changes = array_merge($changes, $columnChanges);
			}
		}

		return $changes;
	}

	/**
	 * Compare a single column for changes
	 *
	 * @param array $oldDefinition Old column definition
	 * @param array $newDefinition New column definition
	 * @param string $columnName Column name for debugging
	 * @return array Array of SchemaChange objects for this column
	 */
	private function compareColumn(array $oldDefinition, array $newDefinition, string $columnName): array {
		$changes = [];

		// Ensure both definitions have all required keys
		$this->normalizeDefinition($oldDefinition);
		$this->normalizeDefinition($newDefinition);

		// Normalize types for comparison
		$oldType = $this->normalizeType($oldDefinition['type']);
		$newType = $this->normalizeType($newDefinition['type']);

		// Check for type changes (including length for string types)
		$typeChanged = false;

		if ($oldType !== $newType) {
			$typeChanged = true;
		}
		// For string types, also check length
		elseif (in_array($oldType, ['VARCHAR', 'CHAR']) &&
		        isset($oldDefinition['length']) &&
		        isset($newDefinition['length']) &&
		        $oldDefinition['length'] != $newDefinition['length']) {
			$typeChanged = true;
		}
		// For numeric types, check precision and scale
		elseif (in_array($oldType, ['DECIMAL', 'NUMERIC']) &&
		        (isset($oldDefinition['precision']) && isset($newDefinition['precision']) &&
		         $oldDefinition['precision'] != $newDefinition['precision']) ||
		        (isset($oldDefinition['scale']) && isset($newDefinition['scale']) &&
		         $oldDefinition['scale'] != $newDefinition['scale'])) {
			$typeChanged = true;
		}

		if ($typeChanged) {
			$changes[] = new SchemaChange(
				SchemaChangeType::COLUMN_TYPE_CHANGED,
				$columnName,
				$oldDefinition,
				$newDefinition
			);

			ArchetypeLogger::info("Detected column type change for {$columnName}: {$oldType} -> {$newType}");
		}

		// Check for nullable changes
		$oldNullable = $oldDefinition['nullable'] ?? false;
		$newNullable = $newDefinition['nullable'] ?? false;

		if ($oldNullable !== $newNullable) {
			$changes[] = new SchemaChange(
				SchemaChangeType::COLUMN_NULLABILITY_CHANGED,
				$columnName,
				$oldDefinition,
				$newDefinition
			);

			$oldStr = $oldNullable ? 'NULL' : 'NOT NULL';
			$newStr = $newNullable ? 'NULL' : 'NOT NULL';
			ArchetypeLogger::info("Detected nullability change for {$columnName}: {$oldStr} -> {$newStr}");
		}

		// Check for default value changes
		if ($this->hasDefaultChanged($oldDefinition, $newDefinition)) {
			$changes[] = new SchemaChange(
				SchemaChangeType::COLUMN_DEFAULT_CHANGED,
				$columnName,
				$oldDefinition,
				$newDefinition
			);

			$oldDefault = $oldDefinition['default'] ?? 'NULL';
			$newDefault = $newDefinition['default'] ?? 'NULL';
			if (is_null($oldDefault)) $oldDefault = 'NULL';
			if (is_null($newDefault)) $newDefault = 'NULL';
			ArchetypeLogger::info("Detected default value change for {$columnName}: {$oldDefault} -> {$newDefault}");
		}

		// Check for unique constraint changes
		$oldUnique = $oldDefinition['unique'] ?? false;
		$newUnique = $newDefinition['unique'] ?? false;

		if ($oldUnique !== $newUnique) {
			$changes[] = new SchemaChange(
				SchemaChangeType::COLUMN_UNIQUE_CHANGED,
				$columnName,
				$oldDefinition,
				$newDefinition
			);

			$action = $newUnique ? 'added to' : 'removed from';
			ArchetypeLogger::info("Detected unique constraint {$action} {$columnName}");
		}

		return $changes;
	}

	/**
	 * Ensure all required keys exist in a column definition
	 *
	 * @param array &$definition Column definition to normalize
	 * @return void
	 */
	private function normalizeDefinition(array &$definition): void {
		$defaults = [
			'type' => 'VARCHAR',
			'nullable' => false,
			'default' => null,
			'unique' => false,
			'length' => null,
			'precision' => null,
			'scale' => null,
		];

		foreach ($defaults as $key => $value) {
			if (!isset($definition[$key])) {
				$definition[$key] = $value;
			}
		}
	}

	/**
	 * Normalize type name for consistent comparison
	 *
	 * @param string $type Type name
	 * @return string Normalized type
	 */
	private function normalizeType(string $type): string {
		$type = strtoupper($type);

		$typeAliases = [
			'INT' => 'INTEGER',
			'BOOL' => 'BOOLEAN',
			'CHAR VARYING' => 'VARCHAR',
			'CHARACTER VARYING' => 'VARCHAR',
			'NUMERIC' => 'DECIMAL',
			'REAL' => 'FLOAT'
		];

		return $typeAliases[$type] ?? $type;
	}

	/**
	 * Check if the default value has changed, handling null values
	 *
	 * @param array $oldDefinition Old column definition
	 * @param array $newDefinition New column definition
	 * @return bool
	 */
	private function hasDefaultChanged(array $oldDefinition, array $newDefinition): bool {
		// Check if both have default defined
		$oldHasDefault = array_key_exists('default', $oldDefinition);
		$newHasDefault = array_key_exists('default', $newDefinition);

		// If one has default and other doesn't, they're different
		if ($oldHasDefault !== $newHasDefault) {
			return true;
		}

		// If neither has default, they're the same
		if (!$oldHasDefault && !$newHasDefault) {
			return false;
		}

		// Both have default, compare values
		$oldDefault = $oldDefinition['default'];
		$newDefault = $newDefinition['default'];

		// Handle null comparison
		if ($oldDefault === null && $newDefault === null) {
			return false;
		}

		if ($oldDefault === null || $newDefault === null) {
			return true;
		}

		// Handle numeric comparison (avoid float comparison issues)
		if (is_numeric($oldDefault) && is_numeric($newDefault)) {
			// For integers, do strict comparison
			if (is_int($oldDefault) && is_int($newDefault)) {
				return $oldDefault !== $newDefault;
			}

			// For floats, compare with small epsilon
			$epsilon = 0.00001;
			return abs((float)$oldDefault - (float)$newDefault) > $epsilon;
		}

		// String comparison - normalize first
		$oldStr = (string)$oldDefault;
		$newStr = (string)$newDefault;

		// Special handling for timestamp defaults like CURRENT_TIMESTAMP
		$timestampDefaults = ['CURRENT_TIMESTAMP', 'NOW()', 'CURRENT_TIMESTAMP()'];
		if (in_array(strtoupper($oldStr), $timestampDefaults) && in_array(strtoupper($newStr), $timestampDefaults)) {
			return false;
		}

		return $oldStr !== $newStr;
	}
}

/**
 * Types of schema changes that can be detected
 */
enum SchemaChangeType: string {
	case COLUMN_ADDED = 'column_added';
	case COLUMN_REMOVED = 'column_removed';
	case COLUMN_TYPE_CHANGED = 'column_type_changed';
	case COLUMN_NULLABILITY_CHANGED = 'column_nullability_changed';
	case COLUMN_DEFAULT_CHANGED = 'column_default_changed';
	case COLUMN_UNIQUE_CHANGED = 'column_unique_changed';
}

/**
 * Represents a detected schema change
 */
class SchemaChange {
	/**
	 * Constructor
	 *
	 * @param SchemaChangeType $type Type of change
	 * @param string $columnName Column name
	 * @param array|null $oldDefinition Old column definition
	 * @param array|null $newDefinition New column definition
	 */
	public function __construct(
		public readonly SchemaChangeType $type,
		public readonly string $columnName,
		public readonly ?array $oldDefinition = null,
		public readonly ?array $newDefinition = null
	) {}

	/**
	 * Get description of the change
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return match($this->type) {
			SchemaChangeType::COLUMN_ADDED => "Column '{$this->columnName}' added",
			SchemaChangeType::COLUMN_REMOVED => "Column '{$this->columnName}' removed",
			SchemaChangeType::COLUMN_TYPE_CHANGED => $this->getTypeChangeDescription(),
			SchemaChangeType::COLUMN_NULLABILITY_CHANGED => $this->getNullabilityChangeDescription(),
			SchemaChangeType::COLUMN_DEFAULT_CHANGED => $this->getDefaultChangeDescription(),
			SchemaChangeType::COLUMN_UNIQUE_CHANGED => $this->getUniqueChangeDescription(),
		};
	}

	/**
	 * Get detailed description of type change
	 *
	 * @return string
	 */
	private function getTypeChangeDescription(): string {
		$oldType = $this->oldDefinition['type'] ?? 'unknown';
		$newType = $this->newDefinition['type'] ?? 'unknown';

		// Add length information for string types
		if (in_array(strtoupper($oldType), ['VARCHAR', 'CHAR']) &&
		    in_array(strtoupper($newType), ['VARCHAR', 'CHAR'])) {
			$oldLength = $this->oldDefinition['length'] ?? '?';
			$newLength = $this->newDefinition['length'] ?? '?';
			return "Column '{$this->columnName}' type changed from {$oldType}({$oldLength}) to {$newType}({$newLength})";
		}

		// Add precision/scale for decimal types
		if (in_array(strtoupper($oldType), ['DECIMAL', 'NUMERIC']) &&
		    in_array(strtoupper($newType), ['DECIMAL', 'NUMERIC'])) {
			$oldPrecision = $this->oldDefinition['precision'] ?? '?';
			$oldScale = $this->oldDefinition['scale'] ?? '?';
			$newPrecision = $this->newDefinition['precision'] ?? '?';
			$newScale = $this->newDefinition['scale'] ?? '?';

			return "Column '{$this->columnName}' type changed from {$oldType}({$oldPrecision},{$oldScale}) to {$newType}({$newPrecision},{$newScale})";
		}

		return "Column '{$this->columnName}' type changed from {$oldType} to {$newType}";
	}

	/**
	 * Get nullability change description
	 *
	 * @return string
	 */
	private function getNullabilityChangeDescription(): string {
		$oldNullable = $this->oldDefinition['nullable'] ?? false;
		$newNullable = $this->newDefinition['nullable'] ?? false;

		$fromStr = $oldNullable ? 'NULL' : 'NOT NULL';
		$toStr = $newNullable ? 'NULL' : 'NOT NULL';

		return "Column '{$this->columnName}' nullability changed from {$fromStr} to {$toStr}";
	}

	/**
	 * Get default value change description
	 *
	 * @return string
	 */
	private function getDefaultChangeDescription(): string {
		$oldDefault = $this->oldDefinition['default'] ?? null;
		$newDefault = $this->newDefinition['default'] ?? null;

		$oldStr = is_null($oldDefault) ? 'NULL' : var_export($oldDefault, true);
		$newStr = is_null($newDefault) ? 'NULL' : var_export($newDefault, true);

		return "Column '{$this->columnName}' default value changed from {$oldStr} to {$newStr}";
	}

	/**
	 * Get unique constraint change description
	 *
	 * @return string
	 */
	private function getUniqueChangeDescription(): string {
		$oldUnique = $this->oldDefinition['unique'] ?? false;
		$newUnique = $this->newDefinition['unique'] ?? false;

		if ($newUnique && !$oldUnique) {
			return "Added unique constraint to column '{$this->columnName}'";
		} else {
			return "Removed unique constraint from column '{$this->columnName}'";
		}
	}
}