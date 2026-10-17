<?php
/**
 * Categories Module: List & Overview
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = get_db_connection();
$search = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT 
        c.id, 
        c.name, 
        c.description, 
        c.is_active, 
        c.created_at,
        COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
";

$params = [];
if (!empty($search)) {
    $sql .= " WHERE c.name LIKE :search OR c.description LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " GROUP BY c.id ORDER BY c.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

$pageTitle = 'Category Management';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-3 border-bottom gap-2">
    <div>
        <h2 class="h3 fw-bold mb-0">Categories</h2>
        <p class="text-muted small mb-0">Organize products into logical groups for POS and inventory tracking.</p>
    </div>
    <div>
        <a href="<?= url('modules/categories/create.php'); ?>" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-plus-circle"></i> Add New Category
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('modules/categories/index.php'); ?>" class="row g-2 align-items-center">
            <div class="col-sm-8 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search categories..." value="<?= e($search); ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= url('modules/categories/index.php'); ?>" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-sm-4 col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($categories)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-tags fs-1 d-block mb-2"></i>
                <p class="mb-2">No categories found matching your criteria.</p>
                <a href="<?= url('modules/categories/create.php'); ?>" class="btn btn-sm btn-primary">Create Your First Category</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th class="text-center">Assigned Products</th>
                            <th class="text-center">Status</th>
                            <th class="text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td class="text-muted"><?= $index + 1; ?></td>
                                <td>
                                    <strong><?= e($cat['name']); ?></strong>
                                </td>
                                <td class="text-muted small">
                                    <?= e($cat['description'] ?: '—'); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">
                                        <?= (int)$cat['product_count']; ?> item<?= (int)$cat['product_count'] === 1 ? '' : 's'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$cat['is_active'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('modules/categories/edit.php?id=' . (int)$cat['id']); ?>" class="btn btn-outline-primary" title="Edit Category">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="POST" action="<?= url('modules/categories/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete category \'<?= addslashes(e($cat['name'])); ?>\'?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= (int)$cat['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete Category" <?= (int)$cat['product_count'] > 0 ? 'disabled title="Cannot delete: products are assigned to this category"' : ''; ?>>
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
