<?php
/**
 * Categories Module: Edit Category
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id <= 0) {
    set_flash('danger', 'Invalid category ID.');
    redirect('modules/categories/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('danger', 'Category not found.');
    redirect('modules/categories/index.php');
}

$errors = [];
$name = $category['name'];
$description = (string)($category['description'] ?? '');
$isActive = (int)$category['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $name        = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            $errors[] = 'Category name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Category name must not exceed 100 characters.';
        }

        if (empty($errors)) {
            // Check for duplicate name excluding current category
            $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND id != :id LIMIT 1");
            $stmtCheck->execute([':name' => $name, ':id' => $id]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'Another category already exists with this name.';
            } else {
                try {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE categories 
                        SET name = :name, description = :description, is_active = :is_active 
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':name'        => $name,
                        ':description' => $description ?: null,
                        ':is_active'   => $isActive,
                        ':id'          => $id
                    ]);

                    set_flash('success', 'Category updated successfully.');
                    redirect('modules/categories/index.php');
                } catch (PDOException $e) {
                    error_log("Category update error: " . $e->getMessage());
                    $errors[] = 'Database error while updating category.';
                }
            }
        }
    }
}

$pageTitle = 'Edit Category: ' . $category['name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 fw-bold mb-0">Edit Category</h2>
            <a href="<?= url('modules/categories/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Categories
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

                <form method="POST" action="<?= url('modules/categories/edit.php?id=' . $id); ?>" novalidate>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= $id; ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name); ?>" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= e($description); ?></textarea>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Category is active and visible in POS</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('modules/categories/index.php'); ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
