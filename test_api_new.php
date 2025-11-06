<?php
// Test API endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

try {
    echo "1. Starting test...\n";

    require_once 'config.php';
    echo "2. Config loaded\n";

    require_once __DIR__ . '/api/Orchestrator.php';
    echo "3. Orchestrator loaded\n";

    // Test with a simple session
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = 1;
    $_SESSION['login_time'] = time();

    $db = getDbConnection();
    echo "4. DB connection created\n";

    $input = ['action' => 'check_session'];
    $orchestrator = new Orchestrator($db, $_SESSION, $input, '127.0.0.1');
    echo "5. Orchestrator instantiated\n";

    $orchestrator->route('check_session');

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
