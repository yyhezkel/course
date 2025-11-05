<?php
/**
 * Migration: Add username field to users table
 *
 * This allows users to:
 * 1. Set a custom username for login
 * 2. Login with username/password instead of just ID number
 * 3. Keep ID number login as fallback option
 */

require_once 'config.php';

function logStep($message, $success = true) {
    echo ($success ? "✓" : "✗") . " $message\n";
}

function addUsernameAndPasswordFields($db) {
    try {
        // Check existing columns
        $result = $db->query("PRAGMA table_info(users)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'name');

        // Fields to add
        $newColumns = [
            'username' => 'TEXT UNIQUE',
            'password_hash' => 'TEXT'
        ];

        foreach ($newColumns as $colName => $colType) {
            if (!in_array($colName, $existingColumns)) {
                $db->exec("ALTER TABLE users ADD COLUMN $colName $colType");
                logStep("Added column '$colName' to users table");
            } else {
                logStep("Column '$colName' already exists in users table");
            }
        }

        // Create index for faster username lookups
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        logStep("Created index on username column");

        echo "\n✅ Successfully enhanced users table with username/password support.\n";
        echo "ℹ️  Users can now:\n";
        echo "   - Set a custom username via profile settings\n";
        echo "   - Set a password for their account\n";
        echo "   - Login with username/password\n";
        echo "   - Still login with ID number (tz) as before\n";

    } catch (Exception $e) {
        echo "❌ Error enhancing users table: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Run migration
try {
    $db = getDbConnection();

    echo "=== Adding Username & Password Fields to Users Table ===\n\n";
    addUsernameAndPasswordFields($db);
    echo "\n=== Migration completed successfully! ===\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
