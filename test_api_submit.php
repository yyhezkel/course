<?php
/**
 * API Form Submission Test Script
 * Tests the submit endpoint with various scenarios
 */

require_once 'config.php';

echo "=== Form Submission API Test ===\n\n";

function simulateSubmitRequest($userId, $formData, $useApiKey, $testName) {
    global $db;
    if (!isset($db)) {
        $db = getDbConnection();
    }

    echo "Test: $testName\n";
    echo "User ID: $userId\n";
    echo "Form Data: " . count($formData) . " fields\n";
    echo "API Key: " . ($useApiKey ? 'Provided' : 'Missing') . "\n";

    // Simulate API key check
    if (!$useApiKey) {
        echo "Result: ✗ FAIL - API Key missing or invalid\n\n";
        return false;
    }

    // Validate input
    if (empty($userId) || empty($formData)) {
        echo "Result: ✗ FAIL - Missing user_id or form_data\n\n";
        return false;
    }

    // Test UPSERT functionality
    $db->beginTransaction();
    $success = true;

    $upsertSql = "
        INSERT INTO form_responses (user_id, question_id, answer_value)
        VALUES (?, ?, ?)
        ON CONFLICT(user_id, question_id)
        DO UPDATE SET answer_value = excluded.answer_value, submitted_at = CURRENT_TIMESTAMP;
    ";

    $stmt = $db->prepare($upsertSql);

    foreach ($formData as $questionId => $answerValue) {
        try {
            $stmt->execute([$userId, $questionId, (string)$answerValue]);
        } catch (Exception $e) {
            echo "Result: ✗ FAIL - Database error: " . $e->getMessage() . "\n\n";
            $success = false;
            break;
        }
    }

    if ($success) {
        $db->commit();
        echo "Result: ✓ SUCCESS - Form saved successfully\n";

        // Verify data was saved
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM form_responses WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Details: {$result['count']} responses saved for this user\n\n";
        return true;
    } else {
        $db->rollBack();
        echo "Result: ✗ FAIL - Transaction rolled back\n\n";
        return false;
    }
}

// Setup: Create test user
$db = getDbConnection();
echo "=== Setup: Creating test user ===\n";
$db->prepare("INSERT OR IGNORE INTO users (tz) VALUES (?)")->execute(['123456789']);
$stmt = $db->prepare("SELECT id FROM users WHERE tz = ?");
$stmt->execute(['123456789']);
$testUser = $stmt->fetch(PDO::FETCH_ASSOC);
$testUserId = $testUser['id'];
echo "Test user created/found: ID = $testUserId\n\n";

// Test Cases
echo "=== Running Tests ===\n\n";

// Test 1: Submit without API key
$formData1 = [
    'personal_id' => '123456789',
    'full_name' => 'Test User',
    'rank' => 'טוראי'
];
simulateSubmitRequest($testUserId, $formData1, false, 'Test 1: Submit without API key');

// Test 2: Submit with API key - First submission
$formData2 = [
    'personal_id' => '123456789',
    'full_name' => 'Test User',
    'rank' => 'טוראי',
    'phone' => '0501234567'
];
simulateSubmitRequest($testUserId, $formData2, true, 'Test 2: First submission with API key');

// Test 3: Submit with API key - Update existing data
$formData3 = [
    'personal_id' => '123456789',
    'full_name' => 'Updated User Name',
    'rank' => 'רב-טוראי',
    'email' => 'test@example.com'
];
simulateSubmitRequest($testUserId, $formData3, true, 'Test 3: Update existing data');

// Test 4: Submit without user_id
simulateSubmitRequest(null, $formData2, true, 'Test 4: Submit without user_id');

// Test 5: Submit with empty form_data
simulateSubmitRequest($testUserId, [], true, 'Test 5: Submit with empty form_data');

// Test 6: Submit with invalid user_id
simulateSubmitRequest(99999, $formData2, true, 'Test 6: Submit with non-existent user_id');

// Verify final state
echo "=== Final Data Verification ===\n";
$stmt = $db->prepare("SELECT question_id, answer_value FROM form_responses WHERE user_id = ? ORDER BY question_id");
$stmt->execute([$testUserId]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Final responses for user $testUserId:\n";
foreach ($responses as $response) {
    echo "  {$response['question_id']}: {$response['answer_value']}\n";
}
echo "\n";

// Cleanup
echo "=== Cleanup ===\n";
$db->prepare("DELETE FROM form_responses WHERE user_id = ?")->execute([$testUserId]);
echo "Test data removed\n";

echo "\n=== All tests completed ===\n";
?>
