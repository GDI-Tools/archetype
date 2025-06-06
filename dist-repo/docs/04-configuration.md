# Configuration

Archetype provides extensive configuration options to customize the framework behavior for your specific needs. This guide covers all configuration options, from basic setup to advanced customization.

## Basic Configuration

### Quick Setup Method

The simplest way to configure Archetype is using the `config()` method:

```php
use Archetype\Application;

$app = new Application();

$app->config(
    context_paths: [__DIR__ . '/src'],           // Required: Where to scan for components
    plugin_slug: 'my-awesome-plugin',           // Required: Plugin identifier
    api_namespace: 'my-plugin/v1',              // Optional: REST API namespace
    exclude_folders: ['temp', 'cache'],         // Optional: Additional folders to exclude
    use_default_exclusions: true,               // Optional: Use built-in exclusions
    deep_path_scan: 5,                         // Optional: Max directory recursion depth
    auto_migrations: true,                      // Optional: Enable automatic migrations
    logging_config: [                          // Optional: Logging configuration
        'enabled' => true,
        'level' => 6,                          // INFO level
        'path' => WP_CONTENT_DIR . '/logs'
    ],
    database_config: [                         // Optional: Database configuration
        'table_prefix' => 'shop_',
        'charset' => 'utf8mb4'
    ]
);
```

### Fluent Configuration API

For more control, use the fluent configuration methods:

```php
$app->set_context_paths([__DIR__ . '/src', __DIR__ . '/includes'])
    ->set_plugin_slug('my-plugin')
    ->set_api_namespace('my-plugin/v1')
    ->add_exclude_folder('temp')
    ->set_deep_path_scan(3)
    ->enable_auto_migrations(true)
    ->enable_logging(true)
    ->set_log_level(Logger::DEBUG)
    ->set_database_table_prefix('shop_');
```

## Configuration Options Reference

### Required Configuration

#### Context Paths
Directories where Archetype will scan for components.

```php
// Single directory
$app->set_context_paths(__DIR__ . '/src');

// Multiple directories
$app->set_context_paths([
    __DIR__ . '/src',
    __DIR__ . '/includes',
    __DIR__ . '/lib'
]);

// Add additional paths
$app->add_context_path(__DIR__ . '/custom');
```

**Best Practices:**
- Keep your code organized in a `src/` directory
- Use separate directories for different component types
- Avoid scanning large directories like `vendor/`

#### Plugin Slug
Unique identifier for your plugin.

```php
$app->set_plugin_slug('my-awesome-plugin');
```

**Rules:**
- Must be unique across all WordPress plugins
- Use lowercase letters, numbers, and hyphens only
- Should match your plugin directory name
- Used for logging namespaces and default API namespace

### API Configuration

#### API Namespace
Sets the REST API namespace for your endpoints.

```php
// Custom namespace
$app->set_api_namespace('my-plugin/v1');

// Results in URLs like: /wp-json/my-plugin/v1/endpoint

// If not set, defaults to plugin_slug
$app->set_plugin_slug('ecommerce-plugin');
// Default namespace becomes: ecommerce-plugin/v1
```

**Versioning Strategy:**
```php
// Version 1.0
$app->set_api_namespace('my-plugin/v1');

// Version 2.0 (breaking changes)
$app->set_api_namespace('my-plugin/v2'); 

// Both can coexist for backwards compatibility
```

### Component Discovery Configuration

#### Exclude Folders
Control which directories are skipped during component scanning.

```php
// Add custom exclusions (keeps defaults)
$app->set_exclude_folders(['temp', 'backup'], true);

// Replace all exclusions (no defaults)
$app->set_exclude_folders(['temp', 'backup'], false);

// Add individual folders
$app->add_exclude_folder('custom-temp');

// Remove from exclusion list
$app->remove_exclude_folder('docs'); // Now 'docs' will be scanned
```

**Default Exclusions:**
```php
$defaultExclusions = [
    // Package management
    'node_modules', 'vendor', 'bower_components', 'packages',
    
    // Build directories
    'build', 'dist', 'assets/dist', 'assets/build',
    
    // Testing
    'tests', 'test', 'testing', 'coverage', '__tests__',
    
    // Documentation
    'docs', 'doc', 'documentation', 'wiki',
    
    // Version control
    '.git', '.svn', '.hg', '.github', '.gitlab',
    
    // Editor/IDE
    '.vscode', '.idea', '.vs', '.atom',
    
    // Cache and temporary
    'cache', 'temp', 'tmp', 'log', 'logs',
    
    // WordPress specific
    'languages', 'i18n', 'l10n', 'uploads',
    
    // Miscellaneous
    'examples', 'fixtures', 'backup', 'storage'
];
```

#### Scanning Depth
Control how deep the framework searches in directory structures.

```php
// Default: 5 levels deep
$app->set_deep_path_scan(5);

// Unlimited depth (use with caution)
$app->set_deep_path_scan(0);

// Shallow scanning for performance
$app->set_deep_path_scan(2);
```

**Performance Impact:**
```php
// Example directory structure
src/
├── Models/              # Depth 1
│   └── Nested/         # Depth 2
│       └── Deep/       # Depth 3
│           └── VeryDeep/ # Depth 4 (may be skipped if max_depth = 3)
```

### Database Configuration

#### Basic Database Settings

```php
$app->set_database_config([
    'driver' => 'mysql',                    // Database driver
    'host' => DB_HOST,                      // Database host
    'database' => DB_NAME,                  // Database name
    'username' => DB_USER,                  // Database username
    'password' => DB_PASSWORD,              // Database password
    'charset' => 'utf8mb4',                 // Character set
    'collation' => 'utf8mb4_unicode_ci',    // Collation
    'prefix' => $wpdb->prefix,              // WordPress table prefix
    'table_prefix' => 'shop_'               // Additional plugin prefix
]);
```

#### Individual Database Settings

```php
// Set individual database properties
$app->set_database_driver('mysql')
    ->set_database_host('localhost')
    ->set_database_name('my_database')
    ->set_database_username('db_user')
    ->set_database_password('secure_password')
    ->set_database_charset('utf8mb4')
    ->set_database_collation('utf8mb4_unicode_ci')
    ->set_database_prefix($wpdb->prefix)
    ->set_database_table_prefix('myshop_');
```

#### Table Prefix Examples

```php
// WordPress prefix: wp_
// Plugin prefix: shop_
// Model: Product
// Final table name: wp_shop_product

$app->set_database_table_prefix('shop_');

// No additional prefix
$app->set_database_table_prefix('');
// Final table name: wp_product
```

### Logging Configuration

#### Complete Logging Setup

```php
$app->set_logging_config([
    'enabled' => true,                      // Enable/disable logging
    'level' => Logger::INFO,                // Minimum log level
    'path' => WP_CONTENT_DIR . '/logs',    // Custom log directory
    'use_file' => true                      // Write to files vs error_log only
]);
```

#### Individual Logging Settings

```php
$app->enable_logging(true)
    ->set_log_level(Logger::DEBUG)
    ->set_log_path('/custom/log/path')
    ->use_file_logging(true);
```

#### Log Levels

```php
use Archetype\Logging\Logger;

$app->set_log_level(Logger::EMERGENCY);  // 0 - Only emergencies
$app->set_log_level(Logger::ALERT);      // 1 - Alerts and above
$app->set_log_level(Logger::CRITICAL);   // 2 - Critical and above
$app->set_log_level(Logger::ERROR);      // 3 - Errors and above
$app->set_log_level(Logger::WARNING);    // 4 - Warnings and above
$app->set_log_level(Logger::NOTICE);     // 5 - Notices and above
$app->set_log_level(Logger::INFO);       // 6 - Info and above (default)
$app->set_log_level(Logger::DEBUG);      // 7 - Everything
```

#### Environment-Specific Logging

```php
// Development
if (WP_DEBUG) {
    $app->enable_logging(true)
        ->set_log_level(Logger::DEBUG)
        ->use_file_logging(true);
}

// Staging
if (wp_get_environment_type() === 'staging') {
    $app->set_log_level(Logger::INFO);
}

// Production
if (wp_get_environment_type() === 'production') {
    $app->set_log_level(Logger::WARNING)
        ->set_log_path('/secure/logs/path');
}
```

### Migration Configuration

#### Auto-Migration Settings

```php
// Enable automatic migrations (default: true)
$app->enable_auto_migrations(true);

// Disable for production safety
$app->enable_auto_migrations(false);
```

**When to Disable Auto-Migrations:**
- **Production environments** - for safety and control
- **Shared hosting** - where database changes need approval
- **Large datasets** - where migrations might timeout
- **Critical systems** - where downtime must be planned

## Environment-Specific Configuration

### Development Environment

```php
// Development configuration
if (defined('WP_DEBUG') && WP_DEBUG) {
    $app->config(
        context_paths: [__DIR__ . '/src'],
        plugin_slug: 'my-plugin-dev',
        api_namespace: 'my-plugin-dev/v1',
        auto_migrations: true,
        logging_config: [
            'enabled' => true,
            'level' => Logger::DEBUG,
            'use_file' => true
        ],
        database_config: [
            'table_prefix' => 'dev_'
        ]
    );
}
```

### Staging Environment

```php
// Staging configuration
if (wp_get_environment_type() === 'staging') {
    $app->config(
        context_paths: [__DIR__ . '/src'],
        plugin_slug: 'my-plugin-staging',
        api_namespace: 'my-plugin/v1',
        auto_migrations: true, // Test migrations here
        logging_config: [
            'enabled' => true,
            'level' => Logger::INFO,
            'path' => '/staging/logs'
        ]
    );
}
```

### Production Environment

```php
// Production configuration
if (wp_get_environment_type() === 'production') {
    $app->config(
        context_paths: [__DIR__ . '/src'],
        plugin_slug: 'my-plugin',
        api_namespace: 'my-plugin/v1',
        auto_migrations: false, // Manual control
        exclude_folders: ['tests', 'docs', 'examples'],
        deep_path_scan: 3, // Optimize performance
        logging_config: [
            'enabled' => true,
            'level' => Logger::WARNING, // Only warnings and errors
            'path' => '/secure/logs/path',
            'use_file' => true
        ]
    );
}
```

## Advanced Configuration Patterns

### Multi-Environment Configuration File

Create a separate configuration file:

```php
// config/app-config.php
return [
    'development' => [
        'plugin_slug' => 'my-plugin-dev',
        'api_namespace' => 'my-plugin-dev/v1',
        'auto_migrations' => true,
        'logging' => [
            'enabled' => true,
            'level' => 7, // DEBUG
        ],
        'database' => [
            'table_prefix' => 'dev_'
        ]
    ],
    
    'staging' => [
        'plugin_slug' => 'my-plugin-staging',
        'api_namespace' => 'my-plugin/v1',
        'auto_migrations' => true,
        'logging' => [
            'enabled' => true,
            'level' => 6, // INFO
        ]
    ],
    
    'production' => [
        'plugin_slug' => 'my-plugin',
        'api_namespace' => 'my-plugin/v1',
        'auto_migrations' => false,
        'logging' => [
            'enabled' => true,
            'level' => 4, // WARNING
        ]
    ]
];
```

```php
// main plugin file
$config = require __DIR__ . '/config/app-config.php';
$environment = wp_get_environment_type();
$envConfig = $config[$environment] ?? $config['production'];

$app = new Application();
$app->config(
    context_paths: [__DIR__ . '/src'],
    plugin_slug: $envConfig['plugin_slug'],
    api_namespace: $envConfig['api_namespace'],
    auto_migrations: $envConfig['auto_migrations'],
    logging_config: $envConfig['logging'],
    database_config: $envConfig['database'] ?? []
);
```

### Conditional Feature Configuration

```php
// Feature flags based on environment or user settings
$features = [
    'advanced_caching' => wp_get_environment_type() === 'production',
    'debug_toolbar' => WP_DEBUG,
    'beta_features' => get_option('my_plugin_enable_beta', false)
];

if ($features['advanced_caching']) {
    $app->set_database_config(['cache_enabled' => true]);
}

if ($features['debug_toolbar']) {
    $app->set_log_level(Logger::DEBUG);
}
```

### Plugin Configuration UI

Create an admin interface for configuration:

```php
// Admin settings page
add_action('admin_menu', function() {
    add_options_page(
        'My Plugin Settings',
        'My Plugin',
        'manage_options',
        'my-plugin-settings',
        'my_plugin_settings_page'
    );
});

function my_plugin_settings_page() {
    // Save settings
    if (isset($_POST['submit'])) {
        update_option('my_plugin_log_level', (int) $_POST['log_level']);
        update_option('my_plugin_auto_migrations', (bool) $_POST['auto_migrations']);
    }
    
    // Display form
    $log_level = get_option('my_plugin_log_level', Logger::INFO);
    $auto_migrations = get_option('my_plugin_auto_migrations', true);
    
    ?>
    <div class="wrap">
        <h1>My Plugin Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Log Level</th>
                    <td>
                        <select name="log_level">
                            <option value="4" <?php selected($log_level, 4); ?>>Warning</option>
                            <option value="6" <?php selected($log_level, 6); ?>>Info</option>
                            <option value="7" <?php selected($log_level, 7); ?>>Debug</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Auto Migrations</th>
                    <td>
                        <input type="checkbox" name="auto_migrations" value="1" <?php checked($auto_migrations); ?>>
                        <p class="description">Automatically apply database changes</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Use settings in configuration
$app->set_log_level(get_option('my_plugin_log_level', Logger::INFO))
    ->enable_auto_migrations(get_option('my_plugin_auto_migrations', true));
```

## Configuration Validation

### Built-in Validation

Archetype automatically validates configuration:

```php
// These will throw InvalidArgumentException
$app->set_plugin_slug(''); // Empty plugin slug
$app->set_context_paths([]); // No context paths
$app->set_context_paths(['/nonexistent/path']); // Path doesn't exist
$app->set_database_driver('invalid'); // Invalid database driver
```

### Custom Validation

Add your own validation logic:

```php
class ConfigValidator {
    public static function validate(array $config): void {
        // Check required WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            throw new InvalidArgumentException('WordPress 5.0+ required');
        }
        
        // Check PHP extensions
        if (!extension_loaded('pdo_mysql')) {
            throw new InvalidArgumentException('PDO MySQL extension required');
        }
        
        // Check file permissions
        $log_path = $config['logging']['path'] ?? '';
        if ($log_path && !is_writable(dirname($log_path))) {
            throw new InvalidArgumentException("Log directory not writable: {$log_path}");
        }
        
        // Check database connection
        if (!empty($config['database'])) {
            try {
                new PDO(
                    "mysql:host={$config['database']['host']};dbname={$config['database']['database']}",
                    $config['database']['username'],
                    $config['database']['password']
                );
            } catch (PDOException $e) {
                throw new InvalidArgumentException('Database connection failed: ' . $e->getMessage());
            }
        }
    }
}

// Use custom validation
$config = $app->get_config();
ConfigValidator::validate($config);
```

## Performance Optimization

### Optimized Production Configuration

```php
// Production-optimized settings
$app->config(
    context_paths: [__DIR__ . '/src'], // Minimal paths
    plugin_slug: 'my-plugin',
    exclude_folders: [ // Aggressive exclusion
        'tests', 'docs', 'examples', 'dev-tools',
        'scss', 'less', 'typescript', 'src-dev'
    ],
    deep_path_scan: 3, // Shallow scanning
    auto_migrations: false, // Manual control
    logging_config: [
        'enabled' => true,
        'level' => Logger::WARNING, // Minimal logging
        'use_file' => true
    ]
);
```

### Caching Configuration

```php
// Cache configuration for repeated access
$cached_config = wp_cache_get('my_plugin_config', 'my_plugin');

if (!$cached_config) {
    $cached_config = [
        'api_namespace' => 'my-plugin/v1',
        'table_prefix' => 'shop_',
        'log_level' => Logger::INFO
    ];
    
    wp_cache_set('my_plugin_config', $cached_config, 'my_plugin', HOUR_IN_SECONDS);
}

$app->set_api_namespace($cached_config['api_namespace'])
    ->set_database_table_prefix($cached_config['table_prefix'])
    ->set_log_level($cached_config['log_level']);
```

## Configuration Best Practices

### 1. Environment Separation

```php
// ✅ Good: Environment-specific configuration
$config = match(wp_get_environment_type()) {
    'development' => require 'config/dev.php',
    'staging' => require 'config/staging.php', 
    'production' => require 'config/prod.php',
    default => require 'config/prod.php' // Safe default
};
```

### 2. Secure Sensitive Data

```php
// ✅ Good: Use WordPress constants
$app->set_database_config([
    'host' => DB_HOST,
    'username' => DB_USER,
    'password' => DB_PASSWORD
]);

// ❌ Bad: Hard-coded credentials
$app->set_database_config([
    'host' => 'localhost',
    'username' => 'myuser',
    'password' => 'mypassword123'
]);
```

### 3. Validate Early

```php
// ✅ Good: Validate configuration early
register_activation_hook(__FILE__, function() {
    try {
        $app = new Application();
        $app->config(/* ... */);
        // Configuration is valid
    } catch (InvalidArgumentException $e) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Plugin configuration error: ' . $e->getMessage());
    }
});
```

### 4. Document Configuration

```php
/**
 * Plugin Configuration
 * 
 * @param array $context_paths - Directories to scan for components
 * @param string $plugin_slug - Unique plugin identifier (lowercase, hyphens only)
 * @param string $api_namespace - REST API namespace (default: plugin_slug/v1)
 * @param bool $auto_migrations - Enable automatic database migrations
 * @param array $logging_config - Logging configuration options
 */
$app->config(
    context_paths: [__DIR__ . '/src'],
    plugin_slug: 'my-plugin',
    // ... other options
);
```

### 5. Use Configuration Constants

```php
// Define constants for reusability
define('MY_PLUGIN_SLUG', 'my-awesome-plugin');
define('MY_PLUGIN_API_VERSION', 'v1');
define('MY_PLUGIN_TABLE_PREFIX', 'shop_');

$app->config(
    plugin_slug: MY_PLUGIN_SLUG,
    api_namespace: MY_PLUGIN_SLUG . '/' . MY_PLUGIN_API_VERSION,
    database_config: ['table_prefix' => MY_PLUGIN_TABLE_PREFIX]
);
```

## Troubleshooting Configuration

### Common Configuration Errors

#### Error: "plugin_slug must not be empty"
```php
// ❌ Problem
$app->config(plugin_slug: '');

// ✅ Solution
$app->config(plugin_slug: 'my-plugin');
```

#### Error: "Directory not found"
```php
// ❌ Problem
$app->set_context_paths(['/nonexistent/path']);

// ✅ Solution
$app->set_context_paths([__DIR__ . '/src']);
```

#### Error: "Database connection failed"
```php
// ❌ Problem - incorrect credentials
$app->set_database_config([
    'host' => 'wrong-host',
    'username' => 'wrong-user'
]);

// ✅ Solution - use WordPress constants
$app->set_database_config([
    'host' => DB_HOST,
    'username' => DB_USER,
    'password' => DB_PASSWORD
]);
```

### Configuration Debugging

```php
// Enable configuration debugging
$app->enable_logging(true)
    ->set_log_level(Logger::DEBUG);

// Log current configuration
Logger::debug('Application configuration', $app->get_config());

// Test individual components
try {
    $models = $app->get_models();
    Logger::info('Models discovered', ['count' => count($models)]);
} catch (Exception $e) {
    Logger::error('Model discovery failed', ['error' => $e->getMessage()]);
}
```

---

**Next:** Learn about [Models & Database](05-models-database.md) to understand how to define and work with your data structures.