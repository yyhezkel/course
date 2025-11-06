<?php
/**
 * Admin API
 * Handles all admin panel API requests
 */

// Disable error display to prevent HTML output before JSON
// Errors will still be logged to the error log
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://qr.bot4wa.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$db = getDbConnection();

// ============================================
// PUBLIC ENDPOINTS (No Authentication Required)
// ============================================

// Validate invite token
if ($action === 'validate_invite_token') {
    try {
        $token = $input['token'] ?? '';

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'קוד הזמנה חסר']);
            exit;
        }

        // Find token in database
        $stmt = $db->prepare("
            SELECT
                id,
                token,
                role,
                preset_full_name,
                expires_at,
                used_at,
                is_active
            FROM registration_tokens
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if token exists
        if (!$tokenData) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'קוד הזמנה לא נמצא']);
            exit;
        }

        // Check if token is active
        if (!$tokenData['is_active']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'קוד הזמנה זה בוטל']);
            exit;
        }

        // Check if token was already used
        if ($tokenData['used_at']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'קוד הזמנה זה כבר נוצל']);
            exit;
        }

        // Check if token expired
        if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) < time()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'קוד הזמנה זה פג תוקף']);
            exit;
        }

        // Token is valid
        echo json_encode([
            'success' => true,
            'message' => 'קוד הזמנה תקף',
            'token' => [
                'role' => $tokenData['role'],
                'preset_full_name' => $tokenData['preset_full_name'],
                'expires_at' => $tokenData['expires_at']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בבדיקת קוד ההזמנה: ' . $e->getMessage()]);
    }
    exit;
}

// Register admin with token
if ($action === 'register_with_token') {
    try {
        $token = $input['token'] ?? '';
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $fullName = trim($input['full_name'] ?? '');
        $password = $input['password'] ?? '';

        // Validate required fields
        if (empty($token) || empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'שדות חובה חסרים']);
            exit;
        }

        // Validate username length
        if (strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'שם המשתמש חייב להיות לפחות 3 תווים']);
            exit;
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'כתובת דוא"ל לא תקינה']);
            exit;
        }

        // Validate password strength
        $passwordValidation = validatePasswordStrength($password);
        if (!$passwordValidation['valid']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $passwordValidation['message']]);
            exit;
        }

        // Start transaction
        $db->beginTransaction();

        try {
            // Validate and fetch token
            $stmt = $db->prepare("
                SELECT
                    id,
                    role,
                    preset_full_name,
                    expires_at,
                    used_at,
                    is_active
                FROM registration_tokens
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Validate token (same checks as validate endpoint)
            if (!$tokenData) {
                throw new Exception('קוד הזמנה לא נמצא');
            }
            if (!$tokenData['is_active']) {
                throw new Exception('קוד הזמנה זה בוטל');
            }
            if ($tokenData['used_at']) {
                throw new Exception('קוד הזמנה זה כבר נוצל');
            }
            if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) < time()) {
                throw new Exception('קוד הזמנה זה פג תוקף');
            }

            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('שם המשתמש כבר קיים במערכת');
            }

            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('כתובת הדוא"ל כבר קיימת במערכת');
            }

            // Use preset full name if not provided
            if (empty($fullName) && !empty($tokenData['preset_full_name'])) {
                $fullName = $tokenData['preset_full_name'];
            }

            // Create admin user
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO admin_users (username, password_hash, email, full_name, role, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
            ");
            $stmt->execute([$username, $passwordHash, $email, $fullName, $tokenData['role']]);
            $adminId = $db->lastInsertId();

            // Mark token as used
            $stmt = $db->prepare("
                UPDATE registration_tokens
                SET used_at = datetime('now'),
                    used_by_admin_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$adminId, $tokenData['id']]);

            // Log activity (if activity_log table exists)
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, created_at)
                    VALUES (?, 'create', 'admin_user', ?, datetime('now'))
                ");
                $stmt->execute([$adminId, $adminId]);
            } catch (Exception $logError) {
                // Ignore if activity_log doesn't exist
                error_log("Activity log error: " . $logError->getMessage());
            }

            // Commit transaction
            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'חשבון המנהל נוצר בהצלחה! מעביר לדף התחברות...',
                'admin_id' => $adminId,
                'username' => $username
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// AUTHENTICATED ENDPOINTS
// ============================================

// Require authentication for all other API calls
requireAuth();

// ============================================
// ADMIN USER MANAGEMENT
// ============================================

// List all admin users
if ($action === 'list_admins') {
    try {
        $stmt = $db->query("
            SELECT
                id,
                username,
                email,
                full_name,
                role,
                is_active,
                last_login,
                created_at
            FROM admin_users
            ORDER BY created_at DESC
        ");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'admins' => $admins
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת מנהלים: ' . $e->getMessage()]);
    }
    exit;
}

// Toggle admin status (activate/deactivate)
if ($action === 'toggle_admin_status') {
    try {
        $adminId = $input['admin_id'] ?? null;

        if (!$adminId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה מנהל חסר']);
            exit;
        }

        // Get current status
        $stmt = $db->prepare("SELECT is_active FROM admin_users WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'מנהל לא נמצא']);
            exit;
        }

        // Toggle status
        $newStatus = $admin['is_active'] ? 0 : 1;
        $stmt = $db->prepare("
            UPDATE admin_users
            SET is_active = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $adminId]);

        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, created_at)
                VALUES (?, ?, 'admin_user', ?, datetime('now'))
            ");
            $stmt->execute([$_SESSION['admin_id'], $newStatus ? 'activate' : 'deactivate', $adminId]);
        } catch (Exception $logError) {
            error_log("Activity log error: " . $logError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => $newStatus ? 'המנהל הופעל בהצלחה' : 'המנהל הושבת בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון הסטטוס: ' . $e->getMessage()]);
    }
    exit;
}

// Delete admin user
if ($action === 'delete_admin') {
    try {
        $adminId = $input['admin_id'] ?? null;

        if (!$adminId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה מנהל חסר']);
            exit;
        }

        // Prevent self-deletion
        if ($adminId == $_SESSION['admin_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'לא ניתן למחוק את עצמך']);
            exit;
        }

        // Check if at least one admin will remain
        $activeCount = $db->query("SELECT COUNT(*) FROM admin_users WHERE is_active = 1")->fetchColumn();
        if ($activeCount <= 1) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'לא ניתן למחוק את המנהל האחרון']);
            exit;
        }

        // Delete admin
        $stmt = $db->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$adminId]);

        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, created_at)
                VALUES (?, 'delete', 'admin_user', ?, datetime('now'))
            ");
            $stmt->execute([$_SESSION['admin_id'], $adminId]);
        } catch (Exception $logError) {
            error_log("Activity log error: " . $logError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'המנהל נמחק בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת המנהל: ' . $e->getMessage()]);
    }
    exit;
}

// Change admin password
if ($action === 'change_admin_password') {
    try {
        $adminId = $input['admin_id'] ?? null;
        $newPassword = $input['new_password'] ?? '';

        if (!$adminId || !$newPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'נתונים חסרים']);
            exit;
        }

        // Validate password strength
        $passwordValidation = validatePasswordStrength($newPassword);
        if (!$passwordValidation['valid']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $passwordValidation['message']]);
            exit;
        }

        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            UPDATE admin_users
            SET password_hash = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$passwordHash, $adminId]);

        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, created_at)
                VALUES (?, 'update_password', 'admin_user', ?, datetime('now'))
            ");
            $stmt->execute([$_SESSION['admin_id'], $adminId]);
        } catch (Exception $logError) {
            error_log("Activity log error: " . $logError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'הסיסמה שונתה בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בשינוי הסיסמה: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// INVITE MANAGEMENT
// ============================================

// Create invite
if ($action === 'create_invite') {
    try {
        $role = $input['role'] ?? 'admin';
        $fullName = trim($input['full_name'] ?? '');
        $expiryHours = isset($input['expiry_hours']) ? (int)$input['expiry_hours'] : null;

        // Validate role
        $validRoles = ['admin', 'super_admin', 'moderator'];
        if (!in_array($role, $validRoles)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'תפקיד לא תקין']);
            exit;
        }

        // Generate token
        $token = bin2hex(random_bytes(32));

        // Calculate expiry
        $expiresAt = null;
        if ($expiryHours) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));
        }

        // Insert token
        $stmt = $db->prepare("
            INSERT INTO registration_tokens (token, role, preset_full_name, created_by_admin_id, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$token, $role, $fullName, $_SESSION['admin_id'], $expiresAt]);

        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, created_at)
                VALUES (?, 'create', 'registration_token', ?, datetime('now'))
            ");
            $stmt->execute([$_SESSION['admin_id'], $db->lastInsertId()]);
        } catch (Exception $logError) {
            error_log("Activity log error: " . $logError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'הזמנה נוצרה בהצלחה',
            'token' => $token
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת הזמנה: ' . $e->getMessage()]);
    }
    exit;
}

// List invites
if ($action === 'list_invites') {
    try {
        $stmt = $db->query("
            SELECT
                rt.*,
                au.username as used_by_username
            FROM registration_tokens rt
            LEFT JOIN admin_users au ON rt.used_by_admin_id = au.id
            ORDER BY rt.created_at DESC
        ");
        $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'invites' => $invites
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בטעינת הזמנות: ' . $e->getMessage()]);
    }
    exit;
}

// Revoke invite
if ($action === 'revoke_invite') {
    try {
        $inviteId = $input['invite_id'] ?? null;

        if (!$inviteId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה הזמנה חסר']);
            exit;
        }

        // Update invite
        $stmt = $db->prepare("
            UPDATE registration_tokens
            SET is_active = 0
            WHERE id = ? AND used_at IS NULL
        ");
        $stmt->execute([$inviteId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'הזמנה לא נמצאה או כבר נוצלה']);
            exit;
        }

        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, created_at)
                VALUES (?, 'revoke', 'registration_token', ?, datetime('now'))
            ");
            $stmt->execute([$_SESSION['admin_id'], $inviteId]);
        } catch (Exception $logError) {
            error_log("Activity log error: " . $logError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'ההזמנה בוטלה בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בביטול הזמנה: ' . $e->getMessage()]);
    }
    exit;
}

// Delete invite
if ($action === 'delete_invite') {
    try {
        $inviteId = $input['invite_id'] ?? null;

        if (!$inviteId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה הזמנה חסר']);
            exit;
        }

        // Delete invite
        $stmt = $db->prepare("DELETE FROM registration_tokens WHERE id = ?");
        $stmt->execute([$inviteId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'הזמנה לא נמצאה']);
            exit;
        }

        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, created_at)
                VALUES (?, 'delete', 'registration_token', ?, datetime('now'))
            ");
            $stmt->execute([$_SESSION['admin_id'], $inviteId]);
        } catch (Exception $logError) {
            error_log("Activity log error: " . $logError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'ההזמנה נמחקה בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת הזמנה: ' . $e->getMessage()]);
    }
    exit;
}

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

        // Get course management stats - gracefully handle if tables don't exist
        $totalTasks = 0;
        $completedTasksCount = 0;
        $pendingReviews = 0;

        try {
            $tasksTableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='course_tasks'")->fetch();
            if ($tasksTableCheck) {
                $totalTasks = $db->query("SELECT COUNT(*) FROM course_tasks WHERE is_active = 1")->fetchColumn();
                $completedTasksCount = $db->query("SELECT COUNT(*) FROM user_tasks WHERE status IN ('completed', 'approved')")->fetchColumn();
                $pendingReviews = $db->query("SELECT COUNT(*) FROM user_tasks WHERE status = 'needs_review'")->fetchColumn();
            }
        } catch (Exception $courseError) {
            error_log("Course stats error: " . $courseError->getMessage());
        }

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

        // Get admin info
        $adminId = $_SESSION['admin_id'] ?? null;
        $adminInfo = null;
        if ($adminId) {
            $stmt = $db->prepare("SELECT username, role FROM admin_users WHERE id = ?");
            $stmt->execute([$adminId]);
            $adminInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_users' => (int)$totalUsers,
                'total_forms' => (int)$totalForms,
                'completed_forms' => (int)$completedForms,
                'total_questions' => (int)$totalQuestions,
                'total_tasks' => (int)$totalTasks,
                'completed_tasks' => (int)$completedTasksCount,
                'pending_reviews' => (int)$pendingReviews,
                'recent_activity' => $recentActivity
            ],
            'admin_info' => $adminInfo
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
        $formId = $input['form_id'] ?? null;

        // NOTE: This endpoint creates REGULAR USERS (students) only.
        // Admin users are managed separately via admin_users table.

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

        $stmt = $db->prepare("
            INSERT INTO users (tz, id_type, password_hash, full_name, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, datetime('now'))
        ");
        $stmt->execute([$tz, $idType, $passwordHash, $fullName]);
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
        $formId = $input['form_id'] ?? null;

        // NOTE: This endpoint creates REGULAR USERS (students) only.
        // Admin users are managed separately via admin_users table.

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

            // Create user
            $passwordHash = null;
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            }

            $stmt = $db->prepare("
                INSERT INTO users (tz, id_type, password_hash, full_name, is_active, created_at)
                VALUES (?, ?, ?, ?, 1, datetime('now'))
            ");
            $stmt->execute([$tz, $idType, $passwordHash, $fullName]);
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

        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה משתמש נדרש']);
            exit;
        }

        // Build update query dynamically
        $updates = [];
        $params = [];

        if (isset($input['full_name'])) {
            $updates[] = 'full_name = ?';
            $params[] = $fullName;
        }

        // Fix: When toggling status, update is_blocked (not is_active)
        // is_active parameter actually means block/unblock: 1=unblock, 0=block
        if (isset($input['is_active'])) {
            $isActive = (int)$input['is_active'];
            $updates[] = 'is_blocked = ?';
            // Inverse: if is_active=1 (activate), set is_blocked=0
            $params[] = $isActive === 1 ? 0 : 1;
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'אין שדות לעדכון']);
            exit;
        }

        $updates[] = 'updated_at = datetime(\'now\')';
        $params[] = $userId;

        // Update user
        $stmt = $db->prepare("
            UPDATE users
            SET " . implode(', ', $updates) . "
            WHERE id = ?
        ");
        $stmt->execute($params);

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'update', 'user', $userId);

        echo json_encode(['success' => true, 'message' => 'משתמש עודכן בהצלחה']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון משתמש: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'archive_user') {
    try {
        $userId = $input['user_id'] ?? '';

        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה משתמש נדרש']);
            exit;
        }

        // Check if is_archived column exists
        $columnsResult = $db->query("PRAGMA table_info(users)");
        $columns = $columnsResult->fetchAll(PDO::FETCH_ASSOC);
        $hasArchivedColumn = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'is_archived') {
                $hasArchivedColumn = true;
                break;
            }
        }

        if (!$hasArchivedColumn) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'יש להריץ את תסריט ההגירה לפני שימוש בתכונת הארכיון',
                'migration_needed' => true
            ]);
            exit;
        }

        // Archive user (set is_archived = 1)
        $stmt = $db->prepare("
            UPDATE users
            SET is_archived = 1, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'archive', 'user', $userId);

        echo json_encode(['success' => true, 'message' => 'משתמש הועבר לארכיון בהצלחה']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בארכוב משתמש: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'unarchive_user') {
    try {
        $userId = $input['user_id'] ?? '';

        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'מזהה משתמש נדרש']);
            exit;
        }

        // Check if is_archived column exists
        $columnsResult = $db->query("PRAGMA table_info(users)");
        $columns = $columnsResult->fetchAll(PDO::FETCH_ASSOC);
        $hasArchivedColumn = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'is_archived') {
                $hasArchivedColumn = true;
                break;
            }
        }

        if (!$hasArchivedColumn) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'יש להריץ את תסריט ההגירה לפני שימוש בתכונת הארכיון',
                'migration_needed' => true
            ]);
            exit;
        }

        // Unarchive user (set is_archived = 0)
        $stmt = $db->prepare("
            UPDATE users
            SET is_archived = 0, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'unarchive', 'user', $userId);

        echo json_encode(['success' => true, 'message' => 'משתמש הוחזר מהארכיון בהצלחה']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בהחזרת משתמש מארכיון: ' . $e->getMessage()]);
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
// BATCH OPERATIONS
// ============================================

if ($action === 'batch_update_users') {
    try {
        $userIds = $input['user_ids'] ?? [];
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : null;

        if (empty($userIds) || !is_array($userIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'רשימת משתמשים נדרשת']);
            exit;
        }

        if ($isActive === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'סטטוס נדרש']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = datetime('now') WHERE id IN ($placeholders)");
        $params = array_merge([$isActive], $userIds);
        $stmt->execute($params);

        $affected = $stmt->rowCount();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'batch_update', 'user', null, null, [
            'user_ids' => $userIds,
            'is_active' => $isActive,
            'count' => $affected
        ]);

        echo json_encode(['success' => true, 'message' => "$affected משתמשים עודכנו בהצלחה"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון משתמשים: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'bulk_update_users_fields') {
    try {
        $updates = $input['updates'] ?? [];

        if (empty($updates) || !is_array($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'רשימת עדכונים נדרשת']);
            exit;
        }

        $db->beginTransaction();
        $successCount = 0;

        foreach ($updates as $update) {
            $userId = $update['user_id'] ?? null;
            if (!$userId) continue;

            $setParts = [];
            $params = [];

            // Build UPDATE query dynamically based on provided fields
            if (isset($update['full_name'])) {
                $setParts[] = "full_name = ?";
                $params[] = $update['full_name'];
            }
            if (isset($update['email'])) {
                $setParts[] = "email = ?";
                $params[] = $update['email'];
            }
            if (isset($update['phone'])) {
                $setParts[] = "phone = ?";
                $params[] = $update['phone'];
            }
            if (isset($update['is_active'])) {
                $isActive = (int)$update['is_active'];
                $setParts[] = "is_blocked = ?";
                $params[] = $isActive === 1 ? 0 : 1; // is_blocked is inverse of is_active
            }

            if (empty($setParts)) continue;

            $setParts[] = "updated_at = datetime('now')";
            $params[] = $userId;

            $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                $successCount++;
            }
        }

        $db->commit();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'bulk_edit', 'user', null, null, [
            'count' => $successCount,
            'total' => count($updates)
        ]);

        echo json_encode(['success' => true, 'message' => "$successCount משתמשים עודכנו בהצלחה"]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון משתמשים: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'batch_delete_users') {
    try {
        $userIds = $input['user_ids'] ?? [];

        if (empty($userIds) || !is_array($userIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'רשימת משתמשים נדרשת']);
            exit;
        }

        // Soft delete - just mark as inactive
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = datetime('now') WHERE id IN ($placeholders)");
        $stmt->execute($userIds);

        $affected = $stmt->rowCount();

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'batch_delete', 'user', null, null, [
            'user_ids' => $userIds,
            'count' => $affected
        ]);

        echo json_encode(['success' => true, 'message' => "$affected משתמשים נמחקו בהצלחה"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת משתמשים: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'export_users_csv') {
    try {
        $userIds = $input['user_ids'] ?? [];

        if (empty($userIds) || !is_array($userIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'רשימת משתמשים נדרשת']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $db->prepare("
            SELECT
                u.id,
                u.tz,
                u.full_name,
                u.email,
                u.phone,
                u.is_blocked,
                u.is_active,
                u.last_login,
                u.created_at,
                COUNT(DISTINCT ut.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN ut.status IN ('completed', 'approved') THEN ut.id END) as completed_tasks
            FROM users u
            LEFT JOIN user_tasks ut ON u.id = ut.user_id
            WHERE u.id IN ($placeholders)
            GROUP BY u.id
            ORDER BY u.id DESC
        ");
        $stmt->execute($userIds);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate CSV
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
        $csv .= "מספר זהוי,שם מלא,אימייל,טלפון,סטטוס,סך משימות,משימות שהושלמו,כניסה אחרונה,תאריך הוספה\n";

        foreach ($users as $user) {
            $status = $user['is_active'] && !$user['is_blocked'] ? 'פעיל' : 'לא פעיל';
            $lastLogin = $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : '-';
            $createdAt = date('Y-m-d H:i', strtotime($user['created_at']));

            $csv .= implode(',', [
                $user['tz'],
                '"' . ($user['full_name'] ?: '-') . '"',
                '"' . ($user['email'] ?: '-') . '"',
                '"' . ($user['phone'] ?: '-') . '"',
                $status,
                $user['total_tasks'],
                $user['completed_tasks'],
                $lastLogin,
                $createdAt
            ]) . "\n";
        }

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'export', 'user', null, null, [
            'user_ids' => $userIds,
            'count' => count($users)
        ]);

        echo json_encode(['success' => true, 'csv' => $csv]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בייצוא: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'export_all_users_csv') {
    try {
        $stmt = $db->query("
            SELECT
                u.id,
                u.tz,
                u.full_name,
                u.email,
                u.phone,
                u.is_blocked,
                u.is_active,
                u.last_login,
                u.created_at,
                COUNT(DISTINCT ut.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN ut.status IN ('completed', 'approved') THEN ut.id END) as completed_tasks
            FROM users u
            LEFT JOIN user_tasks ut ON u.id = ut.user_id
            GROUP BY u.id
            ORDER BY u.id DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate CSV
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
        $csv .= "מספר זהוי,שם מלא,אימייל,טלפון,סטטוס,סך משימות,משימות שהושלמו,כניסה אחרונה,תאריך הוספה\n";

        foreach ($users as $user) {
            $status = $user['is_active'] && !$user['is_blocked'] ? 'פעיל' : 'לא פעיל';
            $lastLogin = $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : '-';
            $createdAt = date('Y-m-d H:i', strtotime($user['created_at']));

            $csv .= implode(',', [
                $user['tz'],
                '"' . ($user['full_name'] ?: '-') . '"',
                '"' . ($user['email'] ?: '-') . '"',
                '"' . ($user['phone'] ?: '-') . '"',
                $status,
                $user['total_tasks'],
                $user['completed_tasks'],
                $lastLogin,
                $createdAt
            ]) . "\n";
        }

        // Log activity
        logActivity($db, $_SESSION['admin_user_id'], 'export_all', 'user', null, null, ['count' => count($users)]);

        echo json_encode(['success' => true, 'csv' => $csv]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה בייצוא: ' . $e->getMessage()]);
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
                    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE SET NULL,
                    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
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
                    FOREIGN KEY (assigned_by) REFERENCES admin_users(id) ON DELETE SET NULL,
                    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
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
                    FOREIGN KEY (task_id) REFERENCES course_tasks(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
                )
            ");
        }
    } catch (Exception $e) {
        // Tables might already exist, ignore error
    }
}

// Initialize tables on any course management request
if (strpos($action, 'task') !== false || strpos($action, 'material') !== false ||
    $action === 'get_all_users_with_progress' || $action === 'get_user_detail' ||
    $action === 'get_course_analytics' || $action === 'get_progress_report' || $action === 'get_task_stats') {
    initializeCourseTables($db);
}

// Get all users with their progress
if ($action === 'get_all_users_with_progress') {
    try {
        // Support filtering by archive status
        // archived=1 -> only archived users
        // archived=0 or not set -> only non-archived users
        $archivedFilter = isset($input['archived']) ? (int)$input['archived'] : 0;

        // Check if is_archived column exists
        $columnsResult = $db->query("PRAGMA table_info(users)");
        $columns = $columnsResult->fetchAll(PDO::FETCH_ASSOC);
        $hasArchivedColumn = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'is_archived') {
                $hasArchivedColumn = true;
                break;
            }
        }

        // Build query based on whether is_archived column exists
        if ($hasArchivedColumn) {
            $whereClause = "WHERE COALESCE(u.is_archived, 0) = $archivedFilter";
            $selectArchived = "COALESCE(u.is_archived, 0) as is_archived,";
        } else {
            // If column doesn't exist, show all users when archived=0, none when archived=1
            if ($archivedFilter === 1) {
                // Return empty result for archived filter if column doesn't exist
                echo json_encode([
                    'success' => true,
                    'users' => [],
                    'archived' => $archivedFilter,
                    'migration_needed' => true
                ]);
                exit;
            }
            $whereClause = "";
            $selectArchived = "0 as is_archived,";
        }

        $query = "
            SELECT
                u.id,
                u.tz,
                u.full_name,
                u.is_blocked,
                u.is_active,
                $selectArchived
                u.last_login,
                COUNT(DISTINCT ut.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN ut.status IN ('completed', 'approved') THEN ut.id END) as completed_tasks,
                COUNT(DISTINCT CASE WHEN ut.status = 'pending' THEN ut.id END) as pending_tasks,
                COUNT(DISTINCT CASE WHEN ut.status = 'needs_review' THEN ut.id END) as review_tasks
            FROM users u
            LEFT JOIN user_tasks ut ON u.id = ut.user_id
            $whereClause
            GROUP BY u.id
            ORDER BY u.id DESC
        ";

        $stmt = $db->query($query);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch tasks for each user
        foreach ($users as &$user) {
            $tasksStmt = $db->prepare("
                SELECT task_id, status
                FROM user_tasks
                WHERE user_id = ?
            ");
            $tasksStmt->execute([$user['id']]);
            $user['tasks'] = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'users' => $users,
            'archived' => $archivedFilter,
            'migration_needed' => !$hasArchivedColumn
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
        $stmt = $db->prepare("SELECT id, tz, full_name, is_blocked, is_active, last_login FROM users WHERE id = ?");
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
                ut.feedback,
                ut.submission_text,
                ut.submission_file_path,
                ut.submitted_at,
                ut.reviewed_at,
                ut.grade,
                ct.title,
                ct.description,
                ct.task_type,
                ct.estimated_duration,
                ct.points,
                ct.sequence_order,
                ct.form_id
            FROM user_tasks ut
            JOIN course_tasks ct ON ut.task_id = ct.id
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

// Get single task for editing
if ($action === 'get_task') {
    $taskId = $input['task_id'] ?? '';

    if (empty($taskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה משימה']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM course_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'המשימה לא נמצאה']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'task' => $task
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// List all forms
if ($action === 'list_forms') {
    try {
        $stmt = $db->query("SELECT id, title FROM forms WHERE is_active = 1 ORDER BY created_at DESC");
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'forms' => $forms
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// Delete task
if ($action === 'delete_task') {
    $taskId = $input['task_id'] ?? '';

    if (empty($taskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה משימה']);
        exit;
    }

    try {
        // Check if task has assignments
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_tasks WHERE task_id = ?");
        $stmt->execute([$taskId]);
        $assignmentsCount = $stmt->fetchColumn();

        if ($assignmentsCount > 0) {
            // Don't delete, just deactivate
            $stmt = $db->prepare("UPDATE course_tasks SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$taskId]);
            echo json_encode([
                'success' => true,
                'message' => 'המשימה הושבתה (לא נמחקה כיוון שיש לה הקצאות קיימות)'
            ]);
        } else {
            // Safe to delete
            $stmt = $db->prepare("DELETE FROM course_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            echo json_encode([
                'success' => true,
                'message' => 'המשימה נמחקה בהצלחה'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת המשימה: ' . $e->getMessage()]);
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
        $adminId = $_SESSION['admin_user_id'] ?? null;
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

            // Try to create notification (optional - won't fail assignment if notifications table doesn't exist)
            try {
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
            } catch (Exception $notifError) {
                // Notification failed but assignment succeeded - this is okay
                // Notifications table may not exist yet
            }

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
// MATERIALS MANAGEMENT
// ============================================

// Get all materials
if ($action === 'get_all_materials') {
    try {
        // Check if course_materials table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='course_materials'")->fetch();

        $materials = [];
        if ($tableCheck) {
            $stmt = $db->query("
                SELECT m.*, t.title as task_title
                FROM course_materials m
                LEFT JOIN course_tasks t ON m.task_id = t.id
                ORDER BY m.created_at DESC
            ");
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'materials' => $materials
        ]);
    } catch (Exception $e) {
        error_log("Get materials error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// Get materials by task
if ($action === 'get_materials_by_task') {
    $taskId = $input['task_id'] ?? '';

    if (empty($taskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה משימה']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT * FROM course_materials
            WHERE task_id = ?
            ORDER BY display_order, created_at
        ");
        $stmt->execute([$taskId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'materials' => $materials
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// Create material (metadata only, file upload handled separately)
if ($action === 'create_material') {
    $taskId = $input['task_id'] ?? null;
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $materialType = $input['material_type'] ?? '';
    $filePath = $input['file_path'] ?? '';
    $externalUrl = $input['external_url'] ?? '';
    $contentText = $input['content_text'] ?? '';
    $displayOrder = $input['display_order'] ?? 0;
    $isRequired = $input['is_required'] ?? 0;

    if (empty($title) || empty($materialType)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסרים שדות חובה']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO course_materials
            (task_id, title, description, material_type, file_path, external_url, content_text, display_order, is_required, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([
            $taskId,
            $title,
            $description,
            $materialType,
            $filePath,
            $externalUrl,
            $contentText,
            $displayOrder,
            $isRequired,
            $_SESSION['admin_user_id']
        ]);

        $materialId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'חומר נוסף בהצלחה',
            'material_id' => $materialId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// Update material
if ($action === 'update_material') {
    $materialId = $input['material_id'] ?? '';
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $displayOrder = $input['display_order'] ?? 0;
    $isRequired = $input['is_required'] ?? 0;

    if (empty($materialId) || empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסרים שדות חובה']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE course_materials
            SET title = ?, description = ?, display_order = ?, is_required = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $displayOrder, $isRequired, $materialId]);

        echo json_encode([
            'success' => true,
            'message' => 'חומר עודכן בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// Delete material
if ($action === 'delete_material') {
    $materialId = $input['material_id'] ?? '';

    if (empty($materialId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר מזהה חומר']);
        exit;
    }

    try {
        // Get file path before deleting
        $stmt = $db->prepare("SELECT file_path FROM course_materials WHERE id = ?");
        $stmt->execute([$materialId]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete from database
        $stmt = $db->prepare("DELETE FROM course_materials WHERE id = ?");
        $stmt->execute([$materialId]);

        // Delete physical file if exists
        if ($material && !empty($material['file_path']) && file_exists($material['file_path'])) {
            unlink($material['file_path']);
        }

        echo json_encode([
            'success' => true,
            'message' => 'חומר נמחק בהצלחה'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// REPORTS AND ANALYTICS
// ============================================

// Get comprehensive course analytics
if ($action === 'get_course_analytics') {
    try {
        // Total users
        $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

        // Total tasks
        $totalTasks = $db->query("SELECT COUNT(*) FROM course_tasks WHERE is_active = 1")->fetchColumn();

        // Total assignments
        $totalAssignments = $db->query("SELECT COUNT(*) FROM user_tasks")->fetchColumn();

        // Completion stats
        $completedAssignments = $db->query("SELECT COUNT(*) FROM user_tasks WHERE status IN ('completed', 'approved')")->fetchColumn();
        $pendingAssignments = $db->query("SELECT COUNT(*) FROM user_tasks WHERE status = 'pending'")->fetchColumn();
        $inProgressAssignments = $db->query("SELECT COUNT(*) FROM user_tasks WHERE status = 'in_progress'")->fetchColumn();
        $needsReviewAssignments = $db->query("SELECT COUNT(*) FROM user_tasks WHERE status = 'needs_review'")->fetchColumn();

        // Average completion rate
        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 2) : 0;

        // Active users (users with at least one assignment)
        $activeUsers = $db->query("SELECT COUNT(DISTINCT user_id) FROM user_tasks")->fetchColumn();

        // Task completion by type
        $stmt = $db->query("
            SELECT
                ct.task_type,
                COUNT(ut.id) as total,
                SUM(CASE WHEN ut.status IN ('completed', 'approved') THEN 1 ELSE 0 END) as completed
            FROM user_tasks ut
            JOIN course_tasks ct ON ut.task_id = ct.id
            GROUP BY ct.task_type
        ");
        $taskTypeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent activity (last 7 days)
        $stmt = $db->query("
            SELECT DATE(assigned_at) as date, COUNT(*) as count
            FROM user_tasks
            WHERE assigned_at >= date('now', '-7 days')
            GROUP BY DATE(assigned_at)
            ORDER BY date DESC
        ");
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top performers (users with highest completion rate)
        $stmt = $db->query("
            SELECT
                u.id,
                u.full_name,
                u.tz,
                COUNT(ut.id) as total_tasks,
                SUM(CASE WHEN ut.status IN ('completed', 'approved') THEN 1 ELSE 0 END) as completed_tasks,
                ROUND(SUM(CASE WHEN ut.status IN ('completed', 'approved') THEN 1 ELSE 0 END) * 100.0 / COUNT(ut.id), 2) as completion_rate
            FROM users u
            JOIN user_tasks ut ON u.id = ut.user_id
            GROUP BY u.id
            HAVING COUNT(ut.id) > 0
            ORDER BY completion_rate DESC, completed_tasks DESC
            LIMIT 10
        ");
        $topPerformers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'analytics' => [
                'totals' => [
                    'users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'tasks' => $totalTasks,
                    'assignments' => $totalAssignments,
                    'completion_rate' => $completionRate
                ],
                'status_breakdown' => [
                    'completed' => $completedAssignments,
                    'pending' => $pendingAssignments,
                    'in_progress' => $inProgressAssignments,
                    'needs_review' => $needsReviewAssignments
                ],
                'task_type_stats' => $taskTypeStats,
                'recent_activity' => $recentActivity,
                'top_performers' => $topPerformers
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// Get detailed progress report with filters
if ($action === 'get_progress_report') {
    $statusFilter = $input['status_filter'] ?? null;
    $taskFilter = $input['task_filter'] ?? null;
    $searchTerm = $input['search'] ?? '';

    try {
        $sql = "
            SELECT
                u.id as user_id,
                u.full_name,
                u.tz,
                COUNT(ut.id) as total_assignments,
                SUM(CASE WHEN ut.status = 'completed' OR ut.status = 'approved' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN ut.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN ut.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN ut.status = 'needs_review' THEN 1 ELSE 0 END) as needs_review,
                ROUND(SUM(CASE WHEN ut.status IN ('completed', 'approved') THEN 1 ELSE 0 END) * 100.0 / COUNT(ut.id), 2) as completion_percentage
            FROM users u
            LEFT JOIN user_tasks ut ON u.id = ut.user_id
        ";

        $conditions = [];
        $params = [];

        if ($statusFilter) {
            $conditions[] = "ut.status = ?";
            $params[] = $statusFilter;
        }

        if ($taskFilter) {
            $conditions[] = "ut.task_id = ?";
            $params[] = $taskFilter;
        }

        if (!empty($searchTerm)) {
            $conditions[] = "(u.full_name LIKE ? OR u.tz LIKE ?)";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }

        if (count($conditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " GROUP BY u.id ORDER BY completion_percentage DESC, completed DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'report' => $report
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// Get task completion statistics
if ($action === 'get_task_stats') {
    try {
        $stmt = $db->query("
            SELECT
                ct.id,
                ct.title,
                ct.task_type,
                COUNT(ut.id) as assigned_count,
                SUM(CASE WHEN ut.status IN ('completed', 'approved') THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN ut.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN ut.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN ut.status = 'needs_review' THEN 1 ELSE 0 END) as needs_review_count,
                ROUND(SUM(CASE WHEN ut.status IN ('completed', 'approved') THEN 1 ELSE 0 END) * 100.0 / COUNT(ut.id), 2) as completion_rate
            FROM course_tasks ct
            LEFT JOIN user_tasks ut ON ct.id = ut.task_id
            WHERE ct.is_active = 1
            GROUP BY ct.id
            ORDER BY assigned_count DESC
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'task_stats' => $stats
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// MIGRATIONS
// ============================================

if ($action === 'run_migration') {
    $migrationName = $input['migration'] ?? '';

    if (empty($migrationName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'חסר שם migration']);
        exit;
    }

    $migrationFile = __DIR__ . '/../migrate_' . $migrationName . '.php';

    if (!file_exists($migrationFile)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'קובץ migration לא נמצא']);
        exit;
    }

    try {
        // Capture output
        ob_start();
        include $migrationFile;
        $output = ob_get_clean();

        echo json_encode([
            'success' => true,
            'message' => 'Migration הושלם בהצלחה',
            'output' => $output
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// DEFAULT
// ============================================

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'פעולה לא נמצאה']);
?>
