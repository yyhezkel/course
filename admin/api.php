<?php
/**
 * Admin API
 * Handles all admin panel API requests
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://qr.bot4wa.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Require authentication for all API calls
requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$db = getDbConnection();

// ============================================
// DASHBOARD STATISTICS
// ============================================

if ($action === 'dashboard_stats') {
    try {
        // Get total users
        $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

        // Get total forms
        $totalForms = $db->query("SELECT COUNT(*) FROM forms WHERE is_active = 1")->fetchColumn();

        // Get completed forms (responses)
        $completedForms = $db->query("SELECT COUNT(DISTINCT user_id) FROM form_responses")->fetchColumn();

        // Get total questions
        $totalQuestions = $db->query("SELECT COUNT(*) FROM questions")->fetchColumn();

        // Get recent activity (last 10 activities) - gracefully handle if table doesn't exist
        $recentActivity = [];
        try {
            // Check if activity_log table exists
            $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='activity_log'")->fetch();
            if ($tableCheck) {
                $stmt = $db->query("
                    SELECT
                        a.*,
                        au.full_name as admin_name,
                        au.username
                    FROM activity_log a
                    LEFT JOIN admin_users au ON a.admin_user_id = au.id
                    ORDER BY a.created_at DESC
                    LIMIT 10
                ");
                $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $activityError) {
            // If activity_log query fails, just return empty array
            error_log("Activity log error: " . $activityError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_users' => (int)$totalUsers,
                'total_forms' => (int)$totalForms,
                'completed_forms' => (int)$completedForms,
                'total_questions' => (int)$totalQuestions,
                'recent_activity' => $recentActivity
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת נתונים: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// USER MANAGEMENT
// ============================================

if ($action === 'list_users') {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $search = $_GET['search'] ?? '';
        $whereClause = '';
        $params = [];

        if ($search) {
            $whereClause = "WHERE u.tz LIKE ? OR u.full_name LIKE ?";
            $params = ["%$search%", "%$search%"];
        }

        // Get users with their form assignments
        $stmt = $db->prepare("
            SELECT
                u.*,
                f.title as assigned_form,
                COUNT(DISTINCT fr.question_id) as answered_questions
            FROM users u
            LEFT JOIN user_forms uf ON u.id = uf.user_id AND uf.status = 'assigned'
            LEFT JOIN forms f ON uf.form_id = f.id
            LEFT JOIN form_responses fr ON u.id = fr.user_id
            $whereClause
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereClause");
        $countStmt->execute($params);
        $totalUsers = $countStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$totalUsers,
                'pages' => ceil($totalUsers / $limit)
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת משתמשים: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'create_user') {
    try {
        $tz = $input['tz'] ?? '';
        $idType = $input['id_type'] ?? 'tz'; // 'tz' or 'personal_number'
        $fullName = $input['full_name'] ?? '';
        $password = $input['password'] ?? '';
        $userType = $input['user_type'] ?? 'regular'; // 'admin' or 'regular'
        $formId = $input['form_id'] ?? null;

        // Validate ID number
        if (empty($tz)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מספר זהוי נדרש']);
            exit;
        }

        // Validate ID type
        if (!in_array($idType, ['tz', 'personal_number'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'סוג מזהה לא תקין']);
            exit;
        }

        // Validate length based on ID type
        $expectedLength = $idType === 'personal_number' ? 7 : 9;
        if (strlen($tz) !== $expectedLength || !is_numeric($tz)) {
            $idTypeName = $idType === 'personal_number' ? 'מספר אישי' : 'תעודת זהות';
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "$idTypeName חייב להיות $expectedLength ספרות"]);
            exit;
        }

        // For admin users, password is required
        if ($userType === 'admin' && empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'סיסמה נדרשת למשתמש מנהל']);
            exit;
        }

        // Check if user already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE tz = ?");
        $stmt->execute([$tz]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'משתמש עם מספר זה כבר קיים']);
            exit;
        }

        // Create user
        $passwordHash = null;
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        }

        $role = ($userType === 'admin') ? 'admin' : 'user';

        $stmt = $db->prepare("
            INSERT INTO users (tz, id_type, password_hash, full_name, role, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
        ");
        $stmt->execute([$tz, $idType, $passwordHash, $fullName, $role]);
        $userId = $db->lastInsertId();

        // Assign form if provided
        if ($formId) {
            $stmt = $db->prepare("
                INSERT INTO user_forms (user_id, form_id, assigned_by, assigned_at, status)
                VALUES (?, ?, ?, datetime('now'), 'assigned')
            ");
            $stmt->execute([$userId, $formId, $_SESSION['admin_user_id']]);
        }

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'create', 'user', $userId);

        echo json_encode([
            'success' => true,
            'message' => 'משתמש נוצר בהצלחה',
            'user_id' => $userId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת משתמש: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'bulk_import_users') {
    try {
        $csvData = $input['csv_data'] ?? '';
        $userType = $input['user_type'] ?? 'regular';
        $formId = $input['form_id'] ?? null;

        if (empty($csvData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'נתונים נדרשים']);
            exit;
        }

        $lines = array_filter(array_map('trim', explode("\n", $csvData)));
        $imported = 0;
        $errors = [];

        $db->beginTransaction();

        foreach ($lines as $lineNum => $line) {
            // Support both CSV and comma-separated
            $parts = array_map('trim', str_getcsv($line));

            if (count($parts) < 1 || empty($parts[0])) {
                continue; // Skip empty lines
            }

            $tz = $parts[0];
            $fullName = $parts[1] ?? '';
            $password = $parts[2] ?? '';

            // Validate ID
            if (empty($tz) || !is_numeric($tz)) {
                $errors[] = "שורה " . ($lineNum + 1) . ": מספר זהוי חסר או לא תקין";
                continue;
            }

            // Auto-detect ID type based on length
            $idLength = strlen($tz);
            if ($idLength === 7) {
                $idType = 'personal_number';
            } elseif ($idLength === 9) {
                $idType = 'tz';
            } else {
                $errors[] = "שורה " . ($lineNum + 1) . ": מספר זהוי חייב להיות 7 או 9 ספרות (קיבלתי {$idLength})";
                continue;
            }

            // Check if user exists
            $stmt = $db->prepare("SELECT id FROM users WHERE tz = ?");
            $stmt->execute([$tz]);
            if ($stmt->fetch()) {
                $errors[] = "שורה " . ($lineNum + 1) . ": משתמש {$tz} כבר קיים";
                continue;
            }

            // For admin users, password is required
            if ($userType === 'admin' && empty($password)) {
                $errors[] = "שורה " . ($lineNum + 1) . ": סיסמה נדרשת למשתמש מנהל";
                continue;
            }

            // Create user
            $passwordHash = null;
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            }

            $role = ($userType === 'admin') ? 'admin' : 'user';

            $stmt = $db->prepare("
                INSERT INTO users (tz, id_type, password_hash, full_name, role, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
            ");
            $stmt->execute([$tz, $idType, $passwordHash, $fullName, $role]);
            $userId = $db->lastInsertId();

            // Assign form if provided
            if ($formId) {
                $stmt = $db->prepare("
                    INSERT INTO user_forms (user_id, form_id, assigned_by, assigned_at, status)
                    VALUES (?, ?, ?, datetime('now'), 'assigned')
                ");
                $stmt->execute([$userId, $formId, $_SESSION['admin_user_id']]);
            }

            $imported++;
        }

        $db->commit();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'bulk_import', 'user', null, null, ['imported' => $imported]);

        echo json_encode([
            'success' => true,
            'message' => "ייבוא הושלם: {$imported} משתמשים נוספו",
            'imported' => $imported,
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בייבוא משתמשים: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_user') {
    try {
        $userId = $input['user_id'] ?? '';
        $fullName = $input['full_name'] ?? '';
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה משתמש נדרש']);
            exit;
        }

        // Update user
        $stmt = $db->prepare("
            UPDATE users
            SET full_name = ?, is_active = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $isActive, $userId]);

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'update', 'user', $userId);

        echo json_encode(['success' => true, 'message' => 'משתמש עודכן בהצלחה']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון משתמש: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_user') {
    try {
        $userId = $input['user_id'] ?? $_GET['user_id'] ?? '';

        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה משתמש נדרש']);
            exit;
        }

        // Soft delete - just mark as inactive
        $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$userId]);

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'delete', 'user', $userId);

        echo json_encode(['success' => true, 'message' => 'משתמש נמחק בהצלחה']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת משתמש: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// FORM MANAGEMENT
// ============================================

if ($action === 'list_forms') {
    try {
        $stmt = $db->query("
            SELECT
                f.*,
                COUNT(DISTINCT fq.question_id) as question_count,
                COUNT(DISTINCT uf.user_id) as assigned_users
            FROM forms f
            LEFT JOIN form_questions fq ON f.id = fq.form_id AND fq.is_active = 1
            LEFT JOIN user_forms uf ON f.id = uf.form_id AND uf.status = 'assigned'
            GROUP BY f.id
            ORDER BY f.created_at DESC
        ");
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'forms' => $forms]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת טפסים: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_form') {
    try {
        $formId = $_GET['form_id'] ?? '';

        if (empty($formId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה טופס נדרש']);
            exit;
        }

        // Get form details
        $stmt = $db->prepare("SELECT * FROM forms WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$form) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'טופס לא נמצא']);
            exit;
        }

        // Get form questions with section info
        $stmt = $db->prepare("
            SELECT
                fq.id as form_question_id,
                fq.section_title,
                fq.section_sequence,
                fq.sequence_order,
                q.id,
                q.question_text,
                q.question_type_id,
                q.placeholder,
                q.is_required,
                q.options,
                qt.type_code,
                qt.type_name
            FROM form_questions fq
            JOIN questions q ON fq.question_id = q.id
            JOIN question_types qt ON q.question_type_id = qt.id
            WHERE fq.form_id = ? AND fq.is_active = 1
            ORDER BY
                CASE WHEN fq.section_title IS NULL THEN 1 ELSE 0 END,
                fq.section_sequence,
                fq.sequence_order
        ");
        $stmt->execute([$formId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode structure_json if it exists
        $structure = null;
        if (!empty($form['structure_json'])) {
            $structure = json_decode($form['structure_json'], true);
        }

        echo json_encode([
            'success' => true,
            'form' => $form,
            'structure' => $structure,
            'questions' => $questions
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת טופס: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'create_form') {
    try {
        $title = $input['title'] ?? '';
        $description = $input['description'] ?? '';

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'כותרת נדרשת']);
            exit;
        }

        // Create form
        $stmt = $db->prepare("
            INSERT INTO forms (title, description, created_by, is_active, created_at)
            VALUES (?, ?, ?, 1, datetime('now'))
        ");
        $stmt->execute([$title, $description, $_SESSION['admin_user_id']]);
        $formId = $db->lastInsertId();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'create', 'form', $formId);

        echo json_encode([
            'success' => true,
            'message' => 'טופס נוצר בהצלחה',
            'form_id' => $formId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת טופס: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_question_to_form') {
    try {
        $formId = $input['form_id'] ?? '';
        $questionText = $input['question_text'] ?? '';
        $questionTypeId = $input['question_type_id'] ?? '';
        $sectionTitle = $input['section_title'] ?? null;
        $sectionSequence = $input['section_sequence'] ?? 0;
        $sequence = $input['sequence'] ?? 0;
        $isRequired = isset($input['is_required']) ? (int)$input['is_required'] : 1;
        $placeholder = $input['placeholder'] ?? null;
        $options = $input['options'] ?? null;

        if (empty($formId) || empty($questionText) || empty($questionTypeId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'נתונים חסרים']);
            exit;
        }

        $db->beginTransaction();

        // Create question
        $stmt = $db->prepare("
            INSERT INTO questions (question_text, question_type_id, placeholder, is_required, options, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$questionText, $questionTypeId, $placeholder, $isRequired, $options]);
        $questionId = $db->lastInsertId();

        // Add to form
        $stmt = $db->prepare("
            INSERT INTO form_questions (form_id, question_id, section_title, section_sequence, sequence_order, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
        ");
        $stmt->execute([$formId, $questionId, $sectionTitle, $sectionSequence, $sequence]);

        $db->commit();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'add_question', 'form', $formId, null, ['question_id' => $questionId]);

        echo json_encode([
            'success' => true,
            'message' => 'שאלה נוספה בהצלחה',
            'question_id' => $questionId
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בהוספת שאלה: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_question_in_form') {
    try {
        $formQuestionId = $input['form_question_id'] ?? '';
        $questionText = $input['question_text'] ?? '';
        $isRequired = isset($input['is_required']) ? (int)$input['is_required'] : null;
        $placeholder = $input['placeholder'] ?? null;
        $options = $input['options'] ?? null;

        if (empty($formQuestionId) || empty($questionText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'נתונים חסרים']);
            exit;
        }

        // Get question ID from form_questions
        $stmt = $db->prepare("SELECT question_id FROM form_questions WHERE id = ?");
        $stmt->execute([$formQuestionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'שאלה לא נמצאה']);
            exit;
        }

        $questionId = $result['question_id'];

        // Update question
        $stmt = $db->prepare("
            UPDATE questions
            SET question_text = ?,
                is_required = COALESCE(?, is_required),
                placeholder = COALESCE(?, placeholder),
                options = COALESCE(?, options),
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$questionText, $isRequired, $placeholder, $options, $questionId]);

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'update_question', 'question', $questionId);

        echo json_encode(['success' => true, 'message' => 'שאלה עודכנה בהצלחה']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון שאלה: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'remove_question_from_form') {
    try {
        $formQuestionId = $input['form_question_id'] ?? $_GET['form_question_id'] ?? '';

        if (empty($formQuestionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה נדרש']);
            exit;
        }

        // Soft delete - mark as inactive
        $stmt = $db->prepare("UPDATE form_questions SET is_active = 0 WHERE id = ?");
        $stmt->execute([$formQuestionId]);

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'remove_question', 'form_question', $formQuestionId);

        echo json_encode(['success' => true, 'message' => 'שאלה הוסרה בהצלחה']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בהסרת שאלה: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'reorder_form_structure') {
    try {
        $formId = $input['form_id'] ?? '';
        $structure = $input['structure'] ?? []; // Array of {id, section_title, section_sequence, sequence}

        if (empty($formId) || empty($structure)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'נתונים חסרים']);
            exit;
        }

        $db->beginTransaction();

        foreach ($structure as $item) {
            $stmt = $db->prepare("
                UPDATE form_questions
                SET section_title = ?,
                    section_sequence = ?,
                    sequence_order = ?
                WHERE id = ? AND form_id = ?
            ");
            $stmt->execute([
                $item['section_title'] ?? null,
                $item['section_sequence'] ?? 0,
                $item['sequence'] ?? 0,
                $item['id'],
                $formId
            ]);
        }

        $db->commit();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'reorder', 'form', $formId);

        echo json_encode(['success' => true, 'message' => 'מבנה הטופס עודכן בהצלחה']);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון מבנה: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_form_blocks') {
    try {
        $formId = $input['form_id'] ?? '';
        $blocks = $input['blocks'] ?? [];

        if (empty($formId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה טופס נדרש']);
            exit;
        }

        $db->beginTransaction();

        // First, deactivate all existing questions in this form
        $stmt = $db->prepare("UPDATE form_questions SET is_active = 0 WHERE form_id = ?");
        $stmt->execute([$formId]);

        // Process blocks and convert to database structure
        $sectionSequence = 0;
        $globalSequence = 0;

        foreach ($blocks as $block) {
            if ($block['type'] === 'section') {
                $sectionSequence++;
                $sectionTitle = $block['content']['title'] ?? 'קטגוריה';
                $questionSequence = 0;

                // Process questions in this section
                if (!empty($block['children'])) {
                    foreach ($block['children'] as $childId) {
                        $childBlock = null;
                        foreach ($blocks as $b) {
                            if ($b['id'] === $childId) {
                                $childBlock = $b;
                                break;
                            }
                        }

                        if ($childBlock && $childBlock['type'] === 'question') {
                            $questionSequence++;
                            $content = $childBlock['content'];

                            // Create question in questions table
                            $options = null;
                            if (isset($content['options']) && is_array($content['options'])) {
                                $options = json_encode($content['options']);
                            }

                            $stmt = $db->prepare("
                                INSERT INTO questions (question_text, question_type_id, placeholder, is_required, options, created_at)
                                VALUES (?, ?, ?, ?, ?, datetime('now'))
                            ");
                            $stmt->execute([
                                $content['questionText'],
                                $content['typeId'],
                                $content['placeholder'] ?? null,
                                $content['isRequired'] ? 1 : 0,
                                $options
                            ]);
                            $questionId = $db->lastInsertId();

                            // Add to form_questions
                            $stmt = $db->prepare("
                                INSERT INTO form_questions (form_id, question_id, section_title, section_sequence, sequence_order, is_active, created_at)
                                VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
                            ");
                            $stmt->execute([$formId, $questionId, $sectionTitle, $sectionSequence, $questionSequence]);
                        }
                    }
                }
            } elseif ($block['type'] === 'question' && empty($block['parentId'])) {
                // Orphan question (not in a section)
                $globalSequence++;
                $content = $block['content'];

                // Create question in questions table
                $options = null;
                if (isset($content['options']) && is_array($content['options'])) {
                    $options = json_encode($content['options']);
                }

                $stmt = $db->prepare("
                    INSERT INTO questions (question_text, question_type_id, placeholder, is_required, options, created_at)
                    VALUES (?, ?, ?, ?, ?, datetime('now'))
                ");
                $stmt->execute([
                    $content['questionText'],
                    $content['typeId'],
                    $content['placeholder'] ?? null,
                    $content['isRequired'] ? 1 : 0,
                    $options
                ]);
                $questionId = $db->lastInsertId();

                // Add to form_questions without section
                $stmt = $db->prepare("
                    INSERT INTO form_questions (form_id, question_id, section_title, section_sequence, sequence_order, is_active, created_at)
                    VALUES (?, ?, NULL, 0, ?, 1, datetime('now'))
                ");
                $stmt->execute([$formId, $questionId, $globalSequence]);
            }
        }

        // Save the complete block structure as JSON (includes text and condition blocks)
        $structureJson = json_encode($blocks);
        $stmt = $db->prepare("UPDATE forms SET structure_json = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$structureJson, $formId]);

        $db->commit();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'save_blocks', 'form', $formId);

        echo json_encode([
            'success' => true,
            'message' => 'הטופס נשמר בהצלחה למסד הנתונים'
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בשמירת הטופס: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// QUESTION MANAGEMENT
// ============================================

if ($action === 'list_questions') {
    try {
        $search = $_GET['search'] ?? '';
        $typeId = $_GET['type_id'] ?? '';

        $whereClause = '';
        $params = [];

        if ($search) {
            $whereClause = "WHERE q.question_text LIKE ?";
            $params[] = "%$search%";
        }

        if ($typeId) {
            $whereClause .= ($whereClause ? ' AND' : 'WHERE') . " q.question_type_id = ?";
            $params[] = $typeId;
        }

        $stmt = $db->prepare("
            SELECT
                q.*,
                qt.type_code,
                qt.type_name,
                COUNT(DISTINCT fq.form_id) as used_in_forms
            FROM questions q
            JOIN question_types qt ON q.question_type_id = qt.id
            LEFT JOIN form_questions fq ON q.id = fq.question_id AND fq.is_active = 1
            $whereClause
            GROUP BY q.id
            ORDER BY q.created_at DESC
        ");
        $stmt->execute($params);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'questions' => $questions]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת שאלות: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_question_types') {
    try {
        $stmt = $db->query("SELECT * FROM question_types ORDER BY type_name");
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'types' => $types]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת סוגי שאלות: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// RESPONSE VIEWING
// ============================================

if ($action === 'list_responses') {
    try {
        $formId = $_GET['form_id'] ?? '';
        $userId = $_GET['user_id'] ?? '';

        $whereClause = 'WHERE 1=1';
        $params = [];

        if ($formId) {
            $whereClause .= " AND fr.form_id = ?";
            $params[] = $formId;
        }

        if ($userId) {
            $whereClause .= " AND u.id = ?";
            $params[] = $userId;
        }

        // Modified query to show ALL submissions, not just assigned forms
        $stmt = $db->prepare("
            SELECT
                u.id as user_id,
                u.tz,
                u.full_name,
                'טופס דיגיטלי ברירת מחדל' as form_title,
                COUNT(DISTINCT fr.question_id) as answered_questions,
                MAX(fr.submitted_at) as last_submission
            FROM users u
            INNER JOIN form_responses fr ON u.id = fr.user_id
            $whereClause
            GROUP BY u.id
            ORDER BY last_submission DESC
        ");
        $stmt->execute($params);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'responses' => $responses]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת תשובות: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_user_responses') {
    try {
        $userId = $_GET['user_id'] ?? '';

        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה משתמש נדרש']);
            exit;
        }

        // Get user info
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get all responses
        $stmt = $db->prepare("
            SELECT
                fr.*,
                q.question_text,
                qt.type_code,
                qt.type_name
            FROM form_responses fr
            JOIN questions q ON fr.question_id = q.id
            JOIN question_types qt ON q.question_type_id = qt.id
            WHERE fr.user_id = ?
            ORDER BY fr.submitted_at DESC
        ");
        $stmt->execute([$userId]);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'user' => $user,
            'responses' => $responses
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת תשובות משתמש: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// TABLE VIEW
// ============================================

if ($action === 'get_forms') {
    try {
        $stmt = $db->query("
            SELECT id, title, description, is_active
            FROM forms
            ORDER BY id ASC
        ");
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'forms' => $forms
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת טפסים: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_table_data') {
    try {
        $formId = $_GET['form_id'] ?? null;

        if (!$formId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'חסר מזהה טופס']);
            exit;
        }

        // Get all users who have answered questions for this form
        $usersStmt = $db->prepare("
            SELECT DISTINCT u.id, u.tz, u.full_name, u.email, u.phone
            FROM users u
            INNER JOIN form_responses fr ON u.id = fr.user_id
            WHERE fr.form_id = ?
            ORDER BY u.id ASC
        ");
        $usersStmt->execute([$formId]);
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all questions that have actual answers for this form
        // This ignores form_questions and shows only questions with real data
        $questionsStmt = $db->prepare("
            SELECT DISTINCT q.id, q.question_text, qt.type_code
            FROM questions q
            INNER JOIN form_responses fr ON q.id = fr.question_id
            INNER JOIN question_types qt ON q.question_type_id = qt.id
            WHERE fr.form_id = ?
            ORDER BY q.id ASC
        ");
        $questionsStmt->execute([$formId]);
        $questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all answers for this form
        $answersStmt = $db->prepare("
            SELECT user_id, question_id, answer_value, answer_json
            FROM form_responses
            WHERE form_id = ?
        ");
        $answersStmt->execute([$formId]);
        $answersRaw = $answersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Create a lookup map: "user_id_question_id" => answer
        $answers = [];
        foreach ($answersRaw as $answer) {
            $key = $answer['user_id'] . '_' . $answer['question_id'];
            $answers[$key] = $answer;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'users' => $users,
                'questions' => $questions,
                'answers' => $answers
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת נתוני טבלה: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// COURSE MANAGEMENT API ENDPOINTS
// ============================================

// Get all users with their progress
if ($action === 'get_all_users_with_progress') {
    try {
        $stmt = $db->query("
            SELECT
                u.id,
                u.tz,
                u.is_blocked,
                u.last_login,
                COUNT(DISTINCT ut.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN ut.status IN ('completed', 'approved') THEN ut.id END) as completed_tasks,
                COUNT(DISTINCT CASE WHEN ut.status = 'pending' THEN ut.id END) as pending_tasks,
                COUNT(DISTINCT CASE WHEN ut.status = 'needs_review' THEN ut.id END) as review_tasks
            FROM users u
            LEFT JOIN user_tasks ut ON u.id = ut.user_id
            GROUP BY u.id
            ORDER BY u.id DESC
        ");

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת המשתמשים: ' . $e->getMessage()]);
    }
    exit;
}

// Get user detail with all tasks
if ($action === 'get_user_detail') {
    $userId = $input['user_id'] ?? '';

    if (empty($userId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה משתמש']);
        exit;
    }

    try {
        // Get user info
        $stmt = $db->prepare("SELECT id, tz, is_blocked, last_login FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'משתמש לא נמצא']);
            exit;
        }

        // Get all tasks for this user
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
                ct.task_type,
                ct.estimated_duration,
                ct.points,
                ct.sequence_order,
                ct.form_id,
                tp.review_notes
            FROM user_tasks ut
            JOIN course_tasks ct ON ut.task_id = ct.id
            LEFT JOIN task_progress tp ON ut.id = tp.user_task_id
            WHERE ut.user_id = ?
            ORDER BY ct.sequence_order ASC
        ");
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'user' => $user,
            'tasks' => $tasks
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת פרטי המשתמש: ' . $e->getMessage()]);
    }
    exit;
}

// Review a task (approve/reject)
if ($action === 'review_task') {
    $userTaskId = $input['user_task_id'] ?? '';
    $status = $input['status'] ?? '';
    $reviewNotes = $input['review_notes'] ?? '';

    if (empty($userTaskId) || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסרים פרמטרים']);
        exit;
    }

    if (!in_array($status, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'סטטוס לא תקין']);
        exit;
    }

    try {
        $adminId = $_SESSION['admin_id'] ?? null;

        // Update task status
        $stmt = $db->prepare("UPDATE user_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $userTaskId]);

        // Update task progress with review info
        $stmt = $db->prepare("
            UPDATE task_progress
            SET reviewed_at = CURRENT_TIMESTAMP,
                reviewed_by = ?,
                review_notes = ?,
                status = ?
            WHERE user_task_id = ?
        ");
        $stmt->execute([$adminId, $reviewNotes, $status, $userTaskId]);

        // Create notification for user
        $stmt = $db->prepare("SELECT user_id, task_id FROM user_tasks WHERE id = ?");
        $stmt->execute([$userTaskId]);
        $taskInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($taskInfo) {
            $notificationTitle = $status === 'approved' ? 'המשימה אושרה!' : 'המשימה נדחתה';
            $notificationMessage = $status === 'approved'
                ? 'המשימה שלך אושרה על ידי המנחה'
                : 'המשימה שלך נדחתה. אנא קרא את ההערות ותקן.';

            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, notification_type, related_user_task_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $taskInfo['user_id'],
                $notificationTitle,
                $notificationMessage,
                $status === 'approved' ? 'success' : 'warning',
                $userTaskId
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'הסטטוס עודכן בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון הסטטוס: ' . $e->getMessage()]);
    }
    exit;
}

// Get all course tasks (library)
if ($action === 'get_all_tasks') {
    try {
        $stmt = $db->query("
            SELECT
                ct.*,
                f.title as form_title,
                COUNT(DISTINCT ut.id) as assigned_count,
                COUNT(DISTINCT CASE WHEN ut.status IN ('completed', 'approved') THEN ut.id END) as completed_count
            FROM course_tasks ct
            LEFT JOIN forms f ON ct.form_id = f.id
            LEFT JOIN user_tasks ut ON ct.id = ut.task_id
            GROUP BY ct.id
            ORDER BY ct.sequence_order ASC
        ");

        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'tasks' => $tasks
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת המשימות: ' . $e->getMessage()]);
    }
    exit;
}

// Assign task to user
if ($action === 'assign_task') {
    $userId = $input['user_id'] ?? '';
    $taskId = $input['task_id'] ?? '';
    $dueDate = $input['due_date'] ?? null;
    $priority = $input['priority'] ?? 'normal';
    $adminNotes = $input['admin_notes'] ?? '';

    if (empty($userId) || empty($taskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסרים פרמטרים']);
        exit;
    }

    try {
        $adminId = $_SESSION['admin_id'] ?? null;

        // Check if task already assigned
        $stmt = $db->prepare("SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ?");
        $stmt->execute([$userId, $taskId]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'המשימה כבר הוקצתה למשתמש זה']);
            exit;
        }

        // Assign task
        $stmt = $db->prepare("
            INSERT INTO user_tasks (user_id, task_id, assigned_by, due_date, priority, admin_notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $taskId, $adminId, $dueDate, $priority, $adminNotes]);
        $userTaskId = $db->lastInsertId();

        // Create notification
        $stmt = $db->prepare("SELECT title FROM course_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $taskTitle = $stmt->fetchColumn();

        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, notification_type, related_task_id, related_user_task_id)
            VALUES (?, ?, ?, 'task_assigned', ?, ?)
        ");
        $stmt->execute([
            $userId,
            'משימה חדשה הוקצתה',
            "הוקצתה לך משימה חדשה: {$taskTitle}",
            $taskId,
            $userTaskId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'המשימה הוקצתה בהצלחה',
            'user_task_id' => $userTaskId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בהקצאת המשימה: ' . $e->getMessage()]);
    }
    exit;
}

// Create new task
if ($action === 'create_task') {
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $instructions = $input['instructions'] ?? '';
    $taskType = $input['task_type'] ?? 'assignment';
    $formId = $input['form_id'] ?? null;
    $estimatedDuration = $input['estimated_duration'] ?? null;
    $points = $input['points'] ?? 0;
    $sequenceOrder = $input['sequence_order'] ?? 0;

    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר כותרת למשימה']);
        exit;
    }

    try {
        $adminId = $_SESSION['admin_id'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO course_tasks (title, description, instructions, task_type, form_id, estimated_duration, points, sequence_order, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $instructions, $taskType, $formId, $estimatedDuration, $points, $sequenceOrder, $adminId]);
        $taskId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'המשימה נוצרה בהצלחה',
            'task_id' => $taskId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת המשימה: ' . $e->getMessage()]);
    }
    exit;
}

// Update task
if ($action === 'update_task') {
    $taskId = $input['task_id'] ?? '';
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $instructions = $input['instructions'] ?? '';
    $taskType = $input['task_type'] ?? 'assignment';
    $formId = $input['form_id'] ?? null;
    $estimatedDuration = $input['estimated_duration'] ?? null;
    $points = $input['points'] ?? 0;
    $sequenceOrder = $input['sequence_order'] ?? 0;
    $isActive = $input['is_active'] ?? 1;

    if (empty($taskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה משימה']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE course_tasks
            SET title = ?, description = ?, instructions = ?, task_type = ?, form_id = ?,
                estimated_duration = ?, points = ?, sequence_order = ?, is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $instructions, $taskType, $formId, $estimatedDuration, $points, $sequenceOrder, $isActive, $taskId]);

        echo json_encode([
            'success' => true,
            'message' => 'המשימה עודכנה בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון המשימה: ' . $e->getMessage()]);
    }
    exit;
}

// Bulk assign task to multiple users
if ($action === 'bulk_assign_task') {
    $userIds = $input['user_ids'] ?? [];
    $taskId = $input['task_id'] ?? '';
    $dueDate = $input['due_date'] ?? null;
    $priority = $input['priority'] ?? 'normal';

    if (empty($userIds) || empty($taskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסרים פרמטרים']);
        exit;
    }

    try {
        $adminId = $_SESSION['admin_id'] ?? null;
        $assignedCount = 0;
        $skippedCount = 0;

        // Get task title for notification
        $stmt = $db->prepare("SELECT title FROM course_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $taskTitle = $stmt->fetchColumn();

        foreach ($userIds as $userId) {
            // Check if already assigned
            $stmt = $db->prepare("SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ?");
            $stmt->execute([$userId, $taskId]);
            if ($stmt->fetch()) {
                $skippedCount++;
                continue;
            }

            // Assign task
            $stmt = $db->prepare("
                INSERT INTO user_tasks (user_id, task_id, assigned_by, due_date, priority, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$userId, $taskId, $adminId, $dueDate, $priority]);
            $userTaskId = $db->lastInsertId();

            // Create notification
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, notification_type, related_task_id, related_user_task_id)
                VALUES (?, ?, ?, 'task_assigned', ?, ?)
            ");
            $stmt->execute([
                $userId,
                'משימה חדשה הוקצתה',
                "הוקצתה לך משימה חדשה: {$taskTitle}",
                $taskId,
                $userTaskId
            ]);

            $assignedCount++;
        }

        echo json_encode([
            'success' => true,
            'message' => "המשימה הוקצתה ל-{$assignedCount} משתמשים. {$skippedCount} דולגו (כבר הוקצו).",
            'assigned_count' => $assignedCount,
            'skipped_count' => $skippedCount
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בהקצאה המרובה: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// DEFAULT
// ============================================

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'פעולה לא נמצאה']);
?>
