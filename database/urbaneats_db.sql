-- =====================================================================
-- UrbanEats Cloud Kitchen Platform - Database Schema & Initial Seed Data
-- Component A: Identity, Access Control & Administration
-- Designed for IT3113 Advanced Web Technologies
-- =====================================================================

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
