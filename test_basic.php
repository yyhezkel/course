<?php
/**
 * Simple Test Endpoint
 * This bypasses the new architecture to test if basic API works
 * Access: https://qr.bot4wa.com/kodkod/test_basic.php
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$response = [
    'success' => true,
    'tests' => []
];

// Test 1: PHP version
$response['tests']['php_version'] = [
    'status' => 'ok',
    'value' => phpversion()
];

// Test 2: Session
session_start();
$_SESSION['test'] = 'working';
$response['tests']['session'] = [
    'status' => isset($_SESSION['test']) ? 'ok' : 'failed',
    'value' => session_status()
];

// Test 3: Config file
$response['tests']['config_exists'] = [
    'status' => file_exists(__DIR__ . '/config.php') ? 'ok' : 'failed',
    'value' => file_exists(__DIR__ . '/config.php')
];

// Test 4: New API structure
$response['tests']['new_api_exists'] = [
    'status' => file_exists(__DIR__ . '/api/Orchestrator.php') ? 'ok' : 'failed',
    'value' => file_exists(__DIR__ . '/api/Orchestrator.php')
];

// Test 5: Database
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';

    try {
        $db = getDbConnection();
        $response['tests']['database'] = [
            'status' => 'ok',
            'value' => 'Connected'
        ];

        // Check if task_progress table exists
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='task_progress'");
        $response['tests']['task_progress_table'] = [
            'status' => $result->fetch() ? 'ok' : 'missing',
            'value' => $result->fetch() ? 'exists' : 'does not exist'
        ];

    } catch (Exception $e) {
        $response['tests']['database'] = [
            'status' => 'failed',
            'error' => $e->getMessage()
        ];
    }
}

// Test 6: Try to load new API
if (file_exists(__DIR__ . '/api/Orchestrator.php')) {
    try {
        require_once __DIR__ . '/api/Orchestrator.php';
        $response['tests']['load_orchestrator'] = [
            'status' => 'ok',
            'value' => 'Loaded successfully'
        ];
    } catch (Exception $e) {
        $response['tests']['load_orchestrator'] = [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
