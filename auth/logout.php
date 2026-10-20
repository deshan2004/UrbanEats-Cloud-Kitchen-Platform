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

    // Audit log the logout event
    if ($pdo && $userId > 0) {
        log_action($pdo, $userId, 'LOGOUT', "User '{$username}' logged out successfully");
    }

    // Unset all session variables
    $_SESSION = [];

    // Delete the session cookie if ini settings allow
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

    // Destroy session completely
    session_destroy();
}

// Start fresh temporary session to carry the flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'You have been safely signed out. See you next time!');

redirect(url('auth/login.php'));
