<?php
/**
 * BaseComponent
 * Base class for all API components
 */

abstract class BaseComponent {
    protected $db;
    protected $session;
    protected $input;
    protected $userIP;

    public function __construct($db, &$session, $input, $userIP) {
        $this->db = $db;
        $this->session = &$session;
        $this->input = $input;
        $this->userIP = $userIP;
    }

    /**
     * Check if user is authenticated
     */
    protected function requireAuth() {
        if (!isset($this->session['authenticated']) || $this->session['authenticated'] !== true) {
            $this->sendError(401, 'נדרש אימות. אנא התחבר מחדש.');
        }
    }

    /**
     * Check session timeout (30 minutes = 1800 seconds)
     */
    protected function checkSessionTimeout() {
        if (isset($this->session['login_time']) && (time() - $this->session['login_time'] > 1800)) {
            $this->session['authenticated'] = false;
            $this->sendError(401, 'פג תוקף ההתחברות. אנא התחבר מחדש.', ['session_expired' => true]);
        }
    }

    /**
     * Refresh session timeout
     */
    protected function refreshSessionTimeout() {
        $this->session['login_time'] = time();
    }

    /**
     * Send JSON success response and exit
     */
    protected function sendSuccess($data = [], $message = null) {
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }
        echo json_encode(array_merge($response, $data));
        exit;
    }

    /**
     * Send JSON error response and exit
     */
    protected function sendError($statusCode, $message, $additionalData = []) {
        http_response_code($statusCode);
        $response = [
            'success' => false,
            'message' => $message
        ];
        echo json_encode(array_merge($response, $additionalData));
        exit;
    }

    /**
     * Get input parameter with default value
     */
    protected function getParam($key, $default = null) {
        return $this->input[$key] ?? $default;
    }

    /**
     * Get required parameter or send error
     */
    protected function getRequiredParam($key, $errorMessage = null) {
        if (!isset($this->input[$key]) || empty($this->input[$key])) {
            $message = $errorMessage ?? "חסר פרמטר: $key";
            $this->sendError(400, $message);
        }
        return $this->input[$key];
    }

    /**
     * Log user activity
     *
     * @param int $userId User ID
     * @param string $action Action performed (e.g., 'login', 'view_task', 'submit_task')
     * @param string|null $entityType Type of entity (e.g., 'task', 'form', 'material')
     * @param int|null $entityId ID of the entity
     * @param string|null $details Additional details (JSON or text)
     */
    protected function logUserActivity($userId, $action, $entityType = null, $entityId = null, $details = null) {
        try {
            // Ensure user_activity_log table exists
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS user_activity_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    entity_type TEXT,
                    entity_id INTEGER,
                    details TEXT,
                    ip_address TEXT,
                    user_agent TEXT,
                    session_id TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

            // Create indexes if they don't exist
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_user_id ON user_activity_log(user_id)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_action ON user_activity_log(action)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_created_at ON user_activity_log(created_at)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_entity ON user_activity_log(entity_type, entity_id)");

            // Get user agent and session ID
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $sessionId = session_id();

            // Insert activity log
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $details,
                $this->userIP,
                $userAgent,
                $sessionId
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("User activity logging failed: " . $e->getMessage());
        }
    }

    /**
     * Initialize course tables if they don't exist
     */
    protected function initializeCourseTables() {
        try {
            // Check if course_tasks table exists
            $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='course_tasks'");
            if (!$result->fetch()) {
                // Create course_tasks table
                $this->db->exec("
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
                $this->db->exec("
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

                // Create task_progress table (THIS WAS MISSING!)
                $this->db->exec("
                    CREATE TABLE task_progress (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_task_id INTEGER NOT NULL,
                        status TEXT NOT NULL,
                        progress_percentage INTEGER DEFAULT 0,
                        started_at DATETIME,
                        completed_at DATETIME,
                        reviewed_at DATETIME,
                        reviewed_by INTEGER,
                        review_notes TEXT,
                        submission_data TEXT,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_task_id) REFERENCES user_tasks(id) ON DELETE CASCADE,
                        UNIQUE(user_task_id)
                    )
                ");

                // Create course_materials table
                $this->db->exec("
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
            error_log("initializeCourseTables error: " . $e->getMessage());
        }
    }

    /**
     * Abstract method that each component must implement
     */
    abstract public function handleAction($action);
}
