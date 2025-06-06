<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * RestController attribute is used to mark classes that should be registered
 * as REST API controllers.
 *
 * @package Archetype\Attributes
 */

namespace Archetype\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RestController {
    /**
     * Prefix for all routes in this controller
     *
     * @var string
     */
    public string $prefix;

    /**
     * Constructor
     *
     * @param string $prefix Route prefix for this controller
     */
    public function __construct(string $prefix = '') {
        $this->prefix = $prefix;
    }
}