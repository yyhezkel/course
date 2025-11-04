<?php
// Insert ID question at position 3 (sequence 2) in Form 2

$db = new PDO('sqlite:/www/wwwroot/qr.bot4wa.com/kodkod/form_data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Inserting ID Question into Form 2 Sequence\n";
echo str_repeat('=', 80) . "\n\n";

$db->beginTransaction();

try {
    // Step 1: Shift all questions from sequence 2 onwards up by 1
    echo "Step 1: Shifting questions from sequence 2 onwards...\n";
    $stmt = $db->prepare("
        UPDATE form_questions
        SET sequence_order = sequence_order + 1
        WHERE form_id = 2
            AND category_id IS NOT NULL
            AND sequence_order >= 2
    ");
    $stmt->execute();
    echo "  ✓ Shifted " . $stmt->rowCount() . " questions\n\n";

    // Step 2: Update the ID question (Q 535) to have proper category and sequence
    echo "Step 2: Inserting ID question at sequence 2...\n";
    $stmt = $db->prepare("
        UPDATE form_questions
        SET category_id = 1,
            sequence_order = 2
        WHERE form_id = 2
            AND question_id = 535
    ");
    $stmt->execute();
    echo "  ✓ Updated question 535 (ID question)\n\n";

    $db->commit();

    // Verify the result
    echo str_repeat('=', 80) . "\n";
    echo "Verification - First 10 questions:\n";
    echo str_repeat('-', 80) . "\n";

    $stmt = $db->query("
        SELECT
            fq.sequence_order,
            q.question_text
        FROM form_questions fq
        JOIN questions q ON fq.question_id = q.id
        WHERE fq.form_id = 2 AND fq.category_id IS NOT NULL
        ORDER BY fq.sequence_order
        LIMIT 10
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%2d. %s\n", $row['sequence_order'] + 1, $row['question_text']);
    }

    echo "\n" . str_repeat('=', 80) . "\n";
    echo "✓ Successfully inserted ID question at position 3!\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
