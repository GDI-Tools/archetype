# Logging System

Archetype provides a comprehensive logging system to help you monitor, debug, and maintain your WordPress plugins effectively.

## Basic Usage

### Two Logger Types

```php
use Archetype\Logging\Logger;           // For your plugin
use Archetype\Logging\ArchetypeLogger;  // For framework internals

// Plugin logging
Logger::info('User created successfully');
Logger::error('Failed to save user', ['user_id' => 123]);
Logger::debug('Processing request', $request_data);

// Framework logging (automatic)
ArchetypeLogger::warning('Schema migration needed');
```

### Log Levels

```php
Logger::emergency('System is down');      // 0 - Most critical
Logger::alert('Immediate action needed'); // 1
Logger::critical('Critical error');       // 2
Logger::error('Error occurred');          // 3
Logger::warning('Warning message');       // 4
Logger::notice('Notable event');          // 5
Logger::info('Information message');      // 6 - Default
Logger::debug('Debug information');       // 7 - Most verbose
```

## Configuration

### Basic Setup

```php
$app->set_logging_config([
    'enabled' => true,
    'level' => Logger::INFO,
    'path' => WP_CONTENT_DIR . '/logs',
    'use_file' => true
]);

// Or fluent API
$app->enable_logging(true)
    ->set_log_level(Logger::DEBUG)
    ->set_log_path('/custom/path')
    ->use_file_logging(true);
```

### Environment-Specific Logging

```php
// Development
if (WP_DEBUG) {
    $app->set_log_level(Logger::DEBUG);
}

// Production
if (wp_get_environment_type() === 'production') {
    $app->set_log_level(Logger::WARNING);
}
```

## Log Files

### Default Locations
- **Plugin logs**: `/wp-content/uploads/archetype-logs/your-plugin-slug.log`
- **Framework logs**: `/wp-content/uploads/archetype-logs/archetype.log`

### Log Format
```
2024-01-15 10:30:45 - INFO - [UserController.php:25] - User created successfully
2024-01-15 10:30:46 - ERROR - [DatabaseService.php:100] - Connection failed {"host":"localhost"}
```

## Practical Examples

### Controller Logging

```php
class ProductController
{
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        Logger::info('Creating product', [
            'name' => $request->get_param('name'),
            'user_id' => get_current_user_id()
        ]);
        
        try {
            $product = Product::create($request->get_params());
            
            Logger::info('Product created successfully', [
                'product_id' => $product->id,
                'sku' => $product->sku
            ]);
            
            return ApiResponse::success($product, 201);
            
        } catch (Exception $e) {
            Logger::error('Product creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->get_params()
            ]);
            
            throw new ApiException('creation_failed', 'Could not create product', 500);
        }
    }
}
```

### Model Events Logging

```php
class Product extends BaseModel
{
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            Logger::debug('Creating product', ['name' => $product->name]);
        });
        
        static::created(function ($product) {
            Logger::info('Product created', ['id' => $product->id]);
        });
        
        static::updating(function ($product) {
            if ($product->isDirty('price')) {
                Logger::warning('Price changed', [
                    'product_id' => $product->id,
                    'old_price' => $product->getOriginal('price'),
                    'new_price' => $product->price
                ]);
            }
        });
    }
}
```

### Error Tracking

```php
class OrderService
{
    public function processPayment(Order $order)
    {
        Logger::info('Processing payment', [
            'order_id' => $order->id,
            'amount' => $order->total
        ]);
        
        try {
            $result = $this->paymentGateway->charge($order);
            
            if ($result->success) {
                Logger::info('Payment successful', [
                    'order_id' => $order->id,
                    'transaction_id' => $result->transaction_id
                ]);
            } else {
                Logger::warning('Payment failed', [
                    'order_id' => $order->id,
                    'reason' => $result->error_message
                ]);
            }
            
        } catch (Exception $e) {
            Logger::error('Payment processing error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
```

## Performance Monitoring

### Execution Time Tracking

```php
class PerformanceLogger
{
    private static $timers = [];
    
    public static function start(string $key): void
    {
        self::$timers[$key] = microtime(true);
    }
    
    public static function end(string $key): void
    {
        if (isset(self::$timers[$key])) {
            $duration = microtime(true) - self::$timers[$key];
            
            Logger::debug("Performance: {$key}", [
                'duration_ms' => round($duration * 1000, 2)
            ]);
            
            unset(self::$timers[$key]);
        }
    }
}

// Usage
PerformanceLogger::start('product_search');
$products = Product::search($query)->get();
PerformanceLogger::end('product_search');
```

### Memory Usage Tracking

```php
class MemoryLogger
{
    public static function logUsage(string $context): void
    {
        Logger::debug('Memory usage', [
            'context' => $context,
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ]);
    }
}

// Usage in bulk operations
public function bulkImport(array $data)
{
    MemoryLogger::logUsage('bulk_import_start');
    
    foreach ($data as $item) {
        // Process item
    }
    
    MemoryLogger::logUsage('bulk_import_end');
}
```

## Best Practices

### Structured Logging

```php
// ❌ Poor logging
Logger::info('User did something');

// ✅ Good logging
Logger::info('User action performed', [
    'action' => 'product_created',
    'user_id' => get_current_user_id(),
    'user_email' => wp_get_current_user()->user_email,
    'product_id' => $product->id,
    'timestamp' => current_time('mysql'),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);
```

### Security Considerations

```php
// ❌ Don't log sensitive data
Logger::info('User login', [
    'username' => $username,
    'password' => $password  // NEVER LOG PASSWORDS
]);

// ✅ Log safely
Logger::info('User login attempt', [
    'username' => $username,
    'success' => $loginSuccess,
    'ip_address' => $_SERVER['REMOTE_ADDR']
]);
```

### Log Rotation

```php
// Implement log rotation to prevent large files
class LogRotator
{
    public static function rotateIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile)) {
            return;
        }
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (filesize($logFile) > $maxSize) {
            $rotatedFile = $logFile . '.' . date('Y-m-d-H-i-s');
            rename($logFile, $rotatedFile);
            
            // Keep only last 5 rotated files
            self::cleanOldLogs(dirname($logFile));
        }
    }
    
    private static function cleanOldLogs(string $logDir): void
    {
        $pattern = $logDir . '/*.log.*';
        $files = glob($pattern);
        
        if (count($files) > 5) {
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $filesToDelete = array_slice($files, 5);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }
}
```

## Debugging Tools

### Debug Context

```php
class DebugContext
{
    public static function capture(): array
    {
        return [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'current_user' => get_current_user_id(),
            'is_admin' => is_admin(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
    }
}

// Usage
Logger::debug('Context information', DebugContext::capture());
```

### Query Logging

```php
// Enable WordPress query logging
if (WP_DEBUG) {
    define('SAVEQUERIES', true);
    
    add_action('wp_footer', function() {
        global $wpdb;
        
        Logger::debug('Database queries', [
            'total_queries' => $wpdb->num_queries,
            'total_time' => timer_stop(),
            'slow_queries' => array_filter($wpdb->queries, function($query) {
                return $query[1] > 0.05; // Queries slower than 50ms
            })
        ]);
    });
}
```

## Troubleshooting

### Common Issues

**Log files not created:**
```php
// Check directory permissions
$logDir = WP_CONTENT_DIR . '/uploads/archetype-logs';
if (!is_writable($logDir)) {
    wp_mkdir_p($logDir);
    chmod($logDir, 0755);
}
```

**Too much logging:**
```php
// Adjust log level in production
if (wp_get_environment_type() === 'production') {
    $app->set_log_level(Logger::WARNING); // Only warnings and errors
}
```

**Log file too large:**
```php
// Implement rotation (see LogRotator example above)
LogRotator::rotateIfNeeded($logFile);
```

---

The logging system helps you maintain visibility into your plugin's behavior, making debugging and monitoring much easier in both development and production environments.

**Next:** Learn about [Database Migrations](08-database-migrations.md) to understand automatic schema management.