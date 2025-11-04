<?php
/**
 * Sync Form 2 database to match builder structure
 * Remove extra questions and keep only the 41 questions from the builder
 */

require_once __DIR__ . '/config.php';

echo "=== Syncing Form 2 to Builder Structure ===\n\n";

$db = getDbConnection();

// The correct questions from the builder (IDs 461-501)
$correctQuestions = range(461, 501); // 41 questions total

echo "Builder has " . count($correctQuestions) . " questions\n";
echo "Question IDs: 461-501\n\n";

try {
    // Step 1: Count current questions in Form 2
    $stmt = $db->query("SELECT COUNT(*) FROM form_questions WHERE form_id = 2");
    $currentCount = $stmt->fetchColumn();
    echo "Current database has $currentCount questions for Form 2\n\n";

    // Step 2: Delete all form_questions for Form 2 that are NOT in the builder
    $placeholders = str_repeat('?,', count($correctQuestions) - 1) . '?';
    $stmt = $db->prepare("
        DELETE FROM form_questions
        WHERE form_id = 2
        AND question_id NOT IN ($placeholders)
    ");
    $stmt->execute($correctQuestions);
    $deleted = $stmt->rowCount();
    echo "✓ Deleted $deleted extra questions\n\n";

    // Step 3: Verify remaining questions
    $stmt = $db->query("SELECT COUNT(*) FROM form_questions WHERE form_id = 2");
    $remaining = $stmt->fetchColumn();
    echo "Remaining questions: $remaining\n";

    if ($remaining != 41) {
        echo "⚠ WARNING: Expected 41 questions, but have $remaining\n";
    } else {
        echo "✓ Perfect! Now have exactly 41 questions\n";
    }

    // Step 4: Show current structure
    echo "\n=== Current Form 2 Structure ===\n";
    $stmt = $db->query("
        SELECT
            q.id,
            q.question_text,
            fq.sequence_order,
            fq.section_title
        FROM form_questions fq
        JOIN questions q ON fq.question_id = q.id
        WHERE fq.form_id = 2
        ORDER BY fq.section_sequence ASC, fq.sequence_order ASC
        LIMIT 10
    ");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentSection = '';
    foreach ($questions as $i => $q) {
        if ($q['section_title'] !== $currentSection) {
            echo "\n[{$q['section_title']}]\n";
            $currentSection = $q['section_title'];
        }
        echo "  " . ($i + 1) . ". " . substr($q['question_text'], 0, 50) . "\n";
    }
    echo "  ... (41 total)\n";

    echo "\n✓✓✓ Form 2 synced successfully! ✓✓✓\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
