<?php
/**
 * User Profile Photo Upload Handler
 * Handles profile photo uploads for authenticated users
 */

// Disable error output to prevent breaking JSON response
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

// Check user authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'נדרש אימות']);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'משתמש לא זוהה']);
    exit;
}

try {
    require_once __DIR__ . '/config.php';
} catch (Exception $e) {
    http_response_code(500);
    error_log("Config error in upload_profile_photo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת הגדרות']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'לא הועלתה תמונה או שגיאה בהעלאה']);
    exit;
}

$file = $_FILES['file'];
$uploadDir = '/www/wwwroot/qr.bot4wa.com/files/kodkod-uplodes/profile-photos/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        error_log("Failed to create upload directory: " . $uploadDir);
        echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת תיקיית העלאה']);
        exit;
    }
}

// Validate file size (max 5MB for profile photos)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'התמונה גדולה מדי (מקסימום 5MB)']);
    exit;
}

// Allowed file types - only images
$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
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
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

// Use extension-based MIME if detection failed
if ($mimeType === 'application/octet-stream' && isset($extensionToMime[$extension])) {
    $mimeType = $extensionToMime[$extension];
}

if (!in_array($mimeType, $allowedTypes) && !isset($extensionToMime[$extension])) {
    http_response_code(400);
    error_log("Unsupported file type for profile photo: " . $mimeType . " (extension: " . $extension . ")");
    echo json_encode(['success' => false, 'message' => 'סוג קובץ לא נתמך. אנא העלה תמונה (JPG, PNG, GIF, WEBP)']);
    exit;
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = uniqid('profile_', true) . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFilename;

// Delete old profile photo if exists
try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldPhoto = $stmt->fetchColumn();

    if ($oldPhoto) {
        // Extract filename from URL
        $oldPhotoFilename = basename($oldPhoto);
        $oldPhotoPath = $uploadDir . $oldPhotoFilename;

        // Delete old file if it exists
        if (file_exists($oldPhotoPath)) {
            @unlink($oldPhotoPath);
        }
    }
} catch (Exception $e) {
    error_log("Error deleting old profile photo: " . $e->getMessage());
    // Continue anyway - not critical
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    error_log("Failed to move uploaded file to: " . $uploadPath);
    echo json_encode(['success' => false, 'message' => 'שגיאה בשמירת התמונה']);
    exit;
}

// Verify file was saved
if (!file_exists($uploadPath)) {
    http_response_code(500);
    error_log("File not found after upload: " . $uploadPath);
    echo json_encode(['success' => false, 'message' => 'שגיאה: התמונה לא נמצאה לאחר העלאה']);
    exit;
}

// Update database with new photo URL
$photoUrl = 'https://qr.bot4wa.com/files/kodkod-uplodes/profile-photos/' . $uniqueFilename;

try {
    $db = getDbConnection();
    $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$photoUrl, $userId]);

    // Return success with file info
    echo json_encode([
        'success' => true,
        'message' => 'התמונה הועלתה בהצלחה',
        'photo_url' => $photoUrl
    ]);
} catch (Exception $e) {
    // Delete uploaded file if database update fails
    @unlink($uploadPath);

    http_response_code(500);
    error_log("Database error updating profile photo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון בסיס הנתונים']);
    exit;
}
