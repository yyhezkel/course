<?php
/**
 * User Management Script
 * Use this to add, remove, or manage users in the system
 *
 * Usage:
 *   php manage_users.php add <id> [type]    - Add new user (type: tz or personal_number, default: tz)
 *   php manage_users.php remove <id>        - Remove user
 *   php manage_users.php unblock <id>       - Unblock user
 *   php manage_users.php reset <id>         - Reset failed attempts
 *   php manage_users.php list               - List all users
 *   php manage_users.php block <id>         - Block user
 */

require_once 'config.php';

if ($argc < 2) {
    echo "Usage: php manage_users.php <command> [id] [type]\n";
    echo "Commands:\n";
    echo "  add <id> [type]    - Add new user (type: tz or personal_number, default: tz)\n";
    echo "                       Examples:\n";
    echo "                         php manage_users.php add 123456789 tz\n";
    echo "                         php manage_users.php add 1234567 personal_number\n";
    echo "  remove <id>        - Remove user completely\n";
    echo "  block <id>         - Block user manually\n";
    echo "  unblock <id>       - Unblock user and reset attempts\n";
    echo "  reset <id>         - Reset failed login attempts\n";
    echo "  list               - List all users\n";
    exit(1);
}

$db = getDbConnection();
$command = $argv[1];
$tz = $argv[2] ?? null;
$idType = $argv[3] ?? 'tz'; // Default to 'tz' if not specified

switch ($command) {
    case 'add':
        if (!$tz) {
            echo "Error: ID required\n";
            exit(1);
        }

        // Validate ID type
        if (!in_array($idType, ['tz', 'personal_number'])) {
            echo "Error: ID type must be 'tz' or 'personal_number'\n";
            exit(1);
        }

        // Validate based on type
        if ($idType === 'tz') {
            if (!is_numeric($tz) || strlen($tz) !== 9) {
                echo "Error: תעודת זהות must be 9 digits\n";
                exit(1);
            }
        } else if ($idType === 'personal_number') {
            if (!is_numeric($tz) || strlen($tz) !== 7) {
                echo "Error: מספר אישי must be 7 digits\n";
                exit(1);
            }
        }

        try {
            $stmt = $db->prepare("INSERT INTO users (tz, id_type, is_blocked, failed_attempts) VALUES (?, ?, 0, 0)");
            $stmt->execute([$tz, $idType]);
            $typeName = $idType === 'tz' ? 'תעודת זהות' : 'מספר אישי';
            echo "✓ User $tz added successfully (Type: $typeName)\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                echo "✗ User $tz already exists\n";
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n";
            }
        }
        break;

    case 'remove':
        if (!$tz) {
            echo "Error: TZ required\n";
            exit(1);
        }
        // מחיקת תשובות הטופס קודם (Foreign Key)
        $stmt = $db->prepare("SELECT id FROM users WHERE tz = ?");
        $stmt->execute([$tz]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $db->prepare("DELETE FROM form_responses WHERE user_id = ?")->execute([$user['id']]);
            $db->prepare("DELETE FROM users WHERE tz = ?")->execute([$tz]);
            echo "✓ User $tz removed successfully (including all form data)\n";
        } else {
            echo "✗ User $tz not found\n";
        }
        break;

    case 'block':
        if (!$tz) {
            echo "Error: TZ required\n";
            exit(1);
        }
        $stmt = $db->prepare("UPDATE users SET is_blocked = 1 WHERE tz = ?");
        $stmt->execute([$tz]);
        if ($stmt->rowCount() > 0) {
            echo "✓ User $tz blocked\n";
        } else {
            echo "✗ User $tz not found\n";
        }
        break;

    case 'unblock':
        if (!$tz) {
            echo "Error: TZ required\n";
            exit(1);
        }
        $stmt = $db->prepare("UPDATE users SET is_blocked = 0, failed_attempts = 0 WHERE tz = ?");
        $stmt->execute([$tz]);
        if ($stmt->rowCount() > 0) {
            echo "✓ User $tz unblocked and attempts reset\n";
        } else {
            echo "✗ User $tz not found\n";
        }
        break;

    case 'reset':
        if (!$tz) {
            echo "Error: TZ required\n";
            exit(1);
        }
        $stmt = $db->prepare("UPDATE users SET failed_attempts = 0 WHERE tz = ?");
        $stmt->execute([$tz]);
        if ($stmt->rowCount() > 0) {
            echo "✓ Failed attempts reset for user $tz\n";
        } else {
            echo "✗ User $tz not found\n";
        }
        break;

    case 'list':
        $result = $db->query("SELECT u.*, COUNT(fr.id) as response_count
                              FROM users u
                              LEFT JOIN form_responses fr ON u.id = fr.user_id
                              GROUP BY u.id
                              ORDER BY u.id");
        $users = $result->fetchAll(PDO::FETCH_ASSOC);

        echo "\n=== All Users ===\n";
        echo sprintf("%-4s %-12s %-15s %-8s %-15s %-20s %-10s\n",
                     "ID", "Number", "Type", "Blocked", "Failed Attempts", "Last Login", "Responses");
        echo str_repeat("-", 95) . "\n";

        foreach ($users as $user) {
            $typeName = ($user['id_type'] ?? 'tz') === 'tz' ? 'ת.ז.' : 'מספר אישי';
            echo sprintf("%-4s %-12s %-15s %-8s %-15s %-20s %-10s\n",
                        $user['id'],
                        $user['tz'],
                        $typeName,
                        $user['is_blocked'] ? 'YES' : 'NO',
                        $user['failed_attempts'],
                        $user['last_login'] ?? 'Never',
                        $user['response_count']);
        }
        echo "\nTotal users: " . count($users) . "\n\n";
        break;

    default:
        echo "Unknown command: $command\n";
        exit(1);
}
?>
