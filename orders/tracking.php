<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Live Order Tracking & Status Progression Timeline
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = current_user();
$searchCode = trim($_GET['code'] ?? '');

$order = null;
$history = [];
$items = [];

if ($pdo) {
    if ($searchCode !== '') {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? LIMIT 1");
        $stmt->execute([$searchCode]);
        $order = $stmt->fetch();
    } elseif ($currentUser) {
        // Fetch user's latest order by default
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([(int)$currentUser['id']]);
        $order = $stmt->fetch();
    }

    if ($order) {
        // Fetch timeline history in chronological order to render the visual status progress timeline
        $stmtH = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC");
        $stmtH->execute([(int)$order['order_id']]);
        $history = $stmtH->fetchAll();

        // Fetch line items joining dishes catalog for presentation
        $stmtI = $pdo->prepare(
            "SELECT oi.*, d.name AS dish_name 
             FROM order_items oi
             JOIN dishes d ON d.dish_id = oi.dish_id
             WHERE oi.order_id = ?"
        );
        $stmtI->execute([(int)$order['order_id']]);
        $items = $stmtI->fetchAll();
    }
}

// Stage Definitions: Dynamic state progression mapping based on fulfillment modality (Delivery vs Pickup)
$stages = ['Placed', 'Preparing', 'Ready', 'Out for Delivery', 'Completed'];
$currentStatus = $order['status'] ?? '';
$isPickup = ($order['order_type'] ?? '') === 'pickup';
if ($isPickup) {
    $stages = ['Placed', 'Preparing', 'Ready', 'Collected', 'Completed'];
}

$pageTitle = 'Live Order Tracking';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 1.5rem; padding-bottom: 3.5rem; max-width: 860px;">
    
    <div style="margin-bottom: 2rem; text-align: center;">
        <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
            Real-Time Fulfillment Tracking
        </span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">
            Order Status Tracker
        </h1>
        <p style="color: var(--ink-500); font-size: 1rem;">
            Follow your meal from kitchen preparation to your doorstep.
        </p>

        <!-- Search Bar -->
        <form method="GET" action="<?= e(url('orders/tracking.php')); ?>" style="max-width: 460px; margin: 1.5rem auto 0 auto; display: flex; gap: 0.5rem;">
            <input type="text" name="code" class="form-control" placeholder="Enter Order Reference (e.g. ORD-2026-1001)" value="<?= e($searchCode ?: ($order['order_code'] ?? '')); ?>" required>
            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem; font-weight: 700;">Track</button>
        </form>
    </div>

    <?php if ($order): ?>
        <!-- Main Tracking Card -->
        <div class="card" style="padding: 2rem; margin-bottom: 2rem;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-default); padding-bottom: 1.25rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span style="font-size: 0.85rem; color: var(--ink-500);">Order Reference:</span>
                    <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--ink-900); margin: 0.1rem 0;">
                        #<?= e($order['order_code']); ?>
                    </h2>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 0.85rem; color: var(--ink-500);">Current Status:</span>
                    <span style="display: block; font-weight: 800; color: var(--primary-600); font-size: 1.15rem;">
                        <?= e($order['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Visual Progression Stepper -->
            <div style="display: flex; justify-content: space-between; position: relative; margin-bottom: 2.5rem; gap: 0.5rem; flex-wrap: wrap;">
                <?php 
                $currentIndex = array_search($currentStatus, $stages);
                if ($currentIndex === false && $currentStatus === 'Cancelled') {
                    $currentIndex = -1;
                }
                ?>

                <?php foreach ($stages as $idx => $stage): ?>
                    <?php 
                        $isCompleted = ($currentIndex !== -1 && $idx <= $currentIndex);
                        $isCurrent = ($idx === $currentIndex);
                    ?>
                    <div style="flex: 1; min-width: 110px; text-align: center; position: relative;">
                        <!-- Step Icon -->
                        <div style="width: 44px; height: 44px; border-radius: var(--radius-full); margin: 0 auto 0.75rem auto; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem;
                            <?= $isCompleted ? 'background: var(--primary-500); color: #FFFFFF; box-shadow: 0 4px 10px rgba(234, 88, 12, 0.3);' : 'background: var(--bg-surface-alt); color: var(--ink-400); border: 2px solid var(--border-default);'; ?>
                            <?= $isCurrent ? 'ring: 3px solid var(--primary-200);' : ''; ?>">
                            <?= $isCompleted ? ($isCurrent ? '●' : '✓') : ($idx + 1); ?>
                        </div>
                        <span style="font-size: 0.82rem; font-weight: <?= $isCurrent ? '800' : '600'; ?>; color: <?= $isCompleted ? 'var(--ink-900)' : 'var(--ink-400)'; ?>; display: block;">
                            <?= e($stage); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($currentStatus === 'Cancelled'): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem; text-align: center; font-weight: 700;">
                    ⚠️ This order was cancelled and is no longer being processed.
                </div>
            <?php endif; ?>

            <!-- Timeline logs -->
            <div style="background: var(--bg-surface-alt); border-radius: var(--radius-md); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--ink-800); margin: 0 0 1rem 0;">Status Timeline History</h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php if (empty($history)): ?>
                        <span style="font-size: 0.88rem; color: var(--ink-500);">No historical timeline entries logged yet.</span>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 0.88rem; border-bottom: 1px dashed var(--border-default); padding-bottom: 0.4rem;">
                                <span>
                                    <strong style="color: var(--ink-900);"><?= e($h['status']); ?></strong> 
                                    <small style="color: var(--ink-500);">stage updated</small>
                                </span>
                                <span style="color: var(--ink-500);"><?= date('h:i A - d M Y', strtotime($h['changed_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items and Delivery Details -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; font-size: 0.9rem;">
                <div>
                    <h4 style="font-weight: 700; color: var(--ink-900); margin: 0 0 0.5rem 0;">Items Ordered</h4>
                    <ul style="margin: 0; padding-left: 1.25rem; color: var(--ink-600); line-height: 1.6;">
                        <?php foreach ($items as $it): ?>
                            <li><?= (int)$it['quantity']; ?> &times; <?= e($it['dish_name']); ?> (LKR <?= number_format((float)$it['price_at_purchase'], 2); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4 style="font-weight: 700; color: var(--ink-900); margin: 0 0 0.5rem 0;">Fulfillment Details</h4>
                    <p style="margin: 0; color: var(--ink-600); line-height: 1.5;">
                        <strong>Address:</strong> <?= nl2br(e($order['delivery_address'])); ?><br>
                        <strong>Contact:</strong> <?= e($order['phone']); ?><br>
                        <strong>Total:</strong> LKR <?= number_format((float)$order['total_amount'], 2); ?> (<?= strtoupper(e($order['payment_method'])); ?>)
                    </p>
                </div>
            </div>

        </div>

        <div style="text-align: center;">
            <a href="<?= e(url('orders/history.php')); ?>" class="btn btn-secondary">
                &larr; Back to Order History
            </a>
        </div>

    <?php else: ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h2 style="font-size: 1.4rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">No Order Selected</h2>
            <p style="color: var(--ink-500); margin-bottom: 1.5rem;">Please enter your order reference code above to see live updates, or check your order history.</p>
            <a href="<?= e(url('orders/history.php')); ?>" class="btn btn-primary">Go to My Orders</a>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
