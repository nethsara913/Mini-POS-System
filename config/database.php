<?php
/**
 * Mini-POS and Inventory Management System
 * Database Connection using PDO
 */

declare(strict_types=1);

// Database connection parameters
// Note: Matches user's phpMyAdmin database `mini-pos-db`
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'mini-pos-db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO instance with safe error handling and transaction support.
 *
 * @return PDO
 * @throws RuntimeException If database connection fails
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check fallback in case user renamed database to mini_pos_db
            try {
                $fallbackDsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    'mini_pos_db',
                    DB_CHARSET
                );
                $pdo = new PDO($fallbackDsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $fallbackException) {
                // Log and show user-friendly message
                error_log("Database Connection Error: " . $e->getMessage());
                throw new RuntimeException(
                    "Database connection failed: Could not connect to database '" . DB_NAME . "'. " .
                    "Please ensure XAMPP MySQL is running and import 'database.sql' in phpMyAdmin. " .
                    "Details: " . $e->getMessage()
                );
            }
        }
    }

    return $pdo;
}
