<?php
/**
 * Debug script to test autoloader paths
 */

require_once __DIR__ . '/bootstrap/autoload.php';

echo "🔍 Debugging Autoloader for Illuminate\\Container\\Container\n";
echo "===========================================================\n\n";

$class = 'Illuminate\\Container\\Container';
$relativePath = str_replace(['Illuminate\\', '\\'], ['', '/'], $class);

echo "Class: {$class}\n";
echo "Relative Path: {$relativePath}\n";
echo "Base lib path: " . ARCHETYPE_LIB_PATH . "\n\n";

// Test the search paths manually
$searchPaths = [
    'illuminate/database/' . $relativePath . '.php',
    'illuminate/support/' . $relativePath . '.php',
    'illuminate/container/' . $relativePath . '.php',
    'illuminate/events/' . $relativePath . '.php',
    'illuminate/contracts/' . $relativePath . '.php',
    'illuminate/collections/' . $relativePath . '.php',
    'illuminate/conditionable/' . $relativePath . '.php',
    'illuminate/macroable/' . $relativePath . '.php',
];

echo "Testing search paths:\n";
foreach ($searchPaths as $path) {
    $fullPath = ARCHETYPE_LIB_PATH . '/' . $path;
    $exists = file_exists($fullPath) ? '✅ EXISTS' : '❌ NOT FOUND';
    echo "  {$path} -> {$exists}\n";
    if (file_exists($fullPath)) {
        echo "    Full path: {$fullPath}\n";
    }
}

echo "\n🔍 Checking actual directory contents:\n";
$containerDir = ARCHETYPE_LIB_PATH . '/illuminate/container';
if (is_dir($containerDir)) {
    echo "Container directory exists, files:\n";
    $files = scandir($containerDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "  - {$file}\n";
        }
    }
} else {
    echo "❌ Container directory does not exist!\n";
}

echo "\n🔍 Manual class loading test:\n";
$containerFile = ARCHETYPE_LIB_PATH . '/illuminate/container/Container.php';
if (file_exists($containerFile)) {
    echo "✅ Container.php file exists\n";
    echo "Attempting to require it...\n";
    try {
        require_once $containerFile;
        echo "✅ File required successfully\n";

        if (class_exists('Illuminate\\Container\\Container', false)) {
            echo "✅ Class now exists!\n";
        } else {
            echo "❌ Class still not found after requiring file\n";
        }
    } catch (Exception $e) {
        echo "❌ Error requiring file: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Container.php file does not exist at: {$containerFile}\n";
}