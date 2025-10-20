# Superlog Diagnostic Command - Auto-Fix Feature

## Overview

The enhanced `superlog:check --diagnostics` command now **automatically detects and fixes** channel routing issues when logs are being written to the wrong channel (e.g., `local` instead of `superlog`).

## The Problem

When running diagnostics, if you see logs being written to the `local` channel instead of `superlog`:

```
[2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END] EncryptCookies - SUCCESS...
```

This typically means:
1. Your middleware is using `Log::info()` instead of `Superlog::log()`
2. Or your `config/logging.php` has the wrong default channel
3. Or middleware are explicitly calling `Log::channel('local')`

## How Auto-Fix Works

### Step 1: Detection
The diagnostic command writes a test entry and checks which channel it's written to:

```bash
php artisan superlog:check --diagnostics
```

### Step 2: Automatic Issue Detection

If logs go to `local` channel, the command will:

```
❌ ISSUE DETECTED: Logs are going to "local" channel instead of "superlog"

This typically means:
  1. Your middleware is using Log::info() instead of Superlog::log()
  2. Or your logging.php "stack" includes "local" before "superlog"

🔧 TO FIX:

Option A: Update config/logging.php (Recommended)
Make sure the "superlog" channel is used as the primary channel:
  'default' => env('LOG_CHANNEL', 'superlog'),

Option B: Update your middleware (if applicable)
Change from:
  use Illuminate\Support\Facades\Log;
  Log::info('Message', $data);

To:
  use Superlog\Facades\Superlog;
  Superlog::log('info', 'SECTION', 'Message', $data);
```

### Step 3: Interactive Fix

The command will offer to automatically update your configuration:

```
Current LOG_CHANNEL setting: local

Would you like me to update config/logging.php to use superlog as the default channel? (yes/no) [yes]: 
```

### Step 4: Automatic Updates

If you confirm, the command will:

1. **Update `config/logging.php`**
   ```php
   'default' => env('LOG_CHANNEL', 'superlog'),
   ```

2. **Show next steps**
   ```
   ✅ Updated config/logging.php
      Changed default channel to "superlog"

   Also update your .env file:
     LOG_CHANNEL=superlog

   Then run: php artisan cache:clear
   ```

## Usage Scenarios

### Scenario 1: Automatic Config Fix

```bash
php artisan superlog:check --diagnostics
```

If the default channel is `local`:
- Command detects the issue
- Offers to fix it automatically
- Updates `config/logging.php` if you confirm
- Provides next steps

### Scenario 2: Manual Detection

If you prefer manual fixes, the command will show:

```
Could not automatically update config. Manual steps:

1. Open config/logging.php
2. Find the 'default' => line and change to:
   'default' => env('LOG_CHANNEL', 'superlog'),
3. Update .env: LOG_CHANNEL=superlog
4. Run: php artisan cache:clear
```

### Scenario 3: Hardcoded Channel References

If logging is already configured correctly but some middleware use hardcoded channels:

```
✓ Logging is already configured to use superlog channel

But the test entry went to "local" channel.
This suggests your middleware or services are using:
  Log::channel('local')->info(...)

Search your code for:
  grep -r "Log::channel('local')" app/
  grep -r "Log::channel(\"local\")" app/
```

## Command Flow

```
┌─────────────────────────────────────────────┐
│ superlog:check --diagnostics                │
└──────────────────┬──────────────────────────┘
                   │
         ┌─────────┴─────────┐
         │                   │
    ✓ Config checks      ✓ Unit tests
    (1-4)               (Test 1-6)
         │                   │
         └─────────────┬─────┘
                       │
              ┌────────▼────────┐
              │ Test 7:         │
              │ Write actual    │
              │ log entry       │
              └────────┬────────┘
                       │
          ┌────────────┴────────────┐
          │                         │
    Goes to superlog    Goes to local
    channel? ✓          channel? ✗
          │                         │
       SUCCESS         ┌────────────▼──────────────┐
          │            │ Detect Issue              │
          │            │ Offer Auto-Fix            │
          │            │ Update config if OK       │
          │            │ Show manual steps if not  │
          │            └───────────────────────────┘
          │                       │
          └───────────┬───────────┘
                      │
              ┌───────▼────────┐
              │ Run command:   │
              │ php artisan    │
              │ cache:clear    │
              └────────────────┘
```

## What Gets Fixed

| Issue | Root Cause | Auto-Fix | Manual Fix |
|-------|-----------|----------|-----------|
| Logs go to `local` channel | Default channel is `local` | ✅ Updates config/logging.php | Set `LOG_CHANNEL=superlog` in .env |
| Logs go to `local` channel | Default channel is `stack` | ✅ Updates config/logging.php | Set `LOG_CHANNEL=superlog` in .env |
| Logs use hardcoded channel | Middleware uses `Log::channel('local')` | ❌ Manual | Search and replace in middleware |
| No Superlog metadata | Middleware uses `Log::info()` | ❌ Manual | Use `Superlog::log()` instead |
| No trace_id:req_seq pattern | `initializeRequest()` not called | ❌ Manual | Add initialization to middleware |

## After Auto-Fix

Once the command updates your configuration, **you must**:

1. **Clear config cache:**
   ```bash
   php artisan cache:clear
   ```

2. **Verify the fix:**
   ```bash
   php artisan superlog:check --diagnostics
   ```

3. **Test in your application:**
   Make a real HTTP request and check the logs:
   ```bash
   tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
   ```

You should now see entries like:
```
[2025-10-20T11:22:22+00:00] superlog.INFO: [trace-id:0000000001] [SECTION] message {...}
```

## Troubleshooting

### Q: The command says it updated the file but logs still go to `local`

**A:** Clear the config cache:
```bash
php artisan config:cache
php artisan cache:clear
```

### Q: The command shows "superlog" is already the default but logs still go to `local`

**A:** Check if middleware are using hardcoded channels:
```bash
grep -r "Log::channel('local')" app/
grep -r "Log::channel(\"local\")" app/
```

Update those lines to use:
```php
use Superlog\Facades\Superlog;

// Instead of: Log::channel('local')->info(...)
// Use:
Superlog::log('info', 'SECTION', 'message', $context);
```

### Q: Can the command fix middleware code automatically?

**A:** No, that requires understanding your business logic. However, the diagnostic clearly shows which files need updating:
- Look for `Log::` calls that should be `Superlog::`
- Look for `Log::channel('local')` hardcoded references
- Review middleware that need `initializeRequest()` calls

## Disable Auto-Fix

If you want to prevent automatic updates to your config files, you can:

1. Set file permissions to read-only:
   ```bash
   chmod 444 config/logging.php
   ```

2. Or just answer "no" when prompted:
   ```
   Would you like me to update config/logging.php? (yes/no) [yes]: no
   ```

## See Also

- [DIAGNOSTIC_COMMAND.md](./DIAGNOSTIC_COMMAND.md) - Full diagnostic command reference
- [INTEGRATION_GUIDE.md](./INTEGRATION_GUIDE.md) - How to integrate Superlog in your app
- [examples/middleware_integration.php](../examples/middleware_integration.php) - Middleware examples