<?php
/**
 * Comprehensive dependency and function scanner for Archetype
 * Scans all bundled code to find missing functions and dependencies
 */

echo "🔍 Comprehensive Dependency & Function Scanner\n";
echo "=============================================\n\n";

$libDir = __DIR__ . '/lib';
$srcDir = __DIR__ . '/src';

if (!is_dir($libDir)) {
    echo "❌ Error: lib/ directory not found. Run bundle script first.\n";
    exit(1);
}

$missingFunctions = [];
$missingClasses = [];
$allFunctions = [];
$definedFunctions = [];
$helperFiles = [];

/**
 * Extract function calls from PHP code
 */
function extractFunctionCalls($content) {
    $functions = [];

    // Pattern to match function calls
    if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches)) {
        foreach ($matches[1] as $func) {
            // Skip language constructs and obvious methods
            if (!in_array(strtolower($func), [
                'if', 'else', 'elseif', 'while', 'for', 'foreach', 'switch', 'case', 'default',
                'function', 'class', 'interface', 'trait', 'extends', 'implements', 'public',
                'private', 'protected', 'static', 'final', 'abstract', 'return', 'echo', 'print',
                'isset', 'empty', 'unset', 'array', 'list', 'new', 'clone', 'instanceof', 'self',
                'parent', 'this', 'count', 'strlen', 'substr', 'explode', 'implode', 'trim',
                'strtolower', 'strtoupper', 'is_array', 'is_string', 'is_null', 'is_object',
                'is_callable', 'method_exists', 'class_exists', 'interface_exists', 'defined'
            ])) {
                $functions[] = $func;
            }
        }
    }

    return array_unique($functions);
}

/**
 * Extract class usage from PHP code
 */
function extractClassUsage($content) {
    $classes = [];

    // Extract use statements
    if (preg_match_all('/use\s+([\\\\a-zA-Z0-9_]+)(?:\s+as\s+[a-zA-Z0-9_]+)?;/', $content, $matches)) {
        $classes = array_merge($classes, $matches[1]);
    }

    // Extract new ClassName() calls
    if (preg_match_all('/new\s+([\\\\a-zA-Z0-9_]+)\s*\(/', $content, $matches)) {
        $classes = array_merge($classes, $matches[1]);
    }

    // Extract ClassName:: static calls
    if (preg_match_all('/([\\\\a-zA-Z0-9_]+)::[a-zA-Z0-9_]+/', $content, $matches)) {
        $classes = array_merge($classes, $matches[1]);
    }

    return array_unique($classes);
}

/**
 * Extract function definitions from PHP code
 */
function extractFunctionDefinitions($content) {
    $functions = [];

    // Extract function definitions
    if (preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches)) {
        $functions = array_merge($functions, $matches[1]);
    }

    return array_unique($functions);
}

/**
 * Scan directory recursively
 */
function scanDirectory($dir, $baseDir = null) {
    global $missingFunctions, $missingClasses, $allFunctions, $definedFunctions, $helperFiles;

    if ($baseDir === null) {
        $baseDir = $dir;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace($baseDir, '', $file->getPathname());

            // Check if this looks like a helper file
            if (strpos($relativePath, 'helper') !== false ||
                basename($file) === 'functions.php' ||
                basename($file) === 'helpers.php') {
                $helperFiles[] = $relativePath;
            }

            $content = file_get_contents($file->getPathname());

            // Extract function calls
            $functions = extractFunctionCalls($content);
            foreach ($functions as $func) {
                $allFunctions[$func] = ($allFunctions[$func] ?? 0) + 1;
            }

            // Extract function definitions
            $definitions = extractFunctionDefinitions($content);
            foreach ($definitions as $func) {
                $definedFunctions[$func] = $relativePath;
            }

            // Extract class usage
            $classes = extractClassUsage($content);
            foreach ($classes as $class) {
                if (!class_exists($class, false) && !interface_exists($class, false)) {
                    $missingClasses[$class] = ($missingClasses[$class] ?? 0) + 1;
                }
            }
        }
    }
}

echo "📂 Scanning bundled libraries...\n";
scanDirectory($libDir);

echo "📂 Scanning Archetype source...\n";
scanDirectory($srcDir);

echo "\n📋 Analysis Results:\n";
echo "===================\n\n";

// Check for missing functions
echo "🔍 Checking for missing functions...\n";
$criticalMissingFunctions = [];
foreach ($allFunctions as $func => $count) {
    if (!function_exists($func) && !isset($definedFunctions[$func])) {
        $criticalMissingFunctions[$func] = $count;
    }
}

if (!empty($criticalMissingFunctions)) {
    arsort($criticalMissingFunctions);
    echo "❌ Missing functions (usage count):\n";
    foreach (array_slice($criticalMissingFunctions, 0, 20, true) as $func => $count) {
        echo "   - {$func} (used {$count} times)\n";
    }
} else {
    echo "✅ All functions appear to be available\n";
}

echo "\n🔍 Helper files found:\n";
if (!empty($helperFiles)) {
    foreach ($helperFiles as $file) {
        echo "   - {$file}\n";
    }
} else {
    echo "   No helper files found\n";
}

echo "\n🔍 Function definitions found:\n";
echo "Functions defined in bundled code: " . count($definedFunctions) . "\n";
$importantDefined = array_intersect_key($definedFunctions, $criticalMissingFunctions);
if (!empty($importantDefined)) {
    echo "Important functions defined:\n";
    foreach ($importantDefined as $func => $file) {
        echo "   - {$func} in {$file}\n";
    }
}

echo "\n🔧 Recommendations:\n";
echo "===================\n";

if (!empty($criticalMissingFunctions)) {
    echo "1. Missing functions detected. You should:\n";
    echo "   a) Check if these are Laravel helper functions\n";
    echo "   b) Add them to the autoloader or include their files\n";
    echo "   c) Look for helper files in the bundled packages\n\n";

    // Suggest which packages might contain these functions
    $likelyLaravelHelpers = array_filter(array_keys($criticalMissingFunctions), function($func) {
        return in_array($func, [
            'tap', 'value', 'data_get', 'data_set', 'collect', 'optional', 'retry', 'rescue',
            'throw_if', 'throw_unless', 'with', 'filled', 'blank', 'class_basename',
            'class_uses_recursive', 'trait_uses_recursive', 'head', 'last', 'windows_os'
        ]);
    });

    if (!empty($likelyLaravelHelpers)) {
        echo "🎯 Likely Laravel helper functions:\n";
        foreach ($likelyLaravelHelpers as $func) {
            echo "   - {$func}\n";
        }
        echo "\n";
    }
}

if (!empty($helperFiles)) {
    echo "2. Helper files to include in autoloader:\n";
    foreach ($helperFiles as $file) {
        echo "   require_once ARCHETYPE_LIB_PATH . '{$file}';\n";
    }
    echo "\n";
}

echo "3. Consider adding these to your autoloader:\n";
echo "   - All helper files found above\n";
echo "   - Manual definitions for critical missing functions\n";
echo "   - Proper namespace mapping for missing classes\n\n";

// Generate helper function definitions
if (!empty($criticalMissingFunctions)) {
    echo "🔧 Helper function templates (add to autoloader):\n";
    echo "===================================================\n";

    $commonHelpers = [
        'tap' => "function tap(\$value, \$callback = null) {\n    if (is_null(\$callback)) {\n        return new class(\$value) {\n            public \$target;\n            public function __construct(\$target) { \$this->target = \$target; }\n            public function __call(\$method, \$parameters) {\n                \$this->target->{\$method}(...\$parameters);\n                return \$this->target;\n            }\n        };\n    }\n    \$callback(\$value);\n    return \$value;\n}",

        'value' => "function value(\$value, ...\$args) {\n    return \$value instanceof Closure ? \$value(...\$args) : \$value;\n}",

        'collect' => "function collect(\$value = null) {\n    return new Illuminate\\Support\\Collection(\$value);\n}",

        'data_get' => "function data_get(\$target, \$key, \$default = null) {\n    if (is_null(\$key)) return \$target;\n    \$key = is_array(\$key) ? \$key : explode('.', \$key);\n    foreach (\$key as \$segment) {\n        if (is_array(\$target) && isset(\$target[\$segment])) {\n            \$target = \$target[\$segment];\n        } elseif (is_object(\$target) && isset(\$target->{\$segment})) {\n            \$target = \$target->{\$segment};\n        } else {\n            return \$default;\n        }\n    }\n    return \$target;\n}",

        'filled' => "function filled(\$value) {\n    return !blank(\$value);\n}",

        'blank' => "function blank(\$value) {\n    if (is_null(\$value)) return true;\n    if (is_string(\$value)) return trim(\$value) === '';\n    if (is_numeric(\$value) || is_bool(\$value)) return false;\n    if (\$value instanceof Countable) return count(\$value) === 0;\n    return empty(\$value);\n}",

        'class_basename' => "function class_basename(\$class) {\n    \$class = is_object(\$class) ? get_class(\$class) : \$class;\n    return basename(str_replace('\\\\', '/', \$class));\n}",

        'with' => "function with(\$value, callable \$callback = null) {\n    return is_null(\$callback) ? \$value : \$callback(\$value);\n}",
    ];

    foreach ($commonHelpers as $funcName => $definition) {
        if (isset($criticalMissingFunctions[$funcName])) {
            echo "\nif (!function_exists('{$funcName}')) {\n    {$definition}\n}\n";
        }
    }
}

echo "\n✅ Scan complete!\n";
echo "💡 Use this information to update your autoloader with all missing dependencies.\n";