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
        $isPickup = ($r['order_type'] === 'pickup');
        $statusTitle = 'Order Update';
        $statusMsg = 'Order placed';
        $icon = '📋';
        $themeColor = '#F26B1D';
        $isDeliveredFeedback = false;

        switch ($status) {
            case 'Placed':
                $statusTitle = '⏳ Order Received';
                $statusMsg = 'Your order is waiting for kitchen confirmation.';
                $icon = '⏳';
                $themeColor = '#D97706';
                break;
            case 'Preparing':
                $statusTitle = '🍳 Order Approved & Cooking!';
                $statusMsg = 'The chef has accepted your order and is cooking your dishes fresh!';
                $icon = '🍳';
                $themeColor = '#059669';
                break;
            case 'Ready':
                if ($isPickup) {
                    $statusTitle = '🏪 Ready for Kitchen Pickup!';
                    $statusMsg = 'Your order is packaged and waiting at our kitchen counter. Please come collect it!';
                    $icon = '🏪';
                    $themeColor = '#0D9488';
                } else {
                    $statusTitle = '📦 Packaged & Quality Checked';
                    $statusMsg = 'Your meal is packed and our rider is being assigned for pickup.';
                    $icon = '📦';
                    $themeColor = '#059669';
                }
                break;
            case 'Out for Delivery':
                $statusTitle = '🛵 Out for Delivery!';
                $statusMsg = 'Our rider is on the way with your order! Keep your phone nearby.';
                $icon = '🛵';
                $themeColor = '#2563EB';
                break;
            case 'Collected':
                $statusTitle = '🏪 Order Handed Over & Collected';
                $statusMsg = 'Thank you for picking up your food! Have a wonderful meal!';
                $icon = '🍽️';
                $themeColor = '#10B981';
                break;
            case 'Completed':
                if (!$isPickup) {
                    $statusTitle = '🍽️ Order Delivered! Did you receive it?';
                    $statusMsg = 'Your delivery is completed. Has your order arrived safely? Enjoy your meal!';
                    $icon = '🛵';
                    $themeColor = '#10B981';
                    $isDeliveredFeedback = true;
                } else {
                    $statusTitle = '🍽️ Order Completed!';
                    $statusMsg = 'Counter pickup complete. Thank you for dining with UrbanEats!';
                    $icon = '🍽️';
                    $themeColor = '#10B981';
                }
                break;
            case 'Cancelled':
                $statusTitle = '✕ Order Cancelled';
                $statusMsg = 'This order has been cancelled.';
                $icon = '✕';
                $themeColor = '#DC2626';
                break;
        }

        $orders[] = [
            'order_id'              => (int)$r['order_id'],
            'order_code'            => $r['order_code'],
            'status'                => $status,
            'status_title'          => $statusTitle,
            'status_message'        => $statusMsg,
            'icon'                  => $icon,
            'theme_color'           => $themeColor,
            'is_delivered_feedback' => $isDeliveredFeedback,
            'order_type'            => $r['order_type'],
            'total_formatted'       => 'LKR ' . number_format((float)$r['total_amount'], 2),
            'items_summary'         => $r['items_summary'] ?: 'Kitchen Dishes',
            'updated_at'            => $r['updated_at'],
            'tracking_url'          => url('orders/tracking.php?code=' . urlencode($r['order_code'])),
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
