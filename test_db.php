<?php
require_once 'config.php';

echo "=== Database Test Script ===\n\n";

try {
    $db = getDbConnection();

    // בדיקת טבלת users
    echo "1. Testing users table:\n";
    $result = $db->query("SELECT * FROM users");
    $users = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($users) . " users\n";
    foreach ($users as $user) {
        echo "   - ID: {$user['id']}, TZ: {$user['tz']}, Blocked: {$user['is_blocked']}, Failed Attempts: {$user['failed_attempts']}\n";
    }

    // בדיקת טבלת form_responses
    echo "\n2. Testing form_responses table:\n";
    $result = $db->query("SELECT * FROM form_responses");
    $responses = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($responses) . " form responses\n";

    // בדיקת Foreign Keys
    echo "\n3. Testing Foreign Key constraint:\n";
    $result = $db->query("PRAGMA foreign_keys");
    $fk = $result->fetch(PDO::FETCH_ASSOC);
    echo "   Foreign Keys: " . ($fk['foreign_keys'] ? 'ENABLED' : 'DISABLED') . "\n";

    // בדיקת indexes
    echo "\n4. Table schemas:\n";
    $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table'");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if ($row['sql']) {
            echo "   " . $row['sql'] . "\n\n";
        }
    }

    echo "\n✓ All database tests passed!\n";

} catch (Exception $e) {
    echo "✗ Database test failed: " . $e->getMessage() . "\n";
}
?>
