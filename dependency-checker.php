<?php
/**
 * Comprehensive dependency checker for Archetype bundling
 * This script analyzes what dependencies are actually needed
 */

echo "🔍 Analyzing Illuminate dependencies...\n";
echo "=====================================\n\n";

$vendorDir = __DIR__ . '/vendor';
if (!is_dir($vendorDir)) {
    echo "❌ Error: vendor/ directory not found. Run 'composer install --dev' first.\n";
    exit(1);
}

// Core packages we want to analyze
$corePackages = [
    'illuminate/database',
    'illuminate/support',
    'illuminate/container',
    'illuminate/events',
    'illuminate/contracts',
    'illuminate/collections',
];

$allDependencies = [];

// Function to extract dependencies from composer.json
function extractDependencies($packagePath) {
    $composerFile = $packagePath . '/composer.json';
    if (!file_exists($composerFile)) {
        return [];
    }

    $composer = json_decode(file_get_contents($composerFile), true);
    $dependencies = [];

    if (isset($composer['require'])) {
        foreach ($composer['require'] as $package => $version) {
            // Skip PHP version requirement
            if ($package !== 'php' && $package !== 'ext-*') {
                $dependencies[] = $package;
            }
        }
    }

    return $dependencies;
}

// Function to recursively find all dependencies
function findAllDependencies($package, $vendorDir, &$found = []) {
    if (in_array($package, $found)) {
        return; // Already processed
    }

    $found[] = $package;
    $packagePath = $vendorDir . '/' . $package;

    if (!is_dir($packagePath)) {
        echo "⚠️  Package not found: {$package}\n";
        return;
    }

    $dependencies = extractDependencies($packagePath);
    foreach ($dependencies as $dep) {
        findAllDependencies($dep, $vendorDir, $found);
    }
}

// Analyze each core package
foreach ($corePackages as $package) {
    echo "📦 Analyzing {$package}...\n";
    $packageDeps = [];
    findAllDependencies($package, $vendorDir, $packageDeps);

    echo "   Dependencies found: " . count($packageDeps) . "\n";
    foreach ($packageDeps as $dep) {
        if (!in_array($dep, $allDependencies)) {
            $allDependencies[] = $dep;
        }
    }
    echo "\n";
}

// Sort and display all unique dependencies
sort($allDependencies);

echo "📋 Complete dependency list (" . count($allDependencies) . " packages):\n";
echo "===========================================\n";

$categorized = [
    'illuminate' => [],
    'psr' => [],
    'doctrine' => [],
    'symfony' => [],
    'laravel' => [],
    'others' => []
];

foreach ($allDependencies as $dep) {
    if (strpos($dep, 'illuminate/') === 0) {
        $categorized['illuminate'][] = $dep;
    } elseif (strpos($dep, 'psr/') === 0) {
        $categorized['psr'][] = $dep;
    } elseif (strpos($dep, 'doctrine/') === 0) {
        $categorized['doctrine'][] = $dep;
    } elseif (strpos($dep, 'symfony/') === 0) {
        $categorized['symfony'][] = $dep;
    } elseif (strpos($dep, 'laravel/') === 0) {
        $categorized['laravel'][] = $dep;
    } else {
        $categorized['others'][] = $dep;
    }
}

foreach ($categorized as $category => $packages) {
    if (!empty($packages)) {
        echo "\n🔸 " . ucfirst($category) . " packages:\n";
        foreach ($packages as $package) {
            $packagePath = $vendorDir . '/' . $package;
            $size = 0;
            if (is_dir($packagePath)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packagePath));
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                    }
                }
            }
            $sizeFormatted = round($size / 1024, 1);
            echo "   - {$package} ({$sizeFormatted} KB)\n";
        }
    }
}

// Generate bundle script configuration
echo "\n🔧 Suggested bundle-dependencies.php configuration:\n";
echo "===================================================\n";

echo "\$packages = [\n";
foreach ($categorized as $category => $packages) {
    if (!empty($packages)) {
        echo "    // " . ucfirst($category) . " packages\n";
        foreach ($packages as $package) {
            echo "    '{$package}' => '{$package}',\n";
        }
        echo "\n";
    }
}
echo "];\n\n";

// Calculate total estimated size
$totalSize = 0;
foreach ($allDependencies as $dep) {
    $packagePath = $vendorDir . '/' . $dep;
    if (is_dir($packagePath)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packagePath));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }
    }
}

$totalSizeFormatted = round($totalSize / 1024 / 1024, 2);
echo "💾 Estimated total bundle size: {$totalSizeFormatted} MB\n";

echo "\n✅ Analysis complete!\n";
echo "💡 Next steps:\n";
echo "   1. Update bundle-dependencies.php with the packages above\n";
echo "   2. Update the autoloader to handle all these namespaces\n";
echo "   3. Run the bundle script\n";
echo "   4. Test thoroughly\n";