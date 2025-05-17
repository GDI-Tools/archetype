<?php
/**
 * Main Application class for Archetype Framework
 *
 * @package Archetype
 */

namespace Archetype;

use Archetype\Core\Config\AppConfig;
use Archetype\Core\Bootstrap\Bootstrapper;
use Archetype\Core\Controller\ControllerRegistry;
use Archetype\Core\Database\EloquentManager;
use Archetype\Core\Database\SchemaMigrator;
use Archetype\Logging\ArchetypeLogger;
use Illuminate\Database\Schema\Builder;

class Application {
	/**
	 * Configuration manager
	 *
	 * @var AppConfig
	 */
	private $config;

	/**
	 * Bootstrapper
	 *
	 * @var Bootstrapper
	 */
	private $bootstrapper;

	/**
	 * Schema migrator
	 *
	 * @var SchemaMigrator|null
	 */
	private $schemaMigrator = null;

	/**
	 * Whether the application has been bootstrapped
	 *
	 * @var bool
	 */
	private $bootstrapped = false;

	/**
	 * Whether automatic migrations are enabled
	 *
	 * @var bool
	 */
	private $autoMigrationsEnabled = true;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->config = new AppConfig();
		$this->bootstrapper = new Bootstrapper($this->config);
	}

	/**
	 * Configure the application
	 *
	 * @param array|string $context_paths Paths to scan for components
	 * @param string $plugin_slug Plugin slug (required)
	 * @param string|null $api_namespace API namespace (defaults to plugin_slug/v1 if null)
	 * @param array $exclude_folders Folders to exclude from scanning
	 * @param bool $use_default_exclusions Whether to use default exclusions
	 * @param int $deep_path_scan Maximum recursion depth for scanning
	 * @param array $logging_config Logging configuration
	 * @param array $database_config Database configuration
	 * @param bool $auto_migrations Whether to automatically run migrations
	 * @return self
	 */
	public function config(
		array|string $context_paths,
		string       $plugin_slug,
		?string      $api_namespace = null,
		array        $exclude_folders = [],
		bool         $use_default_exclusions = true,
		int          $deep_path_scan = 5,
		array        $logging_config = [],
		array        $database_config = [],
		bool         $auto_migrations = true
	): self {
		// Configure paths
		$this->config->set_context_paths($context_paths);
		$this->config->set_plugin_slug($plugin_slug);

		// Configure API namespace
		if ($api_namespace === null) {
			$this->config->set_api_namespace($plugin_slug);
		} else {
			$this->config->set_api_namespace($api_namespace);
		}

		// Configure exclusions
		$this->config->set_exclude_folders($exclude_folders, $use_default_exclusions);
		$this->config->set_deep_path_scan($deep_path_scan);

		// Configure logging
		if (!empty($logging_config)) {
			$this->config->set_logging_config($logging_config);
		}

		// Configure database
		if (!empty($database_config)) {
			$this->config->set_database_config($database_config);
			// Handle the table_prefix specifically if it exists
			if (isset($database_config['table_prefix'])) {
				$this->config->set_database_table_prefix($database_config['table_prefix']);
			}
		}

		// Configure auto migrations
		$this->autoMigrationsEnabled = $auto_migrations;

		$this->validate_config();
		$this->bootstrap();

		return $this;
	}

	/**
	 * Set context paths to scan
	 *
	 * @param string|array $context_paths Paths to scan for components
	 * @return self
	 */
	public function set_context_paths($context_paths): self {
		$this->config->set_context_paths($context_paths);
		return $this;
	}

	/**
	 * Add a context path to scan
	 *
	 * @param string $path Path to scan for components
	 * @return self
	 */
	public function add_context_path(string $path): self {
		$this->config->add_context_path($path);
		return $this;
	}

	/**
	 * Set plugin slug
	 *
	 * @param string $plugin_slug Plugin slug
	 * @return self
	 */
	public function set_plugin_slug(string $plugin_slug): self {
		$this->config->set_plugin_slug($plugin_slug);
		return $this;
	}

	/**
	 * Set API namespace
	 *
	 * @param string $api_namespace API namespace
	 * @return self
	 */
	public function set_api_namespace(string $api_namespace): self {
		$this->config->set_api_namespace($api_namespace);
		return $this;
	}

	/**
	 * Set folders to exclude from scanning
	 *
	 * @param array $exclude_folders Folders to exclude
	 * @param bool $use_default_exclusions Whether to use default exclusions
	 * @return self
	 */
	public function set_exclude_folders(array $exclude_folders, bool $use_default_exclusions = true): self {
		$this->config->set_exclude_folders($exclude_folders, $use_default_exclusions);
		return $this;
	}

	/**
	 * Add a folder to exclude from scanning
	 *
	 * @param string $folder Folder to exclude
	 * @return self
	 */
	public function add_exclude_folder(string $folder): self {
		$this->config->add_exclude_folder($folder);
		return $this;
	}

	/**
	 * Remove a folder from exclusion list
	 *
	 * @param string $folder Folder to include
	 * @return self
	 */
	public function remove_exclude_folder(string $folder): self {
		$this->config->remove_exclude_folder($folder);
		return $this;
	}

	/**
	 * Set whether to use default exclusions
	 *
	 * @param bool $use_default_exclusions Whether to use default exclusions
	 * @return self
	 */
	public function set_use_default_exclusions(bool $use_default_exclusions): self {
		$this->config->set_use_default_exclusions($use_default_exclusions);
		return $this;
	}

	/**
	 * Set maximum recursion depth for scanning
	 *
	 * @param int $deep_path_scan Maximum recursion depth
	 * @return self
	 */
	public function set_deep_path_scan(int $deep_path_scan): self {
		$this->config->set_deep_path_scan($deep_path_scan);
		return $this;
	}

	/**
	 * Set logging configuration
	 *
	 * @param array $logging_config Logging configuration
	 * @return self
	 */
	public function set_logging_config(array $logging_config): self {
		$this->config->set_logging_config($logging_config);
		return $this;
	}

	/**
	 * Enable logging
	 *
	 * @param bool $enabled Whether logging is enabled
	 * @return self
	 */
	public function enable_logging(bool $enabled = true): self {
		$this->config->enable_logging($enabled);
		return $this;
	}

	/**
	 * Set log level
	 *
	 * @param int $level Log level
	 * @return self
	 */
	public function set_log_level(int $level): self {
		$this->config->set_log_level($level);
		return $this;
	}

	/**
	 * Set log path
	 *
	 * @param string $path Path to log file
	 * @return self
	 */
	public function set_log_path(string $path): self {
		$this->config->set_log_path($path);
		return $this;
	}

	/**
	 * Set whether to use file logging
	 *
	 * @param bool $use_file Whether to use file logging
	 * @return self
	 */
	public function use_file_logging(bool $use_file = true): self {
		$this->config->use_file_logging($use_file);
		return $this;
	}

	/**
	 * Set database configuration
	 *
	 * @param array $database_config Database configuration
	 * @return self
	 */
	public function set_database_config(array $database_config): self {
		$this->config->set_database_config($database_config);
		return $this;
	}

	/**
	 * Set database driver
	 *
	 * @param string $driver Database driver
	 * @return self
	 */
	public function set_database_driver(string $driver): self {
		$this->config->set_database_driver($driver);
		return $this;
	}

	/**
	 * Set database host
	 *
	 * @param string $host Database host
	 * @return self
	 */
	public function set_database_host(string $host): self {
		$this->config->set_database_host($host);
		return $this;
	}

	/**
	 * Set database name
	 *
	 * @param string $database Database name
	 * @return self
	 */
	public function set_database_name(string $database): self {
		$this->config->set_database_name($database);
		return $this;
	}

	/**
	 * Set database username
	 *
	 * @param string $username Database username
	 * @return self
	 */
	public function set_database_username(string $username): self {
		$this->config->set_database_username($username);
		return $this;
	}

	/**
	 * Set database password
	 *
	 * @param string $password Database password
	 * @return self
	 */
	public function set_database_password(string $password): self {
		$this->config->set_database_password($password);
		return $this;
	}

	/**
	 * Set database charset
	 *
	 * @param string $charset Database charset
	 * @return self
	 */
	public function set_database_charset(string $charset): self {
		$this->config->set_database_charset($charset);
		return $this;
	}

	/**
	 * Set database collation
	 *
	 * @param string $collation Database collation
	 * @return self
	 */
	public function set_database_collation(string $collation): self {
		$this->config->set_database_collation($collation);
		return $this;
	}

	/**
	 * Set database table prefix
	 *
	 * @param string $prefix Database table prefix
	 * @return self
	 */
	public function set_database_prefix(string $prefix): self {
		$this->config->set_database_prefix($prefix);
		return $this;
	}

	/**
	 * Set database table prefix (additional prefix)
	 *
	 * @param string $table_prefix Additional table prefix
	 * @return self
	 */
	public function set_database_table_prefix(string $table_prefix): self {
		$this->config->set_database_table_prefix($table_prefix);
		return $this;
	}

	/**
	 * Set whether to run migrations automatically
	 *
	 * @param bool $enabled Whether auto migrations are enabled
	 * @return self
	 */
	public function enable_auto_migrations(bool $enabled = true): self {
		$this->autoMigrationsEnabled = $enabled;
		return $this;
	}

	/**
	 * Initialize the application
	 *
	 * @return self
	 */
	public function init(): self {
		$this->validate_config();
		$this->bootstrap();
		return $this;
	}

	/**
	 * Bootstrap the application components
	 *
	 * @return void
	 */
	private function bootstrap(): void {
		if ($this->bootstrapped) {
			return;
		}

		$this->bootstrapper->bootstrap();
		$this->bootstrapped = true;

		// Initialize schema migrator if Eloquent is available
		$eloquentManager = $this->get_eloquent_manager();
		if ($eloquentManager && $eloquentManager->isInitialized()) {
			$this->initSchemaMigrator();

			// Run auto migrations if enabled
			if ($this->autoMigrationsEnabled) {
				$this->runAutoMigrations();
			}
		}
	}

	/**
	 * Validate configuration options
	 *
	 * @return void
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	private function validate_config(): void {
		$this->config->validate();
	}

	/**
	 * Get the controller registry
	 *
	 * @return ControllerRegistry
	 */
	public function get_controller_registry(): ControllerRegistry {
		return $this->bootstrapper->get_controller_registry();
	}

	/**
	 * Get the discovered models
	 *
	 * @return array
	 */
	public function get_models(): array {
		return $this->bootstrapper->get_models();
	}

	/**
	 * Get the Eloquent manager
	 *
	 * @return EloquentManager|null
	 */
	public function get_eloquent_manager(): ?EloquentManager {
		return $this->bootstrapper->get_eloquent_manager();
	}

	/**
	 * Get the database schema builder
	 *
	 * @return Builder|null
	 */
	public function get_schema_builder(): ?Builder {
		$eloquent_manager = $this->get_eloquent_manager();
		return $eloquent_manager ? $eloquent_manager->getSchemaBuilder() : null;
	}

	/**
	 * Get configuration options
	 *
	 * @return array
	 */
	public function get_config(): array {
		return $this->config->get_all();
	}

	/**
	 * Initialize the Schema Migrator
	 *
	 * @return void
	 */
	private function initSchemaMigrator(): void {
		$schemaBuilder = $this->get_schema_builder();
		if (!$schemaBuilder) {
			return;
		}

		$tablePrefix = $this->config->get_database_table_prefix();

		$this->schemaMigrator = new SchemaMigrator(
			$schemaBuilder,
			$tablePrefix
		);
	}

	/**
	 * Get the schema migrator instance
	 *
	 * @return SchemaMigrator|null
	 */
	public function get_schema_migrator(): ?SchemaMigrator {
		return $this->schemaMigrator;
	}

	/**
	 * Run automatic database migrations
	 *
	 * @return array Migration results
	 */
	private function runAutoMigrations(): array {
		$results = [];

		try {
			if (!$this->schemaMigrator) {
				ArchetypeLogger::warning("Schema migrator not initialized, skipping auto migrations");
				return $results;
			}

			// Get all models that need migration
			$models = $this->get_models();
			$modelInstances = [];

			// Create instances of each model
			foreach ($models as $model) {
				if (isset($model['instance'])) {
					$modelInstances[] = $model['instance'];
				}
			}

			// Check if any models need migration
			$modelsNeedingMigration = $this->schemaMigrator->getModelsNeedingMigration($modelInstances);
			if (empty($modelsNeedingMigration)) {
				return $results;
			}

			ArchetypeLogger::info("Found " . count($modelsNeedingMigration) . " models needing migration");

			// Run migrations
			$results = $this->schemaMigrator->migrateAll(array_values($modelsNeedingMigration));
			// Log results
			foreach ($results as $modelClassIndex => $result) {
				list($success, $log) = $result;

				if ($success) {
					ArchetypeLogger::info("Successfully migrated model: {$modelClassIndex}" );
				} else {
					ArchetypeLogger::error("Failed to migrate model: {$modelClassIndex}");
					ArchetypeLogger::error($log);
				}
			}
		} catch (\Exception $e) {
			ArchetypeLogger::error("Error running auto migrations: " . $e->getMessage());
		}

		return $results;
	}

	/**
	 * Run migrations manually
	 *
	 * @return array Migration results
	 */
	public function run_migrations(): array {
		if (!$this->schemaMigrator) {
			ArchetypeLogger::warning("Schema migrator not initialized, initializing now");
			$this->initSchemaMigrator();

			if (!$this->schemaMigrator) {
				ArchetypeLogger::error("Failed to initialize schema migrator");
				return [];
			}
		}

		// Get all models
		$models = $this->get_models();
		$modelInstances = [];

		// Create instances of each model
		foreach ($models as $model) {
			if (isset($model['instance'])) {
				$modelInstances[] = $model['instance'];
			}
		}

		// Run migrations for all models
		return $this->schemaMigrator->migrateAll($modelInstances);
	}

	/**
	 * Check if any models need migration
	 *
	 * @return array Models needing migration
	 */
	public function get_models_needing_migration(): array {
		if (!$this->schemaMigrator) {
			ArchetypeLogger::warning("Schema migrator not initialized, initializing now");
			$this->initSchemaMigrator();

			if (!$this->schemaMigrator) {
				ArchetypeLogger::error("Failed to initialize schema migrator");
				return [];
			}
		}

		// Get all models
		$models = $this->get_models();
		$modelInstances = [];

		// Create instances of each model
		foreach ($models as $model) {
			if (isset($model['instance'])) {
				$modelInstances[] = $model['instance'];
			}
		}

		// Check which models need migration
		return $this->schemaMigrator->getModelsNeedingMigration($modelInstances);
	}
}