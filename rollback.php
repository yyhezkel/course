<?php
/**
 * Rollback Script
 * Use this to quickly restore the original api.php if issues occur
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

$backupFile = __DIR__ . '/api.php.backup';
$currentFile = __DIR__ . '/api.php';

if (!file_exists($backupFile)) {
    die("ERROR: Backup file not found at: $backupFile\n");
}

// Create a backup of the new api.php before rolling back
$newBackup = __DIR__ . '/api.php.new';
if (file_exists($currentFile)) {
    copy($currentFile, $newBackup);
    echo "Created backup of current api.php as api.php.new\n";
}

// Restore the original
copy($backupFile, $currentFile);
echo "SUCCESS: Rolled back to original api.php\n";
echo "The new version is saved as api.php.new if you need to compare or restore it later.\n";
?>
