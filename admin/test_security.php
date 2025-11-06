<?php
// Test security implementation
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing security implementation...\n\n";

// Test 1: Check if security_helpers.php loads
echo "1. Loading security_helpers.php... ";
try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/security_helpers.php';
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check database connection
echo "2. Testing database connection... ";
try {
    $db = getDbConnection();
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check if failed_login_attempts table exists
echo "3. Checking if failed_login_attempts table exists... ";
try {
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='failed_login_attempts'")->fetchAll();
    if (count($tables) > 0) {
        echo "✓ OK\n";
    } else {
        echo "✗ NOT FOUND\n";
        echo "   You need to run the migration to create this table.\n";
    }
} catch (Exception $e) {
    echo "✗ FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 4: Test CSRF token generation
echo "4. Testing CSRF token generation... ";
try {
    session_start();
    $token = generateCsrfToken();
    if (strlen($token) == 64) {
        echo "✓ OK (token: " . substr($token, 0, 16) . "...)\n";
    } else {
        echo "✗ FAILED (token length: " . strlen($token) . ")\n";
    }
} catch (Exception $e) {
    echo "✗ FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 5: Test password validation
echo "5. Testing password validation... ";
try {
    $weak = validatePasswordStrength("admin123");
    $strong = validatePasswordStrength("MyStr0ng@Pass!");

    if (!$weak['valid'] && $strong['valid']) {
        echo "✓ OK\n";
        echo "   Weak password correctly rejected\n";
        echo "   Strong password correctly accepted\n";
    } else {
        echo "✗ FAILED\n";
        echo "   Weak valid: " . ($weak['valid'] ? 'YES' : 'NO') . "\n";
        echo "   Strong valid: " . ($strong['valid'] ? 'YES' : 'NO') . "\n";
    }
} catch (Exception $e) {
    echo "✗ FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "If 'failed_login_attempts table' is NOT FOUND, you have two options:\n";
echo "1. Create the table manually (see below)\n";
echo "2. Temporarily disable brute force protection\n\n";

echo "To create the table manually, run:\n";
echo "sqlite3 /home/user/course/form_data.db < /tmp/create_table.sql\n";
?>
