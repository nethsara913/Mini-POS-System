<?php
/**
 * Mini-POS and Inventory Management System
 * Global Application Configuration & Helper Functions
 */

declare(strict_types=1);

// Prevent direct script execution issues and set timezone
date_default_timezone_set('Asia/Colombo'); // Or system default

// Application Constants
if (!defined('APP_NAME')) define('APP_NAME', 'LK Super');
define('CURRENCY_SYMBOL', 'LKR ');
function format_money($amount) { return CURRENCY_SYMBOL . number_format($amount, 2); }
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('LOW_STOCK_DEFAULT')) define('LOW_STOCK_DEFAULT', 5);

// Determine Base URL dynamically
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir  = dirname($scriptName);
$baseFolder = trim($scriptDir, '/');

// Find root folder mini_pos
$pos = strpos($scriptName, '/mini_pos');
if ($pos !== false) {
    define('BASE_URL', '/mini_pos');
} else {
    define('BASE_URL', rtrim($scriptDir, '/'));
}

// Secure PHP Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0, // Session ends on browser close
        'path'     => '/',
        'domain'   => $cookieParams['domain'],
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Include Database connection
require_once __DIR__ . '/database.php';

// ==============================================================
// Helper Functions
// ==============================================================

/**
 * Escapes output strings safely for HTML rendering (prevents XSS).
 */
function e(?string $string): string
{
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Formats a numeric price into a currency string.
 */
function format_currency(float|int|string|null $amount): string
{
    $num = is_numeric($amount) ? (float)$amount : 0.0;
    return CURRENCY_SYMBOL . number_format($num, 2);
}

/**
 * Formats a date/time string for display.
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string
{
    if (empty($datetime)) return '-';
    $time = strtotime($datetime);
    return $time ? date($format, $time) : $datetime;
}

/**
 * Generates an application URL.
 */
function url(string $path = ''): string
{
    $cleanPath = ltrim($path, '/');
    return BASE_URL . ($cleanPath ? '/' . $cleanPath : '');
}

/**
 * Redirects to a relative or absolute URL and exits immediately.
 */
function redirect(string $path): void
{
    $targetUrl = str_starts_with($path, 'http') ? $path : url($path);
    header("Location: " . $targetUrl);
    exit;
}

// ==============================================================
// CSRF Protection Helpers
// ==============================================================

/**
 * Returns the current session's CSRF token, generating one if not present.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden input field containing the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validates a submitted CSRF token against the session token.
 */
function validate_csrf(?string $token = null): bool
{
    $submittedToken = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (empty($submittedToken) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$submittedToken);
}

// ==============================================================
// Flash Messaging System
// ==============================================================

/**
 * Sets a flash message to display on the next page load.
 * Types: 'success', 'danger', 'warning', 'info'
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type'    => in_array($type, ['success', 'danger', 'warning', 'info']) ? $type : 'info',
        'message' => $message
    ];
}

/**
 * Retrieves and clears the flash message.
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// ==============================================================
// Authentication & Role-Based Access Control (RBAC) Helpers
// ==============================================================

/**
 * Checks if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Returns current logged-in user array or null.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['user_username'] ?? '',
        'full_name' => $_SESSION['user_fullname'] ?? '',
        'role'      => $_SESSION['user_role'] ?? ''
    ];
}

/**
 * Checks if the current user has the 'admin' role.
 */
function is_admin(): bool
{
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'admin');
}

/**
 * Checks if the current user has the 'staff' role.
 */
function is_staff(): bool
{
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'staff');
}

/**
 * Enforces that a user must be logged in. Redirects to login page if unauthenticated.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        redirect('modules/auth/login.php');
    }
}

/**
 * Enforces that a user must have the 'admin' role.
 * Redirects unauthorized staff with a 403 Forbidden message.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        set_flash('danger', 'Access denied: Only Administrators can perform this action.');
        redirect('modules/pos/index.php');
    }
}

/**
 * Enforces that a user is either admin or staff.
 */
function require_staff_or_admin(): void
{
    require_login();
    if (!is_admin() && !is_staff()) {
        http_response_code(403);
        set_flash('danger', 'Unauthorized user role.');
        redirect('modules/auth/login.php');
    }
}
