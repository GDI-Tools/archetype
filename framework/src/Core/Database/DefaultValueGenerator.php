<?php
/**
 * Default Value Generator for Database Columns
 *
 * @package Archetype\Core\Database
 * ''
 */

namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;

/**
 * Generates appropriate default values for different column types
 */
class DefaultValueGenerator {
	/**
	 * Generate a default value for a column
	 *
	 * @param string $type Column type
	 * @param array $definition Column definition
	 * @return mixed
	 */
	public function generateDefault(string $type, array $definition): mixed {
		$type = strtoupper($type);

		// Generate based on type category
		return match($this->getTypeCategory($type)) {
			'numeric' => $this->generateNumericDefault($type, $definition),
			'string' => $this->generateStringDefault($type, $definition),
			'datetime' => $this->generateDateTimeDefault($type),
			'boolean' => 0, // MySQL stores BOOLEANs as TINYINTs
			'binary' => null, // Binary data typically doesn't have defaults
			'json' => '{}',
			default => null
		};
	}

	/**
	 * Generate default for numeric types
	 *
	 * @param string $type Column type
	 * @param array $definition Column definition
	 * @return int|float|null
	 */
	private function generateNumericDefault(string $type, array $definition): int|float|null {
		// Determine if the column is unsigned
		$unsigned = $definition['unsigned'] ?? false;

		// Generate default based on type
		return match($type) {
			'TINYINT' => $unsigned ? 0 : 0,
			'SMALLINT' => $unsigned ? 0 : 0,
			'MEDIUMINT' => $unsigned ? 0 : 0,
			'INTEGER', 'INT' => $unsigned ? 0 : 0,
			'BIGINT' => $unsigned ? 0 : 0,
			'DECIMAL', 'NUMERIC' => 0.0,
			'FLOAT' => 0.0,
			'DOUBLE' => 0.0,
			default => 0
		};
	}

	/**
	 * Generate default for string types
	 *
	 * @param string $type Column type
	 * @param array $definition Column definition
	 * @return string
	 */
	private function generateStringDefault(string $type, array $definition): string {
		// For most string types, empty string is a good default
		return '';
	}

	/**
	 * Generate default for date/time types
	 *
	 * @param string $type Column type
	 * @return string|null
	 */
	private function generateDateTimeDefault(string $type): ?string {
		return match($type) {
			'DATE' => date('Y-m-d'),
			'DATETIME' => date('Y-m-d H:i:s'),
			'TIMESTAMP' => 'CURRENT_TIMESTAMP',
			'TIME' => date('H:i:s'),
			'YEAR' => date('Y'),
			default => null
		};
	}

	/**
	 * Get the category for a data type
	 *
	 * @param string $type Column type
	 * @return string Type category
	 */
	private function getTypeCategory(string $type): string {
		$type = strtoupper($type);

		// Numeric types
		$numericTypes = [
			'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT',
			'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE', 'REAL'
		];

		if (in_array($type, $numericTypes)) {
			return 'numeric';
		}

		// String types
		$stringTypes = [
			'CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'
		];

		if (in_array($type, $stringTypes)) {
			return 'string';
		}

		// Date/time types
		$dateTimeTypes = [
			'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR'
		];

		if (in_array($type, $dateTimeTypes)) {
			return 'datetime';
		}

		// Boolean
		if (in_array($type, ['BOOLEAN', 'BOOL'])) {
			return 'boolean';
		}

		// Binary types
		$binaryTypes = [
			'BINARY', 'VARBINARY', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'LONGBLOB'
		];

		if (in_array($type, $binaryTypes)) {
			return 'binary';
		}

		// JSON type
		if ($type === 'JSON') {
			return 'json';
		}

		// Enum and set
		if (in_array($type, ['ENUM', 'SET'])) {
			return 'enum';
		}

		// Unknown type
		ArchetypeLogger::warning("Unknown column type for default value: {$type}");
		return 'unknown';
	}

	/**
	 * Generate a non-NULL value for a column in a safe way
	 *
	 * This is used when a column is NOT NULL and doesn't have a default,
	 * but we need to add data to an existing table
	 *
	 * @param string $type Column type
	 * @param array $definition Column definition
	 * @return mixed
	 */
	public function generateNonNullValue(string $type, array $definition): mixed {
		$type = strtoupper($type);

		// Start with the standard default
		$value = $this->generateDefault($type, $definition);

		// For some cases we need special handling
		switch ($this->getTypeCategory($type)) {
			case 'enum':
				// Get allowed values
				$values = $definition['values'] ?? [];

				// Use first value if available
				if (!empty($values)) {
					$value = $values[0];
				} else {
					$value = '';
				}
				break;

			case 'binary':
				// For binary types, empty string is usually acceptable
				$value = '';
				break;
		}

		return $value;
	}

	/**
	 * Attempt to cast a value to a different type safely
	 *
	 * @param mixed $value Original value
	 * @param string $fromType Original type
	 * @param string $toType Target type
	 * @return mixed|null Converted value or null if conversion failed
	 */
	public function safeCastValue(mixed $value, string $fromType, string $toType): mixed {
		$fromType = strtoupper($fromType);
		$toType = strtoupper($toType);

		// Null values stay null
		if ($value === null) {
			return null;
		}

		// If same type, no conversion needed
		if ($fromType === $toType) {
			return $value;
		}

		// Special cases

		// Numeric to String (always safe)
		if (in_array($fromType, ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE']) &&
		    in_array($toType, ['CHAR', 'VARCHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'])) {
			return (string)$value;
		}

		// String to numeric
		if (in_array($fromType, ['CHAR', 'VARCHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT']) &&
		    in_array($toType, ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE'])) {

			// Attempt conversion
			if (is_numeric($value)) {
				// Convert to specific numeric type
				switch ($toType) {
					case 'TINYINT':
					case 'SMALLINT':
					case 'MEDIUMINT':
					case 'INT':
					case 'INTEGER':
					case 'BIGINT':
						return (int)$value;
					case 'DECIMAL':
					case 'NUMERIC':
					case 'FLOAT':
					case 'DOUBLE':
						return (float)$value;
				}
			}

			// Non-numeric string can't be converted
			return null;
		}

		// Boolean conversions
		if (in_array($fromType, ['BOOLEAN', 'BOOL', 'TINYINT']) && in_array($toType, ['BOOLEAN', 'BOOL', 'TINYINT'])) {
			// BOOLEAN in MySQL is just TINYINT(1), so conversion is trivial
			return (int)(bool)$value;
		}

		// String to Boolean
		if (in_array($fromType, ['CHAR', 'VARCHAR', 'TEXT']) && in_array($toType, ['BOOLEAN', 'BOOL', 'TINYINT'])) {
			// Common representations
			$boolStrings = [
				true => ['true', 'yes', 'y', '1', 'on'],
				false => ['false', 'no', 'n', '0', 'off']
			];

			$lower = strtolower(trim((string)$value));

			if (in_array($lower, $boolStrings[true])) {
				return 1;
			}

			if (in_array($lower, $boolStrings[false])) {
				return 0;
			}

			// Default to false for unknown values
			return 0;
		}

		// Date/Time conversions
		if (in_array($fromType, ['DATE', 'DATETIME', 'TIMESTAMP']) && in_array($toType, ['DATE', 'DATETIME', 'TIMESTAMP'])) {
			// Most date/time conversions are safe
			return $value;
		}

		// Default case - can't convert safely
		return null;
	}
}