<?php
// Fix Form 2 Sequence Order

$db = new PDO('sqlite:/www/wwwroot/qr.bot4wa.com/kodkod/form_data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Fixing Form 2 Question Sequence Order\n";
echo str_repeat('=', 80) . "\n\n";

// First, get all questions with proper categories, ordered by category and current sequence
$stmt = $db->query("
    SELECT
        id,
        question_id,
        category_id,
        sequence_order
    FROM form_questions
    WHERE form_id = 2
        AND category_id IS NOT NULL
    ORDER BY category_id, sequence_order, question_id
");

$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($questions) . " questions with proper categories\n\n";

// Re-sequence them from 0
$newSequence = 0;
$updateStmt = $db->prepare("
    UPDATE form_questions
    SET sequence_order = ?
    WHERE id = ?
");

echo "Re-sequencing questions:\n";
echo str_repeat('-', 80) . "\n";

foreach ($questions as $question) {
    echo sprintf(
        "Question ID %d: %d -> %d (Category %d)\n",
        $question['question_id'],
        $question['sequence_order'],
        $newSequence,
        $question['category_id']
    );

    $updateStmt->execute([$newSequence, $question['id']]);
    $newSequence++;
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "✓ Fixed sequence order for " . count($questions) . " questions (0-" . ($newSequence - 1) . ")\n";

// Show summary
echo "\nSummary by category:\n";
$stmt = $db->query("
    SELECT
        category_id,
        COUNT(*) as count,
        MIN(sequence_order) as min_seq,
        MAX(sequence_order) as max_seq
    FROM form_questions
    WHERE form_id = 2 AND category_id IS NOT NULL
    GROUP BY category_id
    ORDER BY category_id
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
        "  Category %d: %d questions (sequence %d-%d)\n",
        $row['category_id'],
        $row['count'],
        $row['min_seq'],
        $row['max_seq']
    );
}

echo "\nNote: Questions with NULL category_id were not re-sequenced.\n";
echo "      You may want to delete or fix those questions separately.\n";
?>
