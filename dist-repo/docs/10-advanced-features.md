# Advanced Features

This guide covers advanced Archetype features for component scanning, performance optimization, and framework customization.

## Component Scanning Control

### Custom Scanning Configuration

```php
// Fine-tune component discovery
$app->set_context_paths([
    __DIR__ . '/src/Models',      // Only models
    __DIR__ . '/src/Controllers', // Only controllers
    __DIR__ . '/lib/Services'     // Additional components
])
->set_deep_path_scan(3)          // Limit recursion depth
->add_exclude_folder('legacy')   // Skip specific folders
->remove_exclude_folder('docs'); // Include normally excluded folders
```

### Performance Optimization

```php
// Optimize for production
if (wp_get_environment_type() === 'production') {
    $app->set_exclude_folders([
        'tests', 'docs', 'examples', 'dev-tools',
        'scss', 'typescript', 'node_modules'
    ], false) // Don't use defaults, custom only
    ->set_deep_path_scan(2); // Shallow scanning
}
```

### Component Discovery Debugging

```php
// Debug what gets discovered
$app->enable_logging(true)->set_log_level(Logger::DEBUG);

// Check discovered components
$models = $app->get_models();
$controllers = $app->get_controller_registry();

Logger::debug('Components discovered', [
    'models' => count($models),
    'controllers' => count($controllers->getRoutes() ?? [])
]);
```

## Custom Attributes

### Creating Custom Attributes

```php
<?php
namespace MyPlugin\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Cacheable
{
    public function __construct(
        public int $ttl = 3600,
        public string $key = '',
        public array $tags = []
    ) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class RateLimit
{
    public function __construct(
        public int $requests = 100,
        public int $window = 3600
    ) {}
}
```

### Using Custom Attributes

```php
#[Model(table: 'products')]
#[Cacheable(ttl: 1800, tags: ['products', 'catalog'])]
class Product extends BaseModel
{
    // Model definition...
}

#[RestController(prefix: 'products')]
class ProductController
{
    #[Route(HttpMethod::GET, '/')]
    #[RateLimit(requests: 50, window: 300)]
    public function index(WP_REST_Request $request)
    {
        // Method implementation...
    }
}
```

### Processing Custom Attributes

```php
class CustomAttributeProcessor
{
    public function processCacheableModels(array $models): void
    {
        foreach ($models as $model) {
            $reflection = new ReflectionClass($model['class']);
            $cacheAttributes = $reflection->getAttributes(Cacheable::class);
            
            if (!empty($cacheAttributes)) {
                $cacheConfig = $cacheAttributes[0]->newInstance();
                
                // Set up caching for this model
                $this->setupModelCaching($model['class'], $cacheConfig);
            }
        }
    }
    
    private function setupModelCaching(string $modelClass, Cacheable $config): void
    {
        // Implement caching logic based on attribute configuration
        Logger::info('Setting up cache for model', [
            'model' => $modelClass,
            'ttl' => $config->ttl,
            'tags' => $config->tags
        ]);
    }
}
```

## Database Connection Management

### Multiple Database Connections

```php
// Configure additional connections
$app->set_database_config([
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD
        ],
        'analytics' => [
            'driver' => 'mysql',
            'host' => 'analytics-server',
            'database' => 'analytics_db',
            'username' => 'analytics_user',
            'password' => 'analytics_pass'
        ]
    ]
]);

// Use different connection in models
#[Model(table: 'user_events', connection: 'analytics')]
class UserEvent extends BaseModel
{
    protected $connection = 'analytics';
    
    public function defineSchema(Blueprint $table): void
    {
        $table->string('event_type');
        $table->json('event_data');
        $table->timestamp('occurred_at');
    }
}
```

### Direct Database Access

```php
// Access Eloquent components directly
$eloquent = $app->get_eloquent_manager();
$connection = $eloquent->getConnection();
$schema = $eloquent->getSchemaBuilder();

// Raw queries
$results = $connection->select('SELECT * FROM custom_table WHERE condition = ?', ['value']);

// Query builder
$data = $connection->table('products')
    ->join('categories', 'products.category_id', '=', 'categories.id')
    ->where('products.price', '>', 100)
    ->select('products.*', 'categories.name as category_name')
    ->get();

// Schema operations
$schema->create('custom_logs', function (Blueprint $table) {
    $table->id();
    $table->string('level');
    $table->text('message');
    $table->json('context')->nullable();
    $table->timestamp('created_at');
});
```

## Performance Optimization

### Query Performance Monitoring

```php
class QueryMonitor
{
    public static function enable(): void
    {
        if (!WP_DEBUG) return;
        
        DB::listen(function ($query) {
            if ($query->time > 100) { // Queries over 100ms
                Logger::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms'
                ]);
            }
        });
    }
}

// Enable in development
if (WP_DEBUG) {
    QueryMonitor::enable();
}
```

### Caching Integration

```php
class CacheManager
{
    public static function remember(string $key, int $ttl, callable $callback)
    {
        $cached = wp_cache_get($key, 'my_plugin');
        
        if ($cached !== false) {
            return $cached;
        }
        
        $result = $callback();
        wp_cache_set($key, $result, 'my_plugin', $ttl);
        
        return $result;
    }
    
    public static function tags(array $tags): self
    {
        // Implement tag-based cache invalidation
        return new self();
    }
}

// Usage in models
class Product extends BaseModel
{
    public static function getPopular(int $limit = 10)
    {
        return CacheManager::remember(
            'products_popular_' . $limit,
            HOUR_IN_SECONDS,
            fn() => static::orderBy('views', 'desc')->limit($limit)->get()
        );
    }
    
    protected static function boot()
    {
        parent::boot();
        
        // Clear cache when products change
        static::saved(function () {
            wp_cache_delete('products_popular_10', 'my_plugin');
        });
    }
}
```

### Memory Management

```php
class MemoryOptimizer
{
    public static function processLargeDataset(callable $processor): void
    {
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $items = Product::offset($offset)->limit($batchSize)->get();
            
            foreach ($items as $item) {
                $processor($item);
            }
            
            // Clear Eloquent model cache to free memory
            Product::clearBootedModels();
            
            $offset += $batchSize;
            
            // Log memory usage
            if ($offset % 10000 === 0) {
                Logger::debug('Memory usage', [
                    'processed' => $offset,
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ]);
            }
            
        } while ($items->count() === $batchSize);
    }
}
```

## Event System

### Custom Events

```php
<?php
namespace MyPlugin\Events;

class ProductCreated
{
    public function __construct(
        public Product $product,
        public int $userId
    ) {}
}

class ProductDeleted
{
    public function __construct(
        public int $productId,
        public string $productName
    ) {}
}
```

### Event Dispatching

```php
class EventDispatcher
{
    private static array $listeners = [];
    
    public static function listen(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }
    
    public static function dispatch(string $event, $data = null): void
    {
        if (!isset(self::$listeners[$event])) {
            return;
        }
        
        foreach (self::$listeners[$event] as $listener) {
            try {
                $listener($data);
            } catch (Exception $e) {
                Logger::error('Event listener error', [
                    'event' => $event,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

// Usage in models
class Product extends BaseModel
{
    protected static function boot()
    {
        parent::boot();
        
        static::created(function ($product) {
            EventDispatcher::dispatch('product.created', new ProductCreated($product, get_current_user_id()));
        });
    }
}

// Register listeners
EventDispatcher::listen('product.created', function (ProductCreated $event) {
    Logger::info('Product created', ['id' => $event->product->id]);
    
    // Send notification, update analytics, etc.
});
```

## Middleware System

### Request Middleware

```php
abstract class Middleware
{
    abstract public function handle(WP_REST_Request $request, callable $next);
}

class AuthenticationMiddleware extends Middleware
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        if (!is_user_logged_in()) {
            throw new ApiUnauthorizedException('Authentication required');
        }
        
        return $next($request);
    }
}

class RateLimitMiddleware extends Middleware
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit_{$clientIp}";
        
        $attempts = (int) get_transient($key);
        if ($attempts >= 100) {
            throw new ApiRateLimitException();
        }
        
        set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
        
        return $next($request);
    }
}
```

### Applying Middleware

```php
class ProductController
{
    private array $middleware = [
        AuthenticationMiddleware::class,
        RateLimitMiddleware::class
    ];
    
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        // Apply middleware chain
        $response = $this->applyMiddleware($request, function($request) {
            // Actual controller logic
            return $this->createProduct($request);
        });
        
        return $response;
    }
    
    private function applyMiddleware(WP_REST_Request $request, callable $controller): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function ($next, $middleware) {
                return function ($request) use ($middleware, $next) {
                    return (new $middleware())->handle($request, $next);
                };
            },
            $controller
        );
        
        return $pipeline($request);
    }
}
```

## Testing Utilities

### Test Helpers

```php
class ArchetypeTestCase extends WP_UnitTestCase
{
    protected Application $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test application
        $this->app = new Application();
        $this->app->config(
            context_paths: [__DIR__ . '/../src'],
            plugin_slug: 'test-plugin',
            auto_migrations: true,
            database_config: ['table_prefix' => 'test_']
        );
    }
    
    protected function createTestUser(array $args = []): WP_User
    {
        $defaults = [
            'user_login' => 'testuser_' . uniqid(),
            'user_email' => 'test_' . uniqid() . '@example.com',
            'user_pass' => 'password123'
        ];
        
        $userId = wp_insert_user(array_merge($defaults, $args));
        return get_user_by('id', $userId);
    }
    
    protected function actingAs(WP_User $user): self
    {
        wp_set_current_user($user->ID);
        return $this;
    }
    
    protected function makeRequest(string $method, string $path, array $params = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, $path);
        
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        
        return rest_do_request($request)->get_response_object();
    }
}
```

### Factory Pattern for Testing

```php
class ModelFactory
{
    public static function product(array $overrides = []): Product
    {
        $defaults = [
            'name' => 'Test Product ' . uniqid(),
            'price' => rand(10, 1000) / 10,
            'sku' => 'SKU-' . strtoupper(uniqid()),
            'is_active' => true
        ];
        
        return Product::create(array_merge($defaults, $overrides));
    }
    
    public static function category(array $overrides = []): Category
    {
        $defaults = [
            'name' => 'Test Category ' . uniqid(),
            'slug' => 'category-' . uniqid()
        ];
        
        return Category::create(array_merge($defaults, $overrides));
    }
}

// Usage in tests
class ProductTest extends ArchetypeTestCase
{
    public function test_can_create_product()
    {
        $category = ModelFactory::category();
        $product = ModelFactory::product(['category_id' => $category->id]);
        
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals($category->id, $product->category_id);
    }
}
```

## Configuration Management

### Environment-Based Configuration

```php
class ConfigManager
{
    public static function load(string $environment): array
    {
        $configFile = __DIR__ . "/config/{$environment}.php";
        
        if (!file_exists($configFile)) {
            throw new InvalidArgumentException("Config file not found: {$configFile}");
        }
        
        return require $configFile;
    }
    
    public static function get(string $key, $default = null)
    {
        static $config = null;
        
        if ($config === null) {
            $environment = wp_get_environment_type();
            $config = self::load($environment);
        }
        
        return data_get($config, $key, $default);
    }
}

// config/development.php
return [
    'logging' => ['level' => Logger::DEBUG],
    'cache' => ['enabled' => false],
    'features' => ['debug_toolbar' => true]
];

// config/production.php
return [
    'logging' => ['level' => Logger::WARNING],
    'cache' => ['enabled' => true, 'ttl' => 3600],
    'features' => ['debug_toolbar' => false]
];
```

---

These advanced features provide powerful customization options for complex WordPress plugin development scenarios.

**Next:** Learn about [Best Practices](11-best-practices.md) for security, performance, and maintainable code.