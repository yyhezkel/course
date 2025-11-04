<?php
/**
 * Check Question Ordering for Form 2
 */

require_once __DIR__ . '/config.php';

$db = getDbConnection();
$formId = 2;

echo "=== Question Order for Form #$formId ===\n\n";

$stmt = $db->prepare("
    SELECT
        c.name as category,
        c.sequence_order as cat_order,
        q.id as q_id,
        q.question_text,
        fq.sequence_order as q_order,
        fq.category_id
    FROM form_questions fq
    LEFT JOIN categories c ON fq.category_id = c.id
    JOIN questions q ON fq.question_id = q.id
    WHERE fq.form_id = ?
    ORDER BY
        COALESCE(c.sequence_order, 999) ASC,
        fq.sequence_order ASC
");

$stmt->execute([$formId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentCategory = null;
$questionNum = 1;

foreach ($questions as $q) {
    $catName = $q['category'] ?? 'ללא קטגוריה';

    if ($catName !== $currentCategory) {
        echo "\n========================================\n";
        echo "CATEGORY: $catName (order: {$q['cat_order']})\n";
        echo "========================================\n\n";
        $currentCategory = $catName;
    }

    echo sprintf(
        "#%d - [Q%s] %s (q_order: %s, cat_id: %s)\n",
        $questionNum,
        $q['q_id'],
        substr($q['question_text'], 0, 60),
        $q['q_order'],
        $q['category_id'] ?? 'NULL'
    );

    $questionNum++;
}

echo "\n\nTotal questions: " . count($questions) . "\n";
?>
