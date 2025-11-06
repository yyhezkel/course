#!/usr/bin/env php
<?php
/**
 * Admin Registration Invite Generator
 * Creates one-time registration links for new admin users
 *
 * Usage:
 *   php generate_invite.php create [role] [fullname] [expiry_hours]
 *   php generate_invite.php list
 *   php generate_invite.php revoke <token>
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

// Generate cryptographically secure random token
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Get database connection
$db = getDbConnection();

// Parse command line arguments
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'create':
        $role = $argv[2] ?? 'admin';
        $fullName = $argv[3] ?? null;
        $expiryHours = isset($argv[4]) ? (int)$argv[4] : null;

        print_header("Creating Registration Link");

        // Validate role
        $validRoles = ['admin', 'super_admin', 'moderator'];
        if (!in_array($role, $validRoles)) {
            print_error("Invalid role. Valid roles: " . implode(', ', $validRoles));
            exit(1);
        }

        // Generate unique token
        $token = generateToken();

        // Calculate expiry
        $expiresAt = null;
        if ($expiryHours) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));
        }

        // Insert into database
        try {
            $stmt = $db->prepare("
                INSERT INTO registration_tokens (token, role, preset_full_name, expires_at, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$token, $role, $fullName, $expiresAt]);

            $tokenId = $db->lastInsertId();

            print_success("Registration link created successfully!");
            echo "\n";
            print_info("Token ID: " . $tokenId);
            print_info("Role: " . $role);
            if ($fullName) {
                print_info("Preset Name: " . $fullName);
            }
            if ($expiresAt) {
                print_info("Expires: " . $expiresAt . " (" . $expiryHours . " hours from now)");
            } else {
                print_warning("Never expires (until used)");
            }

            echo "\n" . Color::BOLD . Color::GREEN . "Registration URL:" . Color::RESET . "\n";

            // Construct the URL - you may need to update the domain
            $baseUrl = "http://localhost"; // CHANGE THIS to your domain
            $registrationUrl = "{$baseUrl}/admin/register.html?token={$token}";

            echo Color::CYAN . $registrationUrl . Color::RESET . "\n\n";

            print_warning("Send this link to the new admin user");
            print_warning("This link can only be used ONCE");
            echo "\n";

        } catch (Exception $e) {
            print_error("Failed to create registration link: " . $e->getMessage());
            exit(1);
        }
        break;

    case 'list':
        print_header("Active Registration Links");

        $stmt = $db->query("
            SELECT
                id,
                token,
                role,
                preset_full_name,
                created_at,
                expires_at,
                used_at,
                is_active
            FROM registration_tokens
            ORDER BY created_at DESC
        ");
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tokens)) {
            print_warning("No registration tokens found");
            break;
        }

        printf("%-5s %-15s %-20s %-20s %-20s %-10s\n",
            "ID", "Role", "Preset Name", "Created", "Expires", "Status");
        echo str_repeat("-", 100) . "\n";

        foreach ($tokens as $token) {
            // Determine status
            $status = "Active";
            $color = Color::GREEN;

            if ($token['used_at']) {
                $status = "Used";
                $color = Color::BLUE;
            } elseif (!$token['is_active']) {
                $status = "Revoked";
                $color = Color::RED;
            } elseif ($token['expires_at'] && strtotime($token['expires_at']) < time()) {
                $status = "Expired";
                $color = Color::YELLOW;
            }

            printf("%-5s %-15s %-20s %-20s %-20s %s%s%s\n",
                $token['id'],
                $token['role'],
                $token['preset_full_name'] ?: '-',
                date('Y-m-d H:i', strtotime($token['created_at'])),
                $token['expires_at'] ? date('Y-m-d H:i', strtotime($token['expires_at'])) : 'Never',
                $color,
                $status,
                Color::RESET
            );
        }

        echo "\n" . Color::BOLD . "Total: " . count($tokens) . " token(s)" . Color::RESET . "\n";
        break;

    case 'revoke':
        if (!isset($argv[2])) {
            print_error("Usage: php generate_invite.php revoke <token_id_or_token>");
            exit(1);
        }

        $tokenIdentifier = $argv[2];

        print_header("Revoking Registration Link");

        // Check if it's an ID (numeric) or token (hex string)
        if (is_numeric($tokenIdentifier)) {
            $stmt = $db->prepare("SELECT id, token, used_at FROM registration_tokens WHERE id = ?");
            $stmt->execute([$tokenIdentifier]);
        } else {
            $stmt = $db->prepare("SELECT id, token, used_at FROM registration_tokens WHERE token = ?");
            $stmt->execute([$tokenIdentifier]);
        }

        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenRecord) {
            print_error("Token not found");
            exit(1);
        }

        if ($tokenRecord['used_at']) {
            print_warning("This token has already been used");
            exit(1);
        }

        // Revoke the token
        try {
            $stmt = $db->prepare("UPDATE registration_tokens SET is_active = 0 WHERE id = ?");
            $stmt->execute([$tokenRecord['id']]);

            print_success("Registration link revoked successfully");
            print_info("Token ID: " . $tokenRecord['id']);

        } catch (Exception $e) {
            print_error("Failed to revoke token: " . $e->getMessage());
            exit(1);
        }
        break;

    case 'cleanup':
        print_header("Cleaning Up Expired Tokens");

        $stmt = $db->prepare("
            UPDATE registration_tokens
            SET is_active = 0
            WHERE expires_at IS NOT NULL
            AND expires_at < datetime('now')
            AND used_at IS NULL
            AND is_active = 1
        ");
        $stmt->execute();
        $affected = $stmt->rowCount();

        if ($affected > 0) {
            print_success("Deactivated " . $affected . " expired token(s)");
        } else {
            print_info("No expired tokens found");
        }
        break;

    case 'help':
    default:
        print_header("Admin Registration Invite Generator");

        echo Color::BOLD . "Available Commands:" . Color::RESET . "\n\n";

        echo Color::CYAN . "  create [role] [fullname] [expiry_hours]" . Color::RESET . "\n";
        echo "    Create a new registration link\n";
        echo "    - role: admin, super_admin, or moderator (default: admin)\n";
        echo "    - fullname: Pre-set full name (optional)\n";
        echo "    - expiry_hours: Hours until link expires (optional, default: never)\n";
        echo "    Example: php generate_invite.php create admin \"John Doe\" 24\n\n";

        echo Color::CYAN . "  list" . Color::RESET . "\n";
        echo "    List all registration tokens and their status\n";
        echo "    Example: php generate_invite.php list\n\n";

        echo Color::CYAN . "  revoke <token_id_or_token>" . Color::RESET . "\n";
        echo "    Revoke a registration link (by ID or full token)\n";
        echo "    Example: php generate_invite.php revoke 5\n\n";

        echo Color::CYAN . "  cleanup" . Color::RESET . "\n";
        echo "    Deactivate all expired tokens\n";
        echo "    Example: php generate_invite.php cleanup\n\n";

        echo Color::BOLD . "Notes:" . Color::RESET . "\n";
        echo "  - Each token can only be used once\n";
        echo "  - Tokens can optionally expire after a set number of hours\n";
        echo "  - Revoked or used tokens cannot be reused\n";
        echo "  - Update the \$baseUrl in this script to match your domain\n\n";
        break;
}
?>
