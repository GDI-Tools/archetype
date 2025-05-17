<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * ControllerScanner is responsible for scanning directories and discovering
 * controller classes marked with the RestController attribute.
 *
 * @package Archetype\Core\Scanner
 */

namespace Archetype\Core\Scanner;

use Archetype\Attributes\Route;
use ReflectionMethod;
use ReflectionClass;
use Archetype\Attributes\RestController;

class ControllerScanner extends BaseComponentScanner {
    /**
     * Scan directories for classes with RestController attribute
     *
     * @param array $paths Directories to scan
     * @param array $excluded_folders Folders to exclude from scanning
     * @param int $max_depth Maximum recursion depth (0 for unlimited)
     * @return array Array of controller class information
     */
    public function scan(array $paths, array $excluded_folders = [], int $max_depth = 0): array {
        $controllers = [];

        foreach ($paths as $path) {
            $classes = $this->findClassesInDirectory($path, $excluded_folders, $max_depth);

            foreach ($classes as $class) {
                try {
                    $reflector = new ReflectionClass($class);

                    // Skip abstract classes
                    if ($reflector->isAbstract()) {
                        continue;
                    }

                    // Check if class has RestController attribute
                    $attributes = $reflector->getAttributes(RestController::class);

                    if (empty($attributes)) {
                        continue;
                    }

                    // Get the RestController attribute instance
                    $controllerAttribute = $attributes[0]->newInstance();

                    // Collect controller information
                    $controller = [
                        'class' => $class,
                        'reflector' => $reflector,
                        'prefix' => $controllerAttribute->prefix,
                        'routes' => $this->findRoutes($reflector)
                    ];

                    $controllers[] = $controller;
                } catch (\Exception $e) {
                    // Log error but continue scanning
                    error_log('Archetype: Error scanning class ' . $class . ': ' . $e->getMessage());
                }
            }
        }

        return $controllers;
    }

    /**
     * Find all routes defined in a controller class
     *
     * @param ReflectionClass $reflector Class reflector
     * @return array Array of route information
     */
    private function findRoutes(ReflectionClass $reflector): array {
        $routes = [];
        $methods = $reflector->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Check if method has Route attribute
            $attributes = $method->getAttributes(Route::class);

            if (empty($attributes)) {
                continue;
            }

            foreach ($attributes as $attribute) {
                $routeAttribute = $attribute->newInstance();

                $routes[] = [
                    'method' => $method->getName(),
                    'http_method' => $routeAttribute->method,
                    'path' => $routeAttribute->path,
                    'permissions' => $routeAttribute->permissions,
                    'reflector' => $method
                ];
            }
        }

        return $routes;
    }
}