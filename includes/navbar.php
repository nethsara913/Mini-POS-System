<?php
/**
 * Navigation Bar Component
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$user = current_user();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= url(); ?>">
            <i class="bi bi-cart4 text-primary fs-4"></i>
            <span><?= APP_NAME ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <?php if (is_logged_in()): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/dashboard/') ? 'active fw-semibold' : ''; ?>" href="<?= url('modules/dashboard/index.php'); ?>">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- POS Terminal accessible by both Admin and Staff -->
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($currentUri, '/pos/') ? 'active fw-semibold' : ''; ?>" href="<?= url('modules/pos/index.php'); ?>">
                            <i class="bi bi-laptop me-1"></i> POS Terminal
                        </a>
                    </li>

                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/products/') ? 'active fw-semibold' : ''; ?>" href="<?= url('modules/products/index.php'); ?>">
                                <i class="bi bi-box-seam me-1"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/categories/') ? 'active fw-semibold' : ''; ?>" href="<?= url('modules/categories/index.php'); ?>">
                                <i class="bi bi-tags me-1"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/reports/') ? 'active fw-semibold' : ''; ?>" href="<?= url('modules/reports/index.php'); ?>">
                                <i class="bi bi-graph-up-arrow me-1"></i> Sales Reports
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <div class="text-light d-flex align-items-center gap-2">
                        <span class="badge <?= is_admin() ? 'bg-danger' : 'bg-primary'; ?> text-uppercase px-2 py-1">
                            <?= e($user['role']); ?>
                        </span>
                        <span class="fw-semibold small text-truncate" style="max-width: 160px;" title="<?= e($user['full_name']); ?>">
                            <i class="bi bi-person-circle me-1"></i><?= e($user['full_name']); ?>
                        </span>
                    </div>

                    <a href="<?= url('modules/auth/logout.php'); ?>" class="btn btn-outline-light btn-sm d-flex align-items-center gap-1" title="Sign out of the system" onclick="return confirm('Are you sure you want to log out?');">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="ms-auto">
                    <a href="<?= url('modules/auth/login.php'); ?>" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
