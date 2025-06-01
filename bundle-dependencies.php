<?php
/**
 * Comprehensive dependency bundler for Archetype
 * Based on complete dependency analysis
 */

function copyDirectory($source, $dest) {
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $targetPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
        } else {
            copy($item, $targetPath);
        }
    }

    return true;
}

function cleanupDirectory($dir, $itemsToRemove) {
    foreach ($itemsToRemove as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            exec("rm -rf " . escapeshellarg($path));
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
}

echo "🔧 Bundling ALL Archetype dependencies (comprehensive)...\n";

$libDir = __DIR__ . '/lib';
$vendorDir = __DIR__ . '/vendor';

// Check if vendor directory exists
if (!is_dir($vendorDir)) {
    echo "❌ Error: vendor/ directory not found. Run 'composer install --dev' first.\n";
    exit(1);
}

// Clean existing lib directory
if (is_dir($libDir)) {
    echo "🧹 Cleaning existing lib/ directory...\n";
    exec("rm -rf " . escapeshellarg($libDir));
}
mkdir($libDir);

// Complete package list from dependency analysis
$packages = [
    // Illuminate packages
    'illuminate/bus' => 'illuminate/bus',
    'illuminate/collections' => 'illuminate/collections',
    'illuminate/conditionable' => 'illuminate/conditionable',
    'illuminate/container' => 'illuminate/container',
    'illuminate/contracts' => 'illuminate/contracts',
    'illuminate/database' => 'illuminate/database',
    'illuminate/events' => 'illuminate/events',
    'illuminate/macroable' => 'illuminate/macroable',
    'illuminate/pipeline' => 'illuminate/pipeline',
    'illuminate/support' => 'illuminate/support',

    // PSR packages
    'psr/clock' => 'psr/clock',
    'psr/container' => 'psr/container',
    'psr/simple-cache' => 'psr/simple-cache',

    // Doctrine packages
    'doctrine/inflector' => 'doctrine/inflector',
    'doctrine/dbal' => 'doctrine/dbal', // Adding DBAL for Archetype

    // Symfony packages
    'symfony/clock' => 'symfony/clock',
    'symfony/deprecation-contracts' => 'symfony/deprecation-contracts',
    'symfony/polyfill-mbstring' => 'symfony/polyfill-mbstring',
    'symfony/polyfill-php83' => 'symfony/polyfill-php83',
    'symfony/translation' => 'symfony/translation',
    'symfony/translation-contracts' => 'symfony/translation-contracts',

    // Laravel packages
    'laravel/serializable-closure' => 'laravel/serializable-closure',

    // Other essential packages
    'brick/math' => 'brick/math',
    'carbonphp/carbon-doctrine-types' => 'carbonphp/carbon-doctrine-types',
    'nesbot/carbon' => 'nesbot/carbon',
    'voku/portable-ascii' => 'voku/portable-ascii',

    // Additional packages for Archetype
    'ramsey/uuid' => 'ramsey/uuid',
    'analog/analog' => 'analog/analog',
];

$successCount = 0;
$totalCount = count($packages);
$skippedExtensions = 0;

foreach ($packages as $source => $dest) {
    // Skip PHP extensions (they're not bundleable)
    if (strpos($source, 'ext-') === 0) {
        echo "⏭️  Skipping PHP extension: {$source}\n";
        $skippedExtensions++;
        continue;
    }

    $sourcePath = $vendorDir . '/' . $source;
    $destPath = $libDir . '/' . $dest;

    echo "📦 Copying {$source}... ";

    if (is_dir($sourcePath)) {
        // Create destination directory structure
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Copy the package
        if (copyDirectory($sourcePath, $destPath)) {
            echo "✅\n";
            $successCount++;

            // Clean up unnecessary files
            $cleanupItems = [
                'tests', 'test', 'Tests', 'Test',
                'docs', 'doc', 'documentation',
                '.git', '.github', '.gitlab',
                'examples', 'example',
                'phpunit.xml', 'phpunit.xml.dist',
                '.gitignore', '.gitattributes',
                'CHANGELOG.md', 'CHANGELOG.txt',
                'README.md', 'README.txt',
                'LICENSE', 'LICENSE.md', 'LICENSE.txt',
                'CONTRIBUTING.md',
                'composer.json', 'composer.lock',
                '.travis.yml', '.circleci',
                'Makefile'
            ];

            cleanupDirectory($destPath, $cleanupItems);
        } else {
            echo "❌ (copy failed)\n";
        }
    } else {
        echo "❌ (not found)\n";
    }
}

echo "\n📊 Bundle Summary:\n";
echo "   ✅ Successfully bundled: {$successCount}/" . ($totalCount - $skippedExtensions) . " packages\n";
echo "   ⏭️  Skipped extensions: {$skippedExtensions}\n";

if ($successCount > 0) {
    echo "🎉 Dependencies bundled successfully!\n";

    // Calculate total size
    $size = 0;
    $fileCount = 0;
    if (is_dir($libDir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($libDir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $fileCount++;
            }
        }
    }

    $sizeFormatted = round($size / 1024 / 1024, 2);
    echo "📏 Total bundled size: {$sizeFormatted} MB ({$fileCount} files)\n";
} else {
    echo "⚠️  No packages were bundled successfully.\n";
}

echo "\n✨ Archetype is now ready for conflict-free distribution!\n";
echo "🔧 Next: Update the autoloader to handle all these namespaces.\n";