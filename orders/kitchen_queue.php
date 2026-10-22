<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Kitchen Order Fulfillment Queue (Kanban Console)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_functions.php';

require_role(['kitchen_staff', 'admin']);
$currentUser = current_user();

// Handle status transitions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transition') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

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
                GROUP_CONCAT(CONCAT(oi.quantity, 'x ', d.name) SEPARATOR '<br>') AS items_html
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
$pageTitle = 'Kitchen Order Queue';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 1.5rem; padding-bottom: 3.5rem;">
    
    <!-- Page Header & Metrics Bar -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
                Component C &bull; Kitchen Operations
            </span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">
                Live Kitchen Order Queue
            </h1>
            <p style="color: var(--ink-500); font-size: 1rem;">
                Move tickets through kitchen stages. Transitions enforce strict business workflow rules.
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <div style="background: var(--bg-surface); border: 1px solid var(--border-default); padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.88rem;">
                <span style="color: var(--ink-500);">Active:</span> <strong style="color: var(--primary-600);"><?= $activeCount; ?></strong>
            </div>
            <div style="background: var(--bg-surface); border: 1px solid var(--border-default); padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.88rem;">
                <span style="color: var(--ink-500);">Completed Today:</span> <strong style="color: var(--success-600);"><?= $completedTodayCount; ?></strong>
            </div>
            <a href="<?= e(url('orders/kitchen_queue.php')); ?>" class="btn btn-secondary btn-sm" style="padding: 0.55rem 0.9rem;" title="Refresh Queue">
                🔄 Refresh
            </a>
        </div>
    </div>

    <!-- Kanban Board Layout -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; align-items: start;">
        
        <!-- Column 1: Placed Orders -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--info-500);">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0;">
                    📥 Placed (<?= count($ordersByStatus['Placed']); ?>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--info-100); color: var(--info-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Incoming
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                <?php if (empty($ordersByStatus['Placed'])): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No new incoming orders.</p>
                <?php else: ?>
                    <?php foreach ($ordersByStatus['Placed'] as $o): ?>
                        <div class="card" style="padding: 1.25rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-default);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900);">#<?= e($o['order_code']); ?></strong>
                                    <span style="display: block; font-size: 0.8rem; color: var(--ink-500);"><?= e($o['customer_name']); ?></span>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--ink-500); background: var(--bg-surface-alt); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= date('H:i', strtotime($o['created_at'])); ?>
                                </span>
                            </div>

                            <div style="font-size: 0.88rem; color: var(--ink-700); line-height: 1.5; margin-bottom: 1rem; padding: 0.5rem 0.75rem; background: var(--bg-page); border-radius: var(--radius-sm);">
                                <?= $o['items_html']; ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 0.85rem;">
                                <span style="font-weight: 600; color: var(--ink-600); text-transform: uppercase;">
                                    <?= e($o['order_type']); ?>
                                </span>
                                <strong style="color: var(--primary-600);">LKR <?= number_format((float)$o['total_amount'], 2); ?></strong>
                            </div>

                            <!-- Workflow Action Buttons -->
                            <div style="display: flex; gap: 0.5rem;">
                                <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>" style="flex: 1;">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="transition">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                    <input type="hidden" name="new_status" value="Preparing">
                                    <button type="submit" class="btn btn-primary btn-sm btn-block" style="font-weight: 700;">
                                        Start Preparing &rarr;
                                    </button>
                                </form>
                                <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="transition">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                    <input type="hidden" name="new_status" value="Cancelled">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger-600);" onclick="return confirm('Cancel this order?');" title="Cancel Order">
                                        ✕
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 2: Preparing Orders -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--warning-500);">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0;">
                    🍳 Preparing (<?= count($ordersByStatus['Preparing']); ?>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--warning-100); color: var(--warning-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Cooking
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                <?php if (empty($ordersByStatus['Preparing'])): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No orders currently on the stove.</p>
                <?php else: ?>
                    <?php foreach ($ordersByStatus['Preparing'] as $o): ?>
                        <div class="card" style="padding: 1.25rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-default);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900);">#<?= e($o['order_code']); ?></strong>
                                    <span style="display: block; font-size: 0.8rem; color: var(--ink-500);"><?= e($o['customer_name']); ?></span>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--ink-500); background: var(--bg-surface-alt); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= date('H:i', strtotime($o['created_at'])); ?>
                                </span>
                            </div>

                            <div style="font-size: 0.88rem; color: var(--ink-700); line-height: 1.5; margin-bottom: 1rem; padding: 0.5rem 0.75rem; background: var(--bg-page); border-radius: var(--radius-sm);">
                                <?= $o['items_html']; ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 0.85rem;">
                                <span style="font-weight: 600; color: var(--ink-600); text-transform: uppercase;">
                                    <?= e($o['order_type']); ?>
                                </span>
                                <strong style="color: var(--primary-600);">LKR <?= number_format((float)$o['total_amount'], 2); ?></strong>
                            </div>

                            <!-- Workflow Action Buttons -->
                            <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="transition">
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                <input type="hidden" name="new_status" value="Ready">
                                <button type="submit" class="btn btn-primary btn-sm btn-block" style="background: var(--warning-600); border-color: var(--warning-600); font-weight: 700;">
                                    Mark as Ready 🔔
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 3: Ready Orders -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--success-500);">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0;">
                    ✅ Ready (<?= count($ordersByStatus['Ready']); ?>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--success-100); color: var(--success-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Packaged
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                <?php if (empty($ordersByStatus['Ready'])): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No packaged orders awaiting dispatch.</p>
                <?php else: ?>
                    <?php foreach ($ordersByStatus['Ready'] as $o): ?>
                        <div class="card" style="padding: 1.25rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-default);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900);">#<?= e($o['order_code']); ?></strong>
                                    <span style="display: block; font-size: 0.8rem; color: var(--ink-500);"><?= e($o['customer_name']); ?></span>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--ink-500); background: var(--bg-surface-alt); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= date('H:i', strtotime($o['created_at'])); ?>
                                </span>
                            </div>

                            <div style="font-size: 0.88rem; color: var(--ink-700); line-height: 1.5; margin-bottom: 1rem; padding: 0.5rem 0.75rem; background: var(--bg-page); border-radius: var(--radius-sm);">
                                <?= $o['items_html']; ?>
                            </div>

                            <!-- Next Stage depends on order_type -->
                            <?php $nextStage = ($o['order_type'] === 'pickup') ? 'Collected' : 'Out for Delivery'; ?>
                            <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="transition">
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                <input type="hidden" name="new_status" value="<?= e($nextStage); ?>">
                                <button type="submit" class="btn btn-primary btn-sm btn-block" style="background: var(--success-600); border-color: var(--success-600); font-weight: 700;">
                                    Dispatch: <?= e($nextStage); ?> &rarr;
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 4: In Transit / Awaiting Final Completion -->
        <div class="card" style="padding: 0; background: var(--bg-surface-alt); border-top: 4px solid var(--ink-500);">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--ink-900); margin: 0;">
                    🛵 Transit / Counter (<?= count($ordersByStatus['Out for Delivery']) + count($ordersByStatus['Collected']); ?>)
                </h2>
                <span style="font-size: 0.75rem; background: var(--ink-100); color: var(--ink-700); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                    Delivery
                </span>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                <?php 
                $transitOrders = array_merge($ordersByStatus['Out for Delivery'], $ordersByStatus['Collected']);
                ?>
                <?php if (empty($transitOrders)): ?>
                    <p style="text-align: center; color: var(--ink-400); font-size: 0.9rem; padding: 1.5rem 0; margin: 0;">No orders currently on the road.</p>
                <?php else: ?>
                    <?php foreach ($transitOrders as $o): ?>
                        <div class="card" style="padding: 1.25rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-default);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <strong style="font-size: 1.05rem; color: var(--ink-900);">#<?= e($o['order_code']); ?></strong>
                                    <span style="display: block; font-size: 0.8rem; color: var(--ink-500);"><?= e($o['customer_name']); ?> (<?= e($o['status']); ?>)</span>
                                </div>
                            </div>

                            <p style="font-size: 0.85rem; color: var(--ink-600); margin-bottom: 1rem;">
                                📍 <?= nl2br(e($o['delivery_address'])); ?>
                            </p>

                            <form method="POST" action="<?= e(url('orders/kitchen_queue.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="transition">
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id']; ?>">
                                <input type="hidden" name="new_status" value="Completed">
                                <button type="submit" class="btn btn-secondary btn-sm btn-block" style="font-weight: 700; color: var(--success-700);">
                                    Mark as Delivered / Completed ✓
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
