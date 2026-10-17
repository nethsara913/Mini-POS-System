-- ==============================================================
-- Mini-POS and Inventory Management System
-- Trainee Practical Assessment Database Schema
-- Target Database: `mini-pos-db`
-- Engine: InnoDB, Charset: utf8mb4
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `mini-pos-db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mini-pos-db`;

-- Drop tables in reverse order of foreign key dependency if re-importing
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Table: users
-- Roles: 'admin' (full access to inventory/reports), 'staff' (POS & receipt access only)
-- --------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- Product categories for catalog organization
-- --------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_categories_name` (`name`),
    INDEX `idx_categories_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: products
-- Inventory items with stock tracking and alert thresholds
-- --------------------------------------------------------
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `selling_price` DECIMAL(10,2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `alert_threshold` INT NOT NULL DEFAULT 5,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_products_sku` (`sku`),
    INDEX `idx_products_name` (`name`),
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_stock` (`stock_quantity`),
    INDEX `idx_products_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sales
-- Sales transactions / invoices
-- --------------------------------------------------------
CREATE TABLE `sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `customer_name` VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
    `customer_phone` VARCHAR(25) NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_type` ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `final_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash', 'card', 'online', 'other') NOT NULL DEFAULT 'cash',
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_sales_invoice` (`invoice_no`),
    INDEX `idx_sales_user` (`user_id`),
    INDEX `idx_sales_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sale_items
-- Individual line items per sale invoice
-- --------------------------------------------------------
CREATE TABLE `sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `sku` VARCHAR(50) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_sale_items_sale` (`sale_id`),
    INDEX `idx_sale_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- Safe Sample Data Seeding
-- ==============================================================

-- Demo Users
-- Admin: username 'admin', password 'Admin@123'
-- Staff: username 'cashier1', password 'Staff@123'
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `is_active`) VALUES
(1, 'admin', '$2y$10$KqUq1HbLBp.Kb1rbX61xUu.M9hQm5BxugSqJj6gno81EwlnB3TtjK', 'System Administrator', 'admin', 1),
(2, 'cashier1', '$2y$10$WJYfCUQ5qDfsfa7f9T.HReAJIatOlPKWqxRle0imU9qs6Bo.LC0vu', 'Jane Staff Cashier', 'staff', 1);

-- Demo Categories
INSERT INTO `categories` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'Beverages', 'Hot & cold refreshments, canned drinks, coffee, tea', 1),
(2, 'Bakery & Snacks', 'Fresh bread, pastries, cookies, and chips', 1),
(3, 'Dairy & Refrigerated', 'Milk, yogurt, cheese, butter, and chilled goods', 1),
(4, 'Pantry & Groceries', 'Cooking staples, rice, sugar, flour, canned goods', 1),
(5, 'Personal Care', 'Soaps, hygiene items, shampoos, and toiletries', 1);

-- Demo Products (realistic items with prices, stock levels, and alert thresholds)
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `cost_price`, `selling_price`, `stock_quantity`, `alert_threshold`, `is_active`) VALUES
(1, 1, 'BEV-001', 'Arabica Coffee Beans 250g', 'Premium roasted whole coffee beans', 4.50, 8.50, 24, 5, 1),
(2, 1, 'BEV-002', 'Ceylon Black Tea 100 bags', 'Rich aromatic black tea bags', 2.80, 5.20, 18, 5, 1),
(3, 1, 'BEV-003', 'Sparkling Mineral Water 500ml', 'Chilled carbonated mineral water', 0.60, 1.50, 45, 10, 1),
(4, 2, 'BAK-001', 'Artisan Sourdough Loaf', 'Freshly baked artisan sourdough bread', 1.80, 3.80, 12, 4, 1),
(5, 2, 'BAK-002', 'Chocolate Chip Cookies Pack', 'Crispy handcrafted chocolate cookies', 1.20, 2.75, 20, 5, 1),
(6, 2, 'BAK-003', 'Croissant Butter 2-Pack', 'Flaky French butter croissants (Low Stock Demo)', 1.50, 3.20, 3, 5, 1),
(7, 3, 'DAI-001', 'Whole Fresh Milk 1L', 'Pasteurized farm whole milk', 1.10, 2.10, 30, 8, 1),
(8, 3, 'DAI-002', 'Greek Yogurt Plain 500g', 'High protein natural yogurt', 1.90, 3.60, 15, 5, 1),
(9, 3, 'DAI-003', 'Cheddar Cheese Block 250g', 'Aged sharp cheddar cheese (Low Stock Demo)', 2.50, 4.90, 2, 5, 1),
(10, 4, 'GRO-001', 'Jasmine White Rice 2kg', 'Fragrant premium grade jasmine rice', 3.20, 5.80, 22, 6, 1),
(11, 4, 'GRO-002', 'Extra Virgin Olive Oil 500ml', 'Cold-pressed extra virgin olive oil', 5.00, 9.50, 14, 4, 1),
(12, 4, 'GRO-003', 'Organic Cane Sugar 1kg', 'Unrefined pure granulated cane sugar', 1.20, 2.40, 35, 10, 1),
(13, 5, 'PER-001', 'Gentle Cleansing Soap Bar', 'Natural botanical moisturizing soap', 0.80, 1.95, 28, 8, 1),
(14, 5, 'PER-002', 'Hydrating Shampoo 300ml', 'Sulfate-free nourishing hair wash (Out of Stock Demo)', 3.10, 6.50, 0, 5, 1);

-- Demo Sales (Today and Yesterday transactions for instant dashboard & report visualization)
INSERT INTO `sales` (`id`, `invoice_no`, `user_id`, `customer_name`, `customer_phone`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `tax_amount`, `final_total`, `payment_method`, `paid_amount`, `change_amount`, `notes`, `created_at`) VALUES
(1, 'INV-20261011-0001', 2, 'Alice Johnson', '+1-555-0101', 13.70, 'fixed', 0.00, 0.00, 0.00, 13.70, 'cash', 20.00, 6.30, 'Initial register test transaction', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'INV-20261011-0002', 1, 'Bob Martinez', '+1-555-0102', 20.45, 'percent', 10.00, 2.05, 0.00, 18.40, 'card', 18.40, 0.00, 'Customer card payment', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'INV-20261012-0003', 2, 'Walk-in Customer', NULL, 11.70, 'fixed', 1.00, 1.00, 0.00, 10.70, 'cash', 15.00, 4.30, 'Morning walk-in sale', NOW());

-- Demo Sale Items corresponding to sales
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_name`, `sku`, `unit_price`, `quantity`, `subtotal`) VALUES
(1, 1, 1, 'Arabica Coffee Beans 250g', 'BEV-001', 8.50, 1, 8.50),
(2, 1, 2, 'Ceylon Black Tea 100 bags', 'BEV-002', 5.20, 1, 5.20),
(3, 2, 11, 'Extra Virgin Olive Oil 500ml', 'GRO-002', 9.50, 1, 9.50),
(4, 2, 10, 'Jasmine White Rice 2kg', 'GRO-001', 5.80, 1, 5.80),
(5, 2, 2, 'Ceylon Black Tea 100 bags', 'BEV-002', 5.20, 1, 5.20),
(6, 3, 4, 'Artisan Sourdough Loaf', 'BAK-001', 3.80, 2, 7.60),
(7, 3, 7, 'Whole Fresh Milk 1L', 'DAI-001', 2.10, 1, 2.10),
(8, 3, 3, 'Sparkling Mineral Water 500ml', 'BEV-003', 1.50, 1, 1.50);
