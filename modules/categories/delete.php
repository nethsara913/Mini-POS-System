<?php
/**
 * Categories Module: Safe Delete Category
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Invalid request method.');
    redirect('modules/categories/index.php');
}

if (!validate_csrf()) {
    set_flash('danger', 'Invalid security token.');
    redirect('modules/categories/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid category ID.');
    redirect('modules/categories/index.php');
}

$pdo = get_db_connection();

try {
    // Check if category exists
    $stmtCat = $pdo->prepare("SELECT id, name FROM categories WHERE id = :id LIMIT 1");
    $stmtCat->execute([':id' => $id]);
    $cat = $stmtCat->fetch();

    if (!$cat) {
        set_flash('danger', 'Category not found.');
        redirect('modules/categories/index.php');
    }

    // Check if products are associated with this category
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) AS count FROM products WHERE category_id = :id");
    $stmtCheck->execute([':id' => $id]);
    $productCount = (int)$stmtCheck->fetch()['count'];

    if ($productCount > 0) {
        set_flash('danger', 'Cannot delete category "' . $cat['name'] . '": ' . $productCount . ' product(s) are currently linked to it. Please reassign or delete those products first.');
        redirect('modules/categories/index.php');
    }

    // Safe to delete
    $stmtDelete = $pdo->prepare("DELETE FROM categories WHERE id = :id");
    $stmtDelete->execute([':id' => $id]);

    set_flash('success', 'Category "' . $cat['name'] . '" deleted successfully.');
} catch (PDOException $e) {
    error_log("Category delete error: " . $e->getMessage());
    set_flash('danger', 'Database constraint prevented category deletion.');
}

redirect('modules/categories/index.php');
