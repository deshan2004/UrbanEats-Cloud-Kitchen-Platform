<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Utility Functions
 */

declare(strict_types=1);

/**
 * Escapes output safely for HTML rendering to prevent XSS.
 *
 * @param mixed $value
 * @return string
 */
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Calculates a reliable absolute web path for local assets and routing.
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        // Find project root directory relative to web root
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appRoot = str_replace('\\', '/', dirname(__DIR__));
        
        if (!empty($docRoot) && strpos($appRoot, $docRoot) === 0) {
            $subPath = substr($appRoot, strlen($docRoot));
            $base = rtrim($subPath, '/');
        } else {
            // Fallback: estimate from script name
            $scriptDir = dirname($scriptName);
            $base = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
            // If inside auth/ or includes/, step up
            $base = preg_replace('/(\/(auth|admin|menu|orders|public|includes))$/', '', $base);
        }
    }
    return ($base ?: '') . '/' . ltrim($path, '/');
}

/**
 * Redirect to a specific URL and stop script execution.
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Set a session flash message.
 *
 * @param string $type 'success', 'error', 'warning', 'info'
 * @param string $message
 * @return void
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the session flash message if any.
 *
 * @return array|null
 */
function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Retrieves the client's IP address safely.
 *
 * @return string
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/**
 * Component A contract function: writes an action to the audit_log table.
 * Used across Components A, B, and C for security and traceability.
 *
 * @param PDO $pdo
 * @param int|null $user_id
 * @param string $action
 * @param string|null $details
 * @return bool
 */
function log_action(PDO $pdo, ?int $user_id, string $action, ?string $details = null): bool {
    try {
        $sql = "INSERT INTO audit_log (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id'    => $user_id,
            ':action'     => mb_substr($action, 0, 100),
            ':details'    => $details,
            ':ip_address' => get_client_ip(),
            ':user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255),
        ]);
    } catch (PDOException $e) {
        error_log('[Audit Log Error] Failed to log action: ' . $e->getMessage());
        return false;
    }
}
