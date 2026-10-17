<?php
/**
 * Mini-POS and Inventory Management System
 * Main Application Entry Point / Router
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    if (is_admin()) {
        redirect('modules/dashboard/index.php');
    } else {
        redirect('modules/pos/index.php');
    }
} else {
    redirect('modules/auth/login.php');
}
