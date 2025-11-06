<?php
/**
 * Main API Endpoint
 * This file now uses an orchestrator pattern to route requests to appropriate components
 *
 * Refactored to improve maintainability and reduce complexity
 * Original monolithic version backed up as api.php.backup
 */

// התחלת Session לאימות מבוסס-Session
session_start();

// Enable error logging for debugging but disable display to prevent HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.txt');

// Check if required files exist before including
if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration file not found']);
    exit;
}

if (!file_exists(__DIR__ . '/api/Orchestrator.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Orchestrator file not found']);
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/api/Orchestrator.php';

// Security headers
header('Content-Type: application/json');
$allowed_origin = 'https://qr.bot4wa.com';
header("Access-Control-Allow-Origin: $allowed_origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get input and action
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$db = getDbConnection();
$userIP = $_SERVER['REMOTE_ADDR'];

// Initialize username/password fields if they don't exist
function ensureUserAuthFields($db) {
    try {
        $result = $db->query("PRAGMA table_info(users)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'name');

        if (!in_array('username', $existingColumns)) {
            $db->exec("ALTER TABLE users ADD COLUMN username TEXT");
        }

        if (!in_array('password_hash', $existingColumns)) {
            $db->exec("ALTER TABLE users ADD COLUMN password_hash TEXT");
        }

        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username)");
    } catch (Exception $e) {
        error_log("ensureUserAuthFields error: " . $e->getMessage());
    }
}

// Initialize auth fields
ensureUserAuthFields($db);

try {
    // Create orchestrator instance
    $orchestrator = new Orchestrator($db, $_SESSION, $input, $userIP);

    // Route the request to the appropriate component
    $orchestrator->route($action);

} catch (Exception $e) {
    // Log the error with full details
    $errorDetails = "API Error: " . $e->getMessage() .
                   " | Action: $action" .
                   " | File: " . $e->getFile() .
                   " | Line: " . $e->getLine();
    error_log($errorDetails);

    // Return error response with more details in development
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'שגיאה פנימית בשרת'
    ];

    // Add debug info if not in production
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        $response['debug'] = [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }

    echo json_encode($response);
}
?>
