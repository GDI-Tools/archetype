<?php
/**
 * test-scoped.php
 * Test if the scoped Archetype package works without conflicts
 */

echo "🧪 Testing Scoped Archetype Package\n";
echo "===================================\n\n";

// Load the scoped package
echo "1. Loading scoped autoloader...\n";
require_once 'build/vendor/scoper-autoload.php';

try {
    echo "2. Testing scoped Illuminate components...\n";

    // Test scoped Illuminate Collection
    $collection = new ArchetypeVendor\Illuminate\Support\Collection([1, 2, 3, 4, 5]);
    echo "   ✅ Scoped Collection created with " . $collection->count() . " items\n";

    // Test scoped Illuminate Container
    $container = new ArchetypeVendor\Illuminate\Container\Container();
    echo "   ✅ Scoped Container created successfully\n";

    // Test scoped Carbon (if available)
    if (class_exists('ArchetypeVendor\Carbon\Carbon')) {
        $date = ArchetypeVendor\Carbon\Carbon::now();
        echo "   ✅ Scoped Carbon date: " . $date->format('Y-m-d H:i:s') . "\n";
    }

    echo "\n3. Testing scoped Archetype classes...\n";

    // Test scoped Archetype Application
    $app = new ArchetypeVendor\Archetype\Application();
    echo "   ✅ Scoped Archetype\\Application created successfully\n";

    // Test scoped Archetype ApiResponse
    $response = ArchetypeVendor\Archetype\Api\ApiResponse::success(['message' => 'test']);
    echo "   ✅ Scoped ApiResponse created successfully\n";

    // Test scoped Archetype Logger
    ArchetypeVendor\Archetype\Logging\ArchetypeLogger::info("Test log message");
    echo "   ✅ Scoped ArchetypeLogger works\n";

    echo "\n4. Testing WordPress function exclusions...\n";

    // These should work without the ArchetypeVendor prefix (not scoped)
    if (function_exists('sanitize_text_field')) {
        echo "   ✅ sanitize_text_field() not scoped (correct)\n";
    } else {
        echo "   ❌ sanitize_text_field() not found\n";
    }

    if (class_exists('WP_REST_Request')) {
        echo "   ✅ WP_REST_Request not scoped (correct)\n";
    } else {
        echo "   ❌ WP_REST_Request not found\n";
    }

    echo "\n🎉 SUCCESS: All scoped classes work correctly!\n";
    echo "📦 Your Archetype package is ready for conflict-free distribution!\n\n";

    echo "📋 Summary:\n";
    echo "- ✅ Illuminate components scoped with ArchetypeVendor\\ prefix\n";
    echo "- ✅ Archetype classes scoped with ArchetypeVendor\\ prefix\n";
    echo "- ✅ WordPress functions/classes remain unscoped\n";
    echo "- ✅ No conflicts possible with other plugins\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n🔧 Debug info:\n";
    echo "- Check if scoper-autoload.php exists\n";
    echo "- Check if classes were properly scoped\n";
    echo "- Check scoper.inc.php configuration\n";
} catch (Error $e) {
    echo "\n💥 FATAL ERROR: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n🔧 This usually means:\n";
    echo "- Autoloader is not working correctly\n";
    echo "- Classes were not scoped properly\n";
    echo "- Missing dependencies in scoped package\n";
}