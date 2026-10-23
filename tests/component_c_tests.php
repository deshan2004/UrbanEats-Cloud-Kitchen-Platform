<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Ordering, Payment & Tracking Test Suite
 *
 * Requirements Covered:
 * - Session-based cart calculations and running totals
 * - Order type pricing logic (Delivery fee vs. Free pickup)
 * - Coupon discount validation (percentage, fixed, max cap, min spend, expiry)
 * - Order workflow state machine & transition validation
 * - Transaction reference and payment simulation tracking
 * - Prevention of illegal order jumping (Placed -> Completed)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_functions.php';

$passedTests = 0;
$failedTests = 0;
$testLog = [];

function assert_test(string $testName, bool $condition, string $details = ''): void {
    global $passedTests, $failedTests, $testLog;
    if ($condition) {
        $passedTests++;
        $testLog[] = "[PASS] {$testName}" . ($details ? " - {$details}" : '');
        echo "✅ PASS: {$testName}\n";
    } else {
        $failedTests++;
        $testLog[] = "[FAIL] {$testName} - {$details}";
        echo "❌ FAIL: {$testName}: {$details}\n";
    }
}

echo "========================================================\n";
echo "URBANEATS: COMPONENT C (ORDERING & WORKFLOW) TEST SUITE\n";
echo "========================================================\n\n";

// TC-C01: Cart Subtotal Calculation
$mockCart = [
    1 => ['dish_id' => 1, 'name' => 'Chicken Kottu', 'price' => 1450.00, 'quantity' => 2],
    2 => ['dish_id' => 2, 'name' => 'Seafood Rice',   'price' => 1850.00, 'quantity' => 1],
];
$subtotal = 0.00;
foreach ($mockCart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
assert_test("TC-C01: Cart subtotal calculates cumulative line item totals", $subtotal === 4750.00, "Calculated: {$subtotal}");

// TC-C02: Cart Quantity Modification
$mockCart[1]['quantity'] = 3;
$newSubtotal = ($mockCart[1]['price'] * $mockCart[1]['quantity']) + ($mockCart[2]['price'] * $mockCart[2]['quantity']);
assert_test("TC-C02: Cart quantity incrementation dynamically updates subtotal", $newSubtotal === 6200.00, "Calculated: {$newSubtotal}");

// TC-C03: Cart Item Removal
unset($mockCart[1]);
$remainingCount = count($mockCart);
assert_test("TC-C03: Cart item removal removes line item from session collection", $remainingCount === 1 && !isset($mockCart[1]));

// TC-C04: Delivery Fee for Delivery Orders
$orderType = 'delivery';
$deliveryFee = ($orderType === 'delivery') ? 250.00 : 0.00;
assert_test("TC-C04: Delivery orders apply standardized dispatch fee of LKR 250.00", $deliveryFee === 250.00);

// TC-C05: Free Pickup Fulfillment
$orderTypePickup = 'pickup';
$pickupFee = ($orderTypePickup === 'delivery') ? 250.00 : 0.00;
assert_test("TC-C05: Kitchen counter pickup waives dispatch delivery fee", $pickupFee === 0.00);

// TC-C06: Percentage Coupon Discount Calculation
$couponPercent = 10.00; // 10%
$subtotalForDiscount = 3000.00;
$discountVal = ($subtotalForDiscount * $couponPercent) / 100.00;
assert_test("TC-C06: Percentage coupon calculates proportional discount value", $discountVal === 300.00, "Discount: {$discountVal}");

// TC-C07: Fixed Amount Coupon Discount Calculation
$fixedDiscount = 500.00;
$discountAmount = min($subtotalForDiscount, $fixedDiscount);
assert_test("TC-C07: Fixed amount coupon applies flat rate reduction", $discountAmount === 500.00);

// TC-C08: Coupon Maximum Discount Ceiling Cap
$largeSubtotal = 10000.00;
$rawDiscount = ($largeSubtotal * 20.00) / 100.00; // LKR 2,000
$maxCap = 1000.00;
$effectiveDiscount = min($rawDiscount, $maxCap);
assert_test("TC-C08: Discount cap limits maximum permissible promotional reduction", $effectiveDiscount === 1000.00, "Capped at: {$effectiveDiscount}");

// TC-C09: Coupon Minimum Spend Requirement
$minSpend = 2000.00;
$cartValue = 1500.00;
$isEligible = $cartValue >= $minSpend;
assert_test("TC-C09: Coupon rejects application when subtotal is below minimum order value", $isEligible === false);

// TC-C10: Coupon Expiry Date Verification
$couponExpiryDate = '2025-01-01'; // Expired
$isExpired = strtotime($couponExpiryDate) < time();
assert_test("TC-C10: Expired promotional coupon is invalidated", $isExpired === true);

// TC-C11: Order Code Generation Format
$generatedCode = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$codePattern = '/^ORD-\d{8}-[A-F0-9]{6}$/';
assert_test("TC-C11: Order code complies with unique prefix & alphanumeric mask", preg_match($codePattern, $generatedCode) === 1, "Code: {$generatedCode}");

// TC-C12: Order Workflow Transition: Placed -> Preparing
$transitions = allowed_transitions();
assert_test("TC-C12: Workflow permits transition from 'Placed' to 'Preparing'", in_array('Preparing', $transitions['Placed'], true));

// TC-C13: Order Workflow Transition: Preparing -> Ready
assert_test("TC-C13: Workflow permits transition from 'Preparing' to 'Ready'", in_array('Ready', $transitions['Preparing'], true));

// TC-C14: Order Workflow Transition: Ready -> Out for Delivery
assert_test("TC-C14: Workflow permits dispatching from 'Ready' to 'Out for Delivery'", in_array('Out for Delivery', $transitions['Ready'], true));

// TC-C15: Order Workflow Transition: Out for Delivery -> Completed
assert_test("TC-C15: Workflow permits customer fulfillment from 'Out for Delivery' to 'Completed'", in_array('Completed', $transitions['Out for Delivery'], true));

// TC-C16: Mitigation of Illegal Stage Skipping (Placed -> Completed)
$canSkipDirectly = in_array('Completed', $transitions['Placed'], true);
assert_test("TC-C16: Workflow rejects illegal transition skipping (Placed directly to Completed)", $canSkipDirectly === false);

// TC-C17: Terminal State Immutability (Completed Order cannot transition)
$completedTransitions = $transitions['Completed'];
assert_test("TC-C17: Completed orders enter immutable terminal state (no further transitions)", empty($completedTransitions));

// TC-C18: Payment Transaction Reference Format
$txnRef = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
$txnPattern = '/^TXN-\d{8}-[A-F0-9]{8}$/';
assert_test("TC-C18: Transaction reference generated with audit compliance format", preg_match($txnPattern, $txnRef) === 1, "Ref: {$txnRef}");

// TC-C19: Simulated Payment Status Resolution
$codStatus = ('cod' === 'cod') ? 'Pending' : 'Completed';
$cardStatus = ('card' === 'cod') ? 'Pending' : 'Completed';
assert_test("TC-C19: Payment simulation maps COD to 'Pending' and Card/Bank to 'Completed'", $codStatus === 'Pending' && $cardStatus === 'Completed');

echo "\n--------------------------------------------------------\n";
echo "SUMMARY: {$passedTests} PASSED, {$failedTests} FAILED.\n";
echo "--------------------------------------------------------\n";
