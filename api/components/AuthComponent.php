<?php
/**
 * AuthComponent
 * Handles authentication-related API operations
 */

class AuthComponent extends BaseComponent {

    public function handleAction($action) {
        switch ($action) {
            case 'login':
                return $this->login();
            case 'logout':
                return $this->logout();
            case 'check_session':
                return $this->checkSession();
            default:
                $this->sendError(404, 'פעולה לא נתמכת');
        }
    }

    /**
     * Login handler
     */
    private function login() {
        $tz = $this->getParam('tz', '');
        $username = $this->getParam('username', '');
        $password = $this->getParam('password', '');

        $user = null;

        // METHOD 1: Login with username + password
        if (!empty($username) && !empty($password)) {
            // Validate email format
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $this->sendError(400, 'כתובת האימייל אינה תקינה.');
            }

            // Validate password strength (at least 8 chars, 1 uppercase, 1 number, 1 special char)
            if (strlen($password) < 8) {
                $this->sendError(400, 'הסיסמה חייבת להכיל לפחות 8 תווים.');
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $this->sendError(400, 'הסיסמה חייבת להכיל לפחות אות אחת גדולה.');
            }
            if (!preg_match('/[0-9]/', $password)) {
                $this->sendError(400, 'הסיסמה חייבת להכיל לפחות ספרה אחת.');
            }
            if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'",.<>\/?\\\\|`~]/', $password)) {
                $this->sendError(400, 'הסיסמה חייבת להכיל לפחות תו מיוחד אחד.');
            }

            $stmt = $this->db->prepare("SELECT id, is_blocked, failed_attempts, id_type, password_hash, tz FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password
            if ($user && !empty($user['password_hash'])) {
                if (!password_verify($password, $user['password_hash'])) {
                    $user = null;
                }
            } else {
                $user = null;
            }

            if (!$user) {
                $this->sendError(401, 'אימייל או סיסמה שגויים.');
            }

            $tz = $user['tz'];
        }
        // METHOD 2: Login with personal number (tz) only - legacy method
        else if (!empty($tz)) {
            // Validate ID format
            if (!is_numeric($tz)) {
                $this->sendError(400, 'מספר זהוי לא תקין.');
            }

            $idLength = strlen($tz);
            if ($idLength !== 7 && $idLength !== 9) {
                $this->sendError(400, 'מספר זהוי חייב להכיל 7 או 9 ספרות.');
            }

            // Get user by tz
            $stmt = $this->db->prepare("SELECT id, is_blocked, failed_attempts, id_type, username, password_hash FROM users WHERE tz = ?");
            $stmt->execute([$tz]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Validate ID type
            if ($user) {
                $expectedLength = ($user['id_type'] ?? 'tz') === 'tz' ? 9 : 7;
                if ($idLength !== $expectedLength) {
                    $expectedType = $expectedLength === 9 ? 'תעודת זהות (9 ספרות)' : 'מספר אישי (7 ספרות)';
                    $this->sendError(400, "מספר זה רשום כ-$expectedType");
                }
            }
        } else {
            $this->sendError(400, 'נא למלא מספר זהוי או אימייל וסיסמה.');
        }

        if ($user) {
            if ($user['is_blocked']) {
                $this->sendError(403, 'הכניסה חסומה עבורך.');
            }

            // Reset failed attempts and update login time
            $this->db->prepare("UPDATE users SET failed_attempts = 0, last_login = ?, ip_address = ? WHERE id = ?")
                ->execute([date('Y-m-d H:i:s'), $this->userIP, $user['id']]);

            // Get assigned form
            $stmt = $this->db->prepare("SELECT form_id FROM user_forms WHERE user_id = ? AND status = 'assigned' LIMIT 1");
            $stmt->execute([$user['id']]);
            $assignedForm = $stmt->fetch(PDO::FETCH_ASSOC);
            $formId = $assignedForm ? $assignedForm['form_id'] : 1;

            // Create session
            $this->session['user_id'] = $user['id'];
            $this->session['user_tz'] = $tz;
            $this->session['authenticated'] = true;
            $this->session['login_time'] = time();
            $this->session['form_id'] = $formId;

            // Check if user needs to setup credentials
            $redirectTo = 'dashboard.html';
            $loginMethod = !empty($username) && !empty($password) ? 'credentials' : 'number';

            if ($loginMethod === 'number') {
                $stmt = $this->db->prepare("SELECT username, password_hash FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $credentialsCheck = $stmt->fetch(PDO::FETCH_ASSOC);

                if (empty($credentialsCheck['username']) || empty($credentialsCheck['password_hash'])) {
                    $redirectTo = 'setup-credentials.html';
                }
            }

            // Get previous responses
            $stmt = $this->db->prepare("SELECT question_id, answer_value, answer_json FROM form_responses WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $previousData = [];
            foreach ($responses as $response) {
                $questionId = $response['question_id'];
                if (!empty($response['answer_json'])) {
                    $previousData[$questionId] = $response['answer_json'];
                } else {
                    $previousData[$questionId] = $response['answer_value'];
                }
            }

            $this->session['cached_answers'] = $previousData;

            $this->sendSuccess([
                'user_id' => $user['id'],
                'redirect_to' => $redirectTo
            ], 'כניסה מאושרת.');

        } else {
            $this->sendError(401, 'ת.ז. או סיסמה שגויים.');
        }
    }

    /**
     * Check session status
     */
    private function checkSession() {
        if (isset($this->session['authenticated']) && $this->session['authenticated'] === true) {
            // Check session timeout
            $sessionAge = time() - ($this->session['login_time'] ?? time());

            if ($sessionAge > 1800) {
                $this->session['authenticated'] = false;
                $this->sendSuccess([
                    'authenticated' => false,
                    'session_expired' => true
                ], 'פג תוקף ההתחברות. אנא התחבר מחדש להמשך.');
            }

            // Refresh timeout
            $this->refreshSessionTimeout();

            $userId = $this->session['user_id'];
            $previousData = $this->session['cached_answers'] ?? [];

            if (empty($previousData)) {
                $stmt = $this->db->prepare("SELECT question_id, answer_value, answer_json FROM form_responses WHERE user_id = ?");
                $stmt->execute([$userId]);
                $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($responses as $response) {
                    $questionId = $response['question_id'];
                    if (!empty($response['answer_json'])) {
                        $previousData[$questionId] = $response['answer_json'];
                    } else {
                        $previousData[$questionId] = $response['answer_value'];
                    }
                }
                $this->session['cached_answers'] = $previousData;
            }

            $this->sendSuccess([
                'authenticated' => true,
                'user_id' => $userId,
                'previous_data' => $previousData,
                'first_unanswered_index' => 0
            ]);
        } else {
            $this->sendSuccess(['authenticated' => false]);
        }
    }

    /**
     * Logout handler
     */
    private function logout() {
        session_destroy();
        $this->sendSuccess([], 'יצאת מהמערכת בהצלחה.');
    }
}
