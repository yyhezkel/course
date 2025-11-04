# Production Deployment Checklist

## ✅ Pre-Deployment Checklist

### Security (Critical)

- [x] **Secure API Key Generated**
  - New random API key: `bviw/hWbmRDpYrv0u7Lu6h6+uS9p4Vgy2JNygkHo9To90KI/CmkPsE6WBVTfr45c`
  - Updated in both `config.php` and `app.js`
  - ⚠️ **WARNING**: API key is visible in `app.js` (client-side). See security notes below.

- [x] **HTTPS Enabled**
  - API URL changed from `http://` to `https://`
  - Cloudflare SSL configured

- [x] **CORS Restricted**
  - Changed from `*` to `https://qr.bot4wa.com`

- [ ] **Nginx Security Configuration** (MANUAL ACTION REQUIRED)
  - See `SECURITY_SETUP.md` for nginx configuration
  - Must protect: `config.php`, `*.db`, `test_*.php`, `manage_users.php`, `db_init.php`

- [ ] **Remove Test Files** (RECOMMENDED)
  ```bash
  cd /www/wwwroot/qr.bot4wa.com/kodkod
  rm test_*.php
  # Or move to a secure location outside web root
  ```

- [x] **File Permissions Set**
  - All files: `644` (rw-r--r--)
  - Database: `664` (rw-rw-r--)
  - Owner: `www:www`

- [x] **Critical Security Bug Fixed**
  - Fixed auto-user-creation vulnerability in `api.php`
  - Now only pre-existing users can log in

### Database

- [x] **Database Initialized**
  - Tables created: `users`, `form_responses`
  - Foreign key constraints enabled
  - Test user exists (TZ: 123456789)

- [ ] **Production Users Added** (ACTION REQUIRED)
  ```bash
  php manage_users.php add <tz>
  ```

- [ ] **Backup Strategy** (RECOMMENDED)
  - Set up automated backups of `form_data.db`
  - Recommended: Daily backups with 30-day retention
  - Example cron:
    ```bash
    0 2 * * * cp /www/wwwroot/qr.bot4wa.com/kodkod/form_data.db /backups/kodkod/form_data_$(date +\%Y\%m\%d).db
    ```

### Testing

- [x] **Login Functionality Tested**
  - Valid user login: ✓
  - Invalid ID format: ✓
  - Non-existent user: ✓
  - Blocked user: ✓

- [x] **Form Submission Tested**
  - API key authentication: ✓
  - Data persistence: ✓
  - UPSERT functionality: ✓
  - Foreign key constraints: ✓

- [x] **Security Features Tested**
  - SQL injection prevention: ✓
  - Input validation: ✓
  - Transaction rollback: ✓
  - Special characters: ✓

### Configuration

- [x] **API Endpoint Configured**
  - `app.js` points to `https://qr.bot4wa.com/kodkod/api.php`

- [x] **Error Handling Implemented**
  - Database connection errors: ✓
  - Validation errors: ✓
  - Foreign key violations: ✓

---

## 🚨 Known Issues & Limitations

### 1. API Key Exposed in Frontend (HIGH PRIORITY)

**Issue**: The API key is hardcoded in `app.js` which is sent to every client's browser.

**Risk**: Anyone can view the source code and steal the API key.

**Impact**: Attackers could:
- Submit fake form data
- Spam the API
- Potentially DoS the application

**Recommended Solutions** (Choose one):

**Option A: Session-Based Authentication (Recommended)**
```php
// After successful login in api.php, create a session
session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['authenticated'] = true;

// For submit action, check session instead of API key
if (!isset($_SESSION['authenticated'])) {
    http_response_code(401);
    exit;
}
```

**Option B: Remove API Key from Submit Endpoint**
- Remove API key requirement from `submit` action
- Rely on user_id validation only
- Add rate limiting

**Option C: Server-Side Rendering**
- Move form logic to PHP
- Keep API key server-side only

### 2. No Rate Limiting

**Issue**: No rate limiting on API endpoints

**Risk**: API can be abused with excessive requests

**Recommended Solution**: Add rate limiting in nginx:
```nginx
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/m;

location /kodkod/api.php {
    limit_req zone=api_limit burst=5 nodelay;
}
```

### 3. No Logging

**Issue**: No access or error logging implemented

**Recommended**: Add logging for:
- Failed login attempts
- API key failures
- Form submissions
- Errors

### 4. Test User in Production

**Issue**: Test user (123456789) exists in database

**Action**: Remove or change after testing:
```bash
php manage_users.php remove 123456789
```

---

## 📋 Deployment Steps

### Step 1: Configure Nginx

1. Edit nginx site configuration:
   ```bash
   nano /www/server/panel/vhost/nginx/qr.bot4wa.com.conf
   ```

2. Add security rules from `SECURITY_SETUP.md`

3. Test and reload:
   ```bash
   nginx -t
   systemctl reload nginx
   ```

### Step 2: Add Production Users

```bash
cd /www/wwwroot/qr.bot4wa.com/kodkod
php manage_users.php add <user_tz_1>
php manage_users.php add <user_tz_2>
# ... add all authorized users
```

### Step 3: Remove Development Files

```bash
cd /www/wwwroot/qr.bot4wa.com/kodkod
rm test_*.php
# Optional: Keep manage_users.php for administration
```

### Step 4: Set Up Backups

```bash
mkdir -p /backups/kodkod
crontab -e
# Add: 0 2 * * * cp /www/wwwroot/qr.bot4wa.com/kodkod/form_data.db /backups/kodkod/form_data_$(date +\%Y\%m\%d).db
```

### Step 5: Verify Protection

Test that sensitive files are blocked:
```bash
curl -I https://qr.bot4wa.com/kodkod/config.php
# Should return 404 or 403

curl -I https://qr.bot4wa.com/kodkod/form_data.db
# Should return 404 or 403
```

### Step 6: Test Application

1. Open https://qr.bot4wa.com/kodkod/ in browser
2. Test login with valid user
3. Test login with invalid user (should fail)
4. Test form submission
5. Verify data saved in database

### Step 7: Monitor

After deployment:
- Check nginx error logs: `/www/wwwlogs/qr.bot4wa.com.error.log`
- Monitor failed login attempts
- Check for blocked users

---

## 🔧 User Management

### Add User
```bash
php manage_users.php add <9-digit-tz>
```

### List All Users
```bash
php manage_users.php list
```

### Unblock User
```bash
php manage_users.php unblock <tz>
```

### Remove User
```bash
php manage_users.php remove <tz>
```

### Reset Failed Attempts
```bash
php manage_users.php reset <tz>
```

---

## 📊 Monitoring & Maintenance

### Daily Tasks
- [ ] Check for blocked users
- [ ] Review nginx error logs
- [ ] Verify backups completed

### Weekly Tasks
- [ ] Review form submissions
- [ ] Check disk space (database growth)
- [ ] Test backup restoration

### Monthly Tasks
- [ ] Rotate old backups (keep 30 days)
- [ ] Review user accounts
- [ ] Check for security updates (PHP, nginx)

---

## 🆘 Troubleshooting

### Users Can't Login
1. Check user exists: `php manage_users.php list`
2. Check if blocked: Look for `Blocked: YES`
3. Unblock if needed: `php manage_users.php unblock <tz>`

### Form Not Saving
1. Check database permissions: `ls -la form_data.db`
2. Check nginx logs: `tail -f /www/wwwlogs/qr.bot4wa.com.error.log`
3. Test API manually: `curl -X POST https://qr.bot4wa.com/kodkod/api.php`

### Database Locked Error
1. Check for long-running processes: `lsof | grep form_data.db`
2. Restart PHP-FPM: `systemctl restart php-fpm`

### API Key Error
1. Verify API keys match in `config.php` and `app.js`
2. Clear browser cache
3. Check for hidden characters in API key

---

## ✅ Final Verification

Before going live, verify:

- [ ] Nginx security configuration applied
- [ ] Test files removed or protected
- [ ] All production users added
- [ ] Test user removed
- [ ] Backups configured
- [ ] Sensitive files return 404
- [ ] HTTPS working correctly
- [ ] Login works for valid users
- [ ] Login blocks invalid users
- [ ] Form submission works
- [ ] Data persists in database
- [ ] API key authentication working
- [ ] CORS headers present

---

## 📞 Support

For issues or questions:
- Check `SECURITY_SETUP.md` for security configuration
- Review test results in test files (before removal)
- Check nginx and PHP error logs

---

**Last Updated**: 2025-10-29
**Production Ready**: ⚠️ After completing manual actions above
