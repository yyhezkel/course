<?php
/**
 * Migration Script: Add Categories Support
 * Adds categories table and links questions to categories
 */

require_once __DIR__ . '/../config.php';

echo "=== Adding Categories Support ===\n\n";

$db = getDbConnection();
$db->exec('PRAGMA foreign_keys = ON;');

try {
    // ============================================
    // STEP 1: Create categories table
    // ============================================
    echo "--- Step 1: Creating categories table ---\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            color TEXT DEFAULT '#4A90E2',
            sequence_order INTEGER NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created categories table\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_sequence ON categories(sequence_order)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_active ON categories(is_active)");
    echo "✓ Created indexes on categories table\n";

    // ============================================
    // STEP 2: Add category_id to form_questions
    // ============================================
    echo "\n--- Step 2: Adding category_id to form_questions ---\n";

    $columns = $db->query("PRAGMA table_info(form_questions)")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'name');

    if (!in_array('category_id', $existingColumns)) {
        $db->exec("ALTER TABLE form_questions ADD COLUMN category_id INTEGER REFERENCES categories(id)");
        echo "✓ Added category_id column to form_questions\n";
    } else {
        echo "✓ category_id column already exists\n";
    }

    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_questions_category ON form_questions(category_id)");
    echo "✓ Created index on category_id\n";

    // ============================================
    // STEP 3: Create default categories with colors
    // ============================================
    echo "\n--- Step 3: Creating default categories ---\n";

    $defaultCategories = [
        ['פרטים אישיים', 'מידע כללי על המשתמש', '#4A90E2', 1],  // Blue
        ['פרטי קשר', 'אמצעי יצירת קשר', '#50C878', 2],           // Green
        ['השכלה ותעסוקה', 'רקע מקצועי', '#FFB347', 3],           // Orange
        ['בריאות', 'מידע רפואי', '#FF6B6B', 4],                   // Red
        ['משפחה', 'מצב משפחתי ומשפחה', '#B19CD9', 5],            // Purple
        ['העדפות', 'העדפות אישיות', '#FFD93D', 6],               // Yellow
        ['נוסף', 'מידע נוסף', '#95A5A6', 7]                      // Gray
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO categories (name, description, color, sequence_order) VALUES (?, ?, ?, ?)");
    $insertedCount = 0;

    foreach ($defaultCategories as $cat) {
        try {
            $stmt->execute($cat);
            if ($stmt->rowCount() > 0) {
                $insertedCount++;
            }
        } catch (Exception $e) {
            // Category might already exist
        }
    }

    echo "✓ Created $insertedCount default categories\n";

    // ============================================
    // STEP 4: Migrate section_title to categories
    // ============================================
    echo "\n--- Step 4: Migrating section_title to categories ---\n";

    // Get all unique section titles
    $sections = $db->query("
        SELECT DISTINCT section_title
        FROM form_questions
        WHERE section_title IS NOT NULL AND section_title != ''
    ")->fetchAll(PDO::FETCH_COLUMN);

    $migratedCount = 0;
    foreach ($sections as $sectionTitle) {
        // Try to find matching category
        $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$sectionTitle]);
        $categoryId = $stmt->fetchColumn();

        if (!$categoryId) {
            // Create new category for this section
            $stmt = $db->prepare("
                INSERT INTO categories (name, color, sequence_order)
                VALUES (?, '#95A5A6', (SELECT COALESCE(MAX(sequence_order), 0) + 1 FROM categories))
            ");
            $stmt->execute([$sectionTitle]);
            $categoryId = $db->lastInsertId();
        }

        // Update form_questions to use category_id
        $stmt = $db->prepare("UPDATE form_questions SET category_id = ? WHERE section_title = ?");
        $stmt->execute([$categoryId, $sectionTitle]);
        $migratedCount += $stmt->rowCount();
    }

    echo "✓ Migrated $migratedCount questions to categories\n";

    // ============================================
    // MIGRATION COMPLETE
    // ============================================
    echo "\n=== Migration Completed Successfully ===\n\n";

    // Show categories
    echo "--- Categories Created ---\n";
    $categories = $db->query("SELECT id, name, color, sequence_order FROM categories ORDER BY sequence_order")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categories as $cat) {
        echo "  • [{$cat['sequence_order']}] {$cat['name']} (Color: {$cat['color']})\n";
    }

    echo "\n--- Next Steps ---\n";
    echo "  1. Assign categories to all questions in admin panel\n";
    echo "  2. Test the form with category-based ordering\n";
    echo "  3. Customize category colors as needed\n";

} catch (Exception $e) {
    echo "\n✗ MIGRATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
?>
