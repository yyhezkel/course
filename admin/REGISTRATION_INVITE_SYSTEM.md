# Admin Registration Invite System

A secure, one-time registration link system for creating new admin users without requiring direct database access or manual account creation.

## Overview

This system allows existing administrators to generate secure, one-time registration links that can be sent to new admin users. The recipient can then create their own account with a self-chosen username and password.

## Features

✅ **One-time use tokens** - Each link can only be used once
✅ **Optional expiration** - Links can expire after a set time period
✅ **Role assignment** - Pre-define the admin role (admin, super_admin, moderator)
✅ **Pre-filled data** - Optionally preset the full name
✅ **Strong password requirements** - Enforces secure password policies
✅ **Revocable links** - Links can be revoked before use
✅ **Activity logging** - All registration activity is tracked
✅ **Pure HTML frontend** - No PHP files exposed via nginx

## Installation

### Step 1: Run the Database Migration

First, create the registration_tokens table:

```bash
php admin/migrate_registration_tokens.php
```

This will create a new table to store registration tokens.

### Step 2: Update Base URL

Edit `admin/generate_invite.php` and update the base URL on line 95:

```php
$baseUrl = "https://your-domain.com"; // Update this!
```

Replace `https://your-domain.com` with your actual domain.

## Usage

### Creating a Registration Link

Use the command-line tool to generate registration links:

#### Basic Usage (admin role, never expires):
```bash
php admin/generate_invite.php create
```

#### Specify Role:
```bash
php admin/generate_invite.php create admin
php admin/generate_invite.php create super_admin
php admin/generate_invite.php create moderator
```

#### With Full Name Preset:
```bash
php admin/generate_invite.php create admin "John Doe"
```

#### With Expiration (24 hours):
```bash
php admin/generate_invite.php create admin "John Doe" 24
```

#### Complete Example:
```bash
php admin/generate_invite.php create super_admin "Sarah Smith" 48
```
This creates a link for a super_admin role, with the name "Sarah Smith" pre-filled, that expires in 48 hours.

### Managing Registration Links

#### List All Tokens:
```bash
php admin/generate_invite.php list
```

Shows all registration tokens with their status (Active, Used, Expired, Revoked).

#### Revoke a Token:
```bash
# By token ID:
php admin/generate_invite.php revoke 5

# Or by full token string:
php admin/generate_invite.php revoke abc123def456...
```

#### Cleanup Expired Tokens:
```bash
php admin/generate_invite.php cleanup
```

Automatically deactivates all expired tokens.

### Registration Process

1. **Generate a link** using the CLI tool
2. **Copy the registration URL** from the output
3. **Send the link** to the new admin user (via email, messaging, etc.)
4. **Recipient clicks the link** and lands on `/admin/register.html`
5. **They fill in the form**:
   - Username (min 3 characters)
   - Email address
   - Full name (optional, may be pre-filled)
   - Password (must meet strength requirements)
   - Confirm password
6. **Submit** - Account is created and token is marked as used
7. **Redirect** - Automatically redirected to login page

## Password Requirements

All passwords must meet these requirements:

- ✅ Minimum 12 characters
- ✅ At least one uppercase letter (A-Z)
- ✅ At least one lowercase letter (a-z)
- ✅ At least one number (0-9)
- ✅ At least one special character (!@#$%^&* etc.)
- ✅ Must not be a common password

The registration form includes a real-time password strength indicator.

## Security Features

### Token Security
- Cryptographically secure random tokens (64 characters)
- One-time use only
- Optional expiration
- Can be revoked at any time
- Stored securely in database

### Validation
- Username uniqueness check
- Email uniqueness check
- Email format validation
- Password strength validation
- Token validation (active, not used, not expired)

### Transaction Safety
- Database transactions ensure atomic operations
- Rollback on any error during registration
- Token is marked as used only after successful account creation

## API Endpoints

Two public endpoints are available (no authentication required):

### Validate Token
```javascript
POST /admin/api.php
{
    "action": "validate_invite_token",
    "token": "abc123..."
}
```

Response:
```json
{
    "success": true,
    "message": "קוד הזמנה תקף",
    "token": {
        "role": "admin",
        "preset_full_name": "John Doe",
        "expires_at": "2024-01-15 12:00:00"
    }
}
```

### Register with Token
```javascript
POST /admin/api.php
{
    "action": "register_with_token",
    "token": "abc123...",
    "username": "johndoe",
    "email": "john@example.com",
    "full_name": "John Doe",
    "password": "SecurePassword123!"
}
```

Response:
```json
{
    "success": true,
    "message": "חשבון המנהל נוצר בהצלחה!",
    "admin_id": 5,
    "username": "johndoe"
}
```

## Files

| File | Purpose |
|------|---------|
| `admin/migrate_registration_tokens.php` | Database migration script |
| `admin/generate_invite.php` | CLI tool for managing invite links |
| `admin/register.html` | Registration page (HTML) |
| `admin/api.php` | API endpoints (updated) |

## Database Schema

### registration_tokens Table

```sql
CREATE TABLE registration_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE NOT NULL,
    role TEXT DEFAULT 'admin',
    preset_full_name TEXT,
    created_by_admin_id INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    expires_at TEXT,
    used_at TEXT,
    used_by_admin_id INTEGER,
    is_active INTEGER DEFAULT 1,
    FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id),
    FOREIGN KEY (used_by_admin_id) REFERENCES admin_users(id)
)
```

## Workflow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│  1. Admin generates invite link (CLI)                       │
│     php admin/generate_invite.php create admin "John" 24    │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  2. Token stored in database                                │
│     - Unique token generated                                │
│     - Role and expiry set                                   │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Admin shares link with new user                         │
│     https://domain.com/admin/register.html?token=abc123...  │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  4. User opens link                                         │
│     - JavaScript validates token via API                    │
│     - Shows registration form if valid                      │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  5. User fills registration form                            │
│     - Username, email, password                             │
│     - Real-time password validation                         │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  6. Submit to API                                           │
│     - Validates all inputs                                  │
│     - Checks token is still valid                           │
│     - Creates admin user                                    │
│     - Marks token as used                                   │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  7. Redirect to login                                       │
│     User can now login with their credentials               │
└─────────────────────────────────────────────────────────────┘
```

## Troubleshooting

### Token Validation Fails
- Check if token has expired
- Verify token hasn't already been used
- Ensure token hasn't been revoked
- Check database connectivity

### Registration Fails
- Verify username isn't already taken
- Check email isn't already registered
- Ensure password meets all requirements
- Check database has proper permissions

### Link Doesn't Work
- Verify the base URL is correctly set in `generate_invite.php`
- Check nginx is serving `/admin/register.html`
- Ensure `admin/api.php` is accessible
- Check browser console for JavaScript errors

## Best Practices

1. **Use expiration times** for security - recommend 24-48 hours
2. **Revoke unused tokens** after they're no longer needed
3. **Run cleanup periodically** to remove expired tokens
4. **Send links securely** via encrypted channels (HTTPS, encrypted email)
5. **Monitor activity logs** for suspicious registration attempts
6. **Document who received each link** for audit purposes

## Example: Creating Multiple Admin Invites

```bash
# Create a super admin with 24-hour expiry
php admin/generate_invite.php create super_admin "Alice Admin" 24

# Create a regular admin with 48-hour expiry
php admin/generate_invite.php create admin "Bob Manager" 48

# Create a moderator with 7-day expiry
php admin/generate_invite.php create moderator "Charlie Mod" 168

# List all active invites
php admin/generate_invite.php list

# Cleanup expired tokens
php admin/generate_invite.php cleanup
```

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the API endpoint responses for error messages
3. Check server error logs
4. Verify database migrations completed successfully

---

**Note**: This system is designed for security and ease of use. All registration links are single-use and can be revoked at any time. The HTML-based frontend ensures nginx compatibility without exposing PHP files directly.
