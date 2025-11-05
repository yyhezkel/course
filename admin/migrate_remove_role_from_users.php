#!/usr/bin/env php
<?php
/**
 * Migration: Remove 'role' field from users table
 *
 * This migration enforces clean separation:
 * - users table: ONLY regular users (students)
 * - admin_users table: ONLY administrators
 *
 * This script will:
 * 1. Create new users table without 'role' field
 * 2. Migrate only regular users (excluding any with role='admin')
 * 3. Drop old table and rename new one
 */

require_once __DIR__ . '/../config.php';

echo "===========================================\n";
echo "  MIGRATION: Remove Role from Users Table\n";
echo "===========================================\n\n";

try {
    $db = getDbConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "[1/6] Checking current users table structure...\n";

    // Check if role column exists
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasRoleColumn = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'role') {
            $hasRoleColumn = true;
            break;
        }
    }

    if (!$hasRoleColumn) {
        echo "✓ Role column does not exist. Migration not needed.\n";
        exit(0);
    }

    echo "✓ Found role column. Proceeding with migration...\n\n";

    // Check for admin users in users table
    echo "[2/6] Checking for admin users in users table...\n";
    $adminUsers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    if ($adminUsers > 0) {
        echo "⚠ WARNING: Found {$adminUsers} users with role='admin' in users table.\n";
        echo "   These will NOT be migrated (admins should be in admin_users table).\n";
        echo "   If these are actual admins, please create them in admin_users table first.\n\n";

        // Show the admin users
        $admins = $db->query("SELECT id, tz, full_name FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_ASSOC);
        echo "   Admin users found:\n";
        foreach ($admins as $admin) {
            echo "   - ID: {$admin['id']}, TZ: {$admin['tz']}, Name: " . ($admin['full_name'] ?: 'N/A') . "\n";
        }
        echo "\n";

        echo "Do you want to continue? These admin users will be DELETED. (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) !== 'yes') {
            echo "\nMigration cancelled by user.\n";
            exit(1);
        }
    } else {
        echo "✓ No admin users found in users table.\n";
    }

    echo "\n[3/6] Creating new users table without role field...\n";

    $db->exec("
        CREATE TABLE users_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tz TEXT UNIQUE NOT NULL,
            id_type TEXT DEFAULT 'tz',
            full_name TEXT,
            email TEXT,
            phone TEXT,
            password_hash TEXT,
            is_blocked INTEGER DEFAULT 0,
            failed_attempts INTEGER DEFAULT 0,
            ip_address TEXT,
            last_login TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    echo "✓ Created users_new table\n";

    echo "\n[4/6] Migrating regular users (role='user' or NULL)...\n";

    $db->exec("
        INSERT INTO users_new (
            id, tz, id_type, full_name, email, phone, password_hash,
            is_blocked, failed_attempts, ip_address, last_login,
            is_active, created_at, updated_at
        )
        SELECT
            id, tz, id_type, full_name, email, phone, password_hash,
            is_blocked, failed_attempts, ip_address, last_login,
            is_active, created_at, updated_at
        FROM users
        WHERE role != 'admin' OR role IS NULL
    ");

    $migratedCount = $db->query("SELECT COUNT(*) FROM users_new")->fetchColumn();
    echo "✓ Migrated {$migratedCount} regular users\n";

    echo "\n[5/6] Replacing old table with new one...\n";

    $db->exec("DROP TABLE users");
    $db->exec("ALTER TABLE users_new RENAME TO users");

    echo "✓ Table replaced\n";

    echo "\n[6/6] Recreating indexes...\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_tz ON users(tz)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_is_blocked ON users(is_blocked)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_id_type ON users(id_type)");

    echo "✓ Indexes created\n";

    echo "\n";
    echo "===========================================\n";
    echo "  ✓ MIGRATION COMPLETED SUCCESSFULLY\n";
    echo "===========================================\n\n";

    echo "Summary:\n";
    echo "- Regular users migrated: {$migratedCount}\n";
    echo "- Admin users removed: {$adminUsers}\n";
    echo "- users table: Now contains ONLY regular users (students)\n";
    echo "- admin_users table: Contains ONLY administrators\n\n";

    echo "Next steps:\n";
    echo "1. Verify authentication still works for regular users\n";
    echo "2. Verify admin authentication via admin_users table\n";
    echo "3. Create any needed admins using: php admin/manage_admins.php create <username> <password> <email>\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Migration failed. Database may be in inconsistent state.\n";
    exit(1);
}
