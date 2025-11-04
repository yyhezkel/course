<?php
/**
 * Test file to verify table API endpoints work
 */

session_start();
require_once __DIR__ . '/../config.php';

echo "<h1>Testing Table API Endpoints</h1>";

// Test 1: Database connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    $db = getDbConnection();
    echo "✅ Database connected<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Get Forms
echo "<h2>Test 2: Get Forms</h2>";
try {
    $stmt = $db->query("SELECT id, title FROM forms ORDER BY id ASC");
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Found " . count($forms) . " forms<br>";
    foreach ($forms as $form) {
        echo "  - Form {$form['id']}: {$form['title']}<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Get Table Data for Form 1
echo "<h2>Test 3: Get Table Data (Form 1)</h2>";
try {
    $formId = 1;

    // Get users
    $usersStmt = $db->prepare("
        SELECT DISTINCT u.id, u.tz, u.full_name
        FROM users u
        INNER JOIN form_responses fr ON u.id = fr.user_id
        WHERE fr.form_id = ?
        ORDER BY u.id ASC
        LIMIT 5
    ");
    $usersStmt->execute([$formId]);
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Found " . count($users) . " users with responses<br>";

    // Get questions
    $questionsStmt = $db->prepare("
        SELECT q.id, q.question_text
        FROM questions q
        INNER JOIN form_questions fq ON q.id = fq.question_id
        WHERE fq.form_id = ? AND fq.is_active = 1
        ORDER BY fq.sequence_order ASC
        LIMIT 5
    ");
    $questionsStmt->execute([$formId]);
    $questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Found " . count($questions) . " questions<br>";

    // Get answers
    $answersStmt = $db->prepare("
        SELECT COUNT(*) as count FROM form_responses WHERE form_id = ?
    ");
    $answersStmt->execute([$formId]);
    $answerCount = $answersStmt->fetchColumn();
    echo "✅ Found {$answerCount} total answers<br>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Direct API call simulation
echo "<h2>Test 4: API Call Simulation</h2>";
echo "<p>Try accessing these URLs:</p>";
echo "<ul>";
echo "<li><a href='api.php?action=get_forms' target='_blank'>api.php?action=get_forms</a></li>";
echo "<li><a href='api.php?action=get_table_data&form_id=1' target='_blank'>api.php?action=get_table_data&form_id=1</a></li>";
echo "</ul>";

echo "<h2>Test 5: Check Authentication</h2>";
echo "<p>Admin session status:</p>";
echo "<ul>";
echo "<li>Session ID: " . (session_id() ? session_id() : 'No session') . "</li>";
echo "<li>Admin logged in: " . (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] ? '✅ Yes' : '❌ No') . "</li>";
echo "<li>Admin ID: " . ($_SESSION['admin_id'] ?? 'Not set') . "</li>";
echo "</ul>";

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo "<p style='color: red; font-weight: bold;'>⚠️ YOU ARE NOT LOGGED IN! This is why the table is empty.</p>";
    echo "<p><a href='index.html'>Go to Admin Login</a></p>";
}
?>
