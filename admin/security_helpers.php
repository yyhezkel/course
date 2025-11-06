<?php
/**
 * Security Helper Functions
 * Provides CSRF protection, password validation, and brute force protection
 */

// ============================================
// CSRF Protection
// ============================================

/**
 * Generate CSRF token and store in session
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token (generates if not exists)
 */
function getCsrfToken() {
    return generateCsrfToken();
}

// ============================================
// Password Validation
// ============================================

/**
 * Validate password strength
 * Requirements:
 * - Minimum 12 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one special character
 * - Not a common password
 *
 * @param string $password
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePasswordStrength($password) {
    $errors = [];

    // Check minimum length
    if (strlen($password) < 12) {
        $errors[] = 'הסיסמה חייבת להיות לפחות 12 תווים';
    }

    // Check for uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'הסיסמה חייבת לכלול לפחות אות גדולה אחת באנגלית';
    }

    // Check for lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'הסיסמה חייבת לכלול לפחות אות קטנה אחת באנגלית';
    }

    // Check for number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'הסיסמה חייבת לכלול לפחות ספרה אחת';
    }

    // Check for special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'הסיסמה חייבת לכלול לפחות תו מיוחד אחד (!@#$%^&* וכו\')';
    }

    // Check against common passwords
    if (isCommonPassword($password)) {
        $errors[] = 'הסיסמה שנבחרה נפוצה מדי. אנא בחר סיסמה יותר מורכבת';
    }

    if (empty($errors)) {
        return ['valid' => true, 'message' => 'הסיסמה עומדת בדרישות האבטחה'];
    }

    return ['valid' => false, 'message' => implode('. ', $errors)];
}

/**
 * Check if password is in common passwords list
 */
function isCommonPassword($password) {
    $commonPasswords = [
        'password', 'password123', 'password1234', '12345678', '123456789',
        '1234567890', 'qwerty', 'qwertyuiop', 'abc123', 'abcd1234',
        'admin', 'admin123', 'admin1234', 'administrator', 'root',
        'welcome', 'welcome123', 'letmein', 'monkey', 'dragon',
        'master', 'sunshine', 'princess', 'football', 'iloveyou',
        '123123', '000000', '111111', '123321', '654321',
        'qwerty123', 'qwerty12345', 'password!', 'Password1', 'Password123',
        'Admin123!', 'Welcome123', 'Pass@123', 'Pass1234', 'Test1234',
        'asdf1234', 'zxcv1234', 'temp1234', 'demo1234', 'user1234'
    ];

    return in_array(strtolower($password), $commonPasswords);
}

// ============================================
// Brute Force Protection
// ============================================

/**
 * Track failed login attempt
 *
 * @param PDO $db Database connection
 * @param string $username Username attempted
 * @param string $ipAddress IP address of attempt
 */
function trackFailedLogin($db, $username, $ipAddress) {
    $stmt = $db->prepare("
        INSERT INTO failed_login_attempts (username, ip_address, attempt_time)
        VALUES (?, ?, datetime('now'))
    ");
    $stmt->execute([$username, $ipAddress]);
}

/**
 * Get failed login attempts count within time window
 *
 * @param PDO $db Database connection
 * @param string $username Username to check
 * @param string $ipAddress IP address to check
 * @param int $minutes Time window in minutes (default 30)
 * @return int Number of failed attempts
 */
function getFailedLoginAttempts($db, $username, $ipAddress, $minutes = 30) {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM failed_login_attempts
        WHERE (username = ? OR ip_address = ?)
        AND attempt_time > datetime('now', '-' || ? || ' minutes')
    ");
    $stmt->execute([$username, $ipAddress, $minutes]);
    return (int)$stmt->fetchColumn();
}

/**
 * Clear failed login attempts for user/IP
 *
 * @param PDO $db Database connection
 * @param string $username Username to clear
 * @param string $ipAddress IP address to clear
 */
function clearFailedLoginAttempts($db, $username, $ipAddress) {
    $stmt = $db->prepare("
        DELETE FROM failed_login_attempts
        WHERE username = ? OR ip_address = ?
    ");
    $stmt->execute([$username, $ipAddress]);
}

/**
 * Calculate progressive delay based on failed attempts
 *
 * @param int $attempts Number of failed attempts
 * @return int Delay in seconds
 */
function calculateLoginDelay($attempts) {
    if ($attempts <= 1) {
        return 0;
    }

    // Progressive delays: 0s, 1s, 2s, 4s, 8s, 16s, 30s (cap at 30s)
    $delays = [0, 1, 2, 4, 8, 16, 30];
    $index = min($attempts - 1, count($delays) - 1);
    return $delays[$index];
}

/**
 * Check if account should be locked due to too many failed attempts
 *
 * @param PDO $db Database connection
 * @param string $username Username to check
 * @param string $ipAddress IP address to check
 * @return array ['locked' => bool, 'attempts' => int, 'delay' => int]
 */
function checkBruteForceProtection($db, $username, $ipAddress) {
    $attempts = getFailedLoginAttempts($db, $username, $ipAddress, 30);
    $delay = calculateLoginDelay($attempts);

    // Lock account after 5 failed attempts
    $locked = $attempts >= 5;

    return [
        'locked' => $locked,
        'attempts' => $attempts,
        'delay' => $delay
    ];
}

/**
 * Apply progressive delay (sleep)
 *
 * @param int $seconds Seconds to delay
 */
function applyLoginDelay($seconds) {
    if ($seconds > 0) {
        sleep($seconds);
    }
}

/**
 * Lock admin account due to too many failed attempts
 *
 * @param PDO $db Database connection
 * @param string $username Username to lock
 */
function lockAdminAccount($db, $username) {
    $stmt = $db->prepare("
        UPDATE admin_users
        SET is_active = 0,
            updated_at = datetime('now')
        WHERE username = ?
    ");
    $stmt->execute([$username]);
}

/**
 * Clean up old failed login attempts (older than 24 hours)
 *
 * @param PDO $db Database connection
 */
function cleanupOldFailedAttempts($db) {
    $db->exec("
        DELETE FROM failed_login_attempts
        WHERE attempt_time < datetime('now', '-24 hours')
    ");
}

// ============================================
// Rate Limiting
// ============================================

/**
 * Check rate limit for IP address
 *
 * @param string $ipAddress IP address to check
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowMinutes Time window in minutes
 * @return bool True if rate limit exceeded
 */
function isRateLimited($ipAddress, $maxAttempts = 20, $windowMinutes = 5) {
    // This is a simple session-based rate limiting
    // For production, consider using Redis or Memcached

    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }

    $key = $ipAddress . '_' . floor(time() / ($windowMinutes * 60));

    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = 0;
    }

    $_SESSION['rate_limit'][$key]++;

    return $_SESSION['rate_limit'][$key] > $maxAttempts;
}

// ============================================
// Security Logging
// ============================================

/**
 * Log security event
 *
 * @param PDO $db Database connection
 * @param string $eventType Type of security event
 * @param string $username Username involved (if any)
 * @param string $details Additional details
 */
function logSecurityEvent($db, $eventType, $username = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $db->prepare("
        INSERT INTO activity_log (admin_user_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
        SELECT
            (SELECT id FROM admin_users WHERE username = ? LIMIT 1),
            ?,
            'security_event',
            NULL,
            ?,
            NULL,
            ?,
            ?
    ");

    $stmt->execute([
        $username,
        $eventType,
        $details,
        $ip,
        $userAgent
    ]);
}
