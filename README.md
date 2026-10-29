# UrbanEats — Cloud Kitchen Web Platform

A complete, database-driven cloud kitchen web application built for **IT3113: Advanced Web Technologies** (Year 3, Semester 1). The system manages end-to-end cloud kitchen operations: user authentication, menu catalog and stock control, customer cart and checkout, kitchen fulfillment queues, and administrative sales reporting.

**Technology Stack:** Native PHP 8.x, MySQL / MariaDB (PDO with Prepared Statements), HTML5, Vanilla CSS3 (No CSS/PHP frameworks).

---

## 👥 Team Members & Component Allocation

| Student Name | Student ID | Component | Allocated Scope |
| :--- | :--- | :--- | :--- |
| **Deshan Siriwardhana** | `IT_UGC_001/B005-B/0009` | **Component A** | Identity, Access Control & System Administration |
| **Chanula Wijerathna** | `IT_UGC_001/B005-A/0012` | **Component B** | Menu, Catalogue & Inventory Control |
| **Thilakshi Nethmini** | `IT_UGC_001/B005-B/0001` | **Component C** | Ordering, Payments, Workflow & Sales Analytics |

* **Institution:** CINEC Campus
* **Module Coordinator:** Ms. Nethmi Mohotti
* **Submission Date:** 28th October 2026

---

## ⚙️ Setup & Installation Instructions

Follow these steps to set up and run the project locally using XAMPP (or WAMP/LAMP).

### 1. Prerequisites
* **Web Server & PHP:** PHP 8.0 or higher.
* **Database:** MySQL 5.7+ or MariaDB 10.4+.
* **Environment:** XAMPP / WAMP / LAMP installed.

### 2. Project Placement
Clone or extract the project folder into your web server's root directory:
* **XAMPP (Windows):** `C:/xampp/htdocs/urbaneats`
* **XAMPP (macOS):** `/Applications/XAMPP/xamppfiles/htdocs/urbaneats`

### 3. Database Import
1. Start **Apache** and **MySQL** from your XAMPP Control Panel.
2. Open **phpMyAdmin** in your browser (`http://localhost/phpmyadmin`).
3. Click on **Import** in the top navigation.
4. Select the file: `database/urbaneats_db.sql`.
5. Click **Import** (or **Go**).
   > *Note:* The SQL script will automatically create the `urbaneats_db` database, tables, relationships, and populate seed data (20+ realistic rows per main table).

### 4. Database Configuration
The database connection configuration is located at `config/db.php`.  
If running on default local XAMPP settings, no modification is needed:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'urbaneats_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Leave empty for default XAMPP, or enter your MySQL password
```

### 5. Running the Application
* **Via Apache:** Open your browser and navigate to:
  ```text
  http://localhost/urbaneats/
  ```
  *(or `http://localhost/urbaneats/public/` depending on your virtual host root)*
* **Via PHP Built-in Server (Alternative):**
  Open terminal inside the project root and run:
  ```bash
  php -S localhost:8000
  ```
  Then visit: `http://localhost:8000`

---

## 🔑 System User Roles & Access

The platform supports three distinct user roles governed by centralized Role-Based Access Control (RBAC):

| Role | Access Scope & Operational Capabilities |
| :--- | :--- |
| **Administrator** | Full system control, user role modification, account activation/deactivation, audit trail inspection, and executive sales reports. |
| **Kitchen Staff** | Live kitchen order queue fulfillment, menu catalog CRUD, stock adjustment logging, and inventory monitoring. |
| **Customer** | Menu browsing, session-based cart management, coupon checkout, real-time order tracking, and printable receipts. |

> **Note:** Initial seed accounts for development and evaluation are populated via `database/urbaneats_db.sql`. New customer accounts can also be created anytime through the public registration interface (`/auth/register.php`).

---

## 🚀 List of Features per Component

### Component A: Identity, Access & Administration (Deshan Siriwardhana)
* **Registration & Secure Login:** User registration and authentication with BCRYPT password hashing (`password_hash()` and `password_verify()`).
* **Session Hardening:** Session regeneration (`session_regenerate_id()`) on login to prevent session fixation, strict session timeouts, and secure session destruction on logout.
* **Role-Based Access Control (RBAC):** Centralized reusable guard functions (`require_login()` and `require_role()`) protecting all privileged pages with custom 403 Access Denied handling.
* **Brute-Force Login Throttling:** Automatic temporary account lockout after 5 consecutive failed login attempts (`login_attempts` table).
* **Password Reset Workflow:** Secure, time-limited token generation for password recovery.
* **Centralized System Audit Log:** Reusable `log_action()` function recording critical security, catalog, and ordering events into `audit_log`.
* **Administrator User Management:** Search, filter by role, activate/deactivate accounts, and change user roles from the admin panel.

### Component B: Menu, Catalogue & Inventory (Chanula Wijerathna)
* **Public Menu Catalog:** Responsive menu with category filters, keyword search, price and name sorting, and server-side pagination with whitelisted query parameters.
* **Dish Detail View:** Single dish page showing dish images, descriptions, live availability, and prices in LKR.
* **Staff Dish & Category CRUD:** Forms to add, edit, and soft-delete dishes (`is_active` flag) and manage food categories.
* **Secure Image Uploads:** Multi-point server validation (MIME-type binary inspection, file extension whitelist, file size limit) and cryptographically randomized filenames stored outside execution paths.
* **Real-Time Stock Monitoring:** Live inventory dashboard with visual alerts for low-stock and out-of-stock items.
* **Stock Adjustments & Movement Audit:** Logging restock and wastage events with reasons and staff user tracking.
* **Transaction-Safe Stock Reduction:** `reduce_stock($dish_id, $qty)` function using conditional database updates to eliminate race conditions and prevent negative stock.

### Component C: Ordering, Payment & Tracking (Thilakshi Nethmini)
* **Session-Based Cart:** Add, update quantity, remove dishes, and calculate live subtotal and delivery charges.
* **Price Re-Verification at Checkout:** Server-side validation of dish prices and availability directly from the database at checkout (prices from browser are never trusted).
* **Atomic Transactional Checkout:** Single ACID MySQL transaction executing order creation, historical purchase price snapshot in `order_items`, stock deduction, coupon redemption, and simulated payment recording—with full automatic rollback on any failure.
* **Order Workflow State Machine:** Strict enforcement of valid order lifecycle stages:  
  `Placed` ➔ `Preparing` ➔ `Ready` ➔ `Out for Delivery` / `Collected` ➔ `Completed` (or `Cancelled`).
* **Order Tracking & Confirmation:** Visual real-time order status timeline for customers and printable order invoice/receipt.
* **Kitchen Order Queue:** Dedicated live console for kitchen staff to transition orders through their fulfillment stages.
* **Sales & Business Analytics:** Administrative reporting with customizable date range filters, total revenue, orders by status, and top-selling dishes using SQL aggregates (`JOIN`, `GROUP BY`, `SUM`, `COUNT`).
