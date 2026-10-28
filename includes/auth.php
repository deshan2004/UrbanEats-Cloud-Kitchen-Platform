<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Authentication & Authorization Guards (Component A)
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Configure and start a secure session with hardened cookie flags.
 * 
 * SECURITY RATIONALE (OWASP Session Management Standards):
 * 1. 'httponly' => true:
 *    Instructs browsers that the session cookie cannot be accessed via JavaScript
 *    (e.g., document.cookie). This effectively mitigates Cross-Site Scripting (XSS)
 *    session token theft.
 * 
 * 2. 'samesite' => 'Lax':
 *    Restricts the browser from sending session cookies with cross-site sub-requests,
 *    providing robust native protection against Cross-Site Request Forgery (CSRF).
 * 
 * 3. 'secure' => bool:
 *    Ensures cookies are exclusively transmitted over TLS/HTTPS encrypted connections
 *    when available, preventing man-in-the-middle packet sniffing.
 * 
 * 4. Inactivity Timeout (1800s / 30 mins):
 *    Compares the current timestamp with $_SESSION['last_activity']. If the user is idle
 *    for >30 minutes, the session is invalidated to prevent session hijacking on unattended terminals.
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 1800; // 30 minutes session inactivity timeout

        // Enforce hardened cookie flags before session initialization
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    // Enforce 30-minute idle inactivity timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        // Destroy existing session data securely
        session_unset();
        session_destroy();
        session_start();
        set_flash('info', 'Your session has expired due to inactivity. Please log in again.');
    }
    // Update last activity timestamp on every authenticated request
    $_SESSION['last_activity'] = time();
}

// Automatically enforce secure sessions whenever auth.php is included
start_secure_session();

/**
 * Checks if the current visitor is authenticated.
 *
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Returns the currently authenticated user's session data.
 *
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
        'role_id'   => $_SESSION['role_id'] ?? null,
    ];
}

/**
 * Route guard: requires user to be logged in.
 * Prevents unauthorized guest access to restricted views.
 *
 * @param string|null $redirectUrl
 * @return void
 */
function require_login(?string $redirectUrl = null): void {
    if (!is_logged_in()) {
        set_flash('error', 'You must log in to access this page.');
        redirect($redirectUrl ?? url('auth/login.php'));
    }
}

/**
 * Route guard: Role-Based Access Control (RBAC).
 * Enforces the Principle of Least Privilege across administrative and kitchen modules.
 *
 * SECURITY IMPLEMENTATION:
 * 1. Checks if visitor is authenticated (delegates to require_login()).
 * 2. Compares active session role against permitted whitelist ($roles).
 * 3. If unauthorized, issues an explicit RFC-compliant HTTP 403 (Forbidden) status code
 *    and halts access by redirecting to the access_denied page.
 *
 * @param string|array $roles Allowed role name or array of role names (e.g. 'admin', ['admin', 'kitchen_staff'])
 * @return void
 */
function require_role(string|array $roles): void {
    require_login();

    $allowed = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['role'] ?? '';

    // Whitelist verification: strict type check to prevent loose comparison exploits
    if (!in_array($userRole, $allowed, true)) {
        // Forbidden: Return HTTP 403 status and redirect to dedicated access denied view
        http_response_code(403);
        set_flash('error', 'Access Denied: You do not have permission to view this resource.');
        redirect(url('auth/access_denied.php'));
    }
}
