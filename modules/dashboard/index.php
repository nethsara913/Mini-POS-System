<?php
/**
 * Dashboard Module: Admin KPI Overview
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin(); // Restrict access to Admin role only

$pdo = get_db_connection();

// 1. Total Active Products Count
$stmtProducts = $pdo->query("SELECT COUNT(*) AS total_products FROM products WHERE is_active = 1");
$totalProducts = (int)$stmtProducts->fetch()['total_products'];

// 2. Low Stock Count (stock_quantity <= alert_threshold)
$stmtLowStock = $pdo->query("SELECT COUNT(*) AS low_stock_count FROM products WHERE is_active = 1 AND stock_quantity <= alert_threshold");
$lowStockCount = (int)$stmtLowStock->fetch()['low_stock_count'];

// 3. Today's Sales Revenue & Today's Orders Count
$stmtTodaySales = $pdo->query("
    SELECT 
        COALESCE(SUM(final_total), 0.00) AS today_revenue,
        COUNT(*) AS today_orders
    FROM sales 
    WHERE DATE(created_at) = CURDATE()
");
$todayData = $stmtTodaySales->fetch();
$todayRevenue = (float)$todayData['today_revenue'];
$todayOrders  = (int)$todayData['today_orders'];

// 4. All-time Total Revenue
$stmtAllTime = $pdo->query("SELECT COALESCE(SUM(final_total), 0.00) AS total_revenue FROM sales");
$allTimeRevenue = (float)$stmtAllTime->fetch()['total_revenue'];

// 5. Low Stock Items List (Limit to top 8)
$stmtLowStockItems = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock_quantity, p.alert_threshold, p.selling_price, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 AND p.stock_quantity <= p.alert_threshold
    ORDER BY p.stock_quantity ASC, p.name ASC
    LIMIT 8
");
$lowStockItems = $stmtLowStockItems->fetchAll();

// 6. Recent Sales Invoices (Limit 6)
$stmtRecentSales = $pdo->query("
    SELECT s.id, s.invoice_no, s.customer_name, s.final_total, s.payment_method, s.created_at, u.full_name AS cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 6
");
$recentSales = $stmtRecentSales->fetchAll();

$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-3 border-bottom gap-2">
    <div>
        <h2 class="h3 fw-bold mb-0">System Dashboard</h2>
        <p class="text-muted small mb-0">Welcome back, <?= e(current_user()['full_name']); ?>! Real-time metrics and inventory health.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('modules/pos/index.php'); ?>" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-cart-plus-fill"></i> New POS Sale
        </a>
        <a href="<?= url('modules/reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-graph-up-arrow"></i> Sales Reports
        </a>
    </div>
</div>

<!-- Key Performance Indicators (KPIs) -->
<div class="row g-3 mb-4">
    <!-- Today's Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card stat-success">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Today's Revenue</span>
                    <h3 class="fw-bold my-1 text-success"><?= format_currency($todayRevenue); ?></h3>
                    <span class="text-muted small"><?= $todayOrders; ?> sale<?= $todayOrders === 1 ? '' : 's'; ?> completed today</span>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Products -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Products</span>
                    <h3 class="fw-bold my-1"><?= $totalProducts; ?></h3>
                    <a href="<?= url('modules/products/index.php'); ?>" class="small text-decoration-none">Manage catalog &rarr;</a>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Count -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card <?= $lowStockCount > 0 ? 'stat-warning' : 'stat-success'; ?>">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Low Stock Items</span>
                    <h3 class="fw-bold my-1 <?= $lowStockCount > 0 ? 'text-warning' : 'text-success'; ?>"><?= $lowStockCount; ?></h3>
                    <span class="text-muted small"><?= $lowStockCount > 0 ? 'Requires attention' : 'Healthy inventory'; ?></span>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Lifetime Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 stat-card stat-info">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Sales Recorded</span>
                    <h3 class="fw-bold my-1 text-info"><?= format_currency($allTimeRevenue); ?></h3>
                    <a href="<?= url('modules/reports/index.php'); ?>" class="small text-decoration-none">View report history &rarr;</a>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Low Stock Warning Table -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill text-danger"></i> Low Stock Alerts
                    <?php if ($lowStockCount > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $lowStockCount; ?></span>
                    <?php endif; ?>
                </span>
                <a href="<?= url('modules/products/index.php?filter=low_stock'); ?>" class="btn btn-outline-secondary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockItems)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-1 mb-2 d-block"></i>
                        <p class="mb-0 fw-semibold">All products have sufficient stock levels.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Threshold</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 180px;"><?= e($item['name']); ?></div>
                                            <small class="text-muted font-monospace"><?= e($item['sku']); ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($item['category_name'] ?? 'General'); ?></span></td>
                                        <td class="text-center">
                                            <span class="badge <?= (int)$item['stock_quantity'] === 0 ? 'bg-danger' : 'bg-warning text-dark'; ?> fs-6">
                                                <?= (int)$item['stock_quantity']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center text-muted small"><?= (int)$item['alert_threshold']; ?></td>
                                        <td class="text-end">
                                            <a href="<?= url('modules/products/edit.php?id=' . (int)$item['id']); ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="Edit or Restock Product">
                                                Restock
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

    <!-- Recent Sales Table -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-receipt text-primary"></i> Recent Transactions
                </span>
                <a href="<?= url('modules/reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm">Full Report</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentSales)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-receipt fs-1 mb-2 d-block"></i>
                        <p class="mb-0">No transactions recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer / Cashier</th>
                                    <th>Payment</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td>
                                            <span class="font-monospace fw-semibold small text-primary"><?= e($sale['invoice_no']); ?></span>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?= format_datetime($sale['created_at']); ?></div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($sale['customer_name']); ?></div>
                                            <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($sale['cashier_name'] ?? 'Staff'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary text-uppercase" style="font-size: 0.7rem;"><?= e($sale['payment_method']); ?></span>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            <?= format_currency($sale['final_total']); ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('modules/pos/receipt.php?id=' . (int)$sale['id']); ?>" class="btn btn-sm btn-light border py-1 px-2" title="View & Print Receipt">
                                                <i class="bi bi-printer"></i>
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
