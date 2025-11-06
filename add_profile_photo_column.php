<?php
/**
 * Migration: Add profile_photo field to users table
 * Stores URL of user's profile photo
 */

require_once 'config.php';

function logStep($message, $success = true) {
    echo ($success ? "✓" : "✗") . " $message\n";
}

function addProfilePhotoField($db) {
    try {
        // Check existing columns
        $result = $db->query("PRAGMA table_info(users)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'name');

        if (!in_array('profile_photo', $existingColumns)) {
            $db->exec("ALTER TABLE users ADD COLUMN profile_photo TEXT");
            logStep("Added column 'profile_photo' to users table");
        } else {
            logStep("Column 'profile_photo' already exists in users table");
        }

        echo "\n✅ Successfully enhanced users table with profile photo support.\n";
        echo "ℹ️  Users can now:\n";
        echo "   - Upload a profile photo via profile settings\n";
        echo "   - View their photo on the profile page\n";
        echo "   - Update or remove their photo at any time\n";

    } catch (Exception $e) {
        echo "❌ Error enhancing users table: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Run migration
try {
    $db = getDbConnection();

    echo "=== Adding Profile Photo Field to Users Table ===\n\n";
    addProfilePhotoField($db);
    echo "\n=== Migration completed successfully! ===\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
