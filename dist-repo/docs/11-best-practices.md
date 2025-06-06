# Best Practices

Essential guidelines for building secure, performant, and maintainable WordPress plugins with Archetype.

## Project Structure

### Recommended Organization

```
my-plugin/
├── my-plugin.php                 # Main plugin file
├── composer.json                 # Dependencies
├── src/                          # src or includes
│   ├── Models/                   # Database models
│   │   ├── Product.php
│   │   └── Category.php
│   ├── Controllers/              # API controllers
│   │   ├── ProductController.php
│   │   └── CategoryController.php
│   ├── Services/                 # Business logic
│   │   ├── PaymentService.php
│   │   └── EmailService.php
│   ├── Permissions/              # Access control
│   │   └── ProductPermission.php
│   └── Exceptions/               # Custom exceptions
│       └── PaymentException.php
├── assets/                       # Frontend assets
├── views/                        # Template files
├── config/                       # Configuration files
├── tests/                        # Test files
└── logs/                         # Log files (if custom path)
```

### Namespace Organization

```php
// Use consistent namespacing
namespace MyCompany\MyPlugin\Models;
namespace MyCompany\MyPlugin\Controllers;
namespace MyCompany\MyPlugin\Services;

// Avoid generic namespaces
namespace Plugin\Models;  // ❌ Too generic
namespace UniqueNamePlugin\Models; // ✅ Better
```

## Security Best Practices

### Input Validation and Sanitization

```php
class ProductController
{
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        // ✅ Always validate and sanitize input
        $data = [
            'name' => sanitize_text_field($request->get_param('name')),
            'description' => wp_kses_post($request->get_param('description')),
            'price' => floatval($request->get_param('price')),
            'email' => sanitize_email($request->get_param('email'))
        ];
        
        // ✅ Validate business rules
        if (empty($data['name'])) {
            throw new ApiBadRequestException('Name is required');
        }
        
        if ($data['price'] < 0) {
            throw new ApiValidationException('Price must be positive');
        }
        
        if ($data['email'] && !is_email($data['email'])) {
            throw new ApiValidationException('Invalid email format');
        }
        
        return Product::create($data);
    }
}
```

### Permission Checks

```php
class ProductPermission
{
    public function canCreate(WP_REST_Request $request): bool
    {
        // ✅ Multiple permission layers
        return $this->isLoggedIn() && 
               $this->hasCapability('create_products') &&
               $this->isNotRateLimited($request);
    }
    
    public function canEdit(WP_REST_Request $request): bool
    {
        $productId = $request->get_param('id');
        $product = Product::find($productId);
        
        if (!$product) {
            return false;
        }
        
        // ✅ Resource-specific permissions
        return current_user_can('manage_options') || 
               $product->created_by === get_current_user_id();
    }
    
    private function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }
    
    private function hasCapability(string $capability): bool
    {
        return current_user_can($capability);
    }
    
    private function isNotRateLimited(WP_REST_Request $request): bool
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $attempts = (int) get_transient("rate_limit_{$clientIp}");
        return $attempts < 100;
    }
}
```

### Data Protection

```php
class Product extends BaseModel
{
    // ✅ Define fillable fields explicitly
    protected $fillable = [
        'name', 'description', 'price', 'category_id'
    ];
    
    // ✅ Protect sensitive fields
    protected $guarded = [
        'id', 'created_by', 'admin_notes'
    ];
    
    // ✅ Hide sensitive data from JSON
    protected $hidden = [
        'internal_notes', 'cost_price', 'supplier_info'
    ];
    
    // ✅ Cast sensitive fields appropriately
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'encrypted:array' // If using encryption
    ];
}
```

## Performance Best Practices

### Database Optimization

```php
class Product extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->foreignId('category_id')->constrained();
        $table->boolean('is_active')->default(true);
        $table->timestamp('created_at');
        
        // ✅ Add indexes for common queries
        $table->index(['is_active', 'created_at']);  // Status + date filtering
        $table->index('category_id');                // Foreign key lookups
        $table->index('price');                      // Price sorting/filtering
        $table->fullText(['name', 'description']);   // Search functionality
    }
    
    // ✅ Use query scopes for reusable logic
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeInPriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }
}
```

### Efficient Queries

```php
class ProductController
{
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        // ✅ Use eager loading to prevent N+1 queries
        $products = Product::with(['category', 'tags'])
            ->active()
            ->select(['id', 'name', 'price', 'category_id']) // ✅ Select specific columns
            ->paginate(20); // ✅ Always paginate
        
        return ApiResponse::success($products);
    }
    
    // ✅ Use chunk() for large datasets
    public function exportProducts()
    {
        Product::active()->chunk(100, function ($products) {
            foreach ($products as $product) {
                // Process each product
                $this->processProduct($product);
            }
        });
    }
}
```

### Caching Strategies

```php
class ProductService
{
    public function getPopularProducts(int $limit = 10): Collection
    {
        $cacheKey = "popular_products_{$limit}";
        
        // ✅ Cache expensive queries
        return cache()->remember($cacheKey, HOUR_IN_SECONDS, function() use ($limit) {
            return Product::active()
                ->where('views', '>', 100)
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get();
        });
    }
    
    public function clearProductCache(Product $product): void
    {
        // ✅ Clear relevant caches when data changes
        cache()->forget("product_{$product->id}");
        cache()->forget('popular_products_10');
        cache()->tags(['products'])->flush();
    }
}
```

## Code Quality

### Model Best Practices

```php
#[Model(table: 'products', timestamps: true)]
class Product extends BaseModel
{
    // ✅ Group related properties
    protected $fillable = ['name', 'description', 'price', 'sku'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];
    protected $dates = ['published_at'];
    
    // ✅ Use descriptive method names
    public function isExpensive(): bool
    {
        return $this->price > 1000;
    }
    
    public function canBeOrdered(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }
    
    // ✅ Keep relationships simple and clear
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    
    // ✅ Use scopes for reusable query logic
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
                    ->where('stock_quantity', '>', 0);
    }
    
    // ✅ Handle events cleanly
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = $product->generateSku();
            }
        });
    }
    
    private function generateSku(): string
    {
        return 'PROD-' . strtoupper(uniqid());
    }
}
```

### Controller Best Practices

```php
#[RestController(prefix: 'products')]
class ProductController
{
    // ✅ Keep methods focused and single-purpose
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        $filters = $this->extractFilters($request);
        $products = $this->getFilteredProducts($filters);
        
        return ApiResponse::success([
            'products' => ProductTransformer::collection($products->items()),
            'pagination' => $this->getPaginationData($products)
        ]);
    }
    
    // ✅ Extract complex logic into private methods
    private function extractFilters(WP_REST_Request $request): array
    {
        return [
            'category' => $request->get_param('category'),
            'min_price' => $request->get_param('min_price'),
            'max_price' => $request->get_param('max_price'),
            'search' => $request->get_param('search')
        ];
    }
    
    private function getFilteredProducts(array $filters): LengthAwarePaginator
    {
        $query = Product::with('category')->active();
        
        if ($filters['category']) {
            $query->where('category_id', $filters['category']);
        }
        
        if ($filters['min_price']) {
            $query->where('price', '>=', $filters['min_price']);
        }
        
        if ($filters['max_price']) {
            $query->where('price', '<=', $filters['max_price']);
        }
        
        if ($filters['search']) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }
        
        return $query->paginate(20);
    }
    
    // ✅ Validate input thoroughly
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        $this->validateCreateRequest($request);
        
        $product = Product::create($this->getCreateData($request));
        
        Logger::info('Product created', ['id' => $product->id]);
        
        return ApiResponse::success(ProductTransformer::transform($product), 201);
    }
    
    private function validateCreateRequest(WP_REST_Request $request): void
    {
        $validator = new ProductValidator($request);
        $validator->validateCreate();
    }
}
```

### Service Layer Pattern

```php
// ✅ Use services for complex business logic
class OrderService
{
    public function createOrder(array $orderData, int $userId): Order
    {
        DB::transaction(function() use ($orderData, $userId) {
            $order = $this->createOrderRecord($orderData, $userId);
            $this->createOrderItems($order, $orderData['items']);
            $this->updateProductStock($orderData['items']);
            $this->sendConfirmationEmail($order);
            
            return $order;
        });
    }
    
    private function createOrderRecord(array $data, int $userId): Order
    {
        return Order::create([
            'user_id' => $userId,
            'total' => $data['total'],
            'status' => 'pending',
            'shipping_address' => $data['shipping_address']
        ]);
    }
    
    private function createOrderItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);
        }
    }
}
```

## Error Handling

### Comprehensive Error Management

```php
class ProductController
{
    #[Route(HttpMethod::GET, '/{id}')]
    public function show(WP_REST_Request $request)
    {
        try {
            $productId = $this->validateProductId($request->get_param('id'));
            $product = $this->findProduct($productId);
            
            return ApiResponse::success(ProductTransformer::transform($product));
            
        } catch (ApiException $e) {
            // ✅ Let API exceptions bubble up (framework handles them)
            throw $e;
        } catch (Exception $e) {
            // ✅ Log unexpected errors
            Logger::error('Unexpected error in product show', [
                'product_id' => $request->get_param('id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new ApiException('server_error', 'An unexpected error occurred', 500);
        }
    }
    
    private function validateProductId($id): int
    {
        if (!$id || !is_numeric($id)) {
            throw new ApiBadRequestException('Valid product ID is required');
        }
        
        return (int) $id;
    }
    
    private function findProduct(int $id): Product
    {
        $product = Product::find($id);
        
        if (!$product) {
            throw new ApiNotFoundException('Product not found');
        }
        
        return $product;
    }
}
```

## Testing Best Practices

### Comprehensive Test Coverage

```php
class ProductControllerTest extends ArchetypeTestCase
{
    private User $user;
    private Category $category;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = $this->createTestUser(['role' => 'editor']);
        $this->category = Category::factory()->create();
    }
    
    public function test_can_create_product_with_valid_data()
    {
        $this->actingAs($this->user);
        
        $productData = [
            'name' => 'Test Product',
            'price' => 99.99,
            'category_id' => $this->category->id
        ];
        
        $response = $this->makeRequest('POST', '/products', $productData);
        
        $this->assertEquals(201, $response->get_status());
        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }
    
    public function test_validates_required_fields()
    {
        $this->actingAs($this->user);
        
        $response = $this->makeRequest('POST', '/products', []);
        
        $this->assertEquals(400, $response->get_status());
        $this->assertStringContains('required', $response->get_data()['message']);
    }
    
    public function test_requires_authentication()
    {
        $response = $this->makeRequest('POST', '/products', ['name' => 'Test']);
        
        $this->assertEquals(403, $response->get_status());
    }
}
```

## Deployment Best Practices

### Environment Configuration

```php
// ✅ Environment-specific settings
$environment = wp_get_environment_type();

$config = match($environment) {
    'development' => [
        'logging' => ['level' => Logger::DEBUG],
        'auto_migrations' => true,
        'cache' => ['enabled' => false]
    ],
    'staging' => [
        'logging' => ['level' => Logger::INFO],
        'auto_migrations' => true,
        'cache' => ['enabled' => true, 'ttl' => 300]
    ],
    'production' => [
        'logging' => ['level' => Logger::WARNING],
        'auto_migrations' => false,
        'cache' => ['enabled' => true, 'ttl' => 3600]
    ],
    default => [
        'logging' => ['level' => Logger::WARNING],
        'auto_migrations' => false
    ]
};

$app->config(
    context_paths: [__DIR__ . '/src'],
    plugin_slug: 'my-plugin',
    logging_config: $config['logging'],
    auto_migrations: $config['auto_migrations']
);
```

### Production Checklist

```php
// ✅ Production deployment checklist:

// 1. Disable auto-migrations
$app->enable_auto_migrations(false);

// 2. Set appropriate log level
$app->set_log_level(Logger::WARNING);

// 3. Enable caching
$app->enable_caching(true);

// 4. Optimize autoloader
// Run: composer install --no-dev --optimize-autoloader

// 5. Set secure headers
add_action('send_headers', function() {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
});

// 6. Monitor error logs
if (wp_get_environment_type() === 'production') {
    add_action('wp_footer', function() {
        if (current_user_can('manage_options') && WP_DEBUG_LOG) {
            $error_log = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($error_log) && filesize($error_log) > 1024 * 1024) {
                echo '<!-- WARNING: Error log is over 1MB -->';
            }
        }
    });
}
```

---

Following these best practices will help you build secure, performant, and maintainable WordPress plugins with Archetype.

**Next:** Learn about [Troubleshooting](12-troubleshooting.md) for debugging and solving common issues.