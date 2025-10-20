# Superlog - Advanced Laravel Logging Engine

An enterprise-grade structured logging package for Laravel with built-in correlation tracking, PII redaction, performance diagnostics, and seamless ELK/OpenSearch integration.

## Features

### 🔗 First-Class Correlation
- **trace_id** (UUID v4): Spans the entire request/job lifecycle
- **req_seq** (fixed-width 10 chars): Incremental ID per log line within a request
- **span_id**: Per-section correlation (startup, middleware, db, http, shutdown)

### 📊 Sections with Timing & Resources
- **[STARTUP]**: Route, HTTP verb, IP, user/tenant, session ID, payload sizes, user-agent
- **[MIDDLEWARE START/END]**: Auto-emitted with duration (ms) and response status
- **[DATABASE]**: Aggregated query count, total query time, slowest query, slow query list
- **[HTTP-OUT]**: Guzzle taps with URL, method, status, duration, retry count, circuit state
- **[CACHE]**: Hits, misses, sets, duration
- **[SHUTDOWN]**: Request time, response status, peak memory, response bytes, PHP opcache status

### 🔐 Redaction & PII Guard
- Smart defaults + zero-config safe defaults
- Configurable key blacklist (email, password, token, authorization, IBAN, NIF, CC, SSN, phone, cookies)
- Binary payloads and files summarized, not dumped
- Customizable masking strategies

### 📈 Adaptive Verbosity & Sampling
- Always log sections; sample noisy info/debug at configurable rates
- Escalate to debug automatically on 4xx/5xx responses
- Slow-request trigger (> 750ms default) adds extra diagnostics

### ⚡ Performance-Friendly
- Processors (cheap) + deferred heavy collectors until shutdown
- Optional async shipping via queue + HTTP handler
- Minimal overhead even in high-traffic environments

### 📝 Structured JSON + Human Header
- Pure JSON context for grep/ELK/OpenSearch shipping
- Human-readable prefix for local development
- Single daily file (standard Laravel rotation)

## Installation

### From GitHub (Before Packagist Publication)

1. **Add the repository to your `composer.json`**:
   ```json
   {
     "repositories": [
       {
         "type": "vcs",
         "url": "https://github.com/lfffd/laravel-logging.git"
       }
     ],
     "require": {
       "lfffd/laravel-logging": "dev-main"
     }
   }
   ```

2. **Or run via command line**:
   ```bash
   composer config repositories.superlog vcs https://github.com/lfffd/laravel-logging.git
   composer require lfffd/laravel-logging:dev-main
   ```

### From Packagist (After Publication)

```bash
composer require lfffd/laravel-logging
```

### Configuration

1. **Publish configuration**:
   ```bash
   php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider"
   ```

2. **Update `config/logging.php`**:
   ```php
   'channels' => [
       // ... other channels
       'superlog' => [
           'driver' => 'superlog',
           'name' => 'superlog',
           'level' => 'debug',
       ],
   ],
   ```

3. **Register middleware** in `app/Http/Kernel.php`:
   ```php
   protected $middlewareGroups = [
       'web' => [
           // ... existing middleware
           \Superlog\Middleware\RequestLifecycleMiddleware::class,
       ],
   ];
   ```

## Configuration Options

See `config/superlog.php` for all available options:

### Core Settings
```php
'enabled' => env('SUPERLOG_ENABLED', true),
'auto_capture_requests' => env('SUPERLOG_AUTO_CAPTURE', true),
'trace_id_header' => env('SUPERLOG_TRACE_ID_HEADER', 'X-Trace-Id'),
```

### Sampling
```php
'sampling' => [
    'enabled' => env('SUPERLOG_SAMPLING_ENABLED', false),
    'info_rate' => 0.1,   // 10% of INFO logs
    'debug_rate' => 0.05, // 5% of DEBUG logs
],
```

### Slow Request Detection
```php
'slow_request' => [
    'threshold_ms' => env('SUPERLOG_SLOW_REQUEST_MS', 750),
    'collect_queries' => true,
    'collect_cache_stats' => true,
    'collect_bindings_count' => true,
],
```

### PII Redaction
```php
'redaction' => [
    'enabled' => env('SUPERLOG_REDACTION_ENABLED', true),
    'mode' => 'mask', // 'mask' or 'remove'
    'smart_detection' => true,
    'custom_keys' => explode(',', env('SUPERLOG_REDACT_KEYS', '')),
],
```

### Async Shipping
```php
'async_shipping' => [
    'enabled' => env('SUPERLOG_ASYNC_ENABLED', false),
    'queue' => env('SUPERLOG_QUEUE', 'default'),
    'batch_size' => 100,
    'batch_timeout_ms' => 5000,
],

'external_handlers' => [
    [
        'driver' => 'http',
        'url' => env('SUPERLOG_HTTP_URL', 'http://localhost:9200'),
        'auth' => [
            'username' => env('SUPERLOG_HTTP_USER'),
            'password' => env('SUPERLOG_HTTP_PASS'),
        ],
        'timeout' => 10,
        'retry_count' => 3,
    ]
],
```

## Usage Examples

### Basic Logging

```php
use Superlog\Facades\Superlog;

// Simple log with section
Superlog::log('info', 'CUSTOM_SECTION', 'User login successful', [
    'user_id' => 123,
], [
    'duration_ms' => 45.2,
]);

// Database logging
Superlog::logDatabase([
    'query_count' => 12,
    'total_query_ms' => 234.5,
    'slowest_query_ms' => 89.2,
    'slow_queries' => [
        'SELECT * FROM users WHERE email = ?',
    ],
]);

// HTTP outbound call
Superlog::logHttpOut('GET', 'https://api.example.com/users', 200, 567.8, [
    'retry_count' => 1,
    'circuit_breaker' => 'closed',
]);

// Cache statistics
Superlog::logCache([
    'hits' => 234,
    'misses' => 56,
    'sets' => 45,
    'duration_ms' => 12.3,
]);
```

### Manual Correlation

```php
use Superlog\Facades\Superlog;

// Get current correlation context
$correlation = Superlog::getCorrelation();
echo $correlation->getTraceId(); // UUID v4
echo $correlation->getDurationMs(); // Request duration

// Initialize custom trace (e.g., in queue jobs)
Superlog::initializeRequest('QUEUE', 'process_order', '127.0.0.1', 'custom-trace-id');
```

### Integration with Laravel Features

The middleware automatically captures:
- Request startup with route, IP, user/tenant info
- Response status and timing
- Memory peak usage
- Included files count
- Queue jobs dispatched

## Sample Output

### JSON Format
```json
{
  "timestamp": "2025-10-20T09:13:46.343976Z",
  "trace_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
  "req_seq": "0000000001",
  "span_id": "b5c6d7e8-f9a0-1234-5678-90abcdef1234",
  "level": "INFO",
  "section": "[MIDDLEWARE END]",
  "message": "[MIDDLEWARE END] VerifyCsrfToken - SUCCESS",
  "context": {},
  "metrics": {
    "duration_ms": 11.75,
    "response_status": 200,
    "csrf_verified": true
  },
  "correlation": {
    "trace_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
    "method": "GET",
    "path": "/users",
    "client_ip": "192.168.1.100",
    "request_duration_ms": 245.32
  }
}
```

### Text Format (Development)
```
[2025-10-20 09:13:46] local.INFO: [MIDDLEWARE END] VerifyCsrfToken - SUCCESS {"duration_ms":11.75,"response_status":200,"csrf_verified":true}
```

## Advanced: Shipping to ELK/OpenSearch

### Environment Setup
```bash
SUPERLOG_ASYNC_ENABLED=true
SUPERLOG_HTTP_URL=http://elasticsearch:9200/_bulk
SUPERLOG_HTTP_USER=elastic
SUPERLOG_HTTP_PASS=changeme
SUPERLOG_QUEUE=superlog
```

### Queue Worker
```bash
php artisan queue:work --queue=superlog
```

The logs will be shipped in batches of 100 (configurable) to your Elasticsearch cluster.

## Performance Considerations

- **Light processors**: Correlation and sampling use minimal CPU
- **Deferred collectors**: Memory and opcache stats gathered at shutdown only
- **Async shipping**: Queue-based HTTP shipping prevents blocking requests
- **Sampling**: Reduce noise on info/debug logs by 90%+ while keeping errors/warnings

## Security & Privacy

By default, Superlog masks:
- Passwords and tokens
- Authorization headers
- Credit card numbers
- Social security numbers
- Email addresses
- Phone numbers
- Cookie values

Customize via `config/superlog.php` or environment variables.

## Support

For issues, feature requests, or documentation, visit:
- GitHub: https://github.com/lfffd/laravel-logging
- Docs: https://github.com/lfffd/laravel-logging
- Issues: https://github.com/lfffd/laravel-logging/issues

## License

MIT License - see LICENSE file for details