# Installation

This guide will help you install and set up the Archetype WordPress Plugin Framework in your development environment.

## System Requirements

### Minimum Requirements

- **PHP:** 8.2 or higher
- **WordPress:** 5.0 or higher
- **Composer:** Latest version
- **MySQL:** 5.7+ or **MariaDB:** 10.2+

### Recommended Requirements

- **PHP:** 8.3+ (latest stable version)
- **WordPress:** 6.0+ (latest stable version)
- **MySQL:** 8.0+ or **MariaDB:** 10.6+
- **Memory Limit:** 256MB or higher
- **Max Execution Time:** 60 seconds or higher

### PHP Extensions

The following PHP extensions are required:
- `pdo_mysql` - Database connectivity
- `json` - JSON handling
- `mbstring` - Multibyte string support
- `openssl` - Security features

Optional but recommended:
- `opcache` - Performance optimization
- `redis` or `memcached` - Caching (if using object caching)

## Installation Methods

### Method 1: Composer (Recommended)

The easiest way to install Archetype is through Composer:

```bash
# Navigate to your plugin directory
cd /path/to/your/plugin

# Install Archetype
composer require rolis/archetype
```

### Method 2: Composer with Version Constraint

For production environments, specify a version constraint:

```bash
# Install specific version
composer require rolis/archetype:^1.0

# Install exact version
composer require rolis/archetype:1.0.5
```

### Method 3: Development Installation

For contributing to the framework or using the latest features:

```bash
# Clone from GitHub
git clone https://github.com/rolis/archetype.git
cd archetype

# Install dependencies
composer install

# Link to your plugin (adjust paths accordingly)
cd /path/to/your/plugin
composer config repositories.archetype path ../archetype
composer require rolis/archetype:@dev
```

## Project Setup

### Directory Structure

Create the recommended directory structure for your plugin:

```
your-plugin/
├── your-plugin.php          # Main plugin file
├── composer.json            # Composer configuration
├── composer.lock            # Dependency lock file
├── vendor/                  # Composer dependencies (auto-generated)
├── src/                     # Your plugin source code
│   ├── Models/             # Database models
│   ├── Controllers/        # API controllers
│   ├── Services/           # Business logic
│   ├── Permissions/        # Permission classes
│   └── Exceptions/         # Custom exceptions
├── assets/                 # Frontend assets
├── views/                  # Template files
├── logs/                   # Log files (if custom path)
└── README.md              # Plugin documentation
```

### Composer Configuration

Create or update your `composer.json` file:

```json
{
    "name": "your-vendor/your-plugin",
    "description": "Your awesome WordPress plugin",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=8.2",
        "rolis/archetype": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "YourPlugin\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true
    }
}
```

Run composer install:
```bash
composer install
```

### Main Plugin File

Create your main plugin file (`your-plugin.php`):

```php
<?php
/**
 * Plugin Name: Your Awesome Plugin
 * Plugin URI: https://your-website.com/plugins/your-plugin
 * Description: A powerful plugin built with Archetype framework
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: your-plugin
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.2
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('YOUR_PLUGIN_VERSION', '1.0.0');
define('YOUR_PLUGIN_PLUGIN_FILE', __FILE__);
define('YOUR_PLUGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YOUR_PLUGIN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Initialize the framework
add_action('plugins_loaded', function() {
    try {
        $app = new \Archetype\Application();
        
        $app->config(
            context_paths: [__DIR__ . '/src'],
            plugin_slug: 'your-plugin',
            api_namespace: 'your-plugin/v1',
            auto_migrations: true
        );
        
    } catch (\Exception $e) {
        // Log error and show admin notice
        error_log('Your Plugin initialization failed: ' . $e->getMessage());
        
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Your Plugin Error:</strong> ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
});

// Plugin activation hook
register_activation_hook(__FILE__, function() {
    // Check requirements
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Your Plugin requires PHP 8.2 or higher.');
    }
    
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Your Plugin requires WordPress 5.0 or higher.');
    }
    
    // Flush rewrite rules to ensure API endpoints work
    flush_rewrite_rules();
});

// Plugin deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
```

## Verification

### Check Installation

Create a simple test to verify everything is working:

**Create `src/Models/TestModel.php`:**
```php
<?php
namespace YourPlugin\Models;

use Archetype\Attributes\Model;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Blueprint;

#[Model(table: 'test_items', timestamps: true)]
class TestModel extends BaseModel
{
    protected $fillable = ['name', 'description'];
    
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->text('description')->nullable();
    }
}
```

**Create `src/Controllers/TestController.php`:**
```php
<?php
namespace YourPlugin\Controllers;

use Archetype\Attributes\RestController;
use Archetype\Attributes\Route;
use Archetype\Http\HttpMethod;
use Archetype\Api\ApiResponse;
use YourPlugin\Models\TestModel;

#[RestController(prefix: 'test')]
class TestController
{
    #[Route(HttpMethod::GET, '/')]
    public function index()
    {
        return ApiResponse::success([
            'message' => 'Archetype is working!',
            'timestamp' => current_time('mysql'),
            'items' => TestModel::all()
        ]);
    }
}
```

### Test the Installation

1. **Activate your plugin** in WordPress admin
2. **Check for errors** in WordPress debug log
3. **Test the API endpoint:**
   ```
   GET /wp-json/your-plugin/v1/test/
   ```
4. **Verify database table** was created (check `wp_test_items` table)

### Expected Response

If everything is working correctly, you should see:

```json
{
    "message": "Archetype is working!",
    "timestamp": "2024-01-15 10:30:45",
    "items": []
}
```

## Common Installation Issues

### Issue: "Class not found" errors

**Solution:**
```bash
# Regenerate autoloader
composer dump-autoload

# Or install with optimized autoloader
composer install --optimize-autoloader
```

### Issue: Database connection errors

**Solution:**
Check your WordPress database configuration in `wp-config.php`:
```php
// Ensure these constants are properly defined
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_HOST', 'localhost');
```

### Issue: PHP version conflicts

**Solution:**
```bash
# Check current PHP version
php -v

# Update PHP through your hosting provider or server admin
# Then clear any cached autoloader files
composer clear-cache
composer install
```

### Issue: Memory limit errors

**Solution:**
Increase PHP memory limit in `wp-config.php`:
```php
ini_set('memory_limit', '256M');
```

Or contact your hosting provider to increase the limit.

### Issue: Permission errors on log files

**Solution:**
```bash
# Create logs directory with proper permissions
mkdir -p /path/to/your/plugin/logs
chmod 755 /path/to/your/plugin/logs

# Or use WordPress uploads directory (default)
# No action needed - framework handles this automatically
```

## Development Environment Setup

### Local Development

For local development, consider using:

- **Local by Flywheel** - Easy WordPress local development
- **XAMPP/WAMP/MAMP** - Traditional local server stack
- **Docker** - Containerized development environment
- **Valet** - macOS development environment

### Debugging Setup

Enable WordPress debugging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### IDE Configuration

For optimal development experience, configure your IDE:

**PHPStorm:**
1. Install WordPress plugin
2. Set PHP language level to 8.2+
3. Configure Composer integration
4. Set up code style (PSR-12 recommended)

**VS Code:**
1. Install PHP extensions
2. Configure PHP path
3. Set up Intellisense for WordPress functions

## Production Deployment

### Optimization

Before deploying to production:

```bash
# Install with production optimizations
composer install --no-dev --optimize-autoloader

# Clear any development caches
composer clear-cache
```

### Security Considerations

1. **Never commit** `vendor/` directory to version control
2. **Use** `composer.lock` for consistent deployments
3. **Set proper** file permissions (644 for files, 755 for directories)
4. **Enable** WordPress security headers
5. **Use** HTTPS for all API endpoints

### Performance Tips

1. **Enable** OPcache in production
2. **Use** object caching (Redis/Memcached) if available
3. **Configure** proper logging levels (INFO or WARNING in production)
4. **Monitor** database query performance

## Next Steps

Now that you have Archetype installed:

1. **Read the [Quick Start Guide](02-quick-start.md)** to build your first model and API
2. **Explore [Core Concepts](03-core-concepts.md)** to understand the framework architecture
3. **Review [Configuration](04-configuration.md)** for advanced setup options

## Getting Help

If you encounter issues during installation:

1. **Check the [Troubleshooting Guide](12-troubleshooting.md)**
2. **Enable debug logging** and check WordPress error logs
3. **Verify system requirements** are met
4. **Test with minimal configuration** to isolate issues

---

**Installation complete!** Ready to build something amazing? Continue with the [Quick Start Guide](02-quick-start.md). 🚀