#!/usr/bin/env php
<?php
/**
 * Admin User Management Script
 * Command-line tool to create, list, update, and delete admin users
 *
 * Usage:
 *   php manage_admins.php list                                    - List all admin users
 *   php manage_admins.php create <username> <password> <fullname> - Create new admin
 *   php manage_admins.php password <username> <new_password>      - Change password
 *   php manage_admins.php activate <username>                     - Activate admin
 *   php manage_admins.php deactivate <username>                   - Deactivate admin
 *   php manage_admins.php delete <username>                       - Delete admin
 */

require_once __DIR__ . '/../config.php';

// Colors for terminal output
class Color {
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

function print_success($message) {
    echo Color::GREEN . "✓ " . $message . Color::RESET . "\n";
}

function print_error($message) {
    echo Color::RED . "✗ " . $message . Color::RESET . "\n";
}

function print_warning($message) {
    echo Color::YELLOW . "⚠ " . $message . Color::RESET . "\n";
}

function print_info($message) {
    echo Color::CYAN . "ℹ " . $message . Color::RESET . "\n";
}

function print_header($message) {
    echo "\n" . Color::BOLD . Color::BLUE . "=== " . $message . " ===" . Color::RESET . "\n\n";
}

// Get database connection
$db = getDbConnection();

// Parse command line arguments
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'list':
        print_header("Admin Users List");

        $stmt = $db->query("
            SELECT
                id,
                username,
                full_name,
                role,
                is_active,
                last_login,
                created_at
            FROM admin_users
            ORDER BY created_at DESC
        ");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($admins)) {
            print_warning("No admin users found");
            break;
        }

        printf("%-5s %-20s %-30s %-15s %-10s %-20s\n",
            "ID", "Username", "Full Name", "Role", "Status", "Last Login");
        echo str_repeat("-", 110) . "\n";

        foreach ($admins as $admin) {
            $status = $admin['is_active'] ? Color::GREEN . "Active" . Color::RESET : Color::RED . "Inactive" . Color::RESET;
            $lastLogin = $admin['last_login'] ? date('Y-m-d H:i', strtotime($admin['last_login'])) : 'Never';

            printf("%-5s %-20s %-30s %-15s %-10s %-20s\n",
                $admin['id'],
                $admin['username'],
                $admin['full_name'] ?: '-',
                $admin['role'],
                $status,
                $lastLogin
            );
        }

        echo "\n" . Color::BOLD . "Total: " . count($admins) . " admin(s)" . Color::RESET . "\n";
        break;

    case 'create':
        if (!isset($argv[2]) || !isset($argv[3]) || !isset($argv[4])) {
            print_error("Usage: php manage_admins.php create <username> <password> <email> [fullname] [role]");
            exit(1);
        }

        $username = $argv[2];
        $password = $argv[3];
        $email = $argv[4];
        $fullName = $argv[5] ?? '';
        $role = $argv[6] ?? 'admin';

        print_header("Creating Admin User");

        // Validate username
        if (strlen($username) < 3) {
            print_error("Username must be at least 3 characters");
            exit(1);
        }

        // Validate password
        if (strlen($password) < 6) {
            print_error("Password must be at least 6 characters");
            exit(1);
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            print_error("Invalid email address");
            exit(1);
        }

        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            print_error("Username already exists");
            exit(1);
        }

        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            print_error("Email already exists");
            exit(1);
        }

        // Create admin
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO admin_users (username, password_hash, email, full_name, role, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
            ");
            $stmt->execute([$username, $passwordHash, $email, $fullName, $role]);

            print_success("Admin user created successfully!");
            print_info("Username: " . $username);
            print_info("Email: " . $email);
            print_info("Full Name: " . ($fullName ?: 'Not set'));
            print_info("Role: " . $role);
            print_warning("Please save the password securely - it cannot be retrieved later");

        } catch (Exception $e) {
            print_error("Failed to create admin: " . $e->getMessage());
            exit(1);
        }
        break;

    case 'password':
        if (!isset($argv[2]) || !isset($argv[3])) {
            print_error("Usage: php manage_admins.php password <username> <new_password>");
            exit(1);
        }

        $username = $argv[2];
        $newPassword = $argv[3];

        print_header("Changing Password");

        // Validate password
        if (strlen($newPassword) < 6) {
            print_error("Password must be at least 6 characters");
            exit(1);
        }

        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            print_error("Admin user not found");
            exit(1);
        }

        // Update password
        try {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                UPDATE admin_users
                SET password_hash = ?, updated_at = datetime('now')
                WHERE username = ?
            ");
            $stmt->execute([$passwordHash, $username]);

            print_success("Password changed successfully for user: " . $username);
            print_warning("Please save the new password securely");

        } catch (Exception $e) {
            print_error("Failed to change password: " . $e->getMessage());
            exit(1);
        }
        break;

    case 'activate':
    case 'deactivate':
        if (!isset($argv[2])) {
            print_error("Usage: php manage_admins.php {$command} <username>");
            exit(1);
        }

        $username = $argv[2];
        $isActive = ($command === 'activate') ? 1 : 0;

        print_header(ucfirst($command) . " Admin User");

        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            print_error("Admin user not found");
            exit(1);
        }

        // Update status
        try {
            $stmt = $db->prepare("
                UPDATE admin_users
                SET is_active = ?, updated_at = datetime('now')
                WHERE username = ?
            ");
            $stmt->execute([$isActive, $username]);

            print_success("User " . $username . " has been " . ($isActive ? "activated" : "deactivated"));

        } catch (Exception $e) {
            print_error("Failed to update user: " . $e->getMessage());
            exit(1);
        }
        break;

    case 'delete':
        if (!isset($argv[2])) {
            print_error("Usage: php manage_admins.php delete <username>");
            exit(1);
        }

        $username = $argv[2];

        print_header("Delete Admin User");

        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            print_error("Admin user not found");
            exit(1);
        }

        // Count total admins
        $totalAdmins = $db->query("SELECT COUNT(*) FROM admin_users WHERE is_active = 1")->fetchColumn();
        if ($totalAdmins <= 1) {
            print_error("Cannot delete the last active admin user");
            exit(1);
        }

        // Confirm deletion
        print_warning("Are you sure you want to delete user: " . $username . "? (yes/no)");
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) !== 'yes') {
            print_info("Deletion cancelled");
            exit(0);
        }

        // Delete admin
        try {
            $stmt = $db->prepare("DELETE FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);

            print_success("Admin user deleted successfully: " . $username);

        } catch (Exception $e) {
            print_error("Failed to delete user: " . $e->getMessage());
            exit(1);
        }
        break;

    case 'info':
        if (!isset($argv[2])) {
            print_error("Usage: php manage_admins.php info <username>");
            exit(1);
        }

        $username = $argv[2];

        print_header("Admin User Information");

        $stmt = $db->prepare("
            SELECT
                id,
                username,
                full_name,
                role,
                is_active,
                last_login,
                created_at,
                updated_at
            FROM admin_users
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            print_error("Admin user not found");
            exit(1);
        }

        echo Color::BOLD . "ID:" . Color::RESET . " " . $admin['id'] . "\n";
        echo Color::BOLD . "Username:" . Color::RESET . " " . $admin['username'] . "\n";
        echo Color::BOLD . "Full Name:" . Color::RESET . " " . ($admin['full_name'] ?: '-') . "\n";
        echo Color::BOLD . "Role:" . Color::RESET . " " . $admin['role'] . "\n";
        echo Color::BOLD . "Status:" . Color::RESET . " " . ($admin['is_active'] ? Color::GREEN . "Active" . Color::RESET : Color::RED . "Inactive" . Color::RESET) . "\n";
        echo Color::BOLD . "Last Login:" . Color::RESET . " " . ($admin['last_login'] ? date('Y-m-d H:i:s', strtotime($admin['last_login'])) : 'Never') . "\n";
        echo Color::BOLD . "Created At:" . Color::RESET . " " . date('Y-m-d H:i:s', strtotime($admin['created_at'])) . "\n";
        echo Color::BOLD . "Updated At:" . Color::RESET . " " . ($admin['updated_at'] ? date('Y-m-d H:i:s', strtotime($admin['updated_at'])) : 'Never') . "\n";

        // Get activity count
        $stmt = $db->prepare("SELECT COUNT(*) FROM activity_log WHERE admin_user_id = ?");
        $stmt->execute([$admin['id']]);
        $activityCount = $stmt->fetchColumn();

        echo Color::BOLD . "Total Actions:" . Color::RESET . " " . $activityCount . "\n";
        break;

    case 'help':
    default:
        print_header("Admin User Management Tool");

        echo Color::BOLD . "Available Commands:" . Color::RESET . "\n\n";

        echo Color::CYAN . "  list" . Color::RESET . "\n";
        echo "    List all admin users\n\n";

        echo Color::CYAN . "  create <username> <password> <email> [fullname] [role]" . Color::RESET . "\n";
        echo "    Create a new admin user\n";
        echo "    Example: php manage_admins.php create admin123 MyPass123 admin@example.com \"John Doe\" admin\n\n";

        echo Color::CYAN . "  password <username> <new_password>" . Color::RESET . "\n";
        echo "    Change admin password\n";
        echo "    Example: php manage_admins.php password admin123 NewPass456\n\n";

        echo Color::CYAN . "  activate <username>" . Color::RESET . "\n";
        echo "    Activate an admin user\n";
        echo "    Example: php manage_admins.php activate admin123\n\n";

        echo Color::CYAN . "  deactivate <username>" . Color::RESET . "\n";
        echo "    Deactivate an admin user\n";
        echo "    Example: php manage_admins.php deactivate admin123\n\n";

        echo Color::CYAN . "  info <username>" . Color::RESET . "\n";
        echo "    Show detailed information about an admin user\n";
        echo "    Example: php manage_admins.php info admin123\n\n";

        echo Color::CYAN . "  delete <username>" . Color::RESET . "\n";
        echo "    Delete an admin user (requires confirmation)\n";
        echo "    Example: php manage_admins.php delete admin123\n\n";

        echo Color::BOLD . "Notes:" . Color::RESET . "\n";
        echo "  - Username must be at least 3 characters\n";
        echo "  - Password must be at least 6 characters\n";
        echo "  - Default role is 'admin'\n";
        echo "  - Cannot delete the last active admin\n\n";
        break;
}
?>
