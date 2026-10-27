-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 27, 2026 at 05:10 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `urbaneats_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'SYSTEM_INIT', 'Initial database schema and default roles/accounts seeded successfully', '127.0.0.1', 'CLI-Migration', '2026-10-15 08:00:00'),
(2, 1, 'USER_LOGIN', 'Administrator authenticated successfully', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-15 08:30:00'),
(3, 1, 'ROLE_ASSIGNED', 'Assigned role Kitchen Staff to user #3 (Kasun Bandara)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-15 09:00:00'),
(4, 3, 'USER_LOGIN', 'Kitchen staff logged into fulfillment station', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-16 07:30:00'),
(5, 1, 'DISH_CREATED', 'Added new dish: Kottu Specialties - Chicken Kottu (LKR 1,450.00)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-16 10:15:00'),
(6, 3, 'STOCK_RESTOCKED', 'Restocked 40 portions of Chicken Kottu after morning preparation', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-16 11:00:00'),
(7, 6, 'USER_REGISTER', 'New customer account created: Anuki Fernando (anuki99)', '112.134.88.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4)', '2026-10-17 12:05:00'),
(8, 6, 'USER_LOGIN', 'Customer logged in from mobile device', '112.134.88.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4)', '2026-10-17 12:06:00'),
(9, 6, 'ORDER_PLACED', 'Order #ORD-2026-1001 placed successfully (Total: LKR 2,860.00)', '112.134.88.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4)', '2026-10-17 12:15:00'),
(10, 3, 'ORDER_STATUS_CHANGED', 'Order #ORD-2026-1001 transitioned Placed -> Preparing', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-17 12:20:00'),
(11, 7, 'USER_REGISTER', 'New customer account created: Kavindu Jayasuriya (kavindu_j)', '112.135.12.98', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-17 13:40:00'),
(12, 7, 'ORDER_PLACED', 'Order #ORD-2026-1002 placed successfully (Total: LKR 2,450.00)', '112.135.12.98', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-17 13:45:00'),
(13, 4, 'STOCK_WASTAGE', 'Logged wastage of 2 portions: Cheese sauce container contaminated', '192.168.1.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-18 10:45:00'),
(14, 1, 'USER_STATUS_UPDATED', 'Deactivated dormant test account #21', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-18 14:00:00'),
(15, 8, 'USER_REGISTER', 'New customer account created: Tharushi Wijesinghe (tharushi_w)', '112.134.90.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5)', '2026-10-19 11:20:00'),
(16, 8, 'ORDER_PLACED', 'Order #ORD-2026-1003 placed successfully (Total: LKR 1,450.00)', '112.134.90.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5)', '2026-10-19 11:30:00'),
(17, 9, 'ORDER_PLACED', 'Order #ORD-2026-1004 placed successfully (Total: LKR 3,040.00)', '124.43.78.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-19 12:10:00'),
(18, 1, 'PASSWORD_RESET_REQUESTED', 'Requested secure password reset link for user #10 (pooja_m)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-20 09:15:00'),
(19, 10, 'PASSWORD_RESET_COMPLETED', 'User #10 successfully reset account password using secure token', '124.43.82.11', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-20 09:22:00'),
(20, 1, 'CATEGORY_UPDATED', 'Updated category display order and metadata for Kottu Specialties', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-21 16:45:00'),
(21, 3, 'STOCK_ADJUSTMENT', 'Weekly physical stock audit reconciliation performed', '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-22 17:00:00'),
(22, 1, 'SALES_REPORT_VIEWED', 'Executive monthly sales analytics exported', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-23 09:30:00'),
(24, NULL, 'LOGIN_FAILED', 'Failed login attempt for identifier \'deshan2004\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:09:26'),
(25, 22, 'USER_REGISTERED', 'New customer account created for username \"Deshan2004\" (deshandhakshitha16@gmail.com)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:10:44'),
(26, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:10:59'),
(27, 22, 'ORDER_PLACED', 'Order #ORD-20261023-8525B1 placed successfully (Total: LKR 2,400.00, Payment: COD)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:35:47'),
(28, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:47:43'),
(29, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:47:56'),
(30, 1, 'ORDER_STATUS_CHANGED', 'Order #25 transitioned from Placed to Preparing', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:48:25'),
(31, 1, 'ORDER_STATUS_CHANGED', 'Order #25 transitioned from Preparing to Ready', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:49:02'),
(32, 1, 'ORDER_STATUS_CHANGED', 'Order #25 transitioned from Ready to Out for Delivery', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:49:07'),
(33, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:49:29'),
(34, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:49:48'),
(35, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:50:09'),
(36, NULL, 'LOGIN_FAILED', 'Failed login attempt for identifier \'admin@epms.com\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:50:12'),
(37, NULL, 'LOGIN_FAILED', 'Failed login attempt for identifier \'admin@epms.com\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:50:17'),
(38, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:51:15'),
(39, 1, 'ORDER_STATUS_CHANGED', 'Order #25 transitioned from Out for Delivery to Completed', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 20:51:41'),
(40, 1, 'ORDER_STATUS_CHANGED', 'Order #2 transitioned from Preparing to Ready', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:01:26'),
(41, 1, 'ORDER_STATUS_CHANGED', 'Order #1 transitioned from Placed to Preparing', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:02:08'),
(42, 1, 'ORDER_STATUS_CHANGED', 'Order #1 transitioned from Preparing to Ready', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:04:29'),
(43, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:05:47'),
(44, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:06:03'),
(45, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:07:02'),
(46, NULL, 'LOGIN_FAILED', 'Failed login attempt for identifier \'admin@urbaneats.lk\'\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:07:10'),
(47, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:07:16'),
(48, 1, 'PROFILE_UPDATE', 'Updated personal profile details', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:09:38'),
(49, 1, 'ORDER_STATUS_CHANGED', 'Order #1 transitioned from Ready to Out for Delivery', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:18:45'),
(50, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:18:56'),
(51, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:19:01'),
(52, 22, 'ORDER_PLACED', 'Order #ORD-20261023-94ED34 placed successfully (Total: LKR 2,100.00, Payment: COD)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:19:32'),
(53, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:19:49'),
(54, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:19:54'),
(55, 1, 'ORDER_STATUS_CHANGED', 'Order #26 transitioned from Placed to Preparing', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:20:05'),
(56, 1, 'ORDER_STATUS_CHANGED', 'Order #26 transitioned from Preparing to Ready', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:20:13'),
(57, 1, 'ORDER_STATUS_CHANGED', 'Order #26 transitioned from Ready to Out for Delivery', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:20:20'),
(58, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:20:33'),
(59, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:20:42'),
(60, 22, 'ORDER_PLACED', 'Order #ORD-20261023-DDFCFC placed successfully (Total: LKR 2,400.00, Payment: COD)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:27:48'),
(61, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:27:52'),
(62, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:27:58'),
(63, 1, 'ORDER_STATUS_CHANGED', 'Order #27 transitioned from Placed to Preparing', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:28:04'),
(64, 1, 'ORDER_STATUS_CHANGED', 'Order #27 transitioned from Preparing to Ready', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:28:06'),
(65, 1, 'ORDER_STATUS_CHANGED', 'Order #27 transitioned from Ready to Out for Delivery', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:28:11'),
(66, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:28:17'),
(67, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:28:25'),
(68, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:28:52'),
(69, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:28:57'),
(70, 1, 'ORDER_STATUS_CHANGED', 'Order #26 transitioned from Out for Delivery to Completed', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:29:02'),
(71, 1, 'ORDER_STATUS_CHANGED', 'Order #27 transitioned from Out for Delivery to Completed', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:29:08'),
(72, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:29:13'),
(73, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:29:24'),
(74, 22, 'ORDER_PLACED', 'Order #ORD-20261023-80215D placed successfully (Total: LKR 3,450.00, Payment: CARD)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:32:39'),
(75, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:34:58'),
(76, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:35:10'),
(77, 1, 'ORDER_STATUS_CHANGED', 'Order #28 transitioned from Placed to Preparing', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:35:15'),
(78, 1, 'ORDER_STATUS_CHANGED', 'Order #28 transitioned from Preparing to Ready', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:35:19'),
(79, 1, 'ORDER_STATUS_CHANGED', 'Order #28 transitioned from Ready to Out for Delivery', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:35:24'),
(80, 1, 'ORDER_STATUS_CHANGED', 'Order #28 transitioned from Out for Delivery to Completed', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:35:32'),
(81, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:35:33'),
(82, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:35:37'),
(83, 22, 'ORDER_PLACED', 'Order #ORD-20261023-B9DC89 placed successfully (Total: LKR 1,050.00, Payment: COD)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:46:44'),
(84, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:46:57'),
(85, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:04'),
(86, 1, 'ORDER_STATUS_CHANGED', 'Order #29 transitioned from Placed to Preparing', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:08'),
(87, 1, 'ORDER_STATUS_CHANGED', 'Order #29 transitioned from Preparing to Ready', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:11'),
(88, 1, 'ORDER_STATUS_CHANGED', 'Order #29 transitioned from Ready to Out for Delivery', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:14'),
(89, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:18'),
(90, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:22'),
(91, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:28'),
(92, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:47:34'),
(93, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-23 21:48:56'),
(94, 23, 'USER_REGISTERED', 'New customer account created for username \"chanula2003\" (chanula123@gmail.com)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 14:27:37'),
(95, 23, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 14:27:55'),
(96, 23, 'LOGOUT', 'User \'chanula2003\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 14:50:55'),
(97, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 14:51:07'),
(98, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:12:50'),
(99, 22, 'LOGIN_FAILED', 'Failed login attempt for identifier \'Deshan2004\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:15:29'),
(100, 22, 'LOGIN_FAILED', 'Failed login attempt for identifier \'Deshan2004\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:15:35'),
(101, 22, 'LOGIN_FAILED', 'Failed login attempt for identifier \'Deshan2004\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:15:40'),
(102, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:16:36'),
(103, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:17:47'),
(104, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:17:57'),
(105, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:19:16'),
(106, 22, 'PASSWORD_RESET_REQUESTED', 'Password reset token generated for user \'Deshan2004\'', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:19:49'),
(107, 22, 'LOGIN_FAILED', 'Failed login attempt for identifier \'Deshan2004\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:33:47'),
(108, 22, 'LOGIN_FAILED', 'Failed login attempt for identifier \'Deshan2004\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:33:51'),
(109, 22, 'LOGIN_FAILED', 'Failed login attempt for identifier \'Deshan2004\' from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:33:55'),
(110, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:34:57'),
(111, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:36:16'),
(112, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:36:21'),
(113, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:39:37'),
(114, 22, 'PASSWORD_RESET_REQUESTED', 'Password reset token generated for user \'Deshan2004\'', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 15:39:41'),
(115, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:00:24'),
(116, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:01:11'),
(117, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:01:14'),
(118, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:04:16'),
(119, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:04:24'),
(120, 1, 'ORDER_STATUS_CHANGED', 'Order #29 transitioned from Out for Delivery to Completed', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:04:33'),
(121, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:04:40'),
(122, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:04:45'),
(123, 22, 'PROFILE_UPDATE', 'Updated personal profile details', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:14:06'),
(124, 22, 'ORDER_PLACED', 'Order #ORD-20261027-E97EA8 placed successfully (Total: LKR 1,700.00, Payment: COD)', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:14:57'),
(125, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:15:51'),
(126, 1, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:15:59'),
(127, 1, 'LOGOUT', 'User \'admin\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:17:38'),
(128, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 09:18:21'),
(129, 22, 'LOGIN_SUCCESS', 'User authenticated successfully from IP ::1', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 16:11:15'),
(130, 22, 'LOGOUT', 'User \'Deshan2004\' logged out successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-27 16:22:58');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `id` int(10) UNSIGNED GENERATED ALWAYS AS (`category_id`) VIRTUAL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `slug`, `description`, `icon`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Kottu Specialties', 'kottu-specialties', 'Authentic Colombo street style chopped roti cooked on hot griddle with spices and meats', '🍲', 1, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(2, 'Rice & Biryani', 'rice-and-biryani', 'Fragrant basmati rice, slow-cooked Dum biryanis, and traditional Lankan banana leaf lamprais', '🍚', 2, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(3, 'Traditional Curries', 'traditional-curries', 'Slow-cooked clay pot curries infused with roasted spices and fresh thick coconut milk', '🍛', 3, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(4, 'Street Bites & Roti', 'street-bites-and-roti', 'Crispy short eats, golden fried cutlets, hot godamba wraps, and village style pol roti', '🥟', 4, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(5, 'Artisan Desserts', 'artisan-desserts', 'Traditional Sri Lankan sweets, rich jaggery watalappan, and tropical puddings', '🍨', 5, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(6, 'Beverages & Refreshers', 'beverages-refreshers', 'Ice cold faludas, spiced Ceylon milk teas, tropical king coconut, and freshly squeezed fruit juices', '🥤', 6, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `coupon_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`coupon_id`, `code`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `expiry_date`, `is_active`, `created_at`) VALUES
(1, 'UE10', 'percentage', 10.00, 1000.00, 500.00, '2027-12-31', 1, '2026-10-23 19:48:09'),
(2, 'WELCOME500', 'fixed', 500.00, 2000.00, 500.00, '2027-12-31', 1, '2026-10-23 19:48:09'),
(3, 'KOTTU15', 'percentage', 15.00, 1200.00, 600.00, '2027-12-31', 1, '2026-10-23 19:48:09'),
(4, 'LUNCH200', 'fixed', 200.00, 1000.00, 200.00, '2027-12-31', 1, '2026-10-23 19:48:09'),
(5, 'FEAST20', 'percentage', 20.00, 3000.00, 1000.00, '2027-12-31', 1, '2026-10-23 19:48:09'),
(6, 'FREEDEL', 'fixed', 250.00, 1500.00, 250.00, '2027-12-31', 1, '2026-10-23 19:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `dishes`
--

CREATE TABLE `dishes` (
  `dish_id` int(10) UNSIGNED NOT NULL,
  `id` int(10) UNSIGNED GENERATED ALWAYS AS (`dish_id`) VIRTUAL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 20,
  `image_url` varchar(255) DEFAULT NULL,
  `is_vegetarian` tinyint(1) NOT NULL DEFAULT 0,
  `is_spicy` tinyint(1) NOT NULL DEFAULT 0,
  `prep_time_minutes` int(11) NOT NULL DEFAULT 15,
  `calories` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dishes`
--

INSERT INTO `dishes` (`dish_id`, `category_id`, `name`, `slug`, `description`, `price`, `stock_quantity`, `image_url`, `is_vegetarian`, `is_spicy`, `prep_time_minutes`, `calories`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Classic Chicken Kottu', 'classic-chicken-kottu', 'Tender shredded chicken, farm-fresh eggs, leeks, carrots and chopped flatbread tossed in aromatic curry gravy.', 1450.00, 34, 'uploads/chicken_kottu.jpg', 0, 1, 15, 680, 1, '2026-10-23 19:48:09', '2026-10-27 09:14:57'),
(2, 1, 'Seafood Cheese Kottu', 'seafood-cheese-kottu', 'Signature wok-charred kottu loaded with fresh prawns, cuttlefish, melted mozzarella and rich creamy cheese sauce.', 2150.00, 16, 'uploads/seafood_cheese_kottu.jpg', 0, 1, 18, 890, 1, '2026-10-23 19:48:09', '2026-10-23 21:27:48'),
(3, 1, 'Spicy Roast Beef Kottu', 'spicy-roast-beef-kottu', 'Slow-roasted dry beef cubes, crispy curry leaves, and green chillies infused with dark roasted Jaffna spices.', 1850.00, 5, 'uploads/beef_kottu.jpg', 0, 1, 15, 750, 1, '2026-10-23 19:48:09', '2026-10-23 21:19:32'),
(4, 1, 'Vegetable & Egg Palandi Kottu', 'vegetable-egg-palandi-kottu', 'Fresh cabbage, carrots, spring onions, tempered paneer cubes, and eggs sauteed in light spices.', 1150.00, 22, 'uploads/veg_kottu.jpg', 0, 0, 12, 520, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(5, 2, 'Authentic Lankan Lamprais', 'authentic-lankan-lamprais', 'Dutch-Burgher recipe steamed in banana leaf: yellow samba rice, mixed meat frikkadels, seeni sambol, and ash plantain.', 1950.00, 4, 'uploads/lamprais.jpg', 0, 1, 20, 840, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(6, 2, 'Dambulla Chicken Dum Biryani', 'dambulla-chicken-dum-biryani', 'Aromatic long-grain basmati layered with marinated chicken, saffron milk, boiled egg, mint raita, and spicy gravy.', 1750.00, 28, 'uploads/chicken_biryani.jpg', 0, 1, 20, 780, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(7, 2, 'Royal Mutton Biryani', 'royal-mutton-biryani', 'Succulent grass-fed mutton chunks cooked with whole aromatic spices, caramelized onions, boiled egg, and cashew nuts.', 2450.00, 0, 'uploads/mutton_biryani.jpg', 0, 1, 22, 920, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(8, 2, 'Wok-Fried Mixed Seafood Rice', 'wok-fried-mixed-seafood-rice', 'Fragrant jasmine rice wok-tossed on high flame with jumbo prawns, calamari rings, scrambled eggs, and chili paste.', 1850.00, 14, 'uploads/seafood_rice.jpg', 0, 0, 15, 690, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(9, 3, 'Jaffna Fiery Crab Curry', 'jaffna-fiery-crab-curry', 'Lagoon mud crabs simmered in toasted black curry powder, drumstick leaves, tamarind juice, and thick coconut cream.', 2850.00, 0, 'uploads/crab_curry.jpg', 0, 1, 25, 620, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(10, 3, 'Southern Fish Ambul Thiyal', 'southern-fish-ambul-thiyal', 'Traditional sour fish curry prepared with fresh tuna steaks, dried goraka paste, black pepper, and garlic in a clay pot.', 1600.00, 14, 'uploads/ambul_thiyal.jpg', 0, 1, 20, 480, 1, '2026-10-23 19:48:09', '2026-10-23 21:32:39'),
(11, 3, 'Creamy Cashew & Green Pea Curry', 'creamy-cashew-green-pea-curry', 'Plump whole Sri Lankan raw cashews and sweet garden peas braised gently in velvety golden coconut gravy.', 1550.00, 20, 'uploads/cashew_curry.jpg', 1, 0, 15, 540, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(12, 3, 'Claypot Black Pork Curry', 'claypot-black-pork-curry', 'Tender pork belly pieces marinated in heavily roasted curry paste, fenugreek, and tart vinegar, simmered until dark.', 1900.00, 5, 'uploads/black_pork.jpg', 0, 1, 22, 790, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(13, 4, 'Hot Butter Cuttlefish (HBC)', 'hot-butter-cuttlefish', 'Crispy batter-fried calamari rings tossed in unsalted butter, spring onions, capsicum, and dry chili flakes.', 1850.00, 25, 'uploads/hbc.jpg', 0, 1, 14, 580, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(14, 4, 'Village Pol Roti with Lunu Miris', 'village-pol-roti-lunu-miris', 'Set of 3 freshly toasted coconut flatbreads served with fiery crushed chili-onion sambol and butter.', 750.00, 40, 'uploads/pol_roti.jpg', 1, 1, 10, 420, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(15, 4, 'Crispy Mutton Rolls (3 Pcs)', 'crispy-mutton-rolls', 'Golden breaded pan rolls stuffed with spiced potatoes, leeks, and minced mutton, served with sweet chili dip.', 950.00, 30, 'uploads/mutton_rolls.jpg', 0, 1, 10, 480, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(16, 4, 'Egg Hoppers & Plain Hoppers Platter', 'egg-hoppers-platter', 'Two crispy lace-edged egg hoppers and two plain bowl hoppers served with spicy katta sambol and coconut milk gravy.', 900.00, 8, 'uploads/hoppers.jpg', 1, 0, 15, 380, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(17, 5, 'Kithul Treacle Watalappan', 'kithul-treacle-watalappan', 'Steamed coconut custard pudding sweetened with pure organic Mirigama kithul jaggery, cardamom, and toasted cashews.', 650.00, 24, 'uploads/watalappan.jpg', 1, 0, 5, 360, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(18, 5, 'Caramel Biscuit Pudding', 'caramel-biscuit-pudding', 'Layered butter biscuits soaked in warm espresso and rich chocolate caramel custard, chilled to perfection.', 700.00, 15, 'uploads/biscuit_pudding.jpg', 1, 0, 5, 410, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(19, 5, 'Creamy Buffalo Curd & Treacle', 'creamy-buffalo-curd-treacle', 'Authentic clay pot curd made from fresh southern buffalo milk, drizzled generously with thick amber kithul syrup.', 600.00, 18, 'uploads/curd_treacle.jpg', 1, 0, 5, 290, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(20, 5, 'Tropical Mango & Passion Tart', 'tropical-mango-passion-tart', 'Buttery pastry shell filled with tangy passionfruit curd and topped with fresh Sri Lankan Willard mango slices.', 850.00, 3, 'uploads/mango_tart.jpg', 1, 0, 5, 340, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(21, 6, 'UrbanEats Royal Faluda', 'urbaneats-royal-faluda', 'Chilled sweet rose milk layered with basil seeds (kasakasa), grass jelly strips, vanilla ice cream, and strawberry glaze.', 750.00, 32, 'uploads/faluda.jpg', 1, 0, 5, 320, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(22, 6, 'Ceylon Spiced Cardamom Milk Tea', 'ceylon-spiced-milk-tea', 'Slow-brewed highland Ceylon BOPF tea infused with cracked green cardamom pods, cinnamon bark, and sweetened milk.', 450.00, 50, 'uploads/milk_tea.jpg', 1, 0, 6, 180, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(23, 6, 'Fresh King Coconut Water', 'fresh-king-coconut-water', 'Pure, natural chilled electrolyte drink served in a chilled glass with tender coconut kernel ribbons.', 400.00, 23, 'uploads/thambili.jpg', 1, 0, 3, 75, 1, '2026-10-23 19:48:09', '2026-10-23 21:46:44'),
(24, 6, 'Lime & Mint Sparkling Soda', 'lime-mint-sparkling-soda', 'Zesty fresh key lime juice crushed with garden mint leaves and bubbly soda water over crushed ice.', 550.00, 30, 'uploads/lime_soda.jpg', 1, 0, 4, 95, 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `dish_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `last_restocked_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `dish_id`, `quantity`, `reorder_level`, `last_restocked_at`, `updated_at`) VALUES
(1, 1, 34, 12, '2026-10-22 08:30:00', '2026-10-27 09:14:57'),
(2, 2, 16, 10, '2026-10-22 08:30:00', '2026-10-23 21:27:48'),
(3, 3, 5, 10, '2026-10-21 16:00:00', '2026-10-23 21:19:32'),
(4, 4, 22, 10, '2026-10-22 08:30:00', '2026-10-23 19:48:09'),
(5, 5, 4, 10, '2026-10-21 14:00:00', '2026-10-23 19:48:09'),
(6, 6, 28, 15, '2026-10-22 09:00:00', '2026-10-23 19:48:09'),
(7, 7, 0, 8, '2026-10-20 18:00:00', '2026-10-23 19:48:09'),
(8, 8, 14, 10, '2026-10-22 09:00:00', '2026-10-23 19:48:09'),
(9, 9, 0, 6, '2026-10-21 12:00:00', '2026-10-23 19:48:09'),
(10, 10, 14, 8, '2026-10-22 08:30:00', '2026-10-23 21:32:39'),
(11, 11, 20, 10, '2026-10-22 08:30:00', '2026-10-23 19:48:09'),
(12, 12, 5, 10, '2026-10-21 15:30:00', '2026-10-23 19:48:09'),
(13, 13, 25, 12, '2026-10-22 09:30:00', '2026-10-23 19:48:09'),
(14, 14, 40, 15, '2026-10-22 07:00:00', '2026-10-23 19:48:09'),
(15, 15, 30, 15, '2026-10-22 07:30:00', '2026-10-23 19:48:09'),
(16, 16, 8, 12, '2026-10-22 07:30:00', '2026-10-23 19:48:09'),
(17, 17, 24, 10, '2026-10-22 08:00:00', '2026-10-23 19:48:09'),
(18, 18, 15, 8, '2026-10-22 08:00:00', '2026-10-23 19:48:09'),
(19, 19, 18, 10, '2026-10-22 08:00:00', '2026-10-23 19:48:09'),
(20, 20, 3, 8, '2026-10-21 17:00:00', '2026-10-23 19:48:09'),
(21, 21, 32, 10, '2026-10-22 08:00:00', '2026-10-23 19:48:09'),
(22, 22, 50, 20, '2026-10-22 07:00:00', '2026-10-23 19:48:09'),
(23, 23, 23, 10, '2026-10-22 07:00:00', '2026-10-23 21:46:44'),
(24, 24, 30, 12, '2026-10-22 08:00:00', '2026-10-23 19:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_successful` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `username`, `attempted_at`, `is_successful`) VALUES
(1, '::1', 'deshan2004', '2026-10-23 20:09:26', 0),
(2, '::1', 'Deshan2004', '2026-10-23 20:10:59', 1),
(3, '::1', 'admin', '2026-10-23 20:47:56', 1),
(4, '::1', 'Deshan2004', '2026-10-23 20:49:48', 1),
(5, '::1', 'admin@epms.com', '2026-10-23 20:50:12', 0),
(6, '::1', 'admin@epms.com', '2026-10-23 20:50:17', 0),
(7, '::1', 'admin', '2026-10-23 20:51:15', 1),
(8, '::1', 'Deshan2004', '2026-10-23 21:06:03', 1),
(9, '::1', 'admin@urbaneats.lk\'', '2026-10-23 21:07:10', 0),
(10, '::1', 'admin', '2026-10-23 21:07:16', 1),
(11, '::1', 'Deshan2004', '2026-10-23 21:19:01', 1),
(12, '::1', 'admin', '2026-10-23 21:19:54', 1),
(13, '::1', 'Deshan2004', '2026-10-23 21:20:42', 1),
(14, '::1', 'admin', '2026-10-23 21:27:58', 1),
(15, '::1', 'Deshan2004', '2026-10-23 21:28:25', 1),
(16, '::1', 'admin', '2026-10-23 21:28:57', 1),
(17, '::1', 'Deshan2004', '2026-10-23 21:29:24', 1),
(18, '::1', 'admin', '2026-10-23 21:35:10', 1),
(19, '::1', 'Deshan2004', '2026-10-23 21:35:37', 1),
(20, '::1', 'admin', '2026-10-23 21:47:04', 1),
(21, '::1', 'admin', '2026-10-23 21:47:22', 1),
(22, '::1', 'Deshan2004', '2026-10-23 21:47:34', 1),
(23, '::1', 'chanula2003', '2026-10-25 14:27:55', 1),
(24, '::1', 'admin', '2026-10-25 14:51:07', 1),
(25, '::1', 'Deshan2004', '2026-10-25 15:15:29', 0),
(26, '::1', 'Deshan2004', '2026-10-25 15:15:35', 0),
(27, '::1', 'Deshan2004', '2026-10-25 15:15:40', 0),
(28, '::1', 'Deshan2004', '2026-10-25 15:16:36', 1),
(29, '::1', 'admin', '2026-10-25 15:17:57', 1),
(30, '::1', 'Deshan2004', '2026-10-25 15:33:47', 0),
(31, '::1', 'Deshan2004', '2026-10-25 15:33:51', 0),
(32, '::1', 'Deshan2004', '2026-10-25 15:33:55', 0),
(33, '::1', 'Deshan2004', '2026-10-25 15:34:57', 1),
(34, '::1', 'admin', '2026-10-25 15:36:21', 1),
(35, '::1', 'admin', '2026-10-27 09:00:24', 1),
(36, '::1', 'Deshan2004', '2026-10-27 09:01:14', 1),
(37, '::1', 'admin', '2026-10-27 09:04:24', 1),
(38, '::1', 'Deshan2004', '2026-10-27 09:04:45', 1),
(39, '::1', 'admin', '2026-10-27 09:15:59', 1),
(40, '::1', 'Deshan2004', '2026-10-27 09:18:21', 1),
(41, '::1', 'Deshan2004', '2026-10-27 16:11:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_code` varchar(50) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_type` enum('delivery','pickup') NOT NULL DEFAULT 'delivery',
  `delivery_address` text DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `payment_method` enum('cod','card','bank') NOT NULL DEFAULT 'cod',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Placed','Preparing','Ready','Out for Delivery','Collected','Completed','Cancelled') NOT NULL DEFAULT 'Placed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_code`, `user_id`, `order_type`, `delivery_address`, `phone`, `notes`, `payment_method`, `subtotal`, `delivery_fee`, `discount_amount`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ORD-2026-1001', 6, 'delivery', '24 Havelock Road, Colombo 05', '0775566778', NULL, 'cod', 2900.00, 250.00, 290.00, 2860.00, 'Out for Delivery', '2026-10-23 18:40:00', '2026-10-23 21:18:45'),
(2, 'ORD-2026-1002', 7, 'delivery', '78 Rajagiriya Road, Rajagiriya', '0714455667', NULL, 'card', 2200.00, 250.00, 0.00, 2450.00, 'Ready', '2026-10-23 18:15:00', '2026-10-23 21:01:26'),
(3, 'ORD-2026-1003', 8, 'pickup', 'Pickup counter', '0702233445', NULL, 'cod', 1450.00, 0.00, 0.00, 1450.00, 'Ready', '2026-10-23 17:50:00', '2026-10-23 19:48:09'),
(4, 'ORD-2026-1004', 9, 'delivery', '90 Nawala Road, Nugegoda', '0758899001', NULL, 'bank', 3100.00, 250.00, 310.00, 3040.00, 'Completed', '2026-10-23 16:30:00', '2026-10-23 19:48:09'),
(5, 'ORD-2026-1005', 10, 'delivery', '31 Stanley Thilakarathne Mw, Nugegoda', '0773344112', NULL, 'card', 2650.00, 250.00, 265.00, 2635.00, 'Out for Delivery', '2026-10-23 17:15:00', '2026-10-23 19:48:09'),
(6, 'ORD-2026-1006', 11, 'pickup', 'Pickup counter', '0729988776', NULL, 'card', 1850.00, 0.00, 0.00, 1850.00, 'Collected', '2026-10-23 15:45:00', '2026-10-23 19:48:09'),
(7, 'ORD-2026-1007', 12, 'delivery', '102 Ward Place, Colombo 07', '0765544332', NULL, 'cod', 4200.00, 250.00, 500.00, 3950.00, 'Completed', '2026-10-22 20:10:00', '2026-10-23 19:48:09'),
(8, 'ORD-2026-1008', 13, 'delivery', '21 Galle Face Terrace, Colombo 03', '0716677889', NULL, 'card', 1650.00, 250.00, 0.00, 1900.00, 'Completed', '2026-10-22 19:30:00', '2026-10-23 19:48:09'),
(9, 'ORD-2026-1009', 14, 'delivery', '67 Kottawa Road, Pannipitiya', '0778877665', NULL, 'bank', 3300.00, 250.00, 330.00, 3220.00, 'Completed', '2026-10-22 13:20:00', '2026-10-23 19:48:09'),
(10, 'ORD-2026-1010', 15, 'pickup', 'Pickup counter', '0781122998', NULL, 'cod', 950.00, 0.00, 0.00, 950.00, 'Completed', '2026-10-22 12:45:00', '2026-10-23 19:48:09'),
(11, 'ORD-2026-1011', 16, 'delivery', '88 Peradeniya Road, Kandy', '0703344990', NULL, 'card', 2700.00, 250.00, 200.00, 2750.00, 'Cancelled', '2026-10-21 21:00:00', '2026-10-23 19:48:09'),
(12, 'ORD-2026-1012', 17, 'delivery', '54 Baseline Road, Dematagoda', '0751239874', NULL, 'cod', 3800.00, 250.00, 380.00, 3670.00, 'Completed', '2026-10-21 19:40:00', '2026-10-23 19:48:09'),
(13, 'ORD-2026-1013', 18, 'delivery', '19 Old Kesbewa Road, Delkanda', '0724455112', NULL, 'bank', 2150.00, 250.00, 0.00, 2400.00, 'Completed', '2026-10-21 13:10:00', '2026-10-23 19:48:09'),
(14, 'ORD-2026-1014', 19, 'pickup', 'Pickup counter', '0768877443', NULL, 'cod', 1600.00, 0.00, 0.00, 1600.00, 'Completed', '2026-10-20 20:30:00', '2026-10-23 19:48:09'),
(15, 'ORD-2026-1015', 20, 'delivery', '62 Wackwella Road, Galle', '0774433221', NULL, 'card', 4500.00, 250.00, 500.00, 4250.00, 'Completed', '2026-10-20 18:20:00', '2026-10-23 19:48:09'),
(16, 'ORD-2026-1016', 6, 'delivery', '24 Havelock Road, Colombo 05', '0775566778', NULL, 'card', 2900.00, 250.00, 250.00, 2900.00, 'Completed', '2026-10-19 19:15:00', '2026-10-23 19:48:09'),
(17, 'ORD-2026-1017', 7, 'delivery', '78 Rajagiriya Road, Rajagiriya', '0714455667', NULL, 'cod', 1450.00, 250.00, 0.00, 1700.00, 'Completed', '2026-10-19 13:00:00', '2026-10-23 19:48:09'),
(18, 'ORD-2026-1018', 8, 'pickup', 'Pickup counter', '0702233445', NULL, 'card', 2250.00, 0.00, 200.00, 2050.00, 'Completed', '2026-10-18 20:00:00', '2026-10-23 19:48:09'),
(19, 'ORD-2026-1019', 9, 'delivery', '90 Nawala Road, Nugegoda', '0758899001', NULL, 'bank', 3700.00, 250.00, 370.00, 3580.00, 'Completed', '2026-10-18 12:40:00', '2026-10-23 19:48:09'),
(20, 'ORD-2026-1020', 10, 'delivery', '31 Stanley Thilakarathne Mw, Nugegoda', '0773344112', NULL, 'cod', 2100.00, 250.00, 0.00, 2350.00, 'Completed', '2026-10-17 19:10:00', '2026-10-23 19:48:09'),
(25, 'ORD-20261023-8525B1', 22, 'delivery', 'Kotiyakumbura, 71325, Sri Lanka', '+94769776315', '', 'cod', 2150.00, 250.00, 0.00, 2400.00, 'Completed', '2026-10-23 20:35:47', '2026-10-23 20:51:41'),
(26, 'ORD-20261023-94ED34', 22, 'delivery', 'Kotiyakumbura, 71325, Sri Lanka', '+94769776315', '', 'cod', 1850.00, 250.00, 0.00, 2100.00, 'Completed', '2026-10-23 21:19:32', '2026-10-23 21:29:02'),
(27, 'ORD-20261023-DDFCFC', 22, 'delivery', 'Kotiyakumbura, 71325, Sri Lanka', '+94769776315', '', 'cod', 2150.00, 250.00, 0.00, 2400.00, 'Completed', '2026-10-23 21:27:48', '2026-10-23 21:29:08'),
(28, 'ORD-20261023-80215D', 22, 'delivery', 'Kotiyakumbura, 71325, Sri Lanka', '+94769776315', '', 'card', 3200.00, 250.00, 0.00, 3450.00, 'Completed', '2026-10-23 21:32:39', '2026-10-23 21:35:32'),
(29, 'ORD-20261023-B9DC89', 22, 'delivery', 'Kotiyakumbura, 71325, Sri Lanka', '+94769776315', '', 'cod', 800.00, 250.00, 0.00, 1050.00, 'Completed', '2026-10-23 21:46:44', '2026-10-27 09:04:33'),
(30, 'ORD-20261027-E97EA8', 22, 'delivery', 'Lake Road, මාළබේ, 10115, Sri Lanka', '0769776315', '', 'cod', 1450.00, 250.00, 0.00, 1700.00, 'Placed', '2026-10-27 09:14:57', '2026-10-27 09:14:57');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `dish_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `price_at_purchase` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

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
(33, 20, 22, 1, 250.00),
(38, 25, 2, 1, 2150.00),
(39, 26, 3, 1, 1850.00),
(40, 27, 2, 1, 2150.00),
(41, 28, 10, 2, 1600.00),
(42, 29, 23, 2, 400.00),
(43, 30, 1, 1, 1450.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(50) NOT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_history`
--

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
(47, 20, 'Completed', 1, '2026-10-17 20:00:00'),
(48, 25, 'Placed', 22, '2026-10-23 20:35:47'),
(49, 25, 'Preparing', 1, '2026-10-23 20:48:25'),
(50, 25, 'Ready', 1, '2026-10-23 20:49:02'),
(51, 25, 'Out for Delivery', 1, '2026-10-23 20:49:07'),
(52, 25, 'Completed', 1, '2026-10-23 20:51:41'),
(53, 2, 'Ready', 1, '2026-10-23 21:01:26'),
(54, 1, 'Preparing', 1, '2026-10-23 21:02:08'),
(55, 1, 'Ready', 1, '2026-10-23 21:04:29'),
(56, 1, 'Out for Delivery', 1, '2026-10-23 21:18:45'),
(57, 26, 'Placed', 22, '2026-10-23 21:19:32'),
(58, 26, 'Preparing', 1, '2026-10-23 21:20:05'),
(59, 26, 'Ready', 1, '2026-10-23 21:20:13'),
(60, 26, 'Out for Delivery', 1, '2026-10-23 21:20:20'),
(61, 27, 'Placed', 22, '2026-10-23 21:27:48'),
(62, 27, 'Preparing', 1, '2026-10-23 21:28:04'),
(63, 27, 'Ready', 1, '2026-10-23 21:28:06'),
(64, 27, 'Out for Delivery', 1, '2026-10-23 21:28:11'),
(65, 26, 'Completed', 1, '2026-10-23 21:29:02'),
(66, 27, 'Completed', 1, '2026-10-23 21:29:08'),
(67, 28, 'Placed', 22, '2026-10-23 21:32:39'),
(68, 28, 'Preparing', 1, '2026-10-23 21:35:15'),
(69, 28, 'Ready', 1, '2026-10-23 21:35:19'),
(70, 28, 'Out for Delivery', 1, '2026-10-23 21:35:24'),
(71, 28, 'Completed', 1, '2026-10-23 21:35:32'),
(72, 29, 'Placed', 22, '2026-10-23 21:46:44'),
(73, 29, 'Preparing', 1, '2026-10-23 21:47:08'),
(74, 29, 'Ready', 1, '2026-10-23 21:47:11'),
(75, 29, 'Out for Delivery', 1, '2026-10-23 21:47:14'),
(76, 29, 'Completed', 1, '2026-10-27 09:04:33'),
(77, 30, 'Placed', 22, '2026-10-27 09:14:57');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `is_used`, `created_at`) VALUES
(1, 22, 'eb7ebd353dfc865c23d53dac7b718f00f5ebd7039a32f3b905a44bc3cb7d845b', '2026-10-25 11:49:49', 1, '2026-10-25 15:19:49'),
(2, 22, 'cdf74353171df3a3975381650a762da1adb006305207df052de8b4f2602fbc3a', '2026-10-25 12:09:41', 0, '2026-10-25 15:39:41');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cod','card','bank') NOT NULL DEFAULT 'cod',
  `payment_status` enum('Pending','Completed','Failed','Refunded') NOT NULL DEFAULT 'Completed',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

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
(20, 20, 2350.00, 'cod', 'Completed', 'TXN-20261020-0AB4', '2026-10-17 19:10:00', '2026-10-17 19:10:00'),
(21, 25, 2400.00, 'cod', 'Pending', 'TXN-20261023-BCBA81A3', '2026-10-23 20:35:47', '2026-10-23 20:35:47'),
(22, 26, 2100.00, 'cod', 'Pending', 'TXN-20261023-45536662', '2026-10-23 21:19:32', '2026-10-23 21:19:32'),
(23, 27, 2400.00, 'cod', 'Pending', 'TXN-20261023-EDB27D50', '2026-10-23 21:27:48', '2026-10-23 21:27:48'),
(24, 28, 3450.00, 'card', 'Completed', 'TXN-20261023-8A19EDD7', '2026-10-23 21:32:39', '2026-10-23 21:32:39'),
(25, 29, 1050.00, 'cod', 'Pending', 'TXN-20261023-2369F8D6', '2026-10-23 21:46:44', '2026-10-23 21:46:44'),
(26, 30, 1700.00, 'cod', 'Pending', 'TXN-20261027-C926423D', '2026-10-27 09:14:57', '2026-10-27 09:14:57');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `created_at`) VALUES
(1, 'admin', 'System administrator with user governance and reporting access', '2026-10-23 19:48:09'),
(2, 'kitchen_staff', 'Kitchen staff managing catalogue, dish stock, and order fulfillment', '2026-10-23 19:48:09'),
(3, 'customer', 'Registered patron placing online delivery and pickup orders', '2026-10-23 19:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(10) UNSIGNED NOT NULL,
  `dish_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `movement_type` enum('restock','wastage','order_deduction','adjustment') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `dish_id`, `user_id`, `movement_type`, `quantity_change`, `previous_quantity`, `new_quantity`, `reason`, `created_at`) VALUES
(1, 1, 3, 'restock', 40, 0, 40, 'Morning kitchen prep: batch of chopped chicken kottu portions', '2026-10-22 08:00:00'),
(2, 1, 3, 'order_deduction', -5, 40, 35, 'Dispatched catering lunch order #1002', '2026-10-22 12:15:00'),
(3, 2, 4, 'restock', 20, 0, 20, 'Prepared fresh seafood portions and shredded godamba', '2026-10-22 08:15:00'),
(4, 2, 4, 'wastage', -2, 20, 18, 'Cheese sauce container dropped and contaminated in prep line', '2026-10-22 10:45:00'),
(5, 3, 3, 'adjustment', -4, 10, 6, 'End of lunch inventory count verification', '2026-10-21 16:00:00'),
(6, 4, 4, 'restock', 25, 0, 25, 'Daily fresh vegetable and seasoned paneer prep', '2026-10-22 08:30:00'),
(7, 4, 4, 'order_deduction', -3, 25, 22, 'Order fulfillment #1004', '2026-10-22 12:40:00'),
(8, 5, 3, 'restock', 15, 0, 15, 'Wrapped authentic banana leaf lamprais packets', '2026-10-21 10:00:00'),
(9, 5, 3, 'order_deduction', -11, 15, 4, 'Heavy dinner rush orders', '2026-10-21 21:30:00'),
(10, 6, 3, 'restock', 30, 0, 30, 'Morning Biryani Handi cooking finished (Chicken Dum)', '2026-10-22 09:00:00'),
(11, 6, 3, 'order_deduction', -2, 30, 28, 'Lunch delivery pickup #1008', '2026-10-22 12:30:00'),
(12, 7, 3, 'order_deduction', -8, 8, 0, 'Sold out during yesterday dinner shift - pending fresh meat shipment', '2026-10-20 22:00:00'),
(13, 8, 4, 'restock', 20, 0, 20, 'Prepped wok ingredients with fresh calamari & prawns', '2026-10-22 09:00:00'),
(14, 8, 4, 'order_deduction', -6, 20, 14, 'Customer orders lunch rush #1012', '2026-10-22 13:00:00'),
(15, 9, 4, 'wastage', -3, 3, 0, 'Fresh mud crabs sold out and remaining stock spoiled', '2026-10-21 21:00:00'),
(16, 10, 3, 'restock', 20, 0, 20, 'Claypot fish ambul thiyal slow cooked and cooled', '2026-10-22 08:30:00'),
(17, 10, 3, 'order_deduction', -4, 20, 16, 'Dispatched customer orders #1015', '2026-10-22 12:50:00'),
(18, 11, 4, 'restock', 25, 0, 25, 'Boiled raw cashews and prepped yellow coconut curry', '2026-10-22 08:30:00'),
(19, 11, 4, 'order_deduction', -5, 25, 20, 'Vegetarian orders batch #1018', '2026-10-22 13:10:00'),
(20, 12, 3, 'order_deduction', -10, 15, 5, 'Pork curry lunch orders - near reorder point', '2026-10-21 15:30:00'),
(21, 13, 4, 'restock', 30, 0, 30, 'Sliced calamari rings battered and prepped for fry station', '2026-10-22 09:30:00'),
(22, 13, 4, 'order_deduction', -5, 30, 25, 'Appetizer orders dispatched', '2026-10-22 13:15:00'),
(23, 14, 3, 'restock', 50, 0, 50, 'Griddled 50 village coconut rotis for breakfast & lunch', '2026-10-22 07:00:00'),
(24, 14, 3, 'order_deduction', -10, 50, 40, 'Breakfast orders completed', '2026-10-22 10:00:00'),
(25, 15, 4, 'restock', 35, 0, 35, 'Rolled and crumbed 35 spicy mutton pan rolls', '2026-10-22 07:30:00'),
(26, 15, 4, 'wastage', -5, 35, 30, 'Damaged during flash frying', '2026-10-22 09:00:00'),
(27, 16, 3, 'order_deduction', -12, 20, 8, 'Hoppers station busy morning orders', '2026-10-22 10:30:00'),
(28, 17, 4, 'restock', 25, 0, 25, 'Chilled fresh steamed kithul jaggery watalappan bowls', '2026-10-22 08:00:00'),
(29, 18, 4, 'restock', 16, 0, 16, 'Chilled caramel biscuit pudding portions in dessert fridge', '2026-10-22 08:00:00'),
(30, 19, 3, 'restock', 20, 0, 20, 'Received fresh southern buffalo curd pots', '2026-10-22 08:00:00'),
(31, 20, 4, 'wastage', -2, 5, 3, 'Fruit glaze melted due to dessert chiller door left ajar', '2026-10-21 17:00:00'),
(32, 21, 4, 'restock', 35, 0, 35, 'Prepped faluda syrup, basil seeds, and chilled milk base', '2026-10-22 08:00:00'),
(33, 22, 3, 'restock', 50, 0, 50, 'Brewed large thermos of spiced Ceylon cardamom tea', '2026-10-22 07:00:00'),
(35, 2, 22, 'order_deduction', -1, 18, 17, 'Order #ORD-20261023-8525B1', '2026-10-23 20:35:47'),
(36, 3, 22, 'order_deduction', -1, 6, 5, 'Order #ORD-20261023-94ED34', '2026-10-23 21:19:32'),
(37, 2, 22, 'order_deduction', -1, 17, 16, 'Order #ORD-20261023-DDFCFC', '2026-10-23 21:27:48'),
(38, 10, 22, 'order_deduction', -2, 16, 14, 'Order #ORD-20261023-80215D', '2026-10-23 21:32:39'),
(39, 23, 22, 'order_deduction', -2, 25, 23, 'Order #ORD-20261023-B9DC89', '2026-10-23 21:46:44'),
(40, 1, 22, 'order_deduction', -1, 35, 34, 'Order #ORD-20261027-E97EA8', '2026-10-27 09:14:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `username`, `email`, `phone`, `address`, `password_hash`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Deshan Siriwardhana', 'admin', 'admin@urbaneats.lk', '0771234560', '12/4 Flower Road, Colombo 07', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 21:09:38'),
(2, 1, 'Nimesh Perera', 'nimesh_admin', 'nimesh@urbaneats.lk', '0712345671', '45 Galle Road, Colombo 03', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(3, 2, 'Kasun Bandara', 'chef_kasun', 'kasun@urbaneats.lk', '0779876540', '108 Kandy Road, Kiribathgoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(4, 2, 'Dilan Silva', 'chef_dilan', 'dilan@urbaneats.lk', '0761122334', '56 Station Road, Dehiwala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(5, 2, 'Mahesh Kumara', 'mahesh_prep', 'mahesh@urbaneats.lk', '0783344556', '89 High Level Road, Maharagama', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(6, 3, 'Anuki Fernando', 'anuki99', 'anuki.f@gmail.com', '0775566778', '24 Havelock Road, Colombo 05', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(7, 3, 'Kavindu Jayasuriya', 'kavindu_j', 'kavindu.j@outlook.com', '0714455667', '78 Rajagiriya Road, Rajagiriya', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(8, 3, 'Tharushi Wijesinghe', 'tharushi_w', 'tharushi.w@gmail.com', '0702233445', '15 Bauddhaloka Mawatha, Colombo 04', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(9, 3, 'Sahan Senanayake', 'sahan_s', 'sahan.s@yahoo.com', '0758899001', '90 Nawala Road, Nugegoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(10, 3, 'Pooja Mendis', 'pooja_m', 'pooja.mendis@gmail.com', '0773344112', '31 Stanley Thilakarathne Mawatha, Nugegoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(11, 3, 'Dinuka Gunasekara', 'dinuka_g', 'dinuka.guna@gmail.com', '0729988776', '44 Hill Street, Dehiwala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(12, 3, 'Shenal Cooray', 'shenal_c', 'shenal.c@gmail.com', '0765544332', '102 Ward Place, Colombo 07', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(13, 3, 'Rashmi Alwis', 'rashmi_a', 'rashmi.alwis@gmail.com', '0716677889', '21 Galle Face Terrace, Colombo 03', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(14, 3, 'Nuwan Liyanage', 'nuwan_l', 'nuwan.liyanage@gmail.com', '0778877665', '67 Kottawa Road, Pannipitiya', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(15, 3, 'Hiruni Rathnayake', 'hiruni_r', 'hiruni.rath@gmail.com', '0781122998', '14 Negombo Road, Wattala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(16, 3, 'Isuru Jayawardena', 'isuru_j', 'isuru.jaya@hotmail.com', '0703344990', '88 Peradeniya Road, Kandy', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(17, 3, 'Malsha Fonseka', 'malsha_f', 'malsha.fonseka@gmail.com', '0751239874', '54 Baseline Road, Dematagoda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(18, 3, 'Chamath Karunaratne', 'chamath_k', 'chamath.k@gmail.com', '0724455112', '19 Old Kesbewa Road, Delkanda', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(19, 3, 'Sanduni Abeykoon', 'sanduni_a', 'sanduni.abey@gmail.com', '0768877443', '37 Dutugemunu Street, Kohuwala', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(20, 3, 'Bhanuka Rajapaksha', 'bhanuka_r', 'bhanuka.r@gmail.com', '0774433221', '62 Wackwella Road, Galle', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(21, 3, 'Hansika Jayakody', 'hansika_j', 'hansika.j@gmail.com', '0715566114', '73 Templers Road, Mount Lavinia', '$2y$12$/CBY6Vg.rrsarUEL8nlYoOYBee8AajiU3oYUADd0bEFvV6zos7zv6', 1, '2026-10-23 19:48:09', '2026-10-23 19:48:09'),
(22, 3, 'Deshan Siriwardhana', 'Deshan2004', 'deshandhakshitha16@gmail.com', '+94769776315', 'Millenium Drive, මාළබේ, 10115, Sri Lanka', '$2y$12$u9Xs35zlcUS.xIVE.juv2u/igOLE5pCLxkkiU9EwqpWC1N534gpi2', 1, '2026-10-23 20:10:44', '2026-10-27 09:14:06'),
(23, 3, 'chanula wije', 'chanula2003', 'chanula123@gmail.com', '+94352289413', 'Kotiyakumbura, 71325, Sri Lanka', '$2y$12$yh7VEVovU2gI6ZJBf435GeDbTNWyJcjsgx0opCsMHZ1tkqaqCZ2vm', 1, '2026-10-25 14:27:37', '2026-10-25 14:27:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user_id` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_created_at` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_categories_name` (`name`),
  ADD UNIQUE KEY `uq_categories_slug` (`slug`),
  ADD KEY `idx_categories_is_active` (`is_active`),
  ADD KEY `idx_categories_display_order` (`display_order`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`coupon_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `dishes`
--
ALTER TABLE `dishes`
  ADD PRIMARY KEY (`dish_id`),
  ADD UNIQUE KEY `uq_dishes_slug` (`slug`),
  ADD KEY `idx_dishes_category_id` (`category_id`),
  ADD KEY `idx_dishes_price` (`price`),
  ADD KEY `idx_dishes_is_active` (`is_active`),
  ADD KEY `idx_dishes_name` (`name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_dish_id` (`dish_id`),
  ADD KEY `idx_inventory_quantity` (`quantity`),
  ADD KEY `idx_inventory_reorder_level` (`reorder_level`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_username_time` (`username`,`attempted_at`),
  ADD KEY `idx_login_attempts_ip_time` (`ip_address`,`attempted_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `fk_orders_user` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_items_order` (`order_id`),
  ADD KEY `fk_items_dish` (`dish_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_history_order` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resets_token_hash` (`token_hash`),
  ADD KEY `idx_resets_user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_payments_order_id` (`order_id`),
  ADD KEY `idx_payments_status` (`payment_status`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`role_name`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_movements_dish_id` (`dish_id`),
  ADD KEY `idx_stock_movements_user_id` (`user_id`),
  ADD KEY `idx_stock_movements_type` (`movement_type`),
  ADD KEY `idx_stock_movements_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role_id` (`role_id`),
  ADD KEY `idx_users_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `coupon_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `dishes`
--
ALTER TABLE `dishes`
  MODIFY `dish_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `dishes`
--
ALTER TABLE `dishes`
  ADD CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`dish_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`dish_id`),
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_movements_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`dish_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
