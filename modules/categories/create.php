<?php
/**
 * Categories Module: Create Category
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = get_db_connection();
$errors = [];
$name = '';
$description = '';
$isActive = 1;

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
            // Check for duplicate name
            $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE name = :name LIMIT 1");
            $stmtCheck->execute([':name' => $name]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'A category with this name already exists.';
            } else {
                try {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO categories (name, description, is_active)
                        VALUES (:name, :description, :is_active)
                    ");
                    $stmtInsert->execute([
                        ':name'        => $name,
                        ':description' => $description ?: null,
                        ':is_active'   => $isActive
                    ]);

                    set_flash('success', 'Category "' . $name . '" created successfully.');
                    redirect('modules/categories/index.php');
                } catch (PDOException $e) {
                    error_log("Category insert error: " . $e->getMessage());
                    $errors[] = 'Database error while saving category.';
                }
            }
        }
    }
}

$pageTitle = 'Add New Category';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 fw-bold mb-0">Add New Category</h2>
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

                <form method="POST" action="<?= url('modules/categories/create.php'); ?>" novalidate>
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name); ?>" required maxlength="100" placeholder="e.g. Frozen Foods">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief details about what belongs in this category..."><?= e($description); ?></textarea>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Category is active and visible in POS</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('modules/categories/index.php'); ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
