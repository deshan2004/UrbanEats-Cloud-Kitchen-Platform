<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Staff Dish Management (Full CRUD, Soft-Delete & Secure Image Upload)
 * 
 * Features:
 * - Full CRUD with prepared statements and parameter binding
 * - Soft delete mechanism (`is_active` flag) preserving historical order referential integrity
 * - Multi-layer secure image upload (MIME checking, extension whitelist, 2MB size cap, randomized filenames)
 * - Live client-side image preview before form submission
 * - Transactional creation linking dishes table with initial inventory record
 * - Audit logging integrated with Component A's `log_action()`
 * - Access-guarded strictly to kitchen_staff and admin roles
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Route Guard: Kitchen Staff and Administrators only
require_role(['kitchen_staff', 'admin']);

$currentUser = current_user();
$userId = (int)$currentUser['id'];

// Helper to generate a clean URL-friendly slug
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'dish-' . time() : $text;
}

// Fetch all categories for select dropdowns
try {
    $catStmt = $pdo->query("SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    error_log("[Manage Dishes] Category query failed: " . $e->getMessage());
    $categories = [];
}

// -------------------------------------------------------------------------
// Handle POST Actions: Create, Update, Soft-Delete
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(url('menu/manage_dishes.php'));
    }

    $action = $_POST['action'] ?? '';

    // ACTION 1: CREATE NEW DISH
    if ($action === 'create_dish') {
        $name        = trim((string)($_POST['name'] ?? ''));
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $price       = (float)($_POST['price'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));
        $prepTime    = max(1, (int)($_POST['prep_time_minutes'] ?? 15));
        $calories    = !empty($_POST['calories']) ? (int)$_POST['calories'] : null;
        $isVeg       = isset($_POST['is_vegetarian']) ? 1 : 0;
        $isSpicy     = isset($_POST['is_spicy']) ? 1 : 0;
        $initialQty  = max(0, (int)($_POST['initial_stock'] ?? 20));
        $reorderLvl  = max(1, (int)($_POST['reorder_level'] ?? 10));

        if ($name === '' || $categoryId <= 0 || $price <= 0 || $description === '') {
            set_flash('error', 'Please fill in all mandatory dish fields (Name, Category, Valid Price, Description).');
            redirect(url('menu/manage_dishes.php'));
        }

        // Process secure image upload if provided
        $imageUrl = 'uploads/chicken_kottu.jpg'; // default fallback
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = upload_dish_image($_FILES['image']);
            if (!$uploadResult['success']) {
                set_flash('error', 'Image Upload Failed: ' . $uploadResult['error']);
                redirect(url('menu/manage_dishes.php'));
            }
            if ($uploadResult['path']) {
                $imageUrl = $uploadResult['path'];
            }
        }

        // Generate unique slug
        $baseSlug = slugify($name);
        $slug = $baseSlug;
        $slugCheck = $pdo->prepare("SELECT id FROM dishes WHERE slug = :s LIMIT 1");
        $slugCheck->execute([':s' => $slug]);
        if ($slugCheck->fetch()) {
            $slug = $baseSlug . '-' . time();
        }

        // Insert Dish and Initial Inventory atomically within transaction
        $pdo->beginTransaction();
        try {
            $dishInsert = $pdo->prepare(
                "INSERT INTO dishes (category_id, name, slug, description, price, image_url, is_vegetarian, is_spicy, prep_time_minutes, calories, is_active, created_at)
                 VALUES (:cat, :name, :slug, :desc, :price, :img, :veg, :spicy, :prep, :cal, 1, NOW())"
            );
            $dishInsert->execute([
                ':cat'   => $categoryId,
                ':name'  => $name,
                ':slug'  => $slug,
                ':desc'  => $description,
                ':price' => $price,
                ':img'   => $imageUrl,
                ':veg'   => $isVeg,
                ':spicy' => $isSpicy,
                ':prep'  => $prepTime,
                ':cal'   => $calories,
            ]);

            $newDishId = (int)$pdo->lastInsertId();

            // Insert matching inventory row
            $invInsert = $pdo->prepare(
                "INSERT INTO inventory (dish_id, quantity, reorder_level, last_restocked_at, updated_at)
                 VALUES (:dish_id, :qty, :reorder, NOW(), NOW())"
            );
            $invInsert->execute([
                ':dish_id' => $newDishId,
                ':qty'     => $initialQty,
                ':reorder' => $reorderLvl,
            ]);

            // Record initial stock movement
            if ($initialQty > 0) {
                // Use distinct parameter markers for native prepared statements to prevent SQLSTATE[HY093]
                $movInsert = $pdo->prepare(
                    "INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity_change, previous_quantity, new_quantity, reason, created_at)
                     VALUES (:dish_id, :user_id, 'restock', :qty_change, 0, :new_qty, 'Initial stock entry on dish creation', NOW())"
                );
                $movInsert->execute([
                    ':dish_id'    => $newDishId,
                    ':user_id'    => $userId,
                    ':qty_change' => $initialQty,
                    ':new_qty'    => $initialQty,
                ]);
            }

            $pdo->commit();

            log_action($pdo, $userId, 'DISH_CREATE', "Created dish '{$name}' (ID: {$newDishId}) with price LKR {$price} and stock {$initialQty}");
            set_flash('success', "New dish '{$name}' created and added to the kitchen catalogue.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log("[Create Dish Error] " . $e->getMessage());
            set_flash('error', 'Database error creating dish: ' . $e->getMessage());
        }

        redirect(url('menu/manage_dishes.php'));
    }

    // ACTION 2: UPDATE DISH
    if ($action === 'update_dish') {
        $dishId      = (int)($_POST['dish_id'] ?? 0);
        $name        = trim((string)($_POST['name'] ?? ''));
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $price       = (float)($_POST['price'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));
        $prepTime    = max(1, (int)($_POST['prep_time_minutes'] ?? 15));
        $calories    = !empty($_POST['calories']) ? (int)$_POST['calories'] : null;
        $isVeg       = isset($_POST['is_vegetarian']) ? 1 : 0;
        $isSpicy     = isset($_POST['is_spicy']) ? 1 : 0;
        $reorderLvl  = max(1, (int)($_POST['reorder_level'] ?? 10));

        if ($dishId <= 0 || $name === '' || $categoryId <= 0 || $price <= 0 || $description === '') {
            set_flash('error', 'Please fill in all mandatory dish fields.');
            redirect(url('menu/manage_dishes.php?edit=' . $dishId));
        }

        // Verify dish exists
        $checkStmt = $pdo->prepare("SELECT id, image_url, name FROM dishes WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $dishId]);
        $existing = $checkStmt->fetch();

        if (!$existing) {
            set_flash('error', 'Dish not found.');
            redirect(url('menu/manage_dishes.php'));
        }

        $imageUrl = $existing['image_url'];
        // Check if a replacement image was uploaded
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = upload_dish_image($_FILES['image']);
            if (!$uploadResult['success']) {
                set_flash('error', 'Image Upload Failed: ' . $uploadResult['error']);
                redirect(url('menu/manage_dishes.php?edit=' . $dishId));
            }
            if ($uploadResult['path']) {
                $imageUrl = $uploadResult['path'];
            }
        }

        try {
            $updStmt = $pdo->prepare(
                "UPDATE dishes 
                 SET category_id = :cat, name = :name, description = :desc, price = :price, 
                     image_url = :img, is_vegetarian = :veg, is_spicy = :spicy, 
                     prep_time_minutes = :prep, calories = :cal, updated_at = NOW()
                 WHERE id = :id"
            );
            $updStmt->execute([
                ':cat'   => $categoryId,
                ':name'  => $name,
                ':desc'  => $description,
                ':price' => $price,
                ':img'   => $imageUrl,
                ':veg'   => $isVeg,
                ':spicy' => $isSpicy,
                ':prep'  => $prepTime,
                ':cal'   => $calories,
                ':id'    => $dishId,
            ]);

            // Update reorder level in inventory table
            $updInv = $pdo->prepare("UPDATE inventory SET reorder_level = :reorder WHERE dish_id = :dish_id");
            $updInv->execute([':reorder' => $reorderLvl, ':dish_id' => $dishId]);

            log_action($pdo, $userId, 'DISH_UPDATE', "Updated dish '{$name}' (ID: {$dishId})");
            set_flash('success', "Dish '{$name}' updated successfully.");
        } catch (PDOException $e) {
            error_log("[Update Dish Error] " . $e->getMessage());
            set_flash('error', 'Database error updating dish: ' . $e->getMessage());
        }

        redirect(url('menu/manage_dishes.php'));
    }

    // ACTION 3: SOFT DELETE (TOGGLE ACTIVE STATE)
    if ($action === 'toggle_active') {
        $dishId = (int)($_POST['dish_id'] ?? 0);
        $newStatus = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE dishes SET is_active = :status, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $dishId]);

            $actionText = ($newStatus === 1) ? 'restored to active catalogue' : 'soft-deleted (hidden from public menu)';
            log_action($pdo, $userId, ($newStatus === 1 ? 'DISH_RESTORE' : 'DISH_SOFT_DELETE'), "Dish ID {$dishId} {$actionText}");
            set_flash('success', "Dish {$actionText}. Existing orders remain fully intact.");
        } catch (PDOException $e) {
            error_log("[Toggle Dish Error] " . $e->getMessage());
            set_flash('error', 'Database error updating dish status.');
        }

        redirect(url('menu/manage_dishes.php'));
    }
}

// -------------------------------------------------------------------------
// Edit Mode Check (If ?edit=ID passed in GET)
// -------------------------------------------------------------------------
$editDish = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editStmt = $pdo->prepare(
        "SELECT d.*, COALESCE(i.reorder_level, 10) AS reorder_level, COALESCE(i.quantity, 0) AS current_quantity
         FROM dishes d
         LEFT JOIN inventory i ON i.dish_id = d.id
         WHERE d.id = :id 
         LIMIT 1"
    );
    $editStmt->execute([':id' => $editId]);
    $editDish = $editStmt->fetch();
}

// -------------------------------------------------------------------------
// Fetch All Dishes for Management Listing
// -------------------------------------------------------------------------
$filterCategory = (int)($_GET['cat_id'] ?? 0);
$filterStatus   = $_GET['status'] ?? 'all';
$searchKeyword  = trim((string)($_GET['q'] ?? ''));

$whereParts = [];
$params = [];

if ($filterCategory > 0) {
    $whereParts[] = 'd.category_id = :cat';
    $params[':cat'] = $filterCategory;
}

if ($filterStatus === 'active') {
    $whereParts[] = 'd.is_active = 1';
} elseif ($filterStatus === 'inactive') {
    $whereParts[] = 'd.is_active = 0';
}

if ($searchKeyword !== '') {
    $whereParts[] = '(d.name LIKE :q OR d.description LIKE :q2)';
    $params[':q']  = '%' . $searchKeyword . '%';
    $params[':q2'] = '%' . $searchKeyword . '%';
}

$whereCondition = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

try {
    $listSql = "SELECT 
                    d.id, d.name, d.slug, d.price, d.image_url, d.is_active,
                    d.is_vegetarian, d.is_spicy, d.prep_time_minutes,
                    c.name AS category_name, c.icon AS category_icon,
                    COALESCE(i.quantity, 0) AS stock_quantity,
                    COALESCE(i.reorder_level, 10) AS reorder_level
                FROM dishes d
                JOIN categories c ON c.id = d.category_id
                LEFT JOIN inventory i ON i.dish_id = d.id
                {$whereCondition}
                ORDER BY d.id DESC";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $allDishes = $listStmt->fetchAll();
} catch (PDOException $e) {
    error_log("[Manage Dishes] Listing failed: " . $e->getMessage());
    $allDishes = [];
}

$pageTitle = 'Dish Management';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Title & Action Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span style="font-size: 1.5rem;">🍲</span>
            <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--primary-600);">
                Kitchen Catalogue Operations
            </span>
        </div>
        <h1 style="font-size: 2.1rem; font-weight: 800; color: var(--ink-900); letter-spacing: -0.02em;">
            Dish Management
        </h1>
        <p style="color: var(--ink-500); font-size: 0.92rem;">
            Add dishes, configure pricing, upload verified media, and manage catalogue availability with soft-delete protection.
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn-primary btn-sm" id="btn-open-create-modal" onclick="document.getElementById('create-dish-modal').classList.add('active')">
            + Add New Dish
        </button>
        <a href="<?= e(url('menu/manage_categories.php')); ?>" class="btn btn-secondary btn-sm">
            Categories
        </a>
        <a href="<?= e(url('menu/stock.php')); ?>" class="btn btn-secondary btn-sm">
            Stock Control &rarr;
        </a>
    </div>
</div>

<!-- If editing an existing dish, render edit box directly -->
<?php if ($editDish): ?>
    <div class="card" style="border: 2px solid var(--primary-500); margin-bottom: 2.5rem;" id="edit-dish-container">
        <div class="card-header">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--ink-900);">
                    Edit Dish: <?= e($editDish['name']); ?> (ID #<?= e((string)$editDish['id']); ?>)
                </h3>
                <p style="font-size: 0.85rem; color: var(--ink-500); margin-top: 0.2rem;">
                    Update dish properties, pricing, media, or inventory reorder threshold.
                </p>
            </div>
            <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-secondary btn-sm">
                Cancel Edit
            </a>
        </div>

        <form method="POST" action="<?= e(url('menu/manage_dishes.php')); ?>" enctype="multipart/form-data">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update_dish">
            <input type="hidden" name="dish_id" value="<?= e((string)$editDish['id']); ?>">

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <div class="form-group">
                        <label for="edit-name">Dish Name <span class="required">*</span></label>
                        <input type="text" id="edit-name" name="name" class="form-control" value="<?= e($editDish['name']); ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="edit-category">Category <span class="required">*</span></label>
                            <select id="edit-category" name="category_id" class="form-control" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e((string)$cat['id']); ?>" <?= (int)$cat['id'] === (int)$editDish['category_id'] ? 'selected' : ''; ?>>
                                        <?= e($cat['icon'] . ' ' . $cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit-price">Price (LKR) <span class="required">*</span></label>
                            <input type="number" id="edit-price" name="price" class="form-control" step="0.50" min="1" value="<?= e((string)$editDish['price']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit-desc">Culinary Description <span class="required">*</span></label>
                        <textarea id="edit-desc" name="description" class="form-control" rows="4" required><?= e($editDish['description']); ?></textarea>
                    </div>
                </div>

                <!-- Right Column: Specs & Image -->
                <div>
                    <div class="form-group">
                        <label>Dish Photo (Max 2MB: JPG, PNG, WEBP)</label>
                        <div class="image-upload-wrapper">
                            <div class="image-preview-thumbnail">
                                <img 
                                    src="<?= e(url('public/' . ltrim($editDish['image_url'], '/'))); ?>" 
                                    id="edit-img-preview" 
                                    alt="Dish Preview"
                                    onerror="this.src='<?= e(url('public/uploads/chicken_kottu.jpg')); ?>'"
                                >
                            </div>
                            <div style="flex: 1;">
                                <input 
                                    type="file" 
                                    name="image" 
                                    id="edit-dish-file-input" 
                                    class="form-control" 
                                    accept=".jpg,.jpeg,.png,.webp"
                                    onchange="previewImage(this, 'edit-img-preview')"
                                >
                                <span class="input-hint">Leave blank to keep existing photo.</span>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="edit-prep">Prep Time (mins)</label>
                            <input type="number" id="edit-prep" name="prep_time_minutes" class="form-control" min="1" value="<?= e((string)$editDish['prep_time_minutes']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit-cal">Calories (kcal)</label>
                            <input type="number" id="edit-cal" name="calories" class="form-control" min="0" value="<?= e((string)($editDish['calories'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit-reorder">Low Stock Alert Level</label>
                        <input type="number" id="edit-reorder" name="reorder_level" class="form-control" min="1" value="<?= e((string)$editDish['reorder_level']); ?>" required>
                        <span class="input-hint">Triggers warning when inventory drops to or below this.</span>
                    </div>

                    <div style="display: flex; gap: 1.5rem; margin-top: 1rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_vegetarian" value="1" <?= (int)$editDish['is_vegetarian'] === 1 ? 'checked' : ''; ?>>
                            <span>🌱 Vegetarian</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_spicy" value="1" <?= (int)$editDish['is_spicy'] === 1 ? 'checked' : ''; ?>>
                            <span>🌶️ Spicy</span>
                        </label>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid rgba(234, 223, 211, 0.7); padding-top: 1.25rem;">
                <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-secondary btn-sm">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-sm" id="btn-save-edit-dish">
                    Save Changes &rarr;
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.75rem;">
    <form method="GET" action="<?= e(url('menu/manage_dishes.php')); ?>" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; min-width: 240px;">
            <input 
                type="text" 
                name="q" 
                value="<?= e($searchKeyword); ?>" 
                placeholder="Search dishes by name or description..." 
                class="form-control"
            >
        </div>

        <select name="cat_id" class="filter-select" onchange="this.form.submit()">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e((string)$cat['id']); ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : ''; ?>>
                    <?= e($cat['icon'] . ' ' . $cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="all" <?= $filterStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="active" <?= $filterStatus === 'active' ? 'selected' : ''; ?>>Active Catalogue Only</option>
            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : ''; ?>>Soft-Deleted (Inactive)</option>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">
            Filter
        </button>
        <?php if ($searchKeyword !== '' || $filterCategory > 0 || $filterStatus !== 'all'): ?>
            <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-outline btn-sm">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Dishes Management Table -->
<div class="table-card">
    <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(234, 223, 211, 0.6);">
        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--ink-900);">
            Cloud Kitchen Dishes (<?= e((string)count($allDishes)); ?> Items)
        </h3>
        <span style="font-size: 0.85rem; color: var(--ink-500);">
            Soft deletes preserve historical orders while removing items from the live customer menu.
        </span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70px;">Image</th>
                <th>Dish Details</th>
                <th>Category</th>
                <th>Price (LKR)</th>
                <th>Stock Availability</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allDishes)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--ink-500);">
                        No dishes found matching the current filters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($allDishes as $d): 
                    $qty = (int)$d['stock_quantity'];
                    $reorder = (int)$d['reorder_level'];
                    $isActive = (int)$d['is_active'] === 1;

                    if ($qty <= 0) {
                        $stockBadge = '<span class="stock-badge stock-badge-out"><span class="dot"></span>0 (Out)</span>';
                    } elseif ($qty <= $reorder) {
                        $stockBadge = '<span class="stock-badge stock-badge-low"><span class="dot"></span>' . $qty . ' (Low)</span>';
                    } else {
                        $stockBadge = '<span class="stock-badge stock-badge-in"><span class="dot"></span>' . $qty . ' in stock</span>';
                    }
                ?>
                    <tr style="<?= !$isActive ? 'opacity: 0.6; background: rgba(0,0,0,0.015);' : ''; ?>">
                        <td>
                            <img 
                                src="<?= e(url('public/' . ltrim($d['image_url'], '/'))); ?>" 
                                alt="<?= e($d['name']); ?>" 
                                style="width: 52px; height: 52px; border-radius: var(--radius-md); object-fit: cover; border: 1px solid rgba(234, 223, 211, 0.9);"
                                onerror="this.src='<?= e(url('public/uploads/chicken_kottu.jpg')); ?>'"
                            >
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--ink-900); font-size: 0.96rem;">
                                <?= e($d['name']); ?>
                            </div>
                            <div style="display: flex; gap: 0.4rem; margin-top: 0.25rem;">
                                <?php if ((int)$d['is_vegetarian'] === 1): ?>
                                    <span style="font-size: 0.72rem; background: rgba(227, 246, 234, 0.85); color: #126335; padding: 0.1rem 0.45rem; border-radius: 9999px; font-weight: 700;">🌱 Veg</span>
                                <?php endif; ?>
                                <?php if ((int)$d['is_spicy'] === 1): ?>
                                    <span style="font-size: 0.72rem; background: rgba(253, 231, 229, 0.85); color: #b3261e; padding: 0.1rem 0.45rem; border-radius: 9999px; font-weight: 700;">🌶️ Spicy</span>
                                <?php endif; ?>
                                <span style="font-size: 0.75rem; color: var(--ink-500);">⏱️ <?= e((string)$d['prep_time_minutes']); ?>m</span>
                            </div>
                        </td>
                        <td>
                            <span class="role-pill" style="font-size: 0.82rem;">
                                <?= e($d['category_icon'] . ' ' . $d['category_name']); ?>
                            </span>
                        </td>
                        <td style="font-weight: 700; color: var(--primary-600); font-size: 1rem;">
                            <?= e(format_price($d['price'])); ?>
                        </td>
                        <td>
                            <?= $stockBadge; ?>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="status-pill status-active"><span class="dot"></span>Active</span>
                            <?php else: ?>
                                <span class="status-pill status-inactive"><span class="dot"></span>Hidden (Soft-Deleted)</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 0.45rem;">
                                <a href="<?= e(url('menu/manage_dishes.php?edit=' . $d['id'])); ?>" class="btn btn-secondary btn-sm" title="Edit Dish Details">
                                    Edit
                                </a>
                                <a href="<?= e(url('menu/stock.php?dish_id=' . $d['id'])); ?>" class="btn btn-outline btn-sm" title="Adjust Inventory">
                                    Stock
                                </a>
                                <form method="POST" action="<?= e(url('menu/manage_dishes.php')); ?>" style="display: inline;" data-confirm-form="<?= $isActive ? 'Soft-delete this dish? It will be hidden from the public menu but historical order records will remain intact.' : 'Restore this dish to active public menu?'; ?>">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="dish_id" value="<?= e((string)$d['id']); ?>">
                                    <input type="hidden" name="status" value="<?= $isActive ? '0' : '1'; ?>">
                                    <?php if ($isActive): ?>
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger-500);" title="Soft Delete">
                                            Hide
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--success-500);" title="Restore">
                                            Restore
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add New Dish Modal -->
<div class="modal-overlay" id="create-dish-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">+ Add New Cloud Kitchen Dish</h3>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('create-dish-modal').classList.remove('active')">&times;</button>
        </div>

        <form method="POST" action="<?= e(url('menu/manage_dishes.php')); ?>" enctype="multipart/form-data">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="create_dish">

            <div class="form-group">
                <label for="new-name">Dish Name <span class="required">*</span></label>
                <input type="text" id="new-name" name="name" class="form-control" placeholder="e.g. Seafood Cheese Kottu" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="new-cat">Category <span class="required">*</span></label>
                    <select id="new-cat" name="category_id" class="form-control" required>
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e((string)$cat['id']); ?>">
                                <?= e($cat['icon'] . ' ' . $cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="new-price">Price in LKR <span class="required">*</span></label>
                    <input type="number" id="new-price" name="price" class="form-control" step="0.50" min="1" placeholder="1850.00" required>
                </div>
            </div>

            <div class="form-group">
                <label for="new-desc">Description &amp; Ingredients <span class="required">*</span></label>
                <textarea id="new-desc" name="description" class="form-control" rows="3" placeholder="Provide mouth-watering culinary details..." required></textarea>
            </div>

            <div class="form-group">
                <label>Dish Photo (Max 2MB: JPG, PNG, WEBP)</label>
                <div class="image-upload-wrapper">
                    <div class="image-preview-thumbnail">
                        <img src="<?= e(url('public/uploads/chicken_kottu.jpg')); ?>" id="new-img-preview" alt="Preview">
                    </div>
                    <div style="flex: 1;">
                        <input 
                            type="file" 
                            name="image" 
                            id="new-dish-file-input" 
                            class="form-control" 
                            accept=".jpg,.jpeg,.png,.webp"
                            onchange="previewImage(this, 'new-img-preview')"
                        >
                        <span class="input-hint">Checked securely on server for binary MIME integrity.</span>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="new-prep">Prep Time (minutes)</label>
                    <input type="number" id="new-prep" name="prep_time_minutes" class="form-control" value="15" min="1">
                </div>
                <div class="form-group">
                    <label for="new-cal">Calories (optional)</label>
                    <input type="number" id="new-cal" name="calories" class="form-control" placeholder="e.g. 650" min="0">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="new-initial-stock">Initial Stock Portions</label>
                    <input type="number" id="new-initial-stock" name="initial_stock" class="form-control" value="25" min="0">
                </div>
                <div class="form-group">
                    <label for="new-reorder">Low Stock Alert Level</label>
                    <input type="number" id="new-reorder" name="reorder_level" class="form-control" value="10" min="1">
                </div>
            </div>

            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem;">
                <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_vegetarian" value="1">
                    <span>🌱 Vegetarian Dish</span>
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_spicy" value="1" checked>
                    <span>🌶️ Spicy Recipe</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('create-dish-modal').classList.remove('active')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-new-dish">
                    Create Dish &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Live Image Preview Helper (Minimal vanilla JS enhancement)
    function previewImage(input, previewElementId) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            // Check size on client
            if (file.size > 2 * 1024 * 1024) {
                alert('Image exceeds maximum allowable limit of 2MB.');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewImg = document.getElementById(previewElementId);
                if (previewImg) {
                    previewImg.src = e.target.result;
                }
            };
            reader.readAsDataURL(file);
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
