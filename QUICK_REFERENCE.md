# Superlog Quick Reference

## Installation (30 seconds)

```bash
composer require lfffd/laravel-logging
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider"
```

Update `config/logging.php`:
```php
'superlog' => [
    'driver' => 'superlog',
    'name' => 'superlog',
    'level' => 'debug',
],
```

Register middleware in `app/Http/Kernel.php`:
```php
\Superlog\Middleware\RequestLifecycleMiddleware::class,
```

## Usage Examples

### Basic Log
```php
use Superlog\Facades\Superlog;

Superlog::log('info', 'SECTION_NAME', 'Message', ['key' => 'value'], ['metric' => 42]);
```

### Database
```php
Superlog::logDatabase([
    'query_count' => 5,
    'total_query_ms' => 234.5,
    'slowest_query_ms' => 89.2,
    'slow_queries' => ['SELECT...'],
]);
```

### HTTP Outbound
```php
Superlog::logHttpOut('GET', 'https://api.example.com/data', 200, 567.8);
```

### Cache Stats
```php
Superlog::logCache([
    'hits' => 234,
    'misses' => 56,
    'sets' => 45,
    'duration_ms' => 12.3,
]);
```

### Get Correlation
```php
$correlation = Superlog::getCorrelation();
$traceId = $correlation->getTraceId();      // UUID v4
$duration = $correlation->getDurationMs();   // Request duration
```

## Configuration

### Environment Variables

```bash
# Core
SUPERLOG_ENABLED=true
SUPERLOG_AUTO_CAPTURE=true
SUPERLOG_FORMAT=json

# Redaction
SUPERLOG_REDACTION_ENABLED=true
SUPERLOG_REDACT_KEYS=custom_field,internal_key

# Sampling
SUPERLOG_SAMPLING_ENABLED=false
# (info_rate: 10%, debug_rate: 5%)

# Slow Request
SUPERLOG_SLOW_REQUEST_MS=750

# Async Shipping
SUPERLOG_ASYNC_ENABLED=false
SUPERLOG_QUEUE=superlog
SUPERLOG_HTTP_URL=http://elasticsearch:9200
SUPERLOG_HTTP_USER=elastic
SUPERLOG_HTTP_PASS=changeme
```

### In config/superlog.php

```php
'redaction' => [
    'enabled' => true,
    'mode' => 'mask',        // or 'remove'
    'smart_detection' => true,
],

'slow_request' => [
    'threshold_ms' => 750,
    'collect_queries' => true,
],

'async_shipping' => [
    'enabled' => false,
    'batch_size' => 100,
    'batch_timeout_ms' => 5000,
],
```

## Log Output

### JSON (Production)
```json
{
  "trace_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
  "req_seq": "0000000001",
  "span_id": "b5c6d7e8-f9a0-1234-5678-90abcdef1234",
  "level": "INFO",
  "section": "[MIDDLEWARE END]",
  "message": "Request completed",
  "metrics": {"duration_ms": 45.2},
  "timestamp": "2025-10-20T09:13:46.343976Z"
}
```

### Text (Development)
```
[2025-10-20 09:13:46] local.INFO: [MIDDLEWARE END] Request completed {"duration_ms":45.2}
```

## Viewing Logs

### Local Development
```bash
tail -f storage/logs/laravel.log
grep "trace_id" storage/logs/laravel.log
grep "\[MIDDLEWARE\]" storage/logs/laravel.log
```

### With ELK/Kibana
```
Query: trace_id:"a1b2c3d4-e5f6-7890-1234-567890abcdef"
Query: level:"ERROR"
Query: section:"[DATABASE]"
```

## Common Tasks

### Enable Redaction
```env
SUPERLOG_REDACTION_ENABLED=true
```

### Add Custom Redaction Keys
```env
SUPERLOG_REDACT_KEYS=company_secret,billing_code
```

### Reduce Log Noise
```env
SUPERLOG_SAMPLING_ENABLED=true
# info_rate: 10%, debug_rate: 5%
```

### Ship to Elasticsearch
```env
SUPERLOG_ASYNC_ENABLED=true
SUPERLOG_HTTP_URL=http://elasticsearch:9200/_bulk
SUPERLOG_HTTP_USER=elastic
SUPERLOG_HTTP_PASS=changeme
```

Start queue worker:
```bash
php artisan queue:work --queue=superlog
```

### Detect Slow Requests
```env
SUPERLOG_SLOW_REQUEST_MS=1000
```

## Request Lifecycle

```
[STARTUP] ➔ Request metadata captured
   ↓
[MIDDLEWARE START] ➔ Middleware begins
[MIDDLEWARE END] ➔ Middleware completes with timing
   ↓
[DATABASE] ➔ Query aggregation
[HTTP-OUT] ➔ External API calls
[CACHE] ➔ Cache statistics
   ↓
[SHUTDOWN] ➔ Request completed, peak memory, opcache status
```

## Correlation Headers

Propagate trace_id to external services:

```php
$correlation = Superlog::getCorrelation();

Http::withHeaders([
    'X-Trace-Id' => $correlation->getTraceId(),
])->get('https://api.example.com/data');
```

## Sections

| Section | Purpose |
|---------|---------|
| STARTUP | Request initialization |
| MIDDLEWARE | HTTP middleware execution |
| DATABASE | Query aggregation |
| HTTP-OUT | External API calls |
| CACHE | Cache hit/miss tracking |
| SHUTDOWN | Request completion |
| CUSTOM | Your own sections |

## Redacted By Default

- Passwords, tokens, API keys
- Authorization headers
- Email addresses
- Phone numbers
- Credit cards, SSN, IBAN
- Cookies, sessions

Configure in `config/superlog.php` or `SUPERLOG_REDACT_KEYS` env var.

## Performance Tips

1. **Use Sampling**: Reduce info/debug log volume by 90%
   ```env
   SUPERLOG_SAMPLING_ENABLED=true
   ```

2. **Enable Async Shipping**: Queue-based instead of blocking
   ```env
   SUPERLOG_ASYNC_ENABLED=true
   ```

3. **Adjust String Length**: Truncate large payloads
   ```php
   'payload_handling' => [
       'max_string_length' => 1000, // Default: 5000
   ],
   ```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Logs not appearing | Check `SUPERLOG_ENABLED=true`, middleware registered |
| PII not masked | Verify `SUPERLOG_REDACTION_ENABLED=true`, check key patterns |
| High memory usage | Enable sampling, reduce `max_array_depth` |
| Logs not shipping | Check queue worker running, external handler URL |

## More Information

- Full docs: **README.md**
- Setup: **SETUP_GUIDE.md**
- Structure: **STRUCTURE.md**
- Examples: **examples/middleware_integration.php**
- Tests: **tests/SuperlogTest.php**