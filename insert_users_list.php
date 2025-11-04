<?php
/**
 * Insert list of users directly into database
 */

require_once __DIR__ . '/config.php';

$users = [
    '8424784',
    '8675622',
    '8489194',
    '8410158',
    '8887842',
    '8331432',
    '8884707',
    '8161714',
    '9060580',
    '8245812',
    '8583055',
    '8621938',
    '8609312',
    '8473526',
    '8017736',
    '8468297',
    '8438746',
    '8282241',
    '8434521',
    '8674350'
];

echo "=== Inserting Users ===\n\n";

$db = getDbConnection();

$inserted = 0;
$skipped = 0;
$errors = [];

foreach ($users as $tz) {
    // Determine ID type based on length
    $idType = strlen($tz) === 7 ? 'personal_number' : 'tz';
    $idTypeName = $idType === 'personal_number' ? 'מספר אישי' : 'תעודת זהות';

    try {
        // Check if user already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE tz = ?");
        $stmt->execute([$tz]);
        if ($stmt->fetch()) {
            echo "⊘ Skipped: $tz (already exists)\n";
            $skipped++;
            continue;
        }

        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (tz, id_type, is_blocked, failed_attempts, created_at)
            VALUES (?, ?, 0, 0, datetime('now'))
        ");
        $stmt->execute([$tz, $idType]);

        echo "✓ Inserted: $tz ($idTypeName)\n";
        $inserted++;

    } catch (Exception $e) {
        echo "✗ Error with $tz: " . $e->getMessage() . "\n";
        $errors[] = $tz;
    }
}

echo "\n=== Summary ===\n";
echo "Inserted: $inserted\n";
echo "Skipped: $skipped\n";
echo "Errors: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\nFailed users: " . implode(', ', $errors) . "\n";
}

echo "\n✓✓✓ Done! ✓✓✓\n";
?>
