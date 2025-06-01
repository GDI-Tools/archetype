<?php
/**
 * Archetype Framework Autoloader
 * Fixed autoloader with correct path mappings
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
 * Archetype autoloader with fixed path mapping
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

    // Handle Illuminate classes with correct path mapping
    if (strpos($class, 'Illuminate\\') === 0) {
        // Remove the Illuminate\ prefix to get the relative path
        $relativePath = substr($class, 11); // Remove 'Illuminate\'
        $parts = explode('\\', $relativePath);

        if (count($parts) >= 2) {
            $package = strtolower($parts[0]); // Database, Container, Support, etc.
            $classPath = implode('/', array_slice($parts, 1)); // Everything after the package

            // Try the standard path: illuminate/{package}/{classPath}.php
            $file = ARCHETYPE_LIB_PATH . '/illuminate/' . $package . '/' . $classPath . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }

        // Special case mappings for classes that moved between packages
        $specialMappings = [
            'Illuminate\\Support\\Collection' => 'illuminate/collections/Collection.php',
            'Illuminate\\Support\\Traits\\EnumeratesValues' => 'illuminate/collections/Traits/EnumeratesValues.php',
            'Illuminate\\Support\\Traits\\Conditionable' => 'illuminate/conditionable/Traits/Conditionable.php',
        ];

        if (isset($specialMappings[$class])) {
            $file = ARCHETYPE_LIB_PATH . '/' . $specialMappings[$class];
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }

        // Fallback: search all illuminate packages
        $illuminatePackages = ['database', 'support', 'container', 'events', 'contracts', 'collections', 'conditionable', 'macroable'];
        $fileName = basename(str_replace('\\', '/', $class)) . '.php';

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

    // Handle other bundled dependencies
    $dependencyMappings = [
        'Ramsey\\Uuid\\' => 'ramsey/uuid/src/',
        'Brick\\Math\\' => 'brick/math/src/',
        'Doctrine\\DBAL\\' => 'doctrine/dbal/src/',
        'Analog\\' => 'analog/analog/lib/',
        'Psr\\Container\\' => 'psr/container/src/',
        'Psr\\SimpleCache\\' => 'psr/simple-cache/src/',
        'Psr\\Log\\' => 'psr/log/src/',
        'Carbon\\' => 'nesbot/carbon/src/Carbon/',
    ];

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

    return false;
}, true, true);

// Load essential helper files if they exist
$helperFiles = [
    ARCHETYPE_LIB_PATH . '/illuminate/support/helpers.php',
    ARCHETYPE_LIB_PATH . '/illuminate/collections/helpers.php',
];

foreach ($helperFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}