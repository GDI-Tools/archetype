# Database Migrations

Archetype's migration system automatically detects schema changes and applies them safely, eliminating the need for manual migration files.

## How It Works

### Automatic Detection

```php
// When you modify your model's defineSchema() method:
#[Model(table: 'products')]
class Product extends BaseModel
{
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->text('description')->nullable(); // ← Add this line
    }
}

// Framework automatically:
// 1. Detects the schema change
// 2. Generates ALTER TABLE statement
// 3. Applies the change safely
// 4. Records the migration
```

### Schema Hashing

The framework uses schema hashing to detect changes:

```php
// Original schema hash: abc123...
// Modified schema hash: def456...
// Hashes differ → Migration needed
```

## Configuration

### Enable/Disable Auto-Migrations

```php
// Enable (default)
$app->enable_auto_migrations(true);

// Disable for production safety
$app->enable_auto_migrations(false);
```

### Environment-Specific Settings

```php
// Development: Auto-migrations enabled
if (WP_DEBUG) {
    $app->enable_auto_migrations(true);
}

// Production: Manual control
if (wp_get_environment_type() === 'production') {
    $app->enable_auto_migrations(false);
}
```

## Manual Migration Control

### Check Migration Status

```php
// Check which models need migration
$modelsNeedingMigration = $app->get_models_needing_migration();

foreach ($modelsNeedingMigration as $modelClass => $model) {
    echo "Model {$modelClass} needs migration\n";
}
```

### Run Migrations Manually

```php
// Run all pending migrations
$results = $app->run_migrations();

foreach ($results as $modelClass => $result) {
    [$success, $log] = $result;
    
    if ($success) {
        echo "✓ {$modelClass} migrated successfully\n";
    } else {
        echo "✗ {$modelClass} migration failed:\n{$log}\n";
    }
}
```

## Supported Changes

### Safe Changes (Applied Automatically)

```php
public function defineSchema(Blueprint $table): void
{
    // ✅ Adding new columns
    $table->string('new_field')->nullable();
    
    // ✅ Adding indexes
    $table->index(['field1', 'field2']);
    
    // ✅ Changing nullable to NOT NULL (with default)
    $table->string('field')->default('value');
    
    // ✅ Adding constraints
    $table->unique('email');
}
```

### Complex Changes (Require Doctrine DBAL)

```php
// Install: composer require doctrine/dbal

public function defineSchema(Blueprint $table): void
{
    // ✅ Changing column types
    $table->text('description'); // was string()
    
    // ✅ Changing column length
    $table->string('name', 100); // was string('name', 255)
    
    // ✅ Modifying defaults
    $table->boolean('active')->default(false); // was default(true)
}
```

### Destructive Changes (Manual Review Recommended)

```php
// ❌ Removing columns (data loss risk)
// Remove from defineSchema() - column will be dropped

// ❌ Changing data types incompatibly
$table->integer('price'); // was decimal() - potential data loss
```

## Migration Examples

### Adding New Features

```php
// Before: Basic product model
public function defineSchema(Blueprint $table): void
{
    $table->string('name');
    $table->decimal('price', 10, 2);
}

// After: Adding inventory tracking
public function defineSchema(Blueprint $table): void
{
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->integer('stock_quantity')->default(0);      // New field
    $table->boolean('track_inventory')->default(false); // New field
    $table->timestamp('last_restocked')->nullable();    // New field
    
    // New index for inventory queries
    $table->index(['track_inventory', 'stock_quantity']);
}
```

### Performance Improvements

```php
// Adding indexes for better performance
public function defineSchema(Blueprint $table): void
{
    $table->string('email');
    $table->string('status');
    $table->timestamp('created_at');
    $table->boolean('is_featured');
    
    // Add indexes for frequent queries
    $table->index('email');                           // User lookups
    $table->index(['status', 'created_at']);          // Status + date filtering
    $table->index(['is_featured', 'created_at']);     // Featured items
}
```

### Data Structure Evolution

```php
// Evolving from simple to complex address storage
public function defineSchema(Blueprint $table): void
{
    // Phase 1: Simple fields
    $table->string('address');
    $table->string('city');
    $table->string('country');
    
    // Phase 2: Add detailed fields (migration will add these)
    $table->string('address_line_2')->nullable();
    $table->string('state_province')->nullable();
    $table->string('postal_code')->nullable();
    $table->decimal('latitude', 10, 8)->nullable();
    $table->decimal('longitude', 11, 8)->nullable();
    
    // Phase 3: Add indexing for geo queries
    $table->index(['latitude', 'longitude']);
}
```

## Migration Safety

### Backup Recommendations

```php
// Before major changes, backup your database
// This is especially important for:
// - Column type changes
// - Removing columns
// - Large datasets

// Example backup check
public function beforeMigration(): void
{
    if (wp_get_environment_type() === 'production') {
        Logger::warning('Production migration starting', [
            'model' => static::class,
            'recommendation' => 'Ensure database backup exists'
        ]);
    }
}
```

### Rollback Strategy

```php
// Archetype doesn't provide automatic rollbacks
// For critical changes, implement your own:

class ProductMigrationRollback
{
    public function rollbackToVersion1(): void
    {
        // Manual rollback logic
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['new_field1', 'new_field2']);
            $table->dropIndex(['field1', 'field2']);
        });
    }
}
```

## Migration Tracking

### Migration Records

Archetype tracks migrations in `wp_schema_migrations` table:

```sql
CREATE TABLE wp_schema_migrations (
    id bigint PRIMARY KEY AUTO_INCREMENT,
    model_class varchar(191) UNIQUE,
    schema_hash varchar(64),
    schema_definition longtext,
    last_migrated_at timestamp,
    version int DEFAULT 1,
    successful boolean DEFAULT 1,
    log text
);
```

### Viewing Migration History

```php
// Get migration history for a model
$migrator = $app->get_schema_migrator();
$record = $migrator->getMigrationRecord(Product::class);

if ($record) {
    echo "Last migrated: {$record->last_migrated_at}\n";
    echo "Version: {$record->version}\n";
    echo "Successful: " . ($record->successful ? 'Yes' : 'No') . "\n";
    echo "Log: {$record->log}\n";
}
```

## Best Practices

### Development Workflow

```php
// 1. Modify your model schema
public function defineSchema(Blueprint $table): void
{
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->string('sku')->unique(); // Added this
}

// 2. Test locally (auto-migration runs)
// 3. Deploy to staging (test migration)
// 4. Deploy to production (run manually if auto-migrations disabled)
```

### Testing Migrations

```php
// Test migration in isolated environment
class MigrationTest extends TestCase
{
    public function test_product_migration()
    {
        // Create old schema
        Schema::create('test_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
        });
        
        // Apply migration
        $product = new Product();
        $product->setTable('test_products');
        
        $migrator = new SchemaMigrator(Schema::connection()->getSchemaBuilder());
        $result = $migrator->migrateModel($product);
        
        $this->assertTrue($result[0]); // Migration successful
        
        // Verify new schema
        $this->assertTrue(Schema::hasColumn('test_products', 'sku'));
    }
}
```

### Performance Considerations

```php
// For large tables, consider maintenance windows
public function defineSchema(Blueprint $table): void
{
    $table->string('name');
    $table->decimal('price', 10, 2);
    
    // Adding index on large table - may take time
    if (Product::count() > 100000) {
        Logger::warning('Large table migration', [
            'table' => 'products',
            'count' => Product::count(),
            'action' => 'adding_index',
            'recommendation' => 'Consider maintenance window'
        ]);
    }
    
    $table->index('name'); // This might take time on large tables
}
```

## Troubleshooting

### Common Issues

**Migration not detected:**
```php
// Clear any caches
$app->get_eloquent_manager()->getConnection()->flushQueryLog();

// Check if table prefix is consistent
$app->set_database_table_prefix('consistent_prefix_');
```

**Migration fails:**
```php
// Check logs for details
Logger::debug('Migration failed', [
    'model' => Product::class,
    'error' => 'Details from migration log'
]);

// Common causes:
// - Doctrine DBAL not installed (for column changes)
// - Insufficient database permissions
// - Conflicting data (e.g., NULL values when adding NOT NULL column)
```

**Large table performance:**
```php
// For tables with millions of rows:
// 1. Disable auto-migrations
$app->enable_auto_migrations(false);

// 2. Run migrations during maintenance window
// 3. Consider breaking changes into smaller steps
```

---

The migration system provides automatic schema management while maintaining safety and flexibility for production environments.

**Next:** Learn about [Exception Handling](09-exception-handling.md) for robust error management.