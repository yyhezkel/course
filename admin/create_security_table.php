<?php
/**
 * Create failed_login_attempts table
 * Run this via web browser to create the security table
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain');

echo "=== Creating Security Table ===\n\n";

try {
    $db = getDbConnection();

    // Check if table already exists
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='failed_login_attempts'")->fetchAll();

    if (count($tables) > 0) {
        echo "✓ Table 'failed_login_attempts' already exists.\n";
    } else {
        echo "Creating table 'failed_login_attempts'...\n";

        $db->exec("
            CREATE TABLE failed_login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                attempt_time TEXT DEFAULT CURRENT_TIMESTAMP,
                user_agent TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        echo "✓ Table created successfully.\n\n";

        echo "Creating indexes...\n";
        $db->exec("CREATE INDEX idx_failed_login_username ON failed_login_attempts(username)");
        echo "✓ Index on username created.\n";

        $db->exec("CREATE INDEX idx_failed_login_ip ON failed_login_attempts(ip_address)");
        echo "✓ Index on ip_address created.\n";

        $db->exec("CREATE INDEX idx_failed_login_time ON failed_login_attempts(attempt_time)");
        echo "✓ Index on attempt_time created.\n";
    }

    echo "\n=== Success ===\n";
    echo "Security table is now ready.\n";
    echo "Brute force protection is now active.\n\n";

    echo "You can now delete this file for security:\n";
    echo "rm " . __FILE__ . "\n";

} catch (Exception $e) {
    echo "\n✗ ERROR\n";
    echo "Failed to create table: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
