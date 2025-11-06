<?php
/**
 * Database Migration Script
 * Adds admin management tables to existing database
 * Preserves all existing data
 */

require_once __DIR__ . '/../config.php';

echo "=== Database Migration Started ===\n\n";

$db = getDbConnection();
$db->exec('PRAGMA foreign_keys = ON;');

// Track migration steps
$steps = [];
$errors = [];

function logStep($message, $success = true) {
    global $steps, $errors;
    echo ($success ? "✓" : "✗") . " $message\n";
    if ($success) {
        $steps[] = $message;
    } else {
        $errors[] = $message;
    }
}

try {
    // ============================================
    // STEP 1: Enhance existing users table
    // ============================================
    echo "\n--- Step 1: Enhancing users table ---\n";

    // Check if columns already exist
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'name');

    $newColumns = [
        'full_name' => 'TEXT',
        'email' => 'TEXT',
        'phone' => 'TEXT',
        'role' => "TEXT DEFAULT 'user'",
        'created_at' => 'TEXT',
        'updated_at' => 'TEXT'
    ];

    foreach ($newColumns as $colName => $colType) {
        if (!in_array($colName, $existingColumns)) {
            $db->exec("ALTER TABLE users ADD COLUMN $colName $colType");
            logStep("Added column '$colName' to users table");
        } else {
            logStep("Column '$colName' already exists in users table");
        }
    }

    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_tz ON users(tz)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_is_blocked ON users(is_blocked)");
    logStep("Created indexes on users table");

    // ============================================
    // STEP 2: Create forms table
    // ============================================
    echo "\n--- Step 2: Creating forms table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS forms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            is_active INTEGER DEFAULT 1,
            allow_multiple_submissions INTEGER DEFAULT 0,
            show_progress INTEGER DEFAULT 1,
            created_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");
    logStep("Created forms table");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_forms_is_active ON forms(is_active)");
    logStep("Created indexes on forms table");

    // ============================================
    // STEP 3: Create question_types table
    // ============================================
    echo "\n--- Step 3: Creating question_types table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS question_types (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type_code TEXT UNIQUE NOT NULL,
            type_name TEXT NOT NULL,
            validation_rules TEXT,
            html_input_type TEXT,
            description TEXT
        )
    ");
    logStep("Created question_types table");

    // Populate with default types
    $questionTypes = [
        ['text', 'טקסט חופשי', 'text', 'שדה טקסט פתוח'],
        ['text_short', 'טקסט קצר', 'text', 'טקסט מוגבל ל-100 תווים'],
        ['textarea', 'טקסט ארוך', 'textarea', 'שדה טקסט רב שורות'],
        ['number', 'מספר', 'number', 'שדה מספרי'],
        ['number_range', 'מספר בטווח', 'number', 'מספר עם min/max'],
        ['email', 'דוא"ל', 'email', 'כתובת אימייל'],
        ['phone', 'טלפון', 'tel', 'מספר טלפון'],
        ['tz', 'תעודת זהות', 'tel', 'מספר ת.ז 9 ספרות'],
        ['date', 'תאריך', 'date', 'בחירת תאריך'],
        ['select', 'בחירה מרשימה', 'select', 'תפריט נפתח'],
        ['radio', 'בחירה יחידה', 'radio', 'רדיו כפתורים'],
        ['checkbox', 'בחירה מרובה', 'checkbox', 'תיבות סימון'],
        ['rating', 'דירוג', 'number', 'דירוג 1-5'],
        ['yes_no', 'כן/לא', 'radio', 'שאלת כן/לא']
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO question_types (type_code, type_name, html_input_type, description) VALUES (?, ?, ?, ?)");
    foreach ($questionTypes as $type) {
        $stmt->execute($type);
    }
    logStep("Populated question_types with " . count($questionTypes) . " types");

    // ============================================
    // STEP 4: Create questions table
    // ============================================
    echo "\n--- Step 4: Creating questions table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question_text TEXT NOT NULL,
            question_type_id INTEGER NOT NULL,
            placeholder TEXT,
            help_text TEXT,
            is_required INTEGER DEFAULT 1,
            validation_rules TEXT,
            options TEXT,
            default_value TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (question_type_id) REFERENCES question_types(id)
        )
    ");
    logStep("Created questions table");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_questions_type ON questions(question_type_id)");
    logStep("Created indexes on questions table");

    // ============================================
    // STEP 5: Create form_questions table
    // ============================================
    echo "\n--- Step 5: Creating form_questions table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS form_questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_id INTEGER NOT NULL,
            question_id INTEGER NOT NULL,
            sequence_order INTEGER NOT NULL,
            is_active INTEGER DEFAULT 1,
            section_title TEXT,
            conditional_logic TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
            UNIQUE(form_id, question_id)
        )
    ");
    logStep("Created form_questions table");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_questions_form ON form_questions(form_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_questions_sequence ON form_questions(form_id, sequence_order)");
    logStep("Created indexes on form_questions table");

    // ============================================
    // STEP 6: Create user_forms table
    // ============================================
    echo "\n--- Step 6: Creating user_forms table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_forms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            form_id INTEGER NOT NULL,
            assigned_by INTEGER,
            assigned_at TEXT DEFAULT CURRENT_TIMESTAMP,
            due_date TEXT,
            status TEXT DEFAULT 'pending',
            started_at TEXT,
            completed_at TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id),
            UNIQUE(user_id, form_id)
        )
    ");
    logStep("Created user_forms table");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_forms_user ON user_forms(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_forms_status ON user_forms(status)");
    logStep("Created indexes on user_forms table");

    // ============================================
    // STEP 7: Enhance form_responses table
    // ============================================
    echo "\n--- Step 7: Enhancing form_responses table ---\n";

    // Check if form_id column exists
    $columns = $db->query("PRAGMA table_info(form_responses)")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'name');

    if (!in_array('form_id', $existingColumns)) {
        $db->exec("ALTER TABLE form_responses ADD COLUMN form_id INTEGER DEFAULT 1");
        logStep("Added form_id column to form_responses");
    } else {
        logStep("form_id column already exists in form_responses");
    }

    if (!in_array('answer_json', $existingColumns)) {
        $db->exec("ALTER TABLE form_responses ADD COLUMN answer_json TEXT");
        logStep("Added answer_json column to form_responses");
    } else {
        logStep("answer_json column already exists in form_responses");
    }

    if (!in_array('updated_at', $existingColumns)) {
        $db->exec("ALTER TABLE form_responses ADD COLUMN updated_at TEXT");
        logStep("Added updated_at column to form_responses");
    } else {
        logStep("updated_at column already exists in form_responses");
    }

    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_responses_user ON form_responses(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_responses_form ON form_responses(form_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_responses_user_form ON form_responses(user_id, form_id)");
    logStep("Created indexes on form_responses table");

    // ============================================
    // STEP 8: Create admin_users table
    // ============================================
    echo "\n--- Step 8: Creating admin_users table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            full_name TEXT,
            role TEXT DEFAULT 'admin',
            is_active INTEGER DEFAULT 1,
            last_login TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    logStep("Created admin_users table");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_username ON admin_users(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_email ON admin_users(email)");
    logStep("Created indexes on admin_users table");

    // Create default admin user (password: admin123 - CHANGE THIS!)
    $defaultPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $db->exec("INSERT OR IGNORE INTO admin_users (username, password_hash, email, full_name, role)
               VALUES ('admin', '$defaultPassword', 'admin@example.com', 'System Administrator', 'super_admin')");
    logStep("Created default admin user (username: admin, password: admin123)");

    // ============================================
    // STEP 9: Create activity_log table
    // ============================================
    echo "\n--- Step 9: Creating activity_log table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id INTEGER,
            old_value TEXT,
            new_value TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
        )
    ");
    logStep("Created activity_log table");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_admin ON activity_log(admin_user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_date ON activity_log(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_entity ON activity_log(entity_type, entity_id)");
    logStep("Created indexes on activity_log table");

    // ============================================
    // STEP 10: Create failed_login_attempts table
    // ============================================
    echo "\n--- Step 10: Creating failed_login_attempts table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS failed_login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            attempt_time TEXT DEFAULT CURRENT_TIMESTAMP,
            user_agent TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    logStep("Created failed_login_attempts table");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_failed_login_username ON failed_login_attempts(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_failed_login_ip ON failed_login_attempts(ip_address)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_failed_login_time ON failed_login_attempts(attempt_time)");
    logStep("Created indexes on failed_login_attempts table");

    // ============================================
    // STEP 11: Create default form
    // ============================================
    echo "\n--- Step 11: Creating default form ---\n";

    // Check if a form already exists
    $formCount = $db->query("SELECT COUNT(*) FROM forms")->fetchColumn();
    if ($formCount == 0) {
        $db->exec("INSERT INTO forms (title, description, is_active)
                   VALUES ('טופס דיגיטלי ברירת מחדל', 'טופס ראשי למילוי פרטים', 1)");
        logStep("Created default form");
    } else {
        logStep("Forms already exist, skipping default form creation");
    }

    // ============================================
    // MIGRATION COMPLETE
    // ============================================
    echo "\n=== Migration Completed Successfully ===\n\n";

    echo "Summary:\n";
    echo "  ✓ " . count($steps) . " steps completed\n";
    if (count($errors) > 0) {
        echo "  ✗ " . count($errors) . " errors occurred\n";
    }

    echo "\n--- Database Tables ---\n";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if ($table !== 'sqlite_sequence') {
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "  • $table: $count rows\n";
        }
    }

    echo "\n--- Default Admin Credentials ---\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "  ⚠️  CHANGE THIS PASSWORD IMMEDIATELY!\n";

    echo "\n--- Next Steps ---\n";
    echo "  1. Change default admin password\n";
    echo "  2. Import questions from questions.js (if needed)\n";
    echo "  3. Build admin panel UI\n";
    echo "  4. Test the system\n";

} catch (Exception $e) {
    echo "\n✗ MIGRATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
    exit(1);
}
?>
