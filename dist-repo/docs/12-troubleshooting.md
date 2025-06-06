# Troubleshooting

Common issues and solutions when working with Archetype WordPress Plugin Framework.

## Installation Issues

### "Class not found" Errors

**Problem:** PHP Fatal error: Class 'Archetype\Application' not found

**Solutions:**
```bash
# Regenerate autoloader
composer dump-autoload

# Install with optimized autoloader
composer install --optimize-autoloader

# Check composer.json autoload section
{
    "autoload": {
        "psr-4": {
            "MyPlugin\\": "src/"
        }
    }
}
```

### Database Connection Errors

**Problem:** Database connection failed during initialization

**Solutions:**
```php
// Check WordPress database constants
define('DB_HOST', 'localhost');     // ✅ Ensure correct
define('DB_NAME', 'database_name'); // ✅ Database exists
define('DB_USER', 'username');      // ✅ User has permissions
define('DB_PASSWORD', 'password');  // ✅ Correct password

// Test connection manually
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASSWORD
    );
    echo "Connection successful";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

// Check database permissions
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
```

## Component Discovery Issues

### Models/Controllers Not Found

**Problem:** Components with attributes not being discovered

**Debug Steps:**
```php
// Enable debug logging
$app->enable_logging(true)->set_log_level(Logger::DEBUG);

// Check discovered components
$models = $app->get_models();
Logger::debug('Models found', ['count' => count($models)]);

foreach ($models as $model) {
    Logger::debug('Model discovered', [
        'class' => $model['class'],
        'table' => $model['table']
    ]);
}

// Verify paths are correct
$app->set_context_paths([
    __DIR__ . '/src',  // ✅ Correct path
    // not: '/full/absolute/path'  // ❌ Unless intentional
]);

// Check file structure
src/
├── Models/
│   └── Product.php     // ✅ Contains #[Model] attribute
└── Controllers/
    └── ProductController.php  // ✅ Contains #[RestController] attribute
```

### Attribute Syntax Issues

**Problem:** Attributes not recognized

**Common Mistakes:**
```php
// ❌ Wrong namespace
use Some\Other\Model;
#[Model()]
class Product extends BaseModel {}

// ✅ Correct namespace  
use Archetype\Attributes\Model;
#[Model()]
class Product extends BaseModel {}

// ❌ Missing attribute target
class Product extends BaseModel {}

// ✅ Attribute present
#[Model(table: 'products')]
class Product extends BaseModel {}

// ❌ Wrong inheritance
#[Model()]
class Product {} // Missing BaseModel

// ✅ Correct inheritance
#[Model()]
class Product extends BaseModel {}
```

## API Endpoint Issues

### Routes Not Working (404 Errors)

**Problem:** API endpoints return 404 Not Found

**Solutions:**
```php
// 1. Flush rewrite rules
add_action('init', function() {
    flush_rewrite_rules();
});

// 2. Check API namespace configuration
$app->set_api_namespace('my-plugin/v1');
// URLs should be: /wp-json/my-plugin/v1/endpoint

// 3. Verify controller registration
#[RestController(prefix: 'products')]
class ProductController {
    #[Route(HttpMethod::GET, '/')]
    public function index() {
        return ApiResponse::success(['test' => 'working']);
    }
}

// 4. Test endpoint manually
// Visit: /wp-json/my-plugin/v1/products/

// 5. Check WordPress REST API is working
// Visit: /wp-json/wp/v2/posts
```

### Permission Errors (403 Forbidden)

**Problem:** API endpoints return 403 Forbidden

**Debug Steps:**
```php
// Check permission class exists
class ProductPermission {
    public function canView(WP_REST_Request $request): bool {
        Logger::debug('Permission check', [
            'method' => 'canView',
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in()
        ]);
        
        return true; // Temporarily allow all for testing
    }
}

// Verify permission format in route
#[Route(
    HttpMethod::GET, 
    '/',
    permissions: ['MyPlugin\\Permissions\\ProductPermission::canView']
)]

// Test without permissions temporarily
#[Route(HttpMethod::GET, '/')]  // Remove permissions parameter
public function index() {
    return ApiResponse::success(['message' => 'No permissions required']);
}
```

## Database & Migration Issues

### Tables Not Created

**Problem:** Model tables not being created automatically

**Solutions:**
```php
// Check if Eloquent is initialized
$eloquent = $app->get_eloquent_manager();
if (!$eloquent || !$eloquent->isInitialized()) {
    Logger::error('Eloquent not initialized');
    
    // Check database configuration
    $app->set_database_config([
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASSWORD
    ]);
}

// Verify model has defineSchema method
#[Model(table: 'products')]
class Product extends BaseModel {
    public function defineSchema(Blueprint $table): void {  // ✅ Required method
        $table->string('name');
        $table->decimal('price', 10, 2);
    }
}

// Check auto-migrations setting
$app->enable_auto_migrations(true);

// Run migrations manually
$results = $app->run_migrations();
foreach ($results as $model => $result) {
    [$success, $log] = $result;
    echo "{$model}: " . ($success ? 'OK' : 'FAILED') . "\n";
    if (!$success) echo $log . "\n";
}
```

### Migration Failures

**Problem:** Migrations fail with errors

**Common Issues:**
```php
// Missing Doctrine DBAL for column changes
// Solution: Install dependency
composer require doctrine/dbal

// Database permissions insufficient
GRANT ALTER, CREATE, DROP ON database_name.* TO 'username'@'localhost';

// Column conflicts (e.g., adding NOT NULL without default)
public function defineSchema(Blueprint $table): void {
    $table->string('name');
    $table->string('email')->nullable();  // ✅ Nullable for existing data
    // OR
    $table->string('status')->default('active');  // ✅ Default for NOT NULL
}

// Large table migration timeout
// Solution: Disable auto-migrations and run manually during maintenance
$app->enable_auto_migrations(false);
```

## Logging Issues

### Logs Not Being Written

**Problem:** No log files created or logs not appearing

**Solutions:**
```php
// Check log directory permissions
$logDir = WP_CONTENT_DIR . '/uploads/archetype-logs';
if (!is_dir($logDir)) {
    wp_mkdir_p($logDir);
}
if (!is_writable($logDir)) {
    chmod($logDir, 0755);
}

// Verify logging configuration
$app->enable_logging(true)
    ->set_log_level(Logger::DEBUG)
    ->use_file_logging(true);

// Test logging manually
Logger::info('Test log message');

// Check WordPress debug settings
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Fallback to error_log if file logging fails
if (!is_writable($logDir)) {
    $app->use_file_logging(false);  // Uses error_log() instead
    Logger::info('Using error_log fallback');
}
```

### Log Files Too Large

**Problem:** Log files growing too large

**Solutions:**
```php
// Implement log rotation
class LogRotator {
    public static function rotate(string $logFile, int $maxSize = 10485760): void {
        if (file_exists($logFile) && filesize($logFile) > $maxSize) {
            $rotatedFile = $logFile . '.' . date('Y-m-d-H-i-s');
            rename($logFile, $rotatedFile);
            
            // Keep only last 5 files
            $pattern = dirname($logFile) . '/' . basename($logFile) . '.*';
            $files = glob($pattern);
            if (count($files) > 5) {
                usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                foreach (array_slice($files, 5) as $oldFile) {
                    unlink($oldFile);
                }
            }
        }
    }
}

// Use in production
if (wp_get_environment_type() === 'production') {
    $app->set_log_level(Logger::WARNING);  // Reduce log volume
}
```

## Performance Issues

### Slow API Responses

**Problem:** API endpoints responding slowly

**Debug Steps:**
```php
// Enable query logging
define('SAVEQUERIES', true);

// Monitor query performance
add_action('wp_footer', function() {
    global $wpdb;
    Logger::debug('Query performance', [
        'total_queries' => $wpdb->num_queries,
        'total_time' => timer_stop(),
        'slow_queries' => array_filter($wpdb->queries ?? [], function($query) {
            return $query[1] > 0.05; // > 50ms
        })
    ]);
});

// Check for N+1 query problems
$products = Product::with('category')->get();  // ✅ Eager load
// Instead of:
$products = Product::all();
foreach ($products as $product) {
    echo $product->category->name;  // ❌ N+1 queries
}

// Add database indexes
public function defineSchema(Blueprint $table): void {
    $table->string('name');
    $table->foreignId('category_id');
    $table->boolean('is_active');
    
    // Add indexes for common queries
    $table->index(['is_active', 'created_at']);
    $table->index('category_id');
}
```

### Memory Issues

**Problem:** PHP memory limit exceeded

**Solutions:**
```php
// Increase memory limit temporarily
ini_set('memory_limit', '256M');

// Use chunk processing for large datasets
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        // Process product
    }
});

// Use cursor for memory-efficient iteration
foreach (Product::cursor() as $product) {
    // Process one at a time
}

// Clear model cache periodically
Product::clearBootedModels();
```

## Common Error Messages

### "Table doesn't exist"

```php
// Check table prefix configuration
$app->set_database_table_prefix('myplugin_');

// Verify model table name
#[Model(table: 'products')]  // Will create: wp_myplugin_products

// Check if migration completed
$models = $app->get_models_needing_migration();
if (!empty($models)) {
    $app->run_migrations();
}
```

### "Method not allowed"

```php
// Check HTTP method in route
#[Route(HttpMethod::POST, '/')]  // ✅ POST requests only
public function store() {}

// Verify request method matches
// POST /wp-json/my-plugin/v1/products/  ✅
// GET  /wp-json/my-plugin/v1/products/  ❌ Method not allowed
```

### "Invalid JSON response"

```php
// Check for PHP errors before JSON output
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure clean output
ob_clean();  // Clear any output buffer

// Return proper API responses
return ApiResponse::success($data);  // ✅
// Not: echo json_encode($data);     // ❌
```

## Debug Tools

### Enable Debug Mode

```php
// WordPress debug configuration
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

// Archetype debug logging
$app->enable_logging(true)
    ->set_log_level(Logger::DEBUG);

// Query debugging
define('SAVEQUERIES', true);
```

### Debug Information Collector

```php
class DebugInfo {
    public static function collect(): array {
        global $wpdb;
        
        return [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'query_count' => $wpdb->num_queries ?? 0,
            'plugins_active' => get_option('active_plugins'),
            'theme_active' => get_option('template'),
            'environment' => wp_get_environment_type()
        ];
    }
}

// Log debug info
Logger::debug('Debug information', DebugInfo::collect());
```

### Testing Utilities

```php
// Test API endpoints
function test_api_endpoint($method, $path, $data = []) {
    $request = new WP_REST_Request($method, $path);
    foreach ($data as $key => $value) {
        $request->set_param($key, $value);
    }
    
    $response = rest_do_request($request);
    
    echo "Status: " . $response->get_status() . "\n";
    echo "Data: " . json_encode($response->get_data()) . "\n";
}

// Usage
test_api_endpoint('GET', '/wp-json/my-plugin/v1/products/');
```

## Getting Help

### Before Asking for Help

1. **Check logs** - Enable debug logging and check for errors
2. **Verify configuration** - Ensure all settings are correct
3. **Test in isolation** - Create minimal reproduction case
4. **Check WordPress compatibility** - Ensure WordPress version is supported
5. **Update dependencies** - Run `composer update` to get latest versions

### Useful Information to Provide

```php
// System information
echo "WordPress: " . get_bloginfo('version') . "\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Archetype: " . (new Application())->getVersion() . "\n";
echo "Environment: " . wp_get_environment_type() . "\n";

// Plugin configuration
$config = $app->get_config();
echo "Config: " . json_encode($config, JSON_PRETTY_PRINT) . "\n";

// Error details
echo "Error message: [exact error message]\n";
echo "Stack trace: [if available]\n";
echo "Steps to reproduce: [detailed steps]\n";
```

---

Most issues can be resolved by checking logs, verifying configuration, and following the debugging steps above. The framework is designed to provide helpful error messages to guide you toward solutions.

**🎉 Documentation Complete!** You now have comprehensive guides covering all aspects of the Archetype WordPress Plugin Framework.