<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Administrator Dashboard
 * 
 * Styled according to the official Figma Design System:
 * - Clean warm light theme
 * - 4 Stat Cards
 * - Security health and audit activity tables
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Route guard: Administrator only
require_role('admin');

$pageTitle = 'Admin Dashboard';

$totalUsers = 0;
$activeUsers = 0;
$deactivatedUsers = 0;
$roleCounts = ['admin' => 0, 'kitchen_staff' => 0, 'customer' => 0];
$logins24h = ['success' => 0, 'failed' => 0];
$recentAudits = [];

try {
    $stmt = $pdo->query("SELECT is_active, COUNT(*) as cnt FROM users GROUP BY is_active");
    while ($row = $stmt->fetch()) {
        if ((int)$row['is_active'] === 1) {
            $activeUsers = (int)$row['cnt'];
        } else {
            $deactivatedUsers = (int)$row['cnt'];
        }
        $totalUsers += (int)$row['cnt'];
    }

    $stmt = $pdo->query(
        "SELECT r.role_name, COUNT(u.id) as cnt 
         FROM roles r 
         LEFT JOIN users u ON r.id = u.role_id 
         GROUP BY r.role_name"
    );
    while ($row = $stmt->fetch()) {
        if (isset($roleCounts[$row['role_name']])) {
            $roleCounts[$row['role_name']] = (int)$row['cnt'];
        }
    }

    $stmt = $pdo->query(
        "SELECT is_successful, COUNT(*) as cnt 
         FROM login_attempts 
         WHERE attempted_at >= (NOW() - INTERVAL 24 HOUR) 
         GROUP BY is_successful"
    );
    while ($row = $stmt->fetch()) {
        if ((int)$row['is_successful'] === 1) {
            $logins24h['success'] = (int)$row['cnt'];
        } else {
            $logins24h['failed'] = (int)$row['cnt'];
        }
    }

    $stmt = $pdo->query(
        "SELECT a.*, u.username, u.full_name, r.role_name
         FROM audit_log a
         LEFT JOIN users u ON a.user_id = u.id
         LEFT JOIN roles r ON u.role_id = r.id
         ORDER BY a.created_at DESC
         LIMIT 6"
    );
    $recentAudits = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[Admin Dashboard Query Error] ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; color: var(--ink-900);">
            Administrator Dashboard
        </h1>
        <p style="color: var(--ink-500); font-size: 1rem;">
            Real-time identity, role access, and security audit metrics.
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="<?= e(url('admin/users.php')); ?>" class="btn btn-primary btn-sm">
            Manage Users &rarr;
        </a>
        <a href="<?= e(url('admin/audit_log.php')); ?>" class="btn btn-secondary btn-sm">
            View Audit Log
        </a>
    </div>
</div>

<!-- 4 Stat Cards in Figma style -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-title">Total registered</span>
        <span class="stat-value"><?= e((string)$totalUsers); ?></span>
        <span class="stat-subtext"><?= e((string)$activeUsers); ?> active accounts</span>
    </div>

    <div class="stat-card">
        <span class="stat-title">Customers</span>
        <span class="stat-value"><?= e((string)$roleCounts['customer']); ?></span>
        <span class="stat-subtext">Verified patrons</span>
    </div>

    <div class="stat-card">
        <span class="stat-title">Kitchen staff</span>
        <span class="stat-value"><?= e((string)$roleCounts['kitchen_staff']); ?></span>
        <span class="stat-subtext">Order fulfillment</span>
    </div>

    <div class="stat-card">
        <span class="stat-title">System administrators</span>
        <span class="stat-value"><?= e((string)$roleCounts['admin']); ?></span>
        <span class="stat-subtext">Full permissions</span>
    </div>
</div>

<!-- Two Column Layout: Recent Audits & Authentication Health -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    
    <!-- Left: Recent Security & Operational Events -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--ink-900);">Recent Security Events</h2>
            <a href="<?= e(url('admin/audit_log.php')); ?>" style="font-size: 0.9rem; font-weight: 600;">Full Audit Log &rarr;</a>
        </div>

        <div class="table-card" style="margin-bottom: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAudits)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--ink-500); padding: 2.5rem;">
                                No recent security events recorded.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentAudits as $log): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.82rem; color: var(--ink-500);">
                                    <?= e(date('M d, H:i', strtotime($log['created_at']))); ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['username'])): ?>
                                        <strong style="color: var(--ink-900);"><?= e($log['username']); ?></strong>
                                    <?php else: ?>
                                        <span style="color: var(--ink-500); font-style: italic;">Guest / System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $act = $log['action'];
                                        $badgeStyle = 'background: var(--ink-100); color: var(--ink-700);';
                                        if (str_contains($act, 'SUCCESS')) $badgeStyle = 'background: var(--success-100); color: var(--success-500);';
                                        elseif (str_contains($act, 'FAILED') || str_contains($act, 'LOCK')) $badgeStyle = 'background: var(--danger-100); color: var(--danger-500);';
                                        elseif (str_contains($act, 'UPDATE') || str_contains($act, 'ROLE')) $badgeStyle = 'background: var(--warning-100); color: var(--warning-500);';
                                    ?>
                                    <span style="display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: var(--radius-full); <?= $badgeStyle; ?>">
                                        <?= e($act); ?>
                                    </span>
                                </td>
                                <td style="font-family: monospace; font-size: 0.8rem; color: var(--ink-500);">
                                    <?= e($log['ip_address']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Authentication Health & Quick Actions -->
    <div>
        <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--ink-900); margin-bottom: 1rem;">
            Authentication Health
        </h2>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-page); border-radius: var(--radius-md); border: 1px solid var(--border-default);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: var(--success-500); font-size: 1.1rem;">●</span>
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--ink-900);">Successful (24h)</span>
                    </div>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--success-500);">
                        <?= e((string)$logins24h['success']); ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-page); border-radius: var(--radius-md); border: 1px solid var(--border-default);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: var(--danger-500); font-size: 1.1rem;">●</span>
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--ink-900);">Failed / Blocked</span>
                    </div>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--danger-500);">
                        <?= e((string)$logins24h['failed']); ?>
                    </span>
                </div>
            </div>

            <div class="callout-warning" style="margin-top: 1.25rem; margin-bottom: 0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span style="font-size: 0.8rem;">Automatic lockout after 5 consecutive failed attempts per IP.</span>
            </div>
        </div>

        <div class="card">
            <h3 style="font-size: 1.05rem; margin-bottom: 0.85rem;">Admin Quick Links</h3>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="<?= e(url('admin/users.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start;">
                    👥 Manage User Accounts &amp; Roles
                </a>
                <a href="<?= e(url('admin/audit_log.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start;">
                    📜 View Full System Audit Trail
                </a>
                <a href="<?= e(url('auth/profile.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start;">
                    ⚙️ My Administrator Profile
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
