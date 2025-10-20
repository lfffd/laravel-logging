# Superlog Diagnostic - Quick Start Guide

## Problem: Logs Not Writing to laravel-YYYY-MM-DD.log

### ⚡ 30-Second Fix

```bash
# Step 1: Run diagnostic
php artisan superlog:check --diagnostics

# Step 2: For each issue, press Enter to auto-fix
# (Just keep pressing Enter when prompted)

# Step 3: Clear cache
php artisan cache:clear

# Step 4: Verify
php artisan superlog:check --diagnostics
```

✅ **Done!** Logs now write to the correct file.

---

## Common Issues & Fixes

### Issue 1: "Test entry was not found in log file"

**Cause**: Configuration parameters are not aligned

**Fix**:
```bash
php artisan superlog:check --diagnostics
# Press Enter for each prompt to auto-fix all issues
php artisan cache:clear
```

### Issue 2: Logs going to "local" channel, not "superlog"

**Signs**:
```
[2025-10-20 11:22:22] local.INFO: ← WRONG
[2025-10-20 11:22:22] superlog.INFO: ← RIGHT
```

**Fix**:
```bash
php artisan superlog:check --diagnostics
# Select "yes" for the default channel issue
php artisan cache:clear
```

### Issue 3: "Fix this issue?" prompt doesn't respond

**Solution**: Simply press Enter (default is "yes")

### Issue 4: Auto-fix shows "✗ (could not update)"

**Solution**: Use manual fix shown in the prompt

---

## What Parameters Get Checked

| Parameter | File | Purpose |
|-----------|------|---------|
| `LOG_CHANNEL` | `.env` | Environment variable for channel selection |
| `channel` | `config/superlog.php` | Superlog's internal channel config |
| `default` | `config/logging.php` | Laravel's default logging channel |
| `stack.channels` | `config/logging.php` | Stack channel routing order |
| `superlog` handler | `config/logging.php` | Superlog driver registration |

---

## Diagnostic Command Options

```bash
# Check configuration only
php artisan superlog:check

# Check + write test entry
php artisan superlog:check --test

# Full diagnostics (check + test + verify logs written)
php artisan superlog:check --diagnostics

# Everything
php artisan superlog:check --test --diagnostics
```

---

## Expected Output When Everything Works

```
✅ Test entry successfully written and verified!

✅ Format verification:
  Channel: ✓ superlog
  Timestamp: ✓ ISO8601
  Trace ID + Seq: ✓ [id:seq] found
  Data preserved: ✓ Yes

✅ APPLICATION INTEGRATION SUCCESSFUL
Your Superlog is properly configured and working!
```

---

## Log Entry Example

When properly configured, you'll see entries like:

```
[2025-10-20T11:22:33.123456Z] superlog.INFO: [trace-id-xyz:0000000001] [DIAGNOSTICS] Test message
```

**Parts**:
- `superlog.INFO` → correct channel (not `local.INFO`)
- `[trace-id-xyz:0000000001]` → trace ID with request sequence
- Data fields are preserved in the JSON

---

## Troubleshooting Checklist

- [ ] Run: `php artisan superlog:check --diagnostics`
- [ ] Say "yes" to all prompts (or press Enter)
- [ ] Run: `php artisan cache:clear`
- [ ] Verify: `php artisan superlog:check --diagnostics` shows ✅

If still failing:
- [ ] Check file permissions: `ls -la config/*.php .env`
- [ ] Check manual fix instructions from diagnostic output
- [ ] Review detailed guide: [ENHANCED_DIAGNOSTIC_GUIDE.md](ENHANCED_DIAGNOSTIC_GUIDE.md)

---

## Manual Fix (If Needed)

If auto-fix fails, manually edit these files:

**`.env`**:
```env
LOG_CHANNEL=superlog
```

**`config/superlog.php`** (around line 26):
```php
'channel' => env('LOG_CHANNEL', 'superlog'),
```

**`config/logging.php`** (around line 16):
```php
'default' => env('LOG_CHANNEL', 'superlog'),
```

Then run:
```bash
php artisan cache:clear
php artisan superlog:check --diagnostics
```

---

## Time to Resolution

- **Auto-fix enabled**: ~30 seconds
- **Manual fix**: ~2 minutes
- **Including verification**: ~5 minutes

**Previous manual debugging**: 30+ minutes

---

## Next Steps After Fixing

1. ✅ Verify: `php artisan superlog:check --diagnostics`
2. 🧪 Test: `php artisan superlog:check --test`
3. 📝 Check logs: `tail -f storage/logs/laravel-*.log`
4. 🚀 Deploy: Ready for production

---

## Questions?

- See [ENHANCED_DIAGNOSTIC_GUIDE.md](ENHANCED_DIAGNOSTIC_GUIDE.md) for detailed documentation
- See [COMPREHENSIVE_DIAGNOSTICS.md](COMPREHENSIVE_DIAGNOSTICS.md) for architecture details
- Review [README.md](../../README.md) for setup