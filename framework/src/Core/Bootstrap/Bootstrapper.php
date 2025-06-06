<?php
/**
 * Application bootstrapper
 *
 * @package Archetype\Core
 */

namespace Archetype\Core\Bootstrap;

use Archetype\Core\Config\AppConfig;
use Archetype\Core\Controller\ControllerRegistry;
use Archetype\Core\Database\EloquentManager;
use Archetype\Core\Database\SchemaGenerator;
use Archetype\Core\Scanner\ControllerScanner;
use Archetype\Core\Scanner\ModelScanner;
use Archetype\Logging\ArchetypeLogger;
use Archetype\Logging\Logger;

class Bootstrapper {
    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * Controller scanner
     *
     * @var ControllerScanner
     */
    private $controller_scanner;

    /**
     * Controller registry
     *
     * @var ControllerRegistry
     */
    private $controller_registry;

	/**
	 * Eloquent manager
	 *
	 * @var EloquentManager
	 */
	private $eloquent_manager;

	/**
	 * Model scanner
	 *
	 * @var ModelScanner
	 */
	private $model_scanner;

	/**
	 * Discovered models
	 *
	 * @var array
	 */
	private $models = [];

    /**
     * Whether components have been bootstrapped
     *
     * @var array
     */
    private $bootstrapped = [
        'logger' => false,
        'controllers' => false,
        'eloquent' => false,
        'models' => false
    ];

    /**
     * Constructor
     *
     * @param AppConfig $config
     */
    public function __construct(AppConfig $config) {
        $this->config = $config;
        $this->controller_scanner = new ControllerScanner();
	    $this->model_scanner = new ModelScanner();
    }

    /**
     * Bootstrap all components
     *
     * @return void
     */
    public function bootstrap(): void {
        $this->bootstrap_logger();
	    $this->bootstrap_eloquent();
        $this->bootstrap_controllers();
	    $this->bootstrap_models();
    }

    /**
     * Bootstrap the logger
     *
     * @return void
     */
    public function bootstrap_logger(): void {
        if ($this->bootstrapped['logger']) {
            return;
        }

        $logging_config = $this->config->get('logging');

        if (!$logging_config['enabled']) {
            return;
        }

        Logger::init(
            $this->config->get('plugin_slug'),
            $logging_config['path'],
            $logging_config['level'],
            $logging_config['use_file']
        );

        $this->bootstrapped['logger'] = true;
    }

    /**
     * Bootstrap controllers
     *
     * @return void
     */
    public function bootstrap_controllers(): void {
        if ($this->bootstrapped['controllers']) {
            return;
        }

        // Create the controller registry
        $this->controller_registry = new ControllerRegistry($this->config->get('api_namespace'));

        $controllers = $this->controller_scanner->scan(
            $this->config->get('context_paths'),
            $this->config->get('exclude_folders'),
            $this->config->get('deep_path_scan')
        );

        // Register discovered controllers
        $this->controller_registry->registerControllers($controllers);
        $this->bootstrapped['controllers'] = true;
    }

    /**
     * Get the controller registry
     *
     * @return ControllerRegistry|null
     */
    public function get_controller_registry(): ?ControllerRegistry {
        return $this->controller_registry;
    }

	/**
	 * Bootstrap Eloquent ORM
	 *
	 * @return void
	 */
	public function bootstrap_eloquent(): void {
		if ($this->bootstrapped['eloquent']) {
			return;
		}

		try {
			// Get database configuration
			$db_config = $this->config->get('database', []);

			// Make sure we have a valid host
			if (empty($db_config['host']) || (is_array($db_config['host']) && empty($db_config['host']))) {
				$db_config['host'] = defined('DB_HOST') ? DB_HOST : 'localhost';
			}

			// Initialize the Eloquent manager
			$this->eloquent_manager = new \Archetype\Core\Database\EloquentManager($db_config);

			$this->bootstrapped['eloquent'] = true;
		} catch (\Exception $e) {
			$this->bootstrapped['eloquent'] = false;
			ArchetypeLogger::error('Failed to bootstrap Eloquent ORM: ' . $e->getMessage());
			// Continue without Eloquent - don't rethrow the exception
		}
	}

	/**
	* Bootstrap models
	*
	* @return void
	*/
	public function bootstrap_models(): void {
		if ($this->bootstrapped['models']) {
			return;
		}

		// Ensure Eloquent is bootstrapped first
		$this->bootstrap_eloquent();

		// Skip model processing if Eloquent failed to initialize
		if (!$this->bootstrapped['eloquent'] || !$this->eloquent_manager || !$this->eloquent_manager->isInitialized()) {
			ArchetypeLogger::warning('Skipping model bootstrapping because Eloquent ORM is not available');
			$this->bootstrapped['models'] = false;
			return;
		}

		try {
			// Scan for models
			$this->models = $this->model_scanner->scan(
				$this->config->get('context_paths'),
				$this->config->get('exclude_folders'),
				$this->config->get('deep_path_scan')
			);

			// Only create tables if we found models
			if (!empty($this->models)) {

				// Create database tables
				$schema_generator = new \Archetype\Core\Database\SchemaGenerator(
					$this->eloquent_manager->getSchemaBuilder()
				);

				try {
					$schema_generator->createTables($this->models);
				} catch (\Exception $e) {
					ArchetypeLogger::error('Error creating tables: ' . $e->getMessage());
					// Continue execution despite table creation errors
				}
			} else {
				ArchetypeLogger::info('No models found to process');
			}

			$this->bootstrapped['models'] = true;
		} catch (\Exception $e) {
			ArchetypeLogger::error('Failed to bootstrap models: ' . $e->getMessage());
			$this->bootstrapped['models'] = false;
			// Continue execution without models
		}
	}

	/**
	 * Get the discovered models
	 *
	 * @return array
	 */
	public function get_models(): array {
		return $this->models;
	}

	/**
	 * Get the Eloquent manager
	 *
	 * @return EloquentManager|null
	 */
	public function get_eloquent_manager(): ?EloquentManager {
		return $this->eloquent_manager;
	}
}