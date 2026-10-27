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

// Retrieve user's contact information and saved delivery address from database if available
if (!empty($currentUser['id']) && $pdo) {
    try {
        $uStmt = $pdo->prepare('SELECT phone, address FROM users WHERE id = :id LIMIT 1');
        $uStmt->execute([':id' => $currentUser['id']]);
        $uData = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uData) {
            $currentUser['phone'] = $uData['phone'] ?? '';
            $currentUser['address'] = $uData['address'] ?? '';
        }
    } catch (PDOException $e) {
        error_log('[Checkout User Fetch Error] ' . $e->getMessage());
    }
}

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
    if ($orderType === 'pickup' && $paymentMethod === 'cod') {
        $errors['payment_method'] = 'Cash on Delivery (COD) is not available for Kitchen Pickup orders. Please select Credit / Debit Card or Direct Bank Transfer.';
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

            // 3. Insert into `order_items` and deduct live inventory using Component B inventory engine
            $stmtItem = $pdo->prepare(
                "INSERT INTO order_items (order_id, dish_id, quantity, price_at_purchase)
                 VALUES (?, ?, ?, ?)"
            );
            $stmtDeductDishStock = $pdo->prepare(
                "UPDATE dishes SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE dish_id = ?"
            );

            foreach ($orderItems as $oi) {
                $stmtItem->execute([
                    $orderId,
                    $oi['dish_id'],
                    $oi['quantity'],
                    $oi['price'],
                ]);

                // Call Component B transaction-safe stock reduction (updates inventory & logs stock_movement)
                if (function_exists('reduce_stock')) {
                    $reduced = reduce_stock($pdo, (int)$oi['dish_id'], (int)$oi['quantity'], "Order #{$orderCode}");
                    if (!$reduced) {
                        throw new RuntimeException("Insufficient stock available for '{$oi['name']}'. Order cannot be fulfilled.");
                    }
                }

                // Mirror stock_quantity on dishes table
                $stmtDeductDishStock->execute([
                    $oi['quantity'],
                    $oi['dish_id'],
                ]);
            }

            // 4. Record payment in `payments` table
            $paymentStatus = ($paymentMethod === 'cod') ? 'Pending' : 'Completed';
            $txRef = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $stmtPayment = $pdo->prepare(
                "INSERT INTO payments (order_id, amount, payment_method, payment_status, transaction_reference, paid_at, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmtPayment->execute([
                $orderId,
                $totalAmount,
                $paymentMethod,
                $paymentStatus,
                $txRef,
            ]);

            // 5. Initial status in `order_status_history`
            $stmtHistory = $pdo->prepare(
                "INSERT INTO order_status_history (order_id, status, changed_by, changed_at)
                 VALUES (?, 'Placed', ?, NOW())"
            );
            $stmtHistory->execute([$orderId, (int)$currentUser['id']]);

            // 6. System audit log
            if (function_exists('log_action')) {
                log_action(
                    $pdo,
                    (int)$currentUser['id'],
                    'ORDER_PLACED',
                    "Order #{$orderCode} placed successfully (Total: LKR " . number_format($totalAmount, 2) . ", Payment: " . strtoupper($paymentMethod) . ")"
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
            Transactional Checkout
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
                    <div class="address-header-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label for="address" class="form-label" style="margin-bottom: 0; font-weight: 700;">Delivery Address <span style="color: var(--danger-500);">*</span></label>
                        <button type="button" 
                                id="btn-detect-location" 
                                class="btn-detect-location" 
                                title="Auto-detect current delivery location">
                            <span class="btn-icon">📍</span>
                            <span class="btn-label">Auto-detect location</span>
                        </button>
                    </div>
                    <textarea name="address" id="address" class="form-control <?= !empty($errors['address']) ? 'is-invalid' : ''; ?>" rows="2" placeholder="e.g. No. 24, Galle Road, Colombo 03" required><?= e($_POST['address'] ?? ($currentUser['address'] ?? '')); ?></textarea>
                    <div id="location-status" class="location-status" style="display: none;"></div>
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
                    <label id="paymentMethodCodLabel" style="border: 2px solid var(--primary-500); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; background: var(--primary-50); transition: all 0.2s ease;">
                        <input type="radio" name="payment_method" value="cod" id="paymentMethodCod" checked onchange="updatePaymentMethodStyles()">
                        <div>
                            <strong style="display: block; color: var(--ink-900);">💵 Cash on Delivery (COD)</strong>
                            <small style="color: var(--ink-500);">Pay cash to our delivery rider upon receipt</small>
                        </div>
                    </label>

                    <label id="paymentMethodCardLabel" style="border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s ease;">
                        <input type="radio" name="payment_method" value="card" id="paymentMethodCard" onchange="updatePaymentMethodStyles()">
                        <div>
                            <strong style="display: block; color: var(--ink-900);">💳 Credit / Debit Card (Instant Sandbox)</strong>
                            <small style="color: var(--ink-500);">Prepaid checkout — immediately queued for kitchen preparation</small>
                        </div>
                    </label>

                    <label id="paymentMethodBankLabel" style="border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s ease;">
                        <input type="radio" name="payment_method" value="bank" id="paymentMethodBank" onchange="updatePaymentMethodStyles()">
                        <div>
                            <strong style="display: block; color: var(--ink-900);">🏦 Direct Bank Transfer</strong>
                            <small style="color: var(--ink-500);">Instant electronic fund transfer verification</small>
                        </div>
                    </label>
                </div>

                <div id="pickupPaymentNotice" style="display: none; margin-top: 1rem; padding: 0.85rem 1.1rem; background: rgba(254, 243, 199, 0.75); border: 1px solid #F59E0B; border-radius: var(--radius-md); font-size: 0.86rem; color: #92400E; line-height: 1.5;">
                    ℹ️ <strong>Kitchen Pickup Notice:</strong> Cash on Delivery (COD) is disabled because this order will be collected directly from the kitchen counter. Please select <strong>Credit / Debit Card</strong> or <strong>Direct Bank Transfer</strong> so our kitchen staff can start preparation.
                </div>

                <?php if (!empty($errors['payment_method'])): ?>
                    <div class="field-error" style="margin-top: 0.75rem; color: var(--danger-500); font-weight: 600; font-size: 0.88rem;"><?= e($errors['payment_method']); ?></div>
                <?php endif; ?>
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
                <span style="font-weight: 800; color: var(--primary-600);" id="totalDueAmount">LKR <?= number_format(max(0, $subtotal + $delivery_fee - $discount), 2); ?></span>
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
const subtotalVal = <?= (float)$subtotal; ?>;
const discountVal = <?= (float)$discount; ?>;
const deliveryFeeVal = 250.00;

function updatePaymentMethodStyles() {
    const codRadio = document.getElementById('paymentMethodCod');
    const cardRadio = document.getElementById('paymentMethodCard');
    const bankRadio = document.getElementById('paymentMethodBank');

    const codLabel = document.getElementById('paymentMethodCodLabel');
    const cardLabel = document.getElementById('paymentMethodCardLabel');
    const bankLabel = document.getElementById('paymentMethodBankLabel');

    const resetStyle = (el) => {
        if (!el) return;
        el.style.borderColor = 'var(--border-default)';
        el.style.background = 'transparent';
        el.style.borderWidth = '1px';
    };
    const activeStyle = (el) => {
        if (!el) return;
        el.style.borderColor = 'var(--primary-500)';
        el.style.background = 'var(--primary-50)';
        el.style.borderWidth = '2px';
    };

    [codLabel, cardLabel, bankLabel].forEach(resetStyle);

    if (codRadio && codRadio.checked && codLabel.style.display !== 'none') {
        activeStyle(codLabel);
    } else if (cardRadio && cardRadio.checked) {
        activeStyle(cardLabel);
    } else if (bankRadio && bankRadio.checked) {
        activeStyle(bankLabel);
    }
}

function toggleDeliveryFields(isDelivery) {
    var group = document.getElementById('deliveryAddressGroup');
    var addressInput = document.getElementById('address');
    var deliveryFeeLine = document.getElementById('deliveryFeeLine');
    var totalDueSpan = document.getElementById('totalDueAmount');
    var codLabel = document.getElementById('paymentMethodCodLabel');
    var codRadio = document.getElementById('paymentMethodCod');
    var cardRadio = document.getElementById('paymentMethodCard');
    var pickupNotice = document.getElementById('pickupPaymentNotice');

    if (isDelivery) {
        if (group) group.style.display = 'block';
        if (addressInput) addressInput.required = true;
        if (deliveryFeeLine) deliveryFeeLine.style.display = 'flex';
        if (codLabel) codLabel.style.display = 'flex';
        if (pickupNotice) pickupNotice.style.display = 'none';

        var newTotal = Math.max(0, subtotalVal + deliveryFeeVal - discountVal);
        if (totalDueSpan) {
            totalDueSpan.textContent = 'LKR ' + newTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    } else {
        if (group) group.style.display = 'none';
        if (addressInput) addressInput.required = false;
        if (deliveryFeeLine) deliveryFeeLine.style.display = 'none';
        if (codLabel) codLabel.style.display = 'none';
        if (pickupNotice) pickupNotice.style.display = 'block';

        // When choosing pickup, automatically switch away from Cash on Delivery to Card
        if (codRadio && codRadio.checked && cardRadio) {
            cardRadio.checked = true;
        }

        var newTotal = Math.max(0, subtotalVal - discountVal);
        if (totalDueSpan) {
            totalDueSpan.textContent = 'LKR ' + newTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    updatePaymentMethodStyles();
}

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

    // Network / IP-based location fallback for devices where browser GPS is unavailable or blocked
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
            setLocationStatus('📡 Acquiring GPS coordinates...', 'info');

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

                        // Primary reverse geocoder: OpenStreetMap Nominatim
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

                        // Secondary reverse geocoder: BigDataCloud
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

                        // Tertiary fallback: Lat/Long
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
                        enableHighAccuracy: true,
                        timeout: 7000,
                        maximumAge: 60000
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
