# Archetype WordPress Plugin Framework

![archetype](/docs/images/archetype.png)

**Version:** 1.0.5  
**Author:** Vitalii Sili  
**License:** GPL-2.0-or-later

A modern attribute-based framework for WordPress plugin development that leverages PHP 8.2+ features, Eloquent ORM, and powerful automation tools to accelerate plugin development.

## 🚀 Quick Start

```bash
composer require rolis/archetype
```

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Archetype\Application;

$app = new Application();
$app->config(
    context_paths: [__DIR__ . '/src'],
    plugin_slug: 'my-awesome-plugin',
    api_namespace: 'my-plugin/v1'
);
```

That's it! Your plugin now has automatic database management and RESTful APIs.

## 📚 Documentation

| Topic | Description |
|-------|-------------|
| **[Installation](docs/01-installation.md)** | System requirements, Composer setup, and dependencies |
| **[Quick Start](docs/02-quick-start.md)** | Get up and running in 5 minutes with your first model and API |
| **[Core Concepts](docs/03-core-concepts.md)** | Understanding attributes, component discovery, and conventions |
| **[Configuration](docs/04-configuration.md)** | Application setup, database config, and environment settings |
| **[Models & Database](docs/05-models-database.md)** | Creating models, schema definition, and Eloquent usage |
| **[REST API Controllers](docs/06-rest-api-controllers.md)** | Building APIs with attributes, routing, and permissions |
| **[Logging System](docs/07-logging-system.md)** | Debug and monitor your plugin with built-in logging |
| **[Database Migrations](docs/08-database-migrations.md)** | Automatic schema management and migration system |
| **[Exception Handling](docs/09-exception-handling.md)** | Structured error handling and custom exceptions |
| **[Advanced Features](docs/10-advanced-features.md)** | Component scanning, performance optimization, and more |
| **[Best Practices](docs/11-best-practices.md)** | Code organization, security, performance, and conventions |
| **[Troubleshooting](docs/12-troubleshooting.md)** | Common issues, debugging tools, and solutions |

## ✨ Key Features

### 🏗️ **Attribute-Based Architecture**
- Use PHP 8+ attributes to eliminate boilerplate code
- `#[Model]`, `#[RestController]`, `#[Route]` for declarative programming
- Automatic component discovery and registration

### 📊 **Eloquent ORM Integration**
- Full Laravel Eloquent capabilities in WordPress
- Automatic table creation and schema management
- Smart database migrations with change detection
- Relationships, scopes, and advanced querying

### 🔌 **RESTful API Made Easy**
- Automatic endpoint registration with attributes
- Built-in request validation and response formatting
- Permission-based access control
- Structured error handling

### 📝 **Production-Ready Tools**
- Comprehensive logging system
- Performance optimization features
- Security best practices built-in
- Migration tracking and safety

## 🎯 Use Cases

**Perfect for building:**
- **E-commerce plugins** with products, orders, and inventory
- **Membership systems** with users, subscriptions, and access control
- **Event management** with bookings, tickets, and scheduling
- **Content management** with custom post types and metadata
- **API-first plugins** that serve mobile apps or external services

## 🏃‍♂️ Example: Complete CRUD API in Minutes

**1. Create a Model:**
```php
#[Model(table: 'products', timestamps: true)]
class Product extends BaseModel
{
    protected $fillable = ['name', 'price', 'description'];
    
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->text('description')->nullable();
    }
}
```

**2. Create a Controller:**
```php
#[RestController(prefix: 'products')]
class ProductController
{
    #[Route(HttpMethod::GET, '/')]
    public function index() {
        return ApiResponse::success(Product::all());
    }
    
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request) {
        $product = Product::create($request->get_params());
        return ApiResponse::success($product, 201);
    }
}
```

**Result:** Full REST API at `/wp-json/my-plugin/v1/products/` with automatic:
- ✅ Database table creation
- ✅ CRUD endpoints
- ✅ Request validation
- ✅ Error handling
- ✅ Response formatting

## 🔧 Requirements

- **PHP:** 8.2 or higher
- **WordPress:** 5.0 or higher
- **Composer:** Latest version

## 📦 What's Included

### Core Components
- `Application` - Main framework bootstrap
- `BaseModel` - Eloquent model base class
- `ApiResponse` - Structured API responses
- `Logger` - Multi-level logging system

### Attributes
- `#[Model]` - Database model definition
- `#[RestController]` - API controller registration
- `#[Route]` - Endpoint routing

### Exception System
- `ApiNotFoundException` - 404 responses
- `ApiBadRequestException` - 400 responses
- `ApiUnauthorizedException` - 403 responses
- `ApiValidationException` - Validation errors

### Database Tools
- Automatic migrations
- Schema change detection
- Migration tracking
- Rollback safety

## 🤝 Support

- **Documentation:** Complete guides in `/docs` folder
- **Examples:** Real-world use cases and patterns
- **Troubleshooting:** Common issues and solutions
- **Best Practices:** Security, performance, and code organization

## 📄 License

GPL-2.0-or-later - Compatible with WordPress licensing requirements.

---

**Ready to supercharge your WordPress plugin development? Start with the [Installation Guide](docs/01-installation.md)!** 🚀