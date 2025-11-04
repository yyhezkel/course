<?php
/**
 * Test Form 2 API response - simulate what users see when they log in
 */
require_once __DIR__ . '/config.php';

$pdo = getDbConnection();
$formId = 2;

echo "=== Testing Form 2 API Response ===\n\n";

// This is the same query used in api.php (lines 103-114)
$stmt = $pdo->prepare("
    SELECT q.id, q.question_text, c.name as category, c.sequence_order as cat_seq, fq.sequence_order as q_seq
    FROM questions q
    JOIN form_questions fq ON q.id = fq.question_id
    LEFT JOIN categories c ON fq.category_id = c.id
    WHERE fq.form_id = ? AND fq.is_active = 1
    ORDER BY
        COALESCE(c.sequence_order, 999) ASC,
        fq.sequence_order ASC
");
$stmt->execute([$formId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total questions: " . count($questions) . "\n\n";

$currentCategory = '';
$count = 0;

foreach ($questions as $index => $q) {
    if ($q['category'] !== $currentCategory) {
        if ($currentCategory !== '') {
            echo "\n";
        }
        $currentCategory = $q['category'];
        $count = 0;
        echo "[{$q['category']}] (Category Seq: {$q['cat_seq']})\n";
    }
    $count++;
    echo sprintf("  %2d. [ID %d, Q-Seq: %2d] %s\n",
        $count,
        $q['id'],
        $q['q_seq'],
        mb_substr($q['question_text'], 0, 45)
    );
}

echo "\n✓✓✓ This is the order users will see! ✓✓✓\n";
