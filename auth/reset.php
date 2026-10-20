<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Token-Based Password Recovery
 * 
 * Implements time-limited token generation (1-hour expiration)
 * and secure credential reset without relying on external SMTP mailers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Reset Password';
$error = null;
$success = null;
$generatedResetLink = null;

$token = trim((string)($_GET['token'] ?? ''));
$validResetRecord = null;

// If a token is provided in the URL, validate it
if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    try {
        $stmt = $pdo->prepare(
            "SELECT pr.id, pr.user_id, pr.expires_at, pr.is_used, u.username, u.email, u.full_name
             FROM password_resets pr
             JOIN users u ON pr.user_id = u.id
             WHERE pr.token_hash = :hash
             LIMIT 1"
        );
        $stmt->execute([':hash' => $tokenHash]);
        $record = $stmt->fetch();

        if (!$record) {
            $error = 'The password reset token is invalid.';
        } elseif ((int)$record['is_used'] === 1) {
            $error = 'This password reset link has already been used.';
        } elseif (strtotime($record['expires_at']) < time()) {
            $error = 'This password reset link has expired (tokens are valid for 1 hour).';
        } else {
            $validResetRecord = $record;
        }
    } catch (PDOException $e) {
        error_log('[Reset Token Check Error] ' . $e->getMessage());
        $error = 'Unable to verify token. Please try again.';
    }
}

// Case 1: Submit new password using valid token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_new_password') {
    if (!$validResetRecord) {
        $error = 'Invalid or expired token session.';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($newPassword)) {
            $error = 'Please enter your new password.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters in length.';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $error = 'Password must contain uppercase, lowercase, and numeric characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            try {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Update password in users table
                $updateUser = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
                $updateUser->execute([
                    ':hash' => $newHash,
                    ':id'   => $validResetRecord['user_id'],
                ]);

                // Mark token as used
                $markUsed = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE id = :id");
                $markUsed->execute([':id' => $validResetRecord['id']]);

                // Audit logging
                log_action(
                    $pdo, 
                    (int)$validResetRecord['user_id'], 
                    'PASSWORD_RESET_SUCCESS', 
                    "Password reset completed successfully via token from IP " . get_client_ip()
                );

                set_flash('success', 'Your password has been reset successfully! Please sign in with your new credentials.');
                redirect(url('auth/login.php'));
            } catch (PDOException $e) {
                error_log('[Reset Password Apply Error] ' . $e->getMessage());
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}

// Case 2: Request token generation via email/username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_token') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $identity = trim((string)($_POST['identity'] ?? ''));

        if (empty($identity)) {
            $error = 'Please provide your account username or registered email.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, email, full_name FROM users WHERE username = :id1 OR email = :id2 LIMIT 1");
                $stmt->execute([':id1' => $identity, ':id2' => $identity]);
                $user = $stmt->fetch();

                if ($user) {
                    // Invalidate past unused tokens for this user
                    $revokeStmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = :uid AND is_used = 0");
                    $revokeStmt->execute([':uid' => $user['id']]);

                    // Generate cryptographically secure token
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration

                    $insertStmt = $pdo->prepare(
                        "INSERT INTO password_resets (user_id, token_hash, expires_at, is_used, created_at)
                         VALUES (:uid, :thash, :expires, 0, NOW())"
                    );
                    $insertStmt->execute([
                        ':uid'     => $user['id'],
                        ':thash'   => $tokenHash,
                        ':expires' => $expiresAt,
                    ]);

                    log_action(
                        $pdo, 
                        (int)$user['id'], 
                        'PASSWORD_RESET_REQUESTED', 
                        "Password reset token generated for user '{$user['username']}'"
                    );

                    // Build full URL
                    $generatedResetLink = url('auth/reset.php?token=' . $rawToken);
                    $success = 'Password reset link generated successfully! (Per assignment specification, link is presented directly on screen).';
                } else {
                    $error = 'No active account found matching that username or email address.';
                }
            } catch (PDOException $e) {
                error_log('[Reset Token Request Error] ' . $e->getMessage());
                $error = 'An error occurred while generating reset token.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 520px;">
        <div class="auth-header">
            <span class="brand-badge" style="margin-bottom: 0.75rem; display: inline-block;">Account Recovery</span>
            <h1><?= $validResetRecord ? 'Create New Password' : 'Reset Password'; ?></h1>
            <p>
                <?= $validResetRecord 
                    ? 'Setting a new password for <strong>' . e($validResetRecord['username']) . '</strong>' 
                    : 'Enter your username or email to generate a time-limited reset link.'; ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <span>⚠️</span>
                <div><?= e($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <span>✅</span>
                <div><?= e($success); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($generatedResetLink): ?>
            <div style="background: var(--bg-surface-elevated); border: 1px solid var(--border-medium); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                <p style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">
                    🔗 Generated One-Time Recovery Link (Valid for 60 Minutes):
                </p>
                <div style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); word-break: break-all; font-family: monospace; font-size: 0.85rem; color: #fdba74; margin-bottom: 0.85rem;">
                    <?= e($generatedResetLink); ?>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="<?= e($generatedResetLink); ?>" class="btn btn-primary btn-sm" style="flex: 1;">
                        Open Reset Form &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($validResetRecord): ?>
            <!-- Set New Password Form -->
            <form action="<?= e(url('auth/reset.php?token=' . $token)); ?>" method="POST" novalidate id="form-set-password">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="set_new_password">

                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-control" 
                           required 
                           autocomplete="new-password"
                           placeholder="••••••••">
                    <small class="input-hint">Minimum 8 characters with uppercase, lowercase, and numbers.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control" 
                           required 
                           autocomplete="new-password"
                           placeholder="••••••••">
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-block" id="btn-submit-reset">
                        Reset Password &amp; Sign In
                    </button>
                </div>
            </form>
        <?php else: ?>
            <!-- Request Reset Token Form -->
            <form action="<?= e(url('auth/reset.php')); ?>" method="POST" novalidate id="form-request-reset">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="request_token">

                <div class="form-group">
                    <label for="identity">Username or Registered Email <span class="required">*</span></label>
                    <input type="text" 
                           id="identity" 
                           name="identity" 
                           class="form-control" 
                           required 
                           placeholder="e.g. admin or customer@email.com">
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-block" id="btn-request-token">
                        Generate Reset Link &rarr;
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle);">
            <p>Remember your password? <a href="<?= e(url('auth/login.php')); ?>" style="font-weight: 600;">Back to Sign In</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
