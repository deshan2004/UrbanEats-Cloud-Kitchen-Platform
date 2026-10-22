<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Menu Catalogue & Public Browsing
 * 
 * Features:
 * - Dynamic Category Filtering with live item counters
 * - Debounced/Form Keyword Search across dish names and descriptions
 * - Whitelisted Sorting Engine (Price asc/desc, Name asc/desc, Newest) to prevent SQL Injection
 * - Server-Side Pagination with parameterized URL preservation
 * - Real-Time Inventory Badges (In Stock, Low Stock Alert, Sold Out)
 * - Quick Actions for Kitchen Staff & Administrators
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Menu Catalogue';
$currentUser = current_user();
$isStaff = $currentUser && in_array($currentUser['role'], ['kitchen_staff', 'admin'], true);

// -------------------------------------------------------------------------
// 1. Validate & Whitelist Query Parameters (Addressing Viva Security Requirements)
// -------------------------------------------------------------------------

// Search Keyword
$searchQuery = trim((string)($_GET['q'] ?? ''));

// Category Filter (by slug or empty for All)
$selectedCategorySlug = trim((string)($_GET['cat'] ?? ''));

// Sort Whitelisting: Maps client GET parameter to immutable SQL ORDER BY fragments
$sortParam = trim((string)($_GET['sort'] ?? 'newest'));
$sortMap = [
    'newest'     => 'd.id DESC',
    'price_asc'  => 'd.price ASC',
    'price_desc' => 'd.price DESC',
    'name_asc'   => 'd.name ASC',
    'name_desc'  => 'd.name DESC',
];
$orderBySql = $sortMap[$sortParam] ?? 'd.id DESC';

// Pagination Parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

// -------------------------------------------------------------------------
// 2. Fetch Active Categories with Dish Counts
// -------------------------------------------------------------------------
try {
    $catStmt = $pdo->query(
        "SELECT c.id, c.name, c.slug, c.icon, COUNT(d.id) AS dish_count
         FROM categories c
         LEFT JOIN dishes d ON d.category_id = c.id AND d.is_active = 1
         WHERE c.is_active = 1
         GROUP BY c.id, c.name, c.slug, c.icon, c.display_order
         ORDER BY c.display_order ASC, c.name ASC"
    );
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    error_log("[Catalogue Error] Failed to load categories: " . $e->getMessage());
    $categories = [];
}

// Total active dishes across all categories
$totalAllDishes = 0;
foreach ($categories as $cat) {
    $totalAllDishes += (int)$cat['dish_count'];
}

// -------------------------------------------------------------------------
// 3. Construct Prepared Query for Dish Listing
// -------------------------------------------------------------------------
$whereClauses = ['d.is_active = 1'];
$queryParams  = [];

// Apply category filter if requested
if ($selectedCategorySlug !== '') {
    $whereClauses[] = 'c.slug = :category_slug';
    $queryParams[':category_slug'] = $selectedCategorySlug;
}

// Apply keyword search if provided
if ($searchQuery !== '') {
    $whereClauses[] = '(d.name LIKE :search_name OR d.description LIKE :search_desc)';
    $queryParams[':search_name'] = '%' . $searchQuery . '%';
    $queryParams[':search_desc'] = '%' . $searchQuery . '%';
}

$whereSql = implode(' AND ', $whereClauses);

// Count total matching items for pagination
try {
    $countSql = "SELECT COUNT(*) 
                 FROM dishes d
                 JOIN categories c ON c.id = d.category_id
                 WHERE {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($queryParams);
    $totalMatchingDishes = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("[Catalogue Error] Count query failed: " . $e->getMessage());
    $totalMatchingDishes = 0;
}

$totalPages = max(1, (int)ceil($totalMatchingDishes / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch paginated dishes with category info and live inventory
try {
    $dishesSql = "SELECT 
                    d.id, d.name, d.slug, d.description, d.price, d.image_url,
                    d.is_vegetarian, d.is_spicy, d.prep_time_minutes, d.calories,
                    c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    COALESCE(i.quantity, 0) AS stock_quantity,
                    COALESCE(i.reorder_level, 10) AS reorder_level
                  FROM dishes d
                  JOIN categories c ON c.id = d.category_id
                  LEFT JOIN inventory i ON i.dish_id = d.id
                  WHERE {$whereSql}
                  ORDER BY {$orderBySql}
                  LIMIT :limit OFFSET :offset";

    $dishStmt = $pdo->prepare($dishesSql);
    foreach ($queryParams as $paramKey => $paramVal) {
        $dishStmt->bindValue($paramKey, $paramVal, PDO::PARAM_STR);
    }
    $dishStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dishStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dishStmt->execute();
    $dishes = $dishStmt->fetchAll();
} catch (PDOException $e) {
    error_log("[Catalogue Error] Dishes query failed: " . $e->getMessage());
    $dishes = [];
}

// Helper to preserve active filter parameters in pagination links
function catalogue_url(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return url('menu/index.php') . (!empty($params) ? '?' . http_build_query($params) : '');
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Toolbar & Title Area -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.35rem;">
            <span style="font-size: 1.6rem;">🍲</span>
            <span style="text-transform: uppercase; font-size: 0.8rem; font-weight: 700; color: var(--primary-600); letter-spacing: 0.05em;">
                Component B &bull; Freshly Prepared Cloud Kitchen
            </span>
        </div>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); letter-spacing: -0.025em; margin-bottom: 0.25rem;">
            Our Cloud Kitchen Menu
        </h1>
        <p style="color: var(--ink-500); font-size: 0.95rem;">
            Browse authentic Sri Lankan street specials, slow-cooked curries, biryanis, and chef desserts.
        </p>
    </div>

    <?php if ($isStaff): ?>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-primary btn-sm" id="btn-staff-manage-dishes">
                + Manage Dishes
            </a>
            <a href="<?= e(url('menu/manage_categories.php')); ?>" class="btn btn-secondary btn-sm" id="btn-staff-manage-categories">
                Categories
            </a>
            <a href="<?= e(url('menu/stock.php')); ?>" class="btn btn-secondary btn-sm" id="btn-staff-stock-dashboard">
                Stock Dashboard &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Search, Filter & Sorting Bar -->
<form method="GET" action="<?= e(url('menu/index.php')); ?>" class="filter-bar" id="catalogue-filter-form">
    <?php if ($selectedCategorySlug !== ''): ?>
        <input type="hidden" name="cat" value="<?= e($selectedCategorySlug); ?>">
    <?php endif; ?>

    <!-- Keyword Search Field -->
    <div class="search-input-wrapper">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input 
            type="text" 
            name="q" 
            value="<?= e($searchQuery); ?>" 
            placeholder="Search by dish name, spice, or ingredients..." 
            class="search-input-field"
            id="catalogue-search-input"
        >
    </div>

    <!-- Sorting Dropdown (Whitelisted parameters) -->
    <select name="sort" class="filter-select" id="catalogue-sort-select" onchange="this.form.submit()">
        <option value="newest" <?= $sortParam === 'newest' ? 'selected' : ''; ?>>Newest Arrivals</option>
        <option value="price_asc" <?= $sortParam === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sortParam === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
        <option value="name_asc" <?= $sortParam === 'name_asc' ? 'selected' : ''; ?>>Name: A &rarr; Z</option>
        <option value="name_desc" <?= $sortParam === 'name_desc' ? 'selected' : ''; ?>>Name: Z &rarr; A</option>
    </select>

    <button type="submit" class="btn btn-primary" id="btn-search-submit">
        Search
    </button>

    <?php if ($searchQuery !== '' || $selectedCategorySlug !== '' || $sortParam !== 'newest'): ?>
        <a href="<?= e(url('menu/index.php')); ?>" class="btn btn-outline" style="padding: 0.7rem 1rem;" title="Reset all filters">
            Clear Filters
        </a>
    <?php endif; ?>
</form>

<!-- Category Pill Navigation Bar -->
<div class="category-nav-wrapper">
    <ul class="category-pill-list" role="tablist">
        <li>
            <a href="<?= e(catalogue_url(['cat' => null, 'page' => 1])); ?>" 
               class="category-pill <?= $selectedCategorySlug === '' ? 'active' : ''; ?>"
               id="cat-pill-all">
                <span class="cat-icon">🍽️</span>
                <span>All Dishes</span>
                <span class="cat-count"><?= e((string)$totalAllDishes); ?></span>
            </a>
        </li>
        <?php foreach ($categories as $cat): ?>
            <li>
                <a href="<?= e(catalogue_url(['cat' => $cat['slug'], 'page' => 1])); ?>" 
                   class="category-pill <?= $selectedCategorySlug === $cat['slug'] ? 'active' : ''; ?>"
                   id="cat-pill-<?= e($cat['slug']); ?>">
                    <span class="cat-icon"><?= e($cat['icon'] ?: '🍲'); ?></span>
                    <span><?= e($cat['name']); ?></span>
                    <span class="cat-count"><?= e((string)$cat['dish_count']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- Active Filter Status Indicator -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div style="font-size: 0.92rem; color: var(--ink-700);">
        Showing <strong><?= e((string)count($dishes)); ?></strong> of <strong><?= e((string)$totalMatchingDishes); ?></strong> delicious dishes
        <?php if ($searchQuery !== ''): ?>
            matching &ldquo;<em><?= e($searchQuery); ?></em>&rdquo;
        <?php endif; ?>
    </div>
</div>

<!-- Dishes Catalogue Grid -->
<?php if (empty($dishes)): ?>
    <div class="card" style="text-align: center; padding: 4rem 2rem;">
        <span style="font-size: 3.5rem; display: block; margin-bottom: 1rem;">🔍</span>
        <h3 style="font-size: 1.35rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">
            No dishes found matching your criteria
        </h3>
        <p style="color: var(--ink-500); max-width: 480px; margin: 0 auto 1.5rem;">
            Try relaxing your search keywords or switching category filters to explore more of our cloud kitchen menu.
        </p>
        <a href="<?= e(url('menu/index.php')); ?>" class="btn btn-primary btn-sm">
            Reset Menu Filters
        </a>
    </div>
<?php else: ?>
    <div class="menu-grid" id="dishes-grid">
        <?php foreach ($dishes as $dish): 
            $qty = (int)$dish['stock_quantity'];
            $reorder = (int)$dish['reorder_level'];
            
            if ($qty <= 0) {
                $stockStatus = 'out';
                $stockLabel = 'Sold Out';
                $stockClass = 'stock-badge-out';
            } elseif ($qty <= $reorder) {
                $stockStatus = 'low';
                $stockLabel = 'Only ' . $qty . ' Left!';
                $stockClass = 'stock-badge-low';
            } else {
                $stockStatus = 'in';
                $stockLabel = 'In Stock (' . $qty . ')';
                $stockClass = 'stock-badge-in';
            }

            $imgSrc = !empty($dish['image_url']) ? url('public/' . ltrim($dish['image_url'], '/')) : url('public/images/default_dish.jpg');
        ?>
            <article class="dish-card" id="dish-card-<?= e((string)$dish['id']); ?>">
                <!-- Media & Badges -->
                <div class="dish-media">
                    <img 
                        src="<?= e($imgSrc); ?>" 
                        alt="<?= e($dish['name']); ?>" 
                        loading="lazy"
                        onerror="this.src='<?= e(url('public/uploads/chicken_kottu.jpg')); ?>'"
                    >
                    
                    <!-- Dietary / Spicy Tags -->
                    <div class="dish-badges-overlay">
                        <?php if ((int)$dish['is_vegetarian'] === 1): ?>
                            <span class="badge-tag badge-veg" title="Vegetarian Dish">🌱 Veg</span>
                        <?php endif; ?>
                        <?php if ((int)$dish['is_spicy'] === 1): ?>
                            <span class="badge-tag badge-spicy" title="Spicy Dish">🌶️ Spicy</span>
                        <?php endif; ?>
                    </div>

                    <!-- Live Inventory Badge -->
                    <div class="dish-stock-overlay">
                        <span class="stock-badge <?= e($stockClass); ?>" id="stock-badge-dish-<?= e((string)$dish['id']); ?>">
                            <span class="dot"></span>
                            <?= e($stockLabel); ?>
                        </span>
                    </div>

                    <!-- Prep Time -->
                    <div class="dish-prep-overlay">
                        ⏱️ <?= e((string)$dish['prep_time_minutes']); ?> mins
                    </div>
                </div>

                <!-- Body Content -->
                <div class="dish-content">
                    <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--primary-600); margin-bottom: 0.25rem;">
                        <?= e($dish['category_icon'] . ' ' . $dish['category_name']); ?>
                    </div>
                    
                    <h2 class="dish-title">
                        <a href="<?= e(url('menu/detail.php?id=' . $dish['id'])); ?>">
                            <?= e($dish['name']); ?>
                        </a>
                    </h2>

                    <p class="dish-description">
                        <?= e($dish['description']); ?>
                    </p>

                    <!-- Footer: Price & Action CTA -->
                    <div class="dish-footer">
                        <div class="dish-price-block">
                            <span class="dish-price-currency">Price</span>
                            <span class="dish-price-value"><?= e(format_price($dish['price'])); ?></span>
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <a href="<?= e(url('menu/detail.php?id=' . $dish['id'])); ?>" class="btn btn-secondary btn-sm" id="btn-view-dish-<?= e((string)$dish['id']); ?>">
                                View Details &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <!-- Server-Side Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <nav class="pagination-wrapper" aria-label="Catalogue pagination">
            <div class="pagination-info">
                Page <strong><?= e((string)$page); ?></strong> of <strong><?= e((string)$totalPages); ?></strong>
            </div>

            <ul class="pagination-controls">
                <!-- Previous Page Button -->
                <?php if ($page > 1): ?>
                    <li>
                        <a href="<?= e(catalogue_url(['page' => $page - 1])); ?>" class="page-btn btn-nav" id="btn-page-prev">
                            &larr; Prev
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Numbered Page Links -->
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li>
                        <a href="<?= e(catalogue_url(['page' => $p])); ?>" 
                           class="page-btn <?= $p === $page ? 'active' : ''; ?>"
                           id="btn-page-<?= $p; ?>">
                            <?= $p; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <!-- Next Page Button -->
                <?php if ($page < $totalPages): ?>
                    <li>
                        <a href="<?= e(catalogue_url(['page' => $page + 1])); ?>" class="page-btn btn-nav" id="btn-page-next">
                            Next &rarr;
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
