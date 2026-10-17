<?php
/**
 * POS Module: Checkout Processor
 * Implements PDO Database Transactions with Row-Locking (FOR UPDATE)
 * for safe inventory concurrency and stock deduction.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_login(); // Admin and Staff authorized

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Invalid checkout request method.');
    redirect('modules/pos/index.php');
}

if (!validate_csrf()) {
    set_flash('danger', 'Invalid security token. Please try again.');
    redirect('modules/pos/index.php');
}

$rawCartData = $_POST['cart_data'] ?? '';
$cartItems = json_decode($rawCartData, true);

if (!is_array($cartItems) || empty($cartItems)) {
    set_flash('warning', 'Your shopping cart is empty.');
    redirect('modules/pos/index.php');
}

// Extract customer and payment inputs
$customerName  = trim((string)($_POST['customer_name'] ?? 'Walk-in Customer')) ?: 'Walk-in Customer';
$customerPhone = trim((string)($_POST['customer_phone'] ?? '')) ?: null;
$paymentMethod = in_array($_POST['payment_method'] ?? '', ['cash', 'card', 'online', 'other']) ? $_POST['payment_method'] : 'cash';
$notes         = trim((string)($_POST['notes'] ?? '')) ?: null;
$discountType  = ($_POST['discount_type'] ?? '') === 'percent' ? 'percent' : 'fixed';
$discountValue = max(0, (float)($_POST['discount_value'] ?? 0));
$user          = current_user();
$userId        = (int)$user['id'];

// Consolidate duplicate product entries
$consolidatedCart = [];
foreach ($cartItems as $item) {
    $productId = (int)($item['product_id'] ?? 0);
    $qty       = (int)($item['quantity'] ?? 0);
    if ($productId > 0 && $qty > 0) {
        if (!isset($consolidatedCart[$productId])) {
            $consolidatedCart[$productId] = 0;
        }
        $consolidatedCart[$productId] += $qty;
    }
}

if (empty($consolidatedCart)) {
    set_flash('warning', 'Invalid cart items.');
    redirect('modules/pos/index.php');
}

$pdo = get_db_connection();

try {
    // -------------------------------------------------------------
    // BEGIN DATABASE TRANSACTION
    // -------------------------------------------------------------
    $pdo->beginTransaction();

    $calculatedSubtotal = 0.00;
    $verifiedLineItems = [];

    // Statement for row-locking product record
    $stmtLockProduct = $pdo->prepare("
        SELECT id, sku, name, selling_price, stock_quantity, is_active 
        FROM products 
        WHERE id = :id 
        FOR UPDATE
    ");

    // Statement for updating inventory stock
    $stmtDeductStock = $pdo->prepare("
        UPDATE products 
        SET stock_quantity = stock_quantity - :deduct_qty 
        WHERE id = :id
    ");

    // Process each cart item under row lock
    foreach ($consolidatedCart as $productId => $requestedQty) {
        $stmtLockProduct->execute([':id' => $productId]);
        $product = $stmtLockProduct->fetch();

        if (!$product) {
            throw new Exception("Product ID #{$productId} no longer exists.");
        }

        if ((int)$product['is_active'] !== 1) {
            throw new Exception("Product '{$product['name']}' is currently inactive.");
        }

        $currentStock = (int)$product['stock_quantity'];
        if ($currentStock < $requestedQty) {
            throw new Exception(
                "Insufficient stock for product '{$product['name']}'. Requested: {$requestedQty}, Available in stock: {$currentStock}."
            );
        }

        $unitPrice = (float)$product['selling_price'];
        $lineTotal = round($unitPrice * $requestedQty, 2);
        $calculatedSubtotal += $lineTotal;

        // Deduct inventory stock safely
        $stmtDeductStock->execute([
            ':deduct_qty' => $requestedQty,
            ':id'         => $productId
        ]);

        $verifiedLineItems[] = [
            'product_id'   => (int)$product['id'],
            'product_name' => $product['name'],
            'sku'          => $product['sku'],
            'unit_price'   => $unitPrice,
            'quantity'     => $requestedQty,
            'subtotal'     => $lineTotal
        ];
    }

    // Calculate discount amount
    $discountAmount = 0.00;
    if ($discountType === 'percent') {
        $discountValue = min(100.0, max(0.0, $discountValue));
        $discountAmount = round($calculatedSubtotal * ($discountValue / 100), 2);
    } else {
        $discountAmount = min($calculatedSubtotal, max(0.0, $discountValue));
    }

    $finalTotal = round(max(0.00, $calculatedSubtotal - $discountAmount), 2);

    // Validate payment amount tendered
    $paidAmountInput = (float)($_POST['paid_amount'] ?? 0);
    if ($paymentMethod === 'cash') {
        if ($paidAmountInput < $finalTotal) {
            throw new Exception(
                "Tendered cash amount (LKR " . number_format($paidAmountInput, 2) . ") is less than the net order total (LKR " . number_format($finalTotal, 2) . ")."
            );
        }
        $paidAmount = $paidAmountInput;
        $changeAmount = round($paidAmount - $finalTotal, 2);
    } else {
        // For card/online/other, paid amount matches net total
        $paidAmount = $finalTotal;
        $changeAmount = 0.00;
    }

    // Generate unique invoice number: INV-YYYYMMDD-XXXX
    $datePart = date('Ymd');
    $randomPart = strtoupper(bin2hex(random_bytes(2)));
    $invoiceNo = sprintf('INV-%s-%s', $datePart, $randomPart);

    // Insert into sales table
    $stmtInsertSale = $pdo->prepare("
        INSERT INTO sales (
            invoice_no, user_id, customer_name, customer_phone,
            subtotal, discount_type, discount_value, discount_amount,
            tax_amount, final_total, payment_method, paid_amount,
            change_amount, notes, created_at
        ) VALUES (
            :invoice_no, :user_id, :customer_name, :customer_phone,
            :subtotal, :discount_type, :discount_value, :discount_amount,
            0.00, :final_total, :payment_method, :paid_amount,
            :change_amount, :notes, NOW()
        )
    ");

    $stmtInsertSale->execute([
        ':invoice_no'      => $invoiceNo,
        ':user_id'         => $userId,
        ':customer_name'   => $customerName,
        ':customer_phone'  => $customerPhone,
        ':subtotal'        => $calculatedSubtotal,
        ':discount_type'   => $discountType,
        ':discount_value'  => $discountValue,
        ':discount_amount' => $discountAmount,
        ':final_total'     => $finalTotal,
        ':payment_method'  => $paymentMethod,
        ':paid_amount'     => $paidAmount,
        ':change_amount'   => $changeAmount,
        ':notes'           => $notes
    ]);

    $saleId = (int)$pdo->lastInsertId();

    // Insert sale line items
    $stmtInsertItem = $pdo->prepare("
        INSERT INTO sale_items (
            sale_id, product_id, product_name, sku, unit_price, quantity, subtotal
        ) VALUES (
            :sale_id, :product_id, :product_name, :sku, :unit_price, :quantity, :subtotal
        )
    ");

    foreach ($verifiedLineItems as $line) {
        $stmtInsertItem->execute([
            ':sale_id'      => $saleId,
            ':product_id'   => $line['product_id'],
            ':product_name' => $line['product_name'],
            ':sku'          => $line['sku'],
            ':unit_price'   => $line['unit_price'],
            ':quantity'     => $line['quantity'],
            ':subtotal'     => $line['subtotal']
        ]);
    }

    // -------------------------------------------------------------
    // COMMIT TRANSACTION
    // -------------------------------------------------------------
    $pdo->commit();

    set_flash('success', "Sale #{$invoiceNo} completed successfully!");
    redirect("modules/pos/receipt.php?id={$saleId}");

} catch (Exception $e) {
    // -------------------------------------------------------------
    // ROLLBACK ON ANY ERROR OR CONCURRENCY FAILURE
    // -------------------------------------------------------------
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("POS Checkout Transaction Failed: " . $e->getMessage());
    set_flash('danger', 'Checkout Failed: ' . $e->getMessage());
    redirect('modules/pos/index.php');
}
