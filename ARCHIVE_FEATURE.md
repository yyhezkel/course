# User Archive Feature

## Overview
This feature allows administrators to archive users, moving them to a separate "Archived" tab while keeping their data intact. Archived users are hidden from the main user list but can be restored at any time.

## What Was Fixed

### Bug Fix: Active/Inactive Toggle
**Problem**: The activate/deactivate buttons weren't working.
- Frontend was filtering users by `is_blocked` field
- API was updating `is_active` field instead
- Result: Button clicks had no visible effect

**Solution**: Updated the API to correctly update the `is_blocked` field when toggling user status.

## New Features

### 1. Database Changes
Added `is_archived` field to the users table:
- `is_archived = 0`: Normal user (default)
- `is_archived = 1`: Archived user

### 2. API Endpoints

#### Archive User
```json
POST /admin/api.php
{
  "action": "archive_user",
  "user_id": 123
}
```

#### Unarchive User
```json
POST /admin/api.php
{
  "action": "unarchive_user",
  "user_id": 123
}
```

#### Get Users with Archive Filter
```json
POST /admin/api.php
{
  "action": "get_all_users_with_progress",
  "archived": 0  // 0 = normal users, 1 = archived users
}
```

### 3. UI Changes

#### New Tab
- Added "ארכיון" (Archive) tab to the filter buttons
- Clicking this tab loads only archived users

#### Archive Buttons
- **📦 Archive button**: Appears on normal users, moves them to archive
- **📤 Unarchive button**: Appears on archived users, restores them

## User Status Fields Explained

The system uses three status fields:

| Field | Values | Purpose |
|-------|--------|---------|
| `is_active` | 0 or 1 | Soft delete (0 = deleted, 1 = exists) |
| `is_blocked` | 0 or 1 | Login access (0 = can login, 1 = blocked) |
| `is_archived` | 0 or 1 | Archive status (0 = normal, 1 = archived) |

### Status Display Logic
- **פעיל (Active)**: `is_blocked = 0`
- **לא פעיל (Inactive)**: `is_blocked = 1`
- **ארכיון (Archived)**: `is_archived = 1`

## Installation

### Run Migration
To add the `is_archived` field to existing databases:

```bash
php admin/migrate_archive.php
```

The migration script will:
1. Add `is_archived` column with default value 0
2. Create an index for better query performance
3. Show current user statistics

### Manual Migration (if PHP SQLite driver unavailable)
```sql
ALTER TABLE users ADD COLUMN is_archived INTEGER DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_users_is_archived ON users(is_archived);
```

## Files Changed

### Backend
- `admin/api.php`: Fixed toggle bug, added archive/unarchive endpoints
- `admin/migrate_archive.php`: Migration script for is_archived field
- `config.php`: Created from config.example.php

### Frontend
- `admin/course/index.php`:
  - Added "Archived" tab
  - Added archive/unarchive buttons
  - Updated filtering logic
  - Added archive/unarchive functions

## Usage

### Archiving a User
1. Go to Student Management page
2. Find the user you want to archive
3. Click the 📦 (archive) button
4. Confirm the action
5. User is moved to the archive

### Viewing Archived Users
1. Click the "ארכיון" (Archive) tab
2. View all archived users
3. Use search to find specific archived users

### Restoring a User
1. Click the "ארכיון" (Archive) tab
2. Find the archived user
3. Click the 📤 (unarchive) button
4. Confirm the action
5. User is restored to the main list

## Behavior

- **Archived users**:
  - Hidden from main user list
  - Visible only in Archive tab
  - Can still be edited while archived
  - Can be unarchived at any time
  - Their data (tasks, forms, etc.) remains intact

- **Active/Inactive vs Archived**:
  - Active/Inactive controls login access
  - Archive is for organizational purposes
  - Users can be both inactive AND archived

## Testing Checklist

- [ ] Toggle active/inactive status works correctly
- [ ] Archive button moves user to archive
- [ ] Archived users appear in Archive tab
- [ ] Archived users don't appear in main list
- [ ] Unarchive button restores user
- [ ] Search works in archived view
- [ ] User data preserved after archive/unarchive
- [ ] Archive status persists after page reload

## Notes

- Archived users are NOT deleted, just hidden
- Archive status is independent of active/blocked status
- The migration uses `COALESCE(is_archived, 0)` for backward compatibility
- If `is_archived` column doesn't exist, it defaults to 0 (not archived)
