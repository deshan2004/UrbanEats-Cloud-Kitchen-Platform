<?php
// orders/checkout.php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_login();

// Fetch active CSRF Token
$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - UrbanEats</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container">
        <h1>Checkout</h1>

        <form action="process_checkout.php" method="POST" class="checkout-layout">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="checkout-main">
                <!-- 1. Delivery Details -->
                <section class="checkout-section">
                    <h2>1. Delivery details</h2>
                    <div class="toggle-switch">
                        <label><input type="radio" name="order_type" value="delivery" checked> Delivery</label>
                        <label><input type="radio" name="order_type" value="pickup"> Pickup</label>
                    </div>

                    <div class="form-group">
                        <label>Delivery address</label>
                        <input type="text" name="address" required placeholder="No. 24, Galle Road, Colombo 03">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone number</label>
                            <input type="tel" name="phone" required placeholder="07X XXX XXXX">
                        </div>
                        <div class="form-group">
                            <label>Delivery notes (optional)</label>
                            <input type="text" name="notes" placeholder="e.g. Call at the gate">
                        </div>
                    </div>
                </section>

                <!-- 2. Payment Method -->
                <section class="checkout-section">
                    <h2>2. Payment method</h2>
                    <label class="payment-option selected">
                        <input type="radio" name="payment_method" value="cod" checked>
                        <div>
                            <strong>Cash on delivery</strong>
                            <p>Pay the rider when your order arrives</p>
                        </div>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="card">
                        <div>
                            <strong>Card</strong>
                            <p>Recorded only - no real payment is taken</p>
                        </div>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="bank">
                        <div>
                            <strong>Bank transfer</strong>
                            <p>Recorded only - no real payment is taken</p>
                        </div>
                    </label>
                </section>
            </div>

            <!-- Side Summary -->
            <div class="checkout-summary">
                <h3>Your order</h3>
                <div class="order-item-mini">
                    <div><strong>Chicken Kottu</strong><br><small>Qty 2</small></div>
                    <span>LKR 2,900</span>
                </div>
                <hr>
                <div class="summary-row"><span>Total</span> <strong>LKR 3,958</strong></div>
                <button type="submit" class="btn-primary full-width">Place order</button>
                <a href="cart.php" class="btn-link">Back to cart</a>
            </div>
        </form>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>