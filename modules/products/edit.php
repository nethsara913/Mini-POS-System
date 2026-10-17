<?php
/**
 * Products Module: Edit Product
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id <= 0) {
    set_flash('danger', 'Invalid product ID.');
    redirect('modules/products/index.php');
}

$stmtProd = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmtProd->execute([':id' => $id]);
$product = $stmtProd->fetch();

if (!$product) {
    set_flash('danger', 'Product not found.');
    redirect('modules/products/index.php');
}

// Categories list
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$errors = [];
$sku            = $product['sku'];
$name           = $product['name'];
$categoryId     = (int)$product['category_id'];
$description    = (string)($product['description'] ?? '');
$costPrice      = (string)$product['cost_price'];
$sellingPrice   = (string)$product['selling_price'];
$stockQuantity  = (string)$product['stock_quantity'];
$alertThreshold = (string)$product['alert_threshold'];
$isActive       = (int)$product['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $sku            = strtoupper(trim((string)($_POST['sku'] ?? '')));
        $name           = trim((string)($_POST['name'] ?? ''));
        $categoryId     = (int)($_POST['category_id'] ?? 0);
        $description    = trim((string)($_POST['description'] ?? ''));
        $costPrice      = trim((string)($_POST['cost_price'] ?? '0.00'));
        $sellingPrice   = trim((string)($_POST['selling_price'] ?? '0.00'));
        $stockQuantity  = trim((string)($_POST['stock_quantity'] ?? '0'));
        $alertThreshold = trim((string)($_POST['alert_threshold'] ?? '5'));
        $isActive       = isset($_POST['is_active']) ? 1 : 0;

        if (empty($sku)) {
            $errors[] = 'Product SKU / Code is required.';
        } elseif (mb_strlen($sku) > 50) {
            $errors[] = 'SKU cannot exceed 50 characters.';
        }

        if (empty($name)) {
            $errors[] = 'Product name is required.';
        } elseif (mb_strlen($name) > 150) {
            $errors[] = 'Product name cannot exceed 150 characters.';
        }

        if ($categoryId <= 0) {
            $errors[] = 'Please select a valid category.';
        }

        if (!is_numeric($costPrice) || (float)$costPrice < 0) {
            $errors[] = 'Cost price must be a valid non-negative number.';
        }

        if (!is_numeric($sellingPrice) || (float)$sellingPrice <= 0) {
            $errors[] = 'Selling price must be greater than zero.';
        }

        if (!is_numeric($stockQuantity) || (int)$stockQuantity < 0) {
            $errors[] = 'Stock quantity must be a non-negative integer.';
        }

        if (!is_numeric($alertThreshold) || (int)$alertThreshold < 0) {
            $errors[] = 'Alert threshold must be a non-negative integer.';
        }

        if (empty($errors)) {
            // Check for duplicate SKU excluding this product
            $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
            $stmtCheck->execute([':sku' => $sku, ':id' => $id]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'The SKU "' . $sku . '" is already in use by another product.';
            } else {
                try {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE products SET
                            category_id     = :category_id,
                            sku             = :sku,
                            name            = :name,
                            description     = :description,
                            cost_price      = :cost_price,
                            selling_price   = :selling_price,
                            stock_quantity  = :stock_quantity,
                            alert_threshold = :alert_threshold,
                            is_active       = :is_active
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':category_id'     => $categoryId,
                        ':sku'             => $sku,
                        ':name'            => $name,
                        ':description'     => $description ?: null,
                        ':cost_price'      => (float)$costPrice,
                        ':selling_price'   => (float)$sellingPrice,
                        ':stock_quantity'  => (int)$stockQuantity,
                        ':alert_threshold' => (int)$alertThreshold,
                        ':is_active'       => $isActive,
                        ':id'              => $id
                    ]);

                    set_flash('success', 'Product "' . $name . '" updated successfully.');
                    redirect('modules/products/index.php');
                } catch (PDOException $e) {
                    error_log("Product update error: " . $e->getMessage());
                    $errors[] = 'Database error while saving changes.';
                }
            }
        }
    }
}

$pageTitle = 'Edit Product: ' . $product['name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 fw-bold mb-0">Edit Product</h2>
            <a href="<?= url('modules/products/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Products
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('modules/products/edit.php?id=' . $id); ?>" novalidate>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= $id; ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="sku" class="form-label fw-semibold">SKU / Barcode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="sku" name="sku" value="<?= e($sku); ?>" required maxlength="50">
                        </div>

                        <div class="col-md-7">
                            <label for="category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id']; ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
                                        <?= e($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name); ?>" required maxlength="150">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= e($description); ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="cost_price" class="form-label fw-semibold">Cost Price (Purchase) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL; ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="<?= e($costPrice); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="selling_price" class="form-label fw-semibold">Selling Price (Retail) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL; ?></span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="selling_price" name="selling_price" value="<?= e($sellingPrice); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="stock_quantity" class="form-label fw-semibold">Current Stock Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="<?= e($stockQuantity); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="alert_threshold" class="form-label fw-semibold">Low-Stock Alert Threshold <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="alert_threshold" name="alert_threshold" value="<?= e($alertThreshold); ?>" required>
                        </div>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Product is active and available in POS Terminal</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('modules/products/index.php'); ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
