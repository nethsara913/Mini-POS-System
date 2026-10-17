<?php
/**
 * POS Module: Sales Register Terminal
 * Accessible by both Admin and Staff roles
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_login(); // Admin and Staff permitted

$pdo = get_db_connection();

// Fetch active categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Fetch active products with category names
$products = $pdo->query("
    SELECT 
        p.id, 
        p.category_id, 
        p.sku, 
        p.name, 
        p.selling_price, 
        p.stock_quantity, 
        p.alert_threshold,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
")->fetchAll();

$pageTitle = 'POS Register Terminal';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
    <!-- Left Column: Catalog & Product Selection (col-lg-7 col-xl-8) -->
    <div class="col-lg-7 col-xl-8">
        <!-- Top Search and Filter Bar -->
        <div class="card shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="posSearchInput" class="form-control" placeholder="Search product or scan barcode (Enter)..." autofocus autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('posSearchInput').value=''; document.getElementById('posSearchInput').dispatchEvent(new Event('input'));">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <span class="small text-muted">
                            <i class="bi bi-box-seam me-1"></i> <span id="totalProductCount"><?= count($products); ?></span> items available
                        </span>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div class="d-flex gap-1 overflow-auto pt-3 pb-1" style="white-space: nowrap;">
                    <button type="button" class="btn btn-sm btn-outline-primary category-filter-btn active" data-category-id="all">
                        All Items
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary category-filter-btn" data-category-id="<?= (int)$cat['id']; ?>">
                            <?= e($cat['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Products Grid Container -->
        <div id="productGrid" class="row row-cols-2 row-cols-sm-3 row-cols-md-3 row-cols-xl-4 g-3">
            <?php foreach ($products as $prod): 
                $stock = (int)$prod['stock_quantity'];
                $isOut = $stock <= 0;
            ?>
                <div class="col pos-product-item" 
                     data-id="<?= (int)$prod['id']; ?>" 
                     data-sku="<?= e($prod['sku']); ?>" 
                     data-name="<?= e($prod['name']); ?>" 
                     data-price="<?= (float)$prod['selling_price']; ?>" 
                     data-stock="<?= $stock; ?>" 
                     data-category-id="<?= (int)$prod['category_id']; ?>">
                    <div class="card h-100 pos-product-card <?= $isOut ? 'out-of-stock' : ''; ?>" 
                         onclick="<?= $isOut ? '' : 'addToCart(' . (int)$prod['id'] . ');'; ?>">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="badge bg-light text-secondary border font-monospace small" style="font-size: 0.7rem;">
                                        <?= e($prod['sku']); ?>
                                    </span>
                                    <?php if ($isOut): ?>
                                        <span class="badge bg-danger" style="font-size: 0.65rem;">Out of Stock</span>
                                    <?php elseif ($stock <= (int)$prod['alert_threshold']): ?>
                                        <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">Qty: <?= $stock; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success" style="font-size: 0.65rem;">Qty: <?= $stock; ?></span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="card-title fw-semibold text-dark mb-1 text-truncate" title="<?= e($prod['name']); ?>">
                                    <?= e($prod['name']); ?>
                                </h6>
                                <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;">
                                    <?= e($prod['category_name'] ?? 'General'); ?>
                                </small>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <span class="price-badge">LKR<?= number_format((float)$prod['selling_price'], 2) ?></span>
                                <button type="button" class="btn btn-sm <?= $isOut ? 'btn-secondary disabled' : 'btn-outline-primary'; ?> py-0 px-2 rounded-pill">
                                    <?= $isOut ? '<i class="bi bi-x"></i>' : '<i class="bi bi-plus"></i> Add'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="noProductsMatch" class="text-center py-5 text-muted card shadow-sm mt-3" style="display: none;">
            <i class="bi bi-search fs-1 mb-2 d-block"></i>
            <p class="mb-0 fw-semibold">No products match your current search or category filter.</p>
        </div>
    </div>

    <!-- Right Column: Interactive Shopping Cart & Checkout Form (col-lg-5 col-xl-4) -->
    <div class="col-lg-5 col-xl-4">
        <div class="card shadow-sm sticky-top" style="top: 80px;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-cart3 text-primary"></i> Current Order
                </span>
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="clearCart()" title="Empty shopping cart">
                    <i class="bi bi-trash me-1"></i> Clear
                </button>
            </div>

            <div class="card-body p-3">
                <!-- Cart Items Container -->
                <div class="cart-items-container mb-3" style="min-height: 180px;">
                    <div id="emptyCartMsg" class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        <p class="mb-1 fw-semibold">Cart is currently empty</p>
                        <small>Click any product on the left to start billing.</small>
                    </div>

                    <table class="table table-sm cart-table mb-0" id="cartTable">
                        <tbody id="cartTableBody">
                            <!-- Populated dynamically by pos.js -->
                        </tbody>
                    </table>
                </div>

                <!-- Cart Summary & Calculation Panel -->
                <div id="cartSummary" style="display: none;">
                    <div class="border-top pt-2 mb-3">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold" id="cartSubtotal">LKR 0.00</span>
                        </div>

                        <!-- Discount Section -->
                        <div class="row g-1 align-items-center my-2 bg-light p-2 rounded">
                            <div class="col-4">
                                <label class="small text-muted mb-0 fw-semibold">Discount:</label>
                            </div>
                            <div class="col-4">
                                <select id="discountType" name="discount_type_select" class="form-select form-select-sm">
                                    <option value="fixed">Flat (LKR)</option>
                                    <option value="percent">Ratio (%)</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <input type="number" step="0.01" min="0" id="discountValue" value="0" class="form-control form-select-sm text-end">
                            </div>
                            <div class="col-12 text-end text-danger small mt-1">
                                Discount Applied: <span id="cartDiscountAmount">-LKR 0.00</span>
                            </div>
                        </div>

                        <!-- Net Total -->
                        <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom my-2">
                            <span class="h5 mb-0 fw-bold">Net Total:</span>
                            <span class="h4 mb-0 fw-bold text-primary" id="cartNetTotal">LKR 0.00</span>
                        </div>
                    </div>

                    <!-- Checkout Form to modules/pos/checkout.php -->
                    <form id="checkoutForm" method="POST" action="<?= url('modules/pos/checkout.php'); ?>">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="cart_data" id="cartDataJson" value="[]">

                        <!-- Customer Details (Optional) -->
                        <div class="row g-2 mb-2">
                            <div class="col-7">
                                <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Customer Name (Optional)" value="Walk-in Customer">
                            </div>
                            <div class="col-5">
                                <input type="text" name="customer_phone" class="form-control form-control-sm" placeholder="Phone No.">
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1 fw-semibold">Payment Method</label>
                            <select name="payment_method" id="paymentMethod" class="form-select form-select-sm">
                                <option value="cash" selected>Cash Payment</option>
                                <option value="card">Credit / Debit Card</option>
                                <option value="online">Online / QR Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Cash Tendered & Change -->
                        <div class="mb-3 bg-light p-2 rounded border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="paidAmount" class="small text-muted fw-semibold mb-0">Amount Tendered (LKR):</label>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary py-0 px-1" style="font-size: 0.75rem;" onclick="setTenderAmount('exact')">Exact</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-1" style="font-size: 0.75rem;" onclick="setTenderAmount(500)">LKR 500</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-1" style="font-size: 0.75rem;" onclick="setTenderAmount(1000)">LKR 1000</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-1" style="font-size: 0.75rem;" onclick="setTenderAmount(5000)">LKR 5000</button>
                                </div>
                            </div>
                            <input type="number" step="0.01" min="0" name="paid_amount" id="paidAmount" class="form-control form-control-sm text-end fw-bold fs-6" placeholder="0.00" required>

                            <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top small">
                                <span class="text-muted fw-semibold">Change Return:</span>
                                <span class="fw-bold fs-6 text-success" id="changeAmount">LKR 0.00</span>
                            </div>
                        </div>

                        <!-- Notes (Optional) -->
                        <div class="mb-3">
                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Order notes or reference (Optional)">
                        </div>

                        <!-- Pass discount info to backend -->
                        <input type="hidden" name="discount_type" id="hiddenDiscountType" value="fixed">
                        <input type="hidden" name="discount_value" id="hiddenDiscountValue" value="0">

                        <button type="submit" id="checkoutBtn" class="btn btn-success w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" disabled>
                            <i class="bi bi-check2-circle fs-5"></i> Complete Sale & Print Receipt
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('assets/js/pos.js?v=2'); ?>"></script>
<script>
    // Keep hidden discount fields synchronized with checkout form
    document.getElementById('discountType').addEventListener('change', function() {
        document.getElementById('hiddenDiscountType').value = this.value;
    });
    document.getElementById('discountValue').addEventListener('input', function() {
        document.getElementById('hiddenDiscountValue').value = this.value;
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
