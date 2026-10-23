<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Executive Administrator Command Center & Operations Dashboard
 * 
 * Features:
 * - Real-time culinary operations and kitchen ticket status
 * - Live commercial revenue & inventory threshold alerts
 * - Identity governance & role allocation metrics
 * - Security health audit trail with interactive test triggers
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Route guard: Administrator only
require_role('admin');

$pageTitle = 'Administrator Command Center';

$totalUsers = 0;
$activeUsers = 0;
$deactivatedUsers = 0;
$roleCounts = ['admin' => 0, 'kitchen_staff' => 0, 'customer' => 0];
$logins24h = ['success' => 0, 'failed' => 0];
$recentAudits = [];

$kitchenMetrics = [
    'active_orders'   => 0,
    'placed_orders'   => 0,
    'revenue_today'   => 0.00,
    'completed_today' => 0,
    'low_stock_items' => 0,
];
$liveIncomingOrders = [];

try {
    // 1. Identity & Role metrics
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

    // 2. Authentication security metrics
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

    // 3. Security Audit Log
    $stmt = $pdo->query(
        "SELECT a.*, u.username, u.full_name, r.role_name
         FROM audit_log a
         LEFT JOIN users u ON a.user_id = u.id
         LEFT JOIN roles r ON u.role_id = r.id
         ORDER BY a.created_at DESC
         LIMIT 6"
    );
    $recentAudits = $stmt->fetchAll();

    // 4. Culinary Operations & Kitchen Queue Metrics
    $kmStmt = $pdo->query(
        "SELECT 
            COUNT(CASE WHEN status NOT IN ('Completed', 'Cancelled') THEN 1 END) AS active_orders,
            COUNT(CASE WHEN status = 'Placed' THEN 1 END) AS placed_orders,
            COUNT(CASE WHEN status = 'Completed' AND DATE(created_at) = CURDATE() THEN 1 END) AS completed_today,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status != 'Cancelled' THEN total_amount ELSE 0 END), 0) AS revenue_today
         FROM orders"
    );
    if ($km = $kmStmt->fetch()) {
        $kitchenMetrics['active_orders']   = (int)$km['active_orders'];
        $kitchenMetrics['placed_orders']   = (int)$km['placed_orders'];
        $kitchenMetrics['completed_today'] = (int)$km['completed_today'];
        $kitchenMetrics['revenue_today']   = (float)$km['revenue_today'];
    }

    // 5. Live inventory low stock alert count
    $lsStmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level");
    $kitchenMetrics['low_stock_items'] = (int)$lsStmt->fetchColumn();

    // 6. Recent 5 orders for real-time monitoring
    $loStmt = $pdo->query(
        "SELECT o.*, u.full_name AS customer_name,
                GROUP_CONCAT(CONCAT(oi.quantity, 'x ', d.name) SEPARATOR ', ') AS items_summary
         FROM orders o
         JOIN users u ON o.user_id = u.id
         LEFT JOIN order_items oi ON o.order_id = oi.order_id
         LEFT JOIN dishes d ON oi.dish_id = d.dish_id
         WHERE o.status != 'Cancelled'
         GROUP BY o.order_id
         ORDER BY o.order_id DESC
         LIMIT 5"
    );
    $liveIncomingOrders = $loStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[Admin Dashboard Query Error] ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header & Quick Action Hub -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
            <span style="display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.78rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid rgba(16, 185, 129, 0.25);">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981; animation: ue-pulse-dot 1.5s infinite;"></span>
                Live Kitchen Sync Active
            </span>
            <span style="font-size: 0.8rem; color: var(--ink-400);">• Automatic real-time polling</span>
        </div>
        <h1 style="font-size: 2.15rem; font-weight: 800; margin: 0 0 0.35rem; color: var(--ink-900); letter-spacing: -0.02em;">
            Executive Command Center
        </h1>
        <p style="color: var(--ink-500); font-size: 0.98rem; margin: 0;">
            Real-time monitoring of live kitchen tickets, commercial revenues, and security governance.
        </p>
    </div>

    <!-- Interactive Quick Actions & Audio Test -->
    <div style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
        <button type="button" onclick="testAlertChime()" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.4rem; background: #FFFFFF; border: 1px solid var(--border-default); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <span>🔔</span>
            <span>Test Order Chime</span>
        </button>
        <a href="<?= e(url('orders/kitchen_queue.php')); ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700;">
            <span>🍳</span>
            <span>Live Kitchen Queue</span>
            <?php if ($kitchenMetrics['placed_orders'] > 0): ?>
                <span class="badge" style="background: #FFFFFF; color: var(--primary-600); font-size: 0.75rem; padding: 0.15rem 0.45rem; border-radius: 9999px; margin-left: 2px;">
                    <?= $kitchenMetrics['placed_orders']; ?> New
                </span>
            <?php endif; ?>
        </a>
        <a href="<?= e(url('admin/reports.php')); ?>" class="btn btn-secondary btn-sm">
            📊 Reports
        </a>
        <a href="<?= e(url('admin/users.php')); ?>" class="btn btn-secondary btn-sm">
            👥 Users
        </a>
    </div>
</div>

<!-- Section 1: Live Culinary Operations KPIs -->
<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span>🍳</span> Culinary &amp; Sales Pulse (Today)
        </h2>
        <a href="<?= e(url('orders/kitchen_queue.php')); ?>" style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-decoration: none;">
            Open Interactive Console &rarr;
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card" style="border-left: 4px solid var(--primary-500); background: rgba(255, 255, 255, 0.85);">
            <span class="stat-title">Today's Gross Sales</span>
            <span class="stat-value" style="color: var(--primary-600);">
                LKR <?= number_format($kitchenMetrics['revenue_today'], 2); ?>
            </span>
            <span class="stat-subtext"><?= $kitchenMetrics['completed_today']; ?> orders completed today</span>
        </div>

        <div class="stat-card" style="border-left: 4px solid var(--info-500); background: rgba(255, 255, 255, 0.85);">
            <span class="stat-title">Active Kitchen Tickets</span>
            <span class="stat-value" style="color: var(--info-600);">
                <?= $kitchenMetrics['active_orders']; ?>
            </span>
            <span class="stat-subtext">Across Placed, Cooking &amp; Ready</span>
        </div>

        <div class="stat-card" style="border-left: 4px solid <?= ($kitchenMetrics['placed_orders'] > 0) ? '#D97706' : 'var(--border-default)'; ?>; background: rgba(255, 255, 255, 0.85);">
            <span class="stat-title">Incoming (Unaccepted)</span>
            <span class="stat-value" style="color: <?= ($kitchenMetrics['placed_orders'] > 0) ? '#D97706' : 'var(--ink-700)'; ?>;">
                <?= $kitchenMetrics['placed_orders']; ?>
            </span>
            <span class="stat-subtext"><?= ($kitchenMetrics['placed_orders'] > 0) ? '⚡ Action required on stove' : 'All tickets underway'; ?></span>
        </div>

        <div class="stat-card" style="border-left: 4px solid <?= ($kitchenMetrics['low_stock_items'] > 0) ? 'var(--danger-500)' : 'var(--success-500)'; ?>; background: rgba(255, 255, 255, 0.85);">
            <span class="stat-title">Low Stock Alerts</span>
            <span class="stat-value" style="color: <?= ($kitchenMetrics['low_stock_items'] > 0) ? 'var(--danger-500)' : 'var(--success-500)'; ?>;">
                <?= $kitchenMetrics['low_stock_items']; ?>
            </span>
            <span class="stat-subtext">
                <a href="<?= e(url('menu/stock.php')); ?>" style="color: var(--primary-600); text-decoration: none; font-weight: 600;">
                    <?= ($kitchenMetrics['low_stock_items'] > 0) ? 'Review live inventory &rarr;' : 'Inventory healthy'; ?>
                </a>
            </span>
        </div>
    </div>
</div>

<!-- Section 2: Identity Governance KPIs -->
<div style="margin-bottom: 2.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span>👥</span> Identity Governance &amp; Access Roles
        </h2>
        <a href="<?= e(url('admin/users.php')); ?>" style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-decoration: none;">
            Manage Accounts &rarr;
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-title">Total Accounts</span>
            <span class="stat-value"><?= e((string)$totalUsers); ?></span>
            <span class="stat-subtext"><?= e((string)$activeUsers); ?> active &bull; <?= e((string)$deactivatedUsers); ?> deactivated</span>
        </div>

        <div class="stat-card">
            <span class="stat-title">Customer Patrons</span>
            <span class="stat-value"><?= e((string)$roleCounts['customer']); ?></span>
            <span class="stat-subtext">Verified ordering clients</span>
        </div>

        <div class="stat-card">
            <span class="stat-title">Kitchen Brigade</span>
            <span class="stat-value"><?= e((string)$roleCounts['kitchen_staff']); ?></span>
            <span class="stat-subtext">Chefs &amp; kitchen coordinators</span>
        </div>

        <div class="stat-card">
            <span class="stat-title">System Administrators</span>
            <span class="stat-value"><?= e((string)$roleCounts['admin']); ?></span>
            <span class="stat-subtext">Security &amp; governance access</span>
        </div>
    </div>
</div>

<!-- Two Column Layout: Real-time Order Feed & Security Audits -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.75rem; margin-bottom: 2rem;">
    
    <!-- Left: Live Culinary Order Feed & Recent Security Events -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- Live Incoming Orders Monitor -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                    <span>⚡</span> Live Orders Activity
                </h3>
                <a href="<?= e(url('orders/kitchen_queue.php')); ?>" style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-decoration: none;">
                    Full Kitchen Console &rarr;
                </a>
            </div>

            <div class="table-card" style="margin-bottom: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order Code</th>
                            <th>Customer &amp; Dishes</th>
                            <th>Fulfillment</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($liveIncomingOrders)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--ink-500); padding: 2.5rem;">
                                    No live orders currently in processing.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($liveIncomingOrders as $o): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('orders/confirmation.php?code=' . urlencode($o['order_code']))); ?>" style="font-family: 'Courier New', Courier, monospace; font-weight: 700; color: var(--primary-600); text-decoration: none;">
                                            #<?= e($o['order_code']); ?>
                                        </a>
                                        <div style="font-size: 0.75rem; color: var(--ink-400); margin-top: 0.15rem;">
                                            <?= date('M d, H:i', strtotime($o['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--ink-900); display: block; font-size: 0.9rem;"><?= e($o['customer_name']); ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--ink-500); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= e($o['items_summary'] ?: 'No items listed'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.8rem; font-weight: 600; color: var(--ink-700);">
                                            <?= ($o['order_type'] === 'delivery') ? '🛵 Delivery' : '🏪 Pickup'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--ink-900); font-size: 0.92rem;">
                                            LKR <?= number_format((float)$o['total_amount'], 2); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php
                                        $s = $o['status'];
                                        $sColor = '#1D4ED8';
                                        $sBg = '#EFF6FF';
                                        if ($s === 'Preparing') { $sColor = '#B45309'; $sBg = '#FEF3C7'; }
                                        elseif ($s === 'Ready') { $sColor = '#047857'; $sBg = '#ECFDF5'; }
                                        elseif ($s === 'Completed') { $sColor = '#15803D'; $sBg = '#EBFBF0'; }
                                        ?>
                                        <span style="display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full); color: <?= $sColor; ?>; background: <?= $sBg; ?>;">
                                            <?= e($s); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($s === 'Placed'): ?>
                                            <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>" style="display: inline-block; margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="transition">
                                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                                <input type="hidden" name="new_status" value="Preparing">
                                                <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #10B981, #059669); color: #FFFFFF !important; font-weight: 700; padding: 0.35rem 0.7rem; border-radius: 6px; border: none; font-size: 0.76rem; box-shadow: 0 2px 6px rgba(16,185,129,0.3); cursor: pointer;">
                                                    ✅ Approve
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="<?= e(url('orders/kitchen_queue.php')); ?>" class="btn btn-sm btn-secondary" style="font-size: 0.76rem; padding: 0.35rem 0.65rem; border-radius: 6px;">
                                                Manage &rarr;
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Security & Operational Events -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                    <span>🛡️</span> Security &amp; Compliance Trail
                </h3>
                <a href="<?= e(url('admin/audit_log.php')); ?>" style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-decoration: none;">
                    Full Audit Log &rarr;
                </a>
            </div>

            <div class="table-card" style="margin-bottom: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Security Event</th>
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
                                    <td style="white-space: nowrap; font-size: 0.8rem; color: var(--ink-500);">
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

    </div>

    <!-- Right: Authentication Health & Quick Launch Navigation -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Authentication Health Card -->
        <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.4rem;">
                <span>🔐</span> Auth Sentinel (24h)
            </h3>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-page); border-radius: var(--radius-md); border: 1px solid var(--border-default);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: var(--success-500); font-size: 1.1rem;">●</span>
                        <span style="font-size: 0.88rem; font-weight: 600; color: var(--ink-900);">Successful Logins</span>
                    </div>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--success-500);">
                        <?= e((string)$logins24h['success']); ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-page); border-radius: var(--radius-md); border: 1px solid var(--border-default);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: var(--danger-500); font-size: 1.1rem;">●</span>
                        <span style="font-size: 0.88rem; font-weight: 600; color: var(--ink-900);">Failed / Throttled</span>
                    </div>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--danger-500);">
                        <?= e((string)$logins24h['failed']); ?>
                    </span>
                </div>
            </div>

            <div class="callout-warning" style="margin-top: 1rem; margin-bottom: 0; padding: 0.65rem 0.85rem; font-size: 0.8rem; line-height: 1.4;">
                <span style="font-weight: 700;">Rate Limiting Policy:</span> Locked after 5 consecutive password failures within 15 minutes.
            </div>
        </div>

        <!-- Quick Launch Console -->
        <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.4rem;">
                <span>⚡</span> Operational Navigation
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                <a href="<?= e(url('orders/kitchen_queue.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start; padding: 0.65rem 0.85rem; font-weight: 600;">
                    🍳 Live Kitchen Kanban Queue &rarr;
                </a>
                <a href="<?= e(url('admin/users.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start; padding: 0.65rem 0.85rem; font-weight: 600;">
                    👥 User Governance &amp; RBAC &rarr;
                </a>
                <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start; padding: 0.65rem 0.85rem; font-weight: 600;">
                    🍲 Culinary Dishes &amp; Pricing &rarr;
                </a>
                <a href="<?= e(url('menu/stock.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start; padding: 0.65rem 0.85rem; font-weight: 600;">
                    📦 Real-time Inventory Balances &rarr;
                </a>
                <a href="<?= e(url('admin/reports.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start; padding: 0.65rem 0.85rem; font-weight: 600;">
                    📈 Financial &amp; Sales Reports &rarr;
                </a>
                <a href="<?= e(url('admin/audit_log.php')); ?>" class="btn btn-secondary btn-sm" style="justify-content: flex-start; padding: 0.65rem 0.85rem; font-weight: 600;">
                    📜 Compliance Audit Trail &rarr;
                </a>
            </div>
        </div>

    </div>

</div>

<!-- Audio Test Helper for Admin -->
<script>
function testAlertChime() {
    // If our audio chime synthesizer is available in global scope, trigger it!
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) {
        alert("Web Audio API not supported by browser.");
        return;
    }
    const ctx = new AudioCtx();
    const now = ctx.currentTime;

    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(587.33, now);
    gain1.gain.setValueAtTime(0, now);
    gain1.gain.linearRampToValueAtTime(0.25, now + 0.04);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.start(now);
    osc1.stop(now + 0.46);

    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'triangle';
    osc2.frequency.setValueAtTime(880.00, now + 0.12);
    gain2.gain.setValueAtTime(0, now + 0.12);
    gain2.gain.linearRampToValueAtTime(0.3, now + 0.16);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.start(now + 0.12);
    osc2.stop(now + 0.81);

    // Show simulated toast alert
    if (typeof showOrderToast === 'function') {
        showOrderToast({
            order_id: 999,
            order_code: 'TEST-ORDER-' + Math.floor(Math.random() * 900 + 100),
            customer_name: 'Test Customer Alert',
            order_type: 'delivery',
            total_formatted: 'LKR 2,400.00',
            items_summary: '2x Classic Chicken Kottu, 1x Royal Faluda',
            time_ago: 'Just now'
        });
    } else {
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed; top:24px; right:24px; z-index:999999; background:#FFFFFF; border:1px solid #F26B1D; border-left:6px solid #F26B1D; border-radius:12px; padding:1rem 1.25rem; box-shadow:0 15px 35px rgba(242,107,29,0.25); font-family:sans-serif; animation:slideIn 0.3s ease;';
        toast.innerHTML = '<strong>🔔 Audio Chime &amp; Notification Working!</strong><div style="font-size:0.85rem; color:#6B7280; margin-top:0.25rem;">Live order alerts are configured and active.</div>';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
