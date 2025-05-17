<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * Route attribute is used to mark methods that should be registered
 * as REST API endpoints.
 *
 * @package Archetype\Attributes
 */

namespace Archetype\Attributes;

use Archetype\Api\HttpMethod;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route {
    /**
     * HTTP method
     *
     * @var string
     */
    public string $method;

    /**
     * Route path
     *
     * @var string
     */
    public string $path;

    /**
     * Required permissions
     * Array of strings in format 'ClassName::methodName'
     *
     * @var array
     */
    public array $permissions;

    /**
     * Constructor
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path Route path
     * @param array $permissions Required permissions
     */
    public function __construct(string $method = HttpMethod::GET, string $path = '/', array $permissions = []) {
        $this->method = $method;
        $this->path = $path;
        $this->permissions = $permissions;
    }
}