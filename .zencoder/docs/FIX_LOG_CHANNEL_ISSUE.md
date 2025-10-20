# Fix: Logs Going to Local Channel Instead of Superlog

## The Issue (What You Experienced)

When running diagnostics, you saw:

```
✓ Last 5 lines of log file:
  [2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END] EncryptCookies - SUCCESS...
  [2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END] SecureCookies - SUCCESS...
```

❌ **Problem**: Logs showing `local.INFO` instead of `superlog.INFO`

This means your application's logging is routing to the wrong channel.

---

## Why This Happens

### Cause 1: Default LOG_CHANNEL is `local` or `stack`

Your `.env` file or `config/logging.php` sets the default logging channel to something other than `superlog`:

```php
// config/logging.php
'default' => env('LOG_CHANNEL', 'local'),  // ❌ Wrong

// Or in .env
LOG_CHANNEL=local  // ❌ Wrong
```

### Cause 2: Middleware Using Log Facade

Your middleware is using the default Laravel `Log` facade instead of Superlog:

```php
// ❌ Wrong - goes to default channel
use Illuminate\Support\Facades\Log;
Log::info('Middleware executed', $data);

// ✅ Correct - uses Superlog channel
use Superlog\Facades\Superlog;
Superlog::log('info', 'MIDDLEWARE', 'Middleware executed', $data);
```

### Cause 3: Hardcoded Channel in Middleware

Some middleware might be explicitly using the `local` channel:

```php
// ❌ Wrong - hardcoded to local
Log::channel('local')->info('Message', $data);

// ✅ Correct - uses Superlog
Superlog::log('info', 'SECTION', 'Message', $data);
```

---

## Solution: Automatic Fix

The enhanced `superlog:check --diagnostics` command now **automatically detects and fixes** this issue!

### Step 1: Run Diagnostics

```bash
php artisan superlog:check --diagnostics
```

### Step 2: Command Detects Issue

```
❌ ISSUE DETECTED: Logs are going to "local" channel instead of "superlog"

This typically means:
  1. Your middleware is using Log::info() instead of Superlog::log()
  2. Or your logging.php "stack" includes "local" before "superlog"

🔧 Creating automatic fix script...

Current LOG_CHANNEL setting: local

Would you like me to update config/logging.php to use superlog as the default channel? (yes/no) [yes]:
```

### Step 3: Accept Auto-Fix

Answer `yes` to let the command automatically fix your configuration:

```
yes
```

The command will:
- ✅ Update `config/logging.php` to use `superlog` as default channel
- ✅ Show you the next steps
- ✅ Provide commands to clear cache

### Step 4: Complete the Fix

```bash
# Clear config cache (IMPORTANT!)
php artisan cache:clear

# Optional: Also clear app cache
php artisan cache:clear --all

# Verify the fix worked
php artisan superlog:check --test
```

### Step 5: Verify Success

```bash
php artisan superlog:check --test
```

You should now see:

```
✓ Last 5 lines of log file:
  [2025-10-20 11:22:22] superlog.INFO: [STARTUP] GET /api/users...
  [2025-10-20 11:22:22] superlog.INFO: [MIDDLEWARE-END] EncryptCookies...
```

✅ **Success**: Logs now show `superlog.INFO` instead of `local.INFO`

---

## Manual Fix (If Auto-Fix Doesn't Work)

If you prefer manual updates or auto-fix didn't work:

### Option A: Update .env File

Edit `.env` and set:

```env
LOG_CHANNEL=superlog
SUPERLOG_ENABLED=true
```

### Option B: Update config/logging.php

Edit `config/logging.php`:

```php
return [
    'default' => env('LOG_CHANNEL', 'superlog'),  // ← Change this
    
    'channels' => [
        'superlog' => [
            'driver' => 'superlog',
            'name' => 'superlog',
            'level' => 'debug',
        ],
        // ... other channels ...
    ],
];
```

### Option C: Update Middleware to Use Superlog

If you have custom middleware, update them to use Superlog:

**Before (❌ Wrong):**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('Processing request', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);
        
        return $next($request);
    }
}
```

**After (✅ Correct):**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Superlog\Facades\Superlog;

class CustomMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Superlog::log('info', 'CUSTOM_MIDDLEWARE', 'Processing request', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);
        
        return $next($request);
    }
}
```

### Option D: Find and Replace Hardcoded Channels

Search for and replace hardcoded channel references:

```bash
# Find all usages of hardcoded local channel
grep -r "Log::channel('local')" app/

# Replace them with Superlog
# Before:
#   Log::channel('local')->info('message');
# After:
#   Superlog::log('info', 'SECTION', 'message');
```

---

## What Gets Fixed

| Component | Before | After |
|-----------|--------|-------|
| Default LOG_CHANNEL | `local` | `superlog` |
| Log file prefix | `laravel.log` with `local.INFO` | `laravel.log` with `superlog.INFO` |
| Log metadata | No trace_id:seq | Includes `[trace-id:seq]` |
| Format | Generic Laravel format | Superlog structured format |

---

## Log Format Comparison

### Before Fix (❌)
```
[2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END] EncryptCookies - SUCCESS {"request_id":"..."}
```

### After Fix (✅)
```
[2025-10-20T11:22:22+00:00] superlog.INFO: [trace-id-abc123:0000000001] [MIDDLEWARE-END] EncryptCookies - SUCCESS {...}
```

**Key Differences:**
- ✅ `superlog.INFO` instead of `local.INFO`
- ✅ ISO8601 timestamp with timezone
- ✅ Trace ID with sequence: `[trace-id:seq]`
- ✅ Structured metadata

---

## Verification After Fix

### Check 1: Log File Channel

```bash
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep superlog
```

You should see `superlog.INFO` entries.

### Check 2: Config Cache

```bash
# Make sure cache is cleared
php artisan config:cache
php artisan config:clear

# Or just:
php artisan cache:clear
```

### Check 3: Run Diagnostics Again

```bash
php artisan superlog:check --diagnostics
```

Should show:
```
Test 7: Writing actual test entry to superlog channel... ✓
✅ Format verification:
  Channel: ✓ superlog
```

### Check 4: Make Real Request

```bash
# Make an HTTP request to your app
curl http://localhost/api/test

# Check logs immediately
tail storage/logs/laravel-$(date +%Y-%m-%d).log
```

Look for:
```
[timestamp] superlog.INFO: [trace-id:seq] [SECTION] message
```

---

## Troubleshooting Auto-Fix

### Q: Command says it updated but logs still show `local`

**A:** The PHP config cache needs to be cleared:
```bash
php artisan cache:clear --all
php artisan config:clear
```

Then verify:
```bash
php artisan superlog:check --test
```

### Q: Command says I'm already on `superlog` but logs show `local`

**A:** Some middleware might be hardcoding the channel:
```bash
# Search for hardcoded references
grep -r "channel('local')" app/
grep -r '"local"' app/Http/Middleware/

# Replace with Superlog:
sed -i "s/Log::channel('local')/Superlog::log/g" app/Http/Middleware/*.php
```

### Q: File permission error during auto-fix

**A:** Make sure config files are writable:
```bash
chmod 644 config/logging.php
chmod 755 config/
```

Or answer "no" to auto-fix and do it manually.

### Q: Nothing changed after auto-fix

**A:** Check if the change was actually applied:
```bash
grep "LOG_CHANNEL" config/logging.php
grep "'default'" config/logging.php
```

If not changed, check file permissions and try manual fix instead.

---

## Prevention

To prevent this issue in the future:

1. **Set correct default during setup:**
   ```env
   LOG_CHANNEL=superlog
   ```

2. **In middleware, always use Superlog:**
   ```php
   use Superlog\Facades\Superlog;
   
   public function handle($request, Closure $next) {
       Superlog::log(...);
   }
   ```

3. **Add to your code review checklist:**
   - ❌ No `Log::info()` in new middleware
   - ❌ No `Log::channel('local')` in code
   - ✅ All logging uses `Superlog::log()`

4. **Use lint rules:**
   Add to `phpstan.neon`:
   ```yaml
   parameters:
       forbiddenFunctions:
           - Log::channel('local')
   ```

---

## Quick Reference

| Task | Command |
|------|---------|
| Auto-detect and fix | `php artisan superlog:check --diagnostics` |
| Verify after fix | `php artisan superlog:check --test` |
| Clear cache | `php artisan cache:clear` |
| Find hardcoded channels | `grep -r "channel('local')" app/` |
| View last logs | `tail storage/logs/laravel-$(date +%Y-%m-%d).log` |
| Search logs | `grep "superlog.INFO" storage/logs/*.log` |

---

## Still Having Issues?

Run the comprehensive diagnostic with maximum verbosity:

```bash
DEBUG=1 php artisan superlog:check --diagnostics 2>&1 | tee diagnostic_output.txt
```

Then share the output for support.