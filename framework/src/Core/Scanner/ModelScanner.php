<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * ModelScanner is responsible for scanning directories and discovering
 * model classes marked with the Model attribute.
 *
 * @package Archetype\Core\Scanner
 */

namespace Archetype\Core\Scanner;

use Archetype\Attributes\Model;
use Archetype\Logging\ArchetypeLogger;
use Archetype\Models\BaseModel;
use Archetype\Vendor\Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;

class ModelScanner extends BaseComponentScanner {
	/**
	 * Scan directories for classes with Model attribute
	 *
	 * @param array $paths Directories to scan
	 * @param array $excluded_folders Folders to exclude from scanning
	 * @param int $max_depth Maximum recursion depth (0 for unlimited)
	 * @return array Array of model class information
	 */
	public function scan(array $paths, array $excluded_folders = [], int $max_depth = 0): array {
		$models = [];

		foreach ($paths as $path) {
			$classes = $this->findClassesInDirectory($path, $excluded_folders, $max_depth);

			foreach ($classes as $class) {
				try {
					$reflector = new ReflectionClass($class);

					// Skip abstract classes
					if ($reflector->isAbstract()) {
						continue;
					}

					// Check if class has Model attribute
					$attributes = $reflector->getAttributes(Model::class);

					if (empty($attributes)) {
						continue;
					}

					// Check if class extends Model base class
					if (!$reflector->isSubclassOf(BaseModel::class)) {
						ArchetypeLogger::warning("Class {$class} has Model attribute but doesn't extend " . BaseModel::class);
						continue;
					}

					// Get the Model attribute instance
					$modelAttribute = $attributes[0]->newInstance();

					// Create a temporary instance to access properties and methods
					$instance = $reflector->newInstanceWithoutConstructor();

					// Set the table property on the instance if it's defined in the attribute
					if (!empty($modelAttribute->table)) {
						try {
							// First check if the property exists in this class
							if ($reflector->hasProperty('table')) {
								$tableProp = $reflector->getProperty('table');
								$tableProp->setAccessible(true);
								$tableProp->setValue($instance, $modelAttribute->table);
							} else {
								// If not, it might be in a parent class
								$parentClass = $reflector->getParentClass();
								while ($parentClass) {
									if ($parentClass->hasProperty('table')) {
										$tableProp = $parentClass->getProperty('table');
										$tableProp->setAccessible(true);
										$tableProp->setValue($instance, $modelAttribute->table);
										break;
									}
									$parentClass = $parentClass->getParentClass();
								}
							}
						} catch (\Exception $e) {
							ArchetypeLogger::warning("Could not set table property on {$class}: " . $e->getMessage());
						}
					}

					// Store the base table name (without prefixes) for future reference
					$rawTableName = $modelAttribute->table;
					if (empty($rawTableName)) {
						// Use Eloquent convention to derive table name (pluralized snake case class name)
						$rawTableName = $this->guessTableName($class);
					}

					// Don't try to compute the full table name here
					// Let the model's getTable() method handle that when needed

					// Collect model information
					$model = [
						'class' => $class,
						'reflector' => $reflector,
						'instance' => $instance,
						'table' => $rawTableName, // Store the raw table name without prefixes
						'timestamps' => $modelAttribute->timestamps,
						'connection' => $modelAttribute->connection
					];

					$models[] = $model;
				} catch (\Exception $e) {
					// Log error but continue scanning
					ArchetypeLogger::error('Error scanning class ' . $class . ': ' . $e->getMessage());
				}
			}
		}

		return $models;
	}

	/**
	 * Derive table name from class name using Eloquent conventions
	 *
	 * @param string $class Fully qualified class name
	 * @return string Table name
	 */
	private function guessTableName(string $class): string {
		// Convert CamelCase to snake_case and pluralize
		// e.g., PostComment -> post_comments
		$parts = explode('\\', $class);
		$className = end($parts);

		// Convert camel case to snake case and pluralize
		return Str::snake(Str::pluralStudly($className));
	}
}