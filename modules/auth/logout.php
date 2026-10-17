<?php
/**
 * Authentication Module: Secure Logout
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

// Unset all session variables
$_SESSION = [];

// Delete session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session data
session_destroy();

// Start clean new session just to flash logout confirmation
session_start();
set_flash('info', 'You have been safely signed out.');

redirect('modules/auth/login.php');
