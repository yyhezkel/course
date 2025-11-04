<?php
/**
 * Fix Missing Options for Radio/Select Questions
 */

require_once __DIR__ . '/config.php';

echo "=== Fixing Missing Options ===\n\n";

$db = getDbConnection();

try {
    // Fix "האם יש לך פקודים?" (Do you have subordinates?)
    $yesNoOptions = json_encode(['כן', 'לא']);

    $stmt = $db->prepare("
        UPDATE questions
        SET options = ?
        WHERE question_text LIKE '%יש לך פקודים%'
        AND (options IS NULL OR options = '' OR options = '[]')
    ");
    $stmt->execute([$yesNoOptions]);
    $count1 = $stmt->rowCount();
    echo "✓ Fixed $count1 'יש לך פקודים' questions\n";

    // Fix "מהי רמת הכושר הגופני" (Physical fitness level)
    $fitnessOptions = json_encode([
        'מעולה',
        'טובה מאוד',
        'טובה',
        'בינונית',
        'נמוכה'
    ]);

    $stmt = $db->prepare("
        UPDATE questions
        SET options = ?
        WHERE question_text LIKE '%רמת הכושר%'
        AND (options IS NULL OR options = '' OR options = '[]')
    ");
    $stmt->execute([$fitnessOptions]);
    $count2 = $stmt->rowCount();
    echo "✓ Fixed $count2 'רמת הכושר' questions\n";

    // Fix question ID 46 "שם" (Name) - this shouldn't be radio, change to text
    $stmt = $db->prepare("
        UPDATE questions
        SET question_type_id = (SELECT id FROM question_types WHERE type_code = 'text')
        WHERE id = 46
    ");
    $stmt->execute();
    $count3 = $stmt->rowCount();
    echo "✓ Fixed $count3 'שם' question (changed to text type)\n";

    // Verify fix
    echo "\n--- Verification ---\n";
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM questions q
        JOIN question_types qt ON q.question_type_id = qt.id
        WHERE qt.type_code IN ('radio', 'select', 'checkbox', 'yes_no')
        AND (q.options IS NULL OR q.options = '' OR q.options = '[]')
    ");
    $remaining = $stmt->fetchColumn();

    echo "Remaining questions with missing options: $remaining\n";

    if ($remaining == 0) {
        echo "\n✓✓✓ All questions fixed! ✓✓✓\n";
    }

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
