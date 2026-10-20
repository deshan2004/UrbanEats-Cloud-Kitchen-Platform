<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Global Header & Navigation (Component A — Requirement 3)
 * 
 * Implements team-wide shared navigation dynamically adapting across:
 * - Guest (Public menu, Sign In, Sign Up)
 * - Customer (Menu, Cart count, My Orders, Order Tracking, Profile)
 * - Kitchen Staff (Order Queue, Dishes, Stock, Staff Avatar)
 * - Administrator (Users Governance, Metrics Dashboard, Audit Log, Admin Avatar)
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'UrbanEats Cloud Kitchen';
$flash = get_flash();

// Current script for active state highlighting
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';

// Helper for initials
$userInitials = 'UE';
if ($currentUser) {
    $parts = explode(' ', trim($currentUser['full_name'] ?: $currentUser['username']));
    if (count($parts) >= 2) {
        $userInitials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    } else {
        $userInitials = strtoupper(mb_substr($parts[0], 0, 2));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> | UrbanEats Cloud Kitchen</title>
    <link rel="icon" type="image/png" href="<?= e(url('public/images/favicon.png')); ?>">
    <link rel="stylesheet" href="<?= e(url('public/css/style.css')) . '?v=' . time(); ?>">
</head>
<body>

<header class="site-header">
    <div class="container header-container">
        <!-- Brand Logo with custom UrbanEats icon -->
        <a href="<?= e(url('index.php')); ?>" class="brand-logo" id="brand-logo-link">
            <img src="<?= e(url('public/images/logo.png')); ?>" alt="UrbanEats Logo" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover;">
            <span>UrbanEats</span>
        </a>

        <!-- Role-Aware Navigation Links -->
        <nav aria-label="Main Navigation">
            <ul class="nav-links">
                <?php if ($currentUser): ?>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <li>
                            <a href="<?= e(url('admin/users.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'admin/users') ? 'active' : ''; ?>" 
                               id="nav-admin-users">Users</a>
                        </li>
                        <li>
                            <a href="<?= e(url('admin/dashboard.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'admin/dashboard') ? 'active' : ''; ?>" 
                               id="nav-admin-dash">Dashboard</a>
                        </li>
                        <li>
                            <a href="<?= e(url('admin/audit_log.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'admin/audit_log') ? 'active' : ''; ?>" 
                               id="nav-admin-audit">Audit Log</a>
                        </li>
                        <li>
                            <div class="header-user-badge">
                                <span class="header-role-text">Administrator</span>
                                <a href="<?= e(url('auth/profile.php')); ?>" title="View Profile: <?= e($currentUser['full_name']); ?>">
                                    <span class="user-avatar-circle"><?= e($userInitials); ?></span>
                                </a>
                            </div>
                        </li>
                    <?php elseif ($currentUser['role'] === 'kitchen_staff'): ?>
                        <li>
                            <a href="<?= e(url('orders/kitchen_queue.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'kitchen_queue') ? 'active' : ''; ?>" 
                               id="nav-kitchen-queue">Order Queue</a>
                        </li>
                        <li>
                            <a href="<?= e(url('menu/index.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'menu') ? 'active' : ''; ?>" 
                               id="nav-dishes">Dishes</a>
                        </li>
                        <li>
                            <a href="<?= e(url('menu/stock.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'stock') ? 'active' : ''; ?>" 
                               id="nav-stock">Stock</a>
                        </li>
                        <li>
                            <div class="header-user-badge">
                                <span class="header-role-text">Kitchen Staff</span>
                                <a href="<?= e(url('auth/profile.php')); ?>" title="View Profile: <?= e($currentUser['full_name']); ?>">
                                    <span class="user-avatar-circle"><?= e($userInitials); ?></span>
                                </a>
                            </div>
                        </li>
                    <?php else: /* Customer */ ?>
                        <li>
                            <a href="<?= e(url('index.php')); ?>" 
                               class="nav-link <?= (str_contains($currentScript, 'index.php') && !str_contains($currentScript, 'admin')) ? 'active' : ''; ?>" 
                               id="nav-customer-menu">Menu</a>
                        </li>
                        <li>
                            <a href="<?= e(url('orders/history.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'history') ? 'active' : ''; ?>" 
                               id="nav-customer-orders">My Orders</a>
                        </li>
                        <li>
                            <a href="<?= e(url('orders/tracking.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'tracking') ? 'active' : ''; ?>" 
                               id="nav-customer-tracking">Track Order</a>
                        </li>
                        <li>
                            <a href="<?= e(url('orders/cart.php')); ?>" class="btn-cart-pill" id="nav-customer-cart">
                                Cart (<?= e((string)(count($_SESSION['cart'] ?? []))); ?>)
                            </a>
                        </li>
                        <li>
                            <a href="<?= e(url('auth/profile.php')); ?>" title="View Profile: <?= e($currentUser['full_name']); ?>">
                                <span class="user-avatar-circle"><?= e($userInitials); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a href="<?= e(url('auth/logout.php')); ?>" class="nav-link" style="color: var(--ink-500); font-size: 0.88rem;" id="nav-logout">
                            Sign out
                        </a>
                    </li>
                <?php else: /* Guest */ ?>
                    <li>
                        <a href="<?= e(url('index.php')); ?>" class="nav-link <?= str_contains($currentScript, 'index.php') ? 'active' : ''; ?>" id="nav-guest-menu">
                            Menu
                        </a>
                    </li>
                    <li>
                        <a href="<?= e(url('auth/login.php')); ?>" class="nav-link <?= str_contains($currentScript, 'login.php') ? 'active' : ''; ?>" id="nav-guest-login">
                            Sign In
                        </a>
                    </li>
                    <li>
                        <a href="<?= e(url('auth/register.php')); ?>" class="btn btn-primary btn-sm" id="nav-guest-register">
                            Create account
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content" id="main-content">
    <div class="container">
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']); ?>" role="alert" id="system-flash-message">
                <div><?= e($flash['message']); ?></div>
            </div>
        <?php endif; ?>
