<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

$stmt = $pdo->prepare("SELECT d.name, SUM(oi.quantity) AS total_qty,
               SUM(oi.quantity * oi.price_at_purchase) AS revenue
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
        JOIN dishes d ON d.dish_id = oi.dish_id
        WHERE o.created_at BETWEEN ? AND ? AND o.status <> 'Cancelled'
        GROUP BY d.dish_id, d.name
        ORDER BY total_qty DESC
        LIMIT 5");
$stmt->execute($range);
$top = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT status, COUNT(*) AS cnt, SUM(total_amount) AS sales
                        FROM orders WHERE created_at BETWEEN ? AND ?
                        GROUP BY status");
$stmt2->execute($range);
$byStatus = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Sales Report</title></head>
<body>
  <h1>Sales Report</h1>
  <form method="get">
    <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
    <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
    <button type="submit">Filter</button>
  </form>

  <h2>Orders per status</h2>
  <table>
    <tr><th>Status</th><th>Orders</th><th>Sales (LKR)</th></tr>
    <?php foreach ($byStatus as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td><?= (int)$r['cnt'] ?></td>
        <td><?= number_format((float)$r['sales'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Top-selling dishes</h2>
  <table>
    <tr><th>Dish</th><th>Qty Sold</th><th>Revenue (LKR)</th></tr>
    <?php foreach ($top as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= (int)$row['total_qty'] ?></td>
        <td><?= number_format((float)$row['revenue'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>