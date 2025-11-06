<?php
/**
 * Admin Authentication System
 * Handles admin login, logout, and session management
 * Enhanced with CSRF protection, brute force protection, and strong password policies
 */

// Disable error display to prevent HTML output before JSON
// Errors will still be logged to the error log
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . getAdminOrigin());
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$db = getDbConnection();

// Clean up old failed login attempts periodically (1% chance per request)
if (rand(1, 100) === 1) {
    cleanupOldFailedAttempts($db);
}

// ============================================
// Helper Functions
// ============================================

function getAdminOrigin() {
    return 'https://qr.bot4wa.com';
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function requireAuth() {
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'נדרש אימות מנהל']);
        exit;
    }
}

function logActivity($db, $adminUserId, $action, $entityType, $entityId = null, $oldValue = null, $newValue = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $db->prepare("
        INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $adminUserId,
        $action,
        $entityType,
        $entityId,
        $oldValue ? json_encode($oldValue) : null,
        $newValue ? json_encode($newValue) : null,
        $ip,
        $userAgent
    ]);
}

// ============================================
// GET CSRF TOKEN
// ============================================

if ($action === 'get_csrf_token') {
    echo json_encode([
        'success' => true,
        'csrf_token' => getCsrfToken()
    ]);
    exit;
}

// ============================================
// LOGIN
// ============================================

if ($action === 'login') {
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Validate CSRF token
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'אסימון אבטחה לא חוקי. נא לרענן את הדף ולנסות שוב']);
        logSecurityEvent($db, 'csrf_validation_failed', $username, "IP: $ipAddress");
        exit;
    }

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'שם משתמש וסיסמה נדרשים']);
        exit;
    }

    // Check brute force protection
    $bruteForceCheck = checkBruteForceProtection($db, $username, $ipAddress);

    if ($bruteForceCheck['locked']) {
        http_response_code(429);
        logSecurityEvent($db, 'account_locked', $username, "Too many failed attempts from IP: $ipAddress");
        echo json_encode([
            'success' => false,
            'message' => 'החשבון נחסם זמנית עקב ניסיונות התחברות כושלים מרובים. נסה שוב בעוד 30 דקות או צור קשר עם מנהל המערכת.',
            'locked' => true,
            'attempts' => $bruteForceCheck['attempts']
        ]);
        exit;
    }

    // Apply progressive delay if there are failed attempts
    if ($bruteForceCheck['delay'] > 0) {
        applyLoginDelay($bruteForceCheck['delay']);
    }

    // Get admin user
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists and is active
    if (!$admin || $admin['is_active'] != 1) {
        // Track failed attempt
        trackFailedLogin($db, $username, $ipAddress);
        logSecurityEvent($db, 'failed_login_attempt', $username, "User not found or inactive. IP: $ipAddress");

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'שם משתמש או סיסמה שגויים']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        // Track failed attempt
        trackFailedLogin($db, $username, $ipAddress);
        logSecurityEvent($db, 'failed_login_attempt', $username, "Invalid password. IP: $ipAddress");

        // Check if account should be locked after this failed attempt
        $newAttempts = getFailedLoginAttempts($db, $username, $ipAddress, 30);
        if ($newAttempts >= 5) {
            lockAdminAccount($db, $username);
            logSecurityEvent($db, 'account_auto_locked', $username, "Account locked after $newAttempts failed attempts");
        }

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'שם משתמש או סיסמה שגויים']);
        exit;
    }

    // Successful login - clear failed attempts
    clearFailedLoginAttempts($db, $username, $ipAddress);

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Create session
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_user_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_full_name'] = $admin['full_name'];
    $_SESSION['admin_login_time'] = time();

    // Update last login
    $db->prepare("UPDATE admin_users SET last_login = datetime('now') WHERE id = ?")
       ->execute([$admin['id']]);

    // Log activity
    logActivity($db, $admin['id'], 'login', 'admin_user', $admin['id']);
    logSecurityEvent($db, 'successful_login', $username, "IP: $ipAddress");

    echo json_encode([
        'success' => true,
        'message' => 'התחברת בהצלחה',
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'full_name' => $admin['full_name'],
            'role' => $admin['role']
        ]
    ]);
    exit;
}

// ============================================
// LOGOUT
// ============================================

if ($action === 'logout') {
    if (isset($_SESSION['admin_user_id'])) {
        logActivity($db, $_SESSION['admin_user_id'], 'logout', 'admin_user', $_SESSION['admin_user_id']);
    }

    session_destroy();

    echo json_encode(['success' => true, 'message' => 'התנתקת בהצלחה']);
    exit;
}

// ============================================
// CHECK SESSION
// ============================================

if ($action === 'check') {
    if (isAdminLoggedIn()) {
        // Check session timeout (2 hours)
        if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time'] > 7200)) {
            session_destroy();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'פג תוקף ההתחברות']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'admin' => [
                'id' => $_SESSION['admin_user_id'],
                'username' => $_SESSION['admin_username'],
                'full_name' => $_SESSION['admin_full_name'],
                'role' => $_SESSION['admin_role']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'authenticated' => false]);
    }
    exit;
}

// ============================================
// CHANGE PASSWORD
// ============================================

if ($action === 'change_password') {
    requireAuth();

    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';

    // Validate CSRF token
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'אסימון אבטחה לא חוקי. נא לרענן את הדף ולנסות שוב']);
        exit;
    }

    if (empty($currentPassword) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'נדרש סיסמה נוכחית וסיסמה חדשה']);
        exit;
    }

    // Validate password strength
    $passwordValidation = validatePasswordStrength($newPassword);
    if (!$passwordValidation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $passwordValidation['message']
        ]);
        exit;
    }

    // Get current admin
    $stmt = $db->prepare("SELECT username, password_hash FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify current password
    if (!password_verify($currentPassword, $admin['password_hash'])) {
        http_response_code(400);
        logSecurityEvent($db, 'password_change_failed', $admin['username'], 'Invalid current password');
        echo json_encode(['success' => false, 'message' => 'סיסמה נוכחית שגויה']);
        exit;
    }

    // Update password
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->prepare("UPDATE admin_users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?")
       ->execute([$newHash, $_SESSION['admin_user_id']]);

    // Log activity
    logActivity($db, $_SESSION['admin_user_id'], 'change_password', 'admin_user', $_SESSION['admin_user_id']);
    logSecurityEvent($db, 'password_changed', $admin['username'], 'Password changed successfully');

    echo json_encode(['success' => true, 'message' => 'סיסמה שונתה בהצלחה']);
    exit;
}

// ============================================
// DEFAULT
// ============================================

// Only output default response if this file is called directly (not included)
if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'פעולה לא נמצאה']);
}