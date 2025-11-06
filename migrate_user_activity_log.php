<?php
/**
 * User Activity Log Migration
 * Creates user_activity_log table for tracking all user actions in the system
 */

require_once __DIR__ . '/config.php';

echo "=== User Activity Log Migration Started ===\n\n";

$db = getDbConnection();
$db->exec('PRAGMA foreign_keys = ON;');

try {
    // Create user_activity_log table
    echo "Creating user_activity_log table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            entity_type TEXT,
            entity_id INTEGER,
            details TEXT,
            ip_address TEXT,
            user_agent TEXT,
            session_id TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    echo "✓ user_activity_log table created successfully\n";

    // Create indexes for performance
    echo "Creating indexes...\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_user_id ON user_activity_log(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_action ON user_activity_log(action)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_created_at ON user_activity_log(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_entity ON user_activity_log(entity_type, entity_id)");

    echo "✓ Indexes created successfully\n";

    echo "\n=== Migration Completed Successfully ===\n";

} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
