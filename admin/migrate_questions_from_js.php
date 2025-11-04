<?php
/**
 * Migrate Questions from questions.js to Database
 * This script reads the existing questions.js file and imports all questions into the database
 */

require_once __DIR__ . '/../config.php';

echo "=== Migrating Questions from questions.js to Database ===\n\n";

$db = getDbConnection();

// Read questions.js file
$questionsJsPath = __DIR__ . '/../questions.js';
if (!file_exists($questionsJsPath)) {
    die("✗ Error: questions.js file not found at $questionsJsPath\n");
}

$jsContent = file_get_contents($questionsJsPath);

// Extract the formQuestions array
// Remove comments and extract JSON-like structure
$jsContent = preg_replace('/\/\/.*$/m', '', $jsContent); // Remove single-line comments
$jsContent = preg_replace('/\/\*.*?\*\//s', '', $jsContent); // Remove multi-line comments

// Extract the array content between [ and ];
preg_match('/const\s+formQuestions\s*=\s*\[(.*?)\];/s', $jsContent, $matches);
if (!isset($matches[1])) {
    die("✗ Error: Could not parse questions.js\n");
}

$arrayContent = $matches[1];

// Parse JavaScript objects to PHP array
// This is a simple parser - for production, consider using a proper JS parser
$arrayContent = preg_replace('/(\w+):/m', '"$1":', $arrayContent); // Add quotes to keys
$arrayContent = str_replace("'", '"', $arrayContent); // Replace single quotes with double quotes

// Try to decode as JSON
$questions = json_decode('[' . $arrayContent . ']', true);

if (json_last_error() !== JSON_ERROR_NONE) {
    // If JSON decode fails, we'll manually parse it
    echo "⚠ JSON decode failed, using manual parsing...\n\n";
    $questions = parseJsQuestions($jsContent);
}

if (empty($questions)) {
    die("✗ Error: No questions found in questions.js\n");
}

echo "Found " . count($questions) . " questions in questions.js\n\n";

// Get question type mappings
$typeMap = [
    'text' => getQuestionTypeId($db, 'text'),
    'textarea' => getQuestionTypeId($db, 'textarea'),
    'number' => getQuestionTypeId($db, 'number'),
    'radio' => getQuestionTypeId($db, 'radio'),
    'select' => getQuestionTypeId($db, 'select'),
    'phone' => getQuestionTypeId($db, 'phone'),
    'email' => getQuestionTypeId($db, 'email'),
];

// Get or create default form
$formId = getDefaultFormId($db);

// Start migration
$successCount = 0;
$skipCount = 0;
$errorCount = 0;

foreach ($questions as $index => $q) {
    if (!isset($q['id']) || !isset($q['question']) || !isset($q['type'])) {
        echo "⚠ Skipping invalid question at index $index\n";
        $skipCount++;
        continue;
    }

    $questionId = $q['id'];
    $questionText = $q['question'];
    $jsType = $q['type'];
    $isRequired = isset($q['required']) ? ($q['required'] ? 1 : 0) : 1;
    $options = isset($q['options']) ? json_encode($q['options']) : null;

    // Smart type mapping based on content
    $dbType = $jsType;
    if ($jsType === 'text') {
        // Check if it's a phone or email field
        if (stripos($questionText, 'טלפון') !== false || stripos($questionText, 'נייד') !== false) {
            $dbType = 'phone';
        } elseif (stripos($questionText, 'מייל') !== false || stripos($questionText, 'דוא"ל') !== false) {
            $dbType = 'email';
        }
    }

    $questionTypeId = $typeMap[$dbType] ?? $typeMap['text'];

    // Set placeholder based on type
    $placeholder = getPlaceholder($questionText, $dbType);

    try {
        // Check if question with this ID already exists
        $stmt = $db->prepare("SELECT id FROM questions WHERE id = ?");
        $stmt->execute([$index + 1]); // Use sequential IDs
        $existingQuestion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingQuestion) {
            echo "⚠ Question already exists: $questionId (skipping)\n";
            $skipCount++;
            continue;
        }

        // Insert question
        $stmt = $db->prepare("
            INSERT INTO questions (question_text, question_type_id, placeholder, is_required, options, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([
            $questionText,
            $questionTypeId,
            $placeholder,
            $isRequired,
            $options
        ]);

        $newQuestionId = $db->lastInsertId();

        // Link to form with sequence
        $sequenceOrder = $index + 1;
        $sectionTitle = getSectionTitle($questionId);

        $stmt = $db->prepare("
            INSERT INTO form_questions (form_id, question_id, sequence_order, section_title, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $formId,
            $newQuestionId,
            $sequenceOrder,
            $sectionTitle
        ]);

        echo "✓ Imported: $questionId → Question ID: $newQuestionId, Sequence: $sequenceOrder\n";
        $successCount++;

    } catch (Exception $e) {
        echo "✗ Error importing $questionId: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

// Summary
echo "\n=== Migration Summary ===\n";
echo "✓ Successfully imported: $successCount questions\n";
echo "⚠ Skipped: $skipCount questions\n";
echo "✗ Errors: $errorCount questions\n";

// Verify
$totalQuestions = $db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$totalFormQuestions = $db->query("SELECT COUNT(*) FROM form_questions WHERE form_id = $formId")->fetchColumn();

echo "\n=== Database Status ===\n";
echo "Total questions in database: $totalQuestions\n";
echo "Questions linked to default form: $totalFormQuestions\n";

echo "\n✓ Migration completed!\n";

// ============================================
// Helper Functions
// ============================================

function getQuestionTypeId($db, $typeCode) {
    $stmt = $db->prepare("SELECT id FROM question_types WHERE type_code = ?");
    $stmt->execute([$typeCode]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : 1; // Default to text type
}

function getDefaultFormId($db) {
    $stmt = $db->query("SELECT id FROM forms ORDER BY id LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : 1;
}

function getPlaceholder($questionText, $type) {
    switch ($type) {
        case 'phone':
            return '05X-XXXXXXX';
        case 'email':
            return 'example@example.com';
        case 'number':
            return 'הזן מספר';
        case 'textarea':
            return 'הקלד תשובה מפורטת כאן...';
        default:
            return 'הקלד תשובה כאן...';
    }
}

function getSectionTitle($questionId) {
    // Group questions by their prefix
    if (strpos($questionId, 'personal_') === 0) {
        return 'פרטים אישיים';
    } elseif (in_array($questionId, ['personalBackground', 'strengths', 'weaknesses', 'hasSubordinates', 'subordinatesCount', 'roleSummary', 'medicalStatus', 'fitnessLevel', 'sportShirtSize'])) {
        return 'רקע אישי וכושר גופני';
    } elseif (in_array($questionId, ['hobbies', 'personalExperiences', 'whyMilitaryService', 'mostSignificantEvent', 'whatILike', 'whatIDontLike', 'influentialCommander'])) {
        return 'שירות צבאי ותחביבים';
    } elseif (in_array($questionId, ['courseGoals', 'supremeValue', 'unappreciatedTrait', 'roleModel', 'initialImpression'])) {
        return 'יעדים וערכים';
    } elseif (in_array($questionId, ['candidateExpectations', 'staffExpectations', 'additionalNotes'])) {
        return 'ציפיות ונושאים נוספים';
    }
    return null;
}

function parseJsQuestions($jsContent) {
    // Fallback manual parser for questions.js
    $questions = [];

    // Extract each question object
    preg_match_all('/\{[^}]*id:\s*"([^"]+)"[^}]*question:\s*"([^"]+)"[^}]*type:\s*"([^"]+)"[^}]*(?:required:\s*(true|false))?[^}]*(?:options:\s*\[([^\]]+)\])?[^}]*\}/s', $jsContent, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $question = [
            'id' => $match[1],
            'question' => $match[2],
            'type' => $match[3],
            'required' => isset($match[4]) ? ($match[4] === 'true') : true
        ];

        if (isset($match[5])) {
            // Parse options
            $optionsStr = $match[5];
            preg_match_all('/"([^"]+)"/', $optionsStr, $optionsMatches);
            $question['options'] = $optionsMatches[1];
        }

        $questions[] = $question;
    }

    return $questions;
}
?>
