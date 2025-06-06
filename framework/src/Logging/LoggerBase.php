<?php
/**
 * Base Logger Implementation
 *
 * @package Archetype\Logging
 */

namespace Archetype\Logging;

use Analog\Analog;

/**
 * Base logger class with common functionality for all loggers
 */
abstract class LoggerBase {
	/**
	 * Log levels
	 */
	const EMERGENCY = 0;  // System is unusable
	const ALERT     = 1;  // Action must be taken immediately
	const CRITICAL  = 2;  // Critical conditions
	const ERROR     = 3;  // Error conditions
	const WARNING   = 4;  // Warning conditions
	const NOTICE    = 5;  // Normal but significant conditions
	const INFO      = 6;  // Informational messages
	const DEBUG     = 7;  // Debug-level messages

	/**
	 * Log level names
	 *
	 * @var array
	 */
	protected static $levelNames = [
		self::EMERGENCY => 'EMERGENCY',
		self::ALERT     => 'ALERT',
		self::CRITICAL  => 'CRITICAL',
		self::ERROR     => 'ERROR',
		self::WARNING   => 'WARNING',
		self::NOTICE    => 'NOTICE',
		self::INFO      => 'INFO',
		self::DEBUG     => 'DEBUG',
	];

	/**
	 * Class-specific properties - each child class should override these
	 */

	/**
	 * Current log level
	 *
	 * @var int
	 */
	protected static $level = self::INFO;

	/**
	 * Whether the logger is initialized
	 *
	 * @var bool
	 */
	protected static $initialized = false;

	/**
	 * Namespace for logs
	 *
	 * @var string
	 */
	protected static $namespace = 'log';

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
	 * @param string $namespace Namespace for logs
	 * @param string|null $log_path Path to log file or directory, or null for default
	 * @param int $level Log level
	 * @param bool $use_file Whether to use file logging
	 * @return void
	 */
	public static function init(string $namespace, ?string $log_path = null, int $level = self::INFO, bool $use_file = true): void {
		if (static::$initialized) {
			return;
		}

		static::$namespace = $namespace;
		static::$level = $level;

		// Determine the log file path
		$log_file = null;

		if ($use_file) {
			// Get log file path - implementation varies by logger
			$log_file = static::getLogFilePath($namespace, $log_path);
		}

		// Define a custom format function for Analog
		$format_function = function($message, $level) {
			$timestamp = date('Y-m-d H:i:s');
			$levelName = static::get_level_name($level);

			// Get caller information
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
			// Find the original caller (skip internal Analog and Logger calls)
			$caller = null;
			$skipClasses = ['Analog', 'Logger', 'ArchetypeLogger', 'LoggerBase'];

			foreach ($backtrace as $trace) {
				if (isset($trace['file'])) {
					$file = basename($trace['file']);
					$skip = false;

					foreach ($skipClasses as $skipClass) {
						if (strpos($file, $skipClass) !== false) {
							$skip = true;
							break;
						}
					}

					if (!$skip) {
						$caller = $trace;
						break;
					}
				}
			}

			// If we couldn't find a non-Logger caller, use the first available
			if (!$caller && !empty($backtrace)) {
				$caller = end($backtrace);
			}

			// Format the caller info
			$file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
			$line = $caller['line'] ?? '?';
			$caller_info = $file . ':' . $line;

			// Return the formatted log line
			return sprintf("%s - %s - [%s] - %s\n",
				$timestamp,
				$levelName,
				$caller_info,
				$message
			);
		};

		// Set up the appropriate handler with custom formatter
		if ($use_file && $log_file) {
			try {
				// Create a custom file handler for this logger instance
				static::$fileHandler = function($info) use ($log_file, $format_function) {
					$message = $format_function($info['message'], $info['level']);
					file_put_contents($log_file, $message, FILE_APPEND);
				};

				// Make a test write to ensure the file is writable
				$test_result = file_put_contents($log_file, '', FILE_APPEND);
				if ($test_result === false) {
					// File is not writable, fall back to error_log
					error_log(static::$namespace . ": Log file is not writable: {$log_file}, falling back to error_log");
					static::setupFallbackHandler($format_function);
				}
			} catch (\Exception $e) {
				// If file handler initialization fails, use error_log
				error_log(static::$namespace . ": Error initializing file handler: " . $e->getMessage());
				static::setupFallbackHandler($format_function);
			}
		} else {
			// Use error_log() for logging
			static::setupFallbackHandler($format_function);
		}

		static::$initialized = true;
	}

	/**
	 * Get log file path - to be implemented by child classes
	 *
	 * @param string $namespace Namespace for logs
	 * @param string|null $log_path Path to log file or directory, or null for default
	 * @return string|null Log file path
	 */
	protected static function getLogFilePath(string $namespace, ?string $log_path = null): ?string {
		// Implementation varies by logger type
		return null;
	}

	/**
	 * Set up fallback handler with custom formatter
	 *
	 * @param callable $format_function Function to format log messages
	 * @return void
	 */
	protected static function setupFallbackHandler(callable $format_function): void {
		static::$fallbackHandler = function($info) use ($format_function) {
			$message = $format_function($info['message'], $info['level']);
			error_log('[' . static::$namespace . '] ' . trim($message));
		};
	}

	/**
	 * Set log level
	 *
	 * @param int $level Log level
	 * @return void
	 */
	public static function set_level(int $level): void {
		static::$level = $level;
	}

	/**
	 * Get current log level
	 *
	 * @return int
	 */
	public static function get_level(): int {
		return static::$level;
	}

	/**
	 * Get log level name
	 *
	 * @param int $level Log level
	 * @return string
	 */
	public static function get_level_name(int $level): string {
		return static::$levelNames[$level] ?? 'UNKNOWN';
	}

	/**
	 * Log a message if level is sufficient
	 *
	 * @param int $level Log level
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	protected static function log(int $level, string $message, array $context = []): void {
		// Ensure logger is initialized
		if (!static::$initialized) {
			// Auto-initialize with defaults if not done explicitly
			static::init(static::$namespace);
		}

		if ($level > static::$level) {
			return;
		}

		// Add context to message if provided
		if (!empty($context)) {
			$message .= ' ' . json_encode($context);
		}

		// Use the appropriate handler
		$handler = static::$fileHandler ?: static::$fallbackHandler;

		if ($handler) {
			// Call the handler directly instead of using Analog
			$handler([
				'message' => $message,
				'level' => $level
			]);
		} else {
			// Fallback to error_log if no handler is available
			error_log('[' . static::$namespace . '] ' . $message);
		}
	}

	/**
	 * Log an emergency message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function emergency(string $message, array $context = []): void {
		static::log(static::EMERGENCY, $message, $context);
	}

	/**
	 * Log an alert message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function alert(string $message, array $context = []): void {
		static::log(static::ALERT, $message, $context);
	}

	/**
	 * Log a critical message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function critical(string $message, array $context = []): void {
		static::log(static::CRITICAL, $message, $context);
	}

	/**
	 * Log an error message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function error(string $message, array $context = []): void {
		static::log(static::ERROR, $message, $context);
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function warning(string $message, array $context = []): void {
		static::log(static::WARNING, $message, $context);
	}

	/**
	 * Log a notice message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function notice(string $message, array $context = []): void {
		static::log(static::NOTICE, $message, $context);
	}

	/**
	 * Log an info message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function info(string $message, array $context = []): void {
		static::log(static::INFO, $message, $context);
	}

	/**
	 * Log a debug message
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context
	 * @return void
	 */
	public static function debug(string $message, array $context = []): void {
		static::log(static::DEBUG, $message, $context);
	}
}