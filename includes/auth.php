<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Authentication & Authorization Guards (Component A)
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Configure and start a secure session with hardened cookie flags.
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 1800; // 30 minutes session inactivity timeout

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

    // Check session inactivity timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        session_start();
        set_flash('info', 'Your session has expired due to inactivity. Please log in again.');
    }
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
 * Route guard: requires user to have one of the permitted roles.
 *
 * @param string|array $roles Allowed role name or array of role names
 * @return void
 */
function require_role(string|array $roles): void {
    require_login();

    $allowed = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['role'] ?? '';

    if (!in_array($userRole, $allowed, true)) {
        // Forbidden: redirect to access denied page
        http_response_code(403);
        set_flash('error', 'Access Denied: You do not have permission to view this resource.');
        redirect(url('auth/access_denied.php'));
    }
}
