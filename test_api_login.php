<?php
/**
 * API Login Test Script
 * Tests the login endpoint with various scenarios
 */

require_once 'config.php';

echo "=== Login API Test ===\n\n";

function simulateLoginRequest($tz, $testName) {
    global $db;
    if (!isset($db)) {
        $db = getDbConnection();
    }

    echo "Test: $testName\n";
    echo "TZ: $tz\n";

    // Simulate API request
    $input = ['action' => 'login', 'tz' => $tz];
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    // Validation (same as api.php)
    if (empty($tz) || !is_numeric($tz) || strlen($tz) !== 9) {
        echo "Result: ✗ FAIL - Invalid ID format\n\n";
        return false;
    }

    // Check user
    $stmt = $db->prepare("SELECT id, is_blocked, failed_attempts FROM users WHERE tz = ?");
    $stmt->execute([$tz]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['is_blocked']) {
            echo "Result: ✗ BLOCKED - User is blocked\n";
            echo "Details: Failed Attempts = {$user['failed_attempts']}\n\n";
            return false;
        }

        // Reset failed attempts on successful login
        $db->prepare("UPDATE users SET failed_attempts = 0, last_login = ? WHERE id = ?")
           ->execute([date('Y-m-d H:i:s'), $user['id']]);

        echo "Result: ✓ SUCCESS - Login successful\n";
        echo "Details: User ID = {$user['id']}\n\n";
        return true;
    } else {
        echo "Result: ✗ FAIL - User not found in database\n\n";
        return false;
    }
}

// Setup: Create test users
$db = getDbConnection();
echo "=== Setup: Creating test users ===\n";
$db->prepare("INSERT OR IGNORE INTO users (tz) VALUES (?)")->execute(['123456789']);
$db->prepare("INSERT OR IGNORE INTO users (tz) VALUES (?)")->execute(['555555555']);
$db->prepare("INSERT OR IGNORE INTO users (tz, is_blocked, failed_attempts) VALUES (?, 1, 5)")
   ->execute(['777777777']);
echo "Test users created\n\n";

// Test Cases
echo "=== Running Tests ===\n\n";

// Test 1: Valid existing user
simulateLoginRequest('123456789', 'Test 1: Valid existing user');

// Test 2: Invalid format - too short
simulateLoginRequest('12345', 'Test 2: Invalid format (too short)');

// Test 3: Invalid format - non-numeric
simulateLoginRequest('abc123456', 'Test 3: Invalid format (non-numeric)');

// Test 4: Non-existent user
simulateLoginRequest('888888888', 'Test 4: Non-existent user');

// Test 5: Blocked user
simulateLoginRequest('777777777', 'Test 5: Blocked user');

// Test 6: Another valid user
simulateLoginRequest('555555555', 'Test 6: Another valid user');

// Cleanup
echo "=== Cleanup ===\n";
$db->prepare("DELETE FROM users WHERE tz IN ('555555555', '777777777')")->execute();
echo "Test users removed\n";

echo "\n=== All tests completed ===\n";
?>
