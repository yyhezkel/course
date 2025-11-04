<?php
/**
 * Migration: Add id_type field to users table
 * Adds support for both "תעודת זהות" (tz - 9 digits) and "מספר אישי" (personal_number - 7 digits)
 */

require_once __DIR__ . '/config.php';

echo "=== Adding id_type field to users table ===\n\n";

$db = getDbConnection();

try {
    // Check if column already exists
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasIdType = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'id_type') {
            $hasIdType = true;
            break;
        }
    }

    if ($hasIdType) {
        echo "✓ id_type field already exists\n";
    } else {
        // Add id_type column with default value 'tz' for existing users
        $db->exec("ALTER TABLE users ADD COLUMN id_type TEXT DEFAULT 'tz' NOT NULL");
        echo "✓ Added id_type column\n";
    }

    // Check if full_name column exists (for display purposes)
    $hasFullName = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'full_name') {
            $hasFullName = true;
            break;
        }
    }

    if (!$hasFullName) {
        $db->exec("ALTER TABLE users ADD COLUMN full_name TEXT");
        echo "✓ Added full_name column\n";
    }

    // Update all existing users to have id_type = 'tz' (if not already set)
    $stmt = $db->prepare("UPDATE users SET id_type = 'tz' WHERE id_type IS NULL OR id_type = ''");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "✓ Updated $count existing users to id_type='tz'\n";

    // Show summary
    echo "\n--- Summary ---\n";
    $result = $db->query("SELECT id_type, COUNT(*) as count FROM users GROUP BY id_type");
    $types = $result->fetchAll(PDO::FETCH_ASSOC);

    foreach ($types as $type) {
        echo "  {$type['id_type']}: {$type['count']} users\n";
    }

    echo "\n✓✓✓ Migration completed successfully! ✓✓✓\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
