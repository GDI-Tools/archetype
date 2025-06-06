# Core Concepts

Understanding the fundamental concepts behind Archetype will help you build more effective and maintainable WordPress plugins. This guide covers the architecture, design patterns, and conventions that make the framework powerful yet simple to use.

## Framework Architecture

### Component-Based Design

Archetype follows a component-based architecture where each piece of functionality is self-contained and discoverable:

```
┌─────────────────────────────────────┐
│             Application             │
│   ┌─────────────────────────────┐   │
│   │        Bootstrapper         │   │
│   │  ┌─────────┐  ┌──────────┐  │   │
│   │  │ Scanner │  │ Registry │  │   │
│   │  └─────────┘  └──────────┘  │   │
│   └─────────────────────────────┘   │
│                                     │
│  ┌─────────┐  ┌──────────────────┐  │
│  │ Models  │  │   Controllers    │  │
│  │         │  │                  │  │
│  │ #[Model]│  │ #[RestController]│  │
│  └─────────┘  └──────────────────┘  │
│                                     │
│  ┌────────────────────────────────┐ │
│  │          Eloquent ORM          │ │
│  │     Database Abstraction       │ │
│  └────────────────────────────────┘ │
└─────────────────────────────────────┘
```

### Attribute-Driven Development

Archetype uses PHP 8+ attributes as the primary way to configure components:

```php
// Traditional approach (verbose)
class ProductController {
    public function __construct() {
        add_action('rest_api_init', function() {
            register_rest_route('my-plugin/v1', '/products', [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'check_permissions']
            ]);
        });
    }
}

// Archetype approach (declarative)
#[RestController(prefix: 'products')]
class ProductController {
    #[Route(HttpMethod::GET, '/')]
    public function index() { /* ... */ }
}
```

## Core Components

### 1. Application Container

The `Application` class is the central orchestrator that:
- Manages configuration
- Initializes all subsystems
- Provides access to framework services

```php
$app = new Application();

// Configuration
$app->config(/* ... */);

// Access services
$models = $app->get_models();
$eloquent = $app->get_eloquent_manager();
$migrator = $app->get_schema_migrator();
```

### 2. Component Discovery

Archetype automatically discovers components through reflection:

```php
// Directory scanning
$paths = ['/src/Models', '/src/Controllers'];

// Class analysis
foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    $attributes = $reflection->getAttributes(Model::class);
    
    if (!empty($attributes)) {
        // Register as model
    }
}
```

The discovery process:
1. **Scans** specified directories for PHP files
2. **Parses** each file to extract class names
3. **Analyzes** class attributes using reflection
4. **Registers** components with appropriate subsystems

### 3. Attribute System

Attributes provide metadata about your classes and methods:

#### Model Attribute
```php
#[Model(
    table: 'custom_table',    // Optional: custom table name
    timestamps: true,         // Optional: add created_at/updated_at
    connection: 'default'     // Optional: database connection
)]
class MyModel extends BaseModel { }
```

#### Controller Attributes
```php
#[RestController(prefix: 'api')]  // URL prefix for all routes
class MyController {
    
    #[Route(
        method: HttpMethod::POST,           // HTTP method
        path: '/items/{id}',               // URL path with parameters
        permissions: ['Class::method']     // Permission requirements
    )]
    public function updateItem() { }
}
```

## Convention over Configuration

Archetype follows the "Convention over Configuration" principle, providing sensible defaults while allowing customization when needed.

### Table Naming Conventions

```php
// Class: Product
// Default table: product (singular, snake_case)

// Class: ProductCategory  
// Default table: product_category

// With table prefix 'shop_': shop_product

#[Model(table: 'custom_products')]  // Override default
class Product extends BaseModel { }
```

### Route Naming Conventions

```php
#[RestController(prefix: 'products')]
class ProductController {
    
    // GET /wp-json/plugin/v1/products/
    #[Route(HttpMethod::GET, '/')]
    public function index() { }
    
    // POST /wp-json/plugin/v1/products/
    #[Route(HttpMethod::POST, '/')]  
    public function store() { }
    
    // GET /wp-json/plugin/v1/products/{id}
    #[Route(HttpMethod::GET, '/{id}')]
    public function show() { }
    
    // PUT /wp-json/plugin/v1/products/{id}
    #[Route(HttpMethod::PUT, '/{id}')]
    public function update() { }
    
    // DELETE /wp-json/plugin/v1/products/{id}
    #[Route(HttpMethod::DELETE, '/{id}')]
    public function destroy() { }
}
```

### Error Response Conventions

All errors follow a consistent structure:

```json
{
    "code": "error_type",
    "message": "Human readable message",
    "status": 400
}
```

## Eloquent ORM Integration

### Model Lifecycle

Understanding how Archetype integrates with Eloquent:

```php
#[Model(table: 'products')]
class Product extends BaseModel {
    
    // 1. Schema Definition (for table creation)
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->decimal('price', 10, 2);
    }
    
    // 2. Eloquent Configuration
    protected $fillable = ['name', 'price'];
    protected $casts = ['price' => 'decimal:2'];
    
    // 3. Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    // 4. Query Scopes  
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    // 5. Accessors/Mutators
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }
}
```

### Database Connection Management

Archetype manages the Eloquent connection automatically:

```php
// Framework handles connection setup
$eloquent = new EloquentManager([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'charset' => 'utf8mb4',
    'prefix' => $wpdb->prefix,
    'table_prefix' => 'myplugin_'
]);

// Models use the connection automatically
$products = Product::all(); // Uses configured connection
```

## Request/Response Lifecycle

### API Request Flow

Understanding how requests are processed:

```
1. WordPress REST API
   ↓
2. Route Matching
   ↓  
3. Permission Check
   ↓
4. Controller Method
   ↓
5. Exception Handling
   ↓
6. Response Formatting
```

### Detailed Flow Example

```php
// 1. Incoming request: POST /wp-json/my-plugin/v1/products/

// 2. Framework matches route
#[Route(HttpMethod::POST, '/', permissions: ['ProductPermission::canCreate'])]

// 3. Permission check
class ProductPermission {
    public function canCreate(WP_REST_Request $request): bool {
        return current_user_can('manage_options');
    }
}

// 4. Controller method execution
public function store(WP_REST_Request $request) {
    
    // Validation
    if (!$request->get_param('name')) {
        throw new ApiBadRequestException('Name is required');
    }
    
    // Business logic
    $product = Product::create($request->get_params());
    
    // Response
    return ApiResponse::success($product, 201);
}

// 5. Exception handling (if needed)
// Framework catches exceptions and converts to proper API responses

// 6. Response formatting
// Framework ensures consistent JSON response structure
```

## Database Migration Philosophy

Archetype takes a unique approach to database migrations:

### Traditional Migrations vs. Archetype

**Traditional Approach:**
```php
// Create migration file: 2024_01_15_create_products_table.php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('price', 10, 2);
});

// Separate migration file: 2024_01_16_add_description_to_products.php  
Schema::table('products', function (Blueprint $table) {
    $table->text('description')->nullable();
});
```

**Archetype Approach:**
```php
// Single source of truth in the model
#[Model]
class Product extends BaseModel {
    public function defineSchema(Blueprint $table): void {
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->text('description')->nullable(); // Just add this line
    }
}
// Framework automatically detects and applies the change
```

### Migration Detection

The framework uses schema hashing to detect changes:

```php
// Original schema
$oldSchema = [
    'name' => ['type' => 'string', 'length' => 255],
    'price' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2]
];
$oldHash = hash('sha256', json_encode($oldSchema));

// New schema (after adding description)
$newSchema = [
    'name' => ['type' => 'string', 'length' => 255],
    'price' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2],
    'description' => ['type' => 'text', 'nullable' => true]
];
$newHash = hash('sha256', json_encode($newSchema));

// If hashes differ, migration is needed
if ($oldHash !== $newHash) {
    $this->migrateModel($model);
}
```

## Error Handling Philosophy

### Exception Hierarchy

Archetype provides a structured exception system:

```php
ApiException (abstract)
├── ApiNotFoundException (404)
├── ApiBadRequestException (400)
├── ApiUnauthorizedException (403)
├── ApiValidationException (400)
└── Your custom exceptions...
```

### Error Response Strategy

All API errors follow the same pattern:

```php
// Throwing an exception...
throw new ApiNotFoundException('Product not found');

// Results in this response:
{
    "code": "rest_not_found",
    "message": "Product not found", 
    "status": 404
}

// Framework handles the conversion automatically
```

## Configuration Management

### Layered Configuration

Archetype uses a layered configuration approach:

```php
// 1. Framework defaults
$defaults = [
    'deep_path_scan' => 5,
    'exclude_folders' => ['vendor', 'node_modules', ...],
    'logging' => ['enabled' => true, 'level' => Logger::INFO]
];

// 2. User configuration  
$userConfig = [
    'plugin_slug' => 'my-plugin',
    'context_paths' => ['/src'],
    'logging' => ['level' => Logger::DEBUG] // Override default
];

// 3. Final merged configuration
$finalConfig = array_merge_recursive($defaults, $userConfig);
```

### Configuration Validation

The framework validates configuration at startup:

```php
public function validate(): void {
    if (empty($this->config['plugin_slug'])) {
        throw new InvalidArgumentException('plugin_slug must not be empty');
    }
    
    foreach ($this->config['context_paths'] as $path) {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("Directory not found: {$path}");
        }
    }
}
```

## Performance Considerations

### Component Discovery Optimization

```php
// Caching discovered classes
protected $classCache = [];

protected function findClassesInDirectory($directory, $excludedFolders, $maxDepth) {
    $cacheKey = $directory . ':' . implode(',', $excludedFolders) . ':' . $maxDepth;
    
    if (isset($this->classCache[$cacheKey])) {
        return $this->classCache[$cacheKey]; // Return cached result
    }
    
    // Scan directory...
    $this->classCache[$cacheKey] = $classes;
    return $classes;
}
```

### Database Query Optimization

```php
// Framework encourages efficient queries
$products = Product::with('category')    // Eager loading
                  ->where('is_active', true)
                  ->orderBy('created_at', 'desc')
                  ->limit(20)           // Pagination
                  ->get();

// Automatic indexing in schema
public function defineSchema(Blueprint $table): void {
    $table->string('name');
    $table->boolean('is_active');
    $table->timestamp('created_at');
    
    // Framework encourages index definition
    $table->index(['is_active', 'created_at']);
}
```

## Security Model

### Permission-Based Access Control

```php
// Declarative permission requirements
#[Route(
    HttpMethod::DELETE, 
    '/{id}',
    permissions: ['MyPlugin\\Security\\ProductPermission::canDelete']
)]
public function destroy(WP_REST_Request $request) {
    // Method only executes if permission check passes
}

// Permission class
class ProductPermission {
    public function canDelete(WP_REST_Request $request): bool {
        // Custom business logic
        $product = Product::find($request->get_param('id'));
        return current_user_can('delete_posts') && 
               ($product->user_id === get_current_user_id() || current_user_can('manage_options'));
    }
}
```

### Automatic Data Sanitization

```php
// Framework encourages proper sanitization
public function store(WP_REST_Request $request) {
    $product = Product::create([
        'name' => sanitize_text_field($request->get_param('name')),
        'description' => wp_kses_post($request->get_param('description')),
        'price' => floatval($request->get_param('price'))
    ]);
}
```

## Extension Points

### Custom Attributes

You can create your own attributes:

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Cacheable {
    public function __construct(
        public int $ttl = 3600,
        public string $key = ''
    ) {}
}

// Usage
#[Model]
#[Cacheable(ttl: 1800, key: 'products')]
class Product extends BaseModel { }
```

### Custom Exception Types

```php
class ApiRateLimitException extends ApiException {
    public function __construct(string $message = 'Rate limit exceeded') {
        parent::__construct('rate_limit_exceeded', $message, 429);
    }
}

// Usage
if ($this->isRateLimited($request)) {
    throw new ApiRateLimitException();
}
```

### Custom Response Types

```php
class ApiResponse {
    public static function paginated($data, $pagination): WP_REST_Response {
        return new WP_REST_Response([
            'data' => $data,
            'pagination' => $pagination,
            'meta' => [
                'timestamp' => current_time('mysql'),
                'version' => '1.0'
            ]
        ]);
    }
}
```

## Design Patterns Used

### 1. Registry Pattern
- Controllers are registered with the ControllerRegistry
- Models are registered with the ModelRegistry
- Centralized component management

### 2. Factory Pattern
- EloquentManager creates database connections
- SchemaMigrator creates migration strategies
- Flexible object creation

### 3. Observer Pattern
- Eloquent model events (creating, created, updating, updated)
- WordPress action/filter hooks
- Event-driven architecture

### 4. Strategy Pattern
- Different migration strategies based on database type
- Different logging handlers (file, error_log)
- Pluggable behavior

### 5. Decorator Pattern
- Attributes decorate classes and methods with metadata
- Middleware-like permission checking
- Non-intrusive feature addition

## WordPress Integration

### Native WordPress Compatibility

Archetype is designed to work seamlessly with WordPress:

```php
// Uses WordPress functions naturally
$name = sanitize_text_field($input);
$content = wp_kses_post($input);
$user_can = current_user_can('manage_options');

// Integrates with WordPress hooks
add_action('init', function() {
    // Archetype initialization
});

// Uses WordPress database abstraction
global $wpdb;
$prefix = $wpdb->prefix; // Respects WordPress table prefix
```

### Plugin Architecture Alignment

```php
// Follows WordPress plugin structure
your-plugin/
├── your-plugin.php     # Main plugin file (WP standard)
├── uninstall.php       # Cleanup on uninstall (WP standard)
├── src/                # Modern PHP code (Archetype)
└── vendor/             # Dependencies (Composer standard)
```

## Summary

Archetype combines modern PHP development practices with WordPress conventions to create a powerful yet familiar development experience. The key concepts to remember:

1. **Attribute-driven** - Use PHP attributes to declare intent
2. **Convention over configuration** - Sensible defaults with customization options
3. **Component discovery** - Automatic registration reduces boilerplate
4. **WordPress native** - Built for WordPress, not against it
5. **Performance focused** - Caching, indexing, and optimization built-in
6. **Security conscious** - Proper sanitization and permission handling

Understanding these concepts will help you build more effective plugins and take full advantage of what Archetype offers.

---

**Next:** Learn how to [configure your application](04-configuration.md) for different environments and use cases.