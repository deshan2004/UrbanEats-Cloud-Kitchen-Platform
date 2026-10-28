<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Real-Time Stock Dashboard & Inventory Adjustments
 * 
 * Features:
 * - 4 Key Performance Metric Cards (Tracked items, In-stock portions, Low-stock alerts, Out-of-stock items)
 * - Prominent Urgent Attention Alert section for dishes below reorder thresholds
 * - Complete inventory tracking table with status pill indicators
 * - Transactional Stock Adjustments Modal (Restock, Wastage, Recount Correction)
 * - Prevents stock from falling below zero
 * - Immutable Stock Movement Audit Trail with staff attribution and mandatory notes
 * - Role-guarded for kitchen_staff and admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Route Guard
require_role(['kitchen_staff', 'admin']);

$currentUser = current_user();
$userId = (int)$currentUser['id'];

// -------------------------------------------------------------------------
// Handle POST: Stock Adjustment (Restock, Wastage, Adjustment)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    // 1. Anti-CSRF Token Verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(url('menu/stock.php'));
    }

    $dishId       = (int)($_POST['dish_id'] ?? 0);
    $movementType = trim((string)($_POST['movement_type'] ?? ''));
    $quantityRaw  = (int)($_POST['quantity_change'] ?? 0);
    $reason       = trim((string)($_POST['reason'] ?? ''));

    // Whitelist movement types to prevent arbitrary inventory mutations
    if ($dishId <= 0 || !in_array($movementType, ['restock', 'wastage', 'adjustment'], true)) {
        set_flash('error', 'Invalid stock adjustment parameters.');
        redirect(url('menu/stock.php'));
    }

    // Mandatory Audit Compliance: Ensure reason is supplied to deter unauthorized wastage logging
    if ($reason === '') {
        set_flash('error', 'A mandatory reason or operational note must be provided for every stock adjustment.');
        redirect(url('menu/stock.php'));
    }

    // Determine strictly signed quantity change to prevent mathematical tampering
    if ($movementType === 'restock') {
        $qtyChange = abs($quantityRaw);
        if ($qtyChange <= 0) {
            set_flash('error', 'Restock quantity must be greater than zero.');
            redirect(url('menu/stock.php'));
        }
    } elseif ($movementType === 'wastage') {
        $qtyChange = -abs($quantityRaw);
        if ($qtyChange >= 0) {
            set_flash('error', 'Wastage deduction must be greater than zero.');
            redirect(url('menu/stock.php'));
        }
    } else { // adjustment
        $qtyChange = $quantityRaw; // can be + or -
        if ($qtyChange === 0) {
            set_flash('error', 'Adjustment quantity cannot be zero.');
            redirect(url('menu/stock.php'));
        }
    }

    // CONCURRENCY SAFEGUARD: Call adjust_stock() which executes within an ACID transaction
    // with pessimistic row locking ('FOR UPDATE') to prevent negative inventory balance and race conditions.
    $success = adjust_stock($pdo, $dishId, $userId, $movementType, $qtyChange, $reason);

    if ($success) {
        // Fetch dish name for audit log
        $dStmt = $pdo->prepare("SELECT name FROM dishes WHERE id = :id LIMIT 1");
        $dStmt->execute([':id' => $dishId]);
        $dishName = $dStmt->fetchColumn() ?: "Dish #{$dishId}";

        $sign = $qtyChange > 0 ? "+{$qtyChange}" : "{$qtyChange}";
        log_action($pdo, $userId, 'STOCK_ADJUST', "Adjusted stock for '{$dishName}' ({$sign} units, Type: {$movementType}). Reason: {$reason}");
        set_flash('success', "Stock for '{$dishName}' updated successfully ({$sign} portions).");
    } else {
        set_flash('error', 'Failed to update stock. Note that inventory quantity cannot drop below zero.');
    }

    redirect(url('menu/stock.php'));
}

// -------------------------------------------------------------------------
// Fetch Metrics & Inventory Data
// -------------------------------------------------------------------------
try {
    // 1. Calculate KPI Metrics
    $metricsStmt = $pdo->query(
        "SELECT 
            COUNT(d.id) AS total_dishes,
            COALESCE(SUM(i.quantity), 0) AS total_units,
            COALESCE(SUM(CASE WHEN i.quantity <= i.reorder_level AND i.quantity > 0 THEN 1 ELSE 0 END), 0) AS low_stock_count,
            COALESCE(SUM(CASE WHEN i.quantity = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count
         FROM dishes d
         LEFT JOIN inventory i ON i.dish_id = d.id
         WHERE d.is_active = 1"
    );
    $metrics = $metricsStmt->fetch();

    $totalDishes     = (int)($metrics['total_dishes'] ?? 0);
    $totalStockUnits = (int)($metrics['total_units'] ?? 0);
    $lowStockCount   = (int)($metrics['low_stock_count'] ?? 0);
    $outOfStockCount = (int)($metrics['out_of_stock_count'] ?? 0);

    // 2. Fetch Urgent Low Stock / Out of Stock Items
    $urgentStmt = $pdo->query(
        "SELECT 
            d.id, d.name, d.image_url, d.price,
            c.name AS category_name, c.icon AS category_icon,
            COALESCE(i.quantity, 0) AS quantity,
            COALESCE(i.reorder_level, 10) AS reorder_level
         FROM dishes d
         JOIN categories c ON c.id = d.category_id
         LEFT JOIN inventory i ON i.dish_id = d.id
         WHERE d.is_active = 1 AND COALESCE(i.quantity, 0) <= COALESCE(i.reorder_level, 10)
         ORDER BY i.quantity ASC, d.name ASC"
    );
    $urgentItems = $urgentStmt->fetchAll();

    // 3. Fetch Master Inventory
    $invStmt = $pdo->query(
        "SELECT 
            d.id, d.name, d.image_url, d.price, d.is_active,
            c.name AS category_name, c.icon AS category_icon,
            COALESCE(i.quantity, 0) AS quantity,
            COALESCE(i.reorder_level, 10) AS reorder_level,
            i.last_restocked_at, i.updated_at
         FROM dishes d
         JOIN categories c ON c.id = d.category_id
         LEFT JOIN inventory i ON i.dish_id = d.id
         WHERE d.is_active = 1
         ORDER BY (COALESCE(i.quantity, 0) <= COALESCE(i.reorder_level, 10)) DESC, d.name ASC"
    );
    $inventoryList = $invStmt->fetchAll();

    // 4. Fetch Recent 25 Stock Movement Logs
    $movStmt = $pdo->query(
        "SELECT 
            sm.id, sm.movement_type, sm.quantity_change, sm.previous_quantity, sm.new_quantity, sm.reason, sm.created_at,
            d.name AS dish_name, d.id AS dish_id,
            u.full_name AS staff_name, u.username AS staff_username
         FROM stock_movements sm
         JOIN dishes d ON d.id = sm.dish_id
         LEFT JOIN users u ON u.id = sm.user_id
         ORDER BY sm.id DESC
         LIMIT 25"
    );
    $movements = $movStmt->fetchAll();

    // Fetch all active dishes for adjustment dropdown selector
    $dishesDropdownStmt = $pdo->query("SELECT id, name, price FROM dishes WHERE is_active = 1 ORDER BY name ASC");
    $dishesDropdown = $dishesDropdownStmt->fetchAll();

} catch (PDOException $e) {
    error_log("[Stock Dashboard Error] " . $e->getMessage());
    $totalDishes = $totalStockUnits = $lowStockCount = $outOfStockCount = 0;
    $urgentItems = $inventoryList = $movements = $dishesDropdown = [];
}

// Check if preselected dish requested via GET
$preselectedDishId = (int)($_GET['dish_id'] ?? 0);

$pageTitle = 'Stock Control & Inventory Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Title Area & Action Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span style="font-size: 1.5rem;">📊</span>
            <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--primary-600);">
                Inventory Governance &amp; Restock Control
            </span>
        </div>
        <h1 style="font-size: 2.1rem; font-weight: 800; color: var(--ink-900); letter-spacing: -0.02em;">
            Stock Dashboard
        </h1>
        <p style="color: var(--ink-500); font-size: 0.92rem;">
            Monitor live ingredient batch portions, track low-stock thresholds, and log restocks or kitchen wastage.
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn-primary btn-sm" id="btn-open-adjust-modal" onclick="openAdjustModal(0)">
            + Record Stock Adjustment
        </button>
        <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-secondary btn-sm">
            Dishes Management
        </a>
        <a href="<?= e(url('menu/index.php')); ?>" class="btn btn-outline btn-sm">
            Public Menu &rarr;
        </a>
    </div>
</div>

<!-- 4 Key Performance Stat Cards -->
<div class="stats-grid">
    <!-- Active Dishes -->
    <div class="stat-card">
        <span class="stat-title">Tracked Dishes</span>
        <span class="stat-value"><?= e((string)$totalDishes); ?></span>
        <span class="stat-subtext" style="color: var(--info-500);">Active menu items</span>
    </div>

    <!-- Total Portions Available -->
    <div class="stat-card">
        <span class="stat-title">Available Portions</span>
        <span class="stat-value" style="color: var(--success-500);"><?= e((string)$totalStockUnits); ?></span>
        <span class="stat-subtext">Ready to serve</span>
    </div>

    <!-- Low Stock Alert -->
    <div class="stat-card" style="<?= $lowStockCount > 0 ? 'border-color: rgba(201, 130, 6, 0.4); background: rgba(255, 243, 214, 0.6);' : ''; ?>">
        <span class="stat-title">Low-Stock Warnings</span>
        <span class="stat-value" style="color: var(--warning-500);"><?= e((string)$lowStockCount); ?></span>
        <span class="stat-subtext" style="color: var(--warning-500);">At or below reorder level</span>
    </div>

    <!-- Out of Stock -->
    <div class="stat-card" style="<?= $outOfStockCount > 0 ? 'border-color: rgba(216, 68, 58, 0.4); background: rgba(253, 231, 229, 0.6);' : ''; ?>">
        <span class="stat-title">Sold Out (0 Units)</span>
        <span class="stat-value" style="color: var(--danger-500);"><?= e((string)$outOfStockCount); ?></span>
        <span class="stat-subtext" style="color: var(--danger-500);">Unavailable for ordering</span>
    </div>
</div>

<!-- Urgent Restock Callout (if items are below threshold) -->
<?php if (!empty($urgentItems)): ?>
    <div class="card" style="border: 1px solid rgba(201, 130, 6, 0.4); background: rgba(255, 243, 214, 0.45); margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="font-size: 1.3rem;">⚠️</span>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #8c5700;">
                    Immediate Kitchen Restock Required (<?= e((string)count($urgentItems)); ?> items)
                </h3>
            </div>
            <span style="font-size: 0.85rem; color: #8c5700; font-weight: 600;">
                Portions have fallen below minimum kitchen buffer levels.
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
            <?php foreach ($urgentItems as $u): 
                $uQty = (int)$u['quantity'];
                $uReorder = (int)$u['reorder_level'];
                $isZero = $uQty === 0;
            ?>
                <div style="background: rgba(255, 255, 255, 0.85); border-radius: var(--radius-md); padding: 1rem; border: 1px solid rgba(234, 223, 211, 0.8); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 700; font-size: 0.95rem; color: var(--ink-900);">
                            <?= e($u['name']); ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--ink-500); margin-top: 0.15rem;">
                            <?= e($u['category_icon'] . ' ' . $u['category_name']); ?>
                        </div>
                        <div style="margin-top: 0.4rem;">
                            <?php if ($isZero): ?>
                                <span class="stock-badge stock-badge-out" style="font-size: 0.76rem; padding: 0.2rem 0.55rem;">
                                    <span class="dot"></span>
                                    0 Remaining (Sold Out)
                                </span>
                            <?php else: ?>
                                <span class="stock-badge stock-badge-low" style="font-size: 0.76rem; padding: 0.2rem 0.55rem;">
                                    <span class="dot"></span>
                                    <?= e((string)$uQty); ?> left (Buffer: <?= e((string)$uReorder); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openAdjustModal(<?= (int)$u['id']; ?>, 'restock')">
                        Restock +
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Tabs: Master Inventory vs. Stock Movement History -->
<div style="margin-bottom: 2rem;">
    <!-- Inventory Master Table -->
    <div class="table-card">
        <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(234, 223, 211, 0.6); flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--ink-900);">
                    Kitchen Portion Inventory
                </h3>
                <span style="font-size: 0.85rem; color: var(--ink-500);">
                    All stock balance changes are tracked atomically with row-locking protection.
                </span>
            </div>
            <div style="font-size: 0.85rem; color: var(--ink-700); font-weight: 600;">
                Total: <?= e((string)count($inventoryList)); ?> dishes
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Image</th>
                    <th>Dish Name</th>
                    <th>Category</th>
                    <th>Current Portions</th>
                    <th>Buffer Alert Level</th>
                    <th>Inventory Status</th>
                    <th>Last Restocked</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventoryList)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--ink-500);">
                            No dishes found in inventory.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventoryList as $row): 
                        $qty = (int)$row['quantity'];
                        $reorder = (int)$row['reorder_level'];

                        if ($qty <= 0) {
                            $statusPill = '<span class="stock-badge stock-badge-out"><span class="dot"></span>Out of Stock</span>';
                        } elseif ($qty <= $reorder) {
                            $statusPill = '<span class="stock-badge stock-badge-low"><span class="dot"></span>Low Stock (' . $qty . ')</span>';
                        } else {
                            $statusPill = '<span class="stock-badge stock-badge-in"><span class="dot"></span>In Stock (' . $qty . ')</span>';
                        }

                        $restockTime = !empty($row['last_restocked_at']) ? date('M j, Y - g:i A', strtotime($row['last_restocked_at'])) : 'Never';
                    ?>
                        <tr id="inv-row-<?= e((string)$row['id']); ?>">
                            <td>
                                <img 
                                    src="<?= e(url('public/' . ltrim($row['image_url'], '/'))); ?>" 
                                    alt="<?= e($row['name']); ?>" 
                                    style="width: 48px; height: 48px; border-radius: var(--radius-md); object-fit: cover; border: 1px solid rgba(234, 223, 211, 0.9);"
                                    onerror="this.src='<?= e(url('public/uploads/chicken_kottu.jpg')); ?>'"
                                >
                            </td>
                            <td>
                                <a href="<?= e(url('menu/detail.php?id=' . $row['id'])); ?>" style="font-weight: 700; color: var(--ink-900);">
                                    <?= e($row['name']); ?>
                                </a>
                                <div style="font-size: 0.8rem; color: var(--ink-500);">
                                    <?= e(format_price($row['price'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="role-pill" style="font-size: 0.8rem;">
                                    <?= e($row['category_icon'] . ' ' . $row['category_name']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 1.15rem; font-weight: 800; color: <?= $qty <= $reorder ? 'var(--danger-500)' : 'var(--ink-900)'; ?>;">
                                    <?= e((string)$qty); ?>
                                </span>
                                <span style="font-size: 0.8rem; color: var(--ink-500);">units</span>
                            </td>
                            <td style="font-weight: 600; color: var(--ink-700);">
                                <?= e((string)$reorder); ?> units
                            </td>
                            <td>
                                <?= $statusPill; ?>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--ink-500);">
                                <?= e($restockTime); ?>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="openAdjustModal(<?= (int)$row['id']; ?>)">
                                    Adjust Stock
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stock Movements Audit Trail Table -->
<div class="table-card">
    <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(234, 223, 211, 0.6); flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--ink-900);">
                Stock Movement History &amp; Audit Trail
            </h3>
            <p style="font-size: 0.85rem; color: var(--ink-500); margin-top: 0.15rem;">
                Historical log of kitchen prep restocks, customer order deductions, and recorded wastage.
            </p>
        </div>
        <span style="font-size: 0.85rem; color: var(--ink-500);">
            Showing latest 25 events
        </span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Date &amp; Time</th>
                <th>Dish</th>
                <th>Movement Type</th>
                <th>Change</th>
                <th>Quantity Shift</th>
                <th>Reason / Operational Notes</th>
                <th>Staff Member</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--ink-500);">
                        No stock movement logs recorded yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($movements as $m): 
                    $type = $m['movement_type'];
                    $change = (int)$m['quantity_change'];
                    $sign = $change > 0 ? "+{$change}" : "{$change}";
                    
                    if ($type === 'restock') {
                        $typeBadge = '<span class="status-pill status-active"><span class="dot"></span>Restock</span>';
                        $changeColor = 'var(--success-500)';
                    } elseif ($type === 'wastage') {
                        $typeBadge = '<span class="status-pill status-inactive"><span class="dot"></span>Kitchen Wastage</span>';
                        $changeColor = 'var(--danger-500)';
                    } elseif ($type === 'order_deduction') {
                        $typeBadge = '<span class="role-pill" style="background: rgba(228, 237, 253, 0.85); color: #2F6FDE; border-color: rgba(47, 111, 222, 0.3);">Order Dispatch</span>';
                        $changeColor = 'var(--info-500)';
                    } else {
                        $typeBadge = '<span class="role-pill">Audit Adjustment</span>';
                        $changeColor = 'var(--ink-700)';
                    }
                ?>
                    <tr>
                        <td style="font-size: 0.85rem; color: var(--ink-500); white-space: nowrap;">
                            <?= e(date('M j, Y - H:i', strtotime($m['created_at']))); ?>
                        </td>
                        <td style="font-weight: 700; color: var(--ink-900);">
                            <?= e($m['dish_name']); ?>
                        </td>
                        <td>
                            <?= $typeBadge; ?>
                        </td>
                        <td style="font-weight: 800; font-size: 0.98rem; color: <?= e($changeColor); ?>;">
                            <?= e($sign); ?>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--ink-700);">
                            <?= e((string)$m['previous_quantity']); ?> &rarr; <strong><?= e((string)$m['new_quantity']); ?></strong>
                        </td>
                        <td style="font-size: 0.88rem; color: var(--ink-700);">
                            <?= e($m['reason']); ?>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--ink-500);">
                            <?= e($m['staff_name'] ?: ($m['staff_username'] ?: 'Automated Order Fulfillment')); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal-overlay" id="adjust-stock-modal">
    <div class="modal-box" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title" id="adjust-modal-heading">Record Stock Adjustment</h3>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('adjust-stock-modal').classList.remove('active')">&times;</button>
        </div>

        <form method="POST" action="<?= e(url('menu/stock.php')); ?>">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="adjust_stock">

            <!-- Dish Selector -->
            <div class="form-group">
                <label for="modal-dish-id">Select Dish <span class="required">*</span></label>
                <select name="dish_id" id="modal-dish-id" class="form-control" required>
                    <option value="">-- Choose a dish --</option>
                    <?php foreach ($dishesDropdown as $dd): ?>
                        <option value="<?= e((string)$dd['id']); ?>">
                            <?= e($dd['name'] . ' (' . format_price($dd['price']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Movement Type Selector -->
            <div class="form-group">
                <label for="modal-movement-type">Adjustment Classification <span class="required">*</span></label>
                <select name="movement_type" id="modal-movement-type" class="form-control" required onchange="handleMovementTypeChange(this.value)">
                    <option value="restock">➕ Restock (Kitchen batch prepared)</option>
                    <option value="wastage">➖ Kitchen Wastage (Spoilage / dropped / expired)</option>
                    <option value="adjustment">⚖️ Inventory Recount Correction</option>
                </select>
            </div>

            <!-- Quantity Change -->
            <div class="form-group">
                <label for="modal-quantity-change" id="modal-qty-label">Quantity to Add (Portions) <span class="required">*</span></label>
                <input 
                    type="number" 
                    name="quantity_change" 
                    id="modal-quantity-change" 
                    class="form-control" 
                    value="20" 
                    min="1" 
                    required
                >
                <span class="input-hint" id="modal-qty-hint">Enter the number of newly cooked portions to add to inventory.</span>
            </div>

            <!-- Mandatory Reason -->
            <div class="form-group">
                <label for="modal-reason">Reason &amp; Culinary Note <span class="required">*</span></label>
                <textarea 
                    name="reason" 
                    id="modal-reason" 
                    class="form-control" 
                    rows="3" 
                    placeholder="e.g. Prepared 25 fresh chicken kottu portions for dinner shift"
                    required
                ></textarea>
                <span class="input-hint">Mandatory for quality control and audit compliance.</span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('adjust-stock-modal').classList.remove('active')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary btn-sm" id="btn-save-stock-adjustment">
                    Confirm &amp; Apply Stock Change &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Open modal with preselected dish and optional type
    function openAdjustModal(dishId, defaultType = 'restock') {
        const modal = document.getElementById('adjust-stock-modal');
        const dishSelect = document.getElementById('modal-dish-id');
        const typeSelect = document.getElementById('modal-movement-type');
        
        if (dishId > 0 && dishSelect) {
            dishSelect.value = dishId;
        }
        if (typeSelect && defaultType) {
            typeSelect.value = defaultType;
            handleMovementTypeChange(defaultType);
        }
        
        modal.classList.add('active');
    }

    function handleMovementTypeChange(type) {
        const qtyLabel = document.getElementById('modal-qty-label');
        const qtyHint = document.getElementById('modal-qty-hint');
        const qtyInput = document.getElementById('modal-quantity-change');
        const reasonInput = document.getElementById('modal-reason');

        if (type === 'restock') {
            qtyLabel.innerHTML = 'Quantity to Add (Portions) <span class="required">*</span>';
            qtyHint.textContent = 'Enter the number of newly cooked portions to add to inventory.';
            qtyInput.min = '1';
            qtyInput.value = '20';
            reasonInput.placeholder = 'e.g. Fresh batch cooked for dinner shift';
        } else if (type === 'wastage') {
            qtyLabel.innerHTML = 'Quantity Lost / Discarded (Portions) <span class="required">*</span>';
            qtyHint.textContent = 'Enter the number of portions spoiled or dropped (will be deducted).';
            qtyInput.min = '1';
            qtyInput.value = '2';
            reasonInput.placeholder = 'e.g. Rice container overheated in warm station';
        } else {
            qtyLabel.innerHTML = 'Portion Correction (+/- Change) <span class="required">*</span>';
            qtyHint.textContent = 'Use positive numbers to add, negative numbers to subtract.';
            qtyInput.removeAttribute('min');
            qtyInput.value = '5';
            reasonInput.placeholder = 'e.g. End of shift physical inventory recount verification';
        }
    }

    // Auto-open modal if preselected dish was passed in query parameter
    <?php if ($preselectedDishId > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openAdjustModal(<?= $preselectedDishId; ?>);
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
