<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Landing Portal (Component A)
 * 
 * Provides an entry portal for the three platform user personas:
 * Customer, Kitchen Staff, and Administrator.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$currentUser = current_user();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner (Figma warm theme) -->
<div style="background-color: var(--primary-500); border-radius: var(--radius-xl); padding: 3.5rem 3rem; color: #FFFFFF; margin-bottom: 2.5rem; text-align: center;">
    <div style="max-width: 760px; margin: 0 auto;">
        <span style="background: rgba(255, 255, 255, 0.2); color: #FFFFFF; font-size: 0.82rem; font-weight: 700; padding: 0.35rem 0.95rem; border-radius: var(--radius-full); text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 1.25rem;">
            UrbanEats &bull; Cloud Kitchen Platform
        </span>
        <h1 style="font-size: 2.75rem; font-weight: 800; color: #FFFFFF; margin-bottom: 1rem; line-height: 1.15; letter-spacing: -0.02em;">
            Fresh food. Fast.<br>Made to order.
        </h1>
        <p style="font-size: 1.15rem; color: rgba(255, 255, 255, 0.95); line-height: 1.6; margin-bottom: 2rem;">
            Order from our cloud kitchen, follow your meal in real time and pay your way: cash on delivery, card or bank transfer.
        </p>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <?php if (!$currentUser): ?>
                <a href="<?= e(url('auth/login.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem; border-radius: var(--radius-md);">
                    Sign In &rarr;
                </a>
                <a href="<?= e(url('auth/register.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem; border-radius: var(--radius-md);">
                    Create Account
                </a>
            <?php else: ?>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/users.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Go to User Management &rarr;
                    </a>
                    <a href="<?= e(url('admin/dashboard.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Admin Dashboard
                    </a>
                <?php elseif ($currentUser['role'] === 'kitchen_staff'): ?>
                    <a href="<?= e(url('orders/kitchen_queue.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Kitchen Order Queue &rarr;
                    </a>
                    <a href="<?= e(url('auth/profile.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Staff Profile
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('orders/cart.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Order Food (Cart) &rarr;
                    </a>
                    <a href="<?= e(url('orders/tracking.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Track Order
                    </a>
                    <a href="<?= e(url('orders/history.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        My Orders
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- Fresh Kitchen Dishes Catalogue Section -->
<div id="dishes-section" style="margin-bottom: 3.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
                Fresh From Our Kitchen
            </span>
            <h2 style="font-size: 1.85rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">
                Today's Fresh Specials
            </h2>
            <p style="color: var(--ink-500); font-size: 0.95rem;">
                Prepared to order using locally sourced Sri Lankan ingredients.
            </p>
        </div>

        <a href="<?= e(url('orders/cart.php')); ?>" class="btn btn-primary" style="padding: 0.65rem 1.4rem;">
            🛒 View Cart (<?= e((string)(count($_SESSION['cart'] ?? []))); ?>)
        </a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
        <?php
        $dishesList = [];
        if ($pdo) {
            $dishQuery = $pdo->query("SELECT d.*, c.name AS category_name FROM dishes d LEFT JOIN categories c ON d.category_id = c.category_id WHERE d.is_active = 1 ORDER BY d.dish_id ASC");
            $dishesList = $dishQuery->fetchAll();
        }
        ?>

        <?php if (empty($dishesList)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 2rem;">
                <p style="color: var(--ink-500); margin: 0;">No dishes currently loaded.</p>
            </div>
        <?php else: ?>
            <?php foreach ($dishesList as $dish): ?>
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-radius: var(--radius-lg); transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; padding: 1.5rem;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                            <span style="background: var(--primary-50); color: var(--primary-600); font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full); text-transform: uppercase;">
                                <?= e($dish['category_name'] ?? 'Special'); ?>
                            </span>
                            <span style="font-size: 0.8rem; color: var(--success-600); font-weight: 700;">
                                In Stock (<?= (int)$dish['stock_quantity']; ?>)
                            </span>
                        </div>

                        <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--ink-900); margin-bottom: 0.5rem;">
                            <?= e($dish['name']); ?>
                        </h3>
                        <p style="font-size: 0.88rem; color: var(--ink-500); line-height: 1.5; margin-bottom: 1.25rem;">
                            <?= e($dish['description'] ?? ''); ?>
                        </p>
                    </div>

                    <div style="border-top: 1px solid var(--border-default); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-size: 0.75rem; color: var(--ink-400); display: block;">Price</span>
                            <strong style="font-size: 1.25rem; font-weight: 800; color: var(--ink-900);">
                                LKR <?= number_format((float)$dish['price'], 0); ?>
                            </strong>
                        </div>
                        <a href="<?= e(url('orders/cart.php?action=add&dish_id=' . (int)$dish['dish_id'])); ?>" class="btn btn-primary btn-sm" style="font-weight: 700; padding: 0.5rem 1rem;">
                            + Add to Cart
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
