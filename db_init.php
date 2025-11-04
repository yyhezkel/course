<?php
require_once 'config.php';

// הפעלת סקריפט זה מריץ את יצירת הטבלאות
$db = getDbConnection();

function createTables(PDO $db) {
    // יצירת טבלת המשתמשים
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tz TEXT UNIQUE NOT NULL,
            is_blocked INTEGER DEFAULT 0,
            failed_attempts INTEGER DEFAULT 0,
            ip_address TEXT,
            last_login TEXT,
            tz_hash TEXT
        )
    ");

    // יצירת טבלת שאלות
    $db->exec("
        CREATE TABLE IF NOT EXISTS questions (
            id TEXT PRIMARY KEY,
            question_text TEXT NOT NULL,
            question_type TEXT NOT NULL,
            options TEXT,
            is_required INTEGER DEFAULT 0,
            sort_order INTEGER DEFAULT 0,
            category TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // יצירת טבלת תשובות לטופס
    $db->exec("
        CREATE TABLE IF NOT EXISTS form_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            question_id TEXT NOT NULL,
            answer_value TEXT,
            submitted_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (question_id) REFERENCES questions(id),
            UNIQUE(user_id, question_id)
        )
    ");

    echo "טבלאות נוצרו בהצלחה ב-DB.\n";

    // דוגמה להכנסת משתמש ראשוני לצורך בדיקה (ת"ז: 123456789)
    $stmt = $db->prepare("INSERT OR IGNORE INTO users (tz) VALUES (?)");
    $stmt->execute(['123456789']);
    echo "משתמש דוגמה (123456789) נוסף אם לא קיים.\n";
}

createTables($db);
?>