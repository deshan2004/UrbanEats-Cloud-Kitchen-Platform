<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Access Denied (HTTP 403) Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// Set HTTP 403 Forbidden status code for search engines and automated security scanners
http_response_code(403);

$pageTitle = 'Access Denied';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
        <h1 style="color: var(--danger); margin-bottom: 0.75rem;">Access Denied (403)</h1>
        <p style="margin-bottom: 1.5rem;">
            You do not have the required permissions or role privileges to access this requested resource.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <a href="<?= e(url('index.php')); ?>" class="btn btn-secondary">Return Home</a>
            <?php if (!is_logged_in()): ?>
                <a href="<?= e(url('auth/login.php')); ?>" class="btn btn-primary">Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
