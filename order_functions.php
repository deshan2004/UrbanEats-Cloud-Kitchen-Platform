<?php
// includes/order_functions.php
// Order workflow + coupon validation

/**
 * Each status and the statuses it is allowed to move to.
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
 * Change an order's status safely and save a history record.
 */
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

        $pdo->prepare(
            "INSERT INTO order_status_history (order_id, status, changed_by, changed_at)
             VALUES (?, ?, ?, NOW())"
        )->execute([$orderId, $newStatus, $userId]);

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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Validate a coupon for a given order subtotal.
 * Returns [true, $couponRow] if valid, or [false, 'error message'].
 */
function validate_coupon(PDO $pdo, string $code, float $subtotal): array {
    $code = strtoupper(trim($code));

    if ($code === '') {
        return [false, 'Coupon code is required'];
    }

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = :code AND is_active = 1 LIMIT 1");
    $stmt->execute([':code' => $code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        return [false, 'Invalid coupon code'];
    }

    $expiryDate = $coupon['expiry_date'] ?? null;
    if ($expiryDate !== null && $expiryDate !== '' && strtotime((string)$expiryDate) < time()) {
        return [false, 'This coupon has expired'];
    }

    $minOrderValue = isset($coupon['min_order_value']) ? (float)$coupon['min_order_value'] : 0.0;
    if ($subtotal < $minOrderValue) {
        return [false, 'Minimum order value for this coupon is ' . number_format($minOrderValue, 2)];
    }

    $discountType = strtolower((string)($coupon['discount_type'] ?? 'percentage'));
    $discountValue = (float)($coupon['discount_value'] ?? 0);
    $maxDiscount = isset($coupon['max_discount']) ? (float)$coupon['max_discount'] : 0.0;

    if ($discountType === 'percentage') {
        $discountAmount = ($subtotal * $discountValue) / 100;
    } elseif ($discountType === 'fixed') {
        $discountAmount = $discountValue;
    } else {
        return [false, 'Coupon type is invalid'];
    }

    if ($maxDiscount > 0 && $discountAmount > $maxDiscount) {
        $discountAmount = $maxDiscount;
    }

    if ($discountAmount < 0) {
        $discountAmount = 0;
    }

    $coupon['discount_amount'] = round($discountAmount, 2);
    return [true, $coupon];
}