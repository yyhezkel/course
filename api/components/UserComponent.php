<?php
/**
 * UserComponent
 * Handles user-related API operations
 */

class UserComponent extends BaseComponent {

    public function handleAction($action) {
        switch ($action) {
            case 'get_user_info':
                return $this->getUserInfo();
            case 'update_username':
                return $this->updateUsername();
            case 'update_password':
                return $this->updatePassword();
            case 'setup_credentials':
                return $this->setupCredentials();
            case 'check_needs_credential_setup':
                return $this->checkNeedsCredentialSetup();
            default:
                $this->sendError(404, 'פעולה לא נתמכת');
        }
    }

    /**
     * Get user info
     */
    private function getUserInfo() {
        $this->requireAuth();

        $userId = $this->session['user_id'];

        try {
            $stmt = $this->db->prepare("SELECT id, tz, full_name, username, id_type, last_login FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $this->sendSuccess(['user' => $user]);
            } else {
                $this->sendError(404, 'משתמש לא נמצא.');
            }
        } catch (Exception $e) {
            $this->sendError(500, 'שגיאה בשליפת נתוני משתמש.');
        }
    }

    /**
     * Update username
     */
    private function updateUsername() {
        $this->requireAuth();

        $userId = $this->session['user_id'];
        $username = $this->getRequiredParam('username', 'שם משתמש חובה.');

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $this->sendError(400, 'שם משתמש לא תקין. השתמש רק באותיות אנגליות, מספרים וקו תחתון (_), 3-20 תווים.');
        }

        try {
            // Check if username exists for another user
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                $this->sendError(409, 'שם משתמש זה כבר תפוס. נא לבחור שם משתמש אחר.');
            }

            // Update username
            $stmt = $this->db->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $userId]);

            $this->sendSuccess(['username' => $username], 'שם המשתמש עודכן בהצלחה!');
        } catch (Exception $e) {
            $this->sendError(500, 'שגיאה בעדכון שם משתמש: ' . $e->getMessage());
        }
    }

    /**
     * Update password
     */
    private function updatePassword() {
        $this->requireAuth();

        $userId = $this->session['user_id'];
        $password = $this->getRequiredParam('password', 'סיסמה חובה.');

        if (strlen($password) < 6) {
            $this->sendError(400, 'הסיסמה חייבת להכיל לפחות 6 תווים.');
        }

        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);

            $this->sendSuccess([], 'הסיסמה עודכנה בהצלחה!');
        } catch (Exception $e) {
            $this->sendError(500, 'שגיאה בעדכון סיסמה: ' . $e->getMessage());
        }
    }

    /**
     * Setup credentials (username + password)
     */
    private function setupCredentials() {
        $this->requireAuth();

        $userId = $this->session['user_id'];
        $username = $this->getParam('username', '');
        $password = $this->getParam('password', '');

        // Validate username
        if (empty($username) || strlen($username) < 3) {
            $this->sendError(400, 'שם המשתמש חייב להכיל לפחות 3 תווים.');
        }

        // Validate password
        if (empty($password) || strlen($password) < 6) {
            $this->sendError(400, 'הסיסמה חייבת להכיל לפחות 6 תווים.');
        }

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_\x{0590}-\x{05FF}]+$/u', $username)) {
            $this->sendError(400, 'שם המשתמש יכול להכיל רק אותיות, מספרים וקו תחתון.');
        }

        try {
            // Check if credentials already set
            $stmt = $this->db->prepare("SELECT username, password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->sendError(404, 'משתמש לא נמצא.');
            }

            if (!empty($user['username']) && !empty($user['password_hash'])) {
                $this->sendError(400, 'פרטי התחברות כבר הוגדרו עבור משתמש זה.');
            }

            // Check if username taken
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                $this->sendError(409, 'שם משתמש זה כבר תפוס. נא לבחור שם משתמש אחר.');
            }

            // Hash password and update
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $this->db->prepare("UPDATE users SET username = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$username, $passwordHash, $userId]);

            $this->sendSuccess(['username' => $username], 'פרטי ההתחברות נשמרו בהצלחה!');
        } catch (Exception $e) {
            $this->sendError(500, 'שגיאה בשמירת פרטי התחברות: ' . $e->getMessage());
        }
    }

    /**
     * Check if user needs credential setup
     */
    private function checkNeedsCredentialSetup() {
        $this->requireAuth();

        $userId = $this->session['user_id'];

        try {
            $stmt = $this->db->prepare("SELECT username, password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $needsSetup = empty($user['username']) || empty($user['password_hash']);
                $this->sendSuccess(['needs_setup' => $needsSetup]);
            } else {
                $this->sendError(404, 'משתמש לא נמצא.');
            }
        } catch (Exception $e) {
            $this->sendError(500, 'שגיאה בבדיקת סטטוס משתמש.');
        }
    }
}
