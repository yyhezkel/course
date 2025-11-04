<?php
/**
 * Migration Script: Course Management System
 *
 * Adds tables for:
 * - Course tasks (task templates)
 * - User task assignments
 * - Task progress tracking
 * - Course materials
 * - Task dependencies
 * - Notifications
 */

require_once __DIR__ . '/config.php';

try {
    $db = new PDO("sqlite:" . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting Course Management System migration...\n\n";

    // 1. Course Tasks Table - Task templates that can be assigned to users
    echo "Creating course_tasks table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS course_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            instructions TEXT,
            task_type TEXT DEFAULT 'assignment',
            form_id INTEGER,
            estimated_duration INTEGER,
            points INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            sequence_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
        )
    ");

    // 2. User Tasks Table - Assigns tasks to specific users
    echo "Creating user_tasks table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            task_id INTEGER NOT NULL,
            assigned_by INTEGER,
            assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            due_date DATETIME,
            status TEXT DEFAULT 'pending',
            priority TEXT DEFAULT 'normal',
            admin_notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES course_tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            UNIQUE(user_id, task_id)
        )
    ");

    // 3. Task Progress Table - Detailed tracking of task completion
    echo "Creating task_progress table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS task_progress (
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
            FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
        )
    ");

    // 4. Course Materials Table - Files and resources for tasks
    echo "Creating course_materials table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS course_materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            material_type TEXT NOT NULL,
            file_path TEXT,
            external_url TEXT,
            content_text TEXT,
            display_order INTEGER DEFAULT 0,
            is_required INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            FOREIGN KEY (task_id) REFERENCES course_tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
        )
    ");

    // 5. Task Dependencies Table - Prerequisites between tasks
    echo "Creating task_dependencies table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS task_dependencies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            depends_on_task_id INTEGER NOT NULL,
            dependency_type TEXT DEFAULT 'required',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES course_tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (depends_on_task_id) REFERENCES course_tasks(id) ON DELETE CASCADE,
            UNIQUE(task_id, depends_on_task_id)
        )
    ");

    // 6. Notifications Table - User notifications for new tasks, deadlines, etc.
    echo "Creating notifications table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
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

    // 7. Task Comments Table - Communication between admin and user about tasks
    echo "Creating task_comments table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS task_comments (
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

    // Create indexes for better performance
    echo "Creating indexes...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_tasks_user ON user_tasks(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_tasks_task ON user_tasks(task_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_tasks_status ON user_tasks(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_task_progress_user_task ON task_progress(user_task_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_materials_task ON course_materials(task_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_task_comments_user_task ON task_comments(user_task_id)");

    // Insert some sample course tasks
    echo "\nInserting sample course tasks...\n";
    $db->exec("
        INSERT OR IGNORE INTO course_tasks (id, title, description, instructions, task_type, estimated_duration, points, sequence_order) VALUES
        (1, 'מילוי טופס קבלה', 'מילוי טופס הקבלה הראשוני לקורס', 'אנא מלא את כל השדות הנדרשים בטופס. ודא שכל המידע נכון ומדויק.', 'form', 30, 10, 1),
        (2, 'קריאת חומר הכנה', 'קריאת חומרי ההכנה לקורס', 'קרא את כל חומרי ההכנה המצורפים. זה חשוב להבנת הבסיס של הקורס.', 'reading', 60, 5, 2),
        (3, 'משימה ראשונה', 'השלמת המשימה הראשונה בקורס', 'בצע את המשימה לפי ההוראות המפורטות בחומרים.', 'assignment', 120, 20, 3)
    ");

    // Insert sample materials
    echo "Inserting sample course materials...\n";
    $db->exec("
        INSERT OR IGNORE INTO course_materials (task_id, title, description, material_type, content_text, display_order, is_required) VALUES
        (2, 'מבוא לקורס', 'חומר הכנה בסיסי', 'text', 'זהו חומר ההכנה הבסיסי לקורס. אנא קרא בעיון.', 1, 1),
        (2, 'וידאו הדרכה', 'סרטון הדרכה ראשוני', 'video', 'https://example.com/video1', 2, 0)
    ");

    echo "\n✓ Migration completed successfully!\n\n";
    echo "Database schema updated with:\n";
    echo "- course_tasks (task templates)\n";
    echo "- user_tasks (task assignments)\n";
    echo "- task_progress (progress tracking)\n";
    echo "- course_materials (resources)\n";
    echo "- task_dependencies (prerequisites)\n";
    echo "- notifications (user notifications)\n";
    echo "- task_comments (communication)\n";
    echo "\nSample tasks and materials have been added.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
