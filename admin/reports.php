<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Administrative Sales Reports & Analytics
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

$topDishes = [];
$byStatus = [];
$grossSales = 0.00;
$totalOrders = 0;
$avgOrderValue = 0.00;

if ($pdo) {
    // 1. Top-selling dishes
    $stmt = $pdo->prepare(
        "SELECT d.name, SUM(oi.quantity) AS total_qty,
                SUM(oi.quantity * oi.price_at_purchase) AS revenue
         FROM order_items oi
         JOIN orders o ON o.order_id = oi.order_id
         JOIN dishes d ON d.dish_id = oi.dish_id
         WHERE o.created_at BETWEEN ? AND ? AND o.status <> 'Cancelled'
         GROUP BY d.dish_id, d.name
         ORDER BY total_qty DESC
         LIMIT 6"
    );
    $stmt->execute($range);
    $topDishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Orders per status
    $stmt2 = $pdo->prepare(
        "SELECT status, COUNT(*) AS cnt, SUM(total_amount) AS sales
         FROM orders 
         WHERE created_at BETWEEN ? AND ?
         GROUP BY status"
    );
    $stmt2->execute($range);
    $byStatus = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // 3. Aggregate totals
    foreach ($byStatus as $r) {
        $totalOrders += (int)$r['cnt'];
        if ($r['status'] !== 'Cancelled') {
            $grossSales += (float)$r['sales'];
        }
    }
    $avgOrderValue = ($totalOrders > 0) ? ($grossSales / $totalOrders) : 0.00;
}

$pageTitle = 'Sales Analytics & Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 1.5rem; padding-bottom: 3.5rem;">
    
    <!-- Title & Date Filter Bar -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
                Business Intelligence &bull; Analytics
            </span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0.25rem 0;">
                Executive Sales Report
            </h1>
            <p style="color: var(--ink-500); font-size: 1rem;">
                Aggregate financial metrics, order breakdown, and top-selling dishes.
            </p>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="<?= e(url('admin/reports.php')); ?>" style="display: flex; gap: 0.75rem; align-items: flex-end; background: var(--bg-surface); padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-default);">
            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--ink-500); margin-bottom: 0.2rem;">From</label>
                <input type="date" name="from" value="<?= e($from); ?>" class="form-control" style="padding: 0.4rem 0.6rem; font-size: 0.88rem;">
            </div>
            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--ink-500); margin-bottom: 0.2rem;">To</label>
                <input type="date" name="to" value="<?= e($to); ?>" class="form-control" style="padding: 0.4rem 0.6rem; font-size: 0.88rem;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.25rem; font-size: 0.88rem; font-weight: 700;">
                Filter
            </button>
        </form>
    </div>

    <!-- Stat Summary Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 2rem;">
        <div class="card">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--ink-500);">Gross Revenue</span>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--primary-600); margin-top: 0.25rem;">
                LKR <?= number_format($grossSales, 2); ?>
            </div>
        </div>

        <div class="card">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--ink-500);">Total Orders Placed</span>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--ink-900); margin-top: 0.25rem;">
                <?= $totalOrders; ?>
            </div>
        </div>

        <div class="card">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--ink-500);">Average Order Value</span>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--success-600); margin-top: 0.25rem;">
                LKR <?= number_format($avgOrderValue, 2); ?>
            </div>
        </div>
    </div>

    <!-- Two-column tables -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        
        <!-- Status Breakdown Table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-default); background: var(--bg-surface);">
                <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--ink-900); margin: 0;">
                    Orders per Status Breakdown
                </h2>
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-default); background: var(--bg-surface-alt); text-align: left; font-size: 0.85rem; color: var(--ink-500);">
                        <th style="padding: 0.85rem 1.25rem;">Status</th>
                        <th style="padding: 0.85rem 1.25rem; text-align: center;">Orders</th>
                        <th style="padding: 0.85rem 1.25rem; text-align: right;">Sales (LKR)</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid var(--border-default);">
                    <?php if (empty($byStatus)): ?>
                        <tr><td colspan="3" style="padding: 2rem; text-align: center; color: var(--ink-400);">No orders found for selected date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($byStatus as $r): ?>
                            <tr style="border-bottom: 1px solid var(--border-default); font-size: 0.92rem;">
                                <td style="padding: 0.85rem 1.25rem; font-weight: 600; color: var(--ink-900);">
                                    <?= e($r['status']); ?>
                                </td>
                                <td style="padding: 0.85rem 1.25rem; text-align: center; color: var(--ink-700);">
                                    <?= (int)$r['cnt']; ?>
                                </td>
                                <td style="padding: 0.85rem 1.25rem; text-align: right; font-weight: 700; color: var(--ink-900);">
                                    <?= number_format((float)$r['sales'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Top-Selling Dishes Table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-default); background: var(--bg-surface);">
                <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--ink-900); margin: 0;">
                    Top-Selling Kitchen Dishes
                </h2>
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-default); background: var(--bg-surface-alt); text-align: left; font-size: 0.85rem; color: var(--ink-500);">
                        <th style="padding: 0.85rem 1.25rem;">Dish Name</th>
                        <th style="padding: 0.85rem 1.25rem; text-align: center;">Portions Sold</th>
                        <th style="padding: 0.85rem 1.25rem; text-align: right;">Revenue (LKR)</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid var(--border-default);">
                    <?php if (empty($topDishes)): ?>
                        <tr><td colspan="3" style="padding: 2rem; text-align: center; color: var(--ink-400);">No item sales found for selected date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topDishes as $row): ?>
                            <tr style="border-bottom: 1px solid var(--border-default); font-size: 0.92rem;">
                                <td style="padding: 0.85rem 1.25rem; font-weight: 600; color: var(--ink-900);">
                                    <?= e($row['name']); ?>
                                </td>
                                <td style="padding: 0.85rem 1.25rem; text-align: center; color: var(--ink-700);">
                                    <?= (int)$row['total_qty']; ?>
                                </td>
                                <td style="padding: 0.85rem 1.25rem; text-align: right; font-weight: 700; color: var(--ink-900);">
                                    <?= number_format((float)$row['revenue'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
