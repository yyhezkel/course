<?php
/**
 * Emergency Admin Password Reset
 * This bypasses validation to set a new password
 * USE WITH CAUTION - Only for emergency access
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain');

// Set your desired password here
$newPassword = 'Admin@Secure123!';  // CHANGE THIS!

echo "=== Emergency Admin Password Reset ===\n\n";

try {
    $db = getDbConnection();

    // Hash the password
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update the admin password
    $stmt = $db->prepare("
        UPDATE admin_users
        SET password_hash = ?, updated_at = datetime('now')
        WHERE username = 'admin'
    ");
    $stmt->execute([$passwordHash]);

    if ($stmt->rowCount() > 0) {
        echo "✓ Password updated successfully!\n\n";
        echo "New Credentials:\n";
        echo "  Username: admin\n";
        echo "  Password: " . $newPassword . "\n\n";
        echo "⚠️  IMPORTANT: Save this password securely!\n\n";
        echo "For security, delete this file after use:\n";
        echo "rm " . __FILE__ . "\n";
    } else {
        echo "✗ Failed to update password.\n";
        echo "Admin user might not exist.\n";
    }

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
