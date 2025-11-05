<?php
// התחלת Session לאימות מבוסס-Session
session_start();

require_once 'config.php';

header('Content-Type: application/json');
// הגדרות CORS - רק עבור הדומיין הזה (מאובטח יותר מ-*)
$allowed_origin = 'https://qr.bot4wa.com';
header("Access-Control-Allow-Origin: $allowed_origin");
header('Access-Control-Allow-Credentials: true'); // אפשר שליחת Cookies
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
// כותרות אבטחה נוספות
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// אם זו בקשת OPTIONS (Preflight), מחזירים תשובה מידית
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// קבלת נתוני POST (מכיוון שהם נשלחים כ-JSON מה-JS)
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$db = getDbConnection();
$userIP = $_SERVER['REMOTE_ADDR'];

// Initialize course tables if they don't exist
function initializeCourseTables($db) {
    try {
        // Check if course_tasks table exists
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='course_tasks'");
        if (!$result->fetch()) {
            // Create course_tasks table
            $db->exec("
                CREATE TABLE course_tasks (
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
                    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE SET NULL
                )
            ");

            // Create user_tasks table
            $db->exec("
                CREATE TABLE user_tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    task_id INTEGER NOT NULL,
                    assigned_by INTEGER,
                    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    due_date DATETIME,
                    status TEXT DEFAULT 'pending',
                    priority TEXT DEFAULT 'normal',
                    progress_percentage INTEGER DEFAULT 0,
                    started_at DATETIME,
                    completed_at DATETIME,
                    submitted_at DATETIME,
                    reviewed_at DATETIME,
                    reviewed_by INTEGER,
                    admin_notes TEXT,
                    student_notes TEXT,
                    submission_text TEXT,
                    submission_file_path TEXT,
                    grade REAL,
                    feedback TEXT,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (task_id) REFERENCES course_tasks(id) ON DELETE CASCADE,
                    UNIQUE(user_id, task_id)
                )
            ");

            // Create course_materials table
            $db->exec("
                CREATE TABLE course_materials (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    task_id INTEGER,
                    title TEXT NOT NULL,
                    description TEXT,
                    material_type TEXT DEFAULT 'document',
                    file_path TEXT,
                    external_url TEXT,
                    content_text TEXT,
                    display_order INTEGER DEFAULT 0,
                    is_required INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_by INTEGER,
                    FOREIGN KEY (task_id) REFERENCES course_tasks(id) ON DELETE CASCADE
                )
            ");
        }
    } catch (Exception $e) {
        // Tables might already exist, ignore error
    }
}

// Initialize username/password fields if they don't exist
function ensureUserAuthFields($db) {
    try {
        $result = $db->query("PRAGMA table_info(users)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'name');

        // Add username field (without UNIQUE - that will be handled by index)
        if (!in_array('username', $existingColumns)) {
            $db->exec("ALTER TABLE users ADD COLUMN username TEXT");
        }

        // Add password_hash field
        if (!in_array('password_hash', $existingColumns)) {
            $db->exec("ALTER TABLE users ADD COLUMN password_hash TEXT");
        }

        // Create unique index for username lookups
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username)");
    } catch (Exception $e) {
        // Fields might already exist, ignore error silently
        error_log("ensureUserAuthFields error: " . $e->getMessage());
    }
}

// Initialize auth fields on any request
ensureUserAuthFields($db);

// Initialize tables on dashboard or task-related requests
if ($action === 'get_dashboard' || strpos($action, 'task') !== false || strpos($action, 'material') !== false) {
    initializeCourseTables($db);
}

// הסרנו את בדיקת המכשיר הנייד - המערכת פתוחה לכולם
// העיצוב מותאם למובייל אבל ניתן לגשת גם ממחשב


// === 1. טיפול באימות כניסה (LOGIN) ===
if ($action === 'login') {
    $tz = $input['tz'] ?? '';
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    $user = null;

    // METHOD 1: Login with username + password
    if (!empty($username) && !empty($password)) {
        // Get user by username
        $stmt = $db->prepare("SELECT id, is_blocked, failed_attempts, id_type, password_hash, tz FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password
        if ($user && !empty($user['password_hash'])) {
            if (!password_verify($password, $user['password_hash'])) {
                // Wrong password
                $user = null;
            }
        } else {
            // No password set for this user
            $user = null;
        }

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'שם משתמש או סיסמה שגויים.']);
            exit;
        }

        // Use tz from database for session
        $tz = $user['tz'];
    }
    // METHOD 2: Login with personal number (tz) only - legacy method
    else if (!empty($tz)) {
        // Validate that ID is numeric and has correct length (7 or 9 digits)
        if (!is_numeric($tz)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מספר זהוי לא תקין.']);
            exit;
        }

        $idLength = strlen($tz);
        if ($idLength !== 7 && $idLength !== 9) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מספר זהוי חייב להכיל 7 או 9 ספרות.']);
            exit;
        }

        // Get user by tz
        $stmt = $db->prepare("SELECT id, is_blocked, failed_attempts, id_type FROM users WHERE tz = ?");
        $stmt->execute([$tz]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validate that the ID matches the expected type
        if ($user) {
            $expectedLength = ($user['id_type'] ?? 'tz') === 'tz' ? 9 : 7;
            if ($idLength !== $expectedLength) {
                http_response_code(400);
                $expectedType = $expectedLength === 9 ? 'תעודת זהות (9 ספרות)' : 'מספר אישי (7 ספרות)';
                echo json_encode(['success' => false, 'message' => "מספר זה רשום כ-$expectedType"]);
                exit;
            }
        }
    }
    // No valid login credentials provided
    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'נא למלא מספר זהוי או שם משתמש וסיסמה.']);
        exit;
    }

    if ($user) {
        if ($user['is_blocked']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'הכניסה חסומה עבורך.']);
            exit;
        }

        // אימות מוצלח!
        // 2. איפוס ניסיונות כושלים ועדכון זמן
        $db->prepare("UPDATE users SET failed_attempts = 0, last_login = ?, ip_address = ? WHERE id = ?")
           ->execute([date('Y-m-d H:i:s'), $userIP, $user['id']]);

        // 3. Get assigned form for user
        $stmt = $db->prepare("SELECT form_id FROM user_forms WHERE user_id = ? AND status = 'assigned' LIMIT 1");
        $stmt->execute([$user['id']]);
        $assignedForm = $stmt->fetch(PDO::FETCH_ASSOC);
        $formId = $assignedForm ? $assignedForm['form_id'] : 1; // Default to form 1 if no assignment

        // 4. יצירת Session למשתמש
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_tz'] = $tz;
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['form_id'] = $formId; // Store assigned form in session

        // 5. שליפת נתונים קודמים - גם answer_value וגם answer_json
        $stmt = $db->prepare("SELECT question_id, answer_value, answer_json FROM form_responses WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // מיזוג התשובות - מעדיף answer_json אם קיים, אחרת answer_value
        $previousData = [];
        foreach ($responses as $response) {
            $questionId = $response['question_id'];
            if (!empty($response['answer_json'])) {
                // אם יש answer_json, משתמשים בו (כבר JSON)
                $previousData[$questionId] = $response['answer_json'];
            } else {
                // אחרת משתמשים ב-answer_value
                $previousData[$questionId] = $response['answer_value'];
            }
        }

        // שמירה ב-cache של הסשן
        $_SESSION['cached_answers'] = $previousData;

        // 6. חישוב השאלה הראשונה שאין לה תשובה
        // Check if form uses new structure format
        $stmt = $db->prepare("SELECT structure_json FROM forms WHERE id = ?");
        $stmt->execute([$formId]);
        $formData = $stmt->fetch(PDO::FETCH_ASSOC);

        $firstUnansweredIndex = 0;

        if ($formData && !empty($formData['structure_json'])) {
            // New format: use structure_json
            $structure = json_decode($formData['structure_json'], true);
            $steps = flattenStructureToSteps($structure);

            // Find first unanswered question step (skip text blocks)
            foreach ($steps as $index => $step) {
                if ($step['blockType'] === 'text') {
                    // Skip text blocks - they're not questions
                    continue;
                }

                if ($step['blockType'] === 'question') {
                    // Check if this question has an answer
                    if (!isset($previousData[$step['id']]) || $previousData[$step['id']] === '') {
                        $firstUnansweredIndex = $index;
                        break;
                    }
                }

                // If all questions answered, stay on last step
                if ($index === count($steps) - 1) {
                    $firstUnansweredIndex = $index;
                }
            }
        } else {
            // Old format: use questions table
            // מסודר לפי קטגוריה ואז לפי סדר השאלה
            // For Form 2, only use categorized questions
            $stmt = $db->prepare("
                SELECT q.id
                FROM questions q
                JOIN form_questions fq ON q.id = fq.question_id
                LEFT JOIN categories c ON fq.category_id = c.id
                WHERE fq.form_id = ? AND fq.is_active = 1
                    AND (fq.form_id != 2 OR fq.category_id IS NOT NULL)
                ORDER BY
                    COALESCE(c.sequence_order, 999) ASC,
                    fq.sequence_order ASC
            ");
            $stmt->execute([$formId]);
            $allQuestionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allQuestionIds as $index => $questionId) {
                if (!isset($previousData[$questionId]) || $previousData[$questionId] === '') {
                    $firstUnansweredIndex = $index;
                    break;
                }
                // אם כל השאלות נענו, נשאר על האחרונה
                if ($index === count($allQuestionIds) - 1) {
                    $firstUnansweredIndex = $index;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'redirect_to' => 'dashboard.html', // Redirect to dashboard instead of form
            'message' => 'כניסה מאושרת.'
        ]);
        exit;

    } else {
        // 4. משתמש לא קיים במערכת
        // בטחון: לא חושפים אם הת.ז. קיימת או לא, מחזירים הודעה זהה
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'ת.ז. או סיסמה שגויים.']);
        exit;
    }
}


// === 2. טיפול בשמירת הטופס (SUBMIT) ===
if ($action === 'submit') {
    // 1. בדיקת אימות מבוסס-Session
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות. אנא התחבר מחדש.']);
        exit;
    }

    // 2. בדיקת Session Timeout (30 דקות = 1800 שניות)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
        $_SESSION['authenticated'] = false;
        http_response_code(401);
        echo json_encode(['success' => false, 'session_expired' => true, 'message' => 'פג תוקף ההתחברות. אנא התחבר מחדש להמשך.']);
        exit;
    }

    // Refresh session timeout
    $_SESSION['login_time'] = time();

    $userId = $_SESSION['user_id']; // משתמש מה-Session
    $formId = $_SESSION['form_id'] ?? 1; // טופס מה-Session
    $formData = $input['form_data'] ?? [];

    if (empty($formData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסרים פרטים לשליחה.']);
        exit;
    }

    $db->beginTransaction();
    $success = true;

    // קבלת סוגי השאלות כדי לדעת איך לשמור את התשובות
    $questionIds = array_keys($formData);
    $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
    $stmt = $db->prepare("SELECT q.id, qt.type_code FROM questions q JOIN question_types qt ON q.question_type_id = qt.id WHERE q.id IN ($placeholders)");
    $stmt->execute($questionIds);
    $questionTypes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [question_id => type_code]

    foreach ($formData as $questionId => $answerValue) {
        try {
            $questionType = $questionTypes[$questionId] ?? 'text';

            // בדיקה אם זה checkbox (מערך) או ערך פשוט
            if ($questionType === 'checkbox') {
                // checkbox - שמירה ב-answer_json
                $jsonValue = is_array($answerValue) ? json_encode($answerValue, JSON_UNESCAPED_UNICODE) : $answerValue;
                $upsertSql = "
                    INSERT INTO form_responses (user_id, question_id, answer_json, form_id, updated_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT(user_id, question_id, form_id)
                    DO UPDATE SET answer_json = excluded.answer_json, answer_value = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP;
                ";
                $stmt = $db->prepare($upsertSql);
                $stmt->execute([$userId, $questionId, $jsonValue, $formId]);
            } else {
                // סוג רגיל - שמירה ב-answer_value
                $upsertSql = "
                    INSERT INTO form_responses (user_id, question_id, answer_value, form_id, updated_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT(user_id, question_id, form_id)
                    DO UPDATE SET answer_value = excluded.answer_value, answer_json = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP;
                ";
                $stmt = $db->prepare($upsertSql);
                $stmt->execute([$userId, $questionId, (string)$answerValue, $formId]);
            }
        } catch (\Exception $e) {
            $success = false;
            error_log("Error saving answer for question $questionId: " . $e->getMessage());
            break;
        }
    }

    if ($success) {
        // Auto-populate full_name from first name and last name answers
        try {
            // Get first name and last name from form responses
            $firstName = $formData['personal_firstName'] ?? null;
            $lastName = $formData['personal_lastName'] ?? null;

            // If both names exist, update the user's full_name
            if ($firstName && $lastName) {
                $fullName = trim($firstName . ' ' . $lastName);
                $stmt = $db->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                $stmt->execute([$fullName, $userId]);
            }
        } catch (Exception $e) {
            // Don't fail the submission if name update fails
            error_log("Error updating full_name: " . $e->getMessage());
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'הטופס נשמר בהצלחה!']);
    } else {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בשמירת חלק מהנתונים ל-DB.']);
    }
    exit;
}

// === 3. טיפול בקבלת שאלות מהמאגר (GET_QUESTIONS) ===
if ($action === 'get_questions') {
    try {
        // Get form_id from session (set during login)
        $formId = $_SESSION['form_id'] ?? 1;

        // First, check if form has structure_json (new format with text/condition blocks)
        $stmt = $db->prepare("SELECT structure_json FROM forms WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($form && !empty($form['structure_json'])) {
            // New format: return structure with text and condition blocks
            $structure = json_decode($form['structure_json'], true);

            echo json_encode([
                'success' => true,
                'structure' => $structure, // Full block structure
                'questions' => [], // Empty for backwards compatibility
                'total' => 0,
                'use_structure' => true // Flag to tell frontend to use new structure
            ]);
            exit;
        }

        // Fallback: Load questions from old format
        // שאילתה לקבלת כל השאלות מהטופס המוקצה למשתמש
        // מסודר לפי קטגוריה ואז לפי סדר השאלה בתוך הקטגוריה
        // For Form 2, only load categorized questions (category_id IS NOT NULL)
        $stmt = $db->prepare("
            SELECT
                q.id,
                q.question_text,
                qt.type_code as type,
                q.options,
                q.is_required,
                fq.sequence_order,
                fq.section_title,
                c.id as category_id,
                c.name as category_name,
                c.color as category_color,
                c.sequence_order as category_order
            FROM questions q
            JOIN form_questions fq ON q.id = fq.question_id
            JOIN question_types qt ON q.question_type_id = qt.id
            LEFT JOIN categories c ON fq.category_id = c.id
            WHERE fq.form_id = ? AND fq.is_active = 1
                AND (fq.form_id != 2 OR fq.category_id IS NOT NULL)
            ORDER BY
                COALESCE(c.sequence_order, 999) ASC,
                fq.sequence_order ASC
        ");

        $stmt->execute([$formId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // המרת השאלות לפורמט הנכון עבור הפרונטאנד
        $formattedQuestions = [];
        foreach ($questions as $q) {
            $question = [
                'id' => (string)$q['id'], // ID כמחרוזת (תואם למה שנשמר ב-form_responses)
                'question' => $q['question_text'],
                'type' => mapQuestionType($q['type']),
                'required' => (bool)$q['is_required']
            ];

            // הוספת options אם קיימות
            if (!empty($q['options'])) {
                $options = json_decode($q['options'], true);
                if (is_array($options)) {
                    $question['options'] = $options;
                }
            }

            // הוספת פרטי קטגוריה
            if (!empty($q['category_id'])) {
                $question['category'] = [
                    'id' => $q['category_id'],
                    'name' => $q['category_name'],
                    'color' => $q['category_color']
                ];
            } elseif (!empty($q['section_title'])) {
                // fallback לשדות ישנים ללא קטגוריה
                $question['category'] = [
                    'id' => null,
                    'name' => $q['section_title'],
                    'color' => '#95A5A6' // צבע ברירת מחדל
                ];
            }

            $formattedQuestions[] = $question;
        }

        echo json_encode([
            'success' => true,
            'questions' => $formattedQuestions,
            'total' => count($formattedQuestions)
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'שגיאה בטעינת השאלות: ' . $e->getMessage()
        ]);
        exit;
    }
}

// פונקציית עזר להמרת סוגי שאלות
function mapQuestionType($dbType) {
    $typeMap = [
        'text' => 'text',
        'text_short' => 'text',
        'textarea' => 'textarea',
        'number' => 'number',
        'number_range' => 'number',
        'email' => 'text',
        'phone' => 'text',
        'tz' => 'text',
        'date' => 'text',
        'select' => 'select',
        'radio' => 'radio',
        'checkbox' => 'checkbox',
        'rating' => 'number',
        'yes_no' => 'radio'
    ];

    return $typeMap[$dbType] ?? 'text';
}

/**
 * Flattens block structure into sequential steps (mirrors frontend logic)
 * Returns array of steps with their types and IDs
 */
function flattenStructureToSteps($blocks) {
    $steps = [];
    $blockMap = [];

    // Create block map
    foreach ($blocks as $block) {
        $blockMap[$block['id']] = $block;
    }

    // Process a single block
    $processBlock = function($block, $sectionInfo = null) use (&$steps, &$blockMap, &$processBlock) {
        if ($block['type'] === 'section') {
            // Process children of section
            if (!empty($block['children'])) {
                foreach ($block['children'] as $childId) {
                    if (isset($blockMap[$childId])) {
                        $processBlock($blockMap[$childId], $sectionInfo);
                    }
                }
            }
        } elseif ($block['type'] === 'text') {
            // Text block - not a question, but counts as a step
            $steps[] = [
                'id' => $block['id'],
                'type' => 'text',
                'blockType' => 'text'
            ];
        } elseif ($block['type'] === 'question') {
            // Question block
            $steps[] = [
                'id' => $block['id'],
                'type' => 'question',
                'blockType' => 'question'
            ];
        } elseif ($block['type'] === 'condition') {
            // Process conditional children (they're still in the sequence)
            if (!empty($block['children'])) {
                foreach ($block['children'] as $childId) {
                    if (isset($blockMap[$childId])) {
                        $childBlock = $blockMap[$childId];
                        // Add conditional marker
                        if ($childBlock['type'] === 'text') {
                            $steps[] = [
                                'id' => $childBlock['id'],
                                'type' => 'text',
                                'blockType' => 'text',
                                'conditional' => true
                            ];
                        } elseif ($childBlock['type'] === 'question') {
                            $steps[] = [
                                'id' => $childBlock['id'],
                                'type' => 'question',
                                'blockType' => 'question',
                                'conditional' => true
                            ];
                        }
                    }
                }
            }
        }
    };

    // Process top-level blocks
    foreach ($blocks as $block) {
        if (!isset($block['parentId']) || $block['parentId'] === null) {
            $processBlock($block);
        }
    }

    return $steps;
}

// === 4. בדיקת סטטוס סשן (CHECK_SESSION) ===
if ($action === 'check_session') {
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        // בדיקת Session Timeout (30 דקות = 1800 שניות)
        $sessionAge = time() - ($_SESSION['login_time'] ?? time());

        if ($sessionAge > 1800) {
            // Session expired - don't destroy, just mark unauthenticated
            // User's progress is saved in database via auto-save
            $_SESSION['authenticated'] = false;
            echo json_encode([
                'success' => true,
                'authenticated' => false,
                'session_expired' => true,
                'message' => 'פג תוקף ההתחברות. אנא התחבר מחדש להמשך.'
            ]);
            exit;
        }

        // Refresh session timeout on activity
        $_SESSION['login_time'] = time();

        $userId = $_SESSION['user_id'];

        // שליפת נתונים קודמים מה-cache או מהמאגר
        $previousData = $_SESSION['cached_answers'] ?? [];

        if (empty($previousData)) {
            $stmt = $db->prepare("SELECT question_id, answer_value, answer_json FROM form_responses WHERE user_id = ?");
            $stmt->execute([$userId]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // מיזוג התשובות - מעדיף answer_json אם קיים, אחרת answer_value
            $previousData = [];
            foreach ($responses as $response) {
                $questionId = $response['question_id'];
                if (!empty($response['answer_json'])) {
                    $previousData[$questionId] = $response['answer_json'];
                } else {
                    $previousData[$questionId] = $response['answer_value'];
                }
            }
            $_SESSION['cached_answers'] = $previousData;
        }

        // Get form_id from session
        $formId = $_SESSION['form_id'] ?? 1;

        // חישוב השאלה הראשונה שאין לה תשובה
        // Check if form uses new structure format
        $stmt = $db->prepare("SELECT structure_json FROM forms WHERE id = ?");
        $stmt->execute([$formId]);
        $formData = $stmt->fetch(PDO::FETCH_ASSOC);

        $firstUnansweredIndex = 0;

        if ($formData && !empty($formData['structure_json'])) {
            // New format: use structure_json
            $structure = json_decode($formData['structure_json'], true);
            $steps = flattenStructureToSteps($structure);

            // Find first unanswered question step (skip text blocks)
            foreach ($steps as $index => $step) {
                if ($step['blockType'] === 'text') {
                    // Skip text blocks - they're not questions
                    continue;
                }

                if ($step['blockType'] === 'question') {
                    // Check if this question has an answer
                    if (!isset($previousData[$step['id']]) || $previousData[$step['id']] === '') {
                        $firstUnansweredIndex = $index;
                        break;
                    }
                }

                // If all questions answered, stay on last step
                if ($index === count($steps) - 1) {
                    $firstUnansweredIndex = $index;
                }
            }
        } else {
            // Old format: use questions table
            // מסודר לפי קטגוריה ואז לפי סדר השאלה
            // For Form 2, only use categorized questions
            $stmt = $db->prepare("
                SELECT q.id
                FROM questions q
                JOIN form_questions fq ON q.id = fq.question_id
                LEFT JOIN categories c ON fq.category_id = c.id
                WHERE fq.form_id = ? AND fq.is_active = 1
                    AND (fq.form_id != 2 OR fq.category_id IS NOT NULL)
                ORDER BY
                    COALESCE(c.sequence_order, 999) ASC,
                    fq.sequence_order ASC
            ");
            $stmt->execute([$formId]);
            $allQuestionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allQuestionIds as $index => $questionId) {
                if (!isset($previousData[$questionId]) || $previousData[$questionId] === '') {
                    $firstUnansweredIndex = $index;
                    break;
                }
                if ($index === count($allQuestionIds) - 1) {
                    $firstUnansweredIndex = $index;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user_id' => $userId,
            'previous_data' => $previousData,
            'first_unanswered_index' => $firstUnansweredIndex
        ]);
    } else {
        echo json_encode(['success' => true, 'authenticated' => false]);
    }
    exit;
}

// === 5. טיפול בשמירה אוטומטית של תשובה בודדת (AUTO_SAVE) ===
if ($action === 'auto_save') {
    // 1. בדיקת אימות מבוסס-Session
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות. אנא התחבר מחדש.']);
        exit;
    }

    // 2. בדיקת Session Timeout (30 דקות = 1800 שניות)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
        $_SESSION['authenticated'] = false;
        http_response_code(401);
        echo json_encode(['success' => false, 'session_expired' => true, 'message' => 'פג תוקף ההתחברות. התשובות נשמרו.']);
        exit;
    }

    // Refresh session timeout on activity
    $_SESSION['login_time'] = time();

    $userId = $_SESSION['user_id'];
    $formId = $_SESSION['form_id'] ?? 1; // טופס מה-Session
    $questionId = $input['question_id'] ?? '';
    $answerValue = $input['answer_value'] ?? '';

    if (empty($questionId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר question_id.']);
        exit;
    }

    try {
        // קבלת סוג השאלה כדי לדעת איך לשמור
        $stmt = $db->prepare("SELECT qt.type_code FROM questions q JOIN question_types qt ON q.question_type_id = qt.id WHERE q.id = ?");
        $stmt->execute([$questionId]);
        $questionType = $stmt->fetchColumn() ?: 'text';

        // שמירה או עדכון התשובה במאגר
        if ($questionType === 'checkbox') {
            // checkbox - שמירה ב-answer_json
            $jsonValue = is_array($answerValue) ? json_encode($answerValue, JSON_UNESCAPED_UNICODE) : $answerValue;
            $upsertSql = "
                INSERT INTO form_responses (user_id, question_id, answer_json, form_id, updated_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(user_id, question_id, form_id)
                DO UPDATE SET answer_json = excluded.answer_json, answer_value = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP;
            ";
            $stmt = $db->prepare($upsertSql);
            $stmt->execute([$userId, $questionId, $jsonValue, $formId]);
        } else {
            // סוג רגיל - שמירה ב-answer_value
            $upsertSql = "
                INSERT INTO form_responses (user_id, question_id, answer_value, form_id, updated_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(user_id, question_id, form_id)
                DO UPDATE SET answer_value = excluded.answer_value, answer_json = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP;
            ";
            $stmt = $db->prepare($upsertSql);
            $stmt->execute([$userId, $questionId, (string)$answerValue, $formId]);
        }

        // עדכון cache בסשן
        if (!isset($_SESSION['cached_answers'])) {
            $_SESSION['cached_answers'] = [];
        }
        $_SESSION['cached_answers'][$questionId] = $answerValue;

        echo json_encode(['success' => true, 'message' => 'נשמר בהצלחה.']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בשמירה: ' . $e->getMessage()]);
        exit;
    }
}

// === 6. קבלת לוח המשימות של המשתמש (GET_DASHBOARD) ===
if ($action === 'get_dashboard') {
    // בדיקת אימות
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    try {
        // שליפת פרטי המשתמש
        $stmt = $db->prepare("SELECT tz, id_type, full_name, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // שליפת כל המשימות של המשתמש
        $stmt = $db->prepare("
            SELECT
                ut.id,
                ut.task_id,
                ut.status,
                ut.due_date,
                ut.priority,
                ct.title,
                ct.description,
                ct.task_type,
                ct.estimated_duration,
                ct.points,
                ct.sequence_order,
                ct.form_id
            FROM user_tasks ut
            JOIN course_tasks ct ON ut.task_id = ct.id
            WHERE ut.user_id = ? AND ct.is_active = 1
            ORDER BY ct.sequence_order ASC
        ");
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'user' => $user,
            'tasks' => $tasks
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת הנתונים: ' . $e->getMessage()]);
        exit;
    }
}

// === 7. קבלת פרטי משימה ספציפית (GET_TASK_DETAIL) ===
if ($action === 'get_task_detail') {
    // בדיקת אימות
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $userTaskId = $input['user_task_id'] ?? '';

    if (empty($userTaskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה משימה.']);
        exit;
    }

    try {
        // שליפת פרטי המשימה
        $stmt = $db->prepare("
            SELECT
                ut.id,
                ut.task_id,
                ut.status,
                ut.due_date,
                ut.priority,
                ut.admin_notes,
                ct.title,
                ct.description,
                ct.instructions,
                ct.task_type,
                ct.estimated_duration,
                ct.points,
                ct.form_id,
                tp.review_notes
            FROM user_tasks ut
            JOIN course_tasks ct ON ut.task_id = ct.id
            LEFT JOIN task_progress tp ON ut.id = tp.user_task_id
            WHERE ut.id = ? AND ut.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userTaskId, $userId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'המשימה לא נמצאה.']);
            exit;
        }

        // שליפת חומרי לימוד
        $stmt = $db->prepare("
            SELECT
                id,
                title,
                description,
                material_type,
                file_path,
                external_url,
                content_text,
                is_required
            FROM course_materials
            WHERE task_id = ?
            ORDER BY display_order ASC
        ");
        $stmt->execute([$task['task_id']]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $task['materials'] = $materials;

        echo json_encode([
            'success' => true,
            'task' => $task
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת המשימה: ' . $e->getMessage()]);
        exit;
    }
}

// === 8. עדכון סטטוס משימה (UPDATE_TASK_STATUS) ===
if ($action === 'update_task_status') {
    // בדיקת אימות
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $userTaskId = $input['user_task_id'] ?? '';
    $newStatus = $input['status'] ?? '';

    if (empty($userTaskId) || empty($newStatus)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסרים פרמטרים.']);
        exit;
    }

    // בדיקת סטטוס תקין
    $validStatuses = ['pending', 'in_progress', 'completed', 'needs_review', 'approved', 'rejected'];
    if (!in_array($newStatus, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'סטטוס לא תקין.']);
        exit;
    }

    try {
        // וידוא שהמשימה שייכת למשתמש
        $stmt = $db->prepare("SELECT id FROM user_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$userTaskId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'אין הרשאה לעדכן משימה זו.']);
            exit;
        }

        // עדכון הסטטוס
        $stmt = $db->prepare("UPDATE user_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userTaskId]);

        // עדכון/הוספת רשומה ב-task_progress
        $progressData = [
            'user_task_id' => $userTaskId,
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // הוספת started_at אם התחלנו
        if ($newStatus === 'in_progress') {
            $stmt = $db->prepare("
                INSERT INTO task_progress (user_task_id, status, started_at, updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT(user_task_id)
                DO UPDATE SET status = excluded.status, started_at = COALESCE(task_progress.started_at, CURRENT_TIMESTAMP), updated_at = excluded.updated_at
            ");
            $stmt->execute([$userTaskId, $newStatus]);
        }
        // הוספת completed_at אם סיימנו
        elseif ($newStatus === 'needs_review' || $newStatus === 'completed') {
            $stmt = $db->prepare("
                INSERT INTO task_progress (user_task_id, status, completed_at, updated_at, progress_percentage)
                VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 100)
                ON CONFLICT(user_task_id)
                DO UPDATE SET status = excluded.status, completed_at = CURRENT_TIMESTAMP, updated_at = excluded.updated_at, progress_percentage = 100
            ");
            $stmt->execute([$userTaskId, $newStatus]);
        }
        else {
            $stmt = $db->prepare("
                INSERT INTO task_progress (user_task_id, status, updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(user_task_id)
                DO UPDATE SET status = excluded.status, updated_at = excluded.updated_at
            ");
            $stmt->execute([$userTaskId, $newStatus]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'הסטטוס עודכן בהצלחה.'
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון הסטטוס: ' . $e->getMessage()]);
        exit;
    }
}

// === 9. יציאה מהמערכת (LOGOUT) ===
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'יצאת מהמערכת בהצלחה.']);
    exit;
}

// === 10. קבלת מידע על המשתמש (GET_USER_INFO) ===
if ($action === 'get_user_info') {
    // Check authentication
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    try {
        $stmt = $db->prepare("SELECT id, tz, full_name, username, id_type, last_login FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'משתמש לא נמצא.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בשליפת נתוני משתמש.']);
    }
    exit;
}

// === 11. עדכון שם משתמש (UPDATE_USERNAME) ===
if ($action === 'update_username') {
    // Check authentication
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $username = $input['username'] ?? '';

    // Validate username
    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'שם משתמש חובה.']);
        exit;
    }

    // Validate username format (3-20 chars, alphanumeric + underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'שם משתמש לא תקין. השתמש רק באותיות אנגליות, מספרים וקו תחתון (_), 3-20 תווים.']);
        exit;
    }

    try {
        // Check if username already exists (for another user)
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'שם משתמש זה כבר תפוס. נא לבחור שם משתמש אחר.']);
            exit;
        }

        // Update username
        $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$username, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'שם המשתמש עודכן בהצלחה!',
            'username' => $username
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון שם משתמש: ' . $e->getMessage()]);
    }
    exit;
}

// === 12. עדכון סיסמה (UPDATE_PASSWORD) ===
if ($action === 'update_password') {
    // Check authentication
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $password = $input['password'] ?? '';

    // Validate password
    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'סיסמה חובה.']);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'הסיסמה חייבת להכיל לפחות 6 תווים.']);
        exit;
    }

    try {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Update password
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'הסיסמה עודכנה בהצלחה!'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון סיסמה: ' . $e->getMessage()]);
    }
    exit;
}

// אם לא נמצאה פעולה מתאימה
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Endpoint לא נמצא.']);
?>