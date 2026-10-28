<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Logout Handler
 * 
 * Safely terminates authenticated sessions, cleans cookies, and logs the event.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $username = (string)($_SESSION['username'] ?? 'unknown');

    // 1. Audit Logging: Record session termination before session variables are cleared
    if ($pdo && $userId > 0) {
        log_action($pdo, $userId, 'LOGOUT', "User '{$username}' logged out successfully");
    }

    // SECURITY: Comprehensive 3-Step Session Teardown (OWASP Session Invalidation)
    // Step A: Clear all server-side session data from memory
    $_SESSION = [];

    // Step B: Explicitly expire and overwrite the browser's session cookie in the past (time() - 42000)
    // Prevents zombie cookie retention in browser storage
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Step C: Destroy the active server session file/backend storage
    session_destroy();
}

// Start fresh temporary session to carry the flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'You have been safely signed out. See you next time!');

redirect(url('auth/login.php'));
