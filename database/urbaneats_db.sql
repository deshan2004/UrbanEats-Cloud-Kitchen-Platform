
CREATE DATABASE IF NOT EXISTS `urbaneats_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `urbaneats_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

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
-- Seed Data: At least 20 realistic records in `users`
-- Default password for all seed accounts is: Password123!
-- Hashed using BCRYPT: $2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6
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

-- ---------------------------------------------------------------------
-- Seed Audit Trail for Initialization
-- ---------------------------------------------------------------------
INSERT INTO `audit_log` (`user_id`, `action`, `details`, `ip_address`, `user_agent`) VALUES
(1, 'SYSTEM_INIT', 'Initial database schema and default roles/accounts seeded successfully', '127.0.0.1', 'CLI-Migration');

-- ---------------------------------------------------------------------
-- Table: categories (Component B)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: dishes (Component B)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dishes` (
    `dish_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 50,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    CONSTRAINT `fk_items_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`dish_id`) ON DELETE RESTRICT
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

-- ---------------------------------------------------------------------
-- Component B & C Seed Data
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `categories` (`category_id`, `name`, `description`) VALUES
(1, 'Kottu & Roti', 'Signature chopped flatbread specials with spices'),
(2, 'Rice & Curry', 'Authentic Sri Lankan curries with steamed rice'),
(3, 'Beverages', 'Refreshing cold and hot drinks'),
(4, 'Desserts', 'Traditional and modern sweets');

INSERT IGNORE INTO `dishes` (`dish_id`, `category_id`, `name`, `description`, `price`, `stock_quantity`, `is_active`) VALUES
(1, 1, 'Chicken Kottu', 'Fresh shredded roti tossed with tender chicken, vegetables and aromatic roast curry spices.', 1450.00, 45, 1),
(2, 1, 'Cheese Kottu', 'Creamy cheese blend mixed with spiced chicken kottu and green chilies.', 1650.00, 30, 1),
(3, 2, 'Sri Lankan Village Rice & Curry', 'Steamed samba rice served with 4 vegetable curries and crispy papadum.', 950.00, 50, 1),
(4, 2, 'Seafood Nasi Goreng', 'Wok-tossed Indonesian style rice with calamari, prawns, fried egg and chili paste.', 1550.00, 25, 1),
(5, 3, 'Mango Lassi', 'Chilled sweet yoghurt blended with real alphonso mango puree.', 550.00, 60, 1),
(6, 4, 'Traditional Watalappan', 'Steamed coconut custard sweetened with pure Kitul jaggery, cardamom and cashew nuts.', 450.00, 40, 1);

INSERT IGNORE INTO `coupons` (`coupon_id`, `code`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `expiry_date`, `is_active`) VALUES
(1, 'UE10', 'percentage', 10.00, 1000.00, 500.00, '2027-12-31', 1),
(2, 'WELCOME500', 'fixed', 500.00, 2000.00, 500.00, '2027-12-31', 1);

INSERT IGNORE INTO `orders` (`order_id`, `order_code`, `user_id`, `order_type`, `delivery_address`, `phone`, `payment_method`, `subtotal`, `delivery_fee`, `discount_amount`, `total_amount`, `status`, `created_at`) VALUES
(1, 'ORD-2026-1001', 6, 'delivery', '24 Havelock Road, Colombo 05', '0775566778', 'cod', 2900.00, 250.00, 290.00, 2860.00, 'Placed', NOW() - INTERVAL 25 MINUTE),
(2, 'ORD-2026-1002', 7, 'delivery', '78 Rajagiriya Road, Rajagiriya', '0714455667', 'card', 2200.00, 250.00, 0.00, 2450.00, 'Preparing', NOW() - INTERVAL 45 MINUTE),
(3, 'ORD-2026-1003', 8, 'pickup', 'Pickup counter', '0702233445', 'cod', 1450.00, 0.00, 0.00, 1450.00, 'Ready', NOW() - INTERVAL 1 HOUR),
(4, 'ORD-2026-1004', 9, 'delivery', '90 Nawala Road, Nugegoda', '0758899001', 'bank', 3100.00, 250.00, 310.00, 3040.00, 'Completed', NOW() - INTERVAL 2 HOUR);

INSERT IGNORE INTO `order_items` (`order_id`, `dish_id`, `quantity`, `price_at_purchase`) VALUES
(1, 1, 2, 1450.00),
(2, 2, 1, 1650.00),
(2, 5, 1, 550.00),
(3, 1, 1, 1450.00),
(4, 4, 2, 1550.00);

INSERT IGNORE INTO `order_status_history` (`order_id`, `status`, `changed_by`, `changed_at`) VALUES
(1, 'Placed', 6, NOW() - INTERVAL 25 MINUTE),
(2, 'Placed', 7, NOW() - INTERVAL 45 MINUTE),
(2, 'Preparing', 3, NOW() - INTERVAL 30 MINUTE),
(3, 'Placed', 8, NOW() - INTERVAL 60 MINUTE),
(3, 'Preparing', 3, NOW() - INTERVAL 40 MINUTE),
(3, 'Ready', 3, NOW() - INTERVAL 15 MINUTE),
(4, 'Placed', 9, NOW() - INTERVAL 120 MINUTE),
(4, 'Preparing', 4, NOW() - INTERVAL 90 MINUTE),
(4, 'Ready', 4, NOW() - INTERVAL 60 MINUTE),
(4, 'Out for Delivery', 4, NOW() - INTERVAL 40 MINUTE),
(4, 'Completed', 1, NOW() - INTERVAL 10 MINUTE);

