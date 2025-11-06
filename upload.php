<?php
/**
 * File Upload Handler for Course Management System
 * Handles file uploads for task submissions and course materials
 */

session_start();
require_once __DIR__ . '/config.php';

// Disable HTML error output to ensure JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'נדרש אימות']);
    exit;
}

try {
    $db = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

$userId = $_SESSION['user_id'];

// Ensure task_submissions table exists
try {
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='task_submissions'");
    if (!$result->fetch()) {
        // Create the table if it doesn't exist
        $db->exec("
            CREATE TABLE task_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_task_id INTEGER NOT NULL,
                filename TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                filepath TEXT NOT NULL,
                filesize INTEGER NOT NULL,
                mime_type TEXT NOT NULL,
                description TEXT,
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_task_submissions_user_task ON task_submissions(user_task_id)");
    }
} catch (Exception $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// Create uploads directory if it doesn't exist (using same structure as admin materials and profile photos)
$uploadDir = '/www/wwwroot/qr.bot4wa.com/files/kodkod-uplodes/task-submissions/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        error_log("Failed to create upload directory: " . $uploadDir);
        echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת תיקיית העלאה']);
        exit;
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $userTaskId = $_POST['user_task_id'] ?? '';
    $description = $_POST['description'] ?? '';

    if (empty($userTaskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה משימה']);
        exit;
    }

    $file = $_FILES['file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'שגיאה בהעלאת הקובץ']);
        exit;
    }

    // Check file size (max 10MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'הקובץ גדול מדי (מקסימום 10MB)']);
        exit;
    }

    // Validate file type
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'text/plain',
        'application/zip'
    ];

    // Get MIME type safely with fallback
    $mimeType = 'application/octet-stream'; // default
    if (function_exists('mime_content_type') && file_exists($file['tmp_name'])) {
        $mimeType = mime_content_type($file['tmp_name']);
    } elseif (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }

    // Also check by file extension as fallback
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extensionToMime = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'txt' => 'text/plain'
    ];

    // Use extension-based MIME if detection failed
    if ($mimeType === 'application/octet-stream' && isset($extensionToMime[$extension])) {
        $mimeType = $extensionToMime[$extension];
    }

    if (!in_array($mimeType, $allowedTypes) && !isset($extensionToMime[$extension])) {
        http_response_code(400);
        error_log("Unsupported file type for task submission: " . $mimeType . " (extension: " . $extension . ")");
        echo json_encode(['success' => false, 'message' => 'סוג קובץ לא נתמך']);
        exit;
    }

    try {
        // Verify user owns this task
        $stmt = $db->prepare("SELECT task_id FROM user_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$userTaskId, $userId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'אין הרשאה להעלות קובץ למשימה זו']);
            exit;
        }

        // Create unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('task_' . $userTaskId . '_') . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save file');
        }

        // Verify file was saved
        if (!file_exists($uploadPath)) {
            http_response_code(500);
            error_log("File not found after upload: " . $uploadPath);
            throw new Exception('שגיאה: הקובץ לא נמצא לאחר העלאה');
        }

        // Save file info to database with full URL (consistent with admin materials and profile photos)
        $fileUrl = 'https://qr.bot4wa.com/files/kodkod-uplodes/task-submissions/' . $filename;
        $stmt = $db->prepare("
            INSERT INTO task_submissions (user_task_id, filename, original_filename, filepath, filesize, mime_type, description, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $userTaskId,
            $filename,
            $file['name'],
            $fileUrl,
            $file['size'],
            $mimeType,
            $description
        ]);

        $submissionId = $db->lastInsertId();

        // Update task status to needs_review if it was in_progress
        $stmt = $db->prepare("UPDATE user_tasks SET status = 'needs_review' WHERE id = ? AND status = 'in_progress'");
        $stmt->execute([$userTaskId]);

        echo json_encode([
            'success' => true,
            'message' => 'הקובץ הועלה בהצלחה',
            'submission_id' => $submissionId,
            'filename' => $filename,
            'original_filename' => $file['name']
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בשמירת הקובץ: ' . $e->getMessage()]);
    }
    exit;
}

// Handle get submissions for a task
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_task_id'])) {
    $userTaskId = $_GET['user_task_id'];

    try {
        // Verify user owns this task
        $stmt = $db->prepare("SELECT id FROM user_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$userTaskId, $userId]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
            exit;
        }

        // Get all submissions for this task
        $stmt = $db->prepare("
            SELECT id, filename, original_filename, filepath, filesize, mime_type, description, uploaded_at
            FROM task_submissions
            WHERE user_task_id = ?
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$userTaskId]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'submissions' => $submissions
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
