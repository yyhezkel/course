<?php
/**
 * File Upload Handler for Course Management System
 * Handles file uploads for task submissions and course materials
 */

session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'נדרש אימות']);
    exit;
}

$db = getDbConnection();
$userId = $_SESSION['user_id'];

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/uploads';
$userUploadsDir = $uploadsDir . '/users';
$materialsDir = $uploadsDir . '/materials';

if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
if (!is_dir($userUploadsDir)) mkdir($userUploadsDir, 0755, true);
if (!is_dir($materialsDir)) mkdir($materialsDir, 0755, true);

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

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
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
        $filepath = $userUploadsDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save file');
        }

        // Save file info to database
        $relativeFilepath = 'uploads/users/' . $filename;
        $stmt = $db->prepare("
            INSERT INTO task_submissions (user_task_id, filename, original_filename, filepath, filesize, mime_type, description, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $userTaskId,
            $filename,
            $file['name'],
            $relativeFilepath,
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
