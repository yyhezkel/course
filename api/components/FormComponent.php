<?php
/**
 * FormComponent
 * Handles form-related API operations
 */

class FormComponent extends BaseComponent {

    public function handleAction($action) {
        switch ($action) {
            case 'get_questions':
                return $this->getQuestions();
            case 'submit':
                return $this->submit();
            case 'auto_save':
                return $this->autoSave();
            default:
                $this->sendError(404, 'פעולה לא נתמכת');
        }
    }

    /**
     * Get questions for the form
     */
    private function getQuestions() {
        try {
            $formId = $this->session['form_id'] ?? 1;

            // Check if form has structure_json (new format)
            $stmt = $this->db->prepare("SELECT structure_json FROM forms WHERE id = ?");
            $stmt->execute([$formId]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($form && !empty($form['structure_json'])) {
                $structure = json_decode($form['structure_json'], true);
                $this->sendSuccess([
                    'structure' => $structure,
                    'questions' => [],
                    'total' => 0,
                    'use_structure' => true
                ]);
            }

            // Fallback: old format
            $stmt = $this->db->prepare("
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
                    c.color as category_color
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

            $formattedQuestions = [];
            foreach ($questions as $q) {
                $question = [
                    'id' => (string)$q['id'],
                    'question' => $q['question_text'],
                    'type' => $this->mapQuestionType($q['type']),
                    'required' => (bool)$q['is_required']
                ];

                if (!empty($q['options'])) {
                    $options = json_decode($q['options'], true);
                    if (is_array($options)) {
                        $question['options'] = $options;
                    }
                }

                if (!empty($q['category_id'])) {
                    $question['category'] = [
                        'id' => $q['category_id'],
                        'name' => $q['category_name'],
                        'color' => $q['category_color']
                    ];
                }

                $formattedQuestions[] = $question;
            }

            $this->sendSuccess([
                'questions' => $formattedQuestions,
                'total' => count($formattedQuestions)
            ]);

        } catch (Exception $e) {
            $this->sendError(500, 'שגיאה בטעינת השאלות: ' . $e->getMessage());
        }
    }

    /**
     * Submit form
     */
    private function submit() {
        $this->requireAuth();
        $this->checkSessionTimeout();
        $this->refreshSessionTimeout();

        $userId = $this->session['user_id'];
        $formId = $this->session['form_id'] ?? 1;
        $formData = $this->getParam('form_data', []);

        if (empty($formData)) {
            $this->sendError(400, 'חסרים פרטים לשליחה.');
        }

        $this->db->beginTransaction();
        $success = true;

        // Get question types
        $questionIds = array_keys($formData);
        $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
        $stmt = $this->db->prepare("SELECT q.id, qt.type_code FROM questions q JOIN question_types qt ON q.question_type_id = qt.id WHERE q.id IN ($placeholders)");
        $stmt->execute($questionIds);
        $questionTypes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($formData as $questionId => $answerValue) {
            try {
                $questionType = $questionTypes[$questionId] ?? 'text';

                if ($questionType === 'checkbox') {
                    $jsonValue = is_array($answerValue) ? json_encode($answerValue, JSON_UNESCAPED_UNICODE) : $answerValue;
                    $stmt = $this->db->prepare("
                        INSERT INTO form_responses (user_id, question_id, answer_json, form_id, updated_at)
                        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                        ON CONFLICT(user_id, question_id, form_id)
                        DO UPDATE SET answer_json = excluded.answer_json, answer_value = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$userId, $questionId, $jsonValue, $formId]);
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO form_responses (user_id, question_id, answer_value, form_id, updated_at)
                        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                        ON CONFLICT(user_id, question_id, form_id)
                        DO UPDATE SET answer_value = excluded.answer_value, answer_json = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$userId, $questionId, (string)$answerValue, $formId]);
                }
            } catch (Exception $e) {
                $success = false;
                error_log("Error saving answer for question $questionId: " . $e->getMessage());
                break;
            }
        }

        if ($success) {
            // Auto-populate full_name
            try {
                $firstName = $formData['personal_firstName'] ?? null;
                $lastName = $formData['personal_lastName'] ?? null;

                if ($firstName && $lastName) {
                    $fullName = trim($firstName . ' ' . $lastName);
                    $stmt = $this->db->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                    $stmt->execute([$fullName, $userId]);
                }
            } catch (Exception $e) {
                error_log("Error updating full_name: " . $e->getMessage());
            }

            // Log form submission
            $this->logUserActivity($userId, 'submit_form', 'form', $formId, json_encode(['question_count' => count($formData)]));

            $this->db->commit();
            $this->sendSuccess([], 'הטופס נשמר בהצלחה!');
        } else {
            $this->db->rollBack();
            $this->sendError(500, 'שגיאה בשמירת חלק מהנתונים ל-DB.');
        }
    }

    /**
     * Auto-save single answer
     */
    private function autoSave() {
        $this->requireAuth();
        $this->checkSessionTimeout();
        $this->refreshSessionTimeout();

        $userId = $this->session['user_id'];
        $formId = $this->session['form_id'] ?? 1;
        $questionId = $this->getRequiredParam('question_id', 'חסר question_id.');
        $answerValue = $this->getParam('answer_value', '');

        try {
            // Get question type
            $stmt = $this->db->prepare("SELECT qt.type_code FROM questions q JOIN question_types qt ON q.question_type_id = qt.id WHERE q.id = ?");
            $stmt->execute([$questionId]);
            $questionType = $stmt->fetchColumn() ?: 'text';

            // Save answer
            if ($questionType === 'checkbox') {
                $jsonValue = is_array($answerValue) ? json_encode($answerValue, JSON_UNESCAPED_UNICODE) : $answerValue;
                $stmt = $this->db->prepare("
                    INSERT INTO form_responses (user_id, question_id, answer_json, form_id, updated_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT(user_id, question_id, form_id)
                    DO UPDATE SET answer_json = excluded.answer_json, answer_value = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$userId, $questionId, $jsonValue, $formId]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO form_responses (user_id, question_id, answer_value, form_id, updated_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT(user_id, question_id, form_id)
                    DO UPDATE SET answer_value = excluded.answer_value, answer_json = NULL, updated_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$userId, $questionId, (string)$answerValue, $formId]);
            }

            // Update session cache
            if (!isset($this->session['cached_answers'])) {
                $this->session['cached_answers'] = [];
            }
            $this->session['cached_answers'][$questionId] = $answerValue;

            $this->sendSuccess([], 'נשמר בהצלחה.');

        } catch (Exception $e) {
            $this->sendError(500, 'שגיאה בשמירה: ' . $e->getMessage());
        }
    }

    /**
     * Map question types
     */
    private function mapQuestionType($dbType) {
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
}
