# Superlog Diagnostics - Real World Usage Guide

## Quick Start

### 1. Initial Check (2 seconds)

```bash
php artisan superlog:check
```

Output:
```
🔍 Checking Superlog Configuration...

Checking config file... ✓ Found at: /var/www/html/idme/config/superlog.php
Checking logging channel... ✓ Channel "superlog" is configured
Checking if Superlog is enabled... ✓ Superlog is enabled
Checking log directory... ✓ Directory is writable: /var/www/html/idme/storage/logs

✅ Superlog is properly configured!
```

### 2. Integration Test (3 seconds)

```bash
php artisan superlog:check --test
```

Output includes:
```
📝 Writing test entries...
✓ Test entry written to superlog channel
✓ Log file found: /var/www/html/idme/storage/logs/laravel-2025-10-20.log
✓ Last 5 lines of log file:
  [2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END] EncryptCookies...
```

**Issue detected!** Logs are going to `local` instead of `superlog` ❌

### 3. Full Diagnostics with Auto-Fix (5 seconds)

```bash
php artisan superlog:check --diagnostics
```

### Expected Output (Success Case)

```
🔍 Checking Superlog Configuration...
[✓ checks...]

📝 Writing test entries...
✓ Test entry written to superlog channel

🧪 Running Diagnostic Tests...
Test 1: Trace ID generation... ✓ Trace ID: test-trace-id-001
Test 2: Request sequence numbering... ✓ Sequence: 0000000001 → 0000000002 → 0000000003
Test 3: Text formatting... ✓ Format includes trace_id:req_seq
Test 4: JSON formatting... ✓ JSON includes trace_id and req_seq
Test 5: Trace ID isolation... ✓ Different requests have different trace IDs
Test 6: Sequence reset per request... ✓ Sequence resets to 0000000001 per request

✅ Unit tests passed! Superlog package works correctly.

🔍 Checking actual application logs...
Test 7: Writing actual test entry to superlog channel... ✓

✅ Test entry successfully written and verified!

Log entry details:
  [2025-10-20T11:33:54+00:00] superlog.INFO: [trace-id:0000000001] [TEST] message {...}

✅ Format verification:
  Channel: ✓ superlog
  Timestamp: ✓ ISO8601
  Trace ID + Seq: ✓ [id:seq] found
  Data preserved: ✓ Yes

✅ APPLICATION INTEGRATION SUCCESSFUL
Your Superlog is properly configured and working!
```

### Troubleshooting Case: Logs Go to Wrong Channel

```
Test 7: Writing actual test entry to superlog channel... ✗

❌ Test entry found but with incorrect format

Actual log entry:
  [2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END] EncryptCookies - SUCCESS...

Format verification:
  Channel: ✗
  Timestamp: ✓
  Trace ID + Seq: ✗
  Data: ✓

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

🔧 Creating automatic fix script...

Current LOG_CHANNEL setting: local

Would you like me to update config/logging.php to use superlog as the default channel? (yes/no) [yes]: yes

✅ Updated config/logging.php
   Changed default channel to "superlog"

Also update your .env file:
  LOG_CHANNEL=superlog

Then run: php artisan cache:clear

🔄 After fixing, run the diagnostic again:
  php artisan superlog:check --diagnostics
```

## Common Issues & Solutions

### Issue 1: Logs in `local` Channel

**Symptom:**
```
Test 7: Writing actual test entry to superlog channel... ✗
Channel: ✗ (shows local.INFO instead of superlog.INFO)
```

**Root Cause:**
- Default LOG_CHANNEL is `local`
- Or middleware are hardcoding `Log::channel('local')`

**Solution 1 - Let Auto-Fix Update Config (Easiest):**
```bash
php artisan superlog:check --diagnostics
# Answer "yes" to auto-fix prompt
php artisan cache:clear
php artisan superlog:check --diagnostics  # Verify
```

**Solution 2 - Manual Update:**
1. Edit `.env`:
   ```
   LOG_CHANNEL=superlog
   ```

2. Edit `config/logging.php`:
   ```php
   'default' => env('LOG_CHANNEL', 'superlog'),
   ```

3. Clear cache:
   ```bash
   php artisan cache:clear
   ```

### Issue 2: No Trace ID or Sequence Numbers

**Symptom:**
```
Test 3: Text formatting... ✗ Format missing trace_id:req_seq
```

**Root Cause:**
- `initializeRequest()` not being called in middleware
- Or wrong middleware is logging

**Solution:**
Make sure your middleware calls `initializeRequest()`:

```php
use Superlog\Facades\Superlog;

public function handle($request, Closure $next)
{
    // Initialize Superlog for this request
    Superlog::initializeRequest(
        $request->method(),
        $request->path(),
        $request->ip()
    );
    
    return $next($request);
}
```

### Issue 3: Log File Not Found

**Symptom:**
```
Test 7: Writing actual test entry... ✗
❌ Log file was not created
```

**Root Cause:**
- Log directory doesn't exist
- Directory not writable
- Logging disabled

**Solution:**
```bash
# Check directory exists
ls -la storage/logs/

# Check writable
touch storage/logs/test.txt
rm storage/logs/test.txt

# Check logging enabled in .env
SUPERLOG_ENABLED=true
LOG_CHANNEL=superlog
```

### Issue 4: Test Entry Not In Logs

**Symptom:**
```
Test 7: Writing actual test entry... ✗
❌ Test entry was not found in log file

This means Superlog is NOT writing to the log file.
```

**Root Cause:**
- Superlog channel not configured in `logging.php`
- Or handler not attached to channel

**Solution:**
Verify `config/logging.php` has:
```php
'channels' => [
    // ... other channels ...
    'superlog' => [
        'driver' => 'superlog',
        'name' => 'superlog',
        'level' => 'debug',
    ],
],
```

## Real Application Workflow

### Step 1: First Time Setup

```bash
# Publish config
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider" --tag=config

# Basic checks
php artisan superlog:check
# Output should show:
#   ✓ Config published
#   ✓ Channel configured
#   ✓ Enabled
#   ✓ Directory writable
```

### Step 2: Test Integration

```bash
# Run with test entry
php artisan superlog:check --test

# Check output - look for channel
# Should say: superlog.INFO (not local.INFO)
```

### Step 3: If Logs Go to Wrong Channel

```bash
# Run diagnostics with auto-fix
php artisan superlog:check --diagnostics

# Follow the prompts - let it auto-fix
# Answer "yes" to update config

# Clear cache
php artisan cache:clear

# Verify it worked
php artisan superlog:check --test
```

### Step 4: Make a Real Request

```bash
# In one terminal, watch logs:
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# In another terminal, make a request:
curl http://localhost/api/users

# You should see entries like:
# [2025-10-20T11:22:22+00:00] superlog.INFO: [uuid:0000000001] [STARTUP] ...
# [2025-10-20T11:22:22+00:00] superlog.INFO: [uuid:0000000002] [DATABASE] ...
# [2025-10-20T11:22:22+00:00] superlog.INFO: [uuid:0000000003] [SHUTDOWN] ...
```

### Step 5: Verify Middleware Integration

Check if your middleware are using Superlog:

```bash
# Check for hardcoded Log::channel('local')
grep -r "Log::channel('local')" app/

# Replace with Superlog if found:
# Before:
#   Log::info('Message', $data);
#
# After:
#   Superlog::log('info', 'SECTION', 'Message', $data);
```

## Diagnostic Output Reference

### Config Check Results

| Check | ✓ Pass | ✗ Fail |
|-------|--------|--------|
| Config file published | Found at path | Run vendor:publish |
| Channel configured | Channel "superlog" is configured | Add to logging.php |
| Superlog enabled | Superlog is enabled | Set SUPERLOG_ENABLED=true |
| Log directory writable | Directory is writable | chmod 775 storage/logs |

### Unit Test Results

| Test | Success | Fail |
|------|---------|------|
| Trace ID generation | UUID v4 format valid | Check CorrelationContext |
| Sequence numbering | 0000000001 → 0000000002 → ... | Check counter |
| Text formatting | Contains trace_id:req_seq | Check formatLogEntry |
| JSON formatting | trace_id and req_seq in JSON | Check JSON encoder |
| Trace ID isolation | Different per request | Check initialization |
| Sequence reset | Resets to 0000000001 | Check per-request scope |

### Integration Test Results

| Test | Success | Fail |
|------|---------|------|
| Channel | superlog.INFO | Check default channel |
| Timestamp | ISO8601 format | Check timezone |
| Trace ID:Seq | [trace:seq] present | Check initialization |
| Data | All fields preserved | Check handler |

## Command Combinations

```bash
# Just check if config is valid
php artisan superlog:check

# Check config + write test entry
php artisan superlog:check --test

# Just run unit tests (no test entry)
php artisan superlog:check --diagnostics

# Everything (recommended for troubleshooting)
php artisan superlog:check --test --diagnostics
```

## Monitoring in Production

Use diagnostics to monitor Superlog health:

```bash
# Add to deployment checklist
php artisan superlog:check

# Add to health check endpoint
php artisan superlog:check --test
if [ $? -eq 0 ]; then
    echo "Superlog is healthy"
else
    echo "Superlog has issues"
    php artisan superlog:check --diagnostics
fi
```

## Debug Mode

To get more detailed output during diagnostics:

```bash
# Add DEBUG=1 for verbose logging
DEBUG=1 php artisan superlog:check --diagnostics

# Or set in .env
SUPERLOG_DEBUG=true
php artisan superlog:check --diagnostics
```

## Next Steps

After successful diagnostics:

1. ✅ Make real HTTP requests to test
2. ✅ Check log files for proper format
3. ✅ Set up log rotation policies
4. ✅ Configure external log shipping (optional)
5. ✅ Set up monitoring/alerting on logs