<?php
/**
 * Reports Module: Date-Range Sales Reports & Analytics
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin(); // Admin access only

$pdo = get_db_connection();

// Determine date range filters (default: current month)
$startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$endDate   = trim((string)($_GET['end_date'] ?? date('Y-m-d')));
$cashierId = (int)($_GET['cashier_id'] ?? 0);
$method    = trim((string)($_GET['payment_method'] ?? ''));

// Fetch users for cashier filter
$cashiers = $pdo->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll();

// Build Sales Query
$whereClauses = ["DATE(s.created_at) >= :start_date", "DATE(s.created_at) <= :end_date"];
$params = [
    ':start_date' => $startDate,
    ':end_date'   => $endDate
];

if ($cashierId > 0) {
    $whereClauses[] = "s.user_id = :cashier_id";
    $params[':cashier_id'] = $cashierId;
}

if (!empty($method)) {
    $whereClauses[] = "s.payment_method = :payment_method";
    $params[':payment_method'] = $method;
}

$whereSql = implode(' AND ', $whereClauses);

// 1. Summary Statistics for selected period
$stmtSummary = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.id) AS total_orders,
        COALESCE(SUM(s.final_total), 0.00) AS total_revenue,
        COALESCE(SUM(s.subtotal), 0.00) AS total_subtotal,
        COALESCE(SUM(s.discount_amount), 0.00) AS total_discounts
    FROM sales s
    WHERE {$whereSql}
");
$stmtSummary->execute($params);
$summary = $stmtSummary->fetch();

$totalOrders    = (int)$summary['total_orders'];
$totalRevenue   = (float)$summary['total_revenue'];
$totalDiscounts = (float)$summary['total_discounts'];
$avgOrderValue  = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0.00;

// 2. Total items sold count in period
$stmtItemsCount = $pdo->prepare("
    SELECT COALESCE(SUM(si.quantity), 0) AS total_items
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    WHERE {$whereSql}
");
$stmtItemsCount->execute($params);
$totalItemsSold = (int)$stmtItemsCount->fetch()['total_items'];

// 3. Transactions list
$stmtSalesList = $pdo->prepare("
    SELECT 
        s.id,
        s.invoice_no,
        s.customer_name,
        s.subtotal,
        s.discount_amount,
        s.final_total,
        s.payment_method,
        s.created_at,
        u.full_name AS cashier_name,
        COUNT(si.id) AS items_count
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE {$whereSql}
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmtSalesList->execute($params);
$sales = $stmtSalesList->fetchAll();

// 4. Top Selling Products in period
$stmtTopProducts = $pdo->prepare("
    SELECT 
        si.product_name,
        si.sku,
        SUM(si.quantity) AS total_qty_sold,
        SUM(si.subtotal) AS total_product_revenue
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    WHERE {$whereSql}
    GROUP BY si.product_id, si.product_name, si.sku
    ORDER BY total_qty_sold DESC, total_product_revenue DESC
    LIMIT 5
");
$stmtTopProducts->execute($params);
$topProducts = $stmtTopProducts->fetchAll();

// Build URL parameters for print view
$printParams = http_build_query([
    'start_date'     => $startDate,
    'end_date'       => $endDate,
    'cashier_id'     => $cashierId,
    'payment_method' => $method
]);

$pageTitle = 'Sales Reports';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-3 border-bottom gap-2">
    <div>
        <h2 class="h3 fw-bold mb-0">Sales Analytics & Reports</h2>
        <p class="text-muted small mb-0">Generate date-range financial summaries, revenue trends, and audit logs.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('modules/reports/print.php?' . $printParams); ?>" target="_blank" class="btn btn-outline-dark btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-printer"></i> Printable Report View
        </a>
    </div>
</div>

<!-- Date-Range Filter Form -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('modules/reports/index.php'); ?>" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate); ?>" required>
            </div>

            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate); ?>" required>
            </div>

            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold">Cashier / Staff</label>
                <select name="cashier_id" class="form-select">
                    <option value="0">All Staff Cashiers</option>
                    <?php foreach ($cashiers as $c): ?>
                        <option value="<?= (int)$c['id']; ?>" <?= $cashierId === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?= e($c['full_name']); ?> (<?= e($c['username']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-sm-6 col-md-2">
                <label class="form-label small fw-semibold">Payment Method</label>
                <select name="payment_method" class="form-select">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="card" <?= $method === 'card' ? 'selected' : ''; ?>>Card</option>
                    <option value="online" <?= $method === 'online' ? 'selected' : ''; ?>>Online</option>
                </select>
            </div>

            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100" title="Apply Filter">
                    <i class="bi bi-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card stat-success">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Total Net Revenue</span>
                <h3 class="fw-bold my-1 text-success"><?= format_currency($totalRevenue); ?></h3>
                <small class="text-muted"><?= $startDate; ?> to <?= $endDate; ?></small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Invoices Completed</span>
                <h3 class="fw-bold my-1 text-primary"><?= $totalOrders; ?></h3>
                <small class="text-muted">Avg Order: <?= format_currency($avgOrderValue); ?></small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card stat-info">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Units / Items Sold</span>
                <h3 class="fw-bold my-1 text-info"><?= $totalItemsSold; ?></h3>
                <small class="text-muted">Total physical products sold</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card stat-warning">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Discounts Granted</span>
                <h3 class="fw-bold my-1 text-warning"><?= format_currency($totalDiscounts); ?></h3>
                <small class="text-muted">Total promotional price savings</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Detailed Transactions Table -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold fs-6">Transactions History (<?= count($sales); ?> Records)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($sales)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i>
                        <p class="mb-0">No sales transactions found for the chosen date range.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Cashier</th>
                                    <th>Method</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Final Total</th>
                                    <th class="text-center">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td>
                                            <span class="font-monospace fw-semibold small text-primary"><?= e($sale['invoice_no']); ?></span>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?= e($sale['customer_name']); ?></div>
                                        </td>
                                        <td class="small">
                                            <?= format_datetime($sale['created_at']); ?>
                                        </td>
                                        <td class="small">
                                            <?= e($sale['cashier_name'] ?? 'Staff'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary text-uppercase" style="font-size: 0.7rem;">
                                                <?= e($sale['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-muted small">
                                            <?= (float)$sale['discount_amount'] > 0 ? '-' . format_currency($sale['discount_amount']) : '—'; ?>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            <?= format_currency($sale['final_total']); ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= url('modules/pos/receipt.php?id=' . (int)$sale['id']); ?>" class="btn btn-sm btn-light border py-0 px-2" title="View & Print Receipt">
                                                <i class="bi bi-receipt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Products in Range -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold fs-6">Top Selling Items</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topProducts)): ?>
                    <div class="text-center py-5 text-muted small">
                        No product breakdown available.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($topProducts as $idx => $prod): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                                        <?= $idx + 1; ?>
                                    </span>
                                    <div>
                                        <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 170px;" title="<?= e($prod['product_name']); ?>">
                                            <?= e($prod['product_name']); ?>
                                        </h6>
                                        <small class="text-muted font-monospace"><?= e($prod['sku']); ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark border"><?= (int)$prod['total_qty_sold']; ?> sold</span>
                                    <div class="small fw-bold text-success mt-1"><?= format_currency($prod['total_product_revenue']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
