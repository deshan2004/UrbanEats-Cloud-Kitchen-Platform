<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_functions.php';

require_role('kitchen');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    change_order_status(
        $pdo,
        (int)$_POST['order_id'],
        $_POST['new_status'],
        (int)$_SESSION['user_id']
    );
    header('Location: kitchen_queue.php');
    exit;
}

$orders = $pdo->query("SELECT order_id, status FROM orders
                       WHERE status NOT IN ('Completed','Cancelled')
                       ORDER BY created_at")->fetchAll(PDO::FETCH_ASSOC);
$transitions = allowed_transitions();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Kitchen Queue</title></head>
<body>
  <h1>Kitchen Order Queue</h1>
  <?php foreach ($orders as $o): ?>
    <p>Order #<?= (int)$o['order_id'] ?> - <?= htmlspecialchars($o['status']) ?></p>
    <?php foreach ($transitions[$o['status']] as $next): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
        <input type="hidden" name="new_status" value="<?= htmlspecialchars($next) ?>">
        <button type="submit"><?= htmlspecialchars($next) ?></button>
      </form>
    <?php endforeach; ?>
  <?php endforeach; ?>
</body>
</html>
