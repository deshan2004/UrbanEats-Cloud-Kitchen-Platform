<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Landing Portal (Component A)
 * 
 * Provides an entry portal for the three platform user personas:
 * Customer, Kitchen Staff, and Administrator.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$currentUser = current_user();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner (Figma warm theme) -->
<div style="background-color: var(--primary-500); border-radius: var(--radius-xl); padding: 3.5rem 3rem; color: #FFFFFF; margin-bottom: 2.5rem; text-align: center;">
    <div style="max-width: 760px; margin: 0 auto;">
        <span style="background: rgba(255, 255, 255, 0.2); color: #FFFFFF; font-size: 0.82rem; font-weight: 700; padding: 0.35rem 0.95rem; border-radius: var(--radius-full); text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 1.25rem;">
            UrbanEats &bull; Cloud Kitchen Platform
        </span>
        <h1 style="font-size: 2.75rem; font-weight: 800; color: #FFFFFF; margin-bottom: 1rem; line-height: 1.15; letter-spacing: -0.02em;">
            Fresh food. Fast.<br>Made to order.
        </h1>
        <p style="font-size: 1.15rem; color: rgba(255, 255, 255, 0.95); line-height: 1.6; margin-bottom: 2rem;">
            Order from our cloud kitchen, follow your meal in real time and pay your way: cash on delivery, card or bank transfer.
        </p>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <?php if (!$currentUser): ?>
                <a href="<?= e(url('auth/login.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem; border-radius: var(--radius-md);">
                    Sign In &rarr;
                </a>
                <a href="<?= e(url('auth/register.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem; border-radius: var(--radius-md);">
                    Create Account
                </a>
            <?php else: ?>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/users.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Go to User Management &rarr;
                    </a>
                    <a href="<?= e(url('admin/dashboard.php')); ?>" class="btn" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.4); color: #FFFFFF; font-weight: 600; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Admin Dashboard
                    </a>
                <?php elseif ($currentUser['role'] === 'kitchen_staff'): ?>
                    <a href="<?= e(url('auth/profile.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        Kitchen Staff Account &rarr;
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('auth/profile.php')); ?>" class="btn" style="background: #FFFFFF; color: var(--primary-500); font-weight: 700; font-size: 1.05rem; padding: 0.85rem 2rem;">
                        My Account Settings &rarr;
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Three Platform Roles Overview (Assignment Section 3 Case Study) -->
<div style="margin-bottom: 3rem;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--ink-900); margin-bottom: 0.4rem;">
            Unified Cloud Kitchen Ecosystem
        </h2>
        <p style="color: var(--ink-500); font-size: 1rem;">
            Designed and integrated across three specialized operational modules.
        </p>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
        
        <!-- Component A Card (Identity & Access) -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="background: var(--primary-50); color: var(--primary-600); font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase;">
                        Component A (Student 1)
                    </span>
                    <span style="font-size: 1.5rem;">🛡️</span>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">
                    Identity &amp; Administration
                </h3>
                <p style="font-size: 0.9rem; color: var(--ink-500); line-height: 1.5; margin-bottom: 1.25rem;">
                    Secure authentication with brute-force throttling, BCRYPT password hashing, session hardening, user governance, and audit trails.
                </p>
            </div>
            <div>
                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/users.php')); ?>" class="btn btn-primary btn-block btn-sm">
                        Open User Governance &rarr;
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('auth/login.php')); ?>" class="btn btn-secondary btn-block btn-sm">
                        Sign In as Admin &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Component B Card (Menu & Inventory - Student 2) -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="background: var(--warning-100); color: var(--warning-500); font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase;">
                        Component B (Student 2)
                    </span>
                    <span style="font-size: 1.5rem;">🍲</span>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">
                    Menu, Catalogue &amp; Stock
                </h3>
                <p style="font-size: 0.9rem; color: var(--ink-500); line-height: 1.5; margin-bottom: 1.25rem;">
                    Dishes catalogue, categories, image uploads, search/filtering, and real-time inventory adjustments with low-stock tracking.
                </p>
            </div>
            <div>
                <span class="btn btn-secondary btn-block btn-sm" style="opacity: 0.7; cursor: default; background: var(--bg-page); border-color: var(--border-default); color: var(--ink-500);">
                    Assigned to Student 2
                </span>
            </div>
        </div>

        <!-- Component C Card (Ordering & Tracking - Student 3) -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="background: var(--info-100); color: var(--info-500); font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: var(--radius-full); text-transform: uppercase;">
                        Component C (Student 3)
                    </span>
                    <span style="font-size: 1.5rem;">🛒</span>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--ink-900); margin-bottom: 0.5rem;">
                    Ordering, Payment &amp; Tracking
                </h3>
                <p style="font-size: 0.9rem; color: var(--ink-500); line-height: 1.5; margin-bottom: 1.25rem;">
                    Transactional checkout, simulated multi-mode payments, kitchen order fulfillment queue, and analytics sales reports.
                </p>
            </div>
            <div>
                <span class="btn btn-secondary btn-block btn-sm" style="opacity: 0.7; cursor: default; background: var(--bg-page); border-color: var(--border-default); color: var(--ink-500);">
                    Assigned to Student 3
                </span>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
