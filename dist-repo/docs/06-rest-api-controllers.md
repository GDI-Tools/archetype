# REST API Controllers

This comprehensive guide covers creating powerful REST APIs with Archetype, from basic endpoints to advanced features like authentication, validation, and custom responses.

## Table of Contents

- [Controller Basics](#controller-basics)
- [Route Definition](#route-definition)
- [HTTP Methods](#http-methods)
- [Request Handling](#request-handling)
- [Response Formatting](#response-formatting)
- [Authentication & Permissions](#authentication--permissions)
- [Validation](#validation)
- [Error Handling](#error-handling)
- [Advanced Routing](#advanced-routing)
- [API Best Practices](#api-best-practices)

## Controller Basics

### Creating Your First Controller

Controllers use the `#[RestController]` attribute and contain methods marked with `#[Route]`:

```php
<?php
namespace MyPlugin\Controllers;

use Archetype\Attributes\RestController;
use Archetype\Attributes\Route;
use Archetype\Http\HttpMethod;
use Archetype\Api\ApiResponse;
use MyPlugin\Models\Product;
use WP_REST_Request;

#[RestController(prefix: 'products')]
class ProductController
{
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        $products = Product::all();
        return ApiResponse::success($products);
    }
    
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        $product = Product::create($request->get_params());
        return ApiResponse::success($product, 201);
    }
    
    #[Route(HttpMethod::GET, '/{id}')]
    public function show(WP_REST_Request $request)
    {
        $product = Product::find($request->get_param('id'));
        
        if (!$product) {
            return ApiResponse::error('not_found', 'Product not found', 404);
        }
        
        return ApiResponse::success($product);
    }
}
```

### URL Structure

With the above controller, your API endpoints will be:
```
GET    /wp-json/your-plugin/v1/products/     # List all products
POST   /wp-json/your-plugin/v1/products/     # Create new product
GET    /wp-json/your-plugin/v1/products/123  # Get specific product
```

### Controller Attribute Options

```php
#[RestController(
    prefix: 'api/v2/products'  // Custom prefix (optional)
)]
class ProductController
{
    // Routes will be under /wp-json/your-plugin/v1/api/v2/products/
}
```

## Route Definition

### Basic Route Syntax

```php
#[Route(
    method: HttpMethod::GET,           // HTTP method
    path: '/',                        // Route path
    permissions: []                   // Permission requirements (optional)
)]
public function methodName(WP_REST_Request $request)
{
    // Method implementation
}
```

### Route Parameters

Use curly braces to define URL parameters:

```php
class ProductController
{
    // Single parameter
    #[Route(HttpMethod::GET, '/{id}')]
    public function show(WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $product = Product::find($id);
        return ApiResponse::success($product);
    }
    
    // Multiple parameters
    #[Route(HttpMethod::GET, '/{categoryId}/products/{productId}')]
    public function showCategoryProduct(WP_REST_Request $request)
    {
        $categoryId = $request->get_param('categoryId');
        $productId = $request->get_param('productId');
        
        $product = Product::where('id', $productId)
                         ->where('category_id', $categoryId)
                         ->first();
        
        return ApiResponse::success($product);
    }
    
    // Optional parameters with default values
    #[Route(HttpMethod::GET, '/category/{categoryId?}')]
    public function byCategory(WP_REST_Request $request)
    {
        $categoryId = $request->get_param('categoryId') ?? 'all';
        
        $query = Product::query();
        if ($categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }
        
        return ApiResponse::success($query->get());
    }
}
```

### Nested Routes

Create organized API structures with nested routes:

```php
#[RestController(prefix: 'users')]
class UserController
{
    #[Route(HttpMethod::GET, '/{userId}/orders')]
    public function getUserOrders(WP_REST_Request $request)
    {
        $userId = $request->get_param('userId');
        $orders = Order::where('user_id', $userId)->get();
        return ApiResponse::success($orders);
    }
    
    #[Route(HttpMethod::POST, '/{userId}/orders')]
    public function createUserOrder(WP_REST_Request $request)
    {
        $userId = $request->get_param('userId');
        $orderData = $request->get_params();
        $orderData['user_id'] = $userId;
        
        $order = Order::create($orderData);
        return ApiResponse::success($order, 201);
    }
}
```

## HTTP Methods

### Available HTTP Methods

```php
use Archetype\Http\HttpMethod;

// Standard REST methods
HttpMethod::GET     // Retrieve data
HttpMethod::POST    // Create new resource
HttpMethod::PUT     // Update entire resource
HttpMethod::PATCH   // Partial update
HttpMethod::DELETE  // Remove resource
```

### RESTful Controller Pattern

```php
#[RestController(prefix: 'products')]
class ProductController
{
    // GET /products - List all products
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        $products = Product::paginate(20);
        return ApiResponse::success($products);
    }
    
    // POST /products - Create new product
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        $product = Product::create($request->get_params());
        return ApiResponse::success($product, 201);
    }
    
    // GET /products/{id} - Show specific product
    #[Route(HttpMethod::GET, '/{id}')]
    public function show(WP_REST_Request $request)
    {
        $product = Product::findOrFail($request->get_param('id'));
        return ApiResponse::success($product);
    }
    
    // PUT /products/{id} - Update entire product
    #[Route(HttpMethod::PUT, '/{id}')]
    public function update(WP_REST_Request $request)
    {
        $product = Product::findOrFail($request->get_param('id'));
        $product->update($request->get_params());
        return ApiResponse::success($product);
    }
    
    // PATCH /products/{id} - Partial update
    #[Route(HttpMethod::PATCH, '/{id}')]
    public function patch(WP_REST_Request $request)
    {
        $product = Product::findOrFail($request->get_param('id'));
        
        // Only update provided fields
        $updateData = array_filter($request->get_params(), function($value) {
            return $value !== null;
        });
        
        $product->update($updateData);
        return ApiResponse::success($product);
    }
    
    // DELETE /products/{id} - Remove product
    #[Route(HttpMethod::DELETE, '/{id}')]
    public function destroy(WP_REST_Request $request)
    {
        $product = Product::findOrFail($request->get_param('id'));
        $product->delete();
        
        return ApiResponse::success(null, 204); // No Content
    }
}
```

## Request Handling

### Accessing Request Data

```php
public function store(WP_REST_Request $request)
{
    // Get single parameter
    $name = $request->get_param('name');
    $price = $request->get_param('price');
    
    // Get all parameters
    $allParams = $request->get_params();
    
    // Get with default value
    $status = $request->get_param('status') ?? 'draft';
    
    // Check if parameter exists
    if ($request->has_param('email')) {
        $email = $request->get_param('email');
    }
    
    // Get headers
    $contentType = $request->get_header('content-type');
    $authorization = $request->get_header('authorization');
    
    // Get query parameters (from URL)
    $page = $request->get_query_params()['page'] ?? 1;
    
    // Get body parameters (from POST/PUT)
    $bodyParams = $request->get_body_params();
    
    // Get JSON body
    $jsonData = $request->get_json_params();
}
```

### File Uploads

```php
#[Route(HttpMethod::POST, '/upload')]
public function uploadFile(WP_REST_Request $request)
{
    // Check if file was uploaded
    $files = $request->get_file_params();
    
    if (empty($files['file'])) {
        throw new ApiBadRequestException('No file uploaded');
    }
    
    $file = $files['file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new ApiBadRequestException('File upload error');
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new ApiValidationException('Invalid file type');
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new ApiValidationException('File too large');
    }
    
    // Use WordPress upload handling
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    
    $uploadedFile = wp_handle_upload($file, ['test_form' => false]);
    
    if (isset($uploadedFile['error'])) {
        throw new ApiException('upload_failed', $uploadedFile['error'], 500);
    }
    
    return ApiResponse::success([
        'url' => $uploadedFile['url'],
        'file' => $uploadedFile['file'],
        'type' => $uploadedFile['type']
    ]);
}
```

### Query String Parameters

```php
#[Route(HttpMethod::GET, '/')]
public function index(WP_REST_Request $request)
{
    // Get pagination parameters
    $page = max(1, (int) $request->get_param('page') ?: 1);
    $perPage = min(100, max(1, (int) $request->get_param('per_page') ?: 20));
    
    // Get filtering parameters
    $status = $request->get_param('status');
    $category = $request->get_param('category');
    $search = $request->get_param('search');
    
    // Get sorting parameters
    $sortBy = $request->get_param('sort_by') ?: 'created_at';
    $sortOrder = $request->get_param('sort_order') ?: 'desc';
    
    // Build query
    $query = Product::query();
    
    if ($status) {
        $query->where('status', $status);
    }
    
    if ($category) {
        $query->where('category_id', $category);
    }
    
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
    
    // Apply sorting
    $allowedSortFields = ['name', 'price', 'created_at'];
    if (in_array($sortBy, $allowedSortFields)) {
        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
    }
    
    // Execute query with pagination
    $products = $query->paginate($perPage, ['*'], 'page', $page);
    
    return ApiResponse::success([
        'data' => $products->items(),
        'pagination' => [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total()
        ]
    ]);
}
```

## Response Formatting

### Standard API Responses

```php
use Archetype\Api\ApiResponse;

class ProductController
{
    public function index()
    {
        $products = Product::all();
        
        // Success response (200 OK)
        return ApiResponse::success($products);
    }
    
    public function store(WP_REST_Request $request)
    {
        $product = Product::create($request->get_params());
        
        // Created response (201 Created)
        return ApiResponse::success($product, 201);
    }
    
    public function destroy(WP_REST_Request $request)
    {
        $product = Product::findOrFail($request->get_param('id'));
        $product->delete();
        
        // No content response (204 No Content)
        return ApiResponse::success(null, 204);
    }
    
    public function notFound()
    {
        // Error response (404 Not Found)
        return ApiResponse::error('not_found', 'Resource not found', 404);
    }
    
    public function serverError()
    {
        // Server error response (500 Internal Server Error)
        return ApiResponse::server_error('Something went wrong');
    }
}
```

### Custom Response Formats

```php
class ProductController
{
    public function index(WP_REST_Request $request)
    {
        $products = Product::with('category')->paginate(20);
        
        // Custom response structure
        return ApiResponse::success([
            'products' => $products->items(),
            'meta' => [
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage()
            ],
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl()
            ],
            'timestamp' => current_time('mysql'),
            'version' => '1.0'
        ]);
    }
    
    public function show(WP_REST_Request $request)
    {
        $product = Product::with(['category', 'tags'])->findOrFail($request->get_param('id'));
        
        // Transform data for API response
        return ApiResponse::success([
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => [
                'amount' => $product->price,
                'currency' => 'USD',
                'formatted' => '$' . number_format($product->price, 2)
            ],
            'category' => [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug
            ],
            'tags' => $product->tags->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug
                ];
            }),
            'dates' => [
                'created' => $product->created_at->toISOString(),
                'updated' => $product->updated_at->toISOString()
            ]
        ]);
    }
}
```

### Response Headers

```php
#[Route(HttpMethod::GET, '/export')]
public function exportProducts(WP_REST_Request $request)
{
    $products = Product::all();
    
    // Generate CSV data
    $csv = $this->generateCsv($products);
    
    // Create response with custom headers
    $response = new WP_REST_Response($csv, 200);
    $response->header('Content-Type', 'text/csv');
    $response->header('Content-Disposition', 'attachment; filename="products.csv"');
    $response->header('Cache-Control', 'no-cache, must-revalidate');
    
    return $response;
}

#[Route(HttpMethod::POST, '/')]
public function store(WP_REST_Request $request)
{
    $product = Product::create($request->get_params());
    
    $response = ApiResponse::success($product, 201);
    
    // Add location header for newly created resource
    $response->header('Location', "/wp-json/my-plugin/v1/products/{$product->id}");
    
    return $response;
}
```

## Authentication & Permissions

### Permission-Based Access Control

```php
// Create permission class
<?php
namespace MyPlugin\Permissions;

use WP_REST_Request;

class ProductPermission
{
    public function canView(WP_REST_Request $request): bool
    {
        // Public endpoint - anyone can view
        return true;
    }
    
    public function canCreate(WP_REST_Request $request): bool
    {
        // Only logged-in users with specific capability
        return is_user_logged_in() && current_user_can('edit_products');
    }
    
    public function canEdit(WP_REST_Request $request): bool
    {
        // Check if user can edit this specific product
        $productId = $request->get_param('id');
        $product = Product::find($productId);
        
        if (!$product) {
            return false;
        }
        
        // Admin or product owner can edit
        return current_user_can('manage_options') || 
               $product->created_by === get_current_user_id();
    }
    
    public function canDelete(WP_REST_Request $request): bool
    {
        // Only administrators can delete products
        return current_user_can('manage_options');
    }
}
```

```php
// Use permissions in controller
#[RestController(prefix: 'products')]
class ProductController
{
    #[Route(HttpMethod::GET, '/', permissions: ['MyPlugin\\Permissions\\ProductPermission::canView'])]
    public function index(WP_REST_Request $request)
    {
        $products = Product::all();
        return ApiResponse::success($products);
    }
    
    #[Route(HttpMethod::POST, '/', permissions: ['MyPlugin\\Permissions\\ProductPermission::canCreate'])]
    public function store(WP_REST_Request $request)
    {
        $product = Product::create(array_merge(
            $request->get_params(),
            ['created_by' => get_current_user_id()]
        ));
        
        return ApiResponse::success($product, 201);
    }
    
    #[Route(HttpMethod::PUT, '/{id}', permissions: ['MyPlugin\\Permissions\\ProductPermission::canEdit'])]
    public function update(WP_REST_Request $request)
    {
        $product = Product::findOrFail($request->get_param('id'));
        $product->update($request->get_params());
        
        return ApiResponse::success($product);
    }
}
```

### Authentication Methods

```php
class AuthPermission
{
    // WordPress cookie authentication (for logged-in users)
    public function requireLogin(WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }
    
    // API key authentication
    public function requireApiKey(WP_REST_Request $request): bool
    {
        $apiKey = $request->get_header('X-API-Key');
        
        if (!$apiKey) {
            return false;
        }
        
        // Validate API key against database
        $validKey = get_option('my_plugin_api_key');
        return hash_equals($validKey, $apiKey);
    }
    
    // JWT token authentication
    public function requireJwtToken(WP_REST_Request $request): bool
    {
        $authorization = $request->get_header('Authorization');
        
        if (!$authorization || !preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        
        try {
            // Validate JWT token (requires JWT library)
            $decoded = JWT::decode($token, $this->getJwtSecret(), ['HS256']);
            
            // Set current user context if needed
            wp_set_current_user($decoded->user_id);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Role-based permissions
    public function requireRole(WP_REST_Request $request, string $role): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array($role, $user->roles);
    }
}
```

### Advanced Permission Patterns

```php
class OrderPermission
{
    public function canViewOrder(WP_REST_Request $request): bool
    {
        $orderId = $request->get_param('id');
        $order = Order::find($orderId);
        
        if (!$order) {
            return false;
        }
        
        // Multiple permission levels
        return $this->isAdmin() || 
               $this->isOrderOwner($order) || 
               $this->isStoreManager();
    }
    
    private function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }
    
    private function isOrderOwner(Order $order): bool
    {
        return is_user_logged_in() && 
               $order->user_id === get_current_user_id();
    }
    
    private function isStoreManager(): bool
    {
        return current_user_can('manage_woocommerce') || 
               current_user_can('edit_shop_orders');
    }
}
```

## Validation

### Request Validation

```php
use Archetype\Exceptions\ApiValidationException;
use Archetype\Exceptions\ApiBadRequestException;

class ProductController
{
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        // Validate required fields
        $this->validateRequired($request, ['name', 'price', 'category_id']);
        
        // Validate data types and formats
        $this->validateProduct($request);
        
        // Create product if validation passes
        $product = Product::create([
            'name' => sanitize_text_field($request->get_param('name')),
            'description' => wp_kses_post($request->get_param('description')),
            'price' => floatval($request->get_param('price')),
            'category_id' => intval($request->get_param('category_id'))
        ]);
        
        return ApiResponse::success($product, 201);
    }
    
    private function validateRequired(WP_REST_Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (!$request->has_param($field) || empty($request->get_param($field))) {
                throw new ApiBadRequestException("Field '{$field}' is required");
            }
        }
    }
    
    private function validateProduct(WP_REST_Request $request): void
    {
        // Validate name
        $name = $request->get_param('name');
        if (strlen($name) < 3 || strlen($name) > 255) {
            throw new ApiValidationException('Product name must be between 3 and 255 characters');
        }
        
        // Validate price
        $price = $request->get_param('price');
        if (!is_numeric($price) || $price < 0) {
            throw new ApiValidationException('Price must be a positive number');
        }
        
        // Validate category exists
        $categoryId = $request->get_param('category_id');
        if (!Category::find($categoryId)) {
            throw new ApiValidationException('Invalid category ID');
        }
        
        // Validate unique SKU if provided
        $sku = $request->get_param('sku');
        if ($sku && Product::where('sku', $sku)->exists()) {
            throw new ApiValidationException('SKU already exists');
        }
        
        // Validate email format if provided
        $contactEmail = $request->get_param('contact_email');
        if ($contactEmail && !is_email($contactEmail)) {
            throw new ApiValidationException('Invalid email format');
        }
        
        // Validate URL format if provided
        $website = $request->get_param('website');
        if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
            throw new ApiValidationException('Invalid website URL');
        }
    }
}
```

### Validation Helper Class

```php
<?php
namespace MyPlugin\Validation;

use Archetype\Exceptions\ApiValidationException;
use WP_REST_Request;

class Validator
{
    private WP_REST_Request $request;
    private array $errors = [];
    
    public function __construct(WP_REST_Request $request)
    {
        $this->request = $request;
    }
    
    public function required(string $field, string $message = null): self
    {
        if (!$this->request->has_param($field) || empty($this->request->get_param($field))) {
            $this->errors[$field] = $message ?? "Field '{$field}' is required";
        }
        
        return $this;
    }
    
    public function email(string $field, string $message = null): self
    {
        $value = $this->request->get_param($field);
        if ($value && !is_email($value)) {
            $this->errors[$field] = $message ?? "Field '{$field}' must be a valid email";
        }
        
        return $this;
    }
    
    public function numeric(string $field, $min = null, $max = null): self
    {
        $value = $this->request->get_param($field);
        if ($value !== null && !is_numeric($value)) {
            $this->errors[$field] = "Field '{$field}' must be numeric";
            return $this;
        }
        
        if ($min !== null && $value < $min) {
            $this->errors[$field] = "Field '{$field}' must be at least {$min}";
        }
        
        if ($max !== null && $value > $max) {
            $this->errors[$field] = "Field '{$field}' must not exceed {$max}";
        }
        
        return $this;
    }
    
    public function string(string $field, int $minLength = null, int $maxLength = null): self
    {
        $value = $this->request->get_param($field);
        if ($value !== null) {
            $length = strlen($value);
            
            if ($minLength && $length < $minLength) {
                $this->errors[$field] = "Field '{$field}' must be at least {$minLength} characters";
            }
            
            if ($maxLength && $length > $maxLength) {
                $this->errors[$field] = "Field '{$field}' must not exceed {$maxLength} characters";
            }
        }
        
        return $this;
    }
    
    public function in(string $field, array $values): self
    {
        $value = $this->request->get_param($field);
        if ($value !== null && !in_array($value, $values)) {
            $allowedValues = implode(', ', $values);
            $this->errors[$field] = "Field '{$field}' must be one of: {$allowedValues}";
        }
        
        return $this;
    }
    
    public function unique(string $field, string $model, string $column = null): self
    {
        $value = $this->request->get_param($field);
        $column = $column ?? $field;
        
        if ($value && $model::where($column, $value)->exists()) {
            $this->errors[$field] = "Field '{$field}' must be unique";
        }
        
        return $this;
    }
    
    public function validate(): void
    {
        if (!empty($this->errors)) {
            throw new ApiValidationException('Validation failed: ' . json_encode($this->errors));
        }
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}

// Usage in controller
class ProductController
{
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        $validator = new Validator($request);
        
        $validator->required('name')
                 ->string('name', 3, 255)
                 ->required('price')
                 ->numeric('price', 0.01)
                 ->required('category_id')
                 ->numeric('category_id', 1)
                 ->email('contact_email')
                 ->unique('sku', Product::class)
                 ->in('status', ['draft', 'published', 'archived'])
                 ->validate();
        
        // Validation passed, create product
        $product = Product::create($request->get_params());
        return ApiResponse::success($product, 201);
    }
}
```

## Error Handling

### Exception-Based Error Handling

```php
use Archetype\Exceptions\ApiNotFoundException;
use Archetype\Exceptions\ApiBadRequestException;
use Archetype\Exceptions\ApiUnauthorizedException;
use Archetype\Exceptions\ApiValidationException;

class ProductController
{
    #[Route(HttpMethod::GET, '/{id}')]
    public function show(WP_REST_Request $request)
    {
        $product = Product::find($request->get_param('id'));
        
        if (!$product) {
            throw new ApiNotFoundException('Product not found');
        }
        
        return ApiResponse::success($product);
    }
    
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        if (!current_user_can('create_products')) {
            throw new ApiUnauthorizedException('Insufficient permissions to create products');
        }
        
        if (!$request->get_param('name')) {
            throw new ApiBadRequestException('Product name is required');
        }
        
        if (Product::where('sku', $request->get_param('sku'))->exists()) {
            throw new ApiValidationException('SKU already exists');
        }
        
        $product = Product::create($request->get_params());
        return ApiResponse::success($product, 201);
    }
    
    #[Route(HttpMethod::DELETE, '/{id}')]
    public function destroy(WP_REST_Request $request)
    {
        $product = Product::find($request->get_param('id'));
        
        if (!$product) {
            throw new ApiNotFoundException('Product not found');
        }
        
        // Check for dependencies
        if ($product->orderItems()->exists()) {
            throw new ApiBadRequestException('Cannot delete product with existing orders');
        }
        
        $product->delete();
        return ApiResponse::success(null, 204);
    }
}
```

### Custom Exception Types

```php
<?php
namespace MyPlugin\Exceptions;

use Archetype\Exceptions\ApiException;

class ApiRateLimitException extends ApiException
{
    public function __construct(string $message = 'Rate limit exceeded')
    {
        parent::__construct('rate_limit_exceeded', $message, 429);
    }
}

class ApiInsufficientStockException extends ApiException
{
    public function __construct(int $available, int $requested)
    {
        $message = "Insufficient stock. Available: {$available}, Requested: {$requested}";
        parent::__construct('insufficient_stock', $message, 400);
    }
}

class ApiPaymentRequiredException extends ApiException
{
    public function __construct(string $message = 'Payment required to access this resource')
    {
        parent::__construct('payment_required', $message, 402);
    }
}

// Usage in controller
class OrderController
{
    #[Route(HttpMethod::POST, '/')]
    public function createOrder(WP_REST_Request $request)
    {
        $productId = $request->get_param('product_id');
        $quantity = $request->get_param('quantity');
        
        $product = Product::findOrFail($productId);
        
        if ($product->stock < $quantity) {
            throw new ApiInsufficientStockException($product->stock, $quantity);
        }
        
        // Create order...
    }
}
```

### Global Error Handling

```php
// The framework automatically catches and converts exceptions to proper API responses:

// This exception...
throw new ApiNotFoundException('Product not found');

// Becomes this JSON response:
{
    "code": "rest_not_found",
    "message": "Product not found",
    "status": 404
}

// This exception...
throw new ApiValidationException('Invalid email format');

// Becomes this JSON response:
{
    "code": "rest_validation_error", 
    "message": "Invalid email format",
    "status": 400
}
```

## Advanced Routing

### Route Groups and Versioning

```php
// Version 1 API
#[RestController(prefix: 'v1/products')]
class ProductV1Controller
{
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        // V1 implementation
        return ApiResponse::success(Product::all());
    }
}

// Version 2 API with breaking changes
#[RestController(prefix: 'v2/products')]
class ProductV2Controller
{
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        // V2 implementation with different response format
        $products = Product::with('category')->get();
        
        return ApiResponse::success([
            'items' => $products,
            'meta' => [
                'count' => $products->count(),
                'version' => '2.0'
            ]
        ]);
    }
}
```

### Middleware-Style Request Processing

```php
class ProductController
{
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        // Rate limiting
        $this->checkRateLimit($request);
        
        // Authentication
        $this->requireAuthentication($request);
        
        // Validation
        $this->validateRequest($request);
        
        // Business logic
        $product = Product::create($request->get_params());
        
        // Response transformation
        return $this->transformProduct($product);
    }
    
    private function checkRateLimit(WP_REST_Request $request): void
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit_{$clientIp}";
        
        $attempts = (int) get_transient($key);
        if ($attempts >= 100) { // 100 requests per hour
            throw new ApiRateLimitException();
        }
        
        set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
    }
    
    private function requireAuthentication(WP_REST_Request $request): void
    {
        if (!is_user_logged_in()) {
            throw new ApiUnauthorizedException('Authentication required');
        }
    }
    
    private function validateRequest(WP_REST_Request $request): void
    {
        $validator = new Validator($request);
        $validator->required('name')
                 ->string('name', 3, 255)
                 ->required('price')
                 ->numeric('price', 0.01)
                 ->validate();
    }
    
    private function transformProduct(Product $product): WP_REST_Response
    {
        return ApiResponse::success([
            'id' => $product->id,
            'name' => $product->name,
            'price' => [
                'amount' => $product->price,
                'formatted' => ' . number_format($product->price, 2)
            ],
            'created_at' => $product->created_at->toISOString()
        ], 201);
    }
}
```

### Bulk Operations

```php
class ProductController
{
    #[Route(HttpMethod::POST, '/bulk')]
    public function bulkCreate(WP_REST_Request $request)
    {
        $products = $request->get_param('products');
        
        if (!is_array($products) || empty($products)) {
            throw new ApiBadRequestException('Products array is required');
        }
        
        if (count($products) > 100) {
            throw new ApiBadRequestException('Maximum 100 products per bulk request');
        }
        
        $results = [];
        $errors = [];
        
        foreach ($products as $index => $productData) {
            try {
                // Validate individual product
                $this->validateProductData($productData);
                
                // Create product
                $product = Product::create($productData);
                $results[] = [
                    'index' => $index,
                    'id' => $product->id,
                    'status' => 'created'
                ];
                
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return ApiResponse::success([
            'results' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($products),
                'created' => count($results),
                'failed' => count($errors)
            ]
        ]);
    }
    
    #[Route(HttpMethod::PATCH, '/bulk')]
    public function bulkUpdate(WP_REST_Request $request)
    {
        $updates = $request->get_param('updates');
        $ids = array_column($updates, 'id');
        
        // Load all products at once
        $products = Product::whereIn('id', $ids)->get()->keyBy('id');
        
        $results = [];
        
        foreach ($updates as $updateData) {
            $productId = $updateData['id'];
            $product = $products->get($productId);
            
            if (!$product) {
                $results[] = [
                    'id' => $productId,
                    'status' => 'not_found'
                ];
                continue;
            }
            
            try {
                unset($updateData['id']); // Remove ID from update data
                $product->update($updateData);
                
                $results[] = [
                    'id' => $productId,
                    'status' => 'updated'
                ];
                
            } catch (Exception $e) {
                $results[] = [
                    'id' => $productId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return ApiResponse::success(['results' => $results]);
    }
    
    #[Route(HttpMethod::DELETE, '/bulk')]
    public function bulkDelete(WP_REST_Request $request)
    {
        $ids = $request->get_param('ids');
        
        if (!is_array($ids) || empty($ids)) {
            throw new ApiBadRequestException('IDs array is required');
        }
        
        // Check for products with orders
        $productsWithOrders = Product::whereIn('id', $ids)
                                   ->whereHas('orderItems')
                                   ->pluck('id')
                                   ->toArray();
        
        if (!empty($productsWithOrders)) {
            throw new ApiBadRequestException(
                'Cannot delete products with existing orders: ' . 
                implode(', ', $productsWithOrders)
            );
        }
        
        $deletedCount = Product::whereIn('id', $ids)->delete();
        
        return ApiResponse::success([
            'deleted' => $deletedCount,
            'requested' => count($ids)
        ]);
    }
}
```

### Search and Filtering

```php
class ProductController
{
    #[Route(HttpMethod::GET, '/search')]
    public function search(WP_REST_Request $request)
    {
        $query = $request->get_param('q');
        $filters = $request->get_param('filters', []);
        $sort = $request->get_param('sort', 'relevance');
        $page = max(1, (int) $request->get_param('page', 1));
        $perPage = min(50, max(1, (int) $request->get_param('per_page', 20)));
        
        if (empty($query)) {
            throw new ApiBadRequestException('Search query (q) is required');
        }
        
        $searchQuery = Product::query();
        
        // Full-text search
        $searchQuery->whereRaw(
            "MATCH(name, description) AGAINST(? IN BOOLEAN MODE)",
            [$query . '*']
        );
        
        // Apply filters
        if (isset($filters['category'])) {
            $searchQuery->whereIn('category_id', (array) $filters['category']);
        }
        
        if (isset($filters['price_min'])) {
            $searchQuery->where('price', '>=', $filters['price_min']);
        }
        
        if (isset($filters['price_max'])) {
            $searchQuery->where('price', '<=', $filters['price_max']);
        }
        
        if (isset($filters['status'])) {
            $searchQuery->whereIn('status', (array) $filters['status']);
        }
        
        if (isset($filters['tags'])) {
            $searchQuery->whereHas('tags', function($q) use ($filters) {
                $q->whereIn('slug', (array) $filters['tags']);
            });
        }
        
        // Apply sorting
        switch ($sort) {
            case 'price_asc':
                $searchQuery->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $searchQuery->orderBy('price', 'desc');
                break;
            case 'newest':
                $searchQuery->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $searchQuery->orderBy('created_at', 'asc');
                break;
            case 'name':
                $searchQuery->orderBy('name', 'asc');
                break;
            case 'relevance':
            default:
                // MySQL MATCH relevance score
                $searchQuery->orderByRaw(
                    "MATCH(name, description) AGAINST(? IN BOOLEAN MODE) DESC",
                    [$query . '*']
                );
                break;
        }
        
        // Execute search with pagination
        $results = $searchQuery->with(['category', 'tags'])
                              ->paginate($perPage, ['*'], 'page', $page);
        
        // Build facets for filtering UI
        $facets = $this->buildSearchFacets($query, $filters);
        
        return ApiResponse::success([
            'query' => $query,
            'results' => $results->items(),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total()
            ],
            'facets' => $facets,
            'filters_applied' => $filters
        ]);
    }
    
    private function buildSearchFacets(string $query, array $appliedFilters): array 
    {
        $baseQuery = Product::whereRaw(
            "MATCH(name, description) AGAINST(? IN BOOLEAN MODE)",
            [$query . '*']
        );
        
        // Category facets
        $categoryFacets = (clone $baseQuery)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('categories.id, categories.name, COUNT(*) as count')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('count', 'desc')
            ->get();
        
        // Price range facets
        $priceStats = (clone $baseQuery)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price, AVG(price) as avg_price')
            ->first();
        
        return [
            'categories' => $categoryFacets,
            'price_range' => [
                'min' => (float) $priceStats->min_price,
                'max' => (float) $priceStats->max_price,
                'avg' => (float) $priceStats->avg_price
            ]
        ];
    }
}
```

## API Best Practices

### Consistent Response Format

```php
class BaseController
{
    protected function successResponse($data, int $code = 200, array $meta = []): WP_REST_Response
    {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => current_time('mysql'),
                'version' => '1.0'
            ], $meta)
        ];
        
        return new WP_REST_Response($response, $code);
    }
    
    protected function errorResponse(string $message, int $code = 400, array $details = []): WP_Error
    {
        return new WP_Error(
            $this->getErrorCode($code),
            $message,
            array_merge(['status' => $code], $details)
        );
    }
    
    private function getErrorCode(int $httpCode): string
    {
        return match($httpCode) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            422 => 'validation_error',
            429 => 'rate_limit_exceeded',
            500 => 'internal_error',
            default => 'api_error'
        };
    }
}
```

### Resource Transformation

```php
class ProductTransformer
{
    public static function transform(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => [
                'amount' => $product->price,
                'currency' => 'USD',
                'formatted' => ' . number_format($product->price, 2)
            ],
            'stock' => [
                'quantity' => $product->stock_quantity,
                'status' => $product->stock_quantity > 0 ? 'in_stock' : 'out_of_stock'
            ],
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug
            ] : null,
            'images' => $product->images->map(function($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->url,
                    'alt' => $image->alt_text
                ];
            }),
            'meta' => [
                'created_at' => $product->created_at->toISOString(),
                'updated_at' => $product->updated_at->toISOString(),
                'is_featured' => $product->is_featured,
                'view_count' => $product->view_count
            ]
        ];
    }
    
    public static function collection($products): array
    {
        return $products->map([self::class, 'transform'])->toArray();
    }
}

// Usage in controller
class ProductController
{
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        $products = Product::with(['category', 'images'])->paginate(20);
        
        return ApiResponse::success([
            'products' => ProductTransformer::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'total_pages' => $products->lastPage(),
                'total_items' => $products->total()
            ]
        ]);
    }
    
    #[Route(HttpMethod::GET, '/{id}')]
    public function show(WP_REST_Request $request)
    {
        $product = Product::with(['category', 'images', 'reviews'])
                         ->findOrFail($request->get_param('id'));
        
        return ApiResponse::success(ProductTransformer::transform($product));
    }
}
```

### API Documentation

```php
class ProductController
{
    /**
     * Get all products
     * 
     * @route GET /products
     * @param int page Page number (default: 1)
     * @param int per_page Items per page (default: 20, max: 100)
     * @param string status Filter by status (draft, published, archived)
     * @param int category_id Filter by category ID
     * @param string search Search in name and description
     * @param string sort_by Sort field (name, price, created_at)
     * @param string sort_order Sort direction (asc, desc)
     * 
     * @return array {
     *   @type array products Array of product objects
     *   @type array pagination Pagination information
     * }
     */
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        // Implementation...
    }
    
    /**
     * Create a new product
     * 
     * @route POST /products
     * @permission create_products
     * 
     * @param string name Product name (required, 3-255 chars)
     * @param string description Product description (optional)
     * @param float price Product price (required, > 0)
     * @param string sku Product SKU (optional, must be unique)
     * @param int category_id Category ID (required, must exist)
     * @param string status Product status (draft, published, archived)
     * 
     * @return object Product object
     * @throws ApiBadRequestException Invalid data
     * @throws ApiValidationException Validation failed
     * @throws ApiUnauthorizedException Insufficient permissions
     */
    #[Route(HttpMethod::POST, '/', permissions: ['MyPlugin\\Permissions\\ProductPermission::canCreate'])]
    public function store(WP_REST_Request $request)
    {
        // Implementation...
    }
}
```

### Performance Optimization

```php
class ProductController
{
    #[Route(HttpMethod::GET, '/')]
    public function index(WP_REST_Request $request)
    {
        // Optimize database queries
        $products = Product::select(['id', 'name', 'price', 'category_id', 'created_at'])
                          ->with(['category:id,name,slug']) // Only select needed columns
                          ->when($request->get_param('status'), function($q, $status) {
                              $q->where('status', $status);
                          })
                          ->when($request->get_param('search'), function($q, $search) {
                              $q->where('name', 'like', "%{$search}%");
                          })
                          ->orderBy('created_at', 'desc')
                          ->paginate(20);
        
        // Cache expensive queries
        $cacheKey = 'products_' . md5(serialize($request->get_params()));
        
        return cache()->remember($cacheKey, 300, function() use ($products) {
            return ApiResponse::success([
                'products' => ProductTransformer::collection($products->items()),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'total_pages' => $products->lastPage(),
                    'total_items' => $products->total()
                ]
            ]);
        });
    }
}
```

### Testing Your API

```php
// Example test structure (using PHPUnit)
class ProductControllerTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = wp_create_user('testuser', 'password', 'test@example.com');
        wp_set_current_user($this->user);
        
        // Create test data
        $this->category = Category::create(['name' => 'Test Category']);
        $this->product = Product::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'category_id' => $this->category->id
        ]);
    }
    
    public function test_can_list_products()
    {
        $request = new WP_REST_Request('GET', '/wp-json/my-plugin/v1/products');
        $controller = new ProductController();
        $response = $controller->index($request);
        
        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('products', $response->get_data());
    }
    
    public function test_can_create_product()
    {
        $request = new WP_REST_Request('POST', '/wp-json/my-plugin/v1/products');
        $request->set_param('name', 'New Product');
        $request->set_param('price', 149.99);
        $request->set_param('category_id', $this->category->id);
        
        $controller = new ProductController();
        $response = $controller->store($request);
        
        $this->assertEquals(201, $response->get_status());
        $this->assertEquals('New Product', $response->get_data()['name']);
    }
}
```

---

This comprehensive guide covers all aspects of creating powerful REST APIs with Archetype. The combination of attributes, automatic registration, and WordPress integration makes it easy to build professional APIs quickly.

**Next:** Learn about the [Logging System](07-logging-system.md) to monitor and debug your API effectively.