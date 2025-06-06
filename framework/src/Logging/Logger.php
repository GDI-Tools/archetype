<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * Logger provides logging capabilities for plugins using the framework.
 *
 * @package Archetype\Logging
 */

namespace Archetype\Logging;

/**
 * General-purpose logger for plugins
 */
class Logger extends LoggerBase {
	/**
	 * Plugin slug for namespacing logs
	 *
	 * @var string
	 */
	protected static $namespace = 'plugin';

	/**
	 * Whether this logger is initialized
	 *
	 * @var bool
	 */
	protected static $initialized = false;

	/**
	 * Current log level
	 *
	 * @var int
	 */
	protected static $level = self::INFO;

	/**
	 * File handler for this logger
	 *
	 * @var callable
	 */
	protected static $fileHandler = null;

	/**
	 * Fallback handler for this logger
	 *
	 * @var callable
	 */
	protected static $fallbackHandler = null;

	/**
	 * Initialize the logger
	 *
	 * @param string $plugin_slug Plugin slug for namespacing logs
	 * @param string|null $log_path Path to log file or directory, or null for default
	 * @param int $level Log level
	 * @param bool $use_file Whether to use file logging
	 * @return void
	 */
	public static function init(string $plugin_slug, ?string $log_path = null, int $level = self::INFO, bool $use_file = true): void {
		static::$namespace = $plugin_slug;

		// First let's initialize ourselves
		parent::init($plugin_slug, $log_path, $level, $use_file);

		// Then initialize ArchetypeLogger with the same parameters but without affecting our handler
		ArchetypeLogger::init('archetype', $log_path, $level, $use_file);
	}

	/**
	 * Get log file path for plugin
	 *
	 * @param string $namespace Namespace for logs (plugin slug)
	 * @param string|null $log_path Path to log file or directory, or null for default
	 * @return string|null Log file path
	 */
	protected static function getLogFilePath(string $namespace, ?string $log_path = null): ?string {
		// If a specific path was provided
		if ($log_path) {
			// Check if the path is a directory or a file
			if (is_dir($log_path) || substr($log_path, -1) === '/' || substr($log_path, -1) === '\\') {
				// It's a directory, append plugin slug for the filename
				$log_dir = rtrim($log_path, '/\\');
				$log_file = $log_dir . '/' . $namespace . '.log';

				// Try to create the directory if it doesn't exist
				if (!is_dir($log_dir)) {
					if (!wp_mkdir_p($log_dir)) {
						// Failed to create directory, fall back to wp_upload_dir
						error_log("Logger: Failed to create log directory: {$log_dir}, falling back to uploads directory");
						$log_file = null;
					}
				}

				return $log_file;
			} else {
				// It's a file path
				$log_file = $log_path;
				$log_dir = dirname($log_path);

				// Try to create the directory if it doesn't exist
				if (!is_dir($log_dir)) {
					if (!wp_mkdir_p($log_dir)) {
						// Failed to create directory, fall back to wp_upload_dir
						error_log("Logger: Failed to create log directory: {$log_dir}, falling back to uploads directory");
						$log_file = null;
					}
				}

				return $log_file;
			}
		}

		// If log_file is still null (no path provided or directory creation failed)
		// Use uploads directory as fallback
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/archetype-logs';

		// Create logs directory if it doesn't exist
		if (!is_dir($log_dir)) {
			if (!wp_mkdir_p($log_dir)) {
				// If we can't create the directory in uploads, disable file logging
				error_log("Logger: Failed to create log directory in uploads, falling back to error_log");
				return null;
			}
		}

		return $log_dir . '/' . $namespace . '.log';
	}
}