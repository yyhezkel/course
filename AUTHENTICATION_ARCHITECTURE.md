# Authentication Architecture

## 🎯 Clean Separation of Users and Admins

This system enforces a **strict separation** between regular users (students) and administrators.

---

## 📋 Table Structure

### 1. `users` Table
**Purpose:** Stores ONLY regular users (students/course participants)

**Authentication Method:** ID-based (tz or personal_number)

**Key Fields:**
- `tz` - ID number (9 digits for tz, 7 digits for personal_number)
- `id_type` - Either 'tz' or 'personal_number'
- `full_name` - Student's full name
- `password_hash` - Optional password (can also login with ID only)
- `is_blocked` - Block status
- `is_active` - Active status

**Login Endpoint:** `/api.php` (action: login)
- Users log in with their ID number (tz)
- Redirects to `dashboard.html` after successful login

---

### 2. `admin_users` Table
**Purpose:** Stores ONLY administrators and instructors

**Authentication Method:** Username/Password

**Key Fields:**
- `username` - Unique username
- `password_hash` - Hashed password (required)
- `email` - Email address
- `full_name` - Admin's full name
- `role` - Admin role (admin, super_admin, etc.)
- `is_active` - Active status

**Login Endpoint:** `/admin/auth.php` (action: login)
- Admins log in with username and password
- Session-based authentication with 2-hour timeout
- Redirects to `admin/dashboard.html` after successful login

---

## 🔧 Creating Users

### Creating Regular Users (Students)

**Method 1: Via Admin Interface**
- Use `/admin/api.php` endpoint `create_user`
- Provide: tz, id_type, full_name, optional password

**Method 2: Bulk Import**
- Use `/admin/api.php` endpoint `bulk_import_users`
- CSV format: `tz, full_name, password`

**Method 3: Insert List Script**
- Use `/insert_users_list.php` command-line tool

**Important:** These endpoints will ONLY create regular users, never admins.

---

### Creating Administrators

**ONLY Method: Command-Line Tool**
```bash
php admin/manage_admins.php create <username> <password> <email> [fullname] [role]
```

**Examples:**
```bash
# Create basic admin
php admin/manage_admins.php create john pass123 john@example.com "John Doe"

# Create super admin
php admin/manage_admins.php create superadmin secret admin@example.com "Super Admin" super_admin

# List all admins
php admin/manage_admins.php list

# Delete admin
php admin/manage_admins.php delete <admin_id>
```

---

## 🚫 What Changed (Migration from Old Architecture)

### Before (Problematic)
- `users` table had a `role` field ('user' or 'admin')
- Admins were stored in BOTH `users` and `admin_users` tables
- Confusing and inconsistent

### After (Clean)
- `users` table has NO `role` field
- `users` table: ONLY students
- `admin_users` table: ONLY administrators
- Clear separation of concerns

---

## 🔄 Migration Process

To migrate from the old architecture to the new one:

```bash
php admin/migrate_remove_role_from_users.php
```

This will:
1. ✅ Check for existing role column
2. ✅ Warn about any admin users in users table
3. ✅ Create new users table without role field
4. ✅ Migrate only regular users (excluding role='admin')
5. ✅ Drop old table and rename new one
6. ✅ Recreate indexes

**⚠️ Warning:** Any users with role='admin' in the users table will be DELETED.
Make sure to recreate them in admin_users table first if needed.

---

## 📊 Summary Diagram

```
┌─────────────────────────────────────────────────────────┐
│                   AUTHENTICATION FLOW                     │
└─────────────────────────────────────────────────────────┘

STUDENTS (Regular Users):
┌──────────────┐     Login with TZ     ┌──────────────┐
│   User       │  ───────────────────>  │    users     │
│  (Student)   │     api.php            │    table     │
└──────────────┘                        └──────────────┘
       │
       └──> Redirect to dashboard.html


ADMINISTRATORS:
┌──────────────┐  Login with username   ┌──────────────┐
│   Admin      │  ───────────────────>  │ admin_users  │
│ (Instructor) │   admin/auth.php       │    table     │
└──────────────┘                        └──────────────┘
       │
       └──> Redirect to admin/dashboard.html
```

---

## ✅ Best Practices

1. **Never mix user types**
   - Students go in `users` table
   - Admins go in `admin_users` table

2. **Use the right tool for creation**
   - Students: Admin interface or bulk import
   - Admins: Command-line tool only

3. **Separate authentication flows**
   - Students use `/api.php`
   - Admins use `/admin/auth.php`

4. **Session management**
   - Students: Simple session with user_id
   - Admins: Enhanced session with timeout and activity logging

---

## 🔒 Security Notes

- **Students:** Can optionally have passwords, or login with ID only
- **Admins:** MUST have strong passwords (bcrypt hashed)
- **Admin sessions:** Timeout after 2 hours of inactivity
- **Activity logging:** All admin actions are logged in `activity_log` table
- **Separation:** No cross-contamination between user types

---

## 📝 API Endpoints Reference

### Student Authentication
- `POST /api.php?action=login` - Login with TZ
- `POST /api.php?action=logout` - Logout

### Admin Authentication
- `POST /admin/auth.php?action=login` - Login with username/password
- `POST /admin/auth.php?action=logout` - Logout
- `POST /admin/auth.php?action=check` - Check session status
- `POST /admin/auth.php?action=change_password` - Change password

### User Management (Admin Only)
- `POST /admin/api.php?action=create_user` - Create student
- `POST /admin/api.php?action=bulk_import_users` - Bulk import students
- `POST /admin/api.php?action=get_all_users` - List all students

---

*Last Updated: 2025-11-05*
*Architecture Version: 2.0 (Clean Separation)*
