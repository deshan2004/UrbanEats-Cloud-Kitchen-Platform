<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Order Confirmation & Invoice Receipt
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();

$code = trim($_GET['code'] ?? '');
$order = null;
$items = [];

if ($pdo && $code !== '') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND user_id = ?");
    $stmt->execute([$code, (int)$currentUser['id']]);
    $order = $stmt->fetch();

    if ($order) {
        $stmtItems = $pdo->prepare(
            "SELECT oi.*, d.name AS dish_name 
             FROM order_items oi
             JOIN dishes d ON d.dish_id = oi.dish_id
             WHERE oi.order_id = ?"
        );
        $stmtItems->execute([(int)$order['order_id']]);
        $items = $stmtItems->fetchAll();
    }
}

if (!$order) {
    set_flash('error', 'Order not found or access denied.');
    redirect(url('index.php'));
}

$pageTitle = 'Order Confirmation #' . $order['order_code'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 2rem; padding-bottom: 3.5rem; max-width: 760px;">
    
    <div style="text-align: center; margin-bottom: 2rem;">
        <div style="width: 64px; height: 64px; border-radius: var(--radius-full); background: var(--success-100); color: var(--success-600); display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem;">
            ✓
        </div>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin-bottom: 0.5rem;">
            Order Received!
        </h1>
        <p style="color: var(--ink-500); font-size: 1.05rem;">
            Thank you, <?= e($currentUser['full_name']); ?>. Your order has been placed and sent to our kitchen team.
        </p>
    </div>

    <!-- Invoice Receipt Card -->
    <div class="card" style="padding: 2.25rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-default); padding-bottom: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase;">UrbanEats Cloud Kitchen</span>
                <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">Invoice Receipt</h2>
                <span style="color: var(--ink-500); font-size: 0.88rem;">Reference: <strong>#<?= e($order['order_code']); ?></strong></span>
            </div>
            <div style="text-align: right;">
                <span class="badge" style="background: var(--info-100); color: var(--info-700); font-weight: 700; padding: 0.4rem 0.9rem; border-radius: var(--radius-full); font-size: 0.85rem;">
                    Status: <?= e($order['status']); ?>
                </span>
                <span style="display: block; color: var(--ink-400); font-size: 0.82rem; margin-top: 0.4rem;">
                    <?= date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                </span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
            <div>
                <strong style="color: var(--ink-700); display: block; margin-bottom: 0.25rem;">Delivery Destination:</strong>
                <p style="color: var(--ink-600); margin: 0; line-height: 1.5;"><?= nl2br(e($order['delivery_address'])); ?></p>
                <span style="color: var(--ink-500); display: block; margin-top: 0.25rem;">Phone: <?= e($order['phone']); ?></span>
            </div>
            <div>
                <strong style="color: var(--ink-700); display: block; margin-bottom: 0.25rem;">Order &amp; Payment Details:</strong>
                <span style="color: var(--ink-600); display: block;">Method: <strong><?= strtoupper(e($order['payment_method'])); ?></strong></span>
                <span style="color: var(--ink-600); display: block;">Fulfillment: <?= ucfirst(e($order['order_type'])); ?></span>
                <?php if (!empty($order['notes'])): ?>
                    <span style="color: var(--ink-500); display: block; margin-top: 0.25rem; font-style: italic;">Notes: <?= e($order['notes']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-default); text-align: left; font-size: 0.85rem; color: var(--ink-500);">
                    <th style="padding: 0.6rem 0;">Dish</th>
                    <th style="padding: 0.6rem 0; text-align: center;">Qty</th>
                    <th style="padding: 0.6rem 0; text-align: right;">Unit Price</th>
                    <th style="padding: 0.6rem 0; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr style="border-bottom: 1px solid var(--border-default); font-size: 0.92rem;">
                        <td style="padding: 0.75rem 0; font-weight: 600; color: var(--ink-900);"><?= e($item['dish_name']); ?></td>
                        <td style="padding: 0.75rem 0; text-align: center; color: var(--ink-700);"><?= (int)$item['quantity']; ?></td>
                        <td style="padding: 0.75rem 0; text-align: right; color: var(--ink-600);">LKR <?= number_format((float)$item['price_at_purchase'], 2); ?></td>
                        <td style="padding: 0.75rem 0; text-align: right; font-weight: 700; color: var(--ink-900);">
                            LKR <?= number_format((float)$item['price_at_purchase'] * (int)$item['quantity'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Breakdown -->
        <div style="max-width: 280px; margin-left: auto; font-size: 0.92rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: var(--ink-600);">
                <span>Subtotal:</span>
                <span>LKR <?= number_format((float)$order['subtotal'], 2); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: var(--ink-600);">
                <span>Delivery:</span>
                <span>LKR <?= number_format((float)$order['delivery_fee'], 2); ?></span>
            </div>
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: var(--success-600); font-weight: 600;">
                    <span>Discount:</span>
                    <span>- LKR <?= number_format((float)$order['discount_amount'], 2); ?></span>
                </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; padding-top: 0.6rem; border-top: 2px solid var(--border-default); font-size: 1.15rem; font-weight: 800; color: var(--ink-900);">
                <span>Total:</span>
                <span style="color: var(--primary-600);">LKR <?= number_format((float)$order['total_amount'], 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="<?= e(url('orders/tracking.php?code=' . urlencode($order['order_code']))); ?>" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
            Track Order Live &rarr;
        </a>
        <a href="<?= e(url('orders/history.php')); ?>" class="btn btn-secondary" style="padding: 0.75rem 1.75rem;">
            View All My Orders
        </a>
        <button onclick="window.print()" class="btn btn-secondary" style="padding: 0.75rem 1.5rem;">
            🖨️ Print Receipt
        </button>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
