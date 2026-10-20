<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Profile & Password Change
 * 
 * Allows users to edit their personal information and securely update their credentials.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Route guard: user must be logged in
require_login();

$userId = (int)$_SESSION['user_id'];

// Fetch latest user details from database
$stmt = $pdo->prepare(
    "SELECT u.id, u.role_id, u.full_name, u.username, u.email, u.phone, u.address, u.password_hash, u.created_at, r.role_name
     FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE u.id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'User account not found.');
    redirect(url('auth/logout.php'));
}

$profileErrors = [];
$passwordErrors = [];
$activeTab = 'profile'; // 'profile' or 'security'

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'update_profile') {
    $activeTab = 'profile';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $profileErrors['csrf'] = 'Invalid security token. Please try again.';
    } else {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $address  = trim((string)($_POST['address'] ?? ''));

        if (empty($fullName)) {
            $profileErrors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($fullName) > 100) {
            $profileErrors['full_name'] = 'Full name cannot exceed 100 characters.';
        }

        if (empty($phone)) {
            $profileErrors['phone'] = 'Contact phone number is required.';
        } elseif (!preg_match('/^[0-9+\s-]{9,15}$/', $phone)) {
            $profileErrors['phone'] = 'Please enter a valid phone number (9-15 digits).';
        }

        if (empty($address)) {
            $profileErrors['address'] = 'Delivery/Contact address is required.';
        }

        if (empty($profileErrors)) {
            try {
                $updateStmt = $pdo->prepare(
                    "UPDATE users 
                     SET full_name = :full_name, phone = :phone, address = :address, updated_at = NOW() 
                     WHERE id = :id"
                );
                $updateStmt->execute([
                    ':full_name' => $fullName,
                    ':phone'     => $phone,
                    ':address'   => $address,
                    ':id'        => $userId,
                ]);

                // Update session
                $_SESSION['full_name'] = $fullName;
                $user['full_name'] = $fullName;
                $user['phone'] = $phone;
                $user['address'] = $address;

                log_action($pdo, $userId, 'PROFILE_UPDATE', 'Updated personal profile details');
                set_flash('success', 'Your profile details have been successfully updated.');
                redirect(url('auth/profile.php'));
            } catch (PDOException $e) {
                error_log('[Profile Update Error] ' . $e->getMessage());
                $profileErrors['general'] = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'change_password') {
    $activeTab = 'security';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $passwordErrors['csrf'] = 'Invalid security token. Please try again.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($currentPassword)) {
            $passwordErrors['current_password'] = 'Please enter your current password.';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $passwordErrors['current_password'] = 'Current password is incorrect.';
        }

        if (empty($newPassword)) {
            $passwordErrors['new_password'] = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $passwordErrors['new_password'] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $passwordErrors['new_password'] = 'Password must include uppercase, lowercase, and numeric characters.';
        }

        if (empty($confirmPassword)) {
            $passwordErrors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors['confirm_password'] = 'New passwords do not match.';
        }

        if (empty($passwordErrors)) {
            try {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $pwdStmt = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
                $pwdStmt->execute([':hash' => $newHash, ':id' => $userId]);

                log_action($pdo, $userId, 'PASSWORD_CHANGE', 'User changed their password via profile page');
                set_flash('success', 'Your password has been changed successfully!');
                redirect(url('auth/profile.php'));
            } catch (PDOException $e) {
                error_log('[Password Change Error] ' . $e->getMessage());
                $passwordErrors['general'] = 'Failed to update password. Please try again.';
            }
        }
    }
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.35rem;">User Account Settings</h1>
            <p style="color: var(--text-muted);">Manage your personal cloud kitchen information and security credentials.</p>
        </div>
        <div>
            <span class="user-badge" style="font-size: 0.95rem; padding: 0.5rem 1.1rem;">
                Role: <strong style="color: var(--primary); text-transform: uppercase;"><?= e(str_replace('_', ' ', $user['role_name'])); ?></strong>
            </span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem;">
        
        <!-- Profile Details Card -->
        <div class="card">
            <div class="card-header">
                <h2>Personal Information</h2>
                <span class="badge badge-info">Details</span>
            </div>

            <?php if (!empty($profileErrors['general'])): ?>
                <div class="alert alert-error">
                    <div><?= e($profileErrors['general']); ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('auth/profile.php')); ?>" method="POST" novalidate id="form-update-profile">
                <?php csrf_field(); ?>
                <input type="hidden" name="action_type" value="update_profile">

                <div class="form-group">
                    <label for="username_ro">Username (Unique)</label>
                    <input type="text" id="username_ro" class="form-control" value="<?= e($user['username']); ?>" readonly style="background: rgba(255,255,255,0.03); cursor: not-allowed; color: var(--text-muted);">
                    <small class="input-hint">Usernames are system-assigned identifiers and cannot be altered.</small>
                </div>

                <div class="form-group">
                    <label for="email_ro">Email Address</label>
                    <input type="email" id="email_ro" class="form-control" value="<?= e($user['email']); ?>" readonly style="background: rgba(255,255,255,0.03); cursor: not-allowed; color: var(--text-muted);">
                    <small class="input-hint">Contact support to update account email addresses.</small>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <input type="text" 
                           id="full_name" 
                           name="full_name" 
                           class="form-control <?= isset($profileErrors['full_name']) ? 'is-invalid' : ''; ?>" 
                           required 
                           value="<?= e($_POST['full_name'] ?? $user['full_name']); ?>">
                    <?php if (isset($profileErrors['full_name'])): ?>
                        <div class="field-error"><?= e($profileErrors['full_name']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="form-control <?= isset($profileErrors['phone']) ? 'is-invalid' : ''; ?>" 
                           required 
                           value="<?= e($_POST['phone'] ?? $user['phone']); ?>">
                    <?php if (isset($profileErrors['phone'])): ?>
                        <div class="field-error"><?= e($profileErrors['phone']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <div class="address-header-row">
                        <label for="address">Delivery / Contact Address <span class="required">*</span></label>
                        <button type="button" 
                                id="btn-detect-location" 
                                class="btn-detect-location" 
                                title="Auto-detect current location">
                            <span class="btn-icon">📍</span>
                            <span class="btn-label">Auto-detect location</span>
                        </button>
                    </div>
                    <textarea id="address" 
                              name="address" 
                              class="form-control <?= isset($profileErrors['address']) ? 'is-invalid' : ''; ?>" 
                              rows="3" 
                              required><?= e($_POST['address'] ?? $user['address']); ?></textarea>
                    <div id="location-status" class="location-status" style="display: none;"></div>
                    <?php if (isset($profileErrors['address'])): ?>
                        <div class="field-error"><?= e($profileErrors['address']); ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" id="btn-save-profile">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Security / Password Card -->
        <div class="card">
            <div class="card-header">
                <h2>Security & Password</h2>
                <span class="badge badge-warning">Credentials</span>
            </div>

            <?php if (!empty($passwordErrors['general'])): ?>
                <div class="alert alert-error">
                    <div><?= e($passwordErrors['general']); ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('auth/profile.php')); ?>" method="POST" novalidate id="form-change-password">
                <?php csrf_field(); ?>
                <input type="hidden" name="action_type" value="change_password">

                <div class="form-group">
                    <label for="current_password">Current Password <span class="required">*</span></label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="form-control <?= isset($passwordErrors['current_password']) ? 'is-invalid' : ''; ?>" 
                           required 
                           placeholder="••••••••">
                    <?php if (isset($passwordErrors['current_password'])): ?>
                        <div class="field-error"><?= e($passwordErrors['current_password']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-control <?= isset($passwordErrors['new_password']) ? 'is-invalid' : ''; ?>" 
                           required 
                           placeholder="••••••••">
                    <?php if (isset($passwordErrors['new_password'])): ?>
                        <div class="field-error"><?= e($passwordErrors['new_password']); ?></div>
                    <?php endif; ?>
                    <small class="input-hint">Minimum 8 characters with uppercase, lowercase, and a number.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control <?= isset($passwordErrors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                           required 
                           placeholder="••••••••">
                    <?php if (isset($passwordErrors['confirm_password'])): ?>
                        <div class="field-error"><?= e($passwordErrors['confirm_password']); ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-secondary" id="btn-change-password">
                        Update Password
                    </button>
                </div>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle);">
                <p style="font-size: 0.85rem; color: var(--text-dim);">
                    Account Registered: <strong><?= e(date('F j, Y', strtotime($user['created_at']))); ?></strong>
                </p>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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

    // Network / IP-based location fallback for laptops and browsers where hardware GPS is unavailable
    async function fetchIpLocation() {
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
