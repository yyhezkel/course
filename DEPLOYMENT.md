# Deployment Instructions

## The Issue

You're seeing a 500 error on production at: `https://qr.bot4wa.com/kodkod/api.php`

## Possible Causes

### 1. Code Not Deployed Yet
The changes are committed to the git repository but haven't been pulled/deployed to the production server yet.

**Solution**: SSH into the production server and pull the latest changes:
```bash
cd /path/to/kodkod
git fetch origin
git checkout claude/fix-task-loading-500-error-011CUrKUWaUtVv4q4eGCCzpk
git pull
```

### 2. Missing Files/Directories
The new `api/` directory and component files may not exist on production.

**Solution**: Verify all files are present:
```bash
ls -la api/
ls -la api/components/
```

Should show:
```
api/BaseComponent.php
api/Orchestrator.php
api/components/AuthComponent.php
api/components/FormComponent.php
api/components/TaskComponent.php
api/components/UserComponent.php
```

### 3. File Permissions
PHP may not have permission to read the new files.

**Solution**: Set proper permissions:
```bash
chmod 644 api.php
chmod 644 api/*.php
chmod 644 api/components/*.php
chmod 755 api
chmod 755 api/components
```

### 4. PHP Configuration Issues
The production server might have different PHP settings.

**Solution**: Check error logs:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
# or check the custom error log
tail -f error_log.txt
```

## Quick Rollback

If you need to quickly restore the old working version:

```bash
# Option 1: Use the rollback script
php rollback.php

# Option 2: Manual rollback
cp api.php.backup api.php
```

This will restore the original monolithic api.php. The new version is saved as `api.php.new`.

## Testing on Production

### 1. Enable Debug Mode
Edit `config.php` and set:
```php
define('DEBUG_MODE', true);
```

This will show detailed error messages in the API response.

### 2. Check File Existence
Create a test file `test_deployment.php`:
```php
<?php
$files = [
    'config.php',
    'api/Orchestrator.php',
    'api/BaseComponent.php',
    'api/components/AuthComponent.php',
    'api/components/FormComponent.php',
    'api/components/TaskComponent.php',
    'api/components/UserComponent.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file MISSING\n";
    }
}
?>
```

Run: `php test_deployment.php`

### 3. Test API Endpoint
```bash
curl -X POST https://qr.bot4wa.com/kodkod/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"check_session"}' \
  -b cookies.txt
```

## Debugging Steps

### Step 1: Check Server Error Log
```bash
# Apache
tail -50 /var/log/apache2/error.log

# Nginx
tail -50 /var/log/nginx/error.log

# Custom log (created by our error handling)
tail -50 error_log.txt
```

### Step 2: Test with Simple Endpoint
Create `test_simple.php`:
```php
<?php
session_start();
header('Content-Type: application/json');

try {
    require_once 'config.php';
    echo json_encode(['success' => true, 'message' => 'Basic setup works']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
```

Test: `curl https://qr.bot4wa.com/kodkod/test_simple.php`

### Step 3: Test Component Loading
Create `test_components.php`:
```php
<?php
try {
    require_once __DIR__ . '/api/Orchestrator.php';
    echo "SUCCESS: All components loaded\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
```

Run: `php test_components.php`

## Common Issues and Solutions

### Issue: "Configuration file not found"
**Cause**: config.php doesn't exist
**Solution**: `cp config.example.php config.php`

### Issue: "Orchestrator file not found"
**Cause**: api/ directory wasn't deployed
**Solution**: Pull from git or manually upload the api/ directory

### Issue: "Class not found"
**Cause**: Component files not loading properly
**Solution**: Check file paths and permissions

### Issue: "Failed to load task" with 500 error
**Cause**: This was the original issue - missing task_progress table
**Solution**: The new code fixes this, but ensure it's deployed

## Deployment Checklist

- [ ] SSH into production server
- [ ] Navigate to kodkod directory
- [ ] Backup current state: `cp api.php api.php.pre-deploy-backup`
- [ ] Pull latest changes: `git pull origin [branch-name]`
- [ ] Verify all files present: `ls -la api/`
- [ ] Set permissions: `chmod 644 api.php api/*.php api/components/*.php`
- [ ] Test simple endpoint
- [ ] Test task loading: Visit task.html?id=1
- [ ] Check error logs if issues occur
- [ ] If problems: Run `php rollback.php`

## Need Help?

If issues persist:
1. Enable DEBUG_MODE in config.php
2. Check error_log.txt
3. Share the exact error message
4. Verify all files are present on production
5. Consider rolling back temporarily and redeploying carefully

## Architecture Comparison

### Old (Monolithic)
```
api.php (1,375 lines)
└── All logic in one file
```

### New (Component-Based)
```
api.php (80 lines)
└── api/
    ├── BaseComponent.php
    ├── Orchestrator.php
    └── components/
        ├── AuthComponent.php
        ├── FormComponent.php
        ├── TaskComponent.php
        └── UserComponent.php
```

Both versions should work identically from the client's perspective.
