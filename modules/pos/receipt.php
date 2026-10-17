<?php
/**
 * POS Module: Sales Receipt & Printable View
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_login();

$saleId = (int)($_GET['id'] ?? 0);
if ($saleId <= 0) {
    set_flash('danger', 'Invalid receipt ID.');
    redirect('modules/pos/index.php');
}

$pdo = get_db_connection();

// Fetch sale details
$stmtSale = $pdo->prepare("
    SELECT s.*, u.full_name AS cashier_name, u.username AS cashier_username
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = :id
    LIMIT 1
");
$stmtSale->execute([':id' => $saleId]);
$sale = $stmtSale->fetch();

if (!$sale) {
    set_flash('danger', 'Receipt record not found.');
    redirect('modules/pos/index.php');
}

// Fetch sale line items
$stmtItems = $pdo->prepare("
    SELECT * 
    FROM sale_items 
    WHERE sale_id = :sale_id 
    ORDER BY id ASC
");
$stmtItems->execute([':sale_id' => $saleId]);
$items = $stmtItems->fetchAll();

$pageTitle = 'Receipt #' . $sale['invoice_no'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <!-- Action Controls (Hidden on Print) -->
    <div class="col-12 text-center mb-4 no-print">
        <div class="btn-group shadow-sm">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Receipt
            </button>
            <a href="<?= url('modules/pos/index.php'); ?>" class="btn btn-success">
                <i class="bi bi-cart-plus me-1"></i> New POS Sale
            </a>
            <?php if (is_admin()): ?>
                <a href="<?= url('modules/reports/index.php'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Reports
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Thermal Receipt Card -->
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="receipt-wrapper border">
            <!-- Header -->
            <div class="receipt-header">
                <h5 class="fw-bold mb-1"><?= e(APP_NAME); ?></h5>
                <p class="mb-1 small">No 10 Kandy road, Uhana </p>
                <p class="mb-1 small">Tel: +94 721256789</p>
                <p class="mb-0 small"> ******* </p>
            </div>

            <!-- Invoice Metadata -->
            <div class="small mb-2">
                <div class="d-flex justify-content-between">
                    <span><strong>Invoice #:</strong></span>
                    <span class="font-monospace fw-bold"><?= e($sale['invoice_no']); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Date:</span>
                    <span><?= format_datetime($sale['created_at']); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Cashier:</span>
                    <span><?= e($sale['cashier_name'] ?? 'Staff'); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Customer:</span>
                    <span><?= e($sale['customer_name']); ?></span>
                </div>
                <?php if (!empty($sale['customer_phone'])): ?>
                    <div class="d-flex justify-content-between">
                        <span>Phone:</span>
                        <span><?= e($sale['customer_phone']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="receipt-divider"></div>

            <!-- Line Items Table -->
            <table class="receipt-table">
                <thead>
                    <tr class="border-bottom">
                        <th class="text-start" style="width: 40%;">Item</th>
                        <th class="text-center" style="width: 14%;">Qty</th>
                        <th class="text-end" style="width: 22%;">Price</th>
                        <th class="text-end" style="width: 24%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="text-start">
                                <div class="fw-semibold text-wrap"><?= e($item['product_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= e($item['sku']); ?></div>
                            </td>
                            <td class="text-center"><?= (int)$item['quantity']; ?></td>
                            <td class="text-end"><?= number_format((float)$item['unit_price'], 2); ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)$item['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-divider"></div>

            <!-- Financial Totals -->
            <div class="small">
                <div class="d-flex justify-content-between py-1">
                    <span>Subtotal:</span>
                    <span><?= format_currency($sale['subtotal']); ?></span>
                </div>

                <?php if ((float)$sale['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between py-1 text-danger">
                        <span>Discount <?= $sale['discount_type'] === 'percent' ? '(' . (float)$sale['discount_value'] . '%)' : ''; ?>:</span>
                        <span>-<?= format_currency($sale['discount_amount']); ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between py-1 border-top border-bottom fw-bold fs-6">
                    <span>TOTAL DUE:</span>
                    <span><?= format_currency($sale['final_total']); ?></span>
                </div>

                <div class="d-flex justify-content-between py-1 mt-1">
                    <span>Payment Method:</span>
                    <span class="text-uppercase fw-semibold"><?= e($sale['payment_method']); ?></span>
                </div>

                <div class="d-flex justify-content-between py-1">
                    <span>Amount Paid:</span>
                    <span><?= format_currency($sale['paid_amount']); ?></span>
                </div>

                <div class="d-flex justify-content-between py-1">
                    <span>Change Returned:</span>
                    <span class="fw-bold"><?= format_currency($sale['change_amount']); ?></span>
                </div>
            </div>

            <?php if (!empty($sale['notes'])): ?>
                <div class="receipt-divider"></div>
                <div class="small text-muted">
                    <strong>Note:</strong> <?= e($sale['notes']); ?>
                </div>
            <?php endif; ?>

            <div class="receipt-divider"></div>

            <!-- Footer & Barcode simulation -->
            <div class="receipt-footer">
                <p class="mb-1 fw-bold">THANK YOU FOR YOUR PATRONAGE!</p>
                <p class="mb-2 text-muted" style="font-size: 0.75rem;">Goods sold are returnable within 7 days with valid receipt.</p>
                
                <!-- SVG Barcode Representation -->
                <div class="d-flex justify-content-center my-2">
                    <svg height="36" width="180">
                        <g fill="#000">
                            <?php 
                            // Render clean decorative barcode bars
                            $bars = [3, 2, 4, 1, 3, 2, 5, 2, 4, 2, 3, 1, 4, 3, 2, 5, 1, 3, 4, 2, 4, 2];
                            $x = 10;
                            foreach ($bars as $idx => $width) {
                                if ($idx % 2 === 0) {
                                    echo '<rect x="' . $x . '" y="0" width="' . $width . '" height="36" />';
                                }
                                $x += $width + 2;
                            }
                            ?>
                        </g>
                    </svg>
                </div>
                <div class="font-monospace small text-muted"><?= e($sale['invoice_no']); ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
