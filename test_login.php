<?php
require_once 'config.php';

echo "=== Login API Test Script ===\n\n";

function testLogin($tz, $testName) {
    $db = getDbConnection();

    // סימולציה של קלט API
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    $input = ['action' => 'login', 'tz' => $tz];

    echo "Test: $testName\n";
    echo "Input TZ: $tz\n";

    // בדיקת ולידציה
    if (empty($tz) || !is_numeric($tz) || strlen($tz) !== 9) {
        echo "Result: ❌ Validation failed - Invalid ID format\n\n";
        return false;
    }

    // בדיקת משתמש
    $stmt = $db->prepare("SELECT id, is_blocked, failed_attempts FROM users WHERE tz = ?");
    $stmt->execute([$tz]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['is_blocked']) {
            echo "Result: ❌ User is blocked\n";
            echo "Status: Blocked={$user['is_blocked']}, Failed Attempts={$user['failed_attempts']}\n\n";
            return false;
        }

        echo "Result: ✓ Login successful\n";
        echo "Status: User ID={$user['id']}, Not blocked\n\n";

        // איפוס ניסיונות
        $db->prepare("UPDATE users SET failed_attempts = 0, last_login = ? WHERE id = ?")
           ->execute([date('Y-m-d H:i:s'), $user['id']]);

        return true;
    } else {
        // משתמש לא קיים - יצירה ועדכון ניסיונות
        $db->prepare("INSERT OR IGNORE INTO users (tz) VALUES (?)")->execute([$tz]);
        $db->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE tz = ?")->execute([$tz]);

        $stmt = $db->prepare("SELECT failed_attempts, is_blocked FROM users WHERE tz = ?");
        $stmt->execute([$tz]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($updatedUser['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $db->prepare("UPDATE users SET is_blocked = 1 WHERE tz = ?")->execute([$tz]);
            echo "Result: ❌ Max attempts reached - User blocked\n";
            echo "Status: Failed Attempts={$updatedUser['failed_attempts']}\n\n";
            return false;
        }

        echo "Result: ❌ Login failed - User not found\n";
        echo "Status: Failed Attempts={$updatedUser['failed_attempts']}\n\n";
        return false;
    }
}

// === Test Cases ===

// 1. טסט עם משתמש קיים (123456789)
echo "--- Test 1: Valid existing user ---\n";
testLogin('123456789', 'Existing user login');

// 2. טסט עם פורמט לא תקין
echo "--- Test 2: Invalid format ---\n";
testLogin('12345', 'Too short ID');
testLogin('abcd12345', 'Non-numeric ID');

// 3. טסט עם משתמש לא קיים - ניסיון כושל
echo "--- Test 3: Non-existent user (will fail) ---\n";
testLogin('999999999', 'New non-existent user - attempt 1');
testLogin('999999999', 'New non-existent user - attempt 2');
testLogin('999999999', 'New non-existent user - attempt 3');
testLogin('999999999', 'New non-existent user - attempt 4');
testLogin('999999999', 'New non-existent user - attempt 5 (should block)');
testLogin('999999999', 'New non-existent user - attempt 6 (should be blocked)');

// 4. בדיקת סטטוס משתמש חסום
$db = getDbConnection();
$stmt = $db->prepare("SELECT * FROM users WHERE tz = ?");
$stmt->execute(['999999999']);
$blockedUser = $stmt->fetch(PDO::FETCH_ASSOC);

echo "--- Test 4: Blocked user status ---\n";
echo "User 999999999:\n";
echo "  Is Blocked: " . ($blockedUser['is_blocked'] ? 'YES' : 'NO') . "\n";
echo "  Failed Attempts: {$blockedUser['failed_attempts']}\n\n";

// 5. יצירת משתמש תקין נוסף לבדיקה
echo "--- Test 5: Creating additional test user ---\n";
$db->prepare("INSERT OR IGNORE INTO users (tz, is_blocked, failed_attempts) VALUES (?, 0, 0)")
   ->execute(['111111111']);
echo "User 111111111 created\n";
testLogin('111111111', 'New valid test user');

echo "=== All login tests completed ===\n";
?>
