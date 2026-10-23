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
                            <a href="<?= e(url('admin/reports.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'admin/reports') ? 'active' : ''; ?>" 
                               id="nav-admin-reports">Reports</a>
                        </li>
                        <li>
                            <a href="<?= e(url('orders/kitchen_queue.php')); ?>" 
                               class="nav-link <?= str_contains($currentScript, 'kitchen_queue') ? 'active' : ''; ?>" 
                               id="nav-admin-queue">Order Queue</a>
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
                        <li style="position: relative;">
                            <button type="button" id="ue-user-bell-btn" class="nav-link" style="background: none; border: none; cursor: pointer; position: relative; padding: 0.35rem 0.55rem; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: var(--ink-700); line-height: 1;" title="Order Notifications">
                                <span>🔔</span>
                                <span id="ue-user-bell-badge" style="display: none; position: absolute; top: 1px; right: 2px; width: 9px; height: 9px; border-radius: 50%; background: #EF4444; border: 2px solid #FFFFFF; box-shadow: 0 0 6px rgba(239, 68, 68, 0.6);"></span>
                            </button>
                            <!-- Live Order Notifications Dropdown -->
                            <div id="ue-user-bell-dropdown" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 10px; width: 340px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-default); border-radius: 14px; box-shadow: 0 16px 36px rgba(0,0,0,0.12); z-index: 999999; padding: 0.85rem 0; font-family: var(--font-family);">
                                <div style="padding: 0 1rem 0.6rem; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="font-size: 0.9rem; color: var(--ink-900);">Order Notifications</strong>
                                    <span style="font-size: 0.72rem; color: #059669; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; background: rgba(16,185,129,0.1); padding: 0.15rem 0.5rem; border-radius: 9999px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10B981;"></span> Live Updates
                                    </span>
                                </div>
                                <div id="ue-user-notifications-list" style="max-height: 280px; overflow-y: auto; padding: 0.4rem 0;">
                                    <div style="padding: 1.5rem 1rem; text-align: center; color: var(--ink-500); font-size: 0.82rem;">
                                        Loading order updates...
                                    </div>
                                </div>
                                <div style="padding: 0.6rem 1rem; border-top: 1px solid var(--border-default); background: #FFF8F5; display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;">
                                    <a href="tel:+94117509000" style="font-size: 0.75rem; font-weight: 800; color: #DC2626; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Call Kitchen Helpline Immediately">
                                        <span>🚨</span> Not received? Call Desk
                                    </a>
                                    <a href="https://wa.me/94771234567?text=Hi%20UrbanEats%20Kitchen,%20my%20order%20status%20has%20an%20issue." target="_blank" style="font-size: 0.75rem; font-weight: 700; color: #059669; text-decoration: none; display: inline-flex; align-items: center; gap: 3px;">
                                        <span>💬</span> WhatsApp
                                    </a>
                                </div>
                            </div>
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
                        <a href="<?= e(url('orders/cart.php')); ?>" class="nav-link <?= str_contains($currentScript, 'cart.php') ? 'active' : ''; ?>" id="nav-guest-cart">
                            Cart (<?= e((string)(count($_SESSION['cart'] ?? []))); ?>)
                        </a>
                    </li>
                    <li>
                        <a href="<?= e(url('orders/tracking.php')); ?>" class="nav-link <?= str_contains($currentScript, 'tracking') ? 'active' : ''; ?>" id="nav-guest-track">
                            Track Order
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
