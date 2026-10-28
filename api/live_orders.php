<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Real-time Order Monitoring API (Component C & Admin Engine)
 * 
 * Provides live polling data for incoming culinary orders, kitchen stage counts,
 * and instant notification triggers.
 */

declare(strict_types=1);

// HTTP Headers: Prevent proxy/browser caching of real-time operational polling data
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_functions.php';

// Route Guard: Enforce strict role authorization for asynchronous API endpoints (Returns JSON 403)
if (!is_logged_in() || !in_array($_SESSION['role'] ?? '', ['admin', 'kitchen_staff'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Administrative or kitchen staff role required.'
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

// Handle Direct Order Approval via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $action = $payload['action'] ?? '';
    $orderId = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;

    if ($action === 'approve' && $orderId > 0) {
        $ok = change_order_status($pdo, $orderId, 'Preparing', (int)$_SESSION['user_id']);
        if ($ok) {
            echo json_encode([
                'success'    => true,
                'message'    => 'Order approved and sent to kitchen stove!',
                'order_id'   => $orderId,
                'new_status' => 'Preparing'
            ]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Unable to approve order. It may have already transitioned or been cancelled.'
            ]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action requested.']);
    exit;
}

try {
    $sinceId = isset($_GET['since_id']) ? max(0, (int)$_GET['since_id']) : 0;

    // 1. Get current maximum order_id
    $maxStmt = $pdo->query("SELECT COALESCE(MAX(order_id), 0) FROM orders");
    $maxOrderId = (int)$maxStmt->fetchColumn();

    // 2. Fetch live metrics across kitchen stages
    $metricsStmt = $pdo->query(
        "SELECT 
            COUNT(CASE WHEN status NOT IN ('Completed', 'Cancelled') THEN 1 END) AS active_count,
            COUNT(CASE WHEN status = 'Placed' THEN 1 END) AS placed_count,
            COUNT(CASE WHEN status = 'Preparing' THEN 1 END) AS preparing_count,
            COUNT(CASE WHEN status = 'Ready' THEN 1 END) AS ready_count,
            COUNT(CASE WHEN status = 'Completed' AND DATE(created_at) = CURDATE() THEN 1 END) AS completed_today,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status != 'Cancelled' THEN total_amount ELSE 0 END), 0) AS revenue_today
         FROM orders"
    );
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 3. Query new orders if since_id > 0
    $newOrders = [];
    if ($sinceId > 0 && $maxOrderId > $sinceId) {
        $stmtNew = $pdo->prepare(
            "SELECT o.order_id, o.order_code, o.order_type, o.total_amount, o.status, o.created_at,
                    u.full_name AS customer_name,
                    GROUP_CONCAT(CONCAT(oi.quantity, 'x ', d.name) SEPARATOR ', ') AS items_summary
             FROM orders o
             JOIN users u ON o.user_id = u.id
             LEFT JOIN order_items oi ON o.order_id = oi.order_id
             LEFT JOIN dishes d ON oi.dish_id = d.dish_id
             WHERE o.order_id > :since_id AND o.status != 'Cancelled'
             GROUP BY o.order_id
             ORDER BY o.order_id ASC"
        );
        $stmtNew->execute([':since_id' => $sinceId]);

        while ($r = $stmtNew->fetch(PDO::FETCH_ASSOC)) {
            $createdTime = strtotime($r['created_at']);
            $diffSeconds = max(0, time() - $createdTime);
            if ($diffSeconds < 60) {
                $timeAgo = 'Just now';
            } elseif ($diffSeconds < 3600) {
                $timeAgo = floor($diffSeconds / 60) . 'm ago';
            } else {
                $timeAgo = floor($diffSeconds / 3600) . 'h ago';
            }

            $newOrders[] = [
                'order_id'        => (int)$r['order_id'],
                'order_code'      => $r['order_code'],
                'customer_name'   => $r['customer_name'] ?? 'Guest Customer',
                'order_type'      => $r['order_type'],
                'status'          => $r['status'],
                'total_formatted' => 'LKR ' . number_format((float)$r['total_amount'], 2),
                'items_summary'   => $r['items_summary'] ?: 'Chef Specialties',
                'time_ago'        => $timeAgo,
                'created_at'      => $r['created_at'],
            ];
        }
    }

    echo json_encode([
        'success'      => true,
        'max_order_id' => $maxOrderId,
        'metrics'      => [
            'active_count'    => (int)($metrics['active_count'] ?? 0),
            'placed_count'    => (int)($metrics['placed_count'] ?? 0),
            'preparing_count' => (int)($metrics['preparing_count'] ?? 0),
            'ready_count'     => (int)($metrics['ready_count'] ?? 0),
            'completed_today' => (int)($metrics['completed_today'] ?? 0),
            'revenue_today'   => (float)($metrics['revenue_today'] ?? 0),
        ],
        'new_orders'   => $newOrders,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Live query failed: ' . $e->getMessage()
    ]);
}
