<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Dish Detail & Availability Presentation
 * 
 * Displays:
 * - High-resolution dish imagery and culinary presentation
 * - Real-time inventory availability status (In Stock, Low Stock, Sold Out)
 * - Nutritional details, dietary tags (🌱 Vegetarian, 🌶️ Spicy), and prep time
 * - Safe add-to-cart action with client/server stock quantity caps
 * - Staff administrative shortcuts (edit dish, adjust inventory)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

$dishId = (int)($_GET['id'] ?? 0);

if ($dishId <= 0) {
    set_flash('error', 'Invalid dish identifier specified.');
    redirect(url('menu/index.php'));
}

// Fetch dish details with category and live inventory
try {
    $stmt = $pdo->prepare(
        "SELECT 
            d.id, d.name, d.slug, d.description, d.price, d.image_url,
            d.is_vegetarian, d.is_spicy, d.prep_time_minutes, d.calories, d.is_active,
            d.created_at, d.updated_at,
            c.id AS category_id, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
            COALESCE(i.quantity, 0) AS stock_quantity,
            COALESCE(i.reorder_level, 10) AS reorder_level,
            i.last_restocked_at
         FROM dishes d
         JOIN categories c ON c.id = d.category_id
         LEFT JOIN inventory i ON i.dish_id = d.id
         WHERE d.id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $dishId]);
    $dish = $stmt->fetch();

    if (!$dish) {
        set_flash('error', 'The requested dish does not exist in our kitchen catalogue.');
        redirect(url('menu/index.php'));
    }
} catch (PDOException $e) {
    error_log("[Dish Detail Error] Failed to fetch dish: " . $e->getMessage());
    set_flash('error', 'A database error occurred while loading dish information.');
    redirect(url('menu/index.php'));
}

$currentUser = current_user();
$isStaff = $currentUser && in_array($currentUser['role'], ['kitchen_staff', 'admin'], true);

$stockQty = (int)$dish['stock_quantity'];
$reorderLevel = (int)$dish['reorder_level'];
$isAvailable = ($stockQty > 0) && ((int)$dish['is_active'] === 1);

// Handle POST: Add to Session Cart (Component C integration contract)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(url('menu/detail.php?id=' . $dishId));
    }

    $qtyToAdd = max(1, (int)($_POST['quantity'] ?? 1));

    if (!$isAvailable) {
        set_flash('error', 'Sorry, this dish is currently sold out and cannot be added to the cart.');
        redirect(url('menu/detail.php?id=' . $dishId));
    }

    if ($qtyToAdd > $stockQty) {
        set_flash('warning', 'Only ' . $stockQty . ' portions available in the kitchen right now.');
        $qtyToAdd = $stockQty;
    }

    // Initialize session cart if needed
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Check if dish is already in cart
    if (isset($_SESSION['cart'][$dishId])) {
        $newCartQty = $_SESSION['cart'][$dishId]['quantity'] + $qtyToAdd;
        if ($newCartQty > $stockQty) {
            $newCartQty = $stockQty;
            set_flash('warning', 'Cart updated to maximum available kitchen stock (' . $stockQty . ' portions).');
        } else {
            set_flash('success', 'Updated quantity for ' . $dish['name'] . ' in your cart.');
        }
        $_SESSION['cart'][$dishId]['quantity'] = $newCartQty;
    } else {
        $_SESSION['cart'][$dishId] = [
            'id'       => (int)$dish['id'],
            'name'     => $dish['name'],
            'price'    => (float)$dish['price'],
            'image'    => $dish['image_url'],
            'quantity' => $qtyToAdd,
        ];
        set_flash('success', 'Added ' . $qtyToAdd . ' &times; ' . $dish['name'] . ' to your cart!');
    }

    redirect(url('menu/detail.php?id=' . $dishId));
}

$pageTitle = $dish['name'];
$imgSrc = !empty($dish['image_url']) ? url('public/' . ltrim($dish['image_url'], '/')) : url('public/images/default_dish.jpg');

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumbs -->
<div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; color: var(--ink-500); margin-bottom: 2rem;">
    <a href="<?= e(url('index.php')); ?>" style="color: var(--ink-500);">Home</a>
    <span>&rsaquo;</span>
    <a href="<?= e(url('menu/index.php')); ?>" style="color: var(--ink-500);">Menu</a>
    <span>&rsaquo;</span>
    <a href="<?= e(url('menu/index.php?cat=' . $dish['category_slug'])); ?>" style="color: var(--ink-500);"><?= e($dish['category_name']); ?></a>
    <span>&rsaquo;</span>
    <span style="color: var(--ink-900); font-weight: 600;"><?= e($dish['name']); ?></span>
</div>

<!-- Two-Column Product Presentation Layout -->
<div class="dish-detail-layout">
    <!-- Left Column: Visual Media Presentation -->
    <div>
        <div class="dish-detail-media-card">
            <img 
                src="<?= e($imgSrc); ?>" 
                alt="<?= e($dish['name']); ?>" 
                onerror="this.src='<?= e(url('public/uploads/chicken_kottu.jpg')); ?>'"
            >
            
            <!-- Badges Overlay -->
            <div class="dish-badges-overlay" style="top: 16px; left: 16px;">
                <span class="badge-tag badge-category">
                    <?= e($dish['category_icon'] . ' ' . $dish['category_name']); ?>
                </span>
                <?php if ((int)$dish['is_vegetarian'] === 1): ?>
                    <span class="badge-tag badge-veg">🌱 Vegetarian</span>
                <?php endif; ?>
                <?php if ((int)$dish['is_spicy'] === 1): ?>
                    <span class="badge-tag badge-spicy">🌶️ Spicy</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kitchen Staff Admin Strip -->
        <?php if ($isStaff): ?>
            <div class="card" style="margin-top: 1.5rem; padding: 1.25rem 1.5rem; background: rgba(255, 241, 231, 0.6); border-color: rgba(242, 107, 29, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div>
                        <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--primary-600); display: block;">
                            Kitchen Staff Controls
                        </span>
                        <span style="font-size: 0.88rem; color: var(--ink-700);">
                            Stock: <strong><?= e((string)$stockQty); ?></strong> &bull; Reorder Level: <strong><?= e((string)$reorderLevel); ?></strong>
                        </span>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="<?= e(url('menu/manage_dishes.php?edit=' . $dish['id'])); ?>" class="btn btn-secondary btn-sm" id="btn-staff-edit-dish">
                            Edit Dish
                        </a>
                        <a href="<?= e(url('menu/stock.php?dish_id=' . $dish['id'])); ?>" class="btn btn-primary btn-sm" id="btn-staff-adjust-stock">
                            Adjust Stock
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Dish Information & Ordering Controls -->
    <div class="dish-detail-info">
        <div>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin-bottom: 0.5rem; line-height: 1.2;">
                <?= e($dish['name']); ?>
            </h1>

            <!-- Price & Availability Callout -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span style="font-size: 0.82rem; font-weight: 700; color: var(--ink-500); text-transform: uppercase; display: block;">
                        Price in LKR
                    </span>
                    <span style="font-size: 2.25rem; font-weight: 800; color: var(--primary-600); letter-spacing: -0.02em;">
                        <?= e(format_price($dish['price'])); ?>
                    </span>
                </div>

                <div>
                    <?php if (!$isAvailable): ?>
                        <span class="stock-badge stock-badge-out" style="padding: 0.5rem 1rem; font-size: 0.9rem;" id="stock-status-detail">
                            <span class="dot"></span>
                            Sold Out &bull; Unavailable
                        </span>
                    <?php elseif ($stockQty <= $reorderLevel): ?>
                        <span class="stock-badge stock-badge-low" style="padding: 0.5rem 1rem; font-size: 0.9rem;" id="stock-status-detail">
                            <span class="dot"></span>
                            Low Stock &bull; Only <?= e((string)$stockQty); ?> Remaining!
                        </span>
                    <?php else: ?>
                        <span class="stock-badge stock-badge-in" style="padding: 0.5rem 1rem; font-size: 0.9rem;" id="stock-status-detail">
                            <span class="dot"></span>
                            In Stock &bull; <?= e((string)$stockQty); ?> Fresh Portions Available
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div style="border-top: 1px solid rgba(234, 223, 211, 0.7); border-bottom: 1px solid rgba(234, 223, 211, 0.7); padding: 1.5rem 0;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.6rem;">
                Chef's Preparation &amp; Flavors
            </h3>
            <p style="color: var(--ink-700); font-size: 1rem; line-height: 1.7;">
                <?= nl2br(e($dish['description'])); ?>
            </p>
        </div>

        <!-- Meta Specification Pills -->
        <div class="meta-stats-row">
            <div class="meta-pill">
                <span>⏱️</span>
                <span>Prep: <strong><?= e((string)$dish['prep_time_minutes']); ?> mins</strong></span>
            </div>
            <?php if (!empty($dish['calories'])): ?>
                <div class="meta-pill">
                    <span>🔥</span>
                    <span>Energy: <strong><?= e((string)$dish['calories']); ?> kcal</strong></span>
                </div>
            <?php endif; ?>
            <div class="meta-pill">
                <span>🍱</span>
                <span>Cloud Kitchen Fresh</span>
            </div>
        </div>

        <!-- Add to Cart Form -->
        <div class="card" style="padding: 1.75rem; background: rgba(255, 255, 255, 0.9); margin-bottom: 0;">
            <?php if (!$isAvailable): ?>
                <div class="alert alert-danger" style="margin-bottom: 0;">
                    <div>
                        <strong>Temporarily Sold Out:</strong> Our kitchen has exhausted all prepared portions for this dish today. Please check back shortly or select another specialty from our menu.
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="<?= e(url('menu/detail.php?id=' . $dishId)); ?>">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add_to_cart">

                    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                        <div style="flex: 0 0 130px;">
                            <label for="order-quantity" style="margin-bottom: 0.4rem;">Portions:</label>
                            <input 
                                type="number" 
                                id="order-quantity" 
                                name="quantity" 
                                class="form-control" 
                                value="1" 
                                min="1" 
                                max="<?= e((string)$stockQty); ?>" 
                                required
                                style="text-align: center; font-weight: 700; font-size: 1.1rem;"
                            >
                        </div>

                        <div style="flex: 1; min-width: 200px;">
                            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.85rem 1.5rem; font-size: 1.05rem;" id="btn-add-to-cart">
                                🛒 Add to Cart &bull; <span id="dynamic-total-price"><?= e(format_price($dish['price'])); ?></span>
                            </button>
                        </div>
                    </div>
                    
                    <div style="font-size: 0.82rem; color: var(--ink-500); margin-top: 0.75rem; text-align: center;">
                        Instant contactless delivery &amp; pickup in Colombo area.
                    </div>
                </form>

                <script>
                    // Simple live price update helper
                    const unitPrice = <?= json_encode((float)$dish['price']); ?>;
                    const qtyInput = document.getElementById('order-quantity');
                    const totalLabel = document.getElementById('dynamic-total-price');

                    if (qtyInput && totalLabel) {
                        qtyInput.addEventListener('input', function() {
                            let q = parseInt(this.value, 10);
                            if (isNaN(q) || q < 1) q = 1;
                            const total = q * unitPrice;
                            totalLabel.textContent = 'LKR ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        });
                    }
                </script>
            <?php endif; ?>
        </div>

        <div>
            <a href="<?= e(url('menu/index.php')); ?>" class="btn btn-outline btn-sm">
                &larr; Back to Full Menu
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
