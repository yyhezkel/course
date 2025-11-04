<?php
// הגדרות בסיס: DB, אבטחה, וכמות ניסיונות
define('DB_PATH', __DIR__ . '/form_data.db'); // נתיב לקובץ ה-SQLite
define('MAX_LOGIN_ATTEMPTS', 5);
define('API_KEY', 'your-secure-api-key-here'); // מפתח API מאובטח - החלף במפתח שלך

/**
 * פונקציה ליצירת חיבור PDO ל-SQLite
 * @return PDO
 */
function getDbConnection() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // מפעיל מפתחות זרים (ForeignKey) ב-SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
        return $pdo;
    } catch (PDOException $e) {
        // שגיאה קריטית בחיבור ל-DB
        die("Fatal DB Connection Error: " . $e->getMessage());
    }
}

/**
 * בדיקת API Key ב-Header של הבקשה
 * @return bool
 */
function authenticateApiKey() {
    $headers = getallheaders();
    $apiKey = $headers['X-Api-Key'] ?? null; // מחפש את המפתח ב-Header

    if ($apiKey !== API_KEY) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'API Key חסר או לא תקין.']);
        return false;
    }
    return true;
}

?>
