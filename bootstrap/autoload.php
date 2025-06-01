<?php
/**
 * Archetype Framework - Fixed Autoloader with Function Guards
 * Prevents function redeclaration conflicts
 */

// Prevent multiple loading
if (defined('ARCHETYPE_AUTOLOADER_LOADED')) {
    return;
}
define('ARCHETYPE_AUTOLOADER_LOADED', true);

// Define base paths
define('ARCHETYPE_BASE_PATH', dirname(__DIR__));
define('ARCHETYPE_LIB_PATH', ARCHETYPE_BASE_PATH . '/lib');
define('ARCHETYPE_SRC_PATH', ARCHETYPE_BASE_PATH . '/src');

/**
 * Comprehensive autoloader for all bundled dependencies
 */
spl_autoload_register(function ($class) {
    // Handle Archetype classes
    if (strpos($class, 'Archetype\\') === 0) {
        $relativePath = str_replace(['Archetype\\', '\\'], ['', '/'], $class);
        $file = ARCHETYPE_SRC_PATH . '/' . $relativePath . '.php';

        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }

    // Handle all bundled dependencies with comprehensive mapping
    $dependencyMappings = [
        // Illuminate packages - fixed paths based on actual structure
        'Illuminate\\Bus\\' => 'illuminate/bus/',
        'Illuminate\\Collections\\' => 'illuminate/collections/',
        'Illuminate\\Conditionable\\' => 'illuminate/conditionable/',
        'Illuminate\\Container\\' => 'illuminate/container/',
        'Illuminate\\Contracts\\' => 'illuminate/contracts/',
        'Illuminate\\Database\\' => 'illuminate/database/',
        'Illuminate\\Events\\' => 'illuminate/events/',
        'Illuminate\\Macroable\\' => 'illuminate/macroable/',
        'Illuminate\\Pipeline\\' => 'illuminate/pipeline/',
        'Illuminate\\Support\\' => 'illuminate/support/',

        // PSR packages
        'Psr\\Clock\\' => 'psr/clock/src/',
        'Psr\\Container\\' => 'psr/container/src/',
        'Psr\\SimpleCache\\' => 'psr/simple-cache/src/',

        // Doctrine packages
        'Doctrine\\Inflector\\' => 'doctrine/inflector/lib/Doctrine/Inflector/',
        'Doctrine\\DBAL\\' => 'doctrine/dbal/src/',

        // Symfony packages
        'Symfony\\Component\\Clock\\' => 'symfony/clock/',
        'Symfony\\Contracts\\Deprecation\\' => 'symfony/deprecation-contracts/',
        'Symfony\\Polyfill\\Mbstring\\' => 'symfony/polyfill-mbstring/',
        'Symfony\\Polyfill\\Php83\\' => 'symfony/polyfill-php83/',
        'Symfony\\Component\\Translation\\' => 'symfony/translation/',
        'Symfony\\Contracts\\Translation\\' => 'symfony/translation-contracts/',

        // Laravel packages
        'Laravel\\SerializableClosure\\' => 'laravel/serializable-closure/src/',

        // Other packages
        'Brick\\Math\\' => 'brick/math/src/',
        'Carbon\\Doctrine\\' => 'carbonphp/carbon-doctrine-types/src/',
        'Carbon\\' => 'nesbot/carbon/src/Carbon/',
        'voku\\helper\\' => 'voku/portable-ascii/src/voku/helper/',
        'Ramsey\\Uuid\\' => 'ramsey/uuid/src/',
        'Analog\\' => 'analog/analog/lib/',
    ];

    // Try direct mapping first
    foreach ($dependencyMappings as $namespace => $path) {
        if (strpos($class, $namespace) === 0) {
            $relativePath = str_replace([$namespace, '\\'], ['', '/'], $class);
            $file = ARCHETYPE_LIB_PATH . '/' . $path . $relativePath . '.php';

            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }

    // Special handling for Illuminate classes that moved between packages
    if (strpos($class, 'Illuminate\\') === 0) {
        $specialMappings = [
            'Illuminate\\Support\\Collection' => 'illuminate/collections/Collection.php',
            'Illuminate\\Support\\Traits\\EnumeratesValues' => 'illuminate/collections/Traits/EnumeratesValues.php',
            'Illuminate\\Support\\Traits\\Conditionable' => 'illuminate/conditionable/Traits/Conditionable.php',
            'Illuminate\\Support\\Traits\\Macroable' => 'illuminate/macroable/Traits/Macroable.php',
        ];

        if (isset($specialMappings[$class])) {
            $file = ARCHETYPE_LIB_PATH . '/' . $specialMappings[$class];
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }

        // Fallback: search all illuminate packages
        $relativePath = substr($class, 11); // Remove 'Illuminate\'
        $parts = explode('\\', $relativePath);

        if (count($parts) >= 2) {
            $package = strtolower($parts[0]);
            $classPath = implode('/', array_slice($parts, 1));

            // Common Illuminate package structures
            $searchPaths = [
                "illuminate/{$package}/{$classPath}.php",
                "illuminate/{$package}/src/Illuminate/{$package}/{$classPath}.php",
                "illuminate/{$package}/src/{$classPath}.php",
            ];

            foreach ($searchPaths as $searchPath) {
                $file = ARCHETYPE_LIB_PATH . '/' . $searchPath;
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }

        // Last resort: search all illuminate directories
        $fileName = basename(str_replace('\\', '/', $class)) . '.php';
        $illuminatePackages = ['bus', 'collections', 'conditionable', 'container', 'contracts', 'database', 'events', 'macroable', 'pipeline', 'support'];

        foreach ($illuminatePackages as $package) {
            $searchDir = ARCHETYPE_LIB_PATH . '/illuminate/' . $package;
            if (is_dir($searchDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($searchDir, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getBasename() === $fileName) {
                        require_once $file->getPathname();
                        return true;
                    }
                }
            }
        }
    }

    return false;
}, true, true);

/**
 * Load helper files with function guards to prevent redeclaration
 */
function archetype_load_helper_files_safely() {
    $helperFiles = [
        // Critical Laravel/Illuminate helpers
        '/illuminate/support/helpers.php',
        '/illuminate/support/functions.php',
        '/illuminate/collections/helpers.php',
        '/illuminate/collections/functions.php',
        '/illuminate/events/functions.php',

        // Symfony polyfills
        '/symfony/polyfill-mbstring/bootstrap.php',
        '/symfony/polyfill-php83/bootstrap.php',

        // Translation helpers
        '/symfony/translation/Resources/functions.php',

        // UUID helpers - LOAD WITH CAUTION
        '/ramsey/uuid/src/functions.php',
    ];

    foreach ($helperFiles as $file) {
        $fullPath = ARCHETYPE_LIB_PATH . $file;
        if (file_exists($fullPath)) {
            // Special handling for UUID functions to prevent conflicts
            if (strpos($file, 'ramsey/uuid') !== false) {
                archetype_load_uuid_functions_safely($fullPath);
            } else {
                require_once $fullPath;
            }
        }
    }
}

/**
 * Safely load UUID functions by checking if they already exist
 */
function archetype_load_uuid_functions_safely($filePath) {
    // Get the file content
    $content = file_get_contents($filePath);

    // Extract function names using regex
    preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches);
    $functionNames = $matches[1];

    $conflictingFunctions = [];
    $safeFunctions = [];

    // Check which functions already exist
    foreach ($functionNames as $functionName) {
        $fullFunctionName = 'Ramsey\\Uuid\\' . $functionName;
        if (function_exists($fullFunctionName)) {
            $conflictingFunctions[] = $fullFunctionName;
        } else {
            $safeFunctions[] = $functionName;
        }
    }

    if (!empty($conflictingFunctions)) {
        // Log the conflict but don't fail
        if (function_exists('error_log')) {
            error_log('Archetype: UUID functions already exist, skipping: ' . implode(', ', $conflictingFunctions));
        }
        return;
    }

    if (!empty($safeFunctions)) {
        // All functions are safe to load
        require_once $filePath;
    }
}

// Load helper files safely
archetype_load_helper_files_safely();

// Define critical missing Laravel helper functions if they don't exist
if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new class($value) {
                public $target;

                public function __construct($target) {
                    $this->target = $target;
                }

                public function __call($method, $parameters) {
                    $this->target->{$method}(...$parameters);
                    return $this->target;
                }
            };
        }

        $callback($value);
        return $value;
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     */
    function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     */
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if (is_array($target) && isset($target[$segment])) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('collect')) {
    /**
     * Create a collection from the given value.
     */
    function collect($value = null)
    {
        return new Illuminate\Support\Collection($value);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is "filled".
     */
    function filled($value)
    {
        return !blank($value);
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     */
    function blank($value)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback.
     */
    function with($value, callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('retry')) {
    /**
     * Retry an operation a given number of times.
     */
    function retry($times, callable $callback, $sleepMilliseconds = 0, $when = null)
    {
        $attempts = 0;

        beginning:
        $attempts++;
        $times--;

        try {
            return $callback($attempts);
        } catch (Exception $e) {
            if ($times < 1 || ($when && ! $when($e))) {
                throw $e;
            }

            if ($sleepMilliseconds) {
                usleep($sleepMilliseconds * 1000);
            }

            goto beginning;
        }
    }
}

if (!function_exists('rescue')) {
    /**
     * Catch a potential exception and return a default value.
     */
    function rescue(callable $callback, $rescue = null, $report = true)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if ($report && function_exists('report')) {
                report($e);
            }

            return value($rescue, $e);
        }
    }
}