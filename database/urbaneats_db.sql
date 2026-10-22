-- =====================================================================
-- UrbanEats Cloud Kitchen Platform - Unified Database Schema & Seed Data
-- Component A: Identity, Access Control & Administration (Student 1)
-- Component B: Menu, Catalogue & Inventory Control (Student 2)
-- Component C: Ordering, Payment, Tracking & Analytics (Student 3)
-- Designed for IT3113 Advanced Web Technologies
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `urbaneats_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `urbaneats_db`;

SET FOREIGN_KEY_CHECKS = 0;
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
-- COMPONENT A TABLES (Identity, Access & Administration)
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

-- ---------------------------------------------------------------------
-- Seed Data: Component A Users (Default Password: Password123!)
-- ---------------------------------------------------------------------
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

INSERT INTO `audit_log` (`user_id`, `action`, `details`, `ip_address`, `user_agent`) VALUES
(1, 'SYSTEM_INIT', 'Initial database schema and default roles/accounts seeded successfully', '127.0.0.1', 'CLI-Migration');

-- =====================================================================
-- COMPONENT B TABLES (Menu, Catalogue & Inventory Control - Student 2)
-- =====================================================================

-- ---------------------------------------------------------------------
-- Table: categories
-- Organizes dishes into customer-facing departments with icons and display ordering
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT '🍲',
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_is_active` (`is_active`),
    KEY `idx_categories_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: dishes
-- Core food item records with soft-delete flag (is_active) to preserve past orders
-- Indexes optimized for search, category filtering, and price sorting
-- ---------------------------------------------------------------------
CREATE TABLE `dishes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `is_vegetarian` TINYINT(1) NOT NULL DEFAULT 0,
    `is_spicy` TINYINT(1) NOT NULL DEFAULT 0,
    `prep_time_minutes` INT NOT NULL DEFAULT 15,
    `calories` INT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dishes_slug` (`slug`),
    KEY `idx_dishes_category_id` (`category_id`),
    KEY `idx_dishes_price` (`price`),
    KEY `idx_dishes_is_active` (`is_active`),
    KEY `idx_dishes_name` (`name`),
    CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: inventory
-- Real-time stock levels with reorder thresholds for low-stock alerts
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
        REFERENCES `dishes` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: stock_movements
-- Immutable audit log for all stock changes (restock, wastage, orders, manual corrections)
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
        REFERENCES `dishes` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_movements_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- COMPONENT C TABLES (Ordering, Payment, Tracking & Workflow - Student 3)
-- =====================================================================

-- ---------------------------------------------------------------------
-- Table: coupons (Component C)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
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
-- Table: orders (Component C)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
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
-- Table: order_items (Component C)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `dish_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `price_at_purchase` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: order_status_history (Component C)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `history_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA: COMPONENT B (Categories, Dishes, Inventory, Movements)
-- Minimum 20 realistic rows in each main table
-- =====================================================================

-- 1. Categories Seed Data
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `display_order`, `is_active`) VALUES
(1, 'Kottu Specialties', 'kottu-specialties', 'Authentic Colombo street style chopped roti cooked on hot griddle with spices and meats', '🍲', 1, 1),
(2, 'Rice & Biryani', 'rice-and-biryani', 'Fragrant basmati rice, slow-cooked Dum biryanis, and traditional Lankan banana leaf lamprais', '🍚', 2, 1),
(3, 'Traditional Curries', 'traditional-curries', 'Slow-cooked clay pot curries infused with roasted spices and fresh thick coconut milk', '🍛', 3, 1),
(4, 'Street Bites & Roti', 'street-bites-and-roti', 'Crispy short eats, golden fried cutlets, hot godamba wraps, and village style pol roti', '🥟', 4, 1),
(5, 'Artisan Desserts', 'artisan-desserts', 'Traditional Sri Lankan sweets, rich jaggery watalappan, and tropical puddings', '🍨', 5, 1),
(6, 'Beverages & Refreshers', 'beverages-refreshers', 'Ice cold faludas, spiced Ceylon milk teas, tropical king coconut, and freshly squeezed fruit juices', '🥤', 6, 1);

-- 2. Dishes Seed Data (24 Realistic Sri Lankan Cloud Kitchen Dishes)
INSERT INTO `dishes` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `image_url`, `is_vegetarian`, `is_spicy`, `prep_time_minutes`, `calories`, `is_active`) VALUES
(1, 1, 'Classic Chicken Kottu', 'classic-chicken-kottu', 'Tender shredded chicken, farm-fresh eggs, leeks, carrots and chopped flatbread tossed in aromatic curry gravy.', 1450.00, 'uploads/chicken_kottu.jpg', 0, 1, 15, 680, 1),
(2, 1, 'Seafood Cheese Kottu', 'seafood-cheese-kottu', 'Signature wok-charred kottu loaded with fresh prawns, cuttlefish, melted mozzarella and rich creamy cheese sauce.', 2150.00, 'uploads/seafood_cheese_kottu.jpg', 0, 1, 18, 890, 1),
(3, 1, 'Spicy Roast Beef Kottu', 'spicy-roast-beef-kottu', 'Slow-roasted dry beef cubes, crispy curry leaves, and green chillies infused with dark roasted Jaffna spices.', 1850.00, 'uploads/beef_kottu.jpg', 0, 1, 15, 750, 1),
(4, 1, 'Vegetable & Egg Palandi Kottu', 'vegetable-egg-palandi-kottu', 'Fresh cabbage, carrots, spring onions, tempered paneer cubes, and eggs sauteed in light spices.', 1150.00, 'uploads/veg_kottu.jpg', 0, 0, 12, 520, 1),

(5, 2, 'Authentic Lankan Lamprais', 'authentic-lankan-lamprais', 'Dutch-Burgher recipe steamed in banana leaf: yellow samba rice, mixed meat frikkadels, seeni sambol, and ash plantain.', 1950.00, 'uploads/lamprais.jpg', 0, 1, 20, 840, 1),
(6, 2, 'Dambulla Chicken Dum Biryani', 'dambulla-chicken-dum-biryani', 'Aromatic long-grain basmati layered with marinated chicken, saffron milk, boiled egg, mint raita, and spicy gravy.', 1750.00, 'uploads/chicken_biryani.jpg', 0, 1, 20, 780, 1),
(7, 2, 'Royal Mutton Biryani', 'royal-mutton-biryani', 'Succulent grass-fed mutton chunks cooked with whole aromatic spices, caramelized onions, boiled egg, and cashew nuts.', 2450.00, 'uploads/mutton_biryani.jpg', 0, 1, 22, 920, 1),
(8, 2, 'Wok-Fried Mixed Seafood Rice', 'wok-fried-mixed-seafood-rice', 'Fragrant jasmine rice wok-tossed on high flame with jumbo prawns, calamari rings, scrambled eggs, and chili paste.', 1850.00, 'uploads/seafood_rice.jpg', 0, 0, 15, 690, 1),

(9, 3, 'Jaffna Fiery Crab Curry', 'jaffna-fiery-crab-curry', 'Lagoon mud crabs simmered in toasted black curry powder, drumstick leaves, tamarind juice, and thick coconut cream.', 2850.00, 'uploads/crab_curry.jpg', 0, 1, 25, 620, 1),
(10, 3, 'Southern Fish Ambul Thiyal', 'southern-fish-ambul-thiyal', 'Traditional sour fish curry prepared with fresh tuna steaks, dried goraka paste, black pepper, and garlic in a clay pot.', 1600.00, 'uploads/ambul_thiyal.jpg', 0, 1, 20, 480, 1),
(11, 3, 'Creamy Cashew & Green Pea Curry', 'creamy-cashew-green-pea-curry', 'Plump whole Sri Lankan raw cashews and sweet garden peas braised gently in velvety golden coconut gravy.', 1550.00, 'uploads/cashew_curry.jpg', 1, 0, 15, 540, 1),
(12, 3, 'Claypot Black Pork Curry', 'claypot-black-pork-curry', 'Tender pork belly pieces marinated in heavily roasted curry paste, fenugreek, and tart vinegar, simmered until dark.', 1900.00, 'uploads/black_pork.jpg', 0, 1, 22, 790, 1),

(13, 4, 'Hot Butter Cuttlefish (HBC)', 'hot-butter-cuttlefish', 'Crispy batter-fried calamari rings tossed in unsalted butter, spring onions, capsicum, and dry chili flakes.', 1850.00, 'uploads/hbc.jpg', 0, 1, 14, 580, 1),
(14, 4, 'Village Pol Roti with Lunu Miris', 'village-pol-roti-lunu-miris', 'Set of 3 freshly toasted coconut flatbreads served with fiery crushed chili-onion sambol and butter.', 750.00, 'uploads/pol_roti.jpg', 1, 1, 10, 420, 1),
(15, 4, 'Crispy Mutton Rolls (3 Pcs)', 'crispy-mutton-rolls', 'Golden breaded pan rolls stuffed with spiced potatoes, leeks, and minced mutton, served with sweet chili dip.', 950.00, 'uploads/mutton_rolls.jpg', 0, 1, 10, 480, 1),
(16, 4, 'Egg Hoppers & Plain Hoppers Platter', 'egg-hoppers-platter', 'Two crispy lace-edged egg hoppers and two plain bowl hoppers served with spicy katta sambol and coconut milk gravy.', 900.00, 'uploads/hoppers.jpg', 1, 0, 15, 380, 1),

(17, 5, 'Kithul Treacle Watalappan', 'kithul-treacle-watalappan', 'Steamed coconut custard pudding sweetened with pure organic Mirigama kithul jaggery, cardamom, and toasted cashews.', 650.00, 'uploads/watalappan.jpg', 1, 0, 5, 360, 1),
(18, 5, 'Caramel Biscuit Pudding', 'caramel-biscuit-pudding', 'Layered butter biscuits soaked in warm espresso and rich chocolate caramel custard, chilled to perfection.', 700.00, 'uploads/biscuit_pudding.jpg', 1, 0, 5, 410, 1),
(19, 5, 'Creamy Buffalo Curd & Treacle', 'creamy-buffalo-curd-treacle', 'Authentic clay pot curd made from fresh southern buffalo milk, drizzled generously with thick amber kithul syrup.', 600.00, 'uploads/curd_treacle.jpg', 1, 0, 5, 290, 1),
(20, 5, 'Tropical Mango & Passion Tart', 'tropical-mango-passion-tart', 'Buttery pastry shell filled with tangy passionfruit curd and topped with fresh Sri Lankan Willard mango slices.', 850.00, 'uploads/mango_tart.jpg', 1, 0, 5, 340, 1),

(21, 6, 'UrbanEats Royal Faluda', 'urbaneats-royal-faluda', 'Chilled sweet rose milk layered with basil seeds (kasakasa), grass jelly strips, vanilla ice cream, and strawberry glaze.', 750.00, 'uploads/faluda.jpg', 1, 0, 5, 320, 1),
(22, 6, 'Ceylon Spiced Cardamom Milk Tea', 'ceylon-spiced-milk-tea', 'Slow-brewed highland Ceylon BOPF tea infused with cracked green cardamom pods, cinnamon bark, and sweetened milk.', 450.00, 'uploads/milk_tea.jpg', 1, 0, 6, 180, 1),
(23, 6, 'Fresh King Coconut Water', 'fresh-king-coconut-water', 'Pure, natural chilled electrolyte drink served in a chilled glass with tender coconut kernel ribbons.', 400.00, 'uploads/thambili.jpg', 1, 0, 3, 75, 1),
(24, 6, 'Lime & Mint Sparkling Soda', 'lime-mint-sparkling-soda', 'Zesty fresh key lime juice crushed with garden mint leaves and bubbly soda water over crushed ice.', 550.00, 'uploads/lime_soda.jpg', 1, 0, 4, 95, 1);

-- 3. Inventory Seed Data (24 Dishes with realistic varied stock levels: healthy stock, low-stock <= 10, and out-of-stock = 0)
INSERT INTO `inventory` (`dish_id`, `quantity`, `reorder_level`, `last_restocked_at`) VALUES
(1,  35, 12, '2026-10-22 08:30:00'), -- Classic Chicken Kottu (Healthy)
(2,  18, 10, '2026-10-22 08:30:00'), -- Seafood Cheese Kottu (Healthy)
(3,  6,  10, '2026-10-21 16:00:00'), -- Spicy Roast Beef Kottu (LOW STOCK ALERT: 6 <= 10)
(4,  22, 10, '2026-10-22 08:30:00'), -- Vegetable & Egg Palandi Kottu (Healthy)
(5,  4,  10, '2026-10-21 14:00:00'), -- Authentic Lankan Lamprais (LOW STOCK ALERT: 4 <= 10)
(6,  28, 15, '2026-10-22 09:00:00'), -- Dambulla Chicken Dum Biryani (Healthy)
(7,  0,  8,  '2026-10-20 18:00:00'), -- Royal Mutton Biryani (OUT OF STOCK: 0)
(8,  14, 10, '2026-10-22 09:00:00'), -- Wok-Fried Mixed Seafood Rice (Healthy)
(9,  0,  6,  '2026-10-21 12:00:00'), -- Jaffna Fiery Crab Curry (OUT OF STOCK: 0)
(10, 16, 8,  '2026-10-22 08:30:00'), -- Southern Fish Ambul Thiyal (Healthy)
(11, 20, 10, '2026-10-22 08:30:00'), -- Creamy Cashew & Green Pea Curry (Healthy)
(12, 5,  10, '2026-10-21 15:30:00'), -- Claypot Black Pork Curry (LOW STOCK ALERT: 5 <= 10)
(13, 25, 12, '2026-10-22 09:30:00'), -- Hot Butter Cuttlefish (Healthy)
(14, 40, 15, '2026-10-22 07:00:00'), -- Village Pol Roti with Lunu Miris (Healthy)
(15, 30, 15, '2026-10-22 07:30:00'), -- Crispy Mutton Rolls (Healthy)
(16, 8,  12, '2026-10-22 07:30:00'), -- Egg Hoppers & Plain Hoppers Platter (LOW STOCK ALERT: 8 <= 12)
(17, 24, 10, '2026-10-22 08:00:00'), -- Kithul Treacle Watalappan (Healthy)
(18, 15, 8,  '2026-10-22 08:00:00'), -- Caramel Biscuit Pudding (Healthy)
(19, 18, 10, '2026-10-22 08:00:00'), -- Creamy Buffalo Curd & Treacle (Healthy)
(20, 3,  8,  '2026-10-21 17:00:00'), -- Tropical Mango & Passion Tart (LOW STOCK ALERT: 3 <= 8)
(21, 32, 10, '2026-10-22 08:00:00'), -- UrbanEats Royal Faluda (Healthy)
(22, 50, 20, '2026-10-22 07:00:00'), -- Ceylon Spiced Cardamom Milk Tea (Healthy)
(23, 25, 10, '2026-10-22 07:00:00'), -- Fresh King Coconut Water (Healthy)
(24, 30, 12, '2026-10-22 08:00:00'); -- Lime & Mint Sparkling Soda (Healthy)

-- 4. Stock Movements Seed Data (24 Realistic Movements: restock, wastage, adjustments)
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

-- =====================================================================
-- SEED DATA: COMPONENT C (Coupons, Orders, Order Items, Status History)
-- =====================================================================
INSERT INTO `coupons` (`coupon_id`, `code`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `expiry_date`, `is_active`) VALUES
(1, 'UE10', 'percentage', 10.00, 1000.00, 500.00, '2027-12-31', 1),
(2, 'WELCOME500', 'fixed', 500.00, 2000.00, 500.00, '2027-12-31', 1);

INSERT INTO `orders` (`order_id`, `order_code`, `user_id`, `order_type`, `delivery_address`, `phone`, `payment_method`, `subtotal`, `delivery_fee`, `discount_amount`, `total_amount`, `status`, `created_at`) VALUES
(1, 'ORD-2026-1001', 6, 'delivery', '24 Havelock Road, Colombo 05', '0775566778', 'cod', 2900.00, 250.00, 290.00, 2860.00, 'Placed', NOW() - INTERVAL 25 MINUTE),
(2, 'ORD-2026-1002', 7, 'delivery', '78 Rajagiriya Road, Rajagiriya', '0714455667', 'card', 2200.00, 250.00, 0.00, 2450.00, 'Preparing', NOW() - INTERVAL 45 MINUTE),
(3, 'ORD-2026-1003', 8, 'pickup', 'Pickup counter', '0702233445', 'cod', 1450.00, 0.00, 0.00, 1450.00, 'Ready', NOW() - INTERVAL 1 HOUR),
(4, 'ORD-2026-1004', 9, 'delivery', '90 Nawala Road, Nugegoda', '0758899001', 'bank', 3100.00, 250.00, 310.00, 3040.00, 'Completed', NOW() - INTERVAL 2 HOUR);

INSERT INTO `order_items` (`item_id`, `order_id`, `dish_id`, `quantity`, `price_at_purchase`) VALUES
(1, 1, 1, 2, 1450.00),
(2, 2, 2, 1, 2150.00),
(3, 2, 21, 1, 750.00),
(4, 3, 1, 1, 1450.00),
(5, 4, 8, 2, 1850.00);

INSERT INTO `order_status_history` (`history_id`, `order_id`, `status`, `changed_by`, `changed_at`) VALUES
(1, 1, 'Placed', 6, NOW() - INTERVAL 25 MINUTE),
(2, 2, 'Placed', 7, NOW() - INTERVAL 45 MINUTE),
(2, 2, 'Preparing', 3, NOW() - INTERVAL 30 MINUTE),
(3, 3, 'Placed', 8, NOW() - INTERVAL 60 MINUTE),
(3, 3, 'Preparing', 3, NOW() - INTERVAL 40 MINUTE),
(3, 3, 'Ready', 3, NOW() - INTERVAL 15 MINUTE),
(4, 4, 'Placed', 9, NOW() - INTERVAL 120 MINUTE),
(4, 4, 'Preparing', 4, NOW() - INTERVAL 90 MINUTE),
(4, 4, 'Ready', 4, NOW() - INTERVAL 60 MINUTE),
(4, 4, 'Out for Delivery', 4, NOW() - INTERVAL 40 MINUTE),
(4, 4, 'Completed', 1, NOW() - INTERVAL 10 MINUTE);
