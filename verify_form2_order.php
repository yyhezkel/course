<?php
/**
 * Verify Form 2 question order
 */
require_once __DIR__ . '/config.php';

echo "=== Form 2 Question Order ===\n\n";

$pdo = getDbConnection();

$query = "
    SELECT
        fq.sequence_order,
        q.id,
        q.question_text,
        c.name as category,
        fq.section_title
    FROM form_questions fq
    JOIN questions q ON fq.question_id = q.id
    LEFT JOIN categories c ON fq.category_id = c.id
    WHERE fq.form_id = 2
    ORDER BY fq.sequence_order ASC
";

$stmt = $pdo->query($query);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_category = '';
$count = 0;
$category_count = 0;

foreach ($questions as $q) {
    if ($q['category'] !== $current_category) {
        if ($current_category !== '') {
            echo "  Total in category: $category_count\n";
        }
        $current_category = $q['category'];
        $category_count = 0;
        echo "\n[" . $current_category . "]\n";
    }
    $category_count++;
    $count++;
    echo sprintf("  %3d. [ID %d, order=%d] %s\n",
        $count,
        $q['id'],
        $q['sequence_order'],
        mb_substr($q['question_text'], 0, 50)
    );
}

if ($current_category !== '') {
    echo "  Total in category: $category_count\n";
}

echo "\n=== Summary ===\n";
echo "Total questions: " . count($questions) . "\n";
echo "\nExpected:\n";
echo "- Category 1 (פרטים אישיים): 33 questions (IDs 461-493)\n";
echo "- Category 2 (שאלון היכרות): 8 questions (IDs 494-501)\n";
echo "- Total: 41 questions\n";
