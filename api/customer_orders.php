<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Customer Real-time Order Status & Notification API
 * 
 * Provides live polling data for active customer orders to deliver instant
 * sound and toast notifications when an administrator or kitchen staff
 * approves or updates their order.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Customer authentication required.'
    ]);
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database service unavailable.'
    ]);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare(
        "SELECT o.order_id, o.order_code, o.status, o.total_amount, o.order_type, o.updated_at, o.created_at,
                GROUP_CONCAT(CONCAT(oi.quantity, 'x ', d.name) SEPARATOR ', ') AS items_summary
         FROM orders o
         LEFT JOIN order_items oi ON o.order_id = oi.order_id
         LEFT JOIN dishes d ON oi.dish_id = d.dish_id
         WHERE o.user_id = :user_id
           AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY o.order_id
         ORDER BY o.order_id DESC
         LIMIT 5"
    );
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    foreach ($rows as $r) {
        $status = $r['status'];
        $statusMsg = 'Order placed';
        $icon = '📋';

        switch ($status) {
            case 'Placed':
                $statusMsg = 'Order received & waiting for kitchen approval';
                $icon = '⏳';
                break;
            case 'Preparing':
                $statusMsg = 'Order approved! The kitchen is cooking your meal';
                $icon = '🍳';
                break;
            case 'Ready':
                $statusMsg = ($r['order_type'] === 'pickup') 
                    ? 'Your meal is ready for counter pickup!' 
                    : 'Order packaged & awaiting rider dispatch!';
                $icon = '📦';
                break;
            case 'Out for Delivery':
                $statusMsg = 'Our rider is on the way to your address!';
                $icon = '🛵';
                break;
            case 'Collected':
                $statusMsg = 'Order handed over at kitchen counter!';
                $icon = '🏪';
                break;
            case 'Completed':
                $statusMsg = 'Order fulfilled! Enjoy your meal!';
                $icon = '🍽️';
                break;
            case 'Cancelled':
                $statusMsg = 'This order has been cancelled.';
                $icon = '✕';
                break;
        }

        $orders[] = [
            'order_id'        => (int)$r['order_id'],
            'order_code'      => $r['order_code'],
            'status'          => $status,
            'status_message'  => $statusMsg,
            'icon'            => $icon,
            'order_type'      => $r['order_type'],
            'total_formatted' => 'LKR ' . number_format((float)$r['total_amount'], 2),
            'items_summary'   => $r['items_summary'] ?: 'Kitchen Dishes',
            'updated_at'      => $r['updated_at'],
            'tracking_url'    => url('orders/tracking.php?code=' . urlencode($r['order_code'])),
        ];
    }

    echo json_encode([
        'success' => true,
        'orders'  => $orders,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Customer live status query failed: ' . $e->getMessage()
    ]);
}
