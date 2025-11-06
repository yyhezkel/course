<?php
/**
 * Database Migration: Add registration_tokens table
 * Enables one-time admin registration links
 */

require_once __DIR__ . '/../config.php';

echo "=== Adding Registration Tokens Table ===\n\n";

$db = getDbConnection();
$db->exec('PRAGMA foreign_keys = ON;');

try {
    // Create registration_tokens table
    $db->exec("
        CREATE TABLE IF NOT EXISTS registration_tokens (
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
    ");
    echo "✓ Created registration_tokens table\n";

    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reg_token ON registration_tokens(token)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reg_token_active ON registration_tokens(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reg_token_expires ON registration_tokens(expires_at)");
    echo "✓ Created indexes on registration_tokens table\n";

    echo "\n=== Migration Completed Successfully ===\n";
    echo "\nNext steps:\n";
    echo "  1. Use admin/generate_invite.php to create registration links\n";
    echo "  2. Share the link with new admins\n";
    echo "  3. They can register at /admin/register.html\n\n";

} catch (Exception $e) {
    echo "\n✗ MIGRATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
