<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Customer Order History
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();

$orders = [];
if ($pdo) {
    $stmt = $pdo->prepare(
        "SELECT o.*, 
                GROUP_CONCAT(CONCAT(oi.quantity, 'x ', d.name) SEPARATOR ', ') AS item_summary
         FROM orders o
         LEFT JOIN order_items oi ON o.order_id = oi.order_id
         LEFT JOIN dishes d ON oi.dish_id = d.dish_id
         WHERE o.user_id = ?
         GROUP BY o.order_id
         ORDER BY o.created_at DESC"
    );
    $stmt->execute([(int)$currentUser['id']]);
    $orders = $stmt->fetchAll();
}

$pageTitle = 'My Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 1.5rem; padding-bottom: 3.5rem;">
    
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
                Self-Service Ordering
            </span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">
                My Orders
            </h1>
            <p style="color: var(--ink-500); font-size: 1rem;">
                Review all your past and active orders placed with UrbanEats.
            </p>
        </div>

        <a href="<?= e(url('orders/cart.php')); ?>" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
            🛒 View Current Cart
        </a>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">📦</div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">You haven't placed any orders yet</h2>
            <p style="color: var(--ink-500); margin-bottom: 2rem;">Explore our menu and place your first freshly cooked meal.</p>
            <a href="<?= e(url('index.php')); ?>" class="btn btn-primary">Browse Catalogue</a>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0; overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 680px;">
                <thead>
                    <tr style="background: var(--bg-surface-alt); border-bottom: 1px solid var(--border-default); text-align: left; font-size: 0.85rem; color: var(--ink-500); text-transform: uppercase;">
                        <th style="padding: 1rem 1.25rem;">Order Code</th>
                        <th style="padding: 1rem 1.25rem;">Date &amp; Time</th>
                        <th style="padding: 1rem 1.25rem;">Items</th>
                        <th style="padding: 1rem 1.25rem;">Total</th>
                        <th style="padding: 1rem 1.25rem;">Status</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid var(--border-default);">
                    <?php foreach ($orders as $o): ?>
                        <tr style="border-bottom: 1px solid var(--border-default); font-size: 0.92rem;">
                            <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--ink-900);">
                                #<?= e($o['order_code']); ?>
                                <span style="display: block; font-size: 0.78rem; font-weight: 500; color: var(--ink-400);">
                                    <?= ucfirst(e($o['order_type'])); ?> &bull; <?= strtoupper(e($o['payment_method'])); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; color: var(--ink-600);">
                                <?= date('d M Y', strtotime($o['created_at'])); ?>
                                <span style="display: block; font-size: 0.8rem; color: var(--ink-400);">
                                    <?= date('h:i A', strtotime($o['created_at'])); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; color: var(--ink-700); max-width: 260px;">
                                <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                    <?= e($o['item_summary'] ?: 'Standard items'); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; font-weight: 800; color: var(--ink-900);">
                                LKR <?= number_format((float)$o['total_amount'], 2); ?>
                            </td>
                            <td style="padding: 1rem 1.25rem;">
                                <?php
                                $badgeStyle = 'background: var(--info-100); color: var(--info-700);';
                                if ($o['status'] === 'Completed') {
                                    $badgeStyle = 'background: var(--success-100); color: var(--success-700);';
                                } elseif ($o['status'] === 'Preparing') {
                                    $badgeStyle = 'background: var(--warning-100); color: var(--warning-700);';
                                } elseif ($o['status'] === 'Cancelled') {
                                    $badgeStyle = 'background: var(--danger-100); color: var(--danger-700);';
                                }
                                ?>
                                <span style="<?= $badgeStyle; ?> font-size: 0.8rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); display: inline-block;">
                                    <?= e($o['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right; white-space: nowrap;">
                                <a href="<?= e(url('orders/tracking.php?code=' . urlencode($o['order_code']))); ?>" class="btn btn-secondary btn-sm" style="font-weight: 600; font-size: 0.82rem; margin-right: 0.35rem;">
                                    Track
                                </a>
                                <a href="<?= e(url('orders/confirmation.php?code=' . urlencode($o['order_code']))); ?>" class="btn btn-secondary btn-sm" style="font-weight: 600; font-size: 0.82rem;">
                                    Receipt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
