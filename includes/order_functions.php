<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Order Workflow & Coupon Validation Functions
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/functions.php';

/**
 * Finite State Machine (FSM): Order Lifecycle Transition Matrix.
 * 
 * ARCHITECTURAL DESIGN:
 * Strictly enforces directional state transitions to maintain business process integrity.
 * Prevents illegal state jumps (e.g., an order jumping from 'Placed' directly to 'Completed'
 * without kitchen preparation and fulfillment validation).
 * 
 * Lifecycle Path:
 * Placed ➔ Preparing ➔ Ready ➔ Out for Delivery / Collected ➔ Completed (or Cancelled)
 * 
 * @return array<string, array<string>> Map of current status => permitted next statuses
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
 * Executes a concurrency-safe order status transition with audit history.
 * 
 * CONCURRENCY & TRANSACTION SAFEGUARDS:
 * 1. Pessimistic Row-Level Locking (SELECT ... FOR UPDATE):
 *    Locks the specific order record in MySQL, preventing race conditions if multiple staff
 *    or automated webhooks attempt to advance status concurrently.
 * 2. FSM Whitelist Verification:
 *    Validates that $newStatus is permitted from the freshly locked $current state.
 * 3. Atomic Multi-Table Mutation:
 *    Updates orders table and appends an immutable entry to order_status_history within
 *    the same ACID transaction. Full rollback executed on any failure.
 * 
 * @param PDO $pdo Active PDO connection
 * @param int $orderId Target order primary key
 * @param string $newStatus Requested target status
 * @param int $userId Authenticated user ID executing the change
 * @return bool True on successful atomic transition, false on invalid transition or failure
 */
function change_order_status(PDO $pdo, int $orderId, string $newStatus, int $userId): bool {
    try {
        $pdo->beginTransaction();

        // 1. Acquire pessimistic write lock on the order row
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $current = $stmt->fetchColumn();

        // 2. Validate requested state change against FSM transition matrix
        $allowed = allowed_transitions();
        if ($current === false || !in_array($newStatus, $allowed[$current] ?? [], true)) {
            $pdo->rollBack();
            return false;
        }

        // 3. Update order state
        $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?")
            ->execute([$newStatus, $orderId]);

        // 4. Record chronological audit trail in order_status_history
        $pdo->prepare(
            "INSERT INTO order_status_history (order_id, status, changed_by, changed_at)
             VALUES (?, ?, ?, NOW())"
        )->execute([$orderId, $newStatus, $userId]);

        $pdo->commit();

        // 5. System-wide audit log for traceability
        if (function_exists('log_action')) {
            log_action(
                $pdo,
                $userId,
                'ORDER_STATUS_CHANGED',
                "Order #{$orderId} transitioned from {$current} to {$newStatus}"
            );
        }

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Order Status Change Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Server-side coupon verification and mathematical discount calculation.
 * Never trusts client-side discounted prices; validates validity, expiry, and minimum cart value.
 * 
 * @param PDO $pdo
 * @param string $code Raw coupon code from checkout request
 * @param float $subtotal Current validated cart subtotal
 * @return array{0: bool, 1: array|string} [true, $couponRowWithDiscount] OR [false, $errorMessage]
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
        return [false, 'Minimum order value for this coupon is LKR ' . number_format($minOrderValue, 2)];
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
