<?php
/**
 * Application configuration manager
 *
 * @package Archetype\Core\Config
 */

namespace Archetype\Core\Config;

use Archetype\Logging\ArchetypeLogger;

class AppConfig {
	/**
	 * Default excluded folders
	 *
	 * @var array
	 */
	private $default_exclude_folders = [
		// Package management
		'node_modules',
		'vendor',
		'bower_components',
		'packages',
		'composer',
		'yarn-cache',

		// Build and output directories
		'build',
		'dist',
		'assets/dist',
		'assets/build',
		'public/build',
		'public/dist',

		// Development/testing environments
		'tests',
		'test',
		'testing',
		'coverage',
		'__tests__',
		'__mocks__',
		'spec',

		// Documentation
		'docs',
		'doc',
		'documentation',
		'wiki',

		// Version control
		'.git',
		'.svn',
		'.hg',
		'.github',
		'.gitlab',
		'.circleci',

		// Editor/IDE configs
		'.vscode',
		'.idea',
		'.vs',
		'.atom',
		'.sublime',

		// Cache and temporary files
		'cache',
		'temp',
		'tmp',
		'log',
		'logs',

		// WordPress specific
		'languages',
		'i18n',
		'l10n',
		'uploads',

		// Miscellaneous
		'examples',
		'fixtures',
		'backup',
		'backups',
		'db-backups',
		'storage'
	];

	/**
	 * Configuration options
	 *
	 * @var array
	 */
	private $config = [
		'context_paths' => [],
		'plugin_slug' => '',
		'api_namespace' => '',
		'exclude_folders' => [],
		'deep_path_scan' => 5,
		'use_default_exclusions' => true,
		'logging' => [
			'enabled' => true,
			'level' => 6, // INFO
			'path' => null,
			'use_file' => true
		],
		'database' => [
			'driver' => 'mysql',
			'host' => null,  // Will use DB_HOST constant
			'database' => null, // Will use DB_NAME constant
			'username' => null, // Will use DB_USER constant
			'password' => null, // Will use DB_PASSWORD constant
			'charset' => 'utf8mb4',
			'collation' => 'utf8mb4_unicode_ci',
			'prefix' => null,  // Will use $wpdb->prefix
			'table_prefix' => '' // Additional prefix for table names
		]
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->config['exclude_folders'] = $this->default_exclude_folders;
	}

	/**
	 * Get all configuration
	 *
	 * @return array
	 */
	public function get_all(): array {
		return $this->config;
	}

	/**
	 * Get a configuration value
	 *
	 * @param string $key Configuration key
	 * @param mixed $default Default value if key doesn't exist
	 * @return mixed
	 */
	public function get(string $key, $default = null) {
		$parts = explode('.', $key);
		$config = $this->config;

		foreach ($parts as $part) {
			if (!isset($config[$part])) {
				return $default;
			}
			$config = $config[$part];
		}

		return $config;
	}

	/**
	 * Set a configuration value
	 *
	 * @param string $key Configuration key
	 * @param mixed $value Configuration value
	 * @return self
	 */
	public function set(string $key, $value): self {
		$parts = explode('.', $key);
		$config = &$this->config;

		foreach ($parts as $i => $part) {
			if ($i === count($parts) - 1) {
				$config[$part] = $value;
			} else {
				if (!isset($config[$part]) || !is_array($config[$part])) {
					$config[$part] = [];
				}
				$config = &$config[$part];
			}
		}

		return $this;
	}

	/**
	 * Set context paths to scan
	 *
	 * @param string|array $context_paths Paths to scan for components
	 * @return self
	 */
	public function set_context_paths($context_paths): self {
		if (is_string($context_paths)) {
			$this->config['context_paths'] = [$context_paths];
		} elseif (is_array($context_paths)) {
			$this->config['context_paths'] = $context_paths;
		}

		return $this;
	}

	/**
	 * Add a context path to scan
	 *
	 * @param string $path Path to scan for components
	 * @return self
	 */
	public function add_context_path(string $path): self {
		if (!in_array($path, $this->config['context_paths'])) {
			$this->config['context_paths'][] = $path;
		}

		return $this;
	}

	/**
	 * Set plugin slug
	 *
	 * @param string $plugin_slug Plugin slug
	 * @return self
	 */
	public function set_plugin_slug(string $plugin_slug): self {
		$this->config['plugin_slug'] = $plugin_slug;

		// If API namespace is not set, set it based on plugin slug
		if (empty($this->config['api_namespace'])) {
			$this->set_api_namespace($plugin_slug . '/v1');
		}

		return $this;
	}

	/**
	 * Set API namespace
	 *
	 * @param string $api_namespace API namespace
	 * @return self
	 */
	public function set_api_namespace(string $api_namespace): self {
		$this->config['api_namespace'] = $api_namespace;
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
		$this->config['use_default_exclusions'] = $use_default_exclusions;

		if ($use_default_exclusions) {
			$this->config['exclude_folders'] = array_merge(
				$this->default_exclude_folders,
				$exclude_folders
			);
		} else {
			$this->config['exclude_folders'] = $exclude_folders;
		}

		return $this;
	}

	/**
	 * Add a folder to exclude from scanning
	 *
	 * @param string $folder Folder to exclude
	 * @return self
	 */
	public function add_exclude_folder(string $folder): self {
		if (!in_array($folder, $this->config['exclude_folders'])) {
			$this->config['exclude_folders'][] = $folder;
		}

		return $this;
	}

	/**
	 * Remove a folder from exclusion list
	 *
	 * @param string $folder Folder to include
	 * @return self
	 */
	public function remove_exclude_folder(string $folder): self {
		$key = array_search($folder, $this->config['exclude_folders']);
		if ($key !== false) {
			unset($this->config['exclude_folders'][$key]);
			$this->config['exclude_folders'] = array_values($this->config['exclude_folders']);
		}

		return $this;
	}

	/**
	 * Set whether to use default exclusions
	 *
	 * @param bool $use_default_exclusions Whether to use default exclusions
	 * @return self
	 */
	public function set_use_default_exclusions(bool $use_default_exclusions): self {
		$this->config['use_default_exclusions'] = $use_default_exclusions;

		if ($use_default_exclusions) {
			// Ensure default folders are included in the exclusion list
			$custom_excludes = array_diff($this->config['exclude_folders'], $this->default_exclude_folders);
			$this->config['exclude_folders'] = array_merge($this->default_exclude_folders, $custom_excludes);
		} else {
			// Remove default folders from exclusion list
			$this->config['exclude_folders'] = array_diff(
				$this->config['exclude_folders'],
				$this->default_exclude_folders
			);
		}

		return $this;
	}

	/**
	 * Set maximum recursion depth for scanning
	 *
	 * @param int $deep_path_scan Maximum recursion depth
	 * @return self
	 */
	public function set_deep_path_scan(int $deep_path_scan): self {
		$this->config['deep_path_scan'] = $deep_path_scan;
		return $this;
	}

	/**
	 * Set logging configuration
	 *
	 * @param array $logging_config Logging configuration
	 * @return self
	 */
	public function set_logging_config(array $logging_config): self {
		// Merge with defaults
		$this->config['logging'] = array_merge($this->config['logging'], $logging_config);
		return $this;
	}

	/**
	 * Enable logging
	 *
	 * @param bool $enabled Whether logging is enabled
	 * @return self
	 */
	public function enable_logging(bool $enabled = true): self {
		$this->config['logging']['enabled'] = $enabled;
		return $this;
	}

	/**
	 * Set log level
	 *
	 * @param int $level Log level
	 * @return self
	 */
	public function set_log_level(int $level): self {
		$this->config['logging']['level'] = $level;
		return $this;
	}

	/**
	 * Set log path
	 *
	 * @param string $path Path to log file
	 * @return self
	 */
	public function set_log_path(string $path): self {
		$this->config['logging']['path'] = $path;
		return $this;
	}

	/**
	 * Set whether to use file logging
	 *
	 * @param bool $use_file Whether to use file logging
	 * @return self
	 */
	public function use_file_logging(bool $use_file = true): self {
		$this->config['logging']['use_file'] = $use_file;
		return $this;
	}

	/**
	 * Set database configuration
	 *
	 * @param array $database_config Database configuration
	 * @return self
	 */
	public function set_database_config(array $database_config): self {
		// Merge with defaults
		$this->config['database'] = array_merge($this->config['database'], $database_config);
		return $this;
	}

	/**
	 * Set database driver
	 *
	 * @param string $driver Database driver
	 * @return self
	 */
	public function set_database_driver(string $driver): self {
		$this->config['database']['driver'] = $driver;
		return $this;
	}

	/**
	 * Set database host
	 *
	 * @param string $host Database host
	 * @return self
	 */
	public function set_database_host(string $host): self {
		$this->config['database']['host'] = $host;
		return $this;
	}

	/**
	 * Set database name
	 *
	 * @param string $database Database name
	 * @return self
	 */
	public function set_database_name(string $database): self {
		$this->config['database']['database'] = $database;
		return $this;
	}

	/**
	 * Set database username
	 *
	 * @param string $username Database username
	 * @return self
	 */
	public function set_database_username(string $username): self {
		$this->config['database']['username'] = $username;
		return $this;
	}

	/**
	 * Set database password
	 *
	 * @param string $password Database password
	 * @return self
	 */
	public function set_database_password(string $password): self {
		$this->config['database']['password'] = $password;
		return $this;
	}

	/**
	 * Set database charset
	 *
	 * @param string $charset Database charset
	 * @return self
	 */
	public function set_database_charset(string $charset): self {
		$this->config['database']['charset'] = $charset;
		return $this;
	}

	/**
	 * Set database collation
	 *
	 * @param string $collation Database collation
	 * @return self
	 */
	public function set_database_collation(string $collation): self {
		$this->config['database']['collation'] = $collation;
		return $this;
	}

	/**
	 * Set database table prefix
	 *
	 * @param string $prefix Database table prefix
	 * @return self
	 */
	public function set_database_prefix(string $prefix): self {
		$this->config['database']['prefix'] = $prefix;
		return $this;
	}

	/**
	 * Set additional table prefix
	 *
	 * @param string $table_prefix Additional table prefix
	 * @return self
	 */
	public function set_database_table_prefix(string $table_prefix): self {
		$this->config['database']['table_prefix'] = $table_prefix;
		return $this;
	}

	/**
	 * Get database table prefix
	 *
	 * @return string|null
	 */
	public function get_database_prefix(): ?string {
		return $this->config['database']['prefix'];
	}

	/**
	 * Get additional table prefix
	 *
	 * @return string
	 */
	public function get_database_table_prefix(): string {
		return $this->config['database']['table_prefix'] ?? '';
	}

	/**
	 * Get default excluded folders
	 *
	 * @return array
	 */
	public function get_default_excluded_folders(): array {
		return $this->default_exclude_folders;
	}

	/**
	 * Validate configuration
	 *
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function validate(): void {
		if (empty($this->config['plugin_slug'])) {
			throw new \InvalidArgumentException('plugin_slug must not be empty');
		}

		if (!is_string($this->config['plugin_slug'])) {
			throw new \InvalidArgumentException('plugin_slug must be a string');
		}

		if (!is_string($this->config['api_namespace'])) {
			throw new \InvalidArgumentException('api_namespace must be a string');
		}

		if (empty($this->config['context_paths'])) {
			throw new \InvalidArgumentException('context_paths must not be empty');
		}

		foreach ($this->config['context_paths'] as $path) {
			if (!is_dir($path)) {
				throw new \InvalidArgumentException("Directory not found: {$path}");
			}
		}

		// Validate database config
		if (isset($this->config['database'])) {
			if (!is_string($this->config['database']['driver'])) {
				throw new \InvalidArgumentException('database.driver must be a string');
			}

			// Check for valid driver
			$valid_drivers = ['mysql', 'sqlite', 'pgsql', 'sqlsrv'];
			if (!in_array($this->config['database']['driver'], $valid_drivers)) {
				throw new \InvalidArgumentException('database.driver must be one of: ' . implode(', ', $valid_drivers));
			}
		}
	}
}