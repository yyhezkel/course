<?php
/**
 * Migration: Add review fields to user_tasks table
 *
 * Adds the following fields:
 * - grade (0-100 score)
 * - feedback (review text/explanation)
 * - submitted_at (when user submitted the task)
 * - reviewed_at (when admin reviewed the task)
 * - submission_text (text submission)
 * - submission_file_path (file submission path)
 */

require_once __DIR__ . '/../config.php';

try {
    $db = getDbConnection();

    echo "Starting migration: Add review fields to user_tasks table...\n\n";

    // Check which columns need to be added
    $columnsToAdd = [
        'grade' => 'REAL DEFAULT NULL',
        'feedback' => 'TEXT DEFAULT NULL',
        'submitted_at' => 'DATETIME DEFAULT NULL',
        'reviewed_at' => 'DATETIME DEFAULT NULL',
        'submission_text' => 'TEXT DEFAULT NULL',
        'submission_file_path' => 'TEXT DEFAULT NULL'
    ];

    // Get existing columns
    $stmt = $db->query("PRAGMA table_info(user_tasks)");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['name'];
    }

    // Add missing columns
    $addedCount = 0;
    foreach ($columnsToAdd as $columnName => $columnDef) {
        if (!in_array($columnName, $existingColumns)) {
            echo "Adding column '$columnName'...\n";
            $db->exec("ALTER TABLE user_tasks ADD COLUMN $columnName $columnDef");
            $addedCount++;
            echo "✓ Column '$columnName' added successfully\n";
        } else {
            echo "✓ Column '$columnName' already exists\n";
        }
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    if ($addedCount > 0) {
        echo "✓ Migration completed! Added $addedCount column(s)\n";
    } else {
        echo "✓ All columns already exist. No changes needed.\n";
    }
    echo str_repeat("=", 50) . "\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
