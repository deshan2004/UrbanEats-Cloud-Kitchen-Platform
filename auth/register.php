<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Registration
 * 
 * Implements:
 * - Anti-CSRF token verification
 * - Comprehensive server-side validation & uniqueness checks
 * - Password security using password_hash() with BCRYPT
 * - Audit logging via log_action()
 * - Clean semantic UI with accessible error feedback
 * - Interactive Client-side enhancements (Password strength meter, match validator, show/hide toggle)
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
    if (empty($formData['username'])) {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $formData['username'])) {
        $errors['username'] = 'Username must be 3-30 characters long and contain only letters, numbers, and underscores.';
    }

    // 5. Validation: Email Address
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

    // 8. Validation: Password & Confirmation
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

    // 9. Database Uniqueness Checks
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

            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

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

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 680px; margin: 0 auto; padding: 1rem 0 3rem;">
    
    <!-- Elevated Glassmorphism Register Card -->
    <div class="card" style="padding: 2.75rem 2.5rem; border-radius: 24px; box-shadow: var(--shadow-glass); background: rgba(255, 255, 255, 0.72); backdrop-filter: blur(24px) saturate(190%); -webkit-backdrop-filter: blur(24px) saturate(190%); border: 1px solid rgba(255, 255, 255, 0.9);">
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <a href="<?= e(url('index.php')); ?>" style="display: inline-block; text-decoration: none; margin-bottom: 0.85rem;">
                <img src="<?= e(url('public/images/logo.png')); ?>" alt="UrbanEats Logo" style="width: 52px; height: 52px; border-radius: 12px; object-fit: cover; box-shadow: 0 4px 14px rgba(242,107,29,0.25);">
            </a>
            <div>
                <span style="background: var(--primary-50); color: var(--primary-600); font-size: 0.78rem; font-weight: 700; padding: 0.35rem 0.85rem; border-radius: var(--radius-full); text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 0.75rem;">
                    Patron Onboarding
                </span>
            </div>
            <h1 style="font-size: 2.15rem; font-weight: 800; color: var(--ink-900); margin-bottom: 0.4rem; letter-spacing: -0.02em;">
                Create your Account
            </h1>
            <p style="color: var(--ink-500); font-size: 0.98rem;">
                Join UrbanEats Cloud Kitchen to place orders, track kitchen fulfillment, and enjoy fresh meals.
            </p>
        </div>

        <?php if (!empty($errors['csrf']) || !empty($errors['database'])): ?>
            <div class="alert alert-error" role="alert">
                <span>⚠️</span>
                <div><?= e($errors['csrf'] ?? $errors['database']); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors) && empty($errors['csrf']) && empty($errors['database'])): ?>
            <div style="background: var(--danger-100); border: 1px solid rgba(216,68,58,0.3); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                <h4 style="color: var(--danger-500); font-size: 0.92rem; font-weight: 700; margin-bottom: 0.4rem;">Please review the highlighted fields:</h4>
                <ul style="list-style-position: inside; color: #9A241C; font-size: 0.85rem; line-height: 1.5;">
                    <?php foreach ($errors as $fieldKey => $errMsg): ?>
                        <li><?= e($errMsg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= e(url('auth/register.php')); ?>" method="POST" novalidate id="register-form">
            <?php csrf_field(); ?>

            <!-- Row 1: Full Name & Username -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
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
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
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
                    <div class="input-hint">Your order confirmations will be sent here</div>
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

            <!-- Row 3: Delivery Address -->
            <div class="form-group">
                <label for="address">Delivery Address <span class="required">*</span></label>
                <textarea id="address" 
                          name="address" 
                          class="form-control <?= isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                          rows="2" 
                          required 
                          placeholder="Street number, street name, city (e.g. 24 Havelock Road, Colombo 05)"><?= e($formData['address']); ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <div class="field-error"><?= e($errors['address']); ?></div>
                <?php endif; ?>
            </div>

            <!-- Row 4: Password & Confirm Password (with interactive show/hide) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 0.5rem;">
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
                                aria-label="Toggle password visibility"
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--ink-500); font-size: 1.1rem; padding: 4px;">
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
                                aria-label="Toggle confirm password visibility"
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--ink-500); font-size: 1.1rem; padding: 4px;">
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
            <div style="margin-top: 0.75rem; margin-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--ink-500);">Security Strength:</span>
                    <span id="strength-label" style="font-size: 0.8rem; font-weight: 700; color: var(--ink-500);">Enter password</span>
                </div>
                <div style="height: 6px; background: #EDE8E1; border-radius: var(--radius-full); overflow: hidden;">
                    <div id="strength-bar" style="height: 100%; width: 0%; background: var(--danger-500); transition: all 0.3s ease;"></div>
                </div>
            </div>

            <!-- Interactive Password Requirements Checklist (Frosted Glass) -->
            <div style="background: rgba(255, 249, 243, 0.75); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(234, 223, 211, 0.8); border-radius: var(--radius-md); padding: 0.95rem 1.15rem; margin-bottom: 1.75rem;">
                <p style="font-size: 0.8rem; font-weight: 700; color: var(--ink-700); margin-bottom: 0.4rem;">
                    Password Complexity Requirements:
                </p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.35rem; font-size: 0.8rem;">
                    <div id="req-length" style="color: var(--ink-500); display: flex; align-items: center; gap: 0.35rem;">
                        <span class="icon">○</span> At least 8 characters
                    </div>
                    <div id="req-upper" style="color: var(--ink-500); display: flex; align-items: center; gap: 0.35rem;">
                        <span class="icon">○</span> One uppercase letter (A-Z)
                    </div>
                    <div id="req-lower" style="color: var(--ink-500); display: flex; align-items: center; gap: 0.35rem;">
                        <span class="icon">○</span> One lowercase letter (a-z)
                    </div>
                    <div id="req-number" style="color: var(--ink-500); display: flex; align-items: center; gap: 0.35rem;">
                        <span class="icon">○</span> One numerical digit (0-9)
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div>
                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.95rem; font-size: 1.05rem;" id="btn-register-submit">
                    Create Account &rarr;
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-default);">
            <p style="font-size: 0.95rem; color: var(--ink-700);">
                Already have an account? 
                <a href="<?= e(url('auth/login.php')); ?>" style="font-weight: 700; color: var(--primary-500);">
                    Sign in here &rarr;
                </a>
            </p>
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
        if (!confirmInput || !pwdInput) return;
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
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
