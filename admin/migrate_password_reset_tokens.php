<?php
/**
 * Password Reset Tokens Migration
 * Creates password_reset_tokens table for user password reset functionality
 */

require_once __DIR__ . '/../config.php';

echo "=== Password Reset Tokens Migration Started ===\n\n";

$db = getDbConnection();
$db->exec('PRAGMA foreign_keys = ON;');

try {
    // Create password_reset_tokens table
    echo "Creating password_reset_tokens table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT UNIQUE NOT NULL,
            expires_at TEXT NOT NULL,
            used_at TEXT,
            created_by_admin_id INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            ip_address TEXT,
            user_agent TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
        )
    ");

    echo "✓ password_reset_tokens table created successfully\n";

    // Create indexes for performance
    echo "Creating indexes...\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_user_id ON password_reset_tokens(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_token ON password_reset_tokens(token)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_expires_at ON password_reset_tokens(expires_at)");

    echo "✓ Indexes created successfully\n";

    echo "\n=== Migration Completed Successfully ===\n";

} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
