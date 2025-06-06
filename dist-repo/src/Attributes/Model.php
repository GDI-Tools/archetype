<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * Model attribute is used to mark classes that should be registered
 * as database models.
 *
 * @package Archetype\Attributes
 */

namespace Archetype\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Model {
	/**
	 * Table name (optional, will be auto-derived from class name if not provided)
	 *
	 * @var string|null
	 */
	public ?string $table;

	/**
	 * Whether to add created_at and updated_at timestamps
	 *
	 * @var bool
	 */
	public bool $timestamps;

	/**
	 * Database connection to use
	 *
	 * @var string
	 */
	public string $connection;

	/**
	 * Constructor
	 *
	 * @param string|null $table Table name (optional)
	 * @param bool $timestamps Whether to use timestamps
	 * @param string $connection Database connection name
	 */
	public function __construct(?string $table = null, bool $timestamps = true, string $connection = 'default') {
		$this->table = $table;
		$this->timestamps = $timestamps;
		$this->connection = $connection;
	}
}