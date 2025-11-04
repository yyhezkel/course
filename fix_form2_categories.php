<?php
/**
 * Fix Form 2 - Organize questions into 2 categories
 * Pattern: Odd positions (1,3,5,7...) = Category 1, Even positions (2,4,6,8...) = Category 2
 */

require_once __DIR__ . '/config.php';

echo "=== Fixing Form #2 Categories ===\n\n";

$db = getDbConnection();
$formId = 2;

try {
    // Step 1: Get all questions in current order
    $stmt = $db->prepare("
        SELECT
            fq.id as fq_id,
            q.id as q_id,
            q.question_text,
            fq.sequence_order
        FROM form_questions fq
        JOIN questions q ON fq.question_id = q.id
        WHERE fq.form_id = ?
        ORDER BY fq.sequence_order ASC
    ");
    $stmt->execute([$formId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($questions) . " questions in Form #2\n\n";

    // Step 2: Check if categories exist, create if not
    $stmt = $db->query("SELECT id, name FROM categories ORDER BY sequence_order ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($categories) < 2) {
        echo "Creating categories...\n";

        // Create Category 1
        $stmt = $db->prepare("INSERT INTO categories (name, color, sequence_order) VALUES (?, ?, ?)");
        $stmt->execute(['קטגוריה 1', '#667eea', 1]);
        $cat1Id = $db->lastInsertId();
        echo "✓ Created Category 1 (ID: $cat1Id)\n";

        // Create Category 2
        $stmt = $db->prepare("INSERT INTO categories (name, color, sequence_order) VALUES (?, ?, ?)");
        $stmt->execute(['קטגוריה 2', '#f093fb', 2]);
        $cat2Id = $db->lastInsertId();
        echo "✓ Created Category 2 (ID: $cat2Id)\n\n";
    } else {
        $cat1Id = $categories[0]['id'];
        $cat2Id = $categories[1]['id'];
        echo "✓ Using existing categories: {$categories[0]['name']} (ID: $cat1Id) and {$categories[1]['name']} (ID: $cat2Id)\n\n";
    }

    // Step 3: Organize questions into categories
    echo "Organizing questions...\n\n";

    $cat1Questions = [];
    $cat2Questions = [];

    foreach ($questions as $index => $q) {
        $position = $index + 1; // Position starts at 1

        if ($position % 2 === 1) {
            // Odd position - Category 1
            $cat1Questions[] = $q;
        } else {
            // Even position - Category 2
            $cat2Questions[] = $q;
        }
    }

    echo "Category 1: " . count($cat1Questions) . " questions\n";
    echo "Category 2: " . count($cat2Questions) . " questions\n\n";

    // Step 4: Update database
    echo "Updating database...\n";

    $updated = 0;

    // Update Category 1 questions
    foreach ($cat1Questions as $index => $q) {
        $newSequenceOrder = $index; // 0, 1, 2, 3...
        $stmt = $db->prepare("UPDATE form_questions SET category_id = ?, sequence_order = ? WHERE id = ?");
        $stmt->execute([$cat1Id, $newSequenceOrder, $q['fq_id']]);
        $updated++;

        if ($index < 3) {
            echo "  Cat1 #$newSequenceOrder: {$q['question_text']}\n";
        }
    }
    echo "  ... (" . count($cat1Questions) . " total)\n\n";

    // Update Category 2 questions
    foreach ($cat2Questions as $index => $q) {
        $newSequenceOrder = $index; // 0, 1, 2, 3...
        $stmt = $db->prepare("UPDATE form_questions SET category_id = ?, sequence_order = ? WHERE id = ?");
        $stmt->execute([$cat2Id, $newSequenceOrder, $q['fq_id']]);
        $updated++;

        if ($index < 3) {
            echo "  Cat2 #$newSequenceOrder: {$q['question_text']}\n";
        }
    }
    echo "  ... (" . count($cat2Questions) . " total)\n\n";

    echo "=== Summary ===\n";
    echo "Total questions updated: $updated\n";
    echo "Category 1: " . count($cat1Questions) . " questions\n";
    echo "Category 2: " . count($cat2Questions) . " questions\n";
    echo "\n✓✓✓ Form #2 fixed successfully! ✓✓✓\n";

    // Show sample of new order
    echo "\n=== Verification (first 10 questions) ===\n";
    $stmt = $db->prepare("
        SELECT
            c.name as category,
            c.sequence_order as cat_order,
            q.question_text,
            fq.sequence_order as q_order
        FROM form_questions fq
        LEFT JOIN categories c ON fq.category_id = c.id
        JOIN questions q ON fq.question_id = q.id
        WHERE fq.form_id = ?
        ORDER BY
            COALESCE(c.sequence_order, 999) ASC,
            fq.sequence_order ASC
        LIMIT 10
    ");
    $stmt->execute([$formId]);
    $verifyQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($verifyQuestions as $i => $vq) {
        echo ($i + 1) . ". [{$vq['category']}] {$vq['question_text']}\n";
    }

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
