<?php
// includes/order_functions.php
// Order workflow: allowed status transitions + status change with history

/**
 * Each status and the statuses it is allowed to move to.
 * Anything not listed here is blocked (e.g. Placed -> Completed).
 */
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

/**
 * Change an order's status safely.
 * Returns true on success, false if the move is not allowed or something fails.
 */
function change_order_status(PDO $pdo, int $orderId, string $newStatus, int $userId): bool {
    try {
        $pdo->beginTransaction();

        // Lock the row so two staff can't change the same order at once
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $current = $stmt->fetchColumn();

        $allowed = allowed_transitions();

        // Order not found, or invalid jump
        if ($current === false || !in_array($newStatus, $allowed[$current] ?? [], true)) {
            $pdo->rollBack();
            return false;
        }

        // 1. Update the order
        $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?")
            ->execute([$newStatus, $orderId]);

        // 2. Save history record
        $pdo->prepare(
            "INSERT INTO order_status_history (order_id, status, changed_by, changed_at)
             VALUES (?, ?, ?, NOW())"
        )->execute([$orderId, $newStatus, $userId]);

        $pdo->commit();

        // Audit log (Component A's function). Skipped safely if not merged yet.
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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        return false;
    }
}