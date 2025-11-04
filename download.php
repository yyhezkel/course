<?php
/**
 * File Download Handler
 * Handles secure file downloads for course materials and task submissions
 */

session_start();
require_once __DIR__ . '/config.php';

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    die('Unauthorized');
}

$db = getDbConnection();
$userId = $_SESSION['user_id'];

$fileId = $_GET['id'] ?? '';
$type = $_GET['type'] ?? 'submission'; // submission or material

if (empty($fileId)) {
    http_response_code(400);
    die('Missing file ID');
}

try {
    if ($type === 'submission') {
        // Download task submission
        $stmt = $db->prepare("
            SELECT ts.filepath, ts.original_filename, ts.mime_type, ut.user_id
            FROM task_submissions ts
            JOIN user_tasks ut ON ts.user_task_id = ut.id
            WHERE ts.id = ?
        ");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            http_response_code(404);
            die('File not found');
        }

        // Check if user owns this file
        if ($file['user_id'] != $userId) {
            http_response_code(403);
            die('Access denied');
        }

        $filepath = __DIR__ . '/' . $file['filepath'];
        $filename = $file['original_filename'];
        $mimeType = $file['mime_type'];

    } elseif ($type === 'material') {
        // Download course material
        $stmt = $db->prepare("
            SELECT cm.file_path, cm.title, cm.task_id
            FROM course_materials cm
            WHERE cm.id = ?
        ");
        $stmt->execute([$fileId]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            http_response_code(404);
            die('Material not found');
        }

        // Check if user has access to this task
        $stmt = $db->prepare("
            SELECT ut.id
            FROM user_tasks ut
            WHERE ut.user_id = ? AND ut.task_id = ?
        ");
        $stmt->execute([$userId, $material['task_id']]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            die('Access denied - task not assigned to you');
        }

        $filepath = __DIR__ . '/' . $material['file_path'];
        $filename = $material['title'];
        $mimeType = mime_content_type($filepath);

    } else {
        http_response_code(400);
        die('Invalid type');
    }

    // Check if file exists
    if (!file_exists($filepath)) {
        http_response_code(404);
        die('File not found on server');
    }

    // Send file
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($filepath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}
?>
