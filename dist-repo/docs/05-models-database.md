# Models & Database

This comprehensive guide covers everything you need to know about creating and working with models in Archetype, from basic schema definition to advanced Eloquent features and relationships.

## Table of Contents

- [Model Basics](#model-basics)
- [Schema Definition](#schema-definition)
- [Model Configuration](#model-configuration)
- [Eloquent Features](#eloquent-features)
- [Relationships](#relationships)
- [Query Builder](#query-builder)
- [Data Casting](#data-casting)
- [Model Events](#model-events)
- [Advanced Schema Features](#advanced-schema-features)
- [Performance Optimization](#performance-optimization)

## Model Basics

### Creating Your First Model

Models in Archetype extend `BaseModel` and use the `#[Model]` attribute:

```php
<?php
namespace MyPlugin\Models;

use Archetype\Attributes\Model;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Blueprint;

#[Model(table: 'products', timestamps: true)]
class Product extends BaseModel
{
    // Define fillable fields for mass assignment security
    protected $fillable = [
        'name', 'description', 'price', 'sku', 'category_id'
    ];
    
    // Define the database schema
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->string('sku')->unique();
        $table->foreignId('category_id')->constrained();
        $table->boolean('is_active')->default(true);
    }
}
```

### Model Attribute Options

The `#[Model]` attribute accepts several configuration options:

```php
#[Model(
    table: 'custom_table_name',    // Optional: Override default table name
    timestamps: true,              // Optional: Add created_at/updated_at (default: true)
    connection: 'default'          // Optional: Database connection name (default: 'default')
)]
class MyModel extends BaseModel
{
    // Model definition...
}
```

### Table Naming Conventions

If you don't specify a table name, Archetype follows these rules:

```php
// Class: Product → Table: product
// Class: ProductCategory → Table: product_category
// Class: OrderItem → Table: order_item

// With table prefix 'shop_':
// Class: Product → Table: wp_shop_product (assuming wp_ is WordPress prefix)
```

## Schema Definition

### Basic Column Types

The `defineSchema()` method uses Laravel's Blueprint API for maximum flexibility:

```php
public function defineSchema(Blueprint $table): void
{
    // String columns
    $table->string('name');                     // VARCHAR(255)
    $table->string('title', 100);              // VARCHAR(100)
    $table->text('description');               // TEXT
    $table->mediumText('content');             // MEDIUMTEXT
    $table->longText('body');                  // LONGTEXT
    
    // Numeric columns
    $table->integer('quantity');               // INT
    $table->bigInteger('views');               // BIGINT
    $table->tinyInteger('status');             // TINYINT
    $table->smallInteger('order');             // SMALLINT
    $table->decimal('price', 8, 2);           // DECIMAL(8,2)
    $table->float('rating', 3, 1);            // FLOAT(3,1)
    $table->double('coordinates');             // DOUBLE
    
    // Date and time
    $table->date('birth_date');               // DATE
    $table->dateTime('published_at');         // DATETIME
    $table->timestamp('logged_at');           // TIMESTAMP
    $table->time('duration');                 // TIME
    $table->year('year_built');               // YEAR
    
    // Boolean and binary
    $table->boolean('is_active');             // TINYINT(1)
    $table->binary('file_data');              // BLOB
    
    // JSON and special types
    $table->json('metadata');                 // JSON
    $table->uuid('uuid');                     // CHAR(36)
    $table->ipAddress('ip_address');          // VARCHAR(45)
    $table->macAddress('mac_address');        // VARCHAR(17)
}
```

### Column Modifiers

Add constraints and modifiers to your columns:

```php
public function defineSchema(Blueprint $table): void
{
    // Nullability
    $table->string('name');                   // NOT NULL (default)
    $table->string('nickname')->nullable();   // NULL allowed
    
    // Default values
    $table->boolean('is_active')->default(true);
    $table->integer('views')->default(0);
    $table->timestamp('created_at')->useCurrent();
    
    // Unique constraints
    $table->string('email')->unique();
    $table->string('slug')->unique();
    
    // Auto-increment (besides primary key)
    $table->integer('order_number')->autoIncrement();
    
    // Unsigned (for numeric types)
    $table->integer('quantity')->unsigned();
    
    // Comments for documentation
    $table->string('status')->comment('Order status: pending, completed, cancelled');
}
```

### Indexes and Performance

Define indexes for better query performance:

```php
public function defineSchema(Blueprint $table): void
{
    $table->string('name');
    $table->string('email');
    $table->string('status');
    $table->timestamp('created_at');
    $table->text('content');
    
    // Single column indexes
    $table->index('email');                           // Standard index
    $table->index('status');                          // For WHERE clauses
    
    // Composite indexes (order matters!)
    $table->index(['status', 'created_at']);          // For status + date queries
    $table->index(['email', 'status']);               // For email + status queries
    
    // Unique composite indexes
    $table->unique(['email', 'tenant_id']);           // Multi-tenant unique constraint
    
    // Full-text search indexes
    $table->fullText(['name', 'content']);            // For MATCH() queries
    
    // Custom index names
    $table->index(['column1', 'column2'], 'custom_idx_name');
}
```

### Foreign Keys and Relationships

Define relationships at the database level:

```php
public function defineSchema(Blueprint $table): void
{
    // Simple foreign key
    $table->foreignId('user_id')->constrained();
    // Creates: FOREIGN KEY (user_id) REFERENCES users(id)
    
    // Foreign key with custom table
    $table->foreignId('category_id')->constrained('categories');
    
    // Foreign key with cascade options
    $table->foreignId('order_id')
          ->constrained()
          ->onUpdate('cascade')
          ->onDelete('cascade');
    
    // Foreign key with custom column
    $table->foreignId('created_by')
          ->constrained('users', 'id')
          ->onDelete('set null');
    
    // Nullable foreign key
    $table->foreignId('parent_id')
          ->nullable()
          ->constrained('categories')
          ->onDelete('set null');
}
```

## Model Configuration

### Mass Assignment Protection

Control which fields can be mass-assigned for security:

```php
class Product extends BaseModel
{
    // Fields that CAN be mass-assigned
    protected $fillable = [
        'name', 'description', 'price', 'category_id'
    ];
    
    // Alternative: Fields that CANNOT be mass-assigned
    protected $guarded = [
        'id', 'created_at', 'updated_at', 'admin_only_field'
    ];
    
    // Allow all fields (NOT recommended for production)
    protected $guarded = [];
}
```

### Primary Key Configuration

Customize primary key behavior:

```php
class Product extends BaseModel
{
    // Use auto-incrementing integer ID (default)
    public $incrementing = true;
    protected $keyType = 'int';
    
    // Use UUID as primary key
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';
    
    // Composite primary key (advanced)
    protected $primaryKey = ['user_id', 'product_id'];
}
```

### Timestamps Configuration

Control automatic timestamp handling:

```php
class Product extends BaseModel
{
    // Enable timestamps (default)
    public $timestamps = true;
    
    // Disable timestamps
    public $timestamps = false;
    
    // Custom timestamp column names
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
    
    // Custom timestamp format
    protected $dateFormat = 'Y-m-d H:i:s';
}
```

## Eloquent Features

### Basic CRUD Operations

```php
// Create
$product = Product::create([
    'name' => 'Awesome Product',
    'price' => 99.99,
    'category_id' => 1
]);

// Alternative creation methods
$product = new Product();
$product->name = 'Another Product';
$product->price = 149.99;
$product->save();

// Read
$product = Product::find(1);                    // Find by primary key
$product = Product::findOrFail(1);              // Throw exception if not found
$products = Product::all();                     // Get all records
$products = Product::where('is_active', true)->get(); // Conditional query

// Update
$product = Product::find(1);
$product->price = 89.99;
$product->save();

// Mass update
Product::where('category_id', 1)->update(['is_active' => false]);

// Delete
$product = Product::find(1);
$product->delete();

// Mass delete
Product::where('is_active', false)->delete();

// Soft delete (if using SoftDeletes trait)
$product->delete();           // Soft delete
$product->forceDelete();      // Permanent delete
$product->restore();          // Restore soft deleted
```

### Query Scopes

Create reusable query logic:

```php
class Product extends BaseModel
{
    // Local scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeExpensive($query, $threshold = 100)
    {
        return $query->where('price', '>', $threshold);
    }
    
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
    
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }
}

// Usage
$products = Product::active()->get();
$expensiveProducts = Product::active()->expensive(200)->get();
$categoryProducts = Product::inCategory(1)->published()->get();

// Chain multiple scopes
$filteredProducts = Product::active()
                          ->expensive()
                          ->inCategory(2)
                          ->orderBy('created_at', 'desc')
                          ->limit(10)
                          ->get();
```

## Relationships

### One-to-Many Relationships

```php
// Category has many Products
class Category extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->string('slug')->unique();
    }
    
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    // Relationship with conditions
    public function activeProducts()
    {
        return $this->hasMany(Product::class)->where('is_active', true);
    }
}

// Product belongs to Category
class Product extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->foreignId('category_id')->constrained();
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

// Usage
$category = Category::find(1);
$products = $category->products; // Get all products in category
$activeProducts = $category->activeProducts; // Get only active products

$product = Product::find(1);
$category = $product->category; // Get the product's category
```

### Many-to-Many Relationships

```php
// Product belongs to many Tags
class Product extends BaseModel
{
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    
    // With pivot table data
    public function tagsWithData()
    {
        return $this->belongsToMany(Tag::class)
                    ->withPivot('created_at', 'priority')
                    ->withTimestamps();
    }
    
    // Custom pivot table name
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }
}

class Tag extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->string('slug')->unique();
    }
    
    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}

// Create pivot table schema
#[Model(table: 'product_tag', timestamps: true)]
class ProductTag extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->foreignId('product_id')->constrained()->onDelete('cascade');
        $table->foreignId('tag_id')->constrained()->onDelete('cascade');
        $table->integer('priority')->default(0);
        
        // Composite unique constraint
        $table->unique(['product_id', 'tag_id']);
    }
}

// Usage
$product = Product::find(1);
$tags = $product->tags; // Get all tags for this product

// Attach/detach relationships
$product->tags()->attach($tagId);
$product->tags()->detach($tagId);
$product->tags()->sync([$tag1, $tag2, $tag3]); // Replace all relationships
```

### One-to-One Relationships

```php
class User extends BaseModel
{
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
}

class UserProfile extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('first_name');
        $table->string('last_name');
        $table->date('birth_date')->nullable();
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Usage
$user = User::find(1);
$profile = $user->profile; // Get user's profile

$profile = UserProfile::find(1);
$user = $profile->user; // Get profile's user
```

### Polymorphic Relationships

```php
// Comments can belong to Posts or Products
class Comment extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->text('content');
        $table->morphs('commentable'); // Adds commentable_id and commentable_type
    }
    
    public function commentable()
    {
        return $this->morphTo();
    }
}

class Post extends BaseModel
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Product extends BaseModel
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

// Usage
$post = Post::find(1);
$comments = $post->comments; // Get all comments for this post

$comment = Comment::find(1);
$commentable = $comment->commentable; // Get the parent (Post or Product)
```

## Query Builder

### Basic Queries

```php
// Where clauses
$products = Product::where('price', '>', 100)->get();
$products = Product::where('name', 'like', '%awesome%')->get();
$products = Product::whereIn('category_id', [1, 2, 3])->get();
$products = Product::whereBetween('price', [50, 200])->get();
$products = Product::whereNull('deleted_at')->get();
$products = Product::whereNotNull('published_at')->get();

// Multiple conditions
$products = Product::where('is_active', true)
                  ->where('price', '>', 50)
                  ->orWhere('featured', true)
                  ->get();

// Grouped conditions
$products = Product::where(function ($query) {
    $query->where('category_id', 1)
          ->orWhere('category_id', 2);
})->where('is_active', true)->get();
```

### Advanced Queries

```php
// Ordering
$products = Product::orderBy('created_at', 'desc')->get();
$products = Product::orderBy('price')->orderBy('name')->get();
$products = Product::latest()->get(); // Order by created_at desc
$products = Product::oldest()->get(); // Order by created_at asc

// Limiting and pagination
$products = Product::limit(10)->get();
$products = Product::offset(20)->limit(10)->get();
$products = Product::paginate(15); // Built-in pagination

// Grouping and aggregation
$categoryCounts = Product::selectRaw('category_id, COUNT(*) as count')
                        ->groupBy('category_id')
                        ->get();

$averagePrice = Product::avg('price');
$totalRevenue = Product::sum('price');
$maxPrice = Product::max('price');

// Raw queries (use with caution)
$products = Product::whereRaw('price > ? AND created_at > ?', [100, '2024-01-01'])->get();
```

### Eager Loading

Prevent N+1 query problems:

```php
// N+1 Problem (BAD)
$products = Product::all();
foreach ($products as $product) {
    echo $product->category->name; // Separate query for each product
}

// Eager Loading (GOOD)
$products = Product::with('category')->get();
foreach ($products as $product) {
    echo $product->category->name; // Category already loaded
}

// Multiple relationships
$products = Product::with(['category', 'tags', 'comments'])->get();

// Nested relationships
$products = Product::with('category.parent')->get();

// Conditional eager loading
$products = Product::with(['comments' => function ($query) {
    $query->where('approved', true)->orderBy('created_at', 'desc');
}])->get();

// Lazy eager loading
$products = Product::all();
$products->load('category'); // Load relationship after initial query
```

## Data Casting

### Automatic Type Casting

```php
class Product extends BaseModel
{
    protected $casts = [
        // Basic types
        'price' => 'decimal:2',        // Cast to decimal with 2 places
        'is_active' => 'boolean',      // Cast to boolean
        'quantity' => 'integer',       // Cast to integer
        'rating' => 'float',           // Cast to float
        
        // Date/time casting
        'published_at' => 'datetime',  // Cast to Carbon instance
        'created_at' => 'datetime:Y-m-d H:i:s', // Custom format
        
        // JSON casting
        'metadata' => 'array',         // JSON to array
        'settings' => 'object',        // JSON to object
        'tags' => 'collection',        // JSON to Collection
        
        // Custom casting
        'status' => ProductStatus::class, // Custom enum class
    ];
}

// Usage
$product = Product::find(1);
$product->is_active; // Returns boolean true/false, not 1/0
$product->price; // Returns decimal, not string
$product->metadata; // Returns array, not JSON string
$product->published_at; // Returns Carbon instance
```

### Custom Casts

```php
// Create custom cast class
class MoneyCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return $value ? new Money($value) : null;
    }
    
    public function set($model, string $key, $value, array $attributes)
    {
        return $value instanceof Money ? $value->getAmount() : $value;
    }
}

// Use in model
class Product extends BaseModel
{
    protected $casts = [
        'price' => MoneyCast::class
    ];
}
```

### Accessors and Mutators

```php
class Product extends BaseModel
{
    // Accessor: Modify data when retrieving
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }
    
    public function getFullNameAttribute()
    {
        return $this->name . ' (' . $this->sku . ')';
    }
    
    // Mutator: Modify data when setting
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = ucwords(strtolower($value));
    }
    
    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = round($value, 2);
    }
}

// Usage
$product = Product::find(1);
echo $product->formatted_price; // $99.99
echo $product->full_name; // Awesome Product (SKU123)

$product->name = 'AWESOME PRODUCT'; // Stored as "Awesome Product"
$product->price = 99.999; // Stored as 99.99
```

## Model Events

### Built-in Events

```php
class Product extends BaseModel
{
    protected static function boot()
    {
        parent::boot();
        
        // Before creating
        static::creating(function ($product) {
            $product->sku = $product->generateSku();
        });
        
        // After creating
        static::created(function ($product) {
            // Log product creation
            Logger::info('Product created', ['id' => $product->id]);
        });
        
        // Before updating
        static::updating(function ($product) {
            if ($product->isDirty('price')) {
                // Log price changes
                Logger::info('Price changed', [
                    'id' => $product->id,
                    'old_price' => $product->getOriginal('price'),
                    'new_price' => $product->price
                ]);
            }
        });
        
        // Before deleting
        static::deleting(function ($product) {
            // Delete related data
            $product->comments()->delete();
        });
    }
    
    private function generateSku()
    {
        return 'PROD-' . strtoupper(uniqid());
    }
}
```

### Available Events

- `creating` / `created` - Before/after creating new record
- `updating` / `updated` - Before/after updating existing record
- `saving` / `saved` - Before/after saving (create or update)
- `deleting` / `deleted` - Before/after deleting record
- `restoring` / `restored` - Before/after restoring soft-deleted record

## Advanced Schema Features

### Enum Columns

```php
public function defineSchema(Blueprint $table): void
{
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
}

// With PHP 8.1+ Enums
enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}

class Product extends BaseModel
{
    protected $casts = [
        'status' => ProductStatus::class
    ];
}
```

### JSON Columns

```php
public function defineSchema(Blueprint $table): void
{
    $table->json('metadata');
    $table->json('settings');
}

class Product extends BaseModel
{
    protected $casts = [
        'metadata' => 'array',
        'settings' => 'collection'
    ];
}

// Usage
$product = Product::create([
    'name' => 'Test Product',
    'metadata' => ['color' => 'red', 'size' => 'large'],
    'settings' => collect(['notifications' => true, 'public' => false])
]);

// Query JSON columns (MySQL 5.7+)
$products = Product::where('metadata->color', 'red')->get();
$products = Product::whereJsonContains('metadata->tags', 'electronics')->get();
```

### Virtual Generated Columns

```php
public function defineSchema(Blueprint $table): void
{
    $table->decimal('price', 10, 2);
    $table->decimal('tax_rate', 5, 4);
    
    // Virtual generated column
    $table->decimal('price_with_tax', 10, 2)
          ->virtualAs('price * (1 + tax_rate)');
    
    // Stored generated column
    $table->string('search_index')
          ->storedAs("CONCAT(name, ' ', description)");
}
```

## Performance Optimization

### Database Indexes Strategy

```php
public function defineSchema(Blueprint $table): void
{
    $table->string('name');
    $table->string('email');
    $table->string('status');
    $table->decimal('price', 10, 2);
    $table->timestamp('created_at');
    $table->boolean('is_featured');
    
    // Single column indexes for WHERE clauses
    $table->index('email');        // For user lookups
    $table->index('status');       // For status filtering
    $table->index('created_at');   // For date sorting
    
    // Composite indexes (order is crucial!)
    $table->index(['status', 'created_at']);      // For status + date queries
    $table->index(['is_featured', 'price']);      // For featured + price queries
    $table->index(['status', 'is_featured', 'price']); // For complex filters
    
    // Covering indexes (include all needed columns)
    $table->index(['status', 'created_at'], 'status_date_covering')
          ->include(['name', 'price']); // MySQL 8.0+
}
```

### Query Optimization

```php
// Use select() to limit columns
$products = Product::select(['id', 'name', 'price'])->get();

// Use chunk() for large datasets
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        // Process each product
    }
});

// Use cursor() for memory efficiency
foreach (Product::cursor() as $product) {
    // Process one product at a time
}

// Use exists() instead of count() > 0
if (Product::where('category_id', 1)->exists()) {
    // More efficient than count() > 0
}

// Use firstOrCreate() to avoid race conditions
$product = Product::firstOrCreate(
    ['sku' => 'UNIQUE-SKU'],
    ['name' => 'Product Name', 'price' => 99.99]
);
```

### Caching Strategies

```php
class Product extends BaseModel
{
    // Cache expensive queries
    public static function getPopularProducts()
    {
        return cache()->remember('popular_products', 3600, function () {
            return static::where('views', '>', 1000)
                        ->orderBy('views', 'desc')
                        ->limit(10)
                        ->get();
        });
    }
    
    // Cache model instances
    public static function findCached($id)
    {
        return cache()->remember("product.{$id}", 1800, function () use ($id) {
            return static::find($id);
        });
    }
    
    // Clear cache on model changes
    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($product) {
            cache()->forget("product.{$product->id}");
            cache()->forget('popular_products');
        });
        
        static::deleted(function ($product) {
            cache()->forget("product.{$product->id}");
            cache()->forget('popular_products');
        });
    }
}
```

---

This comprehensive guide covers all aspects of working with models and databases in Archetype. The combination of Laravel's Eloquent ORM with WordPress integration provides a powerful foundation for building complex data-driven plugins.

**Next:** Learn about [REST API Controllers](06-rest-api-controllers.md) to create powerful APIs for your models.