# Quick Start Guide

Get up and running with Archetype in just 5 minutes! This guide will walk you through creating your first model, API endpoints, and testing everything works perfectly.

## Prerequisites ✅

- Archetype framework installed ([Installation Guide](01-installation.md))
- WordPress development environment ready
- Basic understanding of PHP 8.2+ and WordPress plugin development

## Step 1: Initialize Your Plugin

Create your main plugin file with Archetype configuration:

```php
<?php
/**
 * Plugin Name: My First Archetype Plugin
 * Version: 1.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';

use Archetype\Application;

// Initialize the framework
add_action('plugins_loaded', function() {
    $app = new Application();
    
    $app->config(
        context_paths: [__DIR__ . '/src'],           // Where to scan for components
        plugin_slug: 'my-first-plugin',             // Your plugin identifier
        api_namespace: 'my-plugin/v1',              // REST API namespace
        auto_migrations: true                        // Enable automatic migrations
    );
});
```

## Step 2: Create Your First Model

Let's create a simple blog post model. Create `src/Models/Post.php`:

```php
<?php
namespace MyPlugin\Models;

use Archetype\Attributes\Model;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Blueprint;

#[Model(table: 'posts', timestamps: true)]
class Post extends BaseModel
{
    // Define which fields can be mass-assigned
    protected $fillable = ['title', 'content', 'status', 'featured'];
    
    // Cast attributes to specific types
    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime'
    ];

    // Define the database schema
    public function defineSchema(Blueprint $table): void
    {
        $table->string('title');
        $table->longText('content');
        $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
        $table->boolean('featured')->default(false);
        $table->timestamp('published_at')->nullable();
        
        // Add indexes for better performance
        $table->index(['status', 'created_at']);
        $table->index('featured');
    }
}
```

**What happens here:**
- `#[Model]` attribute tells Archetype this is a database model
- `defineSchema()` creates the database table automatically
- `$fillable` controls which fields can be mass-assigned for security
- `$casts` automatically converts data types

## Step 3: Create Your First Controller

Create `src/Controllers/PostController.php`:

```php
<?php
namespace MyPlugin\Controllers;

use Archetype\Attributes\RestController;
use Archetype\Attributes\Route;
use Archetype\Http\HttpMethod;
use Archetype\Api\ApiResponse;
use Archetype\Exceptions\ApiNotFoundException;
use Archetype\Exceptions\ApiBadRequestException;
use MyPlugin\Models\Post;
use WP_REST_Request;

#[RestController(prefix: 'posts')]
class PostController
{
    // GET /wp-json/my-plugin/v1/posts/
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        $status = $request->get_param('status');
        $featured = $request->get_param('featured');
        
        $query = Post::query();
        
        // Filter by status if provided
        if ($status) {
            $query->where('status', $status);
        }
        
        // Filter by featured if provided
        if ($featured !== null) {
            $query->where('featured', (bool) $featured);
        }
        
        $posts = $query->orderBy('created_at', 'desc')
                      ->limit(20)
                      ->get();
        
        return ApiResponse::success([
            'posts' => $posts,
            'total' => $posts->count()
        ]);
    }
    
    // POST /wp-json/my-plugin/v1/posts/
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        // Validate required fields
        if (!$request->get_param('title')) {
            throw new ApiBadRequestException('Title is required');
        }
        
        if (!$request->get_param('content')) {
            throw new ApiBadRequestException('Content is required');
        }
        
        // Create the post
        $post = Post::create([
            'title' => sanitize_text_field($request->get_param('title')),
            'content' => wp_kses_post($request->get_param('content')),
            'status' => $request->get_param('status') ?? 'draft',
            'featured' => (bool) $request->get_param('featured'),
            'published_at' => $request->get_param('status') === 'published' ? now() : null
        ]);
        
        return ApiResponse::success($post, 201); // 201 = Created
    }
    
    // GET /wp-json/my-plugin/v1/posts/{id}
    #[Route(HttpMethod::GET, '/{id}')]
    public function show(WP_REST_Request $request)
    {
        $post = Post::find($request->get_param('id'));
        
        if (!$post) {
            throw new ApiNotFoundException('Post not found');
        }
        
        return ApiResponse::success($post);
    }
    
    // PUT /wp-json/my-plugin/v1/posts/{id}
    #[Route(HttpMethod::PUT, '/{id}')]
    public function update(WP_REST_Request $request)
    {
        $post = Post::find($request->get_param('id'));
        
        if (!$post) {
            throw new ApiNotFoundException('Post not found');
        }
        
        // Update only provided fields
        $updateData = array_filter([
            'title' => $request->get_param('title') ? sanitize_text_field($request->get_param('title')) : null,
            'content' => $request->get_param('content') ? wp_kses_post($request->get_param('content')) : null,
            'status' => $request->get_param('status'),
            'featured' => $request->has_param('featured') ? (bool) $request->get_param('featured') : null
        ], fn($value) => $value !== null);
        
        // Set published_at when publishing
        if (isset($updateData['status']) && $updateData['status'] === 'published' && $post->status !== 'published') {
            $updateData['published_at'] = now();
        }
        
        $post->update($updateData);
        
        return ApiResponse::success($post);
    }
    
    // DELETE /wp-json/my-plugin/v1/posts/{id}
    #[Route(HttpMethod::DELETE, '/{id}')]
    public function destroy(WP_REST_Request $request)
    {
        $post = Post::find($request->get_param('id'));
        
        if (!$post) {
            throw new ApiNotFoundException('Post not found');
        }
        
        $post->delete();
        
        return ApiResponse::success(null, 204); // 204 = No Content
    }
}
```

**What happens here:**
- `#[RestController]` registers this as an API controller
- `#[Route]` attributes define the HTTP endpoints
- Framework automatically handles request routing and response formatting
- Built-in exception handling provides consistent error responses

## Step 4: Test Your API

Activate your plugin and test the endpoints:

### 1. Create a Post
```bash
curl -X POST "http://your-site.local/wp-json/my-plugin/v1/posts/" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post",
    "content": "This is the content of my first post!",
    "status": "published",
    "featured": true
  }'
```

**Expected Response:**
```json
{
  "id": 1,
  "title": "My First Post",
  "content": "This is the content of my first post!",
  "status": "published",
  "featured": true,
  "published_at": "2024-01-15T10:30:45.000000Z",
  "created_at": "2024-01-15T10:30:45.000000Z",
  "updated_at": "2024-01-15T10:30:45.000000Z"
}
```

### 2. Get All Posts
```bash
curl "http://your-site.local/wp-json/my-plugin/v1/posts/"
```

### 3. Get Specific Post
```bash
curl "http://your-site.local/wp-json/my-plugin/v1/posts/1"
```

### 4. Update a Post
```bash
curl -X PUT "http://your-site.local/wp-json/my-plugin/v1/posts/1" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My Updated Post Title"
  }'
```

### 5. Delete a Post
```bash
curl -X DELETE "http://your-site.local/wp-json/my-plugin/v1/posts/1"
```

## Step 5: Verify Database Table

Check your WordPress database - you should see a new table `wp_posts` (or `wp_{prefix}_posts` if you set a table prefix) with the structure defined in your model.

The table will have these columns:
- `id` (auto-increment primary key)
- `title` (VARCHAR)
- `content` (LONGTEXT)
- `status` (ENUM: draft, published, archived)
- `featured` (BOOLEAN)
- `published_at` (TIMESTAMP, nullable)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

## What Just Happened? 🎉

In just a few minutes, you've created:

✅ **A complete REST API** with full CRUD operations  
✅ **Automatic database table** with proper schema  
✅ **Data validation** and sanitization  
✅ **Error handling** with meaningful responses  
✅ **Type casting** and data transformation  
✅ **Performance optimizations** with database indexes

## Next Steps: Add More Features

### Add Relationships

Create a `Category` model and link it to posts:

```php
// src/Models/Category.php
#[Model(table: 'categories', timestamps: true)]
class Category extends BaseModel
{
    protected $fillable = ['name', 'slug', 'description'];
    
    public function defineSchema(Blueprint $table): void
    {
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
    }
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Update Post model
class Post extends BaseModel
{
    protected $fillable = ['title', 'content', 'status', 'featured', 'category_id'];
    
    public function defineSchema(Blueprint $table): void
    {
        $table->string('title');
        $table->longText('content');
        $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
        $table->boolean('featured')->default(false);
        $table->foreignId('category_id')->nullable()->constrained('categories');
        $table->timestamp('published_at')->nullable();
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

### Add Permissions

Protect your endpoints with permission checks:

```php
// src/Permissions/PostPermission.php
namespace MyPlugin\Permissions;

use WP_REST_Request;

class PostPermission
{
    public function canCreatePosts(WP_REST_Request $request): bool
    {
        return current_user_can('publish_posts');
    }
    
    public function canEditPosts(WP_REST_Request $request): bool
    {
        return current_user_can('edit_posts');
    }
    
    public function canDeletePosts(WP_REST_Request $request): bool
    {
        return current_user_can('delete_posts');
    }
}

// Update your controller routes
#[Route(HttpMethod::POST, '/', permissions: ['MyPlugin\\Permissions\\PostPermission::canCreatePosts'])]
public function store(WP_REST_Request $request) { /* ... */ }
```

### Add Logging

Monitor your API usage:

```php
use Archetype\Logging\Logger;

public function store(WP_REST_Request $request)
{
    Logger::info('Creating new post', [
        'title' => $request->get_param('title'),
        'user_id' => get_current_user_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // ... rest of your method
}
```

## Common Quick Start Issues

### Issue: "Table already exists" error

**Solution:** Archetype handles this automatically, but if you see this error:
```php
// The framework skips table creation if it exists
// To force recreation, you can drop the table manually or change the table name
#[Model(table: 'posts_v2', timestamps: true)]
```

### Issue: API endpoints return 404

**Solutions:**
1. **Flush rewrite rules:**
   ```php
   // Add to your plugin activation hook
   register_activation_hook(__FILE__, function() {
       flush_rewrite_rules();
   });
   ```

2. **Check your API namespace:**
   ```php
   // Make sure this matches your requests
   $app->config(api_namespace: 'my-plugin/v1');
   // URLs should be: /wp-json/my-plugin/v1/posts/
   ```

3. **Verify controller discovery:**
   ```php
   // Enable debug logging to see what's discovered
   $app->set_log_level(\Archetype\Logging\Logger::DEBUG);
   ```

### Issue: Database connection errors

**Solution:** Verify your WordPress database constants:
```php
// In wp-config.php, ensure these are correct:
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_HOST', 'localhost');
```

## Understanding the Magic ✨

### Automatic Component Discovery

Archetype scans your `src/` directory and automatically finds:
- **Models** with `#[Model]` attribute → Creates database tables
- **Controllers** with `#[RestController]` attribute → Registers API routes
- **Methods** with `#[Route]` attribute → Creates endpoints

### Convention over Configuration

The framework follows sensible defaults:
- **Table names** derived from model class names
- **Route paths** follow REST conventions
- **Error handling** provides consistent JSON responses
- **Data validation** includes WordPress sanitization

### Smart Migrations

When you modify your `defineSchema()` method:
1. Framework detects the changes automatically
2. Generates safe ALTER TABLE statements
3. Applies changes without data loss
4. Records migration history for tracking

## Building Your Second Feature

Let's add a comments system to demonstrate relationships:

### 1. Create Comment Model

```php
<?php
// src/Models/Comment.php
namespace MyPlugin\Models;

use Archetype\Attributes\Model;
use Archetype\Models\BaseModel;
use Illuminate\Database\Schema\Blueprint;

#[Model(table: 'comments', timestamps: true)]
class Comment extends BaseModel
{
    protected $fillable = ['post_id', 'author_name', 'author_email', 'content', 'status'];
    
    protected $casts = [
        'post_id' => 'integer'
    ];

    public function defineSchema(Blueprint $table): void
    {
        $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
        $table->string('author_name');
        $table->email('author_email');
        $table->text('content');
        $table->enum('status', ['pending', 'approved', 'spam'])->default('pending');
        
        // Indexes for performance
        $table->index(['post_id', 'status']);
        $table->index('created_at');
    }
    
    // Relationship to Post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

### 2. Update Post Model

```php
// Add to your Post model
public function comments()
{
    return $this->hasMany(Comment::class);
}

public function approvedComments()
{
    return $this->hasMany(Comment::class)->where('status', 'approved');
}
```

### 3. Create Comment Controller

```php
<?php
// src/Controllers/CommentController.php
namespace MyPlugin\Controllers;

use Archetype\Attributes\RestController;
use Archetype\Attributes\Route;
use Archetype\Http\HttpMethod;
use Archetype\Api\ApiResponse;
use Archetype\Exceptions\ApiNotFoundException;
use Archetype\Exceptions\ApiBadRequestException;
use MyPlugin\Models\Comment;
use MyPlugin\Models\Post;
use WP_REST_Request;

#[RestController(prefix: 'comments')]
class CommentController
{
    // GET /wp-json/my-plugin/v1/comments/?post_id=1
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        $postId = $request->get_param('post_id');
        $status = $request->get_param('status') ?? 'approved';
        
        $query = Comment::with('post')->where('status', $status);
        
        if ($postId) {
            $query->where('post_id', $postId);
        }
        
        $comments = $query->orderBy('created_at', 'desc')->get();
        
        return ApiResponse::success($comments);
    }
    
    // POST /wp-json/my-plugin/v1/comments/
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        // Validation
        $required = ['post_id', 'author_name', 'author_email', 'content'];
        foreach ($required as $field) {
            if (!$request->get_param($field)) {
                throw new ApiBadRequestException("{$field} is required");
            }
        }
        
        // Verify post exists
        $post = Post::find($request->get_param('post_id'));
        if (!$post) {
            throw new ApiNotFoundException('Post not found');
        }
        
        // Validate email
        $email = sanitize_email($request->get_param('author_email'));
        if (!is_email($email)) {
            throw new ApiBadRequestException('Valid email is required');
        }
        
        $comment = Comment::create([
            'post_id' => $post->id,
            'author_name' => sanitize_text_field($request->get_param('author_name')),
            'author_email' => $email,
            'content' => sanitize_textarea_field($request->get_param('content')),
            'status' => 'pending' // Always start as pending
        ]);
        
        $comment->load('post');
        
        return ApiResponse::success($comment, 201);
    }
}
```

### 4. Test the Comments API

```bash
# Create a comment
curl -X POST "http://your-site.local/wp-json/my-plugin/v1/comments/" \
  -H "Content-Type: application/json" \
  -d '{
    "post_id": 1,
    "author_name": "John Doe",
    "author_email": "john@example.com",
    "content": "Great post! Thanks for sharing."
  }'

# Get comments for a post
curl "http://your-site.local/wp-json/my-plugin/v1/comments/?post_id=1"
```

## Performance Tips for Quick Start

### 1. Use Eager Loading
```php
// Instead of this (N+1 queries)
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->comments->count(); // Separate query for each post
}

// Do this (2 queries total)
$posts = Post::with('comments')->get();
foreach ($posts as $post) {
    echo $post->comments->count(); // Already loaded
}
```

### 2. Add Proper Indexes
```php
public function defineSchema(Blueprint $table): void
{
    // ... other columns
    
    // Index frequently queried columns
    $table->index(['status', 'created_at']); // Composite index
    $table->index('featured');                // Single column index
    $table->fullText(['title', 'content']);  // Full-text search
}
```

### 3. Use Pagination
```php
#[Route(HttpMethod::GET, '/')]
public function index(WP_REST_Request $request)
{
    $perPage = min($request->get_param('per_page') ?? 20, 100); // Max 100
    $page = max($request->get_param('page') ?? 1, 1);
    
    $posts = Post::paginate($perPage, ['*'], 'page', $page);
    
    return ApiResponse::success([
        'data' => $posts->items(),
        'pagination' => [
            'current_page' => $posts->currentPage(),
            'total_pages' => $posts->lastPage(),
            'total_items' => $posts->total(),
            'per_page' => $posts->perPage()
        ]
    ]);
}
```

## What's Next?

Now that you have a working API, explore these advanced features:

1. **[Configuration](04-configuration.md)** - Customize logging, database settings, and more
2. **[Models & Database](05-models-database.md)** - Advanced Eloquent features, relationships, and queries
3. **[REST API Controllers](06-rest-api-controllers.md)** - Permissions, validation, and advanced routing
4. **[Database Migrations](08-database-migrations.md)** - Understanding automatic schema management

## Quick Reference

### Essential Attributes
```php
#[Model(table: 'custom_table', timestamps: true)]
#[RestController(prefix: 'api')]
#[Route(HttpMethod::POST, '/endpoint')]
```

### Common Response Types
```php
return ApiResponse::success($data);           // 200 OK
return ApiResponse::success($data, 201);      // 201 Created  
return ApiResponse::success(null, 204);       // 204 No Content
throw new ApiNotFoundException('Not found');   // 404 Error
throw new ApiBadRequestException('Invalid');   // 400 Error
```

### Basic Eloquent Operations
```php
Model::create($data);           // Create
Model::find($id);              // Find by ID
Model::where('field', $value); // Query builder
$model->update($data);         // Update
$model->delete();              // Delete
```

---

**Congratulations!** 🎉 You've built your first Archetype-powered plugin with a complete REST API and automatic database management. Ready to dive deeper? Check out the [Core Concepts](03-core-concepts.md) to understand how everything works under the hood.