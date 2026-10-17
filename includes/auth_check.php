<?php
/**
 * Authentication & Authorization Guard Include
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

// Ensure user is signed in
require_login();
