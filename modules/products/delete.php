<?php
/**
 * Products Module: Delete Product with Historical Integrity Protection
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Invalid request method.');
    redirect('modules/products/index.php');
}

if (!validate_csrf()) {
    set_flash('danger', 'Invalid security token.');
    redirect('modules/products/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid product ID.');
    redirect('modules/products/index.php');
}

$pdo = get_db_connection();

try {
    // Check if product exists
    $stmtProd = $pdo->prepare("SELECT id, name FROM products WHERE id = :id LIMIT 1");
    $stmtProd->execute([':id' => $id]);
    $prod = $stmtProd->fetch();

    if (!$prod) {
        set_flash('danger', 'Product not found.');
        redirect('modules/products/index.php');
    }

    // Check if product is referenced in existing sales orders
    $stmtCheckSales = $pdo->prepare("SELECT COUNT(*) AS count FROM sale_items WHERE product_id = :id");
    $stmtCheckSales->execute([':id' => $id]);
    $saleCount = (int)$stmtCheckSales->fetch()['count'];

    if ($saleCount > 0) {
        // Soft delete / deactivate so historical invoices remain valid
        $stmtDeactivate = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
        $stmtDeactivate->execute([':id' => $id]);

        set_flash('warning', 'Product "' . $prod['name'] . '" has ' . $saleCount . ' historical sales record(s). To preserve audit trail and receipts, it was deactivated instead of deleted.');
    } else {
        // Never sold: safe to hard delete
        $stmtDelete = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmtDelete->execute([':id' => $id]);

        set_flash('success', 'Product "' . $prod['name'] . '" permanently deleted.');
    }
} catch (PDOException $e) {
    error_log("Product delete error: " . $e->getMessage());
    set_flash('danger', 'Database constraint prevented product deletion.');
}

redirect('modules/products/index.php');
