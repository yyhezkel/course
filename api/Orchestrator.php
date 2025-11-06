<?php
/**
 * Orchestrator
 * Routes API requests to appropriate components
 */

require_once __DIR__ . '/BaseComponent.php';
require_once __DIR__ . '/components/AuthComponent.php';
require_once __DIR__ . '/components/FormComponent.php';
require_once __DIR__ . '/components/UserComponent.php';
require_once __DIR__ . '/components/TaskComponent.php';

class Orchestrator {
    private $db;
    private $session;
    private $input;
    private $userIP;
    private $actionMap;

    public function __construct($db, &$session, $input, $userIP) {
        $this->db = $db;
        $this->session = &$session;
        $this->input = $input;
        $this->userIP = $userIP;

        // Map actions to their respective components
        $this->actionMap = [
            // Auth actions
            'login' => 'AuthComponent',
            'logout' => 'AuthComponent',
            'check_session' => 'AuthComponent',

            // Form actions
            'get_questions' => 'FormComponent',
            'submit' => 'FormComponent',
            'auto_save' => 'FormComponent',

            // User actions
            'get_user_info' => 'UserComponent',
            'update_username' => 'UserComponent',
            'update_password' => 'UserComponent',
            'setup_credentials' => 'UserComponent',
            'check_needs_credential_setup' => 'UserComponent',

            // Task actions
            'get_dashboard' => 'TaskComponent',
            'get_task_detail' => 'TaskComponent',
            'update_task_status' => 'TaskComponent',
        ];
    }

    /**
     * Route the action to the appropriate component
     */
    public function route($action) {
        if (empty($action)) {
            $this->sendError(400, 'חסרה פעולה (action)');
        }

        // Check if action is mapped
        if (!isset($this->actionMap[$action])) {
            $this->sendError(404, 'Endpoint לא נמצא.');
        }

        $componentClass = $this->actionMap[$action];

        // Instantiate the component
        $component = new $componentClass($this->db, $this->session, $this->input, $this->userIP);

        // Handle the action
        $component->handleAction($action);
    }

    /**
     * Send JSON error response and exit
     */
    private function sendError($statusCode, $message) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }

    /**
     * Get list of all available actions
     */
    public function getAvailableActions() {
        return array_keys($this->actionMap);
    }
}
