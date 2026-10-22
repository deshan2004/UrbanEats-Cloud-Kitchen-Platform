<?php
// includes/order_functions.php

function allowed_transitions(): array {
    return [
        'Placed'           => ['Preparing', 'Cancelled'],
        'Preparing'        => ['Ready', 'Cancelled'],
        'Ready'            => ['Out for Delivery', 'Collected'],
        'Out for Delivery' => ['Completed'],
        'Collected'        => ['Completed'],
        'Completed'        => [],
        'Cancelled'        => [],
    ];
}

function change_order_status(PDO $pdo, int $orderId, string $newStatus, int $userId): bool {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $current = $stmt->fetchColumn();

        $allowed = allowed_transitions();
        if ($current === false || !in_array($newStatus, $allowed[$current] ?? [], true)) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?")
            ->execute([$newStatus, $orderId]);

        $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, changed_at)
                       VALUES (?, ?, ?, NOW())")
            ->execute([$orderId, $newStatus, $userId]);

        $pdo->commit();

        if (function_exists('log_action')) {
            log_action(
                $pdo,
                $userId,
                'ORDER_STATUS_CHANGED',
                "Order {$orderId} changed from {$current} to {$newStatus}"
            );
        }
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($e->getMessage());
        return false;
    }
}