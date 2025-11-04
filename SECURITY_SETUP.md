# Security Setup Instructions

## Important: Server is Running Nginx

This server uses **nginx**, not Apache, so `.htaccess` files will **NOT work**.

## Required Nginx Configuration

Add the following to your nginx site configuration file (usually in `/www/server/panel/vhost/nginx/` or `/etc/nginx/sites-available/`):

```nginx
# Add inside the server {} block for qr.bot4wa.com

location /kodkod {
    # Block access to database files
    location ~ \.db$ {
        deny all;
        return 404;
    }

    # Block access to configuration files
    location ~ /config\.php$ {
        deny all;
        return 404;
    }

    # Block access to test scripts
    location ~ /test_.*\.php$ {
        deny all;
        return 404;
    }

    # Block access to management scripts
    location ~ /(manage_users|db_init)\.php$ {
        deny all;
        return 404;
    }

    # Block access to hidden files
    location ~ /\. {
        deny all;
        return 404;
    }

    # Add security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

## After Adding Configuration

1. Test nginx configuration:
   ```bash
   nginx -t
   ```

2. Reload nginx:
   ```bash
   systemctl reload nginx
   # or
   /etc/init.d/nginx reload
   ```

3. Verify protection by trying to access:
   - https://qr.bot4wa.com/kodkod/config.php (should return 404)
   - https://qr.bot4wa.com/kodkod/form_data.db (should return 404)
   - https://qr.bot4wa.com/kodkod/test_db.php (should return 404)

## File Permissions

Current permissions are set correctly:
- PHP files: 644 (rw-r--r--)
- Database: 664 (rw-rw-r--)
- Owner: www:www

## Additional Security Recommendations

1. **Remove test files** from production:
   ```bash
   rm /www/wwwroot/qr.bot4wa.com/kodkod/test_*.php
   ```

2. **Move management scripts** outside web root or protect them with authentication

3. **Enable HTTPS** (appears to already be configured via Cloudflare)

4. **API Key Security Warning**:
   - The API key is currently exposed in `app.js` (client-side code)
   - Anyone can view the source and see the API key
   - Consider implementing a proper backend authentication system
   - For better security, the frontend should never have direct access to the API key

5. **Database Backups**: Set up regular backups of `form_data.db`

6. **Monitor Failed Login Attempts**: Check for blocked users regularly

## User Management

Use the command-line tool to manage users:

```bash
# Add new user
php /www/wwwroot/qr.bot4wa.com/kodkod/manage_users.php add <tz>

# List all users
php /www/wwwroot/qr.bot4wa.com/kodkod/manage_users.php list

# Unblock user
php /www/wwwroot/qr.bot4wa.com/kodkod/manage_users.php unblock <tz>

# Remove user
php /www/wwwroot/qr.bot4wa.com/kodkod/manage_users.php remove <tz>
```

## Current Security Status

✓ Secure API key generated
✓ HTTPS enabled (via Cloudflare)
✓ CORS restricted to domain
✓ File permissions set correctly
✓ API key authentication implemented
✓ SQL injection protection (prepared statements)
✓ Failed login attempt tracking
✓ Account blocking after max attempts

⚠ PENDING: Nginx configuration needs to be added manually
⚠ WARNING: API key visible in client-side JavaScript
⚠ TODO: Remove test files from production
