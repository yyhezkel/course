<?php
/**
 * Test Old API Endpoint
 * This uses the backup to test if the old code works
 * Access: https://qr.bot4wa.com/kodkod/test_old_api.php
 */

session_start();

// Set up a fake authenticated session
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = 1;
$_SESSION['login_time'] = time();

require_once 'config.php';

header('Content-Type: application/json');

// Get action from POST or GET
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? 'get_task_detail';
$userTaskId = $input['user_task_id'] ?? $_GET['user_task_id'] ?? '1';

$db = getDbConnection();
$userId = $_SESSION['user_id'];

echo json_encode([
    'test' => 'Testing get_task_detail directly',
    'session_user_id' => $userId,
    'requested_task_id' => $userTaskId
]);

// Initialize course tables
try {
    // Check if course_tasks table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='course_tasks'");
    if (!$result->fetch()) {
        echo json_encode(['error' => 'course_tasks table does not exist']);
        exit;
    }

    // Check if task_progress table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='task_progress'");
    if (!$result->fetch()) {
        // Create the missing table
        $db->exec("
            CREATE TABLE task_progress (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_task_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                progress_percentage INTEGER DEFAULT 0,
                started_at DATETIME,
                completed_at DATETIME,
                reviewed_at DATETIME,
                reviewed_by INTEGER,
                review_notes TEXT,
                submission_data TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE,
                UNIQUE(user_task_id)
            )
        ");
        echo json_encode(['info' => 'Created task_progress table']);
    }

    // Now try the actual query
    $stmt = $db->prepare("
        SELECT
            ut.id,
            ut.task_id,
            ut.status,
            ut.due_date,
            ut.priority,
            ut.admin_notes,
            ct.title,
            ct.description,
            ct.instructions,
            ct.task_type,
            ct.estimated_duration,
            ct.points,
            ct.form_id,
            tp.review_notes
        FROM user_tasks ut
        JOIN course_tasks ct ON ut.task_id = ct.id
        LEFT JOIN task_progress tp ON ut.id = tp.user_task_id
        WHERE ut.id = ? AND ut.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userTaskId, $userId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($task) {
        echo json_encode([
            'success' => true,
            'task' => $task,
            'message' => 'Direct query works! The old query now works with the table.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No task found with id=' . $userTaskId . ' for user_id=' . $userId
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
