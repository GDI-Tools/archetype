# Exception Handling

Archetype provides structured exception handling for consistent API responses and better error management throughout your WordPress plugin.

## Built-in Exception Types

### Standard API Exceptions

```php
use Archetype\Exceptions\ApiNotFoundException;
use Archetype\Exceptions\ApiBadRequestException;
use Archetype\Exceptions\ApiUnauthorizedException;
use Archetype\Exceptions\ApiValidationException;

// 404 - Resource not found
throw new ApiNotFoundException('Product not found');

// 400 - Bad request data
throw new ApiBadRequestException('Email is required');

// 403 - Insufficient permissions
throw new ApiUnauthorizedException('Admin access required');

// 400 - Validation errors
throw new ApiValidationException('Invalid email format');
```

### Automatic Response Conversion

The framework automatically converts exceptions to proper API responses:

```php
// This exception...
throw new ApiNotFoundException('Product not found');

// Becomes this JSON response:
{
    "code": "rest_not_found",
    "message": "Product not found",
    "status": 404
}
```

## Using Exceptions in Controllers

### Basic Usage

```php
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
            throw new ApiUnauthorizedException('Insufficient permissions');
        }
        
        if (!$request->get_param('name')) {
            throw new ApiBadRequestException('Product name is required');
        }
        
        $product = Product::create($request->get_params());
        return ApiResponse::success($product, 201);
    }
}
```

### Validation with Exceptions

```php
class ProductController
{
    #[Route(HttpMethod::POST, '/')]
    public function store(WP_REST_Request $request)
    {
        $this->validateProduct($request);
        
        $product = Product::create($request->get_params());
        return ApiResponse::success($product, 201);
    }
    
    private function validateProduct(WP_REST_Request $request): void
    {
        // Required fields
        if (!$request->get_param('name')) {
            throw new ApiBadRequestException('Name is required');
        }
        
        // Format validation
        $email = $request->get_param('contact_email');
        if ($email && !is_email($email)) {
            throw new ApiValidationException('Invalid email format');
        }
        
        // Business rules
        $price = $request->get_param('price');
        if ($price && $price < 0) {
            throw new ApiValidationException('Price must be positive');
        }
        
        // Uniqueness checks
        $sku = $request->get_param('sku');
        if ($sku && Product::where('sku', $sku)->exists()) {
            throw new ApiValidationException('SKU already exists');
        }
    }
}
```

## Custom Exception Types

### Creating Custom Exceptions

```php
<?php
namespace MyPlugin\Exceptions;

use Archetype\Exceptions\ApiException;

class ApiRateLimitException extends ApiException
{
    public function __construct(int $limit = 100, int $window = 3600)
    {
        $message = "Rate limit exceeded. Max {$limit} requests per {$window} seconds";
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
    public function __construct(string $resource = 'resource')
    {
        parent::__construct(
            'payment_required', 
            "Payment required to access {$resource}", 
            402
        );
    }
}
```

### Using Custom Exceptions

```php
class OrderController
{
    #[Route(HttpMethod::POST, '/')]
    public function createOrder(WP_REST_Request $request)
    {
        $this->checkRateLimit($request);
        
        $productId = $request->get_param('product_id');
        $quantity = $request->get_param('quantity');
        
        $product = Product::findOrFail($productId);
        
        if ($product->stock < $quantity) {
            throw new ApiInsufficientStockException($product->stock, $quantity);
        }
        
        // Create order logic...
    }
    
    private function checkRateLimit(WP_REST_Request $request): void
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $attempts = (int) get_transient("rate_limit_{$clientIp}");
        
        if ($attempts >= 100) {
            throw new ApiRateLimitException(100, 3600);
        }
        
        set_transient("rate_limit_{$clientIp}", $attempts + 1, HOUR_IN_SECONDS);
    }
}
```

## Error Context and Logging

### Adding Context to Exceptions

```php
use Archetype\Logging\Logger;

class ProductService
{
    public function updateStock(int $productId, int $quantity): void
    {
        try {
            $product = Product::findOrFail($productId);
            $product->stock_quantity = $quantity;
            $product->save();
            
            Logger::info('Stock updated', [
                'product_id' => $productId,
                'new_quantity' => $quantity
            ]);
            
        } catch (Exception $e) {
            Logger::error('Stock update failed', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new ApiException(
                'stock_update_failed',
                'Could not update product stock',
                500
            );
        }
    }
}
```

### Exception with Additional Data

```php
class ApiValidationException extends ApiException
{
    private array $validationErrors;
    
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct('validation_error', $message, 400);
        $this->validationErrors = $errors;
    }
    
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
    
    public function toWpError(): WP_Error
    {
        $error = parent::toWpError();
        
        if (!empty($this->validationErrors)) {
            $error->add_data(['validation_errors' => $this->validationErrors]);
        }
        
        return $error;
    }
}

// Usage
throw new ApiValidationException('Validation failed', [
    'name' => 'Name is required',
    'email' => 'Invalid email format',
    'price' => 'Price must be positive'
]);
```

## Global Exception Handling

### Framework Exception Wrapper

The framework automatically wraps controller methods to catch exceptions:

```php
// Your controller method
#[Route(HttpMethod::POST, '/')]
public function store(WP_REST_Request $request)
{
    // Your code that might throw exceptions
    throw new ApiNotFoundException('Not found');
}

// Framework automatically wraps like this:
private function wrap_callback($request, callable $callback)
{
    try {
        $response = call_user_func($callback, $request);
        
        if ($response instanceof WP_REST_Response || $response instanceof WP_Error) {
            return $response;
        }
        
        return ApiResponse::success($response);
        
    } catch (ApiException $exception) {
        return $exception->toWpError();
    } catch (Throwable $exception) {
        return ApiResponse::server_error($exception->getMessage());
    }
}
```

### Custom Global Handler

```php
class GlobalExceptionHandler
{
    public static function handle(Throwable $exception): WP_Error
    {
        // Log all exceptions
        Logger::error('Unhandled exception', [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Handle specific exception types
        if ($exception instanceof \InvalidArgumentException) {
            return new WP_Error('invalid_argument', $exception->getMessage(), ['status' => 400]);
        }
        
        if ($exception instanceof \UnauthorizedHttpException) {
            return new WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
        }
        
        // Default server error
        return new WP_Error(
            'server_error',
            wp_get_environment_type() === 'production' 
                ? 'An unexpected error occurred' 
                : $exception->getMessage(),
            ['status' => 500]
        );
    }
}
```

## Best Practices

### Exception Hierarchy

```php
// Create a hierarchy for better organization
namespace MyPlugin\Exceptions;

// Base plugin exception
abstract class PluginException extends ApiException {}

// Domain-specific exceptions
class ProductException extends PluginException {}
class OrderException extends PluginException {}
class UserException extends PluginException {}

// Specific exceptions
class ProductNotFoundException extends ProductException
{
    public function __construct(int $productId)
    {
        parent::__construct('product_not_found', "Product {$productId} not found", 404);
    }
}

class OrderCannotBeCancelledException extends OrderException
{
    public function __construct(string $reason)
    {
        parent::__construct('order_cannot_be_cancelled', "Order cannot be cancelled: {$reason}", 400);
    }
}
```

### Fail Fast Principle

```php
class ProductController
{
    #[Route(HttpMethod::PUT, '/{id}')]
    public function update(WP_REST_Request $request)
    {
        // Validate early and fail fast
        $productId = $request->get_param('id');
        if (!$productId || !is_numeric($productId)) {
            throw new ApiBadRequestException('Valid product ID is required');
        }
        
        $product = Product::find($productId);
        if (!$product) {
            throw new ApiNotFoundException('Product not found');
        }
        
        if (!current_user_can('edit_product', $productId)) {
            throw new ApiUnauthorizedException('Cannot edit this product');
        }
        
        // Now proceed with business logic...
        $product->update($request->get_params());
        return ApiResponse::success($product);
    }
}
```

### Error Recovery

```php
class PaymentService
{
    public function processPayment(Order $order): PaymentResult
    {
        $maxRetries = 3;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return $this->attemptPayment($order);
                
            } catch (PaymentGatewayException $e) {
                $attempt++;
                
                Logger::warning('Payment attempt failed', [
                    'order_id' => $order->id,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                
                if ($attempt >= $maxRetries) {
                    throw new ApiException(
                        'payment_failed',
                        'Payment failed after multiple attempts',
                        402
                    );
                }
                
                // Wait before retry
                sleep(pow(2, $attempt)); // Exponential backoff
            }
        }
    }
}
```

## Debugging Exceptions

### Development vs Production

```php
class ExceptionResponse
{
    public static function format(Throwable $exception): array
    {
        $response = [
            'error' => true,
            'message' => $exception->getMessage(),
            'code' => $exception instanceof ApiException 
                ? $exception->get_error_code() 
                : 'server_error'
        ];
        
        // Add debug info in development
        if (WP_DEBUG) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace()
            ];
        }
        
        return $response;
    }
}
```

### Exception Testing

```php
class ProductControllerTest extends TestCase
{
    public function test_throws_not_found_exception()
    {
        $this->expectException(ApiNotFoundException::class);
        $this->expectExceptionMessage('Product not found');
        
        $request = new WP_REST_Request('GET', '/products/999');
        $request->set_param('id', 999);
        
        $controller = new ProductController();
        $controller->show($request);
    }
    
    public function test_validation_exception_response()
    {
        $request = new WP_REST_Request('POST', '/products');
        // Don't set required 'name' parameter
        
        $controller = new ProductController();
        $response = $controller->store($request);
        
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('bad_request', $response->get_error_code());
    }
}
```

---

Structured exception handling provides consistent error responses and better debugging capabilities throughout your WordPress plugin.

**Next:** Learn about [Advanced Features](10-advanced-features.md) for component scanning and performance optimization.