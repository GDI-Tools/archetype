<?php
/**
 * Archetype Framework Comprehensive Autoloader
 * Handles all bundled dependencies without conflicts
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
        // Illuminate packages
        'Illuminate\\Bus\\' => 'illuminate/bus/src/Illuminate/Bus/',
        'Illuminate\\Collections\\' => 'illuminate/collections/',
        'Illuminate\\Conditionable\\' => 'illuminate/conditionable/',
        'Illuminate\\Container\\' => 'illuminate/container/src/Illuminate/Container/',
        'Illuminate\\Contracts\\' => 'illuminate/contracts/',
        'Illuminate\\Database\\' => 'illuminate/database/src/Illuminate/Database/',
        'Illuminate\\Events\\' => 'illuminate/events/src/Illuminate/Events/',
        'Illuminate\\Macroable\\' => 'illuminate/macroable/',
        'Illuminate\\Pipeline\\' => 'illuminate/pipeline/src/Illuminate/Pipeline/',
        'Illuminate\\Support\\' => 'illuminate/support/src/Illuminate/Support/',

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
                "illuminate/{$package}/src/Illuminate/{$package}/{$classPath}.php",
                "illuminate/{$package}/{$classPath}.php",
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

// Load essential helper files
$helperFiles = [
    ARCHETYPE_LIB_PATH . '/illuminate/support/src/Illuminate/Support/helpers.php',
    ARCHETYPE_LIB_PATH . '/illuminate/collections/helpers.php',
    ARCHETYPE_LIB_PATH . '/symfony/polyfill-mbstring/bootstrap.php',
    ARCHETYPE_LIB_PATH . '/symfony/polyfill-php83/bootstrap.php',
];

foreach ($helperFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}