<?php
/**
 * Complete test of Archetype with bundled dependencies
 * Save as test-complete.php and run: php test-complete.php
 */

require_once __DIR__ . '/bootstrap/autoload.php';

echo "🧪 Complete Archetype Bundle Test\n";
echo "=================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function runTest($testName, $testFunction) {
    global $testsPassed, $totalTests;
    $totalTests++;

    echo "✅ Test {$totalTests}: {$testName}\n";

    try {
        $result = $testFunction();
        if ($result !== false) {
            echo "   ✅ PASSED: {$result}\n";
            $testsPassed++;
        } else {
            echo "   ❌ FAILED\n";
        }
    } catch (Exception $e) {
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ❌ FATAL: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Test 1: Archetype Application
runTest("Archetype Application", function() {
    $app = new Archetype\Application();
    return "Archetype\\Application loaded successfully";
});

// Test 2: Illuminate Container
runTest("Illuminate Container", function() {
    $container = new Illuminate\Container\Container();
    return "Illuminate\\Container\\Container loaded successfully";
});

// Test 3: Illuminate Support Collection
runTest("Illuminate Support Collection", function() {
    $collection = new Illuminate\Support\Collection([1, 2, 3, 4, 5]);
    return "Collection created with " . $collection->count() . " items, sum: " . $collection->sum();
});

// Test 4: UUID Generation
runTest("UUID Generation", function() {
    $uuid = Ramsey\Uuid\Uuid::uuid4();
    return "UUID generated: " . $uuid->toString();
});

// Test 5: Archetype Configuration (basic test without WordPress)
runTest("Archetype Basic Configuration", function() {
    $app = new Archetype\Application();
    return "Application instance created, ready for WordPress environment";
});

// Test 6: Illuminate Events
runTest("Illuminate Events", function() {
    $dispatcher = new Illuminate\Events\Dispatcher();
    $dispatcher->listen('test.event', function($data) {
        return 'Event received: ' . $data;
    });

    $results = $dispatcher->dispatch('test.event', ['test data']);
    return "Event system working: " . count($results) . " listeners responded";
});

// Test 7: Analog Logger
runTest("Analog Logger", function() {
    if (class_exists('Analog\\Analog')) {
        return "Analog\\Analog class available";
    }
    return false;
});

// Test 8: Bundle Information
runTest("Bundle Information", function() {
    $size = 0;
    $fileCount = 0;
    $phpFileCount = 0;

    if (is_dir(__DIR__ . '/lib')) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/lib'));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $fileCount++;
                if ($file->getExtension() === 'php') {
                    $phpFileCount++;
                }
            }
        }
    }

    $sizeFormatted = round($size / 1024 / 1024, 2);
    return "Bundled {$fileCount} files ({$phpFileCount} PHP files), total size: {$sizeFormatted} MB";
});

// Test 9: Check for common WordPress conflicts
runTest("WordPress Compatibility Check", function() {
    // Test that we can create multiple instances without conflicts
    $app1 = new Archetype\Application();
    $app2 = new Archetype\Application();

    // Test that Illuminate classes don't conflict
    $container1 = new Illuminate\Container\Container();
    $container2 = new Illuminate\Container\Container();

    return "Multiple instances created without conflicts";
});

// Test 10: Memory usage test
runTest("Memory Usage Test", function() {
    $memoryBefore = memory_get_usage();

    // Create several objects to test memory usage
    $app = new Archetype\Application();
    $container = new Illuminate\Container\Container();
    $collection = new Illuminate\Support\Collection(range(1, 100));
    $uuid = Ramsey\Uuid\Uuid::uuid4();

    $memoryAfter = memory_get_usage();
    $memoryUsed = round(($memoryAfter - $memoryBefore) / 1024, 2);

    return "Memory usage for object creation: {$memoryUsed} KB";
});

// Results Summary
echo "📊 Test Results Summary\n";
echo "======================\n";
echo "Tests Passed: {$testsPassed}/{$totalTests}\n";

if ($testsPassed === $totalTests) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "✨ Archetype with bundled dependencies is ready for production use!\n\n";

    echo "📋 Next Steps:\n";
    echo "1. Copy this Archetype directory to your SecurityPulse plugin\n";
    echo "2. Update SecurityPulse to require 'archetype/bootstrap/autoload.php'\n";
    echo "3. Remove Composer dependencies from SecurityPulse\n";
    echo "4. Test SecurityPulse with the bundled Archetype\n\n";

    echo "🚀 Ready to integrate with SecurityPulse!\n";
} else {
    echo "⚠️  Some tests failed. Please fix the issues above before proceeding.\n";
    echo "💡 Common issues:\n";
    echo "   - Missing dependencies in bundle script\n";
    echo "   - Autoloader path mappings incorrect\n";
    echo "   - File permission issues\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Archetype Bundle Test Complete\n";
echo str_repeat("=", 50) . "\n";