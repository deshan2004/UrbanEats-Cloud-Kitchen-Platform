<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Transactional Checkout & Atomic Order Placement
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_functions.php';

require_login();
$currentUser = current_user();

if (empty($_SESSION['cart'])) {
    set_flash('info', 'Your cart is empty. Please add dishes before proceeding to checkout.');
    redirect(url('orders/cart.php'));
}

$subtotal = 0.00;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}

$delivery_fee = 250.00;
$discount = 0.00;
if (isset($_SESSION['applied_coupon']) && $subtotal > 0 && $pdo) {
    [$valid, $c] = validate_coupon($pdo, $_SESSION['applied_coupon']['code'], $subtotal);
    if ($valid && is_array($c)) {
        $discount = (float)$c['discount_amount'];
    }
}

$errors = [];

// Handle Checkout Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    $orderType = in_array($_POST['order_type'] ?? '', ['delivery', 'pickup'], true) ? $_POST['order_type'] : 'delivery';
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cod', 'card', 'bank'], true) ? $_POST['payment_method'] : 'cod';

    if ($orderType === 'delivery' && empty($address)) {
        $errors['address'] = 'Delivery address is required for delivery orders.';
    }
    if (empty($phone)) {
        $errors['phone'] = 'A valid contact phone number is required.';
    }

    if (empty($errors) && $pdo) {
        try {
            $pdo->beginTransaction();

            // 1. Recalculate and lock dishes to prevent client tampering
            $calculatedSubtotal = 0.00;
            $orderItems = [];

            foreach ($_SESSION['cart'] as $dishId => $cartItem) {
                $stmt = $pdo->prepare("SELECT dish_id, name, price, stock_quantity FROM dishes WHERE dish_id = ? AND is_active = 1 FOR UPDATE");
                $stmt->execute([(int)$dishId]);
                $dbDish = $stmt->fetch();

                if (!$dbDish) {
                    throw new RuntimeException("Item '" . ($cartItem['name'] ?? 'Dish') . "' is no longer available.");
                }

                $qty = (int)$cartItem['quantity'];
                if ($dbDish['stock_quantity'] < $qty) {
                    throw new RuntimeException("Sorry, only {$dbDish['stock_quantity']} portions of '{$dbDish['name']}' are currently available.");
                }

                $price = (float)$dbDish['price'];
                $calculatedSubtotal += $price * $qty;

                $orderItems[] = [
                    'dish_id' => (int)$dbDish['dish_id'],
                    'name' => $dbDish['name'],
                    'quantity' => $qty,
                    'price' => $price,
                ];
            }

            // Calculate fees & discounts
            $deliveryFee = ($orderType === 'delivery') ? 250.00 : 0.00;
            $discountAmount = 0.00;

            if (isset($_SESSION['applied_coupon'])) {
                [$cValid, $cData] = validate_coupon($pdo, $_SESSION['applied_coupon']['code'], $calculatedSubtotal);
                if ($cValid && is_array($cData)) {
                    $discountAmount = (float)$cData['discount_amount'];
                }
            }

            $totalAmount = max(0.00, $calculatedSubtotal + $deliveryFee - $discountAmount);
            $orderCode = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // 2. Insert into `orders`
            $stmtOrder = $pdo->prepare(
                "INSERT INTO orders (order_code, user_id, order_type, delivery_address, phone, notes, payment_method, subtotal, delivery_fee, discount_amount, total_amount, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Placed', NOW())"
            );
            $stmtOrder->execute([
                $orderCode,
                (int)$currentUser['id'],
                $orderType,
                ($orderType === 'delivery' ? $address : 'Self Pickup at Kitchen Counter'),
                $phone,
                $notes,
                $paymentMethod,
                $calculatedSubtotal,
                $deliveryFee,
                $discountAmount,
                $totalAmount,
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // 3. Insert into `order_items` and deduct live inventory
            $stmtItem = $pdo->prepare(
                "INSERT INTO order_items (order_id, dish_id, quantity, price_at_purchase)
                 VALUES (?, ?, ?, ?)"
            );
            $stmtDeductStock = $pdo->prepare(
                "UPDATE dishes SET stock_quantity = stock_quantity - ? WHERE dish_id = ?"
            );

            foreach ($orderItems as $oi) {
                $stmtItem->execute([
                    $orderId,
                    $oi['dish_id'],
                    $oi['quantity'],
                    $oi['price'],
                ]);
                $stmtDeductStock->execute([
                    $oi['quantity'],
                    $oi['dish_id'],
                ]);
            }

            // 4. Initial status in `order_status_history`
            $stmtHistory = $pdo->prepare(
                "INSERT INTO order_status_history (order_id, status, changed_by, changed_at)
                 VALUES (?, 'Placed', ?, NOW())"
            );
            $stmtHistory->execute([$orderId, (int)$currentUser['id']]);

            // 5. System audit log
            if (function_exists('log_action')) {
                log_action(
                    $pdo,
                    (int)$currentUser['id'],
                    'ORDER_PLACED',
                    "Order #{$orderCode} placed successfully (Total: LKR " . number_format($totalAmount, 2) . ")"
                );
            }

            $pdo->commit();

            // Clear session cart & coupon
            $_SESSION['cart'] = [];
            unset($_SESSION['applied_coupon']);

            set_flash('success', 'Your order was placed successfully!');
            redirect(url('orders/confirmation.php?code=' . urlencode($orderCode)));

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Checkout Transaction Failed] ' . $e->getMessage());
            $errors['general'] = 'Failed to place order: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
    <div style="margin-bottom: 2rem;">
        <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
            Component C &bull; Transactional Checkout
        </span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">
            Review &amp; Place Order
        </h1>
        <p style="color: var(--ink-500); font-size: 1rem;">
            Confirm your delivery address and payment preference.
        </p>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <?= e($errors['general']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(url('orders/checkout.php')); ?>" style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- 1. Delivery Details Section -->
            <div class="card" style="padding: 1.75rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--ink-900); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="width: 28px; height: 28px; border-radius: var(--radius-full); background: var(--primary-50); color: var(--primary-600); display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem;">1</span>
                    Fulfillment &amp; Contact Details
                </h2>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label style="font-weight: 700; display: block; margin-bottom: 0.5rem;">Order Type</label>
                    <div style="display: flex; gap: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                            <input type="radio" name="order_type" value="delivery" checked id="orderTypeDelivery" onchange="toggleDeliveryFields(true)">
                            🛵 Home Delivery (LKR 250)
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                            <input type="radio" name="order_type" value="pickup" id="orderTypePickup" onchange="toggleDeliveryFields(false)">
                            🏪 Kitchen Pickup (Free)
                        </label>
                    </div>
                </div>

                <div class="form-group" id="deliveryAddressGroup" style="margin-bottom: 1.25rem;">
                    <label for="address" class="form-label">Delivery Address *</label>
                    <textarea name="address" id="address" class="form-control" rows="2" placeholder="e.g. No. 24, Galle Road, Colombo 03"><?= e($_POST['address'] ?? ($currentUser['address'] ?? '')); ?></textarea>
                    <?php if (!empty($errors['address'])): ?>
                        <div class="field-error"><?= e($errors['address']); ?></div>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="phone" class="form-label">Contact Phone *</label>
                        <input type="tel" name="phone" id="phone" class="form-control" placeholder="07X XXX XXXX" value="<?= e($_POST['phone'] ?? ($currentUser['phone'] ?? '')); ?>" required>
                        <?php if (!empty($errors['phone'])): ?>
                            <div class="field-error"><?= e($errors['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="notes" class="form-label">Order Notes (Optional)</label>
                        <input type="text" name="notes" id="notes" class="form-control" placeholder="e.g. Call at gate / less spicy" value="<?= e($_POST['notes'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- 2. Payment Method Section -->
            <div class="card" style="padding: 1.75rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--ink-900); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="width: 28px; height: 28px; border-radius: var(--radius-full); background: var(--primary-50); color: var(--primary-600); display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem;">2</span>
                    Payment Method
                </h2>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <label style="border: 2px solid var(--primary-500); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; background: var(--primary-50);">
                        <input type="radio" name="payment_method" value="cod" checked>
                        <div>
                            <strong style="display: block; color: var(--ink-900);">💵 Cash on Delivery (COD)</strong>
                            <small style="color: var(--ink-500);">Pay cash to our delivery rider upon receipt</small>
                        </div>
                    </label>

                    <label style="border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                        <input type="radio" name="payment_method" value="card">
                        <div>
                            <strong style="display: block; color: var(--ink-900);">💳 Credit / Debit Card (Simulated)</strong>
                            <small style="color: var(--ink-500);">Transaction simulated in test sandbox environment</small>
                        </div>
                    </label>

                    <label style="border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                        <input type="radio" name="payment_method" value="bank">
                        <div>
                            <strong style="display: block; color: var(--ink-900);">🏦 Direct Bank Transfer</strong>
                            <small style="color: var(--ink-500);">Instant electronic fund transfer verification</small>
                        </div>
                    </label>
                </div>
            </div>

        </div>

        <!-- Sidebar Summary -->
        <div class="card" style="padding: 1.75rem; position: sticky; top: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--ink-900); margin-bottom: 1rem;">
                Order Summary
            </h3>

            <div style="divide-y: 1px solid var(--border-default); margin-bottom: 1rem;">
                <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; font-size: 0.9rem;">
                        <div>
                            <strong style="color: var(--ink-900);"><?= e($item['name']); ?></strong>
                            <span style="color: var(--ink-500); display: block; font-size: 0.8rem;">Qty: <?= (int)$item['quantity']; ?> &times; LKR <?= number_format((float)$item['price'], 2); ?></span>
                        </div>
                        <span style="font-weight: 700; color: var(--ink-900);">
                            LKR <?= number_format((float)$item['price'] * (int)$item['quantity'], 2); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-default); margin: 0.75rem 0;">

            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--ink-600);">
                <span>Subtotal</span>
                <span>LKR <?= number_format($subtotal, 2); ?></span>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--ink-600);" id="deliveryFeeLine">
                <span>Delivery Fee</span>
                <span>LKR <?= number_format($delivery_fee, 2); ?></span>
            </div>

            <?php if ($discount > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--success-600); font-weight: 600;">
                    <span>Discount</span>
                    <span>- LKR <?= number_format($discount, 2); ?></span>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; margin-top: 1rem; margin-bottom: 1.5rem; font-size: 1.25rem;">
                <span style="font-weight: 800; color: var(--ink-900);">Total Due</span>
                <span style="font-weight: 800; color: var(--primary-600);">LKR <?= number_format(max(0, $subtotal + $delivery_fee - $discount), 2); ?></span>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.9rem; font-size: 1.05rem; font-weight: 800; border-radius: var(--radius-md);">
                Confirm &amp; Place Order &rarr;
            </button>

            <a href="<?= e(url('orders/cart.php')); ?>" style="display: block; text-align: center; margin-top: 1rem; font-size: 0.88rem; color: var(--ink-500); text-decoration: none;">
                &larr; Return to Shopping Cart
            </a>
        </div>

    </form>
</div>

<script>
function toggleDeliveryFields(isDelivery) {
    var group = document.getElementById('deliveryAddressGroup');
    var addressInput = document.getElementById('address');
    if (group && addressInput) {
        if (isDelivery) {
            group.style.display = 'block';
            addressInput.required = true;
        } else {
            group.style.display = 'none';
            addressInput.required = false;
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
