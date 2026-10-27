<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Shopping Cart & Order Preparation
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_functions.php';

$currentUser = current_user();

// Initialize session cart as clean empty array if not set
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions (increase, decrease, remove, clear, add)
$action = $_GET['action'] ?? null;
$dishId = isset($_GET['dish_id']) ? (int)$_GET['dish_id'] : 0;

if ($action === 'increase' && $dishId > 0 && isset($_SESSION['cart'][$dishId])) {
    $_SESSION['cart'][$dishId]['quantity']++;
    redirect(url('orders/cart.php'));
} elseif ($action === 'decrease' && $dishId > 0 && isset($_SESSION['cart'][$dishId])) {
    if ($_SESSION['cart'][$dishId]['quantity'] > 1) {
        $_SESSION['cart'][$dishId]['quantity']--;
    } else {
        unset($_SESSION['cart'][$dishId]);
    }
    redirect(url('orders/cart.php'));
} elseif ($action === 'remove' && $dishId > 0 && isset($_SESSION['cart'][$dishId])) {
    unset($_SESSION['cart'][$dishId]);
    set_flash('info', 'Item removed from cart.');
    redirect(url('orders/cart.php'));
} elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
    unset($_SESSION['applied_coupon']);
    set_flash('info', 'Cart cleared.');
    redirect(url('orders/cart.php'));
} elseif ($action === 'add' && $dishId > 0) {
    // Add dish from DB
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM dishes WHERE dish_id = ? AND is_active = 1");
        $stmt->execute([$dishId]);
        $dish = $stmt->fetch();
        if ($dish) {
            if (isset($_SESSION['cart'][$dishId])) {
                $_SESSION['cart'][$dishId]['quantity']++;
            } else {
                $_SESSION['cart'][$dishId] = [
                    'dish_id' => (int)$dish['dish_id'],
                    'name' => $dish['name'],
                    'price' => (float)$dish['price'],
                    'quantity' => 1,
                    'image' => $dish['image_url'] ?? '🍲'
                ];
            }
            set_flash('success', e($dish['name']) . ' added to cart.');
        }
    }
    redirect(url('orders/cart.php'));
}

// Handle Coupon Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $couponCode = trim($_POST['coupon_code'] ?? '');
    
    // Calculate current subtotal
    $currentSubtotal = 0.0;
    foreach ($_SESSION['cart'] as $item) {
        $currentSubtotal += (float)$item['price'] * (int)$item['quantity'];
    }

    if ($pdo && $couponCode !== '') {
        [$isValid, $result] = validate_coupon($pdo, $couponCode, $currentSubtotal);
        if ($isValid && is_array($result)) {
            $_SESSION['applied_coupon'] = $result;
            set_flash('success', "Coupon '{$result['code']}' applied successfully!");
        } else {
            unset($_SESSION['applied_coupon']);
            set_flash('error', is_string($result) ? $result : 'Invalid coupon code.');
        }
    } else {
        unset($_SESSION['applied_coupon']);
        set_flash('info', 'Coupon removed.');
    }
    redirect(url('orders/cart.php'));
}

// Calculate totals
$subtotal = 0.00;
$totalItems = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
    $totalItems += (int)$item['quantity'];
}

$delivery_fee = ($subtotal > 0) ? 250.00 : 0.00;
$discount = 0.00;
if (isset($_SESSION['applied_coupon']) && $subtotal > 0 && $pdo) {
    // Revalidate coupon
    [$valid, $c] = validate_coupon($pdo, $_SESSION['applied_coupon']['code'], $subtotal);
    if ($valid && is_array($c)) {
        $discount = (float)$c['discount_amount'];
        $_SESSION['applied_coupon'] = $c;
    } else {
        unset($_SESSION['applied_coupon']);
    }
}

$total = max(0.00, $subtotal + $delivery_fee - $discount);

$pageTitle = 'Your Cart';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
    <div style="margin-bottom: 2rem;">
        <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
            Ordering Workflow
        </span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">
            Your Shopping Cart
        </h1>
        <p style="color: var(--ink-500); font-size: 1rem;">
            <?= e((string)$totalItems); ?> item<?= $totalItems === 1 ? '' : 's'; ?> in your UrbanEats order
        </p>
    </div>

    <?php if (empty($_SESSION['cart'])): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🛒</div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">Your cart is currently empty</h2>
            <p style="color: var(--ink-500); margin-bottom: 2rem;">Explore our freshly cooked cloud kitchen dishes and add them to your order.</p>
            <a href="<?= e(url('index.php')); ?>" class="btn btn-primary" style="padding: 0.75rem 2rem;">Browse Menu</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
            
            <!-- Items List -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                    <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--ink-900); margin: 0;">Order Items</h2>
                    <a href="<?= e(url('orders/cart.php?action=clear')); ?>" style="color: var(--danger-600); font-size: 0.88rem; font-weight: 600; text-decoration: none;" data-confirm="Are you sure you want to clear all items from your cart?">
                        Clear Cart
                    </a>
                </div>

                <div style="divide-y: 1px solid var(--border-default);">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <?php $itemTotal = (float)$item['price'] * (int)$item['quantity']; ?>
                        <?php 
                        $imgVal = $item['image'] ?? '';
                        $isImagePath = (is_string($imgVal) && (strpos($imgVal, '/') !== false || preg_match('/\.(jpg|jpeg|png|webp)$/i', $imgVal)));
                        ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-default); gap: 1rem; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 1rem; min-width: 200px; flex: 1;">
                                <div style="width: 56px; height: 56px; border-radius: 12px; background: var(--primary-50); display: flex; align-items: center; justify-content: center; font-size: 1.75rem; overflow: hidden; flex-shrink: 0;">
                                    <?php if ($isImagePath): ?>
                                        <img src="<?= e(url('public/' . ltrim($imgVal, '/'))); ?>" alt="<?= e($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?= e($imgVal ?: '🍲'); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--ink-900); margin: 0 0 0.25rem 0;">
                                        <?= e($item['name']); ?>
                                    </h3>
                                    <span style="color: var(--ink-500); font-size: 0.9rem;">
                                        LKR <?= number_format((float)$item['price'], 2); ?> each
                                    </span>
                                </div>
                            </div>

                            <!-- Quantity Controls -->
                            <div style="display: flex; align-items: center; gap: 0.6rem;">
                                <a href="<?= e(url('orders/cart.php?action=decrease&dish_id=' . (int)$item['dish_id'])); ?>" class="btn btn-secondary btn-sm" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; border-radius: var(--radius-sm);" title="Decrease quantity">-</a>
                                <span style="font-weight: 700; min-width: 24px; text-align: center; color: var(--ink-900);">
                                    <?= (int)$item['quantity']; ?>
                                </span>
                                <a href="<?= e(url('orders/cart.php?action=increase&dish_id=' . (int)$item['dish_id'])); ?>" class="btn btn-secondary btn-sm" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; border-radius: var(--radius-sm);" title="Increase quantity">+</a>
                            </div>

                            <!-- Line Total & Delete -->
                            <div style="display: flex; align-items: center; gap: 1.25rem;">
                                <span style="font-weight: 800; color: var(--ink-900); font-size: 1.05rem; min-width: 100px; text-align: right;">
                                    LKR <?= number_format($itemTotal, 2); ?>
                                </span>
                                <a href="<?= e(url('orders/cart.php?action=remove&dish_id=' . (int)$item['dish_id'])); ?>" style="color: var(--ink-400); font-size: 1.1rem; text-decoration: none; cursor: pointer; padding: 4px;" title="Remove item" data-confirm="Are you sure you want to remove this item from your cart?">
                                    🗑️
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Add More Dishes Quick Shelf -->
                <div style="padding: 1.25rem 1.5rem; background: var(--bg-surface-alt); border-top: 1px dashed var(--border-default);">
                    <h4 style="font-size: 0.9rem; font-weight: 700; color: var(--ink-700); margin: 0 0 0.75rem 0; text-transform: uppercase;">Quick Add Available Dishes:</h4>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <?php
                        if ($pdo) {
                            $dishesStmt = $pdo->query("SELECT dish_id, name, price FROM dishes WHERE is_active = 1 LIMIT 4");
                            while ($d = $dishesStmt->fetch()) {
                                echo '<a href="' . e(url('orders/cart.php?action=add&dish_id=' . $d['dish_id'])) . '" class="btn btn-secondary btn-sm" style="font-size: 0.82rem;">+ ' . e($d['name']) . ' (LKR ' . number_format((float)$d['price'], 0) . ')</a>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Order Summary Card -->
            <div class="card" style="padding: 1.75rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--ink-900); margin-bottom: 1.25rem;">
                    Order Summary
                </h2>

                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--ink-600); font-size: 0.95rem;">
                    <span>Subtotal</span>
                    <span style="font-weight: 600; color: var(--ink-900);">LKR <?= number_format($subtotal, 2); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--ink-600); font-size: 0.95rem;">
                    <span>Delivery fee</span>
                    <span style="font-weight: 600; color: var(--ink-900);">LKR <?= number_format($delivery_fee, 2); ?></span>
                </div>

                <?php if ($discount > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--success-600); font-size: 0.95rem; font-weight: 600;">
                        <span>Coupon Discount (<?= e($_SESSION['applied_coupon']['code']); ?>)</span>
                        <span>- LKR <?= number_format($discount, 2); ?></span>
                    </div>
                <?php endif; ?>

                <hr style="border: 0; border-top: 1px solid var(--border-default); margin: 1rem 0;">

                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 1.2rem;">
                    <span style="font-weight: 800; color: var(--ink-900);">Total</span>
                    <span style="font-weight: 800; color: var(--primary-600);">LKR <?= number_format($total, 2); ?></span>
                </div>

                <!-- Coupon Form -->
                <form method="POST" action="<?= e(url('orders/cart.php')); ?>" style="margin-bottom: 1.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--ink-700); margin-bottom: 0.4rem;">Promo Code / Coupon</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="coupon_code" class="form-control" placeholder="e.g. UE10 or WELCOME500" value="<?= e($_SESSION['applied_coupon']['code'] ?? ''); ?>" style="text-transform: uppercase;">
                        <button type="submit" name="apply_coupon" class="btn btn-secondary btn-sm" style="font-weight: 700;">Apply</button>
                    </div>
                    <small style="color: var(--ink-400); font-size: 0.78rem; display: block; margin-top: 0.35rem;">
                        Available: <code>UE10</code> (10% off) or <code>WELCOME500</code>
                    </small>
                </form>

                <a href="<?= e(url('orders/checkout.php')); ?>" class="btn btn-primary btn-block" style="padding: 0.85rem; font-size: 1.05rem; font-weight: 700; text-align: center; border-radius: var(--radius-md);">
                    Proceed to Checkout &rarr;
                </a>

                <p style="font-size: 0.78rem; color: var(--ink-400); text-align: center; margin-top: 1rem; line-height: 1.4;">
                    🔒 Prices and kitchen stock are confirmed atomically during transactional checkout.
                </p>
            </div>

        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
