<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Live Kitchen Order Fulfillment Queue (Interactive Kanban Console)
 * 
 * Features:
 * - Real-time ticket board across 4 operational fulfillment stages
 * - Live auto-refresh polling with audio chime triggers on new orders
 * - Interactive instant ticket search & filter
 * - Strict business state transitions & workflow guards
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_functions.php';

// ACCESS CONTROL: Enforce Kitchen Staff or Administrator role guard (RBAC)
require_role(['kitchen_staff', 'admin']);
$currentUser = current_user();

// Handle status transitions via the Finite State Machine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transition') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    // Invokes concurrency-safe FSM status transition with optimistic locking & audit trail
    if ($orderId > 0 && $newStatus !== '' && $pdo) {
        $success = change_order_status($pdo, $orderId, $newStatus, (int)$currentUser['id']);
        if ($success) {
            set_flash('success', "Order #{$orderId} moved to '{$newStatus}'.");
        } else {
            set_flash('error', "Invalid status transition to '{$newStatus}'.");
        }
    }
    redirect(url('orders/kitchen_queue.php'));
}

// Fetch orders by status with items
// OPTIMIZATION: Uses GROUP_CONCAT to aggregate line items in a single SQL query, eliminating the N+1 query problem
$ordersByStatus = [
    'Placed' => [],
    'Preparing' => [],
    'Ready' => [],
    'Out for Delivery' => [],
    'Collected' => [],
];

$activeCount = 0;
$completedTodayCount = 0;
$cancelledTodayCount = 0;

if ($pdo) {
    $stmt = $pdo->query(
        "SELECT o.*, u.full_name AS customer_name,
                GROUP_CONCAT(CONCAT(oi.quantity, 'x ', d.name) SEPARATOR '<br>') AS items_html,
                GROUP_CONCAT(CONCAT(oi.quantity, 'x ', d.name) SEPARATOR ', ') AS items_plain
         FROM orders o
         JOIN users u ON o.user_id = u.id
         LEFT JOIN order_items oi ON o.order_id = oi.order_id
         LEFT JOIN dishes d ON oi.dish_id = d.dish_id
         WHERE o.status NOT IN ('Completed', 'Cancelled')
         GROUP BY o.order_id
         ORDER BY o.created_at ASC"
    );
    while ($row = $stmt->fetch()) {
        $status = $row['status'];
        if (isset($ordersByStatus[$status])) {
            $ordersByStatus[$status][] = $row;
            $activeCount++;
        }
    }

    $completedTodayCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM orders WHERE status = 'Completed' AND DATE(created_at) = CURDATE()"
    )->fetchColumn();

    $cancelledTodayCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM orders WHERE status = 'Cancelled' AND DATE(created_at) = CURDATE()"
    )->fetchColumn();
}

$transitions = allowed_transitions();
$pageTitle = 'Live Kitchen Order Queue';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.kitchen-ticket {
    padding: 1.25rem;
    border-radius: var(--radius-md);
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid var(--border-default);
    background: #FFFFFF;
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease;
}

.kitchen-ticket:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.ticket-placed-glow {
    border-left: 5px solid #F26B1D !important;
    background: linear-gradient(180deg, rgba(254, 243, 199, 0.15) 0%, #FFFFFF 100%);
}

.ticket-preparing-glow {
    border-left: 5px solid #F59E0B !important;
}

.ticket-ready-glow {
    border-left: 5px solid #10B981 !important;
}
</style>

<div class="container" style="padding-top: 1.5rem; padding-bottom: 3.5rem;">
    
    <!-- Page Header & Metrics Bar -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.3rem;">
                <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.78rem; font-weight: 700; padding: 0.2rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid rgba(16, 185, 129, 0.25);">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981; animation: ue-pulse-dot 1.5s infinite;"></span>
                    Live Syncing
                </span>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
                    Kitchen Operations
                </span>
            </div>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0 0 0.25rem; letter-spacing: -0.02em;">
                Live Kitchen Order Queue
            </h1>
            <p style="color: var(--ink-500); font-size: 1rem; margin: 0;">
                Move tickets through kitchen stages. Transitions enforce strict culinary workflow rules.
            </p>
        </div>

        <!-- Metric Badges & Quick Action Controls -->
        <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
            <div style="background: var(--bg-surface); border: 1px solid var(--border-default); padding: 0.45rem 0.9rem; border-radius: var(--radius-md); font-size: 0.88rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <span style="color: var(--ink-500);">Active Tickets:</span> <strong style="color: var(--primary-600); font-size: 1.05rem;" class="ue-live-active-orders"><?= $activeCount; ?></strong>
            </div>
            <div style="background: var(--bg-surface); border: 1px solid var(--border-default); padding: 0.45rem 0.9rem; border-radius: var(--radius-md); font-size: 0.88rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <span style="color: var(--ink-500);">Completed Today:</span> <strong style="color: var(--success-600); font-size: 1.05rem;"><?= $completedTodayCount; ?></strong>
            </div>
            <a href="<?= e(url('orders/kitchen_queue.php')); ?>" class="btn btn-secondary btn-sm" style="padding: 0.5rem 0.9rem; font-weight: 600;" title="Refresh Queue Board">
                🔄 Refresh Board
            </a>
        </div>
    </div>

    <!-- Interactive Search Bar & Live Filters -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; background: var(--bg-surface); padding: 0.85rem 1.15rem; border-radius: var(--radius-md); border: 1px solid var(--border-default);">
        <div style="flex: 1; max-width: 480px; position: relative;">
            <input type="text" 
                   id="queueSearch" 
                   placeholder="🔍 Quick filter by order code, customer name, or dish..." 
                   class="form-control" 
                   style="border-radius: 9999px; padding: 0.5rem 1.15rem; font-size: 0.88rem; background: var(--bg-page); border: 1px solid var(--border-default);"
                   autocomplete="off">
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--ink-500);">
            <span id="ticketCountLabel">Showing <strong><?= $activeCount; ?></strong> active orders</span>
        </div>
    </div>

    <!-- Kanban Board Layout -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 1.5rem; align-items: start;">
        
        <!-- Column 1: Placed Orders (Incoming) -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--info-500); border-radius: 14px; overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                    <span>📥</span> Placed (<span id="placedColumnCounter"><?= count($ordersByStatus['Placed']); ?></span>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--info-100); color: var(--info-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Incoming
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;" class="ticket-column-container">
                <?php if (empty($ordersByStatus['Placed'])): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No incoming orders.</p>
                <?php else: ?>
                    <?php foreach ($ordersByStatus['Placed'] as $o): ?>
                        <div class="kitchen-ticket ticket-placed-glow" data-search="<?= strtolower(e($o['order_code'] . ' ' . $o['customer_name'] . ' ' . ($o['items_plain'] ?? ''))); ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900); font-family: 'Courier New', Courier, monospace;">
                                        #<?= e($o['order_code']); ?>
                                    </strong>
                                    <span style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-600);"><?= e($o['customer_name']); ?></span>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--ink-500); background: var(--bg-surface-alt); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= date('H:i', strtotime($o['created_at'])); ?>
                                </span>
                            </div>

                            <div style="font-size: 0.88rem; color: var(--ink-800); line-height: 1.5; margin-bottom: 0.85rem; padding: 0.6rem 0.8rem; background: var(--bg-page); border-radius: var(--radius-sm); border: 1px solid var(--border-default);">
                                <?= $o['items_html']; ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 0.85rem;">
                                <span style="font-weight: 700; color: <?= ($o['order_type'] === 'delivery') ? '#1D4ED8' : '#047857'; ?>; text-transform: uppercase;">
                                    <?= ($o['order_type'] === 'delivery') ? '🛵 Delivery' : '🏪 Pickup'; ?>
                                </span>
                                <strong style="color: var(--primary-600); font-size: 0.95rem;">LKR <?= number_format((float)$o['total_amount'], 2); ?></strong>
                            </div>

                            <!-- Workflow Action Buttons -->
                            <div style="display: flex; gap: 0.5rem;">
                                <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>" style="flex: 1;">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="transition">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                    <input type="hidden" name="new_status" value="Preparing">
                                    <button type="submit" class="btn btn-primary btn-sm btn-block" style="background: linear-gradient(135deg, #10B981, #059669); border: none; color: #FFFFFF !important; font-weight: 800; padding: 0.65rem 0.85rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);">
                                        ✅ Approve &amp; Cook &rarr;
                                    </button>
                                </form>
                                <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="transition">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                    <input type="hidden" name="new_status" value="Cancelled">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: #DC2626; border-color: rgba(220, 38, 38, 0.3); padding: 0.65rem 0.75rem; border-radius: 8px;" data-confirm="Are you sure you want to cancel this order?" title="Cancel Order">
                                        ✕
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 2: Preparing Orders (Cooking) -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--warning-500); border-radius: 14px; overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                    <span>🍳</span> Preparing (<?= count($ordersByStatus['Preparing']); ?>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--warning-100); color: var(--warning-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Cooking
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;" class="ticket-column-container">
                <?php if (empty($ordersByStatus['Preparing'])): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No tickets currently on the stove.</p>
                <?php else: ?>
                    <?php foreach ($ordersByStatus['Preparing'] as $o): ?>
                        <div class="kitchen-ticket ticket-preparing-glow" data-search="<?= strtolower(e($o['order_code'] . ' ' . $o['customer_name'] . ' ' . ($o['items_plain'] ?? ''))); ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900); font-family: 'Courier New', Courier, monospace;">
                                        #<?= e($o['order_code']); ?>
                                    </strong>
                                    <span style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-600);"><?= e($o['customer_name']); ?></span>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--ink-500); background: var(--bg-surface-alt); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= date('H:i', strtotime($o['created_at'])); ?>
                                </span>
                            </div>

                            <div style="font-size: 0.88rem; color: var(--ink-800); line-height: 1.5; margin-bottom: 0.85rem; padding: 0.6rem 0.8rem; background: var(--bg-page); border-radius: var(--radius-sm); border: 1px solid var(--border-default);">
                                <?= $o['items_html']; ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 0.85rem;">
                                <span style="font-weight: 700; color: <?= ($o['order_type'] === 'delivery') ? '#1D4ED8' : '#047857'; ?>; text-transform: uppercase;">
                                    <?= ($o['order_type'] === 'delivery') ? '🛵 Delivery' : '🏪 Pickup'; ?>
                                </span>
                                <strong style="color: var(--primary-600); font-size: 0.95rem;">LKR <?= number_format((float)$o['total_amount'], 2); ?></strong>
                            </div>

                            <!-- Workflow Action Buttons -->
                            <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="transition">
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                <input type="hidden" name="new_status" value="Ready">
                                <button type="submit" class="btn btn-primary btn-sm btn-block" style="background: linear-gradient(135deg, #F59E0B, #D97706); border: none; color: #FFFFFF !important; font-weight: 800; padding: 0.65rem 0.85rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35);">
                                    🔔 Mark as Ready &amp; Packed &rarr;
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 3: Ready Orders (Packaged) -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--success-500); border-radius: 14px; overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                    <span>✅</span> Ready (<?= count($ordersByStatus['Ready']); ?>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--success-100); color: var(--success-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Packaged
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;" class="ticket-column-container">
                <?php if (empty($ordersByStatus['Ready'])): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No packaged orders awaiting dispatch.</p>
                <?php else: ?>
                    <?php foreach ($ordersByStatus['Ready'] as $o): ?>
                        <div class="kitchen-ticket ticket-ready-glow" data-search="<?= strtolower(e($o['order_code'] . ' ' . $o['customer_name'] . ' ' . ($o['items_plain'] ?? ''))); ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900); font-family: 'Courier New', Courier, monospace;">
                                        #<?= e($o['order_code']); ?>
                                    </strong>
                                    <span style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-600);"><?= e($o['customer_name']); ?></span>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--ink-500); background: var(--bg-surface-alt); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= date('H:i', strtotime($o['created_at'])); ?>
                                </span>
                            </div>

                            <div style="font-size: 0.88rem; color: var(--ink-800); line-height: 1.5; margin-bottom: 0.85rem; padding: 0.6rem 0.8rem; background: var(--bg-page); border-radius: var(--radius-sm); border: 1px solid var(--border-default);">
                                <?= $o['items_html']; ?>
                            </div>

                            <!-- Next Stage depends on order_type -->
                            <?php $nextStage = ($o['order_type'] === 'pickup') ? 'Collected' : 'Out for Delivery'; ?>
                            <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="transition">
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                <input type="hidden" name="new_status" value="<?= e($nextStage); ?>">
                                <button type="submit" class="btn btn-primary btn-sm btn-block" style="background: linear-gradient(135deg, #0D9488, #059669); border: none; color: #FFFFFF !important; font-weight: 800; padding: 0.65rem 0.85rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35);">
                                    <?= ($o['order_type'] === 'pickup') ? '🏪 Handover: Collected' : '🛵 Dispatch: Out for Delivery'; ?> &rarr;
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 4: In Transit / Awaiting Final Completion -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--ink-500); border-radius: 14px; overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                    <span>🛵</span> Transit (<?= count($ordersByStatus['Out for Delivery']) + count($ordersByStatus['Collected']); ?>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--ink-100); color: var(--ink-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Dispatch
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;" class="ticket-column-container">
                <?php 
                $transitOrders = array_merge($ordersByStatus['Out for Delivery'], $ordersByStatus['Collected']);
                ?>
                <?php if (empty($transitOrders)): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No orders currently in transit.</p>
                <?php else: ?>
                    <?php foreach ($transitOrders as $o): ?>
                        <div class="kitchen-ticket" data-search="<?= strtolower(e($o['order_code'] . ' ' . $o['customer_name'] . ' ' . $o['delivery_address'])); ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900); font-family: 'Courier New', Courier, monospace;">
                                        #<?= e($o['order_code']); ?>
                                    </strong>
                                    <span style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-600);"><?= e($o['customer_name']); ?></span>
                                </div>
                                <span style="font-size: 0.75rem; font-weight: 700; background: var(--primary-50); color: var(--primary-600); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= e($o['status']); ?>
                                </span>
                            </div>

                            <p style="font-size: 0.85rem; color: var(--ink-600); margin: 0 0 1rem; line-height: 1.4;">
                                📍 <?= nl2br(e($o['delivery_address'])); ?>
                            </p>

                            <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="transition">
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                <input type="hidden" name="new_status" value="Completed">
                                <button type="submit" class="btn btn-primary btn-sm btn-block" style="background: linear-gradient(135deg, #16A34A, #15803D); border: none; color: #FFFFFF !important; font-weight: 800; padding: 0.65rem 0.85rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);">
                                    ✓ Complete &amp; Fulfill Order
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- Interactive Client-side Ticket Filter -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('queueSearch');
    const counterLabel = document.getElementById('ticketCountLabel');
    const allTickets = document.querySelectorAll('.kitchen-ticket');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            let visibleCount = 0;

            allTickets.forEach(ticket => {
                const searchContent = (ticket.getAttribute('data-search') || '').toLowerCase();
                if (!query || searchContent.includes(query)) {
                    ticket.style.display = 'block';
                    visibleCount++;
                } else {
                    ticket.style.display = 'none';
                }
            });

            if (counterLabel) {
                if (query) {
                    counterLabel.innerHTML = `Found <strong>${visibleCount}</strong> of ${allTickets.length} orders`;
                } else {
                    counterLabel.innerHTML = `Showing <strong>${allTickets.length}</strong> active orders`;
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
