<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Landing Portal
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

// Fetch 3 popular active dishes for the homepage showcase (Component B)
$featuredDishes = [];
if (isset($pdo)) {
    try {
        $featStmt = $pdo->query(
            "SELECT d.id, d.name, d.price, d.image_url, d.description, d.prep_time_minutes,
                    c.name AS category_name, c.icon AS category_icon,
                    COALESCE(i.quantity, 0) AS stock_quantity,
                    COALESCE(i.reorder_level, 10) AS reorder_level
             FROM dishes d
             JOIN categories c ON c.id = d.category_id
             LEFT JOIN inventory i ON i.dish_id = d.id
             WHERE d.is_active = 1
             ORDER BY d.id ASC
             LIMIT 3"
        );
        $featuredDishes = $featStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("[Index Featured Dishes] " . $e->getMessage());
    }
}

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
                    <a href="<?= e(url('menu/index.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Browse Menu &rarr;
                    </a>
                    <a href="<?= e(url('orders/cart.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        My Cart
                    </a>
                    <a href="<?= e(url('orders/tracking.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Track Order
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Three Platform Roles Overview (Assignment Section 3 Case Study) -->
<div style="margin-bottom: 3rem;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--ink-900); margin-bottom: 0.4rem;">
            Unified Cloud Kitchen Ecosystem
        </h2>
        <p style="color: var(--ink-500); font-size: 1rem;">
            Designed and integrated across three specialized operational modules.
        </p>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
        
        <!-- Component A Card (Identity & Access - Student 1: Deshan) -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="background: var(--primary-50); color: var(--primary-600); font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase;">
                        Component A (Student 1)
                    </span>
                    <span style="font-size: 1.5rem;">🛡️</span>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">
                    Identity &amp; Administration
                </h3>
                <p style="font-size: 0.9rem; color: var(--ink-500); line-height: 1.5; margin-bottom: 1.25rem;">
                    Secure authentication with brute-force throttling, BCRYPT password hashing, session hardening, user governance, and audit trails.
                </p>
            </div>
            <div>
                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/users.php')); ?>" class="btn btn-primary btn-block btn-sm">
                        Open User Governance &rarr;
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('auth/login.php')); ?>" class="btn btn-secondary btn-block btn-sm">
                        Sign In as Admin &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Component B Card (Menu & Inventory - Student 2: Chanula) -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-color: rgba(242, 107, 29, 0.4);">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="background: var(--warning-100); color: #8c5700; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase;">
                        Component B (Student 2)
                    </span>
                    <span style="font-size: 1.5rem;">🍲</span>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">
                    Menu, Catalogue &amp; Stock
                </h3>
                <p style="font-size: 0.9rem; color: var(--ink-500); line-height: 1.5; margin-bottom: 1.25rem;">
                    Filterable catalogue with whitelisted sorting, category tabs, secure image uploads, and real-time inventory engine with low-stock alerts.
                </p>
            </div>
            <div>
                <?php if ($currentUser && in_array($currentUser['role'], ['kitchen_staff', 'admin'], true)): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-primary btn-block btn-sm">
                            Manage Dishes
                        </a>
                        <a href="<?= e(url('menu/stock.php')); ?>" class="btn btn-secondary btn-block btn-sm">
                            Stock Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <a href="<?= e(url('menu/index.php')); ?>" class="btn btn-primary btn-block btn-sm" id="btn-home-explore-menu">
                        Explore Full Menu &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Component C Card (Ordering & Tracking - Student 3: Thilakshi) -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="background: var(--info-100); color: var(--info-500); font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase;">
                        Component C (Student 3)
                    </span>
                    <span style="font-size: 1.5rem;">🛒</span>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">
                    Ordering, Payment &amp; Tracking
                </h3>
                <p style="font-size: 0.9rem; color: var(--ink-500); line-height: 1.5; margin-bottom: 1.25rem;">
                    Transactional checkout, simulated multi-mode payments, kitchen order fulfillment queue, and analytics sales reports.
                </p>
            </div>
            <div>
                <a href="<?= e(url('orders/cart.php')); ?>" class="btn btn-secondary btn-block btn-sm">
                    View Shopping Cart &rarr;
                </a>
            </div>
        </div>

    </div>
</div>

<!-- Featured Chef Specials (Component B Showcase) -->
<?php if (!empty($featuredDishes)): ?>
    <div style="margin-bottom: 3.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--primary-600); letter-spacing: 0.05em;">
                    Chef's Daily Specials &bull; Fresh from the Kitchen
                </span>
                <h2 style="font-size: 1.85rem; font-weight: 800; color: var(--ink-900); margin-top: 0.2rem;">
                    Popular Customer Favorites
                </h2>
            </div>
            <a href="<?= e(url('menu/index.php')); ?>" class="btn btn-outline btn-sm">
                View All Dishes &rarr;
            </a>
        </div>

        <div class="menu-grid" style="margin-bottom: 0;">
            <?php foreach ($featuredDishes as $fd): 
                $fQty = (int)$fd['stock_quantity'];
                $fReorder = (int)$fd['reorder_level'];
                if ($fQty <= 0) {
                    $fStatus = '<span class="stock-badge stock-badge-out"><span class="dot"></span>Sold Out</span>';
                } elseif ($fQty <= $fReorder) {
                    $fStatus = '<span class="stock-badge stock-badge-low"><span class="dot"></span>Only ' . $fQty . ' Left!</span>';
                } else {
                    $fStatus = '<span class="stock-badge stock-badge-in"><span class="dot"></span>In Stock (' . $fQty . ')</span>';
                }
                $fImg = !empty($fd['image_url']) ? url('public/' . ltrim($fd['image_url'], '/')) : url('public/uploads/chicken_kottu.jpg');
            ?>
                <article class="dish-card">
                    <div class="dish-media">
                        <img src="<?= e($fImg); ?>" alt="<?= e($fd['name']); ?>" loading="lazy" onerror="this.src='<?= e(url('public/uploads/chicken_kottu.jpg')); ?>'">
                        <div class="dish-stock-overlay">
                            <?= $fStatus; ?>
                        </div>
                        <div class="dish-prep-overlay">
                            ⏱️ <?= e((string)$fd['prep_time_minutes']); ?> mins
                        </div>
                    </div>
                    <div class="dish-content">
                        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--primary-600); margin-bottom: 0.25rem;">
                            <?= e($fd['category_icon'] . ' ' . $fd['category_name']); ?>
                        </div>
                        <h3 class="dish-title">
                            <a href="<?= e(url('menu/detail.php?id=' . $fd['id'])); ?>">
                                <?= e($fd['name']); ?>
                            </a>
                        </h3>
                        <p class="dish-description">
                            <?= e($fd['description']); ?>
                        </p>
                        <div class="dish-footer">
                            <div class="dish-price-block">
                                <span class="dish-price-currency">Price</span>
                                <span class="dish-price-value"><?= e(format_price($fd['price'])); ?></span>
                            </div>
                            <a href="<?= e(url('menu/detail.php?id=' . $fd['id'])); ?>" class="btn btn-secondary btn-sm">
                                View Details &rarr;
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
