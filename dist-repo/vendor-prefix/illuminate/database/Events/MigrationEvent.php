<?php

namespace Archetype\Vendor\Illuminate\Database\Events;

use Archetype\Vendor\Illuminate\Contracts\Database\Events\MigrationEvent as MigrationEventContract;
use Archetype\Vendor\Illuminate\Database\Migrations\Migration;
abstract class MigrationEvent implements MigrationEventContract
{
    /**
     * A migration instance.
     *
     * @var \Illuminate\Database\Migrations\Migration
     */
    public $migration;
    /**
     * The migration method that was called.
     *
     * @var string
     */
    public $method;
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Migrations\Migration  $migration
     * @param  string  $method
     */
    public function __construct(Migration $migration, $method)
    {
        $this->method = $method;
        $this->migration = $migration;
    }
}
