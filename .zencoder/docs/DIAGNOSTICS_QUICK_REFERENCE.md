# Superlog Diagnostics - Quick Reference 🚀

## The Problem

Your logs are being written to the **wrong channel**:

```
❌ Wrong: [2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END]
✅ Right: [2025-10-20 11:22:22] superlog.INFO: [trace-id:seq] [SECTION] message
```

## The Solution

### 1️⃣ Run Diagnostics

```bash
php artisan superlog:check --diagnostics
```

### 2️⃣ Let It Auto-Fix

When prompted:
```
Would you like me to automatically fix these issues? (yes/no) [yes]: yes
```

### 3️⃣ Clear Cache

```bash
php artisan cache:clear
```

### 4️⃣ Verify Success

```bash
php artisan superlog:check --diagnostics
```

Expected output:
```
✅ Test entry successfully written and verified!
✅ APPLICATION INTEGRATION SUCCESSFUL
```

---

## Configuration Files Checked

### 1. `.env`
```bash
LOG_CHANNEL=superlog  # ✅ Must be 'superlog'
```

### 2. `config/superlog.php`
```php
'channel' => env('LOG_CHANNEL', 'superlog'),  // ✅ Must be 'superlog'
```

### 3. `config/logging.php`
```php
'default' => env('LOG_CHANNEL', 'superlog'),  // ✅ Must be 'superlog'

'channels' => [
    'superlog' => [...],  // ✅ Must exist
    'stack' => [
        'channels' => ['superlog', 'local'],  // ✅ 'superlog' must be FIRST
    ]
]
```

---

## Common Issues & Fixes

### Issue 1: "Logs still go to `local`"

**Check**: Are middleware using `Log::info()` instead of `Superlog::log()`?

```bash
# Search for wrong usage
grep -r "Log::info\|Log::warning\|Log::error" app/
```

**Fix**: Replace with Superlog facade
```php
// ❌ Wrong
use Illuminate\Support\Facades\Log;
Log::info('message', $data);

// ✅ Correct
use Superlog\Facades\Superlog;
Superlog::log('info', 'SECTION', 'message', $data);
```

### Issue 2: "Missing trace_id:req_seq in logs"

**Check**: Is middleware initializing Superlog?

```php
// ✅ Required at request start
Superlog::initializeRequest(
    request()->method(),
    request()->path(),
    request()->ip(),
    $traceId
);
```

### Issue 3: "Config changes don't take effect"

**Fix**: Cache must be cleared
```bash
php artisan cache:clear

# Or restart if using file caching
rm -rf bootstrap/cache/
```

---

## What Gets Fixed Automatically

| Item | Fixed | How |
|------|-------|-----|
| `.env` LOG_CHANNEL | ✅ | Adds/updates line |
| `config/logging.php` default | ✅ | Regex replacement |
| `config/superlog.php` channel | ✅ | Regex replacement |
| Stack channel order | ⚠️ No | Manual - requires config knowledge |
| Middleware code | ⚠️ No | Manual - requires code review |

---

## Manual Fix (If Auto-Fix Fails)

### Step 1: Update .env
```bash
echo "LOG_CHANNEL=superlog" >> .env
# Or edit .env directly
```

### Step 2: Update config/logging.php
Find this:
```php
'default' => env('LOG_CHANNEL', 'local'),
```

Change to:
```php
'default' => env('LOG_CHANNEL', 'superlog'),
```

### Step 3: Update config/superlog.php
Find this:
```php
'channel' => env('LOG_CHANNEL', 'stack'),
```

Change to:
```php
'channel' => env('LOG_CHANNEL', 'superlog'),
```

### Step 4: Clear Cache
```bash
php artisan cache:clear
```

---

## Verify the Fix

### Test 1: Check Configuration
```bash
php artisan superlog:check
```

All checks should show ✓

### Test 2: Write Test Entry
```bash
php artisan superlog:check --test
```

Should say test entry was written ✓

### Test 3: Run Full Diagnostics
```bash
php artisan superlog:check --diagnostics
```

Should end with ✅ APPLICATION INTEGRATION SUCCESSFUL

### Test 4: Make Real Request
```bash
# In another terminal
curl http://yourapp.local/

# Check the logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

Should see:
```
[2025-10-20T11:22:22+00:00] superlog.INFO: [trace-id:seq] [SECTION] message
```

---

## Diagnostics Command Reference

### Check Everything
```bash
php artisan superlog:check
```

### Check + Write Test
```bash
php artisan superlog:check --test
```

### Check + Run Full Diagnostics
```bash
php artisan superlog:check --diagnostics
```

### Check + Test + Diagnostics
```bash
php artisan superlog:check --test --diagnostics
```

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Auto-fix doesn't apply | Regex pattern mismatch | Use manual fixes |
| Permission denied | File not writable | `chmod 644 config/* .env` |
| Nothing changes after fix | Cache not cleared | `php artisan cache:clear` |
| Logs still wrong | Middleware not fixed | Search & fix hardcoded channels |
| No test entry found | Channel not configured | Check logging.php |

---

## Files to Check

```
Laravel App
├── .env                      # LOG_CHANNEL=superlog
├── config/
│   ├── logging.php          # 'default' and 'channels'
│   └── superlog.php         # 'channel' setting
├── app/
│   ├── Http/Middleware/     # Check Log:: vs Superlog::
│   └── Logging/             # Check custom handlers
└── storage/
    └── logs/
        └── laravel-*.log    # Verify channel in output
```

---

## What Success Looks Like

### ✅ Configuration Check
```
Checking config file... ✓ Found at: /var/www/app/config/superlog.php
Checking logging channel... ✓ Channel "superlog" is configured
Checking if Superlog is enabled... ✓ Superlog is enabled
Checking Superlog channel... ✓ Configured to use "superlog" channel
Checking LOG_CHANNEL env... ✓ Properly set to "superlog"
Checking log directory... ✓ Directory is writable
```

### ✅ Diagnostic Test
```
Test 7: Writing actual test entry to superlog channel... ✓

✅ Test entry successfully written and verified!

Format verification:
  Channel: ✓ superlog
  Timestamp: ✓ ISO8601
  Trace ID + Seq: ✓ [id:seq] found
  Data preserved: ✓ Yes

✅ APPLICATION INTEGRATION SUCCESSFUL
Your Superlog is properly configured and working!
```

### ✅ Log File
```
[2025-10-20T11:22:22+00:00] superlog.INFO: [a1b2c3d4:0000000001] [MIDDLEWARE END] EncryptCookies - SUCCESS
[2025-10-20T11:22:22+00:00] superlog.INFO: [a1b2c3d4:0000000002] [MIDDLEWARE END] SecureCookies - SUCCESS
[2025-10-20T11:22:22+00:00] superlog.INFO: [a1b2c3d4:0000000003] [MIDDLEWARE END] TrustProxies - SUCCESS
```

---

## Next Steps

1. ✅ Run: `php artisan superlog:check --diagnostics`
2. ✅ Confirm auto-fix when prompted
3. ✅ Run: `php artisan cache:clear`
4. ✅ Verify: `php artisan superlog:check --diagnostics`
5. ✅ Test in app: Make HTTP request
6. ✅ Check logs: `tail -f storage/logs/laravel-*.log`

**🎉 Done!** Your Superlog is now properly configured.

---

## More Help

- 📖 Full guide: `COMPREHENSIVE_DIAGNOSTICS.md`
- 🔧 Advanced: `DIAGNOSTIC_AUTO_FIX.md`
- 🐛 Troubleshooting: `FIX_LOG_CHANNEL_ISSUE.md`
- 📋 All commands: `COMMAND_REFERENCE.md`
- 🌍 Real examples: `DIAGNOSTICS_REAL_WORLD.md`