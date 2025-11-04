<?php
/**
 * Organize Form 2 Questions into Correct Categories
 * Category 1: פרטים אישיים - Questions 461-493 (33 questions)
 * Category 2: שאלון היכרות - Questions 494-501 (8 questions)
 */

require_once __DIR__ . '/config.php';

echo "=== Organizing Form 2 into Categories ===\n\n";

$db = getDbConnection();
$formId = 2;

try {
    // Step 1: Check if categories exist, create if not
    $stmt = $db->query("SELECT id, name FROM categories ORDER BY sequence_order ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($categories) < 2) {
        echo "Creating categories...\n";

        // Create Category 1
        $stmt = $db->prepare("INSERT INTO categories (name, color, sequence_order) VALUES (?, ?, ?)");
        $stmt->execute(['פרטים אישיים', '#667eea', 1]);
        $cat1Id = $db->lastInsertId();
        echo "✓ Created Category 1: פרטים אישיים (ID: $cat1Id)\n";

        // Create Category 2
        $stmt = $db->prepare("INSERT INTO categories (name, color, sequence_order) VALUES (?, ?, ?)");
        $stmt->execute(['שאלון היכרות', '#f093fb', 2]);
        $cat2Id = $db->lastInsertId();
        echo "✓ Created Category 2: שאלון היכרות (ID: $cat2Id)\n\n";
    } else {
        $cat1Id = $categories[0]['id'];
        $cat2Id = $categories[1]['id'];
        echo "✓ Using existing categories:\n";
        echo "  Category 1: {$categories[0]['name']} (ID: $cat1Id)\n";
        echo "  Category 2: {$categories[1]['name']} (ID: $cat2Id)\n\n";
    }

    // Step 2: Assign Category 1 questions (461-493) with proper sequence
    echo "Assigning Category 1: פרטים אישיים (Questions 461-493)...\n";

    $sequenceOrder = 0;
    for ($qId = 461; $qId <= 493; $qId++) {
        $stmt = $db->prepare("
            UPDATE form_questions
            SET category_id = ?, sequence_order = ?
            WHERE form_id = ? AND question_id = ?
        ");
        $stmt->execute([$cat1Id, $sequenceOrder, $formId, $qId]);

        if ($sequenceOrder < 3) {
            $stmt = $db->prepare("SELECT question_text FROM questions WHERE id = ?");
            $stmt->execute([$qId]);
            $qText = $stmt->fetchColumn();
            echo "  #$sequenceOrder - Q$qId: $qText\n";
        }

        $sequenceOrder++;
    }
    echo "  ... (33 total questions)\n\n";

    // Step 3: Assign Category 2 questions (494-501) with proper sequence
    echo "Assigning Category 2: שאלון היכרות (Questions 494-501)...\n";

    $sequenceOrder = 0;
    for ($qId = 494; $qId <= 501; $qId++) {
        $stmt = $db->prepare("
            UPDATE form_questions
            SET category_id = ?, sequence_order = ?
            WHERE form_id = ? AND question_id = ?
        ");
        $stmt->execute([$cat2Id, $sequenceOrder, $formId, $qId]);

        $stmt = $db->prepare("SELECT question_text FROM questions WHERE id = ?");
        $stmt->execute([$qId]);
        $qText = $stmt->fetchColumn();
        echo "  #$sequenceOrder - Q$qId: $qText\n";

        $sequenceOrder++;
    }
    echo "\n";

    // Step 4: Verify the structure
    echo "=== Verification ===\n";
    $stmt = $db->prepare("
        SELECT
            c.name as category,
            COUNT(*) as count
        FROM form_questions fq
        LEFT JOIN categories c ON fq.category_id = c.id
        WHERE fq.form_id = ?
        GROUP BY c.name
        ORDER BY c.sequence_order ASC
    ");
    $stmt->execute([$formId]);
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($counts as $count) {
        echo "  {$count['category']}: {$count['count']} questions\n";
    }

    // Show first 10 questions in new order
    echo "\n=== First 10 Questions in New Order ===\n";
    $stmt = $db->prepare("
        SELECT
            c.name as category,
            q.id as q_id,
            q.question_text,
            fq.sequence_order
        FROM form_questions fq
        LEFT JOIN categories c ON fq.category_id = c.id
        JOIN questions q ON fq.question_id = q.id
        WHERE fq.form_id = ?
        ORDER BY c.sequence_order ASC, fq.sequence_order ASC
        LIMIT 10
    ");
    $stmt->execute([$formId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentCat = '';
    $displayNum = 1;
    foreach ($questions as $q) {
        if ($q['category'] !== $currentCat) {
            echo "\n[{$q['category']}]\n";
            $currentCat = $q['category'];
        }
        echo "  $displayNum. [Q{$q['q_id']}] {$q['question_text']}\n";
        $displayNum++;
    }
    echo "  ... (41 total)\n";

    echo "\n✓✓✓ Form 2 categories organized successfully! ✓✓✓\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
