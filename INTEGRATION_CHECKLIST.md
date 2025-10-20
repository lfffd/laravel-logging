# Superlog Integration Checklist for Laravel Apps

## ✓ Step-by-Step Verification

### 1. **Check config/superlog.php**
```bash
# Should exist and be published
ls -la config/superlog.php
```
- [ ] File exists
- [ ] `enabled` is `true`
- [ ] `format` is `'text'` or `'json'` (not something else)

### 2. **Check config/logging.php** 
Look for this structure:
```php
'channels' => [
    'superlog' => [
        'driver' => 'custom',
        'via' => \Superlog\Handlers\SuperlogHandler::class,
    ],
    // ... other channels
],
```
- [ ] `'superlog'` channel exists
- [ ] Driver is `'custom'`
- [ ] `'via'` points to `SuperlogHandler::class`

### 3. **Check Your Middleware**
Find the middleware that logs `[MIDDLEWARE END]`:

**BAD ✗ (Current - using Log facade):**
```php
use Illuminate\Support\Facades\Log;

class MyMiddleware {
    public function handle($request, $next) {
        // ...
        Log::info('[MIDDLEWARE END]', ['status' => 'success']);
        return $response;
    }
}
```

**GOOD ✓ (What it should be):**
```php
use Superlog\Facades\Superlog;

class MyMiddleware {
    public function handle($request, $next) {
        // Initialize ONCE at start of request
        if (!app()->has('superlog.initialized')) {
            Superlog::initializeRequest(
                $request->getMethod(),
                $request->getPathInfo(),
                $request->ip()
            );
            app()->bind('superlog.initialized', true);
        }
        
        // Use Superlog, not Log
        Superlog::logMiddlewareEnd(
            static::class,
            $duration,
            $response->getStatusCode()
        );
        return $response;
    }
}
```

- [ ] Import `Superlog` facade, not `Log`
- [ ] Call `Superlog::initializeRequest()` once per request
- [ ] Use `Superlog::log()` or `Superlog::logMiddlewareEnd()` instead of `Log::info()`

### 4. **Create an Initialization Middleware** (if not exists)
Create `app/Http/Middleware/InitializeSuperlog.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Superlog\Facades\Superlog;

class InitializeSuperlog
{
    public function handle(Request $request, Closure $next)
    {
        // Initialize Superlog at the very start
        Superlog::initializeRequest(
            $request->getMethod(),
            $request->getPathInfo(),
            $request->ip(),
            // Optional: pass trace_id from header if exists
            $request->header('X-Trace-ID')
        );

        // Log request startup
        Superlog::logStartup([
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'full_url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return $next($request);
    }
}
```

Then register in `app/Http/Kernel.php`:
```php
protected $middleware = [
    // ... other middleware
    \App\Http\Middleware\InitializeSuperlog::class,
];
```

- [ ] Middleware created
- [ ] Registered in Kernel.php
- [ ] It's in the FIRST group of middleware (global)

### 5. **Test the Configuration**
```bash
php artisan superlog:check --test
```

Expected output:
```
Checking Superlog Configuration...
✓ Found at: config/superlog.php
✓ Channel "superlog" is configured
✓ Superlog is enabled
✓ Directory is writable
✓ Test entry written to superlog channel
✓ Last 5 lines show [trace_id:req_seq] format
```

- [ ] All checks pass (✓)
- [ ] Last 5 lines show `[UUID:0000000001]` format

### 6. **Verify Log Output**
```bash
tail -f storage/logs/laravel-*.log
```

Expected format:
```
[2025-10-20T10:47:15.123456Z] superlog.INFO: [abc-def-ghi-123:0000000001] [MIDDLEWARE END] EncryptCookies - SUCCESS
```

Should contain:
- [ ] `superlog` channel (not `local`)
- [ ] ISO8601 timestamp (with T and Z)
- [ ] `[trace_id:req_seq]` format
- [ ] Proper section name like `[MIDDLEWARE END]`

---

## 🔍 Common Issues & Solutions

| Issue | Symptom | Solution |
|-------|---------|----------|
| Wrong logger | Logs show `local.INFO` not `superlog.INFO` | Change `Log::` to `Superlog::` |
| No trace_id | Logs don't show `[uuid:seq]` | Call `initializeRequest()` |
| Wrong timestamp | Shows `2025-10-20 10:47:15` not `2025-10-20T10:47:15.123Z` | Set `format` to `'text'` in config |
| Empty channel | Channel in logs is empty | Configure `superlog` channel in config/logging.php |
| initializeRequest error | "Call to undefined method" | Import: `use Superlog\Facades\Superlog;` |

---

## 📋 Final Checklist

Before running in production:

- [ ] `config/superlog.php` exists and is published
- [ ] `config/logging.php` has `'superlog'` channel configured
- [ ] All middleware use `Superlog::` facade (search for `Log::` to verify)
- [ ] `InitializeSuperlog` middleware is registered
- [ ] `php artisan superlog:check --test` shows all ✓
- [ ] `tail -f storage/logs/laravel-*.log` shows correct format
- [ ] Trace IDs appear in logs with proper `[uuid:seq]` format
- [ ] No errors in Laravel logs

---

## 🚀 Once Complete

The logs will show:
```
[2025-10-20T10:47:15.123456Z] superlog.INFO: [550e8400-e29b-41d4:0000000001] [STARTUP] Request started
[2025-10-20T10:47:15.234567Z] superlog.INFO: [550e8400-e29b-41d4:0000000002] [MIDDLEWARE END] EncryptCookies - SUCCESS
[2025-10-20T10:47:15.345678Z] superlog.INFO: [550e8400-e29b-41d4:0000000003] [MIDDLEWARE END] SecureCookies - SUCCESS
```

Notice:
- ✅ Same trace_id throughout request (`550e8400-e29b-41d4`)
- ✅ Incrementing req_seq per log line
- ✅ `superlog` channel (not `local`)
- ✅ ISO8601 timestamps with milliseconds
- ✅ Proper section names