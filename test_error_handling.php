<?php
/**
 * Error Handling and Edge Cases Test Script
 */

require_once 'config.php';

echo "=== Error Handling & Edge Cases Test ===\n\n";

// Test 1: Database Connection
echo "Test 1: Database Connection\n";
try {
    $db = getDbConnection();
    echo "✓ Database connection successful\n";

    // Test database is writable
    $db->exec("CREATE TABLE IF NOT EXISTS _test (id INTEGER)");
    $db->exec("DROP TABLE _test");
    echo "✓ Database is writable\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Foreign Key Constraints
echo "Test 2: Foreign Key Constraints\n";
try {
    // Try to insert response for non-existent user
    $stmt = $db->prepare("INSERT INTO form_responses (user_id, question_id, answer_value) VALUES (?, ?, ?)");
    $stmt->execute([99999, 'test_q', 'test_a']);
    echo "✗ WARNING: Foreign key constraint not working!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'FOREIGN KEY constraint') !== false) {
        echo "✓ Foreign key constraint working correctly\n";
    } else {
        echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 3: Unique Constraint (user_id, question_id)
echo "Test 3: Unique Constraint\n";
try {
    // Create test user
    $db->exec("INSERT OR IGNORE INTO users (tz) VALUES ('111111111')");
    $userId = $db->query("SELECT id FROM users WHERE tz = '111111111'")->fetch()['id'];

    // Insert first response
    $stmt = $db->prepare("INSERT INTO form_responses (user_id, question_id, answer_value) VALUES (?, ?, ?)");
    $stmt->execute([$userId, 'test_unique', 'value1']);

    // Try to insert duplicate (should fail without UPSERT)
    try {
        $stmt->execute([$userId, 'test_unique', 'value2']);
        echo "✗ Unique constraint not working!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
            echo "✓ Unique constraint working correctly\n";
        }
    }

    // Test UPSERT (should succeed)
    $upsertStmt = $db->prepare("
        INSERT INTO form_responses (user_id, question_id, answer_value)
        VALUES (?, ?, ?)
        ON CONFLICT(user_id, question_id)
        DO UPDATE SET answer_value = excluded.answer_value
    ");
    $upsertStmt->execute([$userId, 'test_unique', 'value2_updated']);
    echo "✓ UPSERT working correctly\n";

    // Cleanup
    $db->exec("DELETE FROM form_responses WHERE question_id = 'test_unique'");
    $db->exec("DELETE FROM users WHERE tz = '111111111'");
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Large Data Handling
echo "Test 4: Large Data Handling\n";
try {
    $db->exec("INSERT OR IGNORE INTO users (tz) VALUES ('222222222')");
    $userId = $db->query("SELECT id FROM users WHERE tz = '222222222'")->fetch()['id'];

    // Test with large text (10KB)
    $largeText = str_repeat("Lorem ipsum dolor sit amet. ", 350); // ~10KB
    $stmt = $db->prepare("INSERT INTO form_responses (user_id, question_id, answer_value) VALUES (?, ?, ?)");
    $stmt->execute([$userId, 'large_text', $largeText]);

    // Verify it was stored
    $result = $db->query("SELECT length(answer_value) as len FROM form_responses WHERE question_id = 'large_text'")->fetch();
    echo "✓ Large text stored successfully (" . $result['len'] . " bytes)\n";

    // Cleanup
    $db->exec("DELETE FROM form_responses WHERE question_id = 'large_text'");
    $db->exec("DELETE FROM users WHERE tz = '222222222'");
} catch (Exception $e) {
    echo "✗ Error with large data: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Special Characters Handling
echo "Test 5: Special Characters Handling\n";
try {
    $db->exec("INSERT OR IGNORE INTO users (tz) VALUES ('333333333')");
    $userId = $db->query("SELECT id FROM users WHERE tz = '333333333'")->fetch()['id'];

    $specialChars = [
        'hebrew' => 'שלום עולם! אבגדהוזחטיכלמנסעפצקרשת',
        'quotes' => "Test with \"quotes\" and 'apostrophes'",
        'newlines' => "Line 1\nLine 2\nLine 3",
        'html' => '<script>alert("xss")</script>',
        'emoji' => '😀 🎉 👍 ❤️',
    ];

    $stmt = $db->prepare("INSERT INTO form_responses (user_id, question_id, answer_value) VALUES (?, ?, ?)");

    foreach ($specialChars as $type => $text) {
        $stmt->execute([$userId, "special_$type", $text]);
    }

    // Verify data integrity
    $results = $db->query("SELECT question_id, answer_value FROM form_responses WHERE user_id = $userId")->fetchAll();
    $allCorrect = true;

    foreach ($results as $row) {
        $type = str_replace('special_', '', $row['question_id']);
        if ($row['answer_value'] !== $specialChars[$type]) {
            echo "✗ Data corruption for $type\n";
            $allCorrect = false;
        }
    }

    if ($allCorrect) {
        echo "✓ All special characters stored and retrieved correctly\n";
    }

    // Cleanup
    $db->exec("DELETE FROM form_responses WHERE user_id = $userId");
    $db->exec("DELETE FROM users WHERE tz = '333333333'");
} catch (Exception $e) {
    echo "✗ Error with special characters: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Empty/Null Values
echo "Test 6: Empty/Null Values\n";
try {
    $db->exec("INSERT OR IGNORE INTO users (tz) VALUES ('444444444')");
    $userId = $db->query("SELECT id FROM users WHERE tz = '444444444'")->fetch()['id'];

    $stmt = $db->prepare("INSERT INTO form_responses (user_id, question_id, answer_value) VALUES (?, ?, ?)");

    // Test empty string
    $stmt->execute([$userId, 'empty_test', '']);
    // Test null (converted to string)
    $stmt->execute([$userId, 'null_test', null]);

    $results = $db->query("SELECT question_id, answer_value FROM form_responses WHERE user_id = $userId")->fetchAll();
    echo "✓ Empty/null values handled: " . count($results) . " records stored\n";

    foreach ($results as $row) {
        $value = $row['answer_value'] === null ? 'NULL' : "'{$row['answer_value']}'";
        echo "  - {$row['question_id']}: $value\n";
    }

    // Cleanup
    $db->exec("DELETE FROM form_responses WHERE user_id = $userId");
    $db->exec("DELETE FROM users WHERE tz = '444444444'");
} catch (Exception $e) {
    echo "✗ Error with empty/null values: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Multiple Concurrent Updates
echo "Test 7: Transaction Rollback\n";
try {
    $db->exec("INSERT OR IGNORE INTO users (tz) VALUES ('555555555')");
    $userId = $db->query("SELECT id FROM users WHERE tz = '555555555'")->fetch()['id'];

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO form_responses (user_id, question_id, answer_value) VALUES (?, ?, ?)");
    $stmt->execute([$userId, 'trans_test1', 'value1']);
    $stmt->execute([$userId, 'trans_test2', 'value2']);

    // Rollback
    $db->rollBack();

    // Check if data was actually rolled back
    $count = $db->query("SELECT COUNT(*) as cnt FROM form_responses WHERE user_id = $userId")->fetch()['cnt'];

    if ($count == 0) {
        echo "✓ Transaction rollback working correctly\n";
    } else {
        echo "✗ Transaction rollback failed - $count records found\n";
    }

    // Cleanup
    $db->exec("DELETE FROM users WHERE tz = '555555555'");
} catch (Exception $e) {
    echo "✗ Error with transactions: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 8: Database File Permissions
echo "Test 8: Database File Permissions\n";
$dbPath = DB_PATH;
if (file_exists($dbPath)) {
    $perms = substr(sprintf('%o', fileperms($dbPath)), -4);
    $owner = posix_getpwuid(fileowner($dbPath))['name'] ?? 'unknown';
    $group = posix_getgrgid(filegroup($dbPath))['name'] ?? 'unknown';

    echo "Database file: $dbPath\n";
    echo "Permissions: $perms\n";
    echo "Owner: $owner:$group\n";

    if (is_readable($dbPath) && is_writable($dbPath)) {
        echo "✓ Database is readable and writable\n";
    } else {
        echo "✗ WARNING: Database permissions issue\n";
    }
} else {
    echo "✗ Database file not found!\n";
}
echo "\n";

echo "=== All error handling tests completed ===\n";
?>
