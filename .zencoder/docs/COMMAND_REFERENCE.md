# Superlog Diagnostic Command - Complete Reference

## Command Signature

```bash
php artisan superlog:check {--test} {--diagnostics}
```

## Options

### No Options (Basic Check)
```bash
php artisan superlog:check
```

**What it does:**
- ✓ Checks if config file is published
- ✓ Checks if `superlog` channel is configured in `logging.php`
- ✓ Checks if Superlog is enabled
- ✓ Checks if log directory is writable

**Execution time:** ~1 second

**Output:**
```
🔍 Checking Superlog Configuration...

Checking config file... ✓ Found at: /path/to/config/superlog.php
Checking logging channel... ✓ Channel "superlog" is configured
Checking if Superlog is enabled... ✓ Superlog is enabled
Checking log directory... ✓ Directory is writable: /path/to/storage/logs

✅ Superlog is properly configured!
```

**Use when:** Setting up Superlog for the first time, deployment checklist.

---

### --test Option
```bash
php artisan superlog:check --test
```

**What it does:**
- ✓ Runs all basic checks
- ✓ Writes a real test entry to the Superlog channel
- ✓ Verifies the log file was created
- ✓ Shows the last 5 log lines

**Execution time:** ~2 seconds

**Output:**
```
🔍 Checking Superlog Configuration...
[... basic checks ...]

📝 Writing test entries...
✓ Test entry written to superlog channel
✓ Log file found: /path/to/laravel-2025-10-20.log
✓ Last 5 lines of log file:
  [2025-10-20 11:22:22] superlog.INFO: [trace-id:0000000001] [STARTUP] GET /api/users
  [2025-10-20 11:22:22] superlog.INFO: [trace-id:0000000002] [DATABASE] Query executed
  ...
```

**Use when:** 
- Verifying logs are actually being written to files
- Checking if the log channel routing is correct
- Troubleshooting log file permission issues

---

### --diagnostics Option
```bash
php artisan superlog:check --diagnostics
```

**What it does:**
- ✓ Runs all basic checks
- ✓ Runs 7 comprehensive unit tests on Superlog package:
  1. Trace ID generation (UUID v4 format)
  2. Request sequence numbering (0000000001 → 0000000002 → ...)
  3. Text formatting (trace_id:req_seq pattern)
  4. JSON formatting (all metadata included)
  5. Trace ID isolation (different per request)
  6. Sequence reset (per-request scope)
  7. Actual log file integration test
- ✓ Writes a real test entry to verify integration
- ✓ **AUTO-DETECTS AND OFFERS TO FIX channel routing issues**

**Execution time:** ~5 seconds

**Output (Success):**
```
🔍 Checking Superlog Configuration...
[... basic checks ...]

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

✅ Format verification:
  Channel: ✓ superlog
  Timestamp: ✓ ISO8601
  Trace ID + Seq: ✓ [id:seq] found
  Data preserved: ✓ Yes

✅ APPLICATION INTEGRATION SUCCESSFUL
Your Superlog is properly configured and working!
```

**Output (Channel Routing Issue Detected):**
```
Test 7: Writing actual test entry to superlog channel... ✗

❌ Test entry found but with incorrect format

Actual log entry:
  [2025-10-20 11:22:22] local.INFO: [MIDDLEWARE END] EncryptCookies...

❌ ISSUE DETECTED: Logs are going to "local" channel instead of "superlog"

🔧 Creating automatic fix script...

Current LOG_CHANNEL setting: local

Would you like me to update config/logging.php to use superlog as the default channel? (yes/no) [yes]:
```

**Use when:**
- Full integration testing
- Troubleshooting logging issues
- Deployment verification
- Monitoring log system health

---

### Combined Options
```bash
php artisan superlog:check --test --diagnostics
```

**What it does:**
- ✓ Runs all basic checks
- ✓ Writes a test entry with `--test`
- ✓ Runs all 7 diagnostic tests with `--diagnostics`

**Execution time:** ~5 seconds (same as `--diagnostics` alone, since `--diagnostics` also writes test entry)

**Use when:** You want comprehensive testing with all features.

---

## Exit Codes

| Code | Meaning | When |
|------|---------|------|
| `0` | ✅ Success | All checks passed |
| `1` | ❌ Failure | At least one check failed |

**Use in scripts:**
```bash
php artisan superlog:check
if [ $? -eq 0 ]; then
    echo "Superlog is healthy"
else
    echo "Superlog has issues"
    exit 1
fi
```

---

## Common Usage Patterns

### Pattern 1: Quick Health Check

```bash
# In your deployment script or health endpoint
php artisan superlog:check
```

**Returns:**
- 0 if all config is valid
- 1 if something is misconfigured

### Pattern 2: Verify Logging Works

```bash
# Write and verify a test entry
php artisan superlog:check --test

# Check last log:
tail -1 storage/logs/laravel-$(date +%Y-%m-%d).log
```

### Pattern 3: Full Diagnosis (Troubleshooting)

```bash
# Run everything with auto-fix
php artisan superlog:check --test --diagnostics

# If auto-fix was applied:
php artisan cache:clear
php artisan superlog:check --test --diagnostics
```

### Pattern 4: Monitoring (Cron)

```bash
# In crontab
*/5 * * * * cd /app && php artisan superlog:check --test >> /tmp/superlog_health.log 2>&1
```

Then monitor the exit code/log file.

### Pattern 5: CI/CD Pipeline

```bash
# In GitHub Actions / GitLab CI / Jenkins
php artisan superlog:check --diagnostics || exit 1
```

---

## What Each Check Does

### Config File Check
```
Checking config file...
```

**Tests:**
- Does `config/superlog.php` exist?

**Fix if failed:**
```bash
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider" --tag=config
```

---

### Channel Configuration Check
```
Checking logging channel...
```

**Tests:**
- Is `channels['superlog']` configured in `config/logging.php`?
- Is the driver set to `'superlog'`?

**Fix if failed:**
Add to `config/logging.php`:
```php
'superlog' => [
    'driver' => 'superlog',
    'name' => 'superlog',
    'level' => 'debug',
],
```

---

### Enabled Check
```
Checking if Superlog is enabled...
```

**Tests:**
- Is `SUPERLOG_ENABLED` not false?

**Fix if failed:**
Set in `.env`:
```env
SUPERLOG_ENABLED=true
```

---

### Directory Writable Check
```
Checking log directory...
```

**Tests:**
- Does `storage/logs` directory exist?
- Is it writable by the web server?

**Fix if failed:**
```bash
mkdir -p storage/logs
chmod 775 storage/logs
```

---

### Test Entry Write (with --test)
```
Writing test entries...
```

**Tests:**
- Can Superlog write to the configured channel?
- Does the log file get created?
- Are entries visible in the log file?

**Fix if failed:**
- Check channel configuration
- Check file permissions
- Check disk space

---

### Unit Tests (with --diagnostics)

#### Test 1: Trace ID Generation
```
Test 1: Trace ID generation...
```
- Generates UUID v4 formatted trace ID
- Verifies it matches pattern: `[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-...`

#### Test 2: Request Sequence
```
Test 2: Request sequence numbering...
```
- Creates entries and verifies: 0000000001 → 0000000002 → 0000000003
- Checks counter increments properly

#### Test 3: Text Formatting
```
Test 3: Text formatting...
```
- Formats entry as text
- Verifies format includes: `[trace-id:req-seq]` pattern

#### Test 4: JSON Formatting
```
Test 4: JSON formatting...
```
- Formats entry as JSON
- Verifies all metadata is included:
  - `trace_id`
  - `req_seq`
  - `level`
  - `message`
  - `context`

#### Test 5: Trace ID Isolation
```
Test 5: Trace ID isolation...
```
- Creates two requests
- Verifies each gets different trace ID

#### Test 6: Sequence Reset
```
Test 6: Sequence reset per request...
```
- Creates new request
- Verifies req_seq resets to 0000000001

#### Test 7: Integration Test
```
Test 7: Writing actual test entry to superlog channel...
```
- **Most important test!**
- Writes real entry to log file
- Verifies it appears in correct channel
- Verifies format includes all required fields
- **AUTO-DETECTS if logs go to wrong channel**

---

## Output Colors & Symbols

### Symbols

| Symbol | Meaning | Color |
|--------|---------|-------|
| `✓` | Check passed | Green |
| `✗` | Check failed | Red |
| `ℹ` | Information | Blue |
| `⚠` | Warning | Yellow |
| `✅` | Success/Complete | Green |
| `❌` | Error/Incomplete | Red |

### Examples

```
✓ Found at: /path/to/config   ← Success
✗ Not found                      ← Failure
```

---

## Error Messages & Solutions

### Error: "config file not found"
```
Checking config file... ✗ Not found.
```
**Solution:**
```bash
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider" --tag=config
```

---

### Error: "Channel not configured"
```
Checking logging channel... ✗ Not found.
```
**Solution:** Add to `config/logging.php`:
```php
'superlog' => [
    'driver' => 'superlog',
    'name' => 'superlog',
    'level' => 'debug',
],
```

---

### Error: "Superlog is disabled"
```
Checking if Superlog is enabled... ✗ Superlog is disabled
```
**Solution:** Set in `.env`:
```env
SUPERLOG_ENABLED=true
```

---

### Error: "Directory not writable"
```
Checking log directory... ✗ Directory is not writable
```
**Solution:**
```bash
chmod 775 storage/logs
chown www-data:www-data storage/logs  # or appropriate user
```

---

### Error: "Test entry not found in log"
```
Test 7: Writing actual test entry... ✗
❌ Test entry was not found in log file
```
**Possible causes:**
1. Channel not configured correctly
2. Handler not attached to channel
3. File permissions issue
4. Disk full

**Solution:**
- Verify `config/logging.php` has superlog channel
- Check file permissions
- Check disk space with `df -h`
- Run: `php artisan superlog:check` (without flags) to verify config

---

### Error: "Logs going to local instead of superlog"
```
❌ ISSUE DETECTED: Logs are going to "local" channel instead of "superlog"
```
**Solution:** Let auto-fix update config or run:
```bash
# Update .env
LOG_CHANNEL=superlog

# Clear cache
php artisan cache:clear
php artisan config:cache
```

---

## Programmatic Usage

You can also call the command programmatically:

```php
use Illuminate\Support\Facades\Artisan;

$exitCode = Artisan::call('superlog:check', [
    '--test' => true,
    '--diagnostics' => true,
]);

if ($exitCode === 0) {
    // All checks passed
} else {
    // Some checks failed
}
```

---

## Integration with Monitoring

### Datadog
```php
// In your health check endpoint
$result = Artisan::call('superlog:check');
if ($result === 0) {
    \DataDog\DogStatsd::increment('superlog.health', 1);
} else {
    \DataDog\DogStatsd::increment('superlog.health', 0);
}
```

### Laravel Telescope
Monitor command execution:
```php
// In config/telescope.php
'commands' => [
    'superlog:check',
],
```

### Custom Monitoring
```bash
# Run daily health check
0 0 * * * php artisan superlog:check >> /var/log/superlog_health.log 2>&1
```

---

## Performance Characteristics

| Command | Time | CPU | Memory |
|---------|------|-----|--------|
| No options | ~1s | <5% | <50MB |
| --test | ~2s | <10% | <50MB |
| --diagnostics | ~5s | <15% | <100MB |
| --test --diagnostics | ~5s | <15% | <100MB |

---

## See Also

- [DIAGNOSTIC_AUTO_FIX.md](./DIAGNOSTIC_AUTO_FIX.md) - Auto-fix feature details
- [DIAGNOSTICS_REAL_WORLD.md](./DIAGNOSTICS_REAL_WORLD.md) - Real-world usage examples
- [FIX_LOG_CHANNEL_ISSUE.md](./FIX_LOG_CHANNEL_ISSUE.md) - Fixing channel routing issues