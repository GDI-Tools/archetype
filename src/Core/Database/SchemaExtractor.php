<?php
namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class SchemaExtractor {
	private SchemaBuilder $schemaBuilder;
	private bool $doctrineDbalAvailable;

	public function __construct(
		SchemaBuilder $schemaBuilder,
		bool $doctrineDbalAvailable
	) {
		$this->schemaBuilder = $schemaBuilder;
		$this->doctrineDbalAvailable = $doctrineDbalAvailable;
	}

	public function extractFromModel(BaseModel $model): array {
		$schema = [];
		$tempTableName = 'temp_schema_extract_' . md5(get_class($model) . time());

		try {
			// Create a temporary table with the model's schema
			$this->schemaBuilder->create($tempTableName, function ($table) use ($model) {
				if ($model->incrementing) {
					$table->id();
				}

				$model->defineSchema($table);

				if ($model->timestamps) {
					$table->timestamps();
				}
			});

			// Extract schema - method depends on available libraries
			if ($this->doctrineDbalAvailable) {
				$schema = $this->extractSchemaWithDoctrine($tempTableName);
			} else {
				$schema = $this->extractSchemaBasic($tempTableName);
			}

			// Drop the temporary table
			$this->schemaBuilder->drop($tempTableName);

			ArchetypeLogger::debug("Successfully extracted schema for " . get_class($model));
		} catch (\Exception $e) {
			ArchetypeLogger::error("Failed to extract schema: " . $e->getMessage());

			// Ensure temporary table is cleaned up
			try {
				if ($this->schemaBuilder->hasTable($tempTableName)) {
					$this->schemaBuilder->drop($tempTableName);
				}
			} catch (\Exception $dropException) {
				ArchetypeLogger::error("Failed to drop temporary table: " . $dropException->getMessage());
			}
		}

		return $schema;
	}

	private function extractSchemaWithDoctrine(string $tableName): array {
		$schema = [];

		try {
			$conn = $this->schemaBuilder->getConnection();
			$doctrineSchemaManager = $conn->getDoctrineSchemaManager();

			// Get platform-specific table name (including any prefixes)
			$platformTableName = $conn->getTablePrefix() . $tableName;

			// Use listTableColumns instead of direct access
			$columns = $doctrineSchemaManager->listTableColumns($platformTableName);

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
				];
			}

			// Add unique constraints
			$indices = $doctrineSchemaManager->listTableIndexes($platformTableName);
			foreach ($indices as $index) {
				if ($index->isUnique() && count($index->getColumns()) === 1) {
					$columnName = $index->getColumns()[0];
					if (isset($schema[$columnName])) {
						$schema[$columnName]['unique'] = true;
					}
				}
			}
		} catch (\Exception $e) {
			ArchetypeLogger::error("Error extracting schema with Doctrine: " . $e->getMessage());
		}

		return $schema;
	}

	private function extractSchemaBasic(string $tableName): array {
		$schema = [];

		try {
			$conn = $this->schemaBuilder->getConnection();
			$prefix = $conn->getTablePrefix();

			// Get the fully qualified table name
			$fullTableName = $prefix . $tableName;

			// Use SHOW COLUMNS query
			$columns = $conn->select("SHOW COLUMNS FROM `{$fullTableName}`");

			foreach ($columns as $column) {
				// Parse type and length from Type column (e.g., "varchar(255)" -> "varchar" and 255)
				$typeInfo = $this->parseColumnType($column->Type);

				$schema[$column->Field] = [
					'name' => $column->Field,
					'type' => strtoupper($typeInfo['type']),
					'length' => $typeInfo['length'],
					'nullable' => $column->Null === 'YES',
					'default' => $column->Default,
					'unique' => $column->Key === 'UNI', // Basic uniqueness detection
					'primary' => $column->Key === 'PRI',
					'autoincrement' => strpos($column->Extra, 'auto_increment') !== false,
				];
			}

			// Additional query to get indices (for better uniqueness detection)
			try {
				$indices = $conn->select("SHOW INDEXES FROM `{$fullTableName}`");

				foreach ($indices as $index) {
					if ($index->Non_unique == 0 && isset($schema[$index->Column_name])) {
						$schema[$index->Column_name]['unique'] = true;
					}
				}
			} catch (\Exception $e) {
				ArchetypeLogger::warning("Error getting index information: " . $e->getMessage());
			}
		} catch (\Exception $e) {
			ArchetypeLogger::error("Error extracting basic schema: " . $e->getMessage());
		}

		return $schema;
	}

	/**
	 * Parse MySQL column type definition
	 *
	 * @param string $typeString Type definition (e.g., "varchar(255)" or "int(11)")
	 * @return array Associative array with 'type' and 'length' keys
	 */
	private function parseColumnType(string $typeString): array {
		$type = $typeString;
		$length = null;

		// Extract length if present
		if (preg_match('/^([a-z]+)\((\d+)(?:,(\d+))?\)/', $typeString, $matches)) {
			$type = $matches[1];
			$length = (int)$matches[2];

			// For decimal, we also have scale
			if (isset($matches[3])) {
				$scale = (int)$matches[3];
				return [
					'type' => $type,
					'length' => $length,
					'precision' => $length,
					'scale' => $scale
				];
			}
		}

		return [
			'type' => $type,
			'length' => $length
		];
	}

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
}