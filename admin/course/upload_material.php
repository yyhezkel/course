<?php
/**
 * Course Materials File Upload Handler
 * Handles file uploads for course materials
 */

// Disable error output to prevent breaking JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'נדרש אימות מנהל']);
    exit;
}

try {
    require_once __DIR__ . '/../../config.php';
} catch (Exception $e) {
    http_response_code(500);
    error_log("Config error in upload_material: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת הגדרות']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'לא הועלה קובץ או שגיאה בהעלאה']);
    exit;
}

$file = $_FILES['file'];
$uploadDir = '/www/wwwroot/qr.bot4wa.com/files/kodkod-uplodes/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        error_log("Failed to create upload directory: " . $uploadDir);
        echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת תיקיית העלאה']);
        exit;
    }
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

// Get MIME type safely
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
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    'txt' => 'text/plain'
];

// Use extension-based MIME if detection failed
if ($mimeType === 'application/octet-stream' && isset($extensionToMime[$extension])) {
    $mimeType = $extensionToMime[$extension];
}

if (!in_array($mimeType, $allowedTypes) && !isset($extensionToMime[$extension])) {
    http_response_code(400);
    error_log("Unsupported file type: " . $mimeType . " (extension: " . $extension . ")");
    echo json_encode(['success' => false, 'message' => 'סוג קובץ לא נתמך: ' . $extension]);
    exit;
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = uniqid('material_', true) . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFilename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    error_log("Failed to move uploaded file to: " . $uploadPath);
    echo json_encode(['success' => false, 'message' => 'שגיאה בשמירת הקובץ']);
    exit;
}

// Verify file was saved
if (!file_exists($uploadPath)) {
    http_response_code(500);
    error_log("File not found after upload: " . $uploadPath);
    echo json_encode(['success' => false, 'message' => 'שגיאה: הקובץ לא נמצא לאחר העלאה']);
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
        'file_path' => 'https://qr.bot4wa.com/files/kodkod-uplodes/' . $uniqueFilename,
        'mime_type' => $mimeType,
        'size' => $file['size'],
        'material_type' => $materialType
    ]
]);
