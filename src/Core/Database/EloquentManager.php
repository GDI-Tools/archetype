<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * EloquentManager sets up and manages the Eloquent ORM connection.
 *
 * @package Archetype\Core\Database
 */

namespace Archetype\Core\Database;

use Archetype\Logging\ArchetypeLogger;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;

class EloquentManager {
	/**
	 * Illuminate Container
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Event Dispatcher
	 *
	 * @var Dispatcher
	 */
	private $dispatcher;

	/**
	 * Database Manager
	 *
	 * @var Capsule
	 */
	private $capsule;

	/**
	 * Whether Eloquent has been initialized
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Database configuration
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Constructor
	 *
	 * @param array $config Database configuration
	 */
	public function __construct(array $config = []) {
		$this->config = $config;
		$this->setupContainer();
		$this->setupEventDispatcher();
		$this->setupDatabase($config);
	}

	/**
	 * Set up the Container instance
	 *
	 * @return void
	 */
	private function setupContainer(): void {
		$this->container = new Container();
	}

	/**
	 * Set up the Event Dispatcher
	 *
	 * @return void
	 */
	private function setupEventDispatcher(): void {
		$this->dispatcher = new Dispatcher($this->container);
	}

	/**
	 * Set up the Database connection
	 *
	 * @param array $config Database configuration
	 * @return void
	 */
	private function setupDatabase(array $config): void {
		try {
			global $wpdb;

			// Create new Capsule manager
			$capsule = new Capsule();

			// Get the prefix and table_prefix from config
			$prefix = $config['prefix'] ?? $wpdb->prefix;
			$tablePrefix = $config['table_prefix'] ?? '';

			// Make sure we have a valid host configuration
			$host = $config['host'] ?? DB_HOST;
			// Ensure host is a string and not an empty array
			if (empty($host) || (is_array($host) && empty($host))) {
				// Use the WordPress defined constant as fallback
				$host = defined('DB_HOST') ? DB_HOST : 'localhost';
			}

			// Configure the default connection with proper host
			$connectionConfig = [
				'driver'    => $config['driver'] ?? 'mysql',
				'host'      => $host,
				'database'  => $config['database'] ?? DB_NAME,
				'username'  => $config['username'] ?? DB_USER,
				'password'  => $config['password'] ?? DB_PASSWORD,
				'charset'   => $config['charset'] ?? 'utf8mb4',
				'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
				'prefix'    => $prefix,
				'table_prefix' => $tablePrefix,
			];

			// Add the connection to the capsule
			$capsule->addConnection($connectionConfig);

			// Configure event dispatcher
			$capsule->setEventDispatcher($this->dispatcher);

			// Make this capsule instance globally available
			$capsule->setAsGlobal();

			// Bootstrap Eloquent
			$capsule->bootEloquent();

			$this->capsule = $capsule;
			$this->initialized = true;

		} catch (\Exception $e) {
			ArchetypeLogger::error('Failed to initialize Eloquent ORM: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Get the Capsule Manager instance
	 *
	 * @return Capsule
	 */
	public function getCapsule(): Capsule {
		return $this->capsule;
	}

	/**
	 * Get the default database connection
	 *
	 * @return Connection
	 */
	public function getConnection() {
		return $this->capsule->getConnection();
	}

	/**
	 * Get the schema builder for the default connection
	 *
	 * @return Builder
	 */
	public function getSchemaBuilder() {
		return $this->getConnection()->getSchemaBuilder();
	}

	/**
	 * Check if Eloquent has been initialized
	 *
	 * @return bool
	 */
	public function isInitialized(): bool {
		return $this->initialized;
	}

	/**
	 * Get table prefix from configuration
	 *
	 * @return string
	 */
	public function getTablePrefix(): string {
		return $this->config['table_prefix'] ?? '';
	}

	/**
	 * Get main prefix from configuration
	 *
	 * @return string|null
	 */
	public function getPrefix(): ?string {
		return $this->config['prefix'] ?? null;
	}

	/**
	 * Get the complete configuration
	 *
	 * @return array
	 */
	public function getConfig(): array {
		return $this->config;
	}
}