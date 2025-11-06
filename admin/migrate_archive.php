<?php
/**
 * Archive Feature Migration
 * Adds is_archived field to users table
 */

require_once __DIR__ . '/../config.php';

echo "=== Archive Feature Migration Started ===\n\n";

$db = getDbConnection();
$db->exec('PRAGMA foreign_keys = ON;');

try {
    // Check if is_archived column already exists
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'name');

    if (!in_array('is_archived', $existingColumns)) {
        // Add is_archived column
        $db->exec("ALTER TABLE users ADD COLUMN is_archived INTEGER DEFAULT 0");
        echo "✓ Added is_archived column to users table\n";

        // Create index for better query performance
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_is_archived ON users(is_archived)");
        echo "✓ Created index on is_archived column\n";

        echo "\n=== Migration Completed Successfully ===\n";
        echo "Archive feature is now available!\n";
    } else {
        echo "✓ is_archived column already exists\n";
        echo "\n=== Migration Already Applied ===\n";
    }

    // Show current user counts
    echo "\n--- User Statistics ---\n";
    $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $activeUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND is_archived = 0")->fetchColumn();
    $archivedUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_archived = 1")->fetchColumn();

    echo "  • Total active users: $totalUsers\n";
    echo "  • Active (not archived): $activeUsers\n";
    echo "  • Archived: $archivedUsers\n";

} catch (Exception $e) {
    echo "\n✗ MIGRATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
?>
