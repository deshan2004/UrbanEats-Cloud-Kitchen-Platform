<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Order Confirmation & Professional Invoice Receipt
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();

$code = trim($_GET['code'] ?? '');
$order = null;
$items = [];
$payment = null;

if ($pdo && $code !== '') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND user_id = ?");
    $stmt->execute([$code, (int)$currentUser['id']]);
    $order = $stmt->fetch();

    if ($order) {
        $stmtItems = $pdo->prepare(
            "SELECT oi.*, d.name AS dish_name, d.category_id 
             FROM order_items oi
             JOIN dishes d ON d.dish_id = oi.dish_id
             WHERE oi.order_id = ?
             ORDER BY oi.item_id ASC"
        );
        $stmtItems->execute([(int)$order['order_id']]);
        $items = $stmtItems->fetchAll();

        // Retrieve recorded transaction if available
        $stmtPayment = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1");
        $stmtPayment->execute([(int)$order['order_id']]);
        $payment = $stmtPayment->fetch();
    }
}

if (!$order) {
    set_flash('error', 'Order not found or access denied.');
    redirect(url('index.php'));
}

$pageTitle = 'Invoice Receipt #' . $order['order_code'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Screen & Print Stylesheet for Professional Culinary Invoice Receipt */
.receipt-wrapper {
    max-width: 800px;
    margin: 0 auto;
    padding: 1.5rem 1rem 3.5rem;
}

.success-banner {
    text-align: center;
    margin-bottom: 2rem;
}

.success-icon-badge {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #EBFBF0;
    color: #1F9D55;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    box-shadow: 0 4px 16px rgba(31, 157, 85, 0.2);
    margin-bottom: 0.85rem;
}

.receipt-card {
    background: #FFFFFF;
    border: 1px solid rgba(229, 231, 235, 0.85);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
    padding: 2.5rem;
    position: relative;
    overflow: hidden;
}

/* Subtle decorative top bar */
.receipt-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, var(--primary-500) 0%, #FF8A3D 100%);
}

.receipt-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 1.75rem;
    border-bottom: 1px solid #E5E7EB;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.receipt-brand-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.receipt-brand-logo img {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
}

.receipt-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    padding: 1.5rem 0;
    border-bottom: 1px solid #E5E7EB;
}

.info-block {
    background: #F9FAFB;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    padding: 1rem 1.15rem;
}

.info-block-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.receipt-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
}

.receipt-table th {
    background: #F9FAFB;
    padding: 0.75rem 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #4B5563;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-top: 1px solid #E5E7EB;
    border-bottom: 1px solid #E5E7EB;
}

.receipt-table td {
    padding: 0.9rem 1rem;
    font-size: 0.92rem;
    color: #1F2937;
    border-bottom: 1px solid #F3F4F6;
    vertical-align: middle;
}

.receipt-summary-card {
    margin-left: auto;
    max-width: 320px;
    padding-top: 0.5rem;
}

.receipt-summary-line {
    display: flex;
    justify-content: space-between;
    font-size: 0.92rem;
    color: #4B5563;
    margin-bottom: 0.5rem;
}

.receipt-summary-total {
    display: flex;
    justify-content: space-between;
    padding-top: 0.75rem;
    margin-top: 0.5rem;
    border-top: 2px solid #E5E7EB;
    font-size: 1.25rem;
    font-weight: 800;
    color: #111827;
}

.receipt-footer-strip {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px dashed #D1D5DB;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.barcode-wrapper {
    text-align: center;
}

.barcode-svg {
    height: 38px;
    display: block;
    margin: 0 auto;
}

.barcode-text {
    font-family: "Courier New", Courier, monospace;
    font-size: 0.78rem;
    letter-spacing: 0.2em;
    color: #4B5563;
    margin-top: 0.25rem;
}

/* Dedicated Print Stylesheet */
@media print {
    @page {
        margin: 8mm 12mm;
        size: A4 portrait;
    }

    body {
        background: #FFFFFF !important;
        color: #111827 !important;
        font-size: 10pt !important;
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    header, nav, footer, .site-header, .site-footer, .no-print, .success-banner, .receipt-action-buttons {
        display: none !important;
    }

    .container, .receipt-wrapper {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .receipt-card {
        border: 1px solid #D1D5DB !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding: 1.25rem 1.5rem !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .receipt-card::before {
        display: none !important;
    }

    .info-block {
        background: #FAFAFA !important;
        border: 1px solid #E5E7EB !important;
        padding: 0.65rem 0.85rem !important;
    }

    .receipt-table th {
        background: #F3F4F6 !important;
        padding: 0.5rem 0.75rem !important;
    }

    .receipt-table td {
        padding: 0.5rem 0.75rem !important;
    }

    .receipt-summary-card {
        max-width: 280px !important;
    }

    .receipt-summary-line {
        margin-bottom: 0.3rem !important;
    }

    .receipt-footer-strip {
        margin-top: 1.25rem !important;
        padding-top: 1rem !important;
    }
}
</style>

<div class="container receipt-wrapper">
    
    <!-- Success Banner (Hidden on Print) -->
    <div class="success-banner no-print">
        <div class="success-icon-badge">✓</div>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--ink-900); margin: 0 0 0.4rem; letter-spacing: -0.02em;">
            Order Confirmed!
        </h1>
        <p style="color: var(--ink-500); font-size: 1rem; margin: 0;">
            Thank you, <strong><?= e($currentUser['full_name']); ?></strong>. Your culinary order has been routed directly to our cloud kitchen line.
        </p>
    </div>

    <!-- Quick Navigation / Print Toolbar (Hidden on Print) -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <a href="<?= e(url('orders/history.php')); ?>" style="color: var(--ink-600); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
                &larr; Back to Order History
            </a>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= e(url('orders/tracking.php?code=' . urlencode($order['order_code']))); ?>" class="btn btn-primary" style="padding: 0.6rem 1.25rem; font-size: 0.9rem; border-radius: var(--radius-md);">
                🛵 Track Order Live &rarr;
            </a>
            <button onclick="window.print()" class="btn btn-secondary" style="padding: 0.6rem 1.25rem; font-size: 0.9rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.4rem; background: #FFFFFF; border: 1px solid var(--border-default); box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                🖨️ Print / Save PDF
            </button>
        </div>
    </div>

    <!-- Professional Tax Invoice / Receipt Card (Clean Print Target) -->
    <div class="receipt-card" id="printableInvoice">
        
        <!-- Header: Logo, Commercial Details, Order Ref -->
        <div class="receipt-header-row">
            <div>
                <div class="receipt-brand-logo">
                    <img src="<?= e(url('public/images/logo.png')); ?>" alt="UrbanEats Logo">
                    <div>
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: #111827; margin: 0; line-height: 1.2; letter-spacing: -0.01em;">
                            UrbanEats Cloud Kitchen
                        </h2>
                        <span style="font-size: 0.78rem; font-weight: 600; color: var(--primary-600); text-transform: uppercase; letter-spacing: 0.05em;">
                            Official Culinary Tax Invoice &amp; Customer Receipt
                        </span>
                    </div>
                </div>
                <div style="font-size: 0.8rem; color: #6B7280; line-height: 1.5; margin-top: 0.35rem;">
                    <div>UrbanEats Culinary Operations Hub &bull; 24 Havelock Road, Colombo 05</div>
                    <div>Hotline: +94 11 234 5678 &bull; Support: support@urbaneats.lk &bull; Web: urbaneats.lk</div>
                </div>
            </div>

            <div style="text-align: right; min-width: 200px;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.06em;">Invoice / Order Ref</div>
                <div style="font-family: 'Courier New', Courier, monospace; font-size: 1.2rem; font-weight: 800; color: #111827; margin: 0.15rem 0;">
                    #<?= e($order['order_code']); ?>
                </div>
                <div style="font-size: 0.82rem; color: #4B5563;">
                    <?= date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                </div>
                <div style="margin-top: 0.5rem;">
                    <span style="display: inline-block; background: #EFF6FF; color: #1D4ED8; border: 1px solid rgba(29, 78, 216, 0.2); font-size: 0.78rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 9999px;">
                        Order Status: <?= e($order['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Information Columns: Recipient & Order Parameters -->
        <div class="receipt-info-grid">
            
            <!-- Left: Customer & Fulfillment Details -->
            <div class="info-block">
                <div class="info-block-title">
                    <span>📍</span> Fulfillment &amp; Recipient
                </div>
                <div style="font-weight: 700; font-size: 0.95rem; color: #111827; margin-bottom: 0.2rem;">
                    <?= e($currentUser['full_name']); ?>
                </div>
                <div style="font-size: 0.85rem; color: #4B5563; margin-bottom: 0.35rem;">
                    <strong>Phone:</strong> <?= e($order['phone']); ?>
                </div>
                <div style="font-size: 0.85rem; color: #4B5563; line-height: 1.45;">
                    <strong>Destination:</strong><br>
                    <?= nl2br(e($order['delivery_address'])); ?>
                </div>
                <?php if (!empty($order['notes'])): ?>
                    <div style="margin-top: 0.45rem; padding-top: 0.35rem; border-top: 1px dashed #E5E7EB; font-size: 0.8rem; color: #6B7280; font-style: italic;">
                        <strong>Kitchen Notes:</strong> "<?= e($order['notes']); ?>"
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Commercial, Transaction & Dispatch Specs -->
            <div class="info-block">
                <div class="info-block-title">
                    <span>💳</span> Payment &amp; Logistics
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                    <span style="color: #6B7280;">Order Type:</span>
                    <strong style="color: #111827;">
                        <?= ($order['order_type'] === 'delivery') ? '🛵 Home Delivery' : '🏪 Kitchen Counter Pickup'; ?>
                    </strong>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                    <span style="color: #6B7280;">Payment Method:</span>
                    <strong style="color: #111827;">
                        <?php
                        $methodLabels = [
                            'cod'  => '💵 Cash on Delivery (COD)',
                            'card' => '💳 Credit / Debit Card',
                            'bank' => '🏦 Direct Bank Transfer',
                        ];
                        echo $methodLabels[$order['payment_method']] ?? strtoupper(e($order['payment_method']));
                        ?>
                    </strong>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                    <span style="color: #6B7280;">Payment Status:</span>
                    <strong style="color: <?= ($payment && $payment['payment_status'] === 'Completed') ? '#15803D' : '#B45309'; ?>;">
                        <?= $payment ? e($payment['payment_status']) : (($order['payment_method'] === 'cod') ? 'Pending upon Delivery' : 'Verified'); ?>
                    </strong>
                </div>

                <?php if ($payment && !empty($payment['transaction_reference'])): ?>
                    <div style="display: flex; justify-content: space-between; font-size: 0.82rem; margin-top: 0.35rem; padding-top: 0.35rem; border-top: 1px dashed #E5E7EB;">
                        <span style="color: #6B7280;">Txn Reference:</span>
                        <code style="color: #374151; font-weight: 700;"><?= e($payment['transaction_reference']); ?></code>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Itemized Order Table -->
        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">#</th>
                    <th style="text-align: left;">Culinary Item Description</th>
                    <th style="width: 80px; text-align: center;">Qty</th>
                    <th style="width: 120px; text-align: right;">Unit Price</th>
                    <th style="width: 130px; text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $lineIndex = 1;
                foreach ($items as $item): 
                    $lineTotal = (float)$item['price_at_purchase'] * (int)$item['quantity'];
                ?>
                    <tr>
                        <td style="text-align: center; color: #9CA3AF; font-size: 0.85rem;"><?= $lineIndex++; ?></td>
                        <td>
                            <strong style="color: #111827; display: block;"><?= e($item['dish_name']); ?></strong>
                            <small style="color: #6B7280; font-size: 0.78rem;">Freshly prepared cloud kitchen portion</small>
                        </td>
                        <td style="text-align: center; font-weight: 700; color: #1F2937;"><?= (int)$item['quantity']; ?></td>
                        <td style="text-align: right; color: #4B5563;">LKR <?= number_format((float)$item['price_at_purchase'], 2); ?></td>
                        <td style="text-align: right; font-weight: 700; color: #111827;">LKR <?= number_format($lineTotal, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals & Commercial Settlement Summary -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem; margin-top: 1rem;">
            
            <!-- Left: Cloud Kitchen Guarantee & Notes -->
            <div style="flex: 1; min-width: 250px; font-size: 0.8rem; color: #6B7280; line-height: 1.5; padding-top: 0.5rem;">
                <p style="margin: 0 0 0.35rem; font-weight: 700; color: #374151;">
                    🔒 Certified Food Safety &amp; Quality Guarantee
                </p>
                <p style="margin: 0;">
                    All meals are prepared in temperature-controlled stations following strict HACCP &amp; ISO 22000 culinary guidelines. For real-time rider tracking, quote order code <strong>#<?= e($order['order_code']); ?></strong>.
                </p>
            </div>

            <!-- Right: Financial Breakdown -->
            <div class="receipt-summary-card">
                <div class="receipt-summary-line">
                    <span>Subtotal:</span>
                    <span>LKR <?= number_format((float)$order['subtotal'], 2); ?></span>
                </div>
                
                <div class="receipt-summary-line">
                    <span>Dispatch &amp; Delivery Fee:</span>
                    <span>
                        <?php if ((float)$order['delivery_fee'] > 0): ?>
                            LKR <?= number_format((float)$order['delivery_fee'], 2); ?>
                        <?php else: ?>
                            <span style="color: #16A34A; font-weight: 700;">FREE (Pickup)</span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ((float)$order['discount_amount'] > 0): ?>
                    <div class="receipt-summary-line" style="color: #16A34A; font-weight: 600;">
                        <span>Promotional Discount:</span>
                        <span>- LKR <?= number_format((float)$order['discount_amount'], 2); ?></span>
                    </div>
                <?php endif; ?>

                <div class="receipt-summary-total">
                    <span>Grand Total:</span>
                    <span style="color: var(--primary-600);">LKR <?= number_format((float)$order['total_amount'], 2); ?></span>
                </div>
            </div>

        </div>

        <!-- Bottom Strip: Simulated Barcode & Verification Timestamp -->
        <div class="receipt-footer-strip">
            <div class="barcode-wrapper">
                <!-- High-contrast SVG Barcode Pattern -->
                <svg class="barcode-svg" width="220" height="36" viewBox="0 0 220 36" xmlns="http://www.w3.org/2000/svg">
                    <?php
                    // Generates deterministic clean barcode bars based on the order code
                    $hash = md5($order['order_code']);
                    $x = 8;
                    for ($i = 0; $i < 38; $i++) {
                        $w = (hexdec($hash[$i % 32]) % 3) + 1.4;
                        echo '<rect x="' . round($x, 1) . '" y="2" width="' . round($w, 1) . '" height="32" fill="#1F2937" />';
                        $x += $w + 2;
                    }
                    ?>
                </svg>
                <div class="barcode-text">* <?= e($order['order_code']); ?> *</div>
            </div>

            <div style="text-align: right; font-size: 0.78rem; color: #9CA3AF; line-height: 1.4;">
                <div>Computer Generated Tax Invoice</div>
                <div>No physical signature required &bull; UrbanEats (Pvt) Ltd</div>
            </div>
        </div>

    </div>

    <!-- Bottom Action Buttons (Hidden on Print) -->
    <div class="receipt-action-buttons no-print" style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;">
        <a href="<?= e(url('orders/tracking.php?code=' . urlencode($order['order_code']))); ?>" class="btn btn-primary" style="padding: 0.8rem 1.8rem; font-weight: 700; border-radius: var(--radius-md);">
            🛵 Track Order Live &rarr;
        </a>
        <a href="<?= e(url('orders/history.php')); ?>" class="btn btn-secondary" style="padding: 0.8rem 1.8rem; font-weight: 700; border-radius: var(--radius-md);">
            📋 View All My Orders
        </a>
        <button onclick="window.print()" class="btn btn-secondary" style="padding: 0.8rem 1.8rem; font-weight: 700; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.5rem; background: #FFFFFF; border: 1px solid var(--border-default);">
            🖨️ Print Receipt / PDF
        </button>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
