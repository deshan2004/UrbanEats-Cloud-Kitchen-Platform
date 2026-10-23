<?php
// orders/kitchen_queue.php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_role('kitchen_staff'); // Ensure only kitchen staff can view

// Fetch orders grouped by stage
if ($pdo instanceof PDO) {
    $placed_orders = $pdo->query("SELECT * FROM orders WHERE status = 'Placed' ORDER BY created_at ASC")->fetchAll();
    $preparing_orders = $pdo->query("SELECT * FROM orders WHERE status = 'Preparing' ORDER BY created_at ASC")->fetchAll();
    $ready_orders = $pdo->query("SELECT * FROM orders WHERE status = 'Ready' ORDER BY created_at ASC")->fetchAll();
} else {
    $placed_orders = [];
    $preparing_orders = [];
    $ready_orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kitchen Order Queue - UrbanEats</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container">
        <header class="page-header">
            <div>
                <h1>Order Queue</h1>
                <p>Move each order through the kitchen workflow. Only valid next steps are shown.</p>
            </div>
            <div class="filter-tabs">
                <button class="active">Active orders (8)</button>
                <button>Completed today (41)</button>
                <button>Cancelled (2)</button>
            </div>
        </header>

        <div class="kanban-board">
            <!-- Placed Column -->
            <div class="kanban-column">
                <div class="column-header tag-blue">Placed (<?= count($placed_orders) ?>)</div>
                <?php foreach ($placed_orders as $order): ?>
                <div class="order-card">
                    <div class="card-top">
                        <strong>#<?= htmlspecialchars($order['order_code']) ?></strong>
                        <span class="time"><?= date('H:i', strtotime($order['created_at'])) ?></span>
                    </div>
                    <p class="items-summary">2 x Chicken Kottu, 1 x Mango Lassi, 2 x Watalappan</p>
                    <div class="card-bottom">
                        <span class="badge"><?= htmlspecialchars($order['order_type']) ?></span>
                        <strong>LKR <?= number_format($order['total_amount'], 2) ?></strong>
                    </div>
                    <form action="update_status.php" method="POST">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="next_status" value="Preparing">
                        <button type="submit" class="btn-primary full-width">Start preparing</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Preparing Column -->
            <div class="kanban-column">
                <div class="column-header tag-orange">Preparing (<?= count($preparing_orders) ?>)</div>
                <!-- Card example -->
            </div>

            <!-- Ready Column -->
            <div class="kanban-column">
                <div class="column-header tag-green">Ready (<?= count($ready_orders) ?>)</div>
                <!-- Card example -->
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
