<?php
/**
 * Fix Form 2 sequence_order to prevent interleaving
 */
require_once __DIR__ . '/config.php';

$pdo = getDbConnection();

echo "=== Fixing Form 2 Sequence Order ===\n\n";

// Start transaction
$pdo->beginTransaction();

try {
    // First, get all questions for Form 2 ordered by question ID
    // This ensures: 461-493 (פרטים אישיים) come first, then 494-501 (שאלון היכרות)
    $stmt = $pdo->query("
        SELECT fq.id, fq.question_id, c.name as category
        FROM form_questions fq
        LEFT JOIN categories c ON fq.category_id = c.id
        WHERE fq.form_id = 2
        ORDER BY fq.question_id ASC
    ");

    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($questions) . " questions\n";
    echo "Updating sequence_order...\n\n";

    // Update each question with the correct sequence number
    $updateStmt = $pdo->prepare("
        UPDATE form_questions
        SET sequence_order = :order
        WHERE id = :id
    ");

    $current_category = '';
    $category_count = 0;

    foreach ($questions as $index => $q) {
        if ($q['category'] !== $current_category) {
            if ($current_category !== '') {
                echo "  Updated $category_count questions\n";
            }
            $current_category = $q['category'];
            $category_count = 0;
            echo "\n[" . $current_category . "]\n";
        }

        $updateStmt->execute([
            ':order' => $index,
            ':id' => $q['id']
        ]);

        $category_count++;
        echo "  Q" . ($index + 1) . ": ID " . $q['question_id'] . " => sequence_order = $index\n";
    }

    if ($current_category !== '') {
        echo "  Updated $category_count questions\n";
    }

    // Commit the transaction
    $pdo->commit();

    echo "\n✓✓✓ Successfully fixed sequence order! ✓✓✓\n";
    echo "\nNow Form 2 will show:\n";
    echo "1. פרטים אישיים (Questions 461-493): sequence 0-32\n";
    echo "2. שאלון היכרות (Questions 494-501): sequence 33-40\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
}
