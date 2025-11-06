<?php
/**
 * Check and Create Missing Tables
 * This ensures all required tables exist
 */

require_once __DIR__ . '/config.php';

echo "Checking for missing tables...\n\n";

try {
    $db = getDbConnection();
    $tablesCreated = 0;

    // 1. Check task_submissions table
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='task_submissions'");
    if (!$result->fetch()) {
        echo "Creating task_submissions table...\n";
        $db->exec("
            CREATE TABLE task_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_task_id INTEGER NOT NULL,
                filename TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                filepath TEXT NOT NULL,
                filesize INTEGER NOT NULL,
                mime_type TEXT NOT NULL,
                description TEXT,
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE
            )
        ");
        echo "✓ task_submissions table created\n";
        $tablesCreated++;
    } else {
        echo "✓ task_submissions table exists\n";
    }

    // 2. Check notifications table
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
    if (!$result->fetch()) {
        echo "Creating notifications table...\n";
        $db->exec("
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                notification_type TEXT DEFAULT 'info',
                related_task_id INTEGER,
                related_user_task_id INTEGER,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (related_task_id) REFERENCES course_tasks(id) ON DELETE SET NULL,
                FOREIGN KEY (related_user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE
            )
        ");
        echo "✓ notifications table created\n";
        $tablesCreated++;
    } else {
        echo "✓ notifications table exists\n";
    }

    // 3. Check task_comments table
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='task_comments'");
    if (!$result->fetch()) {
        echo "Creating task_comments table...\n";
        $db->exec("
            CREATE TABLE task_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_task_id INTEGER NOT NULL,
                author_id INTEGER NOT NULL,
                author_type TEXT NOT NULL,
                comment_text TEXT NOT NULL,
                is_internal INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE
            )
        ");
        echo "✓ task_comments table created\n";
        $tablesCreated++;
    } else {
        echo "✓ task_comments table exists\n";
    }

    // 4. Create indexes
    echo "\nCreating indexes...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_task_submissions_user_task ON task_submissions(user_task_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_task_comments_user_task ON task_comments(user_task_id)");
    echo "✓ Indexes created\n";

    echo "\n" . str_repeat("=", 50) . "\n";
    if ($tablesCreated > 0) {
        echo "✓ Created $tablesCreated missing table(s)\n";
    } else {
        echo "✓ All tables already exist\n";
    }
    echo "Database is ready!\n";
    echo str_repeat("=", 50) . "\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
