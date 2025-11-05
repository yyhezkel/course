<?php
/**
 * Course Materials File Upload Handler
 * Handles file uploads for course materials
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'נדרש אימות מנהל']);
    exit;
}

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'לא הועלה קובץ או שגיאה בהעלאה']);
    exit;
}

$file = $_FILES['file'];
$uploadDir = __DIR__ . '/../../uploads/materials/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validate file size (max 50MB for course materials)
$maxSize = 50 * 1024 * 1024; // 50MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'הקובץ גדול מדי (מקסימום 50MB)']);
    exit;
}

// Allowed file types for course materials
$allowedTypes = [
    // Documents
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    // Images
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    // Video
    'video/mp4',
    'video/webm',
    'video/quicktime',
    // Audio
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    // Archives
    'application/zip',
    'application/x-rar-compressed',
    // Text
    'text/plain'
];

$mimeType = mime_content_type($file['tmp_name']);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'סוג קובץ לא נתמך']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = uniqid('material_', true) . '.' . $extension;
$uploadPath = $uploadDir . $uniqueFilename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'שגיאה בשמירת הקובץ']);
    exit;
}

// Determine material type based on MIME type
$materialType = 'document'; // default

if (strpos($mimeType, 'image/') === 0) {
    $materialType = 'image';
} elseif (strpos($mimeType, 'video/') === 0) {
    $materialType = 'video';
} elseif (strpos($mimeType, 'audio/') === 0) {
    $materialType = 'audio';
} elseif ($mimeType === 'application/pdf') {
    $materialType = 'pdf';
} elseif (in_array($mimeType, ['application/zip', 'application/x-rar-compressed'])) {
    $materialType = 'archive';
} elseif ($mimeType === 'text/plain') {
    $materialType = 'text';
}

// Return success with file info
echo json_encode([
    'success' => true,
    'message' => 'הקובץ הועלה בהצלחה',
    'file' => [
        'original_name' => $file['name'],
        'unique_name' => $uniqueFilename,
        'file_path' => 'uploads/materials/' . $uniqueFilename,
        'mime_type' => $mimeType,
        'size' => $file['size'],
        'material_type' => $materialType
    ]
]);
