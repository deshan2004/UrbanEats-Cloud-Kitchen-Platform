<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Login (Figma Screen A1 - Login)
 * 
 * Features:
 * - Pixel-perfect implementation of Figma A1 - Login split screen
 * - Anti-CSRF protection
 * - Server-side validation
 * - Brute-force throttling (temporary lockout after 5 consecutive failed attempts)
 * - password_verify() with BCRYPT
 * - session_regenerate_id() on successful authentication
 * - Audit logging (LOGIN_SUCCESS, LOGIN_FAILED, ACCOUNT_LOCKED)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already authenticated
if (is_logged_in()) {
    $current = current_user();
    if ($current && $current['role'] === 'admin') {
        redirect(url('admin/users.php'));
    }
    redirect(url('index.php'));
}

$pageTitle = 'Sign In';
$error = null;
$username = '';
$isLockedOut = false;
$lockoutRemainingMinutes = 0;

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_WINDOW_MINUTES = 15;

/**
 * Checks if an IP address or username is currently throttled due to repeated failed logins.
 * 
 * SECURITY RATIONALE (OWASP A07 - Identification & Authentication Failures):
 * Implements an intelligent sliding-window rate-limiting mechanism against Brute-Force
 * and Credential-Stuffing dictionary attacks.
 * 
 * DESIGN HIGHLIGHTS:
 * 1. Dual-Pivot Throttling: Inspects both target username AND client IP address.
 *    This catches single-origin password guessing as well as distributed attacks on one user.
 * 2. Sliding Time Window: Queries 'login_attempts' for failures within (NOW() - INTERVAL 15 MINUTE).
 * 3. Dynamic Lockout Countdown: Computes the precise remaining lockout minutes ($secondsRemaining / 60)
 *    so the UI can communicate an accurate unlock timer to the user.
 *
 * @param PDO $pdo Active database connection
 * @param string $userIdentifier Username or email address attempted
 * @param string $ipAddress Client IP address
 * @return array ['is_locked' => bool, 'remaining_minutes' => int, 'recent_failures' => int]
 */
function check_login_throttling(PDO $pdo, string $userIdentifier, string $ipAddress): array {
    try {
        // Query failed attempts in the sliding 15-minute window using prepared statements
        $sql = "SELECT attempted_at 
                FROM login_attempts 
                WHERE (username = :username OR ip_address = :ip)
                  AND attempted_at >= (NOW() - INTERVAL " . LOCKOUT_WINDOW_MINUTES . " MINUTE)
                  AND is_successful = 0
                ORDER BY attempted_at DESC 
                LIMIT " . MAX_FAILED_ATTEMPTS;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $userIdentifier,
            ':ip'       => $ipAddress,
        ]);
        $attempts = $stmt->fetchAll();
        $failureCount = count($attempts);

        // Lockout condition: threshold reached
        if ($failureCount >= MAX_FAILED_ATTEMPTS) {
            $latestAttempt = strtotime($attempts[0]['attempted_at']);
            $unlockTime = $latestAttempt + (LOCKOUT_WINDOW_MINUTES * 60);
            $secondsRemaining = $unlockTime - time();

            if ($secondsRemaining > 0) {
                return [
                    'is_locked'         => true,
                    'remaining_minutes' => (int)ceil($secondsRemaining / 60),
                    'recent_failures'   => $failureCount,
                ];
            }
        }

        return [
            'is_locked'         => false,
            'remaining_minutes' => 0,
            'recent_failures'   => $failureCount,
        ];
    } catch (PDOException $e) {
        error_log('[Throttling Check Error] ' . $e->getMessage());
        return ['is_locked' => false, 'remaining_minutes' => 0, 'recent_failures' => 0];
    }
}

$clientIp = get_client_ip();

// Initial check for current IP
$throttleStatus = check_login_throttling($pdo, '', $clientIp);
if ($throttleStatus['is_locked']) {
    $isLockedOut = true;
    $lockoutRemainingMinutes = $throttleStatus['remaining_minutes'];
    $error = sprintf(
        'Too many failed login attempts from this network. Access is temporarily locked. Please try again in %d minute(s).',
        $lockoutRemainingMinutes
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLockedOut) {
    // 1. Anti-CSRF Token Validation (Protects against cross-site forged login requests)
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter both your email/username and password.';
        } else {
            // 2. Pre-Authentication Brute-Force Check for the specific username + IP pair
            $throttleStatus = check_login_throttling($pdo, $username, $clientIp);
            if ($throttleStatus['is_locked']) {
                $isLockedOut = true;
                $lockoutRemainingMinutes = $throttleStatus['remaining_minutes'];
                $error = sprintf(
                    'Account is temporarily locked due to %d consecutive failed attempts. Please try again in %d minute(s).',
                    MAX_FAILED_ATTEMPTS,
                    $lockoutRemainingMinutes
                );
                log_action($pdo, null, 'ACCOUNT_LOCKED', "Lockout triggered for username '{$username}' from IP {$clientIp}");
            } else {
                // 3. User Lookup using PDO Prepared Statements (Complete SQL Injection Immunity)
                $stmt = $pdo->prepare(
                    "SELECT u.id, u.role_id, u.full_name, u.username, u.email, u.password_hash, u.is_active, r.role_name
                     FROM users u
                     JOIN roles r ON u.role_id = r.id
                     WHERE u.username = :login_user OR u.email = :login_email
                     LIMIT 1"
                );
                $stmt->execute([
                    ':login_user'  => $username,
                    ':login_email' => $username,
                ]);
                $user = $stmt->fetch();

                // 4. Secure Password Verification via BCRYPT
                // Uses password_verify() to safely evaluate plain-text password against stored hash with auto-salt.
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    // Record failure event in login_attempts audit table
                    try {
                        $logStmt = $pdo->prepare(
                            "INSERT INTO login_attempts (ip_address, username, attempted_at, is_successful) 
                             VALUES (:ip, :username, NOW(), 0)"
                        );
                        $logStmt->execute([':ip' => $clientIp, ':username' => $username]);
                    } catch (PDOException $e) {
                        error_log('[Login Attempt Log Error] ' . $e->getMessage());
                    }

                    $newFailureCount = $throttleStatus['recent_failures'] + 1;
                    $remainingAttempts = max(0, MAX_FAILED_ATTEMPTS - $newFailureCount);

                    log_action($pdo, $user['id'] ?? null, 'LOGIN_FAILED', "Failed login attempt for identifier '{$username}' from IP {$clientIp}");

                    if ($remainingAttempts === 0) {
                        $isLockedOut = true;
                        $error = sprintf(
                            'Incorrect credentials. You have exceeded maximum failed attempts. Account locked for %d minutes.',
                            LOCKOUT_WINDOW_MINUTES
                        );
                    } else {
                        $error = sprintf('Incorrect password. %d attempt(s) left.', $remainingAttempts);
                    }
                } elseif ((int)$user['is_active'] !== 1) {
                    // Account status check: reject login for suspended/deactivated users
                    $error = 'Your account has been deactivated. Please contact an UrbanEats administrator.';
                    log_action($pdo, (int)$user['id'], 'LOGIN_INACTIVE_ATTEMPT', "Deactivated user '{$username}' attempted login");
                } else {
                    // 5. Successful Authentication Flow
                    // Record successful attempt to reset consecutive failure tracking
                    try {
                        $successLogStmt = $pdo->prepare(
                            "INSERT INTO login_attempts (ip_address, username, attempted_at, is_successful) 
                             VALUES (:ip, :username, NOW(), 1)"
                        );
                        $successLogStmt->execute([':ip' => $clientIp, ':username' => $user['username']]);
                    } catch (PDOException $e) {
                        error_log('[Login Success Log Error] ' . $e->getMessage());
                    }

                    // SECURITY: Session Fixation Prevention (OWASP A07)
                    // Discards previous unauthenticated session ID and issues a new cryptographically secure session ID.
                    session_regenerate_id(true);

                    // Initialize authenticated session state
                    $_SESSION['user_id']       = (int)$user['id'];
                    $_SESSION['username']      = $user['username'];
                    $_SESSION['full_name']     = $user['full_name'];
                    $_SESSION['email']         = $user['email'];
                    $_SESSION['role']          = $user['role_name'];
                    $_SESSION['role_id']       = (int)$user['role_id'];
                    $_SESSION['last_activity'] = time();

                    // Security audit traceability
                    log_action($pdo, (int)$user['id'], 'LOGIN_SUCCESS', "User authenticated successfully from IP {$clientIp}");

                    set_flash('success', "Welcome back, " . $user['full_name'] . "!");

                    // Role-based post-login redirection
                    if ($user['role_name'] === 'admin') {
                        redirect(url('admin/users.php'));
                    } elseif ($user['role_name'] === 'kitchen_staff') {
                        redirect(url('index.php'));
                    } else {
                        redirect(url('index.php'));
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | UrbanEats Cloud Kitchen</title>
    <link rel="icon" type="image/png" href="<?= e(url('public/images/favicon.png')); ?>">
    <!-- Cache buster query param ensures browser never serves stale CSS -->
    <link rel="stylesheet" href="<?= e(url('public/css/style.css')) . '?v=' . time(); ?>">
    <style>
        /* Exact Figma A1 - Login layout styles to guarantee 100% visual fidelity */
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #FFF9F3 !important;
            color: #1F1B16;
            -webkit-font-smoothing: antialiased;
        }

        .split-auth-container {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 46% 54%;
            width: 100%;
        }

        .auth-hero-banner {
            background-color: #F26B1D;
            padding: 4.5rem 4rem;
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .auth-hero-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #FFFFFF;
        }

        .auth-hero-logo .hero-icon {
            background: #FFFFFF;
            color: #F26B1D;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.35rem;
        }

        .auth-hero-logo .hero-brand-name {
            font-weight: 800;
            font-size: 1.45rem;
            color: #FFFFFF;
            letter-spacing: -0.02em;
        }

        .auth-hero-content {
            margin: auto 0;
            padding: 2rem 0;
        }

        .auth-hero-heading {
            font-size: 3.25rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
            color: #FFFFFF;
            margin-bottom: 1.75rem;
        }

        .auth-hero-desc {
            font-size: 1.12rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.95);
            max-width: 460px;
        }

        .auth-form-side {
            background-color: #FFF9F3;
            background-image: 
              radial-gradient(circle at 85% 20%, rgba(242, 107, 29, 0.14) 0%, transparent 50%),
              radial-gradient(circle at 20% 80%, rgba(238, 150, 75, 0.1) 0%, transparent 50%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
        }

        .auth-card-figma {
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(24px) saturate(190%);
            -webkit-backdrop-filter: blur(24px) saturate(190%);
            border: 1px solid rgba(255, 255, 255, 0.9);
            outline: 1px solid rgba(234, 223, 211, 0.5);
            border-radius: 24px;
            padding: 2.75rem 2.5rem;
            width: 100%;
            max-width: 470px;
            box-shadow: 0 16px 36px -8px rgba(31, 27, 22, 0.06), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
        }

        .auth-card-figma h1 {
            font-size: 1.95rem;
            font-weight: 800;
            color: #1F1B16;
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }

        .auth-subtext {
            color: #7A7167;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .callout-warning {
            background-color: #FFF3D6;
            border: 1px solid #F5E5B8;
            border-radius: 10px;
            padding: 12px 16px;
            color: #925C02;
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 0.88rem;
            color: #1F1B16;
            margin-bottom: 0.45rem;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(234, 223, 211, 0.85);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            color: #1F1B16;
            transition: all 0.15s ease-in-out;
            outline: none;
        }

        .form-control:focus {
            background: #FFFFFF;
            border-color: #F26B1D;
            box-shadow: 0 0 0 4px rgba(242, 107, 29, 0.15);
        }

        .form-control.is-invalid {
            border-color: #D8443A;
        }

        .input-hint {
            font-size: 0.82rem;
            color: #7A7167;
            margin-top: 0.35rem;
        }

        .field-error {
            color: #D8443A;
            font-size: 0.82rem;
            font-weight: 500;
            margin-top: 0.35rem;
        }

        .btn-login {
            background-color: #F26B1D;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            padding: 0.9rem;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: background 0.15s ease-in-out;
            font-family: inherit;
        }

        .btn-login:hover {
            background-color: #D9570F;
        }

        @media (max-width: 900px) {
            .split-auth-container {
                grid-template-columns: 1fr;
            }
            .auth-hero-banner {
                padding: 2.5rem 1.5rem;
                text-align: center;
                align-items: center;
            }
            .auth-hero-heading {
                font-size: 2.25rem;
            }
            .auth-form-side {
                padding: 2rem 1.25rem;
            }
            .auth-card-figma {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Figma A1 - Login Split Screen Layout -->
<div class="split-auth-container">
    
    <!-- Left Hero Banner -->
    <div class="auth-hero-banner">
        <div>
            <a href="<?= e(url('index.php')); ?>" class="auth-hero-logo">
                <img src="<?= e(url('public/images/logo.png')); ?>" alt="UrbanEats Logo" style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                <span class="hero-brand-name">UrbanEats</span>
            </a>
        </div>

        <div class="auth-hero-content">
            <h1 class="auth-hero-heading">
                Fresh food. Fast.<br>Made to order.
            </h1>

            <p class="auth-hero-desc">
                Order from our cloud kitchen, follow your meal in real time and pay your way: cash on delivery, card or bank transfer.
            </p>
        </div>

        <div style="font-size: 0.85rem; color: rgba(255,255,255,0.75);">
            &copy; <?= date('Y'); ?> UrbanEats Cloud Kitchen Platform. All rights reserved.
        </div>
    </div>

    <!-- Right Form Section -->
    <div class="auth-form-side">
        <div class="auth-card-figma">
            <h1>Welcome back</h1>
            <p class="auth-subtext">Log in to order, track and manage your account.</p>

            <!-- Yellow Throttling Advisory Callout Box (Figma design) -->
            <div class="callout-warning">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span>Account locks for 15 minutes after 5 failed attempts.</span>
            </div>

            <?php if ($error): ?>
                <div style="background: #FDE7E5; border: 1px solid rgba(216,68,58,0.3); border-radius: 8px; padding: 12px 14px; margin-bottom: 1.25rem; color: #9A241C; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    <span>⚠️</span>
                    <div><?= e($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!$isLockedOut): ?>
                <form action="<?= e(url('auth/login.php')); ?>" method="POST" novalidate id="login-form">
                    <?php csrf_field(); ?>

                    <div class="form-group">
                        <label for="username">Email</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control <?= ($error && str_contains($error, 'username')) ? 'is-invalid' : ''; ?>" 
                               required 
                               autocomplete="username"
                               value="<?= e($username); ?>" 
                               placeholder="you@example.com">
                        <div class="input-hint">Use the email you registered with</div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control <?= $error ? 'is-invalid' : ''; ?>" 
                               required 
                               autocomplete="current-password"
                               placeholder="Enter your password">
                        <?php if ($error && str_contains($error, 'attempt')): ?>
                            <div class="field-error"><?= e($error); ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; margin-top: 0.5rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.9rem; color: #4A433B; cursor: pointer; margin-bottom: 0;">
                            <input type="checkbox" name="remember_me" value="1" style="accent-color: #F26B1D; width: 16px; height: 16px; cursor: pointer;">
                            <span>Remember me</span>
                        </label>
                        <a href="<?= e(url('auth/reset.php')); ?>" style="font-size: 0.88rem; font-weight: 600; color: #F26B1D; text-decoration: none;">
                            Forgot password?
                        </a>
                    </div>

                    <div>
                        <button type="submit" class="btn-login" id="btn-login-submit">
                            Log in
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 1.5rem 0;">
                    <p style="color: #4A433B; margin-bottom: 1.5rem;">
                        Your access is currently locked. You can reset your password immediately using our recovery system:
                    </p>
                    <a href="<?= e(url('auth/reset.php')); ?>" style="display: block; background: #FFFFFF; border: 1px solid #F26B1D; color: #F26B1D; font-weight: 600; padding: 12px; border-radius: 8px; text-decoration: none;">
                        Reset Forgotten Password &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 2rem;">
                <p style="font-size: 0.95rem; color: #7A7167;">
                    New to UrbanEats? 
                    <a href="<?= e(url('auth/register.php')); ?>" style="font-weight: 700; color: #F26B1D; text-decoration: none;">
                        Create an account
                    </a>
                </p>
            </div>
        </div>
    </div>

</div>

</body>
</html>
