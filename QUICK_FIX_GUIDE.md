# Quick Fix Guide - Task Loading 500 Error

## Current Status

You're getting a 500 error when accessing: `https://qr.bot4wa.com/kodkod/task.html?id=2`

## The Problem

The new code that fixes the bug exists in git but **hasn't been deployed to the production server yet**.

## Immediate Steps to Fix

### Option 1: Deploy New Code (Recommended)

SSH into your production server and run:

```bash
# Navigate to the kodkod directory
cd /path/to/kodkod

# Pull the latest changes
git fetch origin
git checkout claude/fix-task-loading-500-error-011CUrKUWaUtVv4q4eGCCzpk
git pull

# Verify files are present
ls -la api/
ls -la api/components/

# Check for any issues
php diagnose.php

# Test it
curl -X POST https://qr.bot4wa.com/kodkod/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"check_session"}'
```

### Option 2: Rollback to Old Code (Temporary)

If you just need things working immediately:

```bash
cd /path/to/kodkod
cp api.php.backup api.php
```

**Note**: This restores the old code but the original 500 error might still occur.

## Diagnostic Steps

### 1. Check if Files Exist

Visit: `https://qr.bot4wa.com/kodkod/diagnose.php`

This will show you:
- ✓ Which files are present
- ✓ PHP version and extensions
- ✓ Database connection status
- ✓ Whether task_progress table exists
- Any recent errors

### 2. Enable Debug Mode

If you need to see detailed errors:

1. Edit `config.php`:
   ```php
   define('DEBUG_MODE', true);
   ```

2. Try loading a task again

3. Check the error response for details

### 3. Check Error Logs

Look at recent errors:
```bash
tail -50 error_log.txt
# or
tail -50 /var/log/apache2/error.log
```

## What Was Fixed

### The Bug
The `get_task_detail` action tried to LEFT JOIN with `task_progress` table, but this table wasn't being created during initialization.

### The Fix
- Added `task_progress` table creation in `BaseComponent::initializeCourseTables()`
- Refactored entire API into modular components
- Added better error handling and logging

## Files You Need on Production

Make sure these exist on your server:

```
kodkod/
├── api.php                          # New main API (80 lines)
├── api.php.backup                   # Backup of old API (1,375 lines)
├── config.php                       # Configuration
├── api/
│   ├── BaseComponent.php            # Base class
│   ├── Orchestrator.php             # Request router
│   └── components/
│       ├── AuthComponent.php        # Auth operations
│       ├── FormComponent.php        # Form operations
│       ├── TaskComponent.php        # Task operations (contains the fix!)
│       └── UserComponent.php        # User operations
├── diagnose.php                     # Diagnostic tool
├── rollback.php                     # Rollback script
└── DEPLOYMENT.md                    # Full deployment guide
```

## Quick Rollback

If the new code causes issues:

```bash
php rollback.php
```

This restores the original api.php.

## Testing After Deployment

1. **Test Authentication**:
   ```bash
   curl -X POST https://qr.bot4wa.com/kodkod/api.php \
     -H "Content-Type: application/json" \
     -d '{"action":"login","tz":"123456789"}'
   ```

2. **Test Task Loading** (the one that was failing):
   - Visit: `https://qr.bot4wa.com/kodkod/task.html?id=1`
   - Should now work without 500 error

3. **Test Dashboard**:
   - Visit: `https://qr.bot4wa.com/kodkod/dashboard.html`
   - Should load user tasks

## Common Issues

### "Configuration file not found"
**Solution**: Ensure `config.php` exists. If not: `cp config.example.php config.php`

### "Orchestrator file not found"
**Solution**: The `api/` directory wasn't deployed. Pull from git.

### "could not find driver"
**Solution**: Install PHP PDO SQLite extension: `sudo apt-get install php-sqlite3`

### Still getting 500 error
**Solution**:
1. Run `php diagnose.php` and share output
2. Check `error_log.txt` for details
3. Enable DEBUG_MODE in config.php
4. Share the exact error message

## Need Help?

1. Run the diagnostic: `php diagnose.php`
2. Check error logs: `tail -50 error_log.txt`
3. Enable debug mode in config.php
4. Share the output/errors

## What Changed?

### Before (Monolithic)
```
api.php
└── 1,375 lines of code
    └── All logic in one massive file
```

### After (Component-Based)
```
api.php
├── 80 lines (router only)
└── api/
    ├── BaseComponent.php (common functionality)
    ├── Orchestrator.php (request routing)
    └── components/
        ├── AuthComponent.php
        ├── FormComponent.php
        ├── TaskComponent.php ← Contains the bug fix!
        └── UserComponent.php
```

### Key Fix
The `TaskComponent` now properly creates the `task_progress` table, which was missing and causing the 500 error.

## Summary

1. **Run**: `php diagnose.php` to identify issues
2. **Deploy**: Pull latest code from git
3. **Test**: Visit task.html?id=1 - should work now
4. **Rollback if needed**: `php rollback.php`

The code is ready and tested - it just needs to be deployed to your production server!
