<?php
/**
 * Products Module: Create Product
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = get_db_connection();

// Load categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$errors = [];
$sku            = 'SKU-' . strtoupper(bin2hex(random_bytes(3)));
$name           = '';
$categoryId     = 0;
$description    = '';
$costPrice      = '0.00';
$sellingPrice   = '0.00';
$stockQuantity  = '10';
$alertThreshold = '5';
$isActive       = 1;

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

        // Validation
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
            // Check for duplicate SKU
            $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
            $stmtCheck->execute([':sku' => $sku]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'The SKU "' . $sku . '" is already in use by another product.';
            } else {
                try {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO products 
                            (category_id, sku, name, description, cost_price, selling_price, stock_quantity, alert_threshold, is_active)
                        VALUES 
                            (:category_id, :sku, :name, :description, :cost_price, :selling_price, :stock_quantity, :alert_threshold, :is_active)
                    ");
                    $stmtInsert->execute([
                        ':category_id'     => $categoryId,
                        ':sku'             => $sku,
                        ':name'            => $name,
                        ':description'     => $description ?: null,
                        ':cost_price'      => (float)$costPrice,
                        ':selling_price'   => (float)$sellingPrice,
                        ':stock_quantity'  => (int)$stockQuantity,
                        ':alert_threshold' => (int)$alertThreshold,
                        ':is_active'       => $isActive
                    ]);

                    set_flash('success', 'Product "' . $name . '" created successfully.');
                    redirect('modules/products/index.php');
                } catch (PDOException $e) {
                    error_log("Product creation error: " . $e->getMessage());
                    $errors[] = 'Database error while saving product.';
                }
            }
        }
    }
}

$pageTitle = 'Add New Product';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 fw-bold mb-0">Add New Product</h2>
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

                <form method="POST" action="<?= url('modules/products/create.php'); ?>" novalidate>
                    <?= csrf_field(); ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="sku" class="form-label fw-semibold">SKU / Barcode <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="sku" name="sku" value="<?= e($sku); ?>" required maxlength="50">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('sku').value='SKU-' + Math.random().toString(36).substring(2, 8).toUpperCase();" title="Generate Random SKU">
                                    <i class="bi bi-arrow-repeat"></i> Auto
                                </button>
                            </div>
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
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name); ?>" required maxlength="150" placeholder="e.g. Arabica Coffee Beans 250g">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Item description, unit, or specifications..."><?= e($description); ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="cost_price" class="form-label fw-semibold">Cost Price (Purchase) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL; ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="<?= e($costPrice); ?>" required>
                            </div>
                            <small class="text-muted">Wholesale/purchase unit cost</small>
                        </div>

                        <div class="col-md-6">
                            <label for="selling_price" class="form-label fw-semibold">Selling Price (Retail) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL; ?></span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="selling_price" name="selling_price" value="<?= e($sellingPrice); ?>" required>
                            </div>
                            <small class="text-muted">Shelf price charged to customers</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="stock_quantity" class="form-label fw-semibold">Initial Stock Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="<?= e($stockQuantity); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="alert_threshold" class="form-label fw-semibold">Low-Stock Alert Threshold <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="alert_threshold" name="alert_threshold" value="<?= e($alertThreshold); ?>" required>
                            <small class="text-muted">Trigger low stock alert when stock &le; threshold</small>
                        </div>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Product is active and available in POS Terminal</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('modules/products/index.php'); ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Create Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
