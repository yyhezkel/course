<?php
/**
 * Simple test script - upload to your server and visit it in a browser
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile Photo Setup Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; }
    </style>
</head>
<body>
    <h1>Profile Photo Setup Test</h1>

    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Testing Configuration</h2>";

    // Test 1: Check config.php
    echo "<p>";
    if (file_exists(__DIR__ . '/config.php')) {
        echo "<span class='success'>✓ config.php exists</span>";
    } else {
        echo "<span class='error'>✗ config.php NOT found</span>";
        echo "<br><span class='info'>You need to create it from config.example.php</span>";
        exit;
    }
    echo "</p>";

    require_once 'config.php';

    // Test 2: Database connection
    echo "<p>";
    try {
        $db = getDbConnection();
        echo "<span class='success'>✓ Database connection OK</span>";
    } catch (Exception $e) {
        echo "<span class='error'>✗ Database connection FAILED: " . htmlspecialchars($e->getMessage()) . "</span>";
        exit;
    }
    echo "</p>";

    // Test 3: Check users table
    echo "<p>";
    try {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if ($result->fetch()) {
            echo "<span class='success'>✓ users table exists</span>";
        } else {
            echo "<span class='error'>✗ users table NOT found</span>";
            exit;
        }
    } catch (Exception $e) {
        echo "<span class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</span>";
        exit;
    }
    echo "</p>";

    // Test 4: Check profile_photo column
    echo "<p>";
    try {
        $result = $db->query("PRAGMA table_info(users)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $hasProfilePhoto = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'profile_photo') {
                $hasProfilePhoto = true;
                break;
            }
        }

        if ($hasProfilePhoto) {
            echo "<span class='success'>✓ profile_photo column exists</span>";
        } else {
            echo "<span class='error'>✗ profile_photo column NOT found</span>";
            echo "<br><span class='info'>Run: php add_profile_photo_column.php</span>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    echo "</p>";

    // Test 5: Check upload directory
    echo "<h2>Testing Upload Directory</h2>";
    $uploadDir = '/www/wwwroot/qr.bot4wa.com/files/kodkod-uplodes/profile-photos/';

    echo "<p><strong>Directory:</strong> " . htmlspecialchars($uploadDir) . "</p>";

    echo "<p>";
    if (file_exists($uploadDir)) {
        echo "<span class='success'>✓ Directory exists</span>";
    } else {
        echo "<span class='error'>✗ Directory NOT found</span>";
        echo "<br><span class='info'>Run: mkdir -p " . htmlspecialchars($uploadDir) . "</span>";
    }
    echo "</p>";

    if (file_exists($uploadDir)) {
        echo "<p>";
        if (is_writable($uploadDir)) {
            echo "<span class='success'>✓ Directory is writable</span>";
        } else {
            echo "<span class='error'>✗ Directory NOT writable</span>";
            echo "<br><span class='info'>Run: chmod 755 " . htmlspecialchars($uploadDir) . "</span>";
        }
        echo "</p>";
    }

    // Test 6: Check task submissions directory
    echo "<h2>Testing Task Submissions Directory</h2>";
    $taskDir = '/www/wwwroot/qr.bot4wa.com/files/kodkod-uplodes/task-submissions/';

    echo "<p><strong>Directory:</strong> " . htmlspecialchars($taskDir) . "</p>";

    echo "<p>";
    if (file_exists($taskDir)) {
        echo "<span class='success'>✓ Directory exists</span>";
    } else {
        echo "<span class='error'>✗ Directory NOT found</span>";
        echo "<br><span class='info'>Run: mkdir -p " . htmlspecialchars($taskDir) . "</span>";
    }
    echo "</p>";

    if (file_exists($taskDir)) {
        echo "<p>";
        if (is_writable($taskDir)) {
            echo "<span class='success'>✓ Directory is writable</span>";
        } else {
            echo "<span class='error'>✗ Directory NOT writable</span>";
            echo "<br><span class='info'>Run: chmod 755 " . htmlspecialchars($taskDir) . "</span>";
        }
        echo "</p>";
    }

    // Summary
    echo "<h2>Summary</h2>";
    echo "<p>If all checks show <span class='success'>✓</span>, your setup is complete!</p>";
    echo "<p>If you see any <span class='error'>✗</span>, follow the instructions above.</p>";
    ?>
</body>
</html>
