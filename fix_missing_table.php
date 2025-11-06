<?php
/**
 * Create Missing task_progress Table
 * Run this once to fix the 500 error
 *
 * Usage: php fix_missing_table.php
 */

require_once __DIR__ . '/config.php';

echo "Creating task_progress table...\n";

try {
    $db = getDbConnection();

    // Check if table already exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='task_progress'");
    if ($result->fetch()) {
        echo "✓ task_progress table already exists!\n";
        exit(0);
    }

    // Create the table
    $db->exec("
        CREATE TABLE task_progress (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_task_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            progress_percentage INTEGER DEFAULT 0,
            started_at DATETIME,
            completed_at DATETIME,
            reviewed_at DATETIME,
            reviewed_by INTEGER,
            review_notes TEXT,
            submission_data TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE,
            UNIQUE(user_task_id)
        )
    ");

    echo "✓ task_progress table created successfully!\n";
    echo "\nYou can now test the task loading:\n";
    echo "Visit: https://qr.bot4wa.com/kodkod/task.html?id=1\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
