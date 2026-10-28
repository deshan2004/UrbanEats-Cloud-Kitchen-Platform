<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Registration (Split-Screen Hero Layout matching Login)
 * 
 * Implements:
 * - Matching Figma split-screen design language with auth/login.php
 * - Anti-CSRF token verification
 * - Comprehensive server-side validation & uniqueness checks
 * - Password security using password_hash() with BCRYPT
 * - Audit logging via log_action()
 * - Clean semantic UI with accessible error feedback
 * - Interactive Client-side enhancements (Password strength meter, match validator, show/hide toggle, geolocation)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// If user is already logged in, redirect away from registration
if (is_logged_in()) {
    redirect(url('index.php'));
}

$pageTitle = 'Create Account';
$errors = [];
$flash = get_flash();
$formData = [
    'full_name' => '',
    'username'  => '',
    'email'     => '',
    'phone'     => '',
    'address'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $errors['csrf'] = 'Invalid or expired security token. Please refresh and try again.';
    }

    // 2. Sanitize and retrieve input values
    $formData['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $formData['username']  = trim((string)($_POST['username'] ?? ''));
    $formData['email']     = trim((string)($_POST['email'] ?? ''));
    $formData['phone']     = trim((string)($_POST['phone'] ?? ''));
    $formData['address']   = trim((string)($_POST['address'] ?? ''));
    
    $password        = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // 3. Validation: Full Name
    if (empty($formData['full_name'])) {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($formData['full_name']) > 100) {
        $errors['full_name'] = 'Full name cannot exceed 100 characters.';
    }

    // 4. Validation: Username
    // Strict alphanumeric whitelist regex prevents command/path injection and invalid chars
    if (empty($formData['username'])) {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $formData['username'])) {
        $errors['username'] = 'Username must be 3-30 characters long and contain only letters, numbers, and underscores.';
    }

    // 5. Validation: Email Address
    // filter_var() enforces RFC 5322 standard email address format
    if (empty($formData['email'])) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please provide a valid email address.';
    } elseif (mb_strlen($formData['email']) > 150) {
        $errors['email'] = 'Email address cannot exceed 150 characters.';
    }

    // 6. Validation: Contact Phone Number
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Contact phone number is required.';
    } elseif (!preg_match('/^[0-9+\s-]{9,15}$/', $formData['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number (e.g., 0771234567).';
    }

    // 7. Validation: Delivery Address
    if (empty($formData['address'])) {
        $errors['address'] = 'Delivery address is required for cloud kitchen order dispatch.';
    }

    // 8. Validation: Password Complexity (OWASP Password Security Guidelines)
    // Enforces minimum length of 8 characters and character diversity (upper, lower, digit)
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters in length.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    }

    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // 9. Database Uniqueness Checks using Prepared Statements (Prevents account collisions)
    if (empty($errors)) {
        if ($pdo === null) {
            $errors['database'] = 'Database connection is currently unavailable. Please verify your MySQL server.';
        } else {
            try {
                $stmtUser = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
                $stmtUser->execute([':username' => $formData['username']]);
                if ($stmtUser->fetch()) {
                    $errors['username'] = 'This username is already taken. Please choose another.';
                }

                $stmtEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $stmtEmail->execute([':email' => $formData['email']]);
                if ($stmtEmail->fetch()) {
                    $errors['email'] = 'An account with this email address is already registered.';
                }
            } catch (PDOException $e) {
                error_log('[Registration Query Error] ' . $e->getMessage());
                $errors['database'] = 'A database error occurred while verifying details. Please try again.';
            }
        }
    }

    // 10. Process Registration if All Checks Pass
    if (empty($errors)) {
        try {
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'customer' LIMIT 1");
            $roleStmt->execute();
            $roleRow = $roleStmt->fetch();
            $roleId = $roleRow ? (int)$roleRow['id'] : 3;

            // SECURITY: Cryptographic Password Hashing (OWASP A02: Cryptographic Failures)
            // - Algorithm: BCRYPT (adaptive key derivation based on the Blowfish cipher).
            // - Cost Factor: 12 (2^12 = 4,096 hashing iterations).
            // - Salt: Handled automatically by PHP's CSPRNG inside password_hash().
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Parameterized INSERT query completely eliminates SQL Injection vulnerabilities
            $insertSql = 'INSERT INTO users (role_id, full_name, username, email, phone, address, password_hash, is_active, created_at)
                          VALUES (:role_id, :full_name, :username, :email, :phone, :address, :password_hash, 1, NOW())';
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':role_id'       => $roleId,
                ':full_name'     => $formData['full_name'],
                ':username'      => $formData['username'],
                ':email'         => $formData['email'],
                ':phone'         => $formData['phone'],
                ':address'       => $formData['address'],
                ':password_hash' => $passwordHash,
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            // Traceability: Log successful customer self-registration
            log_action(
                $pdo,
                $newUserId,
                'USER_REGISTERED',
                sprintf('New customer account created for username "%s" (%s)', $formData['username'], $formData['email'])
            );

            set_flash('success', 'Registration successful! You can now sign in to your UrbanEats account.');
            redirect(url('auth/login.php'));

        } catch (PDOException $e) {
            error_log('[Registration Insert Error] ' . $e->getMessage());
            $errors['database'] = 'Failed to create account due to a system error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | UrbanEats Cloud Kitchen</title>
    <link rel="icon" type="image/png" href="<?= e(url('public/images/favicon.png')); ?>">
    <!-- Cache buster query param ensures browser never serves stale CSS -->
    <link rel="stylesheet" href="<?= e(url('public/css/style.css')) . '?v=' . time(); ?>">
    <style>
        /* Exact Split-Screen layout styles matching Figma A1 - Login */
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            min-height: 100%;
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
            grid-template-columns: 44% 56%;
            width: 100%;
        }

        .auth-hero-banner {
            background-color: #F26B1D;
            padding: 4rem 3.5rem;
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

        .auth-hero-logo .hero-brand-name {
            font-weight: 800;
            font-size: 1.45rem;
            color: #FFFFFF;
            letter-spacing: -0.02em;
        }

        .auth-hero-content {
            margin: auto 0;
            padding: 1.5rem 0;
        }

        .auth-hero-heading {
            font-size: 2.85rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
            color: #FFFFFF;
            margin-bottom: 1.25rem;
        }

        .auth-hero-desc {
            font-size: 1.08rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.95);
            max-width: 480px;
        }

        .hero-perk-card {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 0.95rem 1.15rem;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            transition: transform 0.2s ease;
        }

        .hero-perk-card:hover {
            transform: translateX(4px);
            background: rgba(255, 255, 255, 0.16);
        }

        .hero-perk-icon {
            background: rgba(255, 255, 255, 0.22);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
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
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(24px) saturate(190%);
            -webkit-backdrop-filter: blur(24px) saturate(190%);
            border: 1px solid rgba(255, 255, 255, 0.95);
            outline: 1px solid rgba(234, 223, 211, 0.5);
            border-radius: 24px;
            padding: 2.75rem 2.5rem;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 16px 36px -8px rgba(31, 27, 22, 0.06), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
        }

        .auth-badge {
            background: #FFF0E6;
            color: #F26B1D;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
            margin-bottom: 0.75rem;
        }

        .auth-card-figma h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #1F1B16;
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }

        .auth-subtext {
            color: #7A7167;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.15rem;
        }

        .form-group {
            margin-bottom: 1.15rem;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 0.88rem;
            color: #1F1B16;
            margin-bottom: 0.45rem;
        }

        label .required {
            color: #D8443A;
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(234, 223, 211, 0.9);
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
            background-color: #FFF7F6;
        }

        .input-hint {
            font-size: 0.8rem;
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
            padding: 0.95rem;
            font-size: 1rem;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 14px rgba(242, 107, 29, 0.25);
        }

        .btn-login:hover {
            background-color: #D9570F;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(242, 107, 29, 0.35);
        }

        .btn-toggle-eye {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #7A7167;
            font-size: 1.1rem;
            padding: 4px;
            line-height: 1;
            transition: opacity 0.15s;
        }

        .btn-toggle-eye:hover {
            opacity: 0.8;
        }

        .address-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.45rem;
        }

        .alert-figma-error {
            background: #FDE7E5;
            border: 1px solid rgba(216,68,58,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
            color: #9A241C;
            font-size: 0.9rem;
        }

        @media (max-width: 960px) {
            .split-auth-container {
                grid-template-columns: 1fr;
            }
            .auth-hero-banner {
                padding: 2.75rem 1.75rem;
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
            .form-grid-2 {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Split Screen Layout matching Figma A1 - Login -->
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
                Join Colombo's Premier Cloud Kitchen.
            </h1>

            <p class="auth-hero-desc">
                Create your patron account in seconds. Order freshly prepped meals, watch kitchen progress in real time, and enjoy swift doorstep delivery.
            </p>

            <!-- Key Culinary Perks Showcase -->
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 2.25rem;">
                <div class="hero-perk-card">
                    <div class="hero-perk-icon">⚡</div>
                    <div>
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: #FFFFFF; margin-bottom: 0.2rem;">15-Min Express Prep</h4>
                        <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.9); line-height: 1.4; margin: 0;">Freshly made to order by certified culinary specialists upon checkout.</p>
                    </div>
                </div>

                <div class="hero-perk-card">
                    <div class="hero-perk-icon">🛡️</div>
                    <div>
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: #FFFFFF; margin-bottom: 0.2rem;">Hygienic Smart Packaging</h4>
                        <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.9); line-height: 1.4; margin: 0;">Tamper-proof thermal insulation keeping meals steaming hot & fresh.</p>
                    </div>
                </div>

                <div class="hero-perk-card">
                    <div class="hero-perk-icon">📍</div>
                    <div>
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: #FFFFFF; margin-bottom: 0.2rem;">Live Kitchen Queue Tracking</h4>
                        <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.9); line-height: 1.4; margin: 0;">Follow your dish from Kitchen Queue & Cooking to Dispatch & Delivery.</p>
                    </div>
                </div>
            </div>
        </div>

        <div style="font-size: 0.85rem; color: rgba(255,255,255,0.85); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 1.5rem; margin-top: 1.5rem;">
            <span>&copy; <?= date('Y'); ?> UrbanEats Platform</span>
            <span>Central Kitchen Colombo 03</span>
        </div>
    </div>

    <!-- Right Form Section -->
    <div class="auth-form-side">
        <div class="auth-card-figma">
            
            <span class="auth-badge">Patron Onboarding</span>
            <h1>Create your Account</h1>
            <p class="auth-subtext">Join UrbanEats Cloud Kitchen to place orders, track kitchen fulfillment, and enjoy fresh meals.</p>

            <!-- Flash notice if redirected from protected page -->
            <?php if ($flash): ?>
                <div style="background: <?= $flash['type'] === 'success' ? '#EBF8F0' : '#FFF3D6'; ?>; border: 1px solid <?= $flash['type'] === 'success' ? '#C2E8D0' : '#F5E5B8'; ?>; border-radius: 10px; padding: 12px 16px; margin-bottom: 1.25rem; color: <?= $flash['type'] === 'success' ? '#146E3A' : '#925C02'; ?>; font-size: 0.88rem; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <span><?= $flash['type'] === 'success' ? '✓' : 'ℹ️'; ?></span>
                    <div><?= e($flash['message']); ?></div>
                </div>
            <?php endif; ?>

            <!-- Global Error / Validation Summary Alert -->
            <?php if (!empty($errors['csrf']) || !empty($errors['database'])): ?>
                <div class="alert-figma-error">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span>⚠️</span>
                        <div><?= e($errors['csrf'] ?? $errors['database']); ?></div>
                    </div>
                </div>
            <?php elseif (!empty($errors)): ?>
                <div class="alert-figma-error">
                    <div style="font-weight: 700; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 6px;">
                        <span>⚠️</span>
                        <span>Please review the highlighted fields:</span>
                    </div>
                    <ul style="list-style-position: inside; font-size: 0.85rem; line-height: 1.5; margin: 0; padding-left: 0.5rem;">
                        <?php foreach ($errors as $fieldKey => $errMsg): ?>
                            <li><?= e($errMsg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('auth/register.php')); ?>" method="POST" novalidate id="register-form">
                <?php csrf_field(); ?>

                <!-- Row 1: Full Name & Username -->
                <div class="form-grid-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                               value="<?= e($formData['full_name']); ?>" 
                               required 
                               maxlength="100" 
                               placeholder="e.g. Kasun Kalhara"
                               autocomplete="name">
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="field-error"><?= e($errors['full_name']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="username">Username <span class="required">*</span></label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control <?= isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                               value="<?= e($formData['username']); ?>" 
                               required 
                               maxlength="30" 
                               placeholder="e.g. kasun_k"
                               autocomplete="username">
                        <div class="input-hint" id="username-hint">3–30 characters (letters, numbers, _)</div>
                        <?php if (isset($errors['username'])): ?>
                            <div class="field-error"><?= e($errors['username']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 2: Email & Phone -->
                <div class="form-grid-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                               value="<?= e($formData['email']); ?>" 
                               required 
                               maxlength="150" 
                               placeholder="name@example.com"
                               autocomplete="email">
                        <div class="input-hint">Order confirmations sent here</div>
                        <?php if (isset($errors['email'])): ?>
                            <div class="field-error"><?= e($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="phone">Contact Phone <span class="required">*</span></label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               class="form-control <?= isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                               value="<?= e($formData['phone']); ?>" 
                               required 
                               placeholder="0771234567"
                               autocomplete="tel">
                        <div class="input-hint">For courier delivery dispatch</div>
                        <?php if (isset($errors['phone'])): ?>
                            <div class="field-error"><?= e($errors['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 3: Delivery Address (Manual Typing + GPS Auto-Detection) -->
                <div class="form-group">
                    <div class="address-header-row">
                        <label for="address" style="margin-bottom: 0;">Delivery Address <span class="required">*</span></label>
                        <button type="button" 
                                id="btn-detect-location" 
                                class="btn-detect-location" 
                                title="Auto-detect current delivery location">
                            <span class="btn-icon">📍</span>
                            <span class="btn-label">Auto-detect location</span>
                        </button>
                    </div>
                    <textarea id="address" 
                              name="address" 
                              class="form-control <?= isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                              rows="2" 
                              required 
                              placeholder="Street number, building, city (e.g. 24 Havelock Road, Colombo 05)"><?= e($formData['address']); ?></textarea>
                    <div id="location-status" class="location-status" style="display: none;"></div>
                    <?php if (isset($errors['address'])): ?>
                        <div class="field-error"><?= e($errors['address']); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Row 4: Password & Confirm Password (with interactive show/hide) -->
                <div class="form-grid-2" style="margin-bottom: 0.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="password">Password <span class="required">*</span></label>
                        <div style="position: relative;">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control <?= isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                   required 
                                   autocomplete="new-password"
                                   placeholder="••••••••"
                                   style="padding-right: 2.75rem;">
                            <button type="button" 
                                    id="btn-toggle-password" 
                                    class="btn-toggle-eye"
                                    aria-label="Toggle password visibility">
                                👁️
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="field-error"><?= e($errors['password']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <div style="position: relative;">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                   required 
                                   autocomplete="new-password"
                                   placeholder="••••••••"
                                   style="padding-right: 2.75rem;">
                            <button type="button" 
                                    id="btn-toggle-confirm" 
                                    class="btn-toggle-eye"
                                    aria-label="Toggle confirm password visibility">
                                👁️
                            </button>
                        </div>
                        <div id="match-indicator" style="font-size: 0.82rem; font-weight: 600; margin-top: 0.35rem; display: none;"></div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="field-error"><?= e($errors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Real-time Password Strength Meter -->
                <div style="margin-top: 0.75rem; margin-bottom: 1.15rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.8rem; font-weight: 600; color: #7A7167;">Security Strength:</span>
                        <span id="strength-label" style="font-size: 0.8rem; font-weight: 700; color: #7A7167;">Enter password</span>
                    </div>
                    <div style="height: 6px; background: #EDE8E1; border-radius: 9999px; overflow: hidden;">
                        <div id="strength-bar" style="height: 100%; width: 0%; background: #D8443A; transition: all 0.3s ease;"></div>
                    </div>
                </div>

                <!-- Interactive Password Requirements Checklist (Frosted Glass) -->
                <div style="background: rgba(255, 249, 243, 0.75); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(234, 223, 211, 0.8); border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 1.5rem;">
                    <p style="font-size: 0.8rem; font-weight: 700; color: #4A433B; margin-bottom: 0.4rem;">
                        Password Complexity Requirements:
                    </p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.35rem; font-size: 0.78rem;">
                        <div id="req-length" style="color: #7A7167; display: flex; align-items: center; gap: 0.35rem;">
                            <span class="icon">○</span> At least 8 characters
                        </div>
                        <div id="req-upper" style="color: #7A7167; display: flex; align-items: center; gap: 0.35rem;">
                            <span class="icon">○</span> One uppercase letter (A-Z)
                        </div>
                        <div id="req-lower" style="color: #7A7167; display: flex; align-items: center; gap: 0.35rem;">
                            <span class="icon">○</span> One lowercase letter (a-z)
                        </div>
                        <div id="req-number" style="color: #7A7167; display: flex; align-items: center; gap: 0.35rem;">
                            <span class="icon">○</span> One numerical digit (0-9)
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" class="btn-login" id="btn-register-submit">
                        <span>Create Account</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </form>

            <div style="text-align: center; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid rgba(234, 223, 211, 0.8);">
                <p style="font-size: 0.95rem; color: #7A7167;">
                    Already have an account? 
                    <a href="<?= e(url('auth/login.php')); ?>" style="font-weight: 700; color: #F26B1D; text-decoration: none;">
                        Log in here &rarr;
                    </a>
                </p>
            </div>

        </div>
    </div>

</div>

<!-- Interactive Client-side Enhancement Script (Strict Native Vanilla JS) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pwdInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const togglePwd = document.getElementById('btn-toggle-password');
    const toggleConfirm = document.getElementById('btn-toggle-confirm');

    const strengthBar = document.getElementById('strength-bar');
    const strengthLabel = document.getElementById('strength-label');

    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');
    const matchIndicator = document.getElementById('match-indicator');

    // 1. Password Visibility Toggles
    function setupToggle(button, input) {
        if (!button || !input) return;
        button.addEventListener('click', function () {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? '🙈' : '👁️';
        });
    }
    setupToggle(togglePwd, pwdInput);
    setupToggle(toggleConfirm, confirmInput);

    // 2. Real-time Password Strength & Rule Checklist
    function updateRule(elem, isValid) {
        if (!elem) return;
        const icon = elem.querySelector('.icon');
        if (isValid) {
            elem.style.color = '#1F9D55';
            elem.style.fontWeight = '600';
            if (icon) icon.textContent = '✓';
        } else {
            elem.style.color = '#7A7167';
            elem.style.fontWeight = '400';
            if (icon) icon.textContent = '○';
        }
    }

    if (pwdInput) {
        pwdInput.addEventListener('input', function () {
            const val = pwdInput.value;
            
            const hasLength = val.length >= 8;
            const hasUpper = /[A-Z]/.test(val);
            const hasLower = /[a-z]/.test(val);
            const hasNumber = /[0-9]/.test(val);

            updateRule(reqLength, hasLength);
            updateRule(reqUpper, hasUpper);
            updateRule(reqLower, hasLower);
            updateRule(reqNumber, hasNumber);

            let score = 0;
            if (hasLength) score += 25;
            if (hasUpper) score += 25;
            if (hasLower) score += 25;
            if (hasNumber) score += 25;

            strengthBar.style.width = score + '%';

            if (score === 0) {
                strengthLabel.textContent = 'Enter password';
                strengthLabel.style.color = '#7A7167';
                strengthBar.style.background = '#EDE8E1';
            } else if (score <= 50) {
                strengthLabel.textContent = 'Weak';
                strengthLabel.style.color = '#D8443A';
                strengthBar.style.background = '#D8443A';
            } else if (score === 75) {
                strengthLabel.textContent = 'Good';
                strengthLabel.style.color = '#C98206';
                strengthBar.style.background = '#C98206';
            } else {
                strengthLabel.textContent = 'Strong ✓';
                strengthLabel.style.color = '#1F9D55';
                strengthBar.style.background = '#1F9D55';
            }

            checkMatch();
        });
    }

    // 3. Password Match Indicator
    function checkMatch() {
        if (!confirmInput || !pwdInput || !matchIndicator) return;
        const p1 = pwdInput.value;
        const p2 = confirmInput.value;

        if (p2.length === 0) {
            matchIndicator.style.display = 'none';
            return;
        }

        matchIndicator.style.display = 'block';
        if (p1 === p2) {
            matchIndicator.textContent = '✓ Passwords match';
            matchIndicator.style.color = '#1F9D55';
            confirmInput.classList.remove('is-invalid');
        } else {
            matchIndicator.textContent = '✕ Passwords do not match';
            matchIndicator.style.color = '#D8443A';
        }
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', checkMatch);
    }

    // 4. Geolocation Auto-Detection for Delivery Address (GPS + Network IP Fallback)
    const btnDetectLocation = document.getElementById('btn-detect-location');
    const addressInput = document.getElementById('address');
    const locationStatus = document.getElementById('location-status');

    function setLocationStatus(msg, type) {
        if (!locationStatus) return;
        if (!msg) {
            locationStatus.style.display = 'none';
            locationStatus.className = 'location-status';
            locationStatus.innerHTML = '';
            return;
        }
        locationStatus.style.display = 'flex';
        locationStatus.className = 'location-status ' + type;
        locationStatus.innerHTML = msg;
    }

    async function fetchIpLocation() {
        // Strategy 1: BigDataCloud client reverse geocoding
        try {
            const resp = await fetch('https://api.bigdatacloud.net/data/reverse-geocode-client');
            if (resp.ok) {
                const bdc = await resp.json();
                const parts = [];
                if (bdc.locality && bdc.locality !== bdc.city) parts.push(bdc.locality);
                if (bdc.city) parts.push(bdc.city);
                if (bdc.principalSubdivision) parts.push(bdc.principalSubdivision);
                if (bdc.countryName) parts.push(bdc.countryName);
                if (parts.length > 0) {
                    return parts.join(', ');
                }
            }
        } catch (e1) {
            console.warn('BigDataCloud IP fetch:', e1);
        }

        // Strategy 2: ipwho.is client IP lookup
        try {
            const resp2 = await fetch('https://ipwho.is/');
            if (resp2.ok) {
                const data2 = await resp2.json();
                if (data2 && data2.success) {
                    const parts2 = [data2.city, data2.region, data2.postal, data2.country].filter(Boolean);
                    if (parts2.length > 0) {
                        return parts2.join(', ');
                    }
                }
            }
        } catch (e2) {
            console.warn('ipwho.is fetch:', e2);
        }

        return null;
    }

    if (btnDetectLocation && addressInput) {
        btnDetectLocation.addEventListener('click', function () {
            const iconSpan = btnDetectLocation.querySelector('.btn-icon');
            const labelSpan = btnDetectLocation.querySelector('.btn-label');
            const originalIcon = iconSpan ? iconSpan.innerHTML : '📍';
            const originalText = labelSpan ? labelSpan.textContent : 'Auto-detect location';

            btnDetectLocation.disabled = true;
            if (iconSpan) iconSpan.innerHTML = '<span class="location-spinner"></span>';
            if (labelSpan) labelSpan.textContent = 'Detecting location...';
            setLocationStatus('📡 Acquiring location coordinates...', 'info');

            async function handleGpsFailure() {
                if (labelSpan) labelSpan.textContent = 'Detecting via network...';
                setLocationStatus('📡 GPS unavailable, detecting via network location...', 'info');

                const ipAddress = await fetchIpLocation();
                if (ipAddress) {
                    addressInput.value = ipAddress;
                    addressInput.classList.remove('is-invalid');
                    addressInput.focus();
                    setLocationStatus('✓ Location detected via network! Please add your street & building details.', 'success');
                    btnDetectLocation.disabled = false;
                    if (iconSpan) iconSpan.innerHTML = '📍';
                    if (labelSpan) labelSpan.textContent = 'Re-detect';
                } else {
                    setLocationStatus('⚠️ Location could not be detected. Please type your delivery address manually.', 'error');
                    btnDetectLocation.disabled = false;
                    if (iconSpan) iconSpan.innerHTML = originalIcon;
                    if (labelSpan) labelSpan.textContent = originalText;
                }
            }

            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    async function (position) {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;

                        if (labelSpan) labelSpan.textContent = 'Resolving address...';
                        setLocationStatus('🔍 Finding street address details...', 'info');

                        let resolvedAddress = '';

                        try {
                            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`, {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (response.ok) {
                                const data = await response.json();
                                if (data && data.address) {
                                    const a = data.address;
                                    const street = [a.house_number, a.building, a.road].filter(Boolean).join(' ');
                                    const area = a.suburb || a.neighbourhood || a.village || a.residential || '';
                                    const city = a.city || a.town || a.municipality || a.county || '';
                                    const postal = a.postcode || '';
                                    const country = a.country || '';

                                    const parts = [street, area, city, postal, country].filter(Boolean);
                                    resolvedAddress = parts.length > 0 ? parts.join(', ') : (data.display_name || '');
                                } else if (data && data.display_name) {
                                    resolvedAddress = data.display_name;
                                }
                            }
                        } catch (err) {
                            console.warn('Nominatim reverse geocode error:', err);
                        }

                        if (!resolvedAddress) {
                            try {
                                const bdcResp = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`);
                                if (bdcResp.ok) {
                                    const bdcData = await bdcResp.json();
                                    const parts = [];
                                    if (bdcData.locality) parts.push(bdcData.locality);
                                    if (bdcData.city && bdcData.city !== bdcData.locality) parts.push(bdcData.city);
                                    if (bdcData.principalSubdivision) parts.push(bdcData.principalSubdivision);
                                    if (bdcData.countryName) parts.push(bdcData.countryName);
                                    if (parts.length > 0) {
                                        resolvedAddress = parts.join(', ');
                                    }
                                }
                            } catch (err2) {
                                console.warn('BigDataCloud geocode fallback error:', err2);
                            }
                        }

                        if (!resolvedAddress) {
                            resolvedAddress = `Lat: ${lat.toFixed(5)}, Long: ${lon.toFixed(5)}`;
                        }

                        addressInput.value = resolvedAddress;
                        addressInput.classList.remove('is-invalid');
                        addressInput.focus();

                        setLocationStatus('✓ Location detected! You can edit or add apartment/house numbers manually.', 'success');

                        btnDetectLocation.disabled = false;
                        if (iconSpan) iconSpan.innerHTML = '📍';
                        if (labelSpan) labelSpan.textContent = 'Re-detect';
                    },
                    function (error) {
                        console.warn('Browser GPS error code:', error.code, error.message);
                        handleGpsFailure();
                    },
                    {
                        enableHighAccuracy: false,
                        timeout: 5000,
                        maximumAge: 300000
                    }
                );
            } else {
                handleGpsFailure();
            }
        });
    }
});
</script>

</body>
</html>
