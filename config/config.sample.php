<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Database Configuration Sample Template
 * 
 * Instructions:
 * Duplicate this file as 'db.php' in the same directory and configure
 * your local MySQL credentials. Note that 'db.php' is git-ignored.
 */

declare(strict_types=1);

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'urbaneats_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("[Database Connection Error] " . $e->getMessage());
    die("Database connection failed. Please ensure MySQL is running in XAMPP/WAMP and database 'urbaneats_db' has been imported.");
}
