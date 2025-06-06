<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * ControllerRegistry is responsible for registering discovered controllers
 * with the WordPress REST API.
 *
 * @package Archetype\Core
 */

namespace Archetype\Core\Controller;

use Archetype\Api\ApiResponse;
use Archetype\Exceptions\ApiException;
use Archetype\Logging\ArchetypeLogger;
use ReflectionMethod;
use Throwable;
use WP_REST_Request;
use WP_Error;

class ControllerRegistry {
    /**
     * REST API namespace
     *
     * @var string
     */
    private string $namespace;

    /**
     * Constructor
     *
     * @param string $namespace REST API namespace
     */
    public function __construct(string $namespace) {
        $this->namespace = $namespace;
    }

    /**
     * Register controllers with WordPress REST API
     *
     * @param array $controllers Array of controller information
     * @return void
     */
    public function registerControllers(array $controllers): void {
        // Register the hook to initialize the REST API routes
        add_action('rest_api_init', function() use ($controllers) {
            foreach ($controllers as $controller) {
                $this->registerController($controller);
            }
        });
    }

    /**
     * Register a single controller
     *
     * @param array $controller Controller information
     * @return void
     */
    private function registerController(array $controller): void {
        // Create instance of the controller
        $controllerClass = $controller['class'];
        $controllerInstance = new $controllerClass();

        // Get route prefix for this controller
        $prefix = $controller['prefix'] ? '/' . ltrim($controller['prefix'], '/') : '';

        // Register each route
        foreach ($controller['routes'] as $route) {
            $this->registerRoute($controllerInstance, $prefix, $route);
        }

    }

    /**
     * Register a single route
     *
     * @param object $controller Controller instance
     * @param string $prefix Route prefix
     * @param array $route Route information
     * @return void
     */
    private function registerRoute(object $controller, string $prefix, array $route): void {
        $method = $route['method'];
        $httpMethod = strtoupper($route['http_method']);
        $path = $this->convertPathParameters($route['path']);
        // Fix path formatting - ensure no double slashes
        $path = $prefix ? rtrim($prefix, '/') . '/' . ltrim($path, '/') : ltrim($path, '/');
        // IMPORTANT: WordPress expects the path WITHOUT a leading slash
        $path = ltrim($path, '/');
        $permissions = $route['permissions'] ?? [];
        $args = $this->generateRouteArgs($controller, $method);

        // Register the route with WordPress
        register_rest_route($this->namespace, $path, [
            'methods' => $httpMethod,
            'callback' => fn($request) => $this->wrap_callback($request, [$controller, $method]),
            'permission_callback' => function(WP_REST_Request $request) use ($permissions) {
                return $this->checkPermissions($permissions, $request);
            },
            'args' => $args
        ]);
    }

    private function wrap_callback($request, callable $callback) {
        try {
            if (!is_callable($callback)) {
                return ApiResponse::error(error_code: 'invalid_callback', error_message: 'Invalid callback function.');
            }

            $response = call_user_func($callback, $request);

            if($response instanceof WP_REST_Response || $response instanceof WP_Error) {
                return $response;
            }

            return ApiResponse::success($response);

        }catch (ApiException $exception) {
            return  $exception->toWpError();
        } catch (Throwable $exception){
            return ApiResponse::server_error(message: $exception->getMessage());
        }
    }

    /**
     * Generate route arguments from method parameters
     *
     * @param object $controller Controller instance
     * @param string $methodName Method name
     * @return array Route arguments
     */
    private function generateRouteArgs(object $controller, string $methodName): array {
        $args = [];

        try {
            $reflectionMethod = new ReflectionMethod($controller, $methodName);
            $parameters = $reflectionMethod->getParameters();

            foreach ($parameters as $parameter) {
                // Skip the request parameter
                if ($parameter->getName() === 'request' ||
                    ($parameter->getType() && $parameter->getType()->getName() === 'WP_REST_Request')) {
                    continue;
                }

                // Add parameter to args
                $args[$parameter->getName()] = [
                    'required' => !$parameter->isOptional(),
                    'default' => $parameter->isOptional() ? $parameter->getDefaultValue() : null,
                    // You could add validation based on param type
                    'validate_callback' => function($value, $request, $param) {
                        return true; // Default validation - can be enhanced
                    },
                    'sanitize_callback' => function($value, $request, $param) {
                        return sanitize_text_field($value); // Basic sanitization
                    }
                ];
            }
        } catch (\Exception $e) {
	        ArchetypeLogger::error('Archetype: Error generating route args: ' . $e->getMessage());
        }

        return $args;
    }

    /**
     * Convert path parameters from {param} format to WordPress (?P<param>\w+) format
     *
     * @param string $path Path with {parameter} format
     * @return string Path with WordPress regex pattern format
     */
    private function convertPathParameters(string $path): string {
        // Match {parameter} pattern and replace with (?P<parameter>\w+)
        return preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>\w+)', $path);
    }

    /**
     * Handle the incoming request
     *
     * @param object $controller Controller instance
     * @param string $methodName Method name to call
     * @param WP_REST_Request $request WordPress request object
     * @return mixed Response from the controller method
     */
    private function handleRequest(object $controller, string $methodName, \WP_REST_Request $request) {
        try {
            // Simple direct call passing the request object
            return call_user_func([$controller, $methodName], $request);
        } catch (\Exception $e) {
	        ArchetypeLogger::error('Archetype error handling request: ' . $e->getMessage());

            return new WP_Error(
                'archetype_controller_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    /**
     * Check if current user has the required permissions
     *
     * @param array $permissions Required permissions (class::method format)
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permissions are granted, WP_Error otherwise
     */
    private function checkPermissions(array $permissions, WP_REST_Request $request) {
        // If no permissions are required, allow access
        if (empty($permissions)) {
            return true;
        }

        // Check each permission
        foreach ($permissions as $permission) {
            // Parse permission string (format: 'ClassName::methodName')
            if (preg_match('/^([^:]+)::([^:]+)$/', $permission, $matches)) {
                $className = $matches[1];
                $methodName = $matches[2];

                // Check if class and method exist
                if (!class_exists($className)) {
	                ArchetypeLogger::warning("Archetype: Permission class {$className} not found");
                    continue;
                }

                if (!method_exists($className, $methodName)) {
	                ArchetypeLogger::warning("Archetype: Permission method {$methodName} not found in class {$className}");
                    continue;
                }

                // Create instance of permission class
                $instance = new $className();

                // Call permission method
                $result = call_user_func([$instance, $methodName], $request);

                // If permission check fails, return the result (false or WP_Error)
                if ($result !== true) {
                    if ($result instanceof WP_Error) {
                        return $result;
                    }

                    return new WP_Error(
                        'rest_forbidden',
                        __('Sorry, you are not allowed to do that.'),
                        ['status' => rest_authorization_required_code()]
                    );
                }
            } else {
                // If permission format is incorrect, log error and continue
	            ArchetypeLogger::error("Archetype: Invalid permission format: {$permission}");
            }
        }

        // All permission checks passed
        return true;
    }
}