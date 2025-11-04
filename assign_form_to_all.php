<?php
/**
 * Assign Form to All Users
 * Assigns a specific form to all users in the database
 */

require_once __DIR__ . '/config.php';

$formId = 2; // Form ID to assign

echo "=== Assigning Form #$formId to All Users ===\n\n";

$db = getDbConnection();

try {
    // Get all users
    $stmt = $db->query("SELECT id, tz, id_type FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) === 0) {
        echo "No users found in database.\n";
        exit(0);
    }

    echo "Found " . count($users) . " users\n\n";

    // Check if form exists
    $stmt = $db->prepare("SELECT id, title FROM forms WHERE id = ?");
    $stmt->execute([$formId]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) {
        echo "✗ Form #$formId not found!\n";
        exit(1);
    }

    echo "Form: {$form['title']}\n\n";

    $assigned = 0;
    $skipped = 0;

    foreach ($users as $user) {
        $userId = $user['id'];
        $tz = $user['tz'];
        $idTypeName = ($user['id_type'] ?? 'tz') === 'tz' ? 'ת.ז.' : 'מספר אישי';

        // Check if already assigned
        $stmt = $db->prepare("SELECT id FROM user_forms WHERE user_id = ? AND form_id = ?");
        $stmt->execute([$userId, $formId]);

        if ($stmt->fetch()) {
            echo "⊘ User $tz ($idTypeName) - already assigned\n";
            $skipped++;
            continue;
        }

        // Assign form to user
        $stmt = $db->prepare("
            INSERT INTO user_forms (user_id, form_id, assigned_by, assigned_at, status)
            VALUES (?, ?, 1, datetime('now'), 'assigned')
        ");
        $stmt->execute([$userId, $formId]);

        echo "✓ User $tz ($idTypeName) - assigned\n";
        $assigned++;
    }

    echo "\n=== Summary ===\n";
    echo "Total users: " . count($users) . "\n";
    echo "Newly assigned: $assigned\n";
    echo "Already assigned (skipped): $skipped\n";
    echo "\n✓✓✓ Done! ✓✓✓\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
