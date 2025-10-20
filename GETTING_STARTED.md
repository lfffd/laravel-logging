# Superlog - Getting Started (5-Minute Quick Start)

Welcome to Superlog! This is the fastest way to get up and running.

## 🎯 In 5 Minutes

### Step 1: Install (1 minute)

```bash
composer require lfffd/laravel-logging
```

### Step 2: Publish Configuration (1 minute)

```bash
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider"
```

### Step 3: Update Logging Config (1 minute)

Edit `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'superlog'], // ← Add 'superlog'
    ],
    
    'superlog' => [
        'driver' => 'superlog',
        'name' => 'superlog',
        'level' => 'debug',
    ],
],
```

### Step 4: Register Middleware (1 minute)

Edit `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        // ... existing middleware
        \Superlog\Middleware\RequestLifecycleMiddleware::class, // ← Add this
    ],
];
```

### Step 5: Test It! (1 minute)

Run your app and check `storage/logs/laravel.log`:

```bash
tail -f storage/logs/laravel.log
```

You should see structured logs like:

```json
{
  "timestamp": "2025-10-20T09:13:46Z",
  "trace_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
  "req_seq": "0000000001",
  "level": "INFO",
  "section": "[STARTUP]",
  "message": "Request initiated",
  "metrics": {"method": "GET", "path": "/", "ip": "127.0.0.1"}
}
```

**That's it!** 🎉

---

## 🚀 Now, Let's Use It

### Log Custom Events

```php
use Superlog\Facades\Superlog;

// In your controller or anywhere
Superlog::log('info', 'USER_SIGNUP', 'New user registered', [
    'email' => 'user@example.com',  // Will be masked!
], [
    'signup_time_ms' => 245.3,
]);
```

### Get the Trace ID

```php
$correlation = Superlog::getCorrelation();
$traceId = $correlation->getTraceId();

// Send to external APIs
Http::withHeaders(['X-Trace-Id' => $traceId])
    ->get('https://api.example.com/data');
```

### Log Database Queries

```php
Superlog::logDatabase([
    'query_count' => 5,
    'total_query_ms' => 234.5,
    'slowest_query_ms' => 89.2,
    'slow_queries' => ['SELECT...'],
]);
```

---

## 🔧 Common Configuration

### Enable JSON Output (Production)

```bash
# Add to .env
SUPERLOG_FORMAT=json
```

### Hide Sensitive Data (Automatic!)

By default, these are automatically masked:
- ✅ Passwords
- ✅ API tokens
- ✅ Email addresses
- ✅ Phone numbers
- ✅ Credit cards

### Reduce Log Noise (Sampling)

```env
# Mask/hide passwords, tokens, emails, etc. automatically
SUPERLOG_SAMPLING_ENABLED=true
# Now 90% of info/debug logs are dropped!
```

### Detect Slow Requests

```env
# Logs extra diagnostics for requests > 1 second
SUPERLOG_SLOW_REQUEST_MS=1000
```

---

## 📊 What You Get

Each request is automatically logged with:

### [STARTUP] Section
```
- Route and HTTP method
- Client IP address
- User ID (if authenticated)
- Request size
- User-Agent
```

### [MIDDLEWARE] Sections
```
- Middleware name
- Execution time (ms)
- Response status
```

### [DATABASE] Section (if you log it)
```
- Query count
- Total query time
- Slowest query time
- Slow queries list
```

### [SHUTDOWN] Section
```
- Total request time
- Response status
- Peak memory usage
- Included files count
- Queue jobs dispatched
```

---

## 🔍 Viewing Logs

### Local Development

```bash
# Follow logs in real-time
tail -f storage/logs/laravel.log

# Find logs by trace ID
grep "a1b2c3d4-e5f6" storage/logs/laravel.log

# Find error logs
grep "ERROR" storage/logs/laravel.log
```

### With Grep (JSON)

```bash
# Find all middleware logs
grep "\[MIDDLEWARE\]" storage/logs/laravel.log

# Find slow requests (> 1000ms)
grep "request_ms.*[1-9][0-9]{3,}" storage/logs/laravel.log
```

---

## 🎓 Key Concepts (2 minutes)

### Trace ID
- Unique ID (UUID) for each request
- Same across all logs in a request
- Used for tracking across services

### Req Seq
- Log line counter (0000000001, 0000000002, ...)
- Increments per log in a request
- Easy to see order of events

### Span ID
- Section identifier ([STARTUP], [MIDDLEWARE], etc.)
- Groups related logs together
- Different for each section

### Example:
```json
{
  "trace_id": "a1b2c3d4-e5f6",      ← Same for all logs
  "req_seq": "0000000001",             ← Increments
  "span_id": "b5c6d7e8-f9a0",        ← Per section
  "section": "[MIDDLEWARE]"
}
```

---

## ⚡ Pro Tips

### 1. Custom Sections

```php
Superlog::log('info', 'PAYMENT', 'Processing payment', [], [
    'amount' => 99.99,
    'gateway' => 'stripe',
]);
```

### 2. Propagate Trace ID

```php
// Get current trace
$traceId = Superlog::getCorrelation()->getTraceId();

// Send to external service
$response = Http::withHeaders([
    'X-Trace-Id' => $traceId,
])->post('https://api.example.com/webhook');
```

### 3. Async Log Shipping (Production)

Enable in `.env`:
```env
SUPERLOG_ASYNC_ENABLED=true
SUPERLOG_QUEUE=superlog
SUPERLOG_HTTP_URL=http://elasticsearch:9200
```

Then start queue worker:
```bash
php artisan queue:work --queue=superlog
```

### 4. Custom Redaction

```env
# Add to .env to redact additional fields
SUPERLOG_REDACT_KEYS=company_secret,billing_code,internal_id
```

---

## 🆘 Troubleshooting

### Q: Logs not appearing?
```bash
# Check if enabled
php artisan tinker
>>> config('superlog.enabled')
# Should return true
```

### Q: Data being masked unexpectedly?
```env
# Check redaction is enabled
SUPERLOG_REDACTION_ENABLED=true
# It's on by default, which is safe!
```

### Q: Too many logs?
```env
# Enable sampling to reduce by 90%
SUPERLOG_SAMPLING_ENABLED=true
```

### Q: Where are the logs?
```bash
# Check here
cat storage/logs/laravel.log
```

---

## 📚 Learn More

- **Want full docs?** → Read `README.md`
- **Need setup details?** → Follow `SETUP_GUIDE.md`
- **Quick API reference?** → See `QUICK_REFERENCE.md`
- **Want architecture?** → Check `ARCHITECTURE.md`
- **Examples?** → See `examples/middleware_integration.php`

---

## 🎉 What's Next?

### Immediate:
1. ✅ Test in local environment (use tail -f)
2. ✅ Try logging custom events
3. ✅ Check trace ID propagation

### Short-term:
1. ✅ Set up production configuration
2. ✅ Enable async shipping
3. ✅ Add custom redaction keys

### Medium-term:
1. ✅ Set up ELK/OpenSearch for log aggregation
2. ✅ Create dashboards in Kibana
3. ✅ Set up alerts for errors

### Long-term:
1. ✅ Analyze log trends
2. ✅ Optimize slow endpoints
3. ✅ Track performance over time

---

## 💡 Key Takeaways

✅ **Automatic** - Captures entire request lifecycle  
✅ **Correlated** - trace_id ties everything together  
✅ **Secure** - Automatic PII masking  
✅ **Fast** - <3ms overhead  
✅ **Scalable** - Async shipping to ELK/OpenSearch  
✅ **Simple** - Works out of the box  

---

## ❓ Questions?

1. Check `README.md` for full documentation
2. See `QUICK_REFERENCE.md` for API
3. Review `SETUP_GUIDE.md` for configuration
4. Look at `examples/` for real-world usage

---

**You're all set!** Start logging like a pro! 🚀

Need help? See the other documentation files in this package.