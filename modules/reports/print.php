<?php
/**
 * Reports Module: Clean Printable Sales Report
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = get_db_connection();

$startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$endDate   = trim((string)($_GET['end_date'] ?? date('Y-m-d')));
$cashierId = (int)($_GET['cashier_id'] ?? 0);
$method    = trim((string)($_GET['payment_method'] ?? ''));

// Cashier name if filtered
$cashierName = 'All Staff';
if ($cashierId > 0) {
    $stmtC = $pdo->prepare("SELECT full_name FROM users WHERE id = :id");
    $stmtC->execute([':id' => $cashierId]);
    $cRow = $stmtC->fetch();
    if ($cRow) {
        $cashierName = $cRow['full_name'];
    }
}

// Build sales query
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

// Summary statistics
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

// Transactions list
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
        u.full_name AS cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE {$whereSql}
    ORDER BY s.created_at ASC
");
$stmtSalesList->execute($params);
$sales = $stmtSalesList->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report: <?= e($startDate); ?> to <?= e($endDate); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #1e293b;
            background: #ffffff;
            padding: 30px;
        }
        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .stat-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px 16px;
            background: #f8fafc;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print mb-4 d-flex justify-content-between align-items-center bg-light p-3 rounded border">
        <div>
            <strong>Printable Financial Report</strong> &bull; Ready for paper print or PDF export.
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                Print This Report
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">
                Close Window
            </button>
        </div>
    </div>

    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-7">
                <h3 class="fw-bold mb-1"><?= e(APP_NAME); ?></h3>
                <h5 class="text-secondary mb-0">Sales & Revenue Audit Report</h5>
            </div>
            <div class="col-5 text-end small">
                <div><strong>Period:</strong> <?= e($startDate); ?> &mdash; <?= e($endDate); ?></div>
                <div><strong>Cashier Filter:</strong> <?= e($cashierName); ?></div>
                <div><strong>Generated:</strong> <?= date('M d, Y h:i A'); ?></div>
                <div><strong>Auditor:</strong> <?= e(current_user()['full_name']); ?></div>
            </div>
        </div>
    </div>

    <!-- Summary KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-box">
                <span class="text-muted small text-uppercase fw-semibold">Net Sales Revenue</span>
                <h4 class="fw-bold mb-0 text-dark"><?= format_currency($totalRevenue); ?></h4>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box">
                <span class="text-muted small text-uppercase fw-semibold">Completed Invoices</span>
                <h4 class="fw-bold mb-0 text-dark"><?= $totalOrders; ?></h4>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box">
                <span class="text-muted small text-uppercase fw-semibold">Total Discounts Allowed</span>
                <h4 class="fw-bold mb-0 text-danger"><?= format_currency($totalDiscounts); ?></h4>
            </div>
        </div>
    </div>

    <!-- Transaction Table -->
    <table class="table table-bordered table-sm align-middle">
        <thead class="table-light small">
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 20%;">Date & Time</th>
                <th style="width: 20%;">Invoice #</th>
                <th style="width: 18%;">Cashier</th>
                <th style="width: 12%;">Method</th>
                <th class="text-end" style="width: 12%;">Discount</th>
                <th class="text-end" style="width: 13%;">Net Total</th>
            </tr>
        </thead>
        <tbody class="small">
            <?php if (empty($sales)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No sales data recorded for the selected period.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sales as $idx => $sale): ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1; ?></td>
                        <td><?= format_datetime($sale['created_at']); ?></td>
                        <td class="font-monospace fw-semibold"><?= e($sale['invoice_no']); ?></td>
                        <td><?= e($sale['cashier_name'] ?? 'Staff'); ?></td>
                        <td class="text-uppercase"><?= e($sale['payment_method']); ?></td>
                        <td class="text-end text-muted">
                            <?= (float)$sale['discount_amount'] > 0 ? '-' . format_currency($sale['discount_amount']) : '0.00'; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?= format_currency($sale['final_total']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold small">
            <tr>
                <td colspan="5" class="text-end">PERIOD TOTALS:</td>
                <td class="text-end text-danger"><?= format_currency($totalDiscounts); ?></td>
                <td class="text-end"><?= format_currency($totalRevenue); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="row mt-5 pt-4 text-center small text-muted">
        <div class="col-6">
            <div style="border-top: 1px solid #94a3b8; width: 60%; margin: 0 auto; padding-top: 5px;">
                Audited & Verified By
            </div>
        </div>
        <div class="col-6">
            <div style="border-top: 1px solid #94a3b8; width: 60%; margin: 0 auto; padding-top: 5px;">
                Management Signature
            </div>
        </div>
    </div>

</body>
</html>
