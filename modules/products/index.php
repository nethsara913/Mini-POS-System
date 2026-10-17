<?php
/**
 * Products Module: Inventory Catalog List
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin(); // Admin only

$pdo = get_db_connection();

$search     = trim((string)($_GET['q'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);
$stockFilter = trim((string)($_GET['filter'] ?? ''));

// Fetch categories for filter dropdown
$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
$filterCategories = $categoriesStmt->fetchAll();

// Build products query
$sql = "
    SELECT 
        p.id,
        p.sku,
        p.name,
        p.cost_price,
        p.selling_price,
        p.stock_quantity,
        p.alert_threshold,
        p.is_active,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($categoryId > 0) {
    $sql .= " AND p.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}

if ($stockFilter === 'low_stock') {
    $sql .= " AND p.stock_quantity <= p.alert_threshold AND p.stock_quantity > 0";
} elseif ($stockFilter === 'out_of_stock') {
    $sql .= " AND p.stock_quantity <= 0";
} elseif ($stockFilter === 'in_stock') {
    $sql .= " AND p.stock_quantity > p.alert_threshold";
}

$sql .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get catalog summary stats
$statTotal = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$statLow   = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= alert_threshold AND stock_quantity > 0")->fetchColumn();
$statOut   = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 0")->fetchColumn();

$pageTitle = 'Products & Inventory';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-3 border-bottom gap-2">
    <div>
        <h2 class="h3 fw-bold mb-0">Products & Inventory</h2>
        <p class="text-muted small mb-0">Track product master records, stock quantities, and pricing.</p>
    </div>
    <div>
        <a href="<?= url('modules/products/create.php'); ?>" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-plus-circle"></i> Add New Product
        </a>
    </div>
</div>

<!-- Stock Quick Stats -->
<div class="row g-2 mb-3">
    <div class="col-sm-4">
        <a href="<?= url('modules/products/index.php'); ?>" class="text-decoration-none">
            <div class="card p-2 text-center <?= empty($stockFilter) ? 'border-primary bg-light' : ''; ?>">
                <span class="text-muted small">Total Products</span>
                <span class="fw-bold fs-5 text-dark"><?= $statTotal; ?></span>
            </div>
        </a>
    </div>
    <div class="col-sm-4">
        <a href="<?= url('modules/products/index.php?filter=low_stock'); ?>" class="text-decoration-none">
            <div class="card p-2 text-center <?= $stockFilter === 'low_stock' ? 'border-warning bg-warning bg-opacity-10' : ''; ?>">
                <span class="text-muted small text-warning-emphasis">Low Stock Warning</span>
                <span class="fw-bold fs-5 text-warning"><?= $statLow; ?></span>
            </div>
        </a>
    </div>
    <div class="col-sm-4">
        <a href="<?= url('modules/products/index.php?filter=out_of_stock'); ?>" class="text-decoration-none">
            <div class="card p-2 text-center <?= $stockFilter === 'out_of_stock' ? 'border-danger bg-danger bg-opacity-10' : ''; ?>">
                <span class="text-muted small text-danger-emphasis">Out of Stock</span>
                <span class="fw-bold fs-5 text-danger"><?= $statOut; ?></span>
            </div>
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('modules/products/index.php'); ?>" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by name or SKU..." value="<?= e($search); ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="category_id" class="form-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($filterCategories as $fc): ?>
                        <option value="<?= (int)$fc['id']; ?>" <?= $categoryId === (int)$fc['id'] ? 'selected' : ''; ?>>
                            <?= e($fc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="filter" class="form-select">
                    <option value="">All Stock</option>
                    <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low_stock" <?= $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
                <?php if (!empty($search) || $categoryId > 0 || !empty($stockFilter)): ?>
                    <a href="<?= url('modules/products/index.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                <p class="mb-2">No products match your search or filter criteria.</p>
                <a href="<?= url('modules/products/create.php'); ?>" class="btn btn-sm btn-primary">Add a Product</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th class="text-end">Cost</th>
                            <th class="text-end">Price</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $prod): 
                            $stock = (int)$prod['stock_quantity'];
                            $thresh = (int)$prod['alert_threshold'];
                        ?>
                            <tr>
                                <td>
                                    <span class="font-monospace fw-semibold small text-primary"><?= e($prod['sku']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($prod['name']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($prod['category_name'] ?? 'Uncategorized'); ?></span>
                                </td>
                                <td class="text-end text-muted small">
                                    <?= format_currency($prod['cost_price']); ?>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?= format_currency($prod['selling_price']); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($stock <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock (0)</span>
                                    <?php elseif ($stock <= $thresh): ?>
                                        <span class="badge bg-warning text-dark">Low (<?= $stock; ?>) &le; <?= $thresh; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= $stock; ?> in stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$prod['is_active'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('modules/products/edit.php?id=' . (int)$prod['id']); ?>" class="btn btn-outline-primary" title="Edit Product">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="POST" action="<?= url('modules/products/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete product \'<?= addslashes(e($prod['name'])); ?>\'?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= (int)$prod['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete Product">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
