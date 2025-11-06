<?php
/**
 * Diagnostic script to check profile photo setup
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Profile Photo Diagnostic Tool ===\n\n";

// Check if config.php exists
if (!file_exists(__DIR__ . '/config.php')) {
    echo "❌ ERROR: config.php not found!\n";
    echo "   You need to create config.php from config.example.php\n";
    exit(1);
}

require_once 'config.php';

echo "✓ config.php found\n";

// Check database connection
try {
    $db = getDbConnection();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if users table exists
try {
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($result->fetch()) {
        echo "✓ users table exists\n";
    } else {
        echo "❌ users table does not exist\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Error checking users table: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if profile_photo column exists
try {
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $hasProfilePhoto = false;

    echo "\nCurrent users table columns:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['name'] . " (" . $column['type'] . ")\n";
        if ($column['name'] === 'profile_photo') {
            $hasProfilePhoto = true;
        }
    }

    if ($hasProfilePhoto) {
        echo "\n✓ profile_photo column exists\n";
    } else {
        echo "\n❌ profile_photo column DOES NOT exist\n";
        echo "   Run this command to add it: php add_profile_photo_column.php\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking columns: " . $e->getMessage() . "\n";
    exit(1);
}

// Check upload directory
$uploadDir = '/www/wwwroot/qr.bot4wa.com/files/kodkod-uplodes/profile-photos/';
echo "\nChecking upload directory: $uploadDir\n";

if (file_exists($uploadDir)) {
    echo "✓ Upload directory exists\n";

    if (is_writable($uploadDir)) {
        echo "✓ Upload directory is writable\n";
    } else {
        echo "❌ Upload directory is NOT writable\n";
        echo "   Run: chmod 755 $uploadDir\n";
    }
} else {
    echo "❌ Upload directory does not exist\n";
    echo "   Run: mkdir -p $uploadDir && chmod 755 $uploadDir\n";
}

// Check session functionality
echo "\nChecking PHP session:\n";
if (function_exists('session_start')) {
    echo "✓ Session functions available\n";
} else {
    echo "❌ Session functions not available\n";
}

// Check file upload settings
echo "\nPHP upload settings:\n";
echo "  - upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "  - post_max_size: " . ini_get('post_max_size') . "\n";
echo "  - max_file_uploads: " . ini_get('max_file_uploads') . "\n";

echo "\n=== Diagnostic Complete ===\n";
?>
