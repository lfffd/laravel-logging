# Superlog Setup Guide

## Quick Start

### 1. Installation

```bash
composer require lfffd/laravel-logging
```

### 2. Configuration

**Step 1**: Publish the configuration file:
```bash
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider"
```

**Step 2**: Update `config/logging.php` to use Superlog:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'superlog'],
    ],
    
    'superlog' => [
        'driver' => 'superlog',
        'name' => 'superlog',
        'level' => 'debug',
    ],
],
```

**Step 3**: Register the middleware in `app/Http/Kernel.php`:

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            
            // Add Superlog middleware
            \Superlog\Middleware\RequestLifecycleMiddleware::class,
        ],
    ];
}
```

**Step 4**: Configure environment variables (`.env`):

```env
# Superlog Settings
SUPERLOG_ENABLED=true
SUPERLOG_AUTO_CAPTURE=true
SUPERLOG_FORMAT=json

# Redaction
SUPERLOG_REDACTION_ENABLED=true
SUPERLOG_REDACT_KEYS=custom_field,internal_key

# Sampling (optional)
SUPERLOG_SAMPLING_ENABLED=false

# Async Shipping (optional)
SUPERLOG_ASYNC_ENABLED=false
SUPERLOG_HTTP_URL=http://localhost:9200/_bulk
SUPERLOG_HTTP_USER=elastic
SUPERLOG_HTTP_PASS=changeme
```

### 3. Publishing Configuration

To customize the configuration, publish the config file:

```bash
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider" --tag="config"
```

This creates `config/superlog.php` with all available options.

## Usage Examples

### Basic Usage in Controllers

```php
<?php

namespace App\Http\Controllers;

use Superlog\Facades\Superlog;

class UserController extends Controller
{
    public function store(Request $request)
    {
        Superlog::log('info', 'USER_CREATE', 'Creating new user', [
            'email' => $request->email,
        ], [
            'form_fields' => count($request->all()),
        ]);

        // Your logic here...

        Superlog::log('info', 'USER_CREATED', 'User created successfully', [
            'user_id' => $user->id,
        ]);

        return redirect()->route('users.index');
    }
}
```

### Logging Database Queries

```php
use Superlog\Facades\Superlog;

$startTime = microtime(true);

// Your queries...
$users = User::all();

$endTime = microtime(true);

Superlog::logDatabase([
    'query_count' => DB::getQueryLog() ? count(DB::getQueryLog()) : 0,
    'total_query_ms' => ($endTime - $startTime) * 1000,
    'slowest_query_ms' => 0, // Calculate if needed
    'slow_queries' => [],
]);
```

### Logging External API Calls

```php
use Superlog\Facades\Superlog;

try {
    $startTime = microtime(true);
    
    $response = Http::get('https://api.example.com/users');
    
    $duration = (microtime(true) - $startTime) * 1000;
    
    Superlog::logHttpOut(
        'GET',
        'https://api.example.com/users',
        $response->status(),
        $duration,
        ['retry_count' => 0]
    );
    
} catch (Exception $e) {
    Superlog::log('error', 'HTTP_CALL', 'API call failed', [
        'url' => 'https://api.example.com/users',
        'error' => $e->getMessage(),
    ]);
}
```

### Getting Correlation Information

```php
use Superlog\Facades\Superlog;

// Get current correlation context
$correlation = Superlog::getCorrelation();

// Use in headers for propagation
$traceId = $correlation->getTraceId();
$duration = $correlation->getDurationMs();

// Pass to external services
Http::withHeaders([
    'X-Trace-Id' => $traceId,
])->get('https://api.example.com/data');
```

## Advanced Configuration

### Custom Redaction Keys

```php
// In config/superlog.php
'redaction' => [
    'enabled' => true,
    'custom_keys' => [
        'company_secret',
        'internal_reference',
        'billing_code',
    ],
],
```

### Slow Request Diagnostics

```php
// In config/superlog.php
'slow_request' => [
    'threshold_ms' => 1000, // 1 second
    'collect_queries' => true,
    'collect_cache_stats' => true,
    'collect_bindings_count' => true,
],
```

### Async Shipping Setup

1. Configure queue worker:
```bash
php artisan queue:work --queue=superlog
```

2. Update `.env`:
```env
SUPERLOG_ASYNC_ENABLED=true
SUPERLOG_QUEUE=superlog
SUPERLOG_HTTP_URL=http://elasticsearch:9200
```

3. Update `config/superlog.php`:
```php
'external_handlers' => [
    [
        'driver' => 'http',
        'url' => env('SUPERLOG_HTTP_URL'),
        'auth' => [
            'username' => env('SUPERLOG_HTTP_USER'),
            'password' => env('SUPERLOG_HTTP_PASS'),
        ],
        'timeout' => 10,
        'retry_count' => 3,
    ]
],
```

## Viewing Logs

### Local Development

Logs are written to `storage/logs/laravel.log` (by default):

```bash
# Follow logs in real-time
tail -f storage/logs/laravel.log

# Filter by trace ID
grep "trace_id" storage/logs/laravel.log | grep "a1b2c3d4-e5f6"

# Filter by section
grep "\[MIDDLEWARE\]" storage/logs/laravel.log
```

### Production with ELK/OpenSearch

1. Ensure async shipping is enabled
2. Queue worker is running
3. Logs ship automatically in batches

Query logs using Kibana or OpenSearch dashboards:
```
trace_id: "a1b2c3d4-e5f6-7890-1234-567890abcdef"
section: "[DATABASE]"
level: "INFO"
```

## Troubleshooting

### Logs not appearing

1. Check if Superlog is enabled:
```php
php artisan tinker
>>> config('superlog.enabled')
```

2. Check middleware is registered in `app/Http/Kernel.php`

3. Verify `config/logging.php` has the superlog channel configured

### Redaction not working

1. Verify `SUPERLOG_REDACTION_ENABLED=true` in `.env`
2. Check custom keys are defined correctly
3. Restart queue workers if using async shipping

### Performance issues

1. Enable sampling in `config/superlog.php`:
```php
'sampling' => [
    'enabled' => true,
    'info_rate' => 0.1,
    'debug_rate' => 0.05,
],
```

2. Use async shipping for external services

3. Reduce `max_string_length` and `max_array_depth` in payload handling

## Testing

Run the test suite:

```bash
composer test
```

## Next Steps

- Review `config/superlog.php` for all available options
- Set up Kibana/OpenSearch for log visualization
- Configure alerting based on log patterns
- Integrate with your monitoring stack

For more information, see README.md or visit https://github.com/lfffd/laravel-logging