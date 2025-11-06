<?php
/**
 * TaskComponent
 * Handles task-related API operations
 */

class TaskComponent extends BaseComponent {

    public function handleAction($action) {
        switch ($action) {
            case 'get_dashboard':
                return $this->getDashboard();
            case 'get_task_detail':
                return $this->getTaskDetail();
            case 'update_task_status':
                return $this->updateTaskStatus();
            default:
                $this->sendError(404, 'פעולה לא נתמכת');
        }
    }

    /**
     * Get user dashboard with tasks
     */
    private function getDashboard() {
        $this->requireAuth();
        $this->initializeCourseTables();

        $userId = $this->session['user_id'];

        try {
            // Get user details
            $stmt = $this->db->prepare("SELECT tz, id_type, full_name, username, profile_photo FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if course_tasks table exists
            $tableCheck = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='course_tasks'")->fetch();

            $tasks = [];
            if ($tableCheck) {
                // Get all user tasks
                $stmt = $this->db->prepare("
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
            }

            // Log dashboard view
            $this->logUserActivity($userId, 'view_dashboard', null, null, json_encode(['task_count' => count($tasks)]));

            $this->sendSuccess([
                'user' => $user,
                'tasks' => $tasks
            ]);

        } catch (Exception $e) {
            error_log("Dashboard error for user $userId: " . $e->getMessage());
            $this->sendError(500, 'שגיאה בטעינת הנתונים: ' . $e->getMessage());
        }
    }

    /**
     * Get task detail
     */
    private function getTaskDetail() {
        $this->requireAuth();
        $this->initializeCourseTables();

        $userId = $this->session['user_id'];
        $userTaskId = $this->getRequiredParam('user_task_id', 'חסר מזהה משימה.');

        try {
            // Get task details with review notes from task_progress
            $stmt = $this->db->prepare("
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
                $this->sendError(404, 'המשימה לא נמצאה.');
            }

            // Get course materials
            $stmt = $this->db->prepare("
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

            // Log task view
            $this->logUserActivity($userId, 'view_task', 'task', $task['task_id'], json_encode(['title' => $task['title']]));

            $this->sendSuccess(['task' => $task]);

        } catch (Exception $e) {
            error_log("Task detail error: " . $e->getMessage());
            $this->sendError(500, 'שגיאה בטעינת המשימה: ' . $e->getMessage());
        }
    }

    /**
     * Update task status
     */
    private function updateTaskStatus() {
        $this->requireAuth();
        $this->checkSessionTimeout();
        $this->refreshSessionTimeout();
        $this->initializeCourseTables();

        $userId = $this->session['user_id'];
        $userTaskId = $this->getRequiredParam('user_task_id');
        $newStatus = $this->getRequiredParam('status');

        // Validate status
        $validStatuses = ['pending', 'in_progress', 'completed', 'needs_review', 'approved', 'rejected'];
        if (!in_array($newStatus, $validStatuses)) {
            $this->sendError(400, 'סטטוס לא תקין.');
        }

        try {
            // Verify task belongs to user
            $stmt = $this->db->prepare("SELECT id FROM user_tasks WHERE id = ? AND user_id = ?");
            $stmt->execute([$userTaskId, $userId]);
            if (!$stmt->fetch()) {
                $this->sendError(403, 'אין הרשאה לעדכן משימה זו.');
            }

            // Update status
            $stmt = $this->db->prepare("UPDATE user_tasks SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userTaskId]);

            // Update/insert task_progress record
            if ($newStatus === 'in_progress') {
                $stmt = $this->db->prepare("
                    INSERT INTO task_progress (user_task_id, status, started_at, updated_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ON CONFLICT(user_task_id)
                    DO UPDATE SET status = excluded.status, started_at = COALESCE(task_progress.started_at, CURRENT_TIMESTAMP), updated_at = excluded.updated_at
                ");
                $stmt->execute([$userTaskId, $newStatus]);
            } elseif ($newStatus === 'needs_review' || $newStatus === 'completed') {
                $stmt = $this->db->prepare("
                    INSERT INTO task_progress (user_task_id, status, completed_at, updated_at, progress_percentage)
                    VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 100)
                    ON CONFLICT(user_task_id)
                    DO UPDATE SET status = excluded.status, completed_at = CURRENT_TIMESTAMP, updated_at = excluded.updated_at, progress_percentage = 100
                ");
                $stmt->execute([$userTaskId, $newStatus]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO task_progress (user_task_id, status, updated_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT(user_task_id)
                    DO UPDATE SET status = excluded.status, updated_at = excluded.updated_at
                ");
                $stmt->execute([$userTaskId, $newStatus]);
            }

            // Log task status update
            $this->logUserActivity($userId, 'update_task_status', 'task', $userTaskId, json_encode(['status' => $newStatus]));

            $this->sendSuccess([], 'הסטטוס עודכן בהצלחה.');

        } catch (Exception $e) {
            error_log("Update task status error: " . $e->getMessage());
            $this->sendError(500, 'שגיאה בעדכון הסטטוס: ' . $e->getMessage());
        }
    }
}
