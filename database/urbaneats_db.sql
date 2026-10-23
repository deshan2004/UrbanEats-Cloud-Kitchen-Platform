-- =====================================================================
-- UrbanEats Cloud Kitchen Platform - Complete Integrated Database Schema
-- Component A: Identity, Access Control & Administration
-- Component B: Menu, Catalogue & Inventory Control
-- Component C: Ordering, Payment & Fulfillment Tracking
-- Designed for IT3113 Advanced Web Technologies
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `urbaneats_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `urbaneats_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `coupons`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `dishes`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- COMPONENT A: IDENTITY, ACCESS & GOVERNANCE
-- =====================================================================

-- ---------------------------------------------------------------------
-- Table: roles
-- ---------------------------------------------------------------------
CREATE TABLE `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `role_name`, `description`) VALUES
(1, 'admin', 'System administrator with user governance and reporting access'),
(2, 'kitchen_staff', 'Kitchen staff managing catalogue, dish stock, and order fulfillment'),
(3, 'customer', 'Registered patron placing online delivery and pickup orders');

-- ---------------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_id` (`role_id`),
    KEY `idx_users_is_active` (`is_active`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) 
        REFERENCES `roles` (`id`) 
        ON UPDATE CASCADE 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: login_attempts
-- ---------------------------------------------------------------------
CREATE TABLE `login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_successful` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_username_time` (`username`, `attempted_at`),
    KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: audit_log
-- ---------------------------------------------------------------------
CREATE TABLE `audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user_id` (`user_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_created_at` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON UPDATE CASCADE 
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: password_resets
-- ---------------------------------------------------------------------
CREATE TABLE `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `is_used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resets_token_hash` (`token_hash`),
    KEY `idx_resets_user_id` (`user_id`),
    CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON UPDATE CASCADE 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Component A Users (Password: Password123!)
INSERT INTO `users` (`id`, `role_id`, `full_name`, `username`, `email`, `phone`, `address`, `password_hash`, `is_active`) VALUES
(1, 1, 'Deshan Siriwardhana', 'admin', 'admin@urbaneats.lk', '0771234560', '12/4 Flower Road, Colombo 07', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(2, 1, 'Nimesh Perera', 'nimesh_admin', 'nimesh@urbaneats.lk', '0712345671', '45 Galle Road, Colombo 03', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(3, 2, 'Kasun Bandara', 'chef_kasun', 'kasun@urbaneats.lk', '0779876540', '108 Kandy Road, Kiribathgoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(4, 2, 'Dilan Silva', 'chef_dilan', 'dilan@urbaneats.lk', '0761122334', '56 Station Road, Dehiwala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(5, 2, 'Mahesh Kumara', 'mahesh_prep', 'mahesh@urbaneats.lk', '0783344556', '89 High Level Road, Maharagama', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(6, 3, 'Anuki Fernando', 'anuki99', 'anuki.f@gmail.com', '0775566778', '24 Havelock Road, Colombo 05', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(7, 3, 'Kavindu Jayasuriya', 'kavindu_j', 'kavindu.j@outlook.com', '0714455667', '78 Rajagiriya Road, Rajagiriya', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(8, 3, 'Tharushi Wijesinghe', 'tharushi_w', 'tharushi.w@gmail.com', '0702233445', '15 Bauddhaloka Mawatha, Colombo 04', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(9, 3, 'Sahan Senanayake', 'sahan_s', 'sahan.s@yahoo.com', '0758899001', '90 Nawala Road, Nugegoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(10, 3, 'Pooja Mendis', 'pooja_m', 'pooja.mendis@gmail.com', '0773344112', '31 Stanley Thilakarathne Mawatha, Nugegoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(11, 3, 'Dinuka Gunasekara', 'dinuka_g', 'dinuka.guna@gmail.com', '0729988776', '44 Hill Street, Dehiwala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(12, 3, 'Shenal Cooray', 'shenal_c', 'shenal.c@gmail.com', '0765544332', '102 Ward Place, Colombo 07', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(13, 3, 'Rashmi Alwis', 'rashmi_a', 'rashmi.alwis@gmail.com', '0716677889', '21 Galle Face Terrace, Colombo 03', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(14, 3, 'Nuwan Liyanage', 'nuwan_l', 'nuwan.liyanage@gmail.com', '0778877665', '67 Kottawa Road, Pannipitiya', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(15, 3, 'Hiruni Rathnayake', 'hiruni_r', 'hiruni.rath@gmail.com', '0781122998', '14 Negombo Road, Wattala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(16, 3, 'Isuru Jayawardena', 'isuru_j', 'isuru.jaya@hotmail.com', '0703344990', '88 Peradeniya Road, Kandy', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(17, 3, 'Malsha Fonseka', 'malsha_f', 'malsha.fonseka@gmail.com', '0751239874', '54 Baseline Road, Dematagoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(18, 3, 'Chamath Karunaratne', 'chamath_k', 'chamath.k@gmail.com', '0724455112', '19 Old Kesbewa Road, Delkanda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(19, 3, 'Sanduni Abeykoon', 'sanduni_a', 'sanduni.abey@gmail.com', '0768877443', '37 Dutugemunu Street, Kohuwala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(20, 3, 'Bhanuka Rajapaksha', 'bhanuka_r', 'bhanuka.r@gmail.com', '0774433221', '62 Wackwella Road, Galle', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1),
(21, 3, 'Hansika Jayakody', 'hansika_j', 'hansika.j@gmail.com', '0715566114', '73 Templers Road, Mount Lavinia', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1);

INSERT INTO `audit_log` (`user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'SYSTEM_INIT', 'Initial database schema and default roles/accounts seeded successfully', '127.0.0.1', 'CLI-Migration', '2026-10-15 08:00:00'),
(1, 'USER_LOGIN', 'Administrator authenticated successfully', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-15 08:30:00'),
(1, 'ROLE_ASSIGNED', 'Assigned role Kitchen Staff to user #3 (Kasun Bandara)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-15 09:00:00'),
(3, 'USER_LOGIN', 'Kitchen staff logged into fulfillment station', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-16 07:30:00'),
(1, 'DISH_CREATED', 'Added new dish: Kottu Specialties - Chicken Kottu (LKR 1,450.00)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-16 10:15:00'),
(3, 'STOCK_RESTOCKED', 'Restocked 40 portions of Chicken Kottu after morning preparation', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-16 11:00:00'),
(6, 'USER_REGISTER', 'New customer account created: Anuki Fernando (anuki99)', '112.134.88.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4)', '2026-10-17 12:05:00'),
(6, 'USER_LOGIN', 'Customer logged in from mobile device', '112.134.88.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4)', '2026-10-17 12:06:00'),
(6, 'ORDER_PLACED', 'Order #ORD-2026-1001 placed successfully (Total: LKR 2,860.00)', '112.134.88.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4)', '2026-10-17 12:15:00'),
(3, 'ORDER_STATUS_CHANGED', 'Order #ORD-2026-1001 transitioned Placed -> Preparing', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-17 12:20:00'),
(7, 'USER_REGISTER', 'New customer account created: Kavindu Jayasuriya (kavindu_j)', '112.135.12.98', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-17 13:40:00'),
(7, 'ORDER_PLACED', 'Order #ORD-2026-1002 placed successfully (Total: LKR 2,450.00)', '112.135.12.98', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-17 13:45:00'),
(4, 'STOCK_WASTAGE', 'Logged wastage of 2 portions: Cheese sauce container contaminated', '192.168.1.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-18 10:45:00'),
(1, 'USER_STATUS_UPDATED', 'Deactivated dormant test account #21', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-18 14:00:00'),
(8, 'USER_REGISTER', 'New customer account created: Tharushi Wijesinghe (tharushi_w)', '112.134.90.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5)', '2026-10-19 11:20:00'),
(8, 'ORDER_PLACED', 'Order #ORD-2026-1003 placed successfully (Total: LKR 1,450.00)', '112.134.90.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5)', '2026-10-19 11:30:00'),
(9, 'ORDER_PLACED', 'Order #ORD-2026-1004 placed successfully (Total: LKR 3,040.00)', '124.43.78.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-19 12:10:00'),
(1, 'PASSWORD_RESET_REQUESTED', 'Requested secure password reset link for user #10 (pooja_m)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-20 09:15:00'),
(10, 'PASSWORD_RESET_COMPLETED', 'User #10 successfully reset account password using secure token', '124.43.82.11', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-20 09:22:00'),
(1, 'CATEGORY_UPDATED', 'Updated category display order and metadata for Kottu Specialties', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-21 16:45:00'),
(3, 'STOCK_ADJUSTMENT', 'Weekly physical stock audit reconciliation performed', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-22 17:00:00'),
(1, 'SALES_REPORT_VIEWED', 'Executive monthly sales analytics exported', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-23 09:30:00');

-- =====================================================================
-- COMPONENT B: MENU, CATALOGUE & INVENTORY CONTROL
-- =====================================================================

-- ---------------------------------------------------------------------
-- Table: categories (with dual id/category_id compatibility)
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
    `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id` INT UNSIGNED AS (category_id) VIRTUAL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT '🍲',
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `uq_categories_name` (`name`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_is_active` (`is_active`),
    KEY `idx_categories_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: dishes (with dual dish_id/id compatibility and stock_quantity)
-- ---------------------------------------------------------------------
CREATE TABLE `dishes` (
    `dish_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id` INT UNSIGNED AS (dish_id) VIRTUAL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 20,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `is_vegetarian` TINYINT(1) NOT NULL DEFAULT 0,
    `is_spicy` TINYINT(1) NOT NULL DEFAULT 0,
    `prep_time_minutes` INT NOT NULL DEFAULT 15,
    `calories` INT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`dish_id`),
    UNIQUE KEY `uq_dishes_slug` (`slug`),
    KEY `idx_dishes_category_id` (`category_id`),
    KEY `idx_dishes_price` (`price`),
    KEY `idx_dishes_is_active` (`is_active`),
    KEY `idx_dishes_name` (`name`),
    CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`category_id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: inventory (Required Component B table for stock & reorder levels)
-- ---------------------------------------------------------------------
CREATE TABLE `inventory` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dish_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `reorder_level` INT NOT NULL DEFAULT 10,
    `last_restocked_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inventory_dish_id` (`dish_id`),
    KEY `idx_inventory_quantity` (`quantity`),
    KEY `idx_inventory_reorder_level` (`reorder_level`),
    CONSTRAINT `fk_inventory_dish` FOREIGN KEY (`dish_id`)
        REFERENCES `dishes` (`dish_id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: stock_movements (Required Component B audit log)
-- ---------------------------------------------------------------------
CREATE TABLE `stock_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dish_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `movement_type` ENUM('restock', 'wastage', 'order_deduction', 'adjustment') NOT NULL,
    `quantity_change` INT NOT NULL,
    `previous_quantity` INT NOT NULL,
    `new_quantity` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_stock_movements_dish_id` (`dish_id`),
    KEY `idx_stock_movements_user_id` (`user_id`),
    KEY `idx_stock_movements_type` (`movement_type`),
    KEY `idx_stock_movements_created_at` (`created_at`),
    CONSTRAINT `fk_movements_dish` FOREIGN KEY (`dish_id`)
        REFERENCES `dishes` (`dish_id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_movements_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- COMPONENT C: ORDERING, PAYMENTS & TRACKING
-- =====================================================================

-- ---------------------------------------------------------------------
-- Table: coupons
-- ---------------------------------------------------------------------
CREATE TABLE `coupons` (
    `coupon_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_order_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `expiry_date` DATE DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: orders
-- ---------------------------------------------------------------------
CREATE TABLE `orders` (
    `order_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT UNSIGNED NOT NULL,
    `order_type` ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
    `delivery_address` TEXT DEFAULT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `payment_method` ENUM('cod', 'card', 'bank') NOT NULL DEFAULT 'cod',
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Placed', 'Preparing', 'Ready', 'Out for Delivery', 'Collected', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Placed',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: order_items
-- ---------------------------------------------------------------------
CREATE TABLE `order_items` (
    `item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `dish_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `price_at_purchase` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`dish_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: order_status_history
-- ---------------------------------------------------------------------
CREATE TABLE `order_status_history` (
    `history_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: payments (Required Component C table)
-- ---------------------------------------------------------------------
CREATE TABLE `payments` (
    `payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cod', 'card', 'bank') NOT NULL DEFAULT 'cod',
    `payment_status` ENUM('Pending', 'Completed', 'Failed', 'Refunded') NOT NULL DEFAULT 'Completed',
    `transaction_reference` VARCHAR(100) DEFAULT NULL,
    `paid_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_payments_order_id` (`order_id`),
    KEY `idx_payments_status` (`payment_status`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- SEED DATA: COMPONENT B & C
-- =====================================================================

-- 1. Categories Seed Data
INSERT INTO `categories` (`category_id`, `name`, `slug`, `description`, `icon`, `display_order`, `is_active`) VALUES
(1, 'Kottu Specialties', 'kottu-specialties', 'Authentic Colombo street style chopped roti cooked on hot griddle with spices and meats', '🍲', 1, 1),
(2, 'Rice & Biryani', 'rice-and-biryani', 'Fragrant basmati rice, slow-cooked Dum biryanis, and traditional Lankan banana leaf lamprais', '🍚', 2, 1),
(3, 'Traditional Curries', 'traditional-curries', 'Slow-cooked clay pot curries infused with roasted spices and fresh thick coconut milk', '🍛', 3, 1),
(4, 'Street Bites & Roti', 'street-bites-and-roti', 'Crispy short eats, golden fried cutlets, hot godamba wraps, and village style pol roti', '🥟', 4, 1),
(5, 'Artisan Desserts', 'artisan-desserts', 'Traditional Sri Lankan sweets, rich jaggery watalappan, and tropical puddings', '🍨', 5, 1),
(6, 'Beverages & Refreshers', 'beverages-refreshers', 'Ice cold faludas, spiced Ceylon milk teas, tropical king coconut, and freshly squeezed fruit juices', '🥤', 6, 1);

-- 2. Dishes Seed Data (24 Dishes)
INSERT INTO `dishes` (`dish_id`, `category_id`, `name`, `slug`, `description`, `price`, `stock_quantity`, `image_url`, `is_vegetarian`, `is_spicy`, `prep_time_minutes`, `calories`, `is_active`) VALUES
(1, 1, 'Classic Chicken Kottu', 'classic-chicken-kottu', 'Tender shredded chicken, farm-fresh eggs, leeks, carrots and chopped flatbread tossed in aromatic curry gravy.', 1450.00, 35, 'uploads/chicken_kottu.jpg', 0, 1, 15, 680, 1),
(2, 1, 'Seafood Cheese Kottu', 'seafood-cheese-kottu', 'Signature wok-charred kottu loaded with fresh prawns, cuttlefish, melted mozzarella and rich creamy cheese sauce.', 2150.00, 18, 'uploads/seafood_cheese_kottu.jpg', 0, 1, 18, 890, 1),
(3, 1, 'Spicy Roast Beef Kottu', 'spicy-roast-beef-kottu', 'Slow-roasted dry beef cubes, crispy curry leaves, and green chillies infused with dark roasted Jaffna spices.', 1850.00, 6, 'uploads/beef_kottu.jpg', 0, 1, 15, 750, 1),
(4, 1, 'Vegetable & Egg Palandi Kottu', 'vegetable-egg-palandi-kottu', 'Fresh cabbage, carrots, spring onions, tempered paneer cubes, and eggs sauteed in light spices.', 1150.00, 22, 'uploads/veg_kottu.jpg', 0, 0, 12, 520, 1),

(5, 2, 'Authentic Lankan Lamprais', 'authentic-lankan-lamprais', 'Dutch-Burgher recipe steamed in banana leaf: yellow samba rice, mixed meat frikkadels, seeni sambol, and ash plantain.', 1950.00, 4, 'uploads/lamprais.jpg', 0, 1, 20, 840, 1),
(6, 2, 'Dambulla Chicken Dum Biryani', 'dambulla-chicken-dum-biryani', 'Aromatic long-grain basmati layered with marinated chicken, saffron milk, boiled egg, mint raita, and spicy gravy.', 1750.00, 28, 'uploads/chicken_biryani.jpg', 0, 1, 20, 780, 1),
(7, 2, 'Royal Mutton Biryani', 'royal-mutton-biryani', 'Succulent grass-fed mutton chunks cooked with whole aromatic spices, caramelized onions, boiled egg, and cashew nuts.', 2450.00, 0, 'uploads/mutton_biryani.jpg', 0, 1, 22, 920, 1),
(8, 2, 'Wok-Fried Mixed Seafood Rice', 'wok-fried-mixed-seafood-rice', 'Fragrant jasmine rice wok-tossed on high flame with jumbo prawns, calamari rings, scrambled eggs, and chili paste.', 1850.00, 14, 'uploads/seafood_rice.jpg', 0, 0, 15, 690, 1),

(9, 3, 'Jaffna Fiery Crab Curry', 'jaffna-fiery-crab-curry', 'Lagoon mud crabs simmered in toasted black curry powder, drumstick leaves, tamarind juice, and thick coconut cream.', 2850.00, 0, 'uploads/crab_curry.jpg', 0, 1, 25, 620, 1),
(10, 3, 'Southern Fish Ambul Thiyal', 'southern-fish-ambul-thiyal', 'Traditional sour fish curry prepared with fresh tuna steaks, dried goraka paste, black pepper, and garlic in a clay pot.', 1600.00, 16, 'uploads/ambul_thiyal.jpg', 0, 1, 20, 480, 1),
(11, 3, 'Creamy Cashew & Green Pea Curry', 'creamy-cashew-green-pea-curry', 'Plump whole Sri Lankan raw cashews and sweet garden peas braised gently in velvety golden coconut gravy.', 1550.00, 20, 'uploads/cashew_curry.jpg', 1, 0, 15, 540, 1),
(12, 3, 'Claypot Black Pork Curry', 'claypot-black-pork-curry', 'Tender pork belly pieces marinated in heavily roasted curry paste, fenugreek, and tart vinegar, simmered until dark.', 1900.00, 5, 'uploads/black_pork.jpg', 0, 1, 22, 790, 1),

(13, 4, 'Hot Butter Cuttlefish (HBC)', 'hot-butter-cuttlefish', 'Crispy batter-fried calamari rings tossed in unsalted butter, spring onions, capsicum, and dry chili flakes.', 1850.00, 25, 'uploads/hbc.jpg', 0, 1, 14, 580, 1),
(14, 4, 'Village Pol Roti with Lunu Miris', 'village-pol-roti-lunu-miris', 'Set of 3 freshly toasted coconut flatbreads served with fiery crushed chili-onion sambol and butter.', 750.00, 40, 'uploads/pol_roti.jpg', 1, 1, 10, 420, 1),
(15, 4, 'Crispy Mutton Rolls (3 Pcs)', 'crispy-mutton-rolls', 'Golden breaded pan rolls stuffed with spiced potatoes, leeks, and minced mutton, served with sweet chili dip.', 950.00, 30, 'uploads/mutton_rolls.jpg', 0, 1, 10, 480, 1),
(16, 4, 'Egg Hoppers & Plain Hoppers Platter', 'egg-hoppers-platter', 'Two crispy lace-edged egg hoppers and two plain bowl hoppers served with spicy katta sambol and coconut milk gravy.', 900.00, 8, 'uploads/hoppers.jpg', 1, 0, 15, 380, 1),

(17, 5, 'Kithul Treacle Watalappan', 'kithul-treacle-watalappan', 'Steamed coconut custard pudding sweetened with pure organic Mirigama kithul jaggery, cardamom, and toasted cashews.', 650.00, 24, 'uploads/watalappan.jpg', 1, 0, 5, 360, 1),
(18, 5, 'Caramel Biscuit Pudding', 'caramel-biscuit-pudding', 'Layered butter biscuits soaked in warm espresso and rich chocolate caramel custard, chilled to perfection.', 700.00, 15, 'uploads/biscuit_pudding.jpg', 1, 0, 5, 410, 1),
(19, 5, 'Creamy Buffalo Curd & Treacle', 'creamy-buffalo-curd-treacle', 'Authentic clay pot curd made from fresh southern buffalo milk, drizzled generously with thick amber kithul syrup.', 600.00, 18, 'uploads/curd_treacle.jpg', 1, 0, 5, 290, 1),
(20, 5, 'Tropical Mango & Passion Tart', 'tropical-mango-passion-tart', 'Buttery pastry shell filled with tangy passionfruit curd and topped with fresh Sri Lankan Willard mango slices.', 850.00, 3, 'uploads/mango_tart.jpg', 1, 0, 5, 340, 1),

(21, 6, 'UrbanEats Royal Faluda', 'urbaneats-royal-faluda', 'Chilled sweet rose milk layered with basil seeds (kasakasa), grass jelly strips, vanilla ice cream, and strawberry glaze.', 750.00, 32, 'uploads/faluda.jpg', 1, 0, 5, 320, 1),
(22, 6, 'Ceylon Spiced Cardamom Milk Tea', 'ceylon-spiced-milk-tea', 'Slow-brewed highland Ceylon BOPF tea infused with cracked green cardamom pods, cinnamon bark, and sweetened milk.', 450.00, 50, 'uploads/milk_tea.jpg', 1, 0, 6, 180, 1),
(23, 6, 'Fresh King Coconut Water', 'fresh-king-coconut-water', 'Pure, natural chilled electrolyte drink served in a chilled glass with tender coconut kernel ribbons.', 400.00, 25, 'uploads/thambili.jpg', 1, 0, 3, 75, 1),
(24, 6, 'Lime & Mint Sparkling Soda', 'lime-mint-sparkling-soda', 'Zesty fresh key lime juice crushed with garden mint leaves and bubbly soda water over crushed ice.', 550.00, 30, 'uploads/lime_soda.jpg', 1, 0, 4, 95, 1);

-- 3. Inventory Seed Data
INSERT INTO `inventory` (`dish_id`, `quantity`, `reorder_level`, `last_restocked_at`) VALUES
(1,  35, 12, '2026-10-22 08:30:00'),
(2,  18, 10, '2026-10-22 08:30:00'),
(3,  6,  10, '2026-10-21 16:00:00'),
(4,  22, 10, '2026-10-22 08:30:00'),
(5,  4,  10, '2026-10-21 14:00:00'),
(6,  28, 15, '2026-10-22 09:00:00'),
(7,  0,  8,  '2026-10-20 18:00:00'),
(8,  14, 10, '2026-10-22 09:00:00'),
(9,  0,  6,  '2026-10-21 12:00:00'),
(10, 16, 8,  '2026-10-22 08:30:00'),
(11, 20, 10, '2026-10-22 08:30:00'),
(12, 5,  10, '2026-10-21 15:30:00'),
(13, 25, 12, '2026-10-22 09:30:00'),
(14, 40, 15, '2026-10-22 07:00:00'),
(15, 30, 15, '2026-10-22 07:30:00'),
(16, 8,  12, '2026-10-22 07:30:00'),
(17, 24, 10, '2026-10-22 08:00:00'),
(18, 15, 8,  '2026-10-22 08:00:00'),
(19, 18, 10, '2026-10-22 08:00:00'),
(20, 3,  8,  '2026-10-21 17:00:00'),
(21, 32, 10, '2026-10-22 08:00:00'),
(22, 50, 20, '2026-10-22 07:00:00'),
(23, 25, 10, '2026-10-22 07:00:00'),
(24, 30, 12, '2026-10-22 08:00:00');

-- 4. Stock Movements Seed Data
INSERT INTO `stock_movements` (`dish_id`, `user_id`, `movement_type`, `quantity_change`, `previous_quantity`, `new_quantity`, `reason`, `created_at`) VALUES
(1,  3, 'restock',         40,  0,  40, 'Morning kitchen prep: batch of chopped chicken kottu portions', '2026-10-22 08:00:00'),
(1,  3, 'order_deduction', -5,  40, 35, 'Dispatched catering lunch order #1002', '2026-10-22 12:15:00'),
(2,  4, 'restock',         20,  0,  20, 'Prepared fresh seafood portions and shredded godamba', '2026-10-22 08:15:00'),
(2,  4, 'wastage',         -2,  20, 18, 'Cheese sauce container dropped and contaminated in prep line', '2026-10-22 10:45:00'),
(3,  3, 'adjustment',      -4,  10, 6,  'End of lunch inventory count verification', '2026-10-21 16:00:00'),
(4,  4, 'restock',         25,  0,  25, 'Daily fresh vegetable and seasoned paneer prep', '2026-10-22 08:30:00'),
(4,  4, 'order_deduction', -3,  25, 22, 'Order fulfillment #1004', '2026-10-22 12:40:00'),
(5,  3, 'restock',         15,  0,  15, 'Wrapped authentic banana leaf lamprais packets', '2026-10-21 10:00:00'),
(5,  3, 'order_deduction', -11, 15, 4,  'Heavy dinner rush orders', '2026-10-21 21:30:00'),
(6,  3, 'restock',         30,  0,  30, 'Morning Biryani Handi cooking finished (Chicken Dum)', '2026-10-22 09:00:00'),
(6,  3, 'order_deduction', -2,  30, 28, 'Lunch delivery pickup #1008', '2026-10-22 12:30:00'),
(7,  3, 'order_deduction', -8,  8,  0,  'Sold out during yesterday dinner shift - pending fresh meat shipment', '2026-10-20 22:00:00'),
(8,  4, 'restock',         20,  0,  20, 'Prepped wok ingredients with fresh calamari & prawns', '2026-10-22 09:00:00'),
(8,  4, 'order_deduction', -6,  20, 14, 'Customer orders lunch rush #1012', '2026-10-22 13:00:00'),
(9,  4, 'wastage',         -3,  3,  0,  'Fresh mud crabs sold out and remaining stock spoiled', '2026-10-21 21:00:00'),
(10, 3, 'restock',         20,  0,  20, 'Claypot fish ambul thiyal slow cooked and cooled', '2026-10-22 08:30:00'),
(10, 3, 'order_deduction', -4,  20, 16, 'Dispatched customer orders #1015', '2026-10-22 12:50:00'),
(11, 4, 'restock',         25,  0,  25, 'Boiled raw cashews and prepped yellow coconut curry', '2026-10-22 08:30:00'),
(11, 4, 'order_deduction', -5,  25, 20, 'Vegetarian orders batch #1018', '2026-10-22 13:10:00'),
(12, 3, 'order_deduction', -10, 15, 5,  'Pork curry lunch orders - near reorder point', '2026-10-21 15:30:00'),
(13, 4, 'restock',         30,  0,  30, 'Sliced calamari rings battered and prepped for fry station', '2026-10-22 09:30:00'),
(13, 4, 'order_deduction', -5,  30, 25, 'Appetizer orders dispatched', '2026-10-22 13:15:00'),
(14, 3, 'restock',         50,  0,  50, 'Griddled 50 village coconut rotis for breakfast & lunch', '2026-10-22 07:00:00'),
(14, 3, 'order_deduction', -10, 50, 40, 'Breakfast orders completed', '2026-10-22 10:00:00'),
(15, 4, 'restock',         35,  0,  35, 'Rolled and crumbed 35 spicy mutton pan rolls', '2026-10-22 07:30:00'),
(15, 4, 'wastage',         -5,  35, 30, 'Damaged during flash frying', '2026-10-22 09:00:00'),
(16, 3, 'order_deduction', -12, 20, 8,  'Hoppers station busy morning orders', '2026-10-22 10:30:00'),
(17, 4, 'restock',         25,  0,  25, 'Chilled fresh steamed kithul jaggery watalappan bowls', '2026-10-22 08:00:00'),
(18, 4, 'restock',         16,  0,  16, 'Chilled caramel biscuit pudding portions in dessert fridge', '2026-10-22 08:00:00'),
(19, 3, 'restock',         20,  0,  20, 'Received fresh southern buffalo curd pots', '2026-10-22 08:00:00'),
(20, 4, 'wastage',         -2,  5,  3,  'Fruit glaze melted due to dessert chiller door left ajar', '2026-10-21 17:00:00'),
(21, 4, 'restock',         35,  0,  35, 'Prepped faluda syrup, basil seeds, and chilled milk base', '2026-10-22 08:00:00'),
(22, 3, 'restock',         50,  0,  50, 'Brewed large thermos of spiced Ceylon cardamom tea', '2026-10-22 07:00:00');

-- 5. Component C Coupons Seed Data
INSERT INTO `coupons` (`coupon_id`, `code`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `expiry_date`, `is_active`) VALUES
(1, 'UE10', 'percentage', 10.00, 1000.00, 500.00, '2027-12-31', 1),
(2, 'WELCOME500', 'fixed', 500.00, 2000.00, 500.00, '2027-12-31', 1),
(3, 'KOTTU15', 'percentage', 15.00, 1200.00, 600.00, '2027-12-31', 1),
(4, 'LUNCH200', 'fixed', 200.00, 1000.00, 200.00, '2027-12-31', 1),
(5, 'FEAST20', 'percentage', 20.00, 3000.00, 1000.00, '2027-12-31', 1),
(6, 'FREEDEL', 'fixed', 250.00, 1500.00, 250.00, '2027-12-31', 1);

-- 6. Component C Orders Seed Data (20 Realistic Orders)
INSERT INTO `orders` (`order_id`, `order_code`, `user_id`, `order_type`, `delivery_address`, `phone`, `payment_method`, `subtotal`, `delivery_fee`, `discount_amount`, `total_amount`, `status`, `created_at`) VALUES
(1, 'ORD-2026-1001', 6, 'delivery', '24 Havelock Road, Colombo 05', '0775566778', 'cod', 2900.00, 250.00, 290.00, 2860.00, 'Placed', '2026-10-23 18:40:00'),
(2, 'ORD-2026-1002', 7, 'delivery', '78 Rajagiriya Road, Rajagiriya', '0714455667', 'card', 2200.00, 250.00, 0.00, 2450.00, 'Preparing', '2026-10-23 18:15:00'),
(3, 'ORD-2026-1003', 8, 'pickup', 'Pickup counter', '0702233445', 'cod', 1450.00, 0.00, 0.00, 1450.00, 'Ready', '2026-10-23 17:50:00'),
(4, 'ORD-2026-1004', 9, 'delivery', '90 Nawala Road, Nugegoda', '0758899001', 'bank', 3100.00, 250.00, 310.00, 3040.00, 'Completed', '2026-10-23 16:30:00'),
(5, 'ORD-2026-1005', 10, 'delivery', '31 Stanley Thilakarathne Mw, Nugegoda', '0773344112', 'card', 2650.00, 250.00, 265.00, 2635.00, 'Out for Delivery', '2026-10-23 17:15:00'),
(6, 'ORD-2026-1006', 11, 'pickup', 'Pickup counter', '0729988776', 'card', 1850.00, 0.00, 0.00, 1850.00, 'Collected', '2026-10-23 15:45:00'),
(7, 'ORD-2026-1007', 12, 'delivery', '102 Ward Place, Colombo 07', '0765544332', 'cod', 4200.00, 250.00, 500.00, 3950.00, 'Completed', '2026-10-22 20:10:00'),
(8, 'ORD-2026-1008', 13, 'delivery', '21 Galle Face Terrace, Colombo 03', '0716677889', 'card', 1650.00, 250.00, 0.00, 1900.00, 'Completed', '2026-10-22 19:30:00'),
(9, 'ORD-2026-1009', 14, 'delivery', '67 Kottawa Road, Pannipitiya', '0778877665', 'bank', 3300.00, 250.00, 330.00, 3220.00, 'Completed', '2026-10-22 13:20:00'),
(10, 'ORD-2026-1010', 15, 'pickup', 'Pickup counter', '0781122998', 'cod', 950.00, 0.00, 0.00, 950.00, 'Completed', '2026-10-22 12:45:00'),
(11, 'ORD-2026-1011', 16, 'delivery', '88 Peradeniya Road, Kandy', '0703344990', 'card', 2700.00, 250.00, 200.00, 2750.00, 'Cancelled', '2026-10-21 21:00:00'),
(12, 'ORD-2026-1012', 17, 'delivery', '54 Baseline Road, Dematagoda', '0751239874', 'cod', 3800.00, 250.00, 380.00, 3670.00, 'Completed', '2026-10-21 19:40:00'),
(13, 'ORD-2026-1013', 18, 'delivery', '19 Old Kesbewa Road, Delkanda', '0724455112', 'bank', 2150.00, 250.00, 0.00, 2400.00, 'Completed', '2026-10-21 13:10:00'),
(14, 'ORD-2026-1014', 19, 'pickup', 'Pickup counter', '0768877443', 'cod', 1600.00, 0.00, 0.00, 1600.00, 'Completed', '2026-10-20 20:30:00'),
(15, 'ORD-2026-1015', 20, 'delivery', '62 Wackwella Road, Galle', '0774433221', 'card', 4500.00, 250.00, 500.00, 4250.00, 'Completed', '2026-10-20 18:20:00'),
(16, 'ORD-2026-1016', 6, 'delivery', '24 Havelock Road, Colombo 05', '0775566778', 'card', 2900.00, 250.00, 250.00, 2900.00, 'Completed', '2026-10-19 19:15:00'),
(17, 'ORD-2026-1017', 7, 'delivery', '78 Rajagiriya Road, Rajagiriya', '0714455667', 'cod', 1450.00, 250.00, 0.00, 1700.00, 'Completed', '2026-10-19 13:00:00'),
(18, 'ORD-2026-1018', 8, 'pickup', 'Pickup counter', '0702233445', 'card', 2250.00, 0.00, 200.00, 2050.00, 'Completed', '2026-10-18 20:00:00'),
(19, 'ORD-2026-1019', 9, 'delivery', '90 Nawala Road, Nugegoda', '0758899001', 'bank', 3700.00, 250.00, 370.00, 3580.00, 'Completed', '2026-10-18 12:40:00'),
(20, 'ORD-2026-1020', 10, 'delivery', '31 Stanley Thilakarathne Mw, Nugegoda', '0773344112', 'cod', 2100.00, 250.00, 0.00, 2350.00, 'Completed', '2026-10-17 19:10:00');

-- 7. Component C Order Items Seed Data (33 Items)
INSERT INTO `order_items` (`item_id`, `order_id`, `dish_id`, `quantity`, `price_at_purchase`) VALUES
(1, 1, 1, 2, 1450.00),
(2, 2, 2, 1, 1650.00),
(3, 2, 21, 1, 550.00),
(4, 3, 1, 1, 1450.00),
(5, 4, 8, 2, 1550.00),
(6, 5, 6, 1, 1850.00),
(7, 5, 17, 2, 400.00),
(8, 6, 2, 1, 1850.00),
(9, 7, 6, 2, 1850.00),
(10, 7, 21, 1, 500.00),
(11, 8, 1, 1, 1450.00),
(12, 8, 22, 1, 200.00),
(13, 9, 8, 2, 1650.00),
(14, 10, 15, 2, 350.00),
(15, 10, 22, 1, 250.00),
(16, 11, 6, 1, 1850.00),
(17, 11, 13, 1, 850.00),
(18, 12, 8, 2, 1550.00),
(19, 12, 17, 2, 350.00),
(20, 13, 1, 1, 1450.00),
(21, 13, 18, 2, 350.00),
(22, 14, 14, 4, 250.00),
(23, 14, 21, 1, 600.00),
(24, 15, 6, 2, 1850.00),
(25, 15, 13, 1, 800.00),
(26, 16, 1, 2, 1450.00),
(27, 17, 1, 1, 1450.00),
(28, 18, 2, 1, 1650.00),
(29, 18, 21, 1, 600.00),
(30, 19, 8, 2, 1850.00),
(31, 20, 1, 1, 1450.00),
(32, 20, 17, 1, 400.00),
(33, 20, 22, 1, 250.00);

-- 8. Component C Payments Seed Data (20 Payment Transactions)
INSERT INTO `payments` (`payment_id`, `order_id`, `amount`, `payment_method`, `payment_status`, `transaction_reference`, `paid_at`, `created_at`) VALUES
(1, 1, 2860.00, 'cod', 'Pending', 'TXN-20261001-0089', '2026-10-23 18:40:00', '2026-10-23 18:40:00'),
(2, 2, 2450.00, 'card', 'Completed', 'TXN-20261002-0112', '2026-10-23 18:15:00', '2026-10-23 18:15:00'),
(3, 3, 1450.00, 'cod', 'Pending', 'TXN-20261003-019B', '2026-10-23 17:50:00', '2026-10-23 17:50:00'),
(4, 4, 3040.00, 'bank', 'Completed', 'TXN-20261004-0224', '2026-10-23 16:30:00', '2026-10-23 16:30:00'),
(5, 5, 2635.00, 'card', 'Completed', 'TXN-20261005-02AD', '2026-10-23 17:15:00', '2026-10-23 17:15:00'),
(6, 6, 1850.00, 'card', 'Completed', 'TXN-20261006-0336', '2026-10-23 15:45:00', '2026-10-23 15:45:00'),
(7, 7, 3950.00, 'cod', 'Completed', 'TXN-20261007-03BF', '2026-10-22 20:10:00', '2026-10-22 20:10:00'),
(8, 8, 1900.00, 'card', 'Completed', 'TXN-20261008-0448', '2026-10-22 19:30:00', '2026-10-22 19:30:00'),
(9, 9, 3220.00, 'bank', 'Completed', 'TXN-20261009-04D1', '2026-10-22 13:20:00', '2026-10-22 13:20:00'),
(10, 10, 950.00, 'cod', 'Completed', 'TXN-20261010-055A', '2026-10-22 12:45:00', '2026-10-22 12:45:00'),
(11, 11, 2750.00, 'card', 'Failed', 'TXN-20261011-05E3', '2026-10-21 21:00:00', '2026-10-21 21:00:00'),
(12, 12, 3670.00, 'cod', 'Completed', 'TXN-20261012-066C', '2026-10-21 19:40:00', '2026-10-21 19:40:00'),
(13, 13, 2400.00, 'bank', 'Completed', 'TXN-20261013-06F5', '2026-10-21 13:10:00', '2026-10-21 13:10:00'),
(14, 14, 1600.00, 'cod', 'Completed', 'TXN-20261014-077E', '2026-10-20 20:30:00', '2026-10-20 20:30:00'),
(15, 15, 4250.00, 'card', 'Completed', 'TXN-20261015-0807', '2026-10-20 18:20:00', '2026-10-20 18:20:00'),
(16, 16, 2900.00, 'card', 'Completed', 'TXN-20261016-0890', '2026-10-19 19:15:00', '2026-10-19 19:15:00'),
(17, 17, 1700.00, 'cod', 'Completed', 'TXN-20261017-0919', '2026-10-19 13:00:00', '2026-10-19 13:00:00'),
(18, 18, 2050.00, 'card', 'Completed', 'TXN-20261018-09A2', '2026-10-18 20:00:00', '2026-10-18 20:00:00'),
(19, 19, 3580.00, 'bank', 'Completed', 'TXN-20261019-0A2B', '2026-10-18 12:40:00', '2026-10-18 12:40:00'),
(20, 20, 2350.00, 'cod', 'Completed', 'TXN-20261020-0AB4', '2026-10-17 19:10:00', '2026-10-17 19:10:00');

-- 9. Component C Order Status Progression History (47 Lifecycle Transitions)
INSERT INTO `order_status_history` (`history_id`, `order_id`, `status`, `changed_by`, `changed_at`) VALUES
(1, 1, 'Placed', 6, '2026-10-23 18:40:00'),
(2, 2, 'Placed', 7, '2026-10-23 18:15:00'),
(3, 2, 'Preparing', 3, '2026-10-23 18:25:00'),
(4, 3, 'Placed', 8, '2026-10-23 17:50:00'),
(5, 3, 'Preparing', 3, '2026-10-23 18:00:00'),
(6, 3, 'Ready', 3, '2026-10-23 18:20:00'),
(7, 4, 'Placed', 9, '2026-10-23 16:30:00'),
(8, 4, 'Preparing', 4, '2026-10-23 16:40:00'),
(9, 4, 'Ready', 4, '2026-10-23 17:05:00'),
(10, 4, 'Out for Delivery', 4, '2026-10-23 17:15:00'),
(11, 4, 'Completed', 1, '2026-10-23 17:45:00'),
(12, 5, 'Placed', 10, '2026-10-23 17:15:00'),
(13, 5, 'Preparing', 3, '2026-10-23 17:25:00'),
(14, 5, 'Ready', 3, '2026-10-23 17:45:00'),
(15, 5, 'Out for Delivery', 4, '2026-10-23 18:00:00'),
(16, 6, 'Placed', 11, '2026-10-23 15:45:00'),
(17, 6, 'Preparing', 3, '2026-10-23 15:55:00'),
(18, 6, 'Ready', 3, '2026-10-23 16:20:00'),
(19, 6, 'Collected', 1, '2026-10-23 16:35:00'),
(20, 7, 'Placed', 12, '2026-10-22 20:10:00'),
(21, 7, 'Completed', 1, '2026-10-22 21:05:00'),
(22, 8, 'Placed', 13, '2026-10-22 19:30:00'),
(23, 8, 'Completed', 1, '2026-10-22 20:20:00'),
(24, 9, 'Placed', 14, '2026-10-22 13:20:00'),
(25, 9, 'Completed', 1, '2026-10-22 14:15:00'),
(26, 10, 'Placed', 15, '2026-10-22 12:45:00'),
(27, 10, 'Completed', 1, '2026-10-22 13:15:00'),
(28, 11, 'Placed', 16, '2026-10-21 21:00:00'),
(29, 11, 'Cancelled', 16, '2026-10-21 21:10:00'),
(30, 12, 'Placed', 17, '2026-10-21 19:40:00'),
(31, 12, 'Completed', 1, '2026-10-21 20:30:00'),
(32, 13, 'Placed', 18, '2026-10-21 13:10:00'),
(33, 13, 'Completed', 1, '2026-10-21 14:00:00'),
(34, 14, 'Placed', 19, '2026-10-20 20:30:00'),
(35, 14, 'Completed', 1, '2026-10-20 21:15:00'),
(36, 15, 'Placed', 20, '2026-10-20 18:20:00'),
(37, 15, 'Completed', 1, '2026-10-20 19:15:00'),
(38, 16, 'Placed', 6, '2026-10-19 19:15:00'),
(39, 16, 'Completed', 1, '2026-10-19 20:10:00'),
(40, 17, 'Placed', 7, '2026-10-19 13:00:00'),
(41, 17, 'Completed', 1, '2026-10-19 13:50:00'),
(42, 18, 'Placed', 8, '2026-10-18 20:00:00'),
(43, 18, 'Completed', 1, '2026-10-18 20:45:00'),
(44, 19, 'Placed', 9, '2026-10-18 12:40:00'),
(45, 19, 'Completed', 1, '2026-10-18 13:30:00'),
(46, 20, 'Placed', 10, '2026-10-17 19:10:00'),
(47, 20, 'Completed', 1, '2026-10-17 20:00:00');
