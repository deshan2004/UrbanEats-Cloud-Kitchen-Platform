<?php
// orders/cart.php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login(); // Ensure user is logged in

// Initialize session cart if empty
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Example Cart Calculation Logic
$subtotal = 0.00;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$delivery_fee = 250.00;
$discount = 412.00; // Example coupon discount
$total = max(0, $subtotal + $delivery_fee - $discount);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Cart - UrbanEats</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container">
        <h1>Your Cart</h1>
        <p class="subtitle">3 items from UrbanEats Cloud Kitchen</p>

        <div class="cart-layout">
            <!-- Cart Items List -->
            <div class="cart-items">
                <div class="cart-item">
                    <div class="item-img-placeholder">🍲</div>
                    <div class="item-details">
                        <h3>Chicken Kottu</h3>
                        <p class="price">LKR 1,450 each</p>
                    </div>
                    <div class="quantity-controls">
                        <button>-</button>
                        <span>2</span>
                        <button>+</button>
                    </div>
                    <span class="item-total">LKR 2,900</span>
                    <button class="btn-delete">🗑️</button>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="order-summary-card">
                <h2>Order summary</h2>
                <div class="summary-row"><span>Subtotal</span> <span>LKR <?= number_format($subtotal, 2) ?></span></div>
                <div class="summary-row"><span>Delivery fee</span> <span>LKR <?= number_format($delivery_fee, 2) ?></span></div>
                <div class="summary-row discount"><span>Discount (UE10)</span> <span>- LKR <?= number_format($discount, 2) ?></span></div>
                <hr>
                <div class="summary-row total"><strong>Total</strong> <strong>LKR <?= number_format($total, 2) ?></strong></div>

                <form class="coupon-form" method="POST" action="apply_coupon.php">
                    <input type="text" name="coupon" placeholder="Coupon code">
                    <button type="submit" class="btn-outline">Apply</button>
                </form>

                <a href="checkout.php" class="btn-primary full-width">Proceed to checkout</a>
                <p class="disclaimer">Prices and availability are checked again when you place your order.</p>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>