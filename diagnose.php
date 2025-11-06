<?php
/**
 * Diagnostic Script
 * Run this on production to diagnose API issues
 * Access via: https://qr.bot4wa.com/kodkod/diagnose.php
 */

header('Content-Type: text/plain');
echo "=== API Diagnostic Report ===\n\n";

// 1. Check PHP Version
echo "1. PHP Version: " . phpversion() . "\n";
echo "   Required: 7.4+\n\n";

// 2. Check PDO SQLite
echo "2. PDO SQLite Extension: ";
if (extension_loaded('pdo_sqlite')) {
    echo "✓ Installed\n\n";
} else {
    echo "✗ MISSING - This is required!\n\n";
}

// 3. Check File Existence
echo "3. Required Files:\n";
$files = [
    'config.php' => 'Configuration file',
    'api.php' => 'Main API endpoint',
    'api.php.backup' => 'Backup of original API',
    'api/BaseComponent.php' => 'Base component class',
    'api/Orchestrator.php' => 'Request router',
    'api/components/AuthComponent.php' => 'Auth component',
    'api/components/FormComponent.php' => 'Form component',
    'api/components/TaskComponent.php' => 'Task component',
    'api/components/UserComponent.php' => 'User component',
    'form_data.db' => 'SQLite database'
];

foreach ($files as $file => $description) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        $readable = is_readable($path) ? 'readable' : 'NOT readable';
        echo "   ✓ $file ($size bytes, $readable)\n";
    } else {
        echo "   ✗ $file - MISSING\n";
    }
}
echo "\n";

// 4. Check Directory Permissions
echo "4. Directory Permissions:\n";
$dirs = ['api', 'api/components'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $readable = is_readable($path) ? 'readable' : 'NOT readable';
        echo "   ✓ $dir (permissions: $perms, $readable)\n";
    } else {
        echo "   ✗ $dir - MISSING\n";
    }
}
echo "\n";

// 5. Test Config Loading
echo "5. Config Loading: ";
try {
    require_once __DIR__ . '/config.php';
    echo "✓ Success\n";
    echo "   DB Path: " . DB_PATH . "\n";
    echo "   DB Exists: " . (file_exists(DB_PATH) ? 'Yes' : 'NO') . "\n";

    if (file_exists(DB_PATH)) {
        $dbSize = filesize(DB_PATH);
        echo "   DB Size: " . $dbSize . " bytes\n";
    }
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Test Orchestrator Loading
echo "6. Orchestrator Loading: ";
try {
    require_once __DIR__ . '/api/Orchestrator.php';
    echo "✓ Success\n";
    echo "   All components loaded successfully\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
}
echo "\n";

// 7. Test Database Connection
echo "7. Database Connection: ";
try {
    $db = getDbConnection();
    echo "✓ Success\n";

    // Check if task_progress table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='task_progress'");
    if ($result->fetch()) {
        echo "   ✓ task_progress table exists\n";
    } else {
        echo "   ✗ task_progress table MISSING (this was the bug!)\n";
    }

    // Check other course tables
    $tables = ['course_tasks', 'user_tasks', 'course_materials'];
    foreach ($tables as $table) {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if ($result->fetch()) {
            echo "   ✓ $table table exists\n";
        } else {
            echo "   ⚠ $table table missing\n";
        }
    }
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}
echo "\n";

// 8. Test Session
echo "8. Session Support: ";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✓ Working\n\n";
} else {
    echo "✗ FAILED\n\n";
}

// 9. Check Error Log
echo "9. Error Log:\n";
$errorLogPath = __DIR__ . '/error_log.txt';
if (file_exists($errorLogPath)) {
    echo "   Location: $errorLogPath\n";
    echo "   Size: " . filesize($errorLogPath) . " bytes\n";
    echo "   Last 10 lines:\n";
    $lines = file($errorLogPath);
    $lastLines = array_slice($lines, -10);
    foreach ($lastLines as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "   No error log found yet\n";
}
echo "\n";

// 10. Recommendations
echo "10. Recommendations:\n";

if (!extension_loaded('pdo_sqlite')) {
    echo "   ! Install PDO SQLite extension\n";
}

if (!file_exists(__DIR__ . '/api/Orchestrator.php')) {
    echo "   ! Deploy new API files from git repository\n";
}

if (!file_exists(DB_PATH)) {
    echo "   ! Database file missing - run db_init.php\n";
}

echo "\n=== End of Diagnostic Report ===\n";
?>
