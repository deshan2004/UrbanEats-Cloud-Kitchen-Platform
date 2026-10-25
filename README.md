# UrbanEats — Cloud Kitchen Web Platform

UrbanEats is a robust, modular, and database-driven cloud kitchen web platform designed and built for **IT3113: Advanced Web Technologies** (Year 3, Semester 1). The application handles end-to-end cloud kitchen operations—from public customer ordering and real-time order tracking to kitchen fulfillment queues, stock control, and administrative business intelligence.

Built strictly using **vanilla HTML5, CSS3, PHP 8.x, and MySQL/MariaDB (PDO)** without external web or CSS frameworks.

---

## 👥 Team & Component Allocation

| Team Member | Component | Focus Area |
| :--- | :--- | :--- |
| **Student 1** | **Component A** | Identity, Access Control & Administration |
| **Student 2** | **Component B** | Menu, Catalogue & Inventory Control |
| **Student 3** | **Component C** | Ordering, Payments, Workflow & Sales Analytics |

---

## 🚀 Key Features by Component

### Component A: Identity, Access & Administration
* **Authentication & Hardening:** Secure registration and login with `password_hash()` (BCRYPT) and `password_verify()`. Session fixation prevention via `session_regenerate_id()`.
* **Access Control:** Centralized role guard (`includes/auth.php`) enforcing permissions across `Customer`, `Kitchen Staff`, and `Admin`. Custom HTTP 403 access-denied handling.
* **Brute-Force Throttling:** Temporary account lockout after 5 consecutive failed login attempts tracked via `login_attempts`.
* **Password Recovery:** Time-limited, secure token-based password reset mechanism.
* **Audit Trail:** Reusable system logger `log_action($pdo, $user_id, $action, $details)` invoked across components for compliance and security tracking.
* **User Management:** Administrator panel to search, filter, activate/deactivate accounts, and adjust user roles.

### Component B: Menu, Catalogue & Inventory
* **Public Catalogue:** Dynamic menu browsing with category tabs, keyword search, price/name sorting, and server-side pagination (whitelisted query parameters).
* **Dish Presentation:** Dedicated dish detail view displaying live stock availability, descriptions, and pricing in LKR.
* **Staff Item Management:** Full CRUD suite for categories and dishes with soft-delete (`is_active` flag) preservation to prevent historical order corruption.
* **Secure Media Uploads:** Multi-layer file validation (MIME-type check, whitelisted extensions, file size limit) with randomized server-side renaming outside executable web paths.
* **Real-Time Inventory Engine:** Live stock monitoring with low-stock alerts and atomic deductions (`reduce_stock($dish_id, $qty)`) with database row-level locking. Full stock movement audit log (restock vs. wastage).

### Component C: Ordering, Payment & Tracking
* **Cart & Pricing Integrity:** Session-backed cart recalculating live item totals and dish availability against the database upon checkout (client-side price bypass mitigation).
* **Atomic Checkout:** ACID-compliant MySQL transaction encapsulating order record insertion, line-item insertion (`order_items` locking historical purchase price), simulated payment tracking, coupon consumption, and inventory deduction. Automatic rollback on failure.
* **Order Lifecycle Workflow:** Controlled multi-stage progression:  
  `Placed` ➔ `Preparing` ➔ `Ready` ➔ `Out for Delivery` / `Collected` ➔ `Completed` (or `Cancelled`).
* **Customer Self-Service:** Detailed order history, printable invoice view, and visual status timeline.
* **Kitchen & Operations Queue:** Live fulfillment console for kitchen staff to transition order states.
* **Executive Analytics:** Admin sales reporting featuring customizable date filters, gross revenue metrics, order status distribution, and top-selling dishes using SQL aggregates (`JOIN`, `GROUP BY`, `SUM`, `COUNT`).

---

## 🛠️ Technology Stack & Architecture Constraints

* **Back-End:** PHP 8.x (Strict Native Standard — No frameworks like Laravel, Symfony, or CodeIgniter)
* **Database:** MySQL / MariaDB via **PHP Data Objects (PDO)** with prepared statements and parameter binding
* **Front-End:** Semantic HTML5, Vanilla Hand-Written CSS3 with CSS custom properties (variables), responsive breakpoints (`360px`, `768px`, `1200px`), minimal Vanilla JavaScript for progressive enhancement
* **Security Layer:** Anti-CSRF token verification on all POST requests, `htmlspecialchars()` contextual output escaping, strict session timeouts, custom exception/error handler logging to `logs/error.log`

---

## 📂 Project Directory Structure

```text
urbaneats/
├── config/
│   ├── config.sample.php       # Template database credentials
│   └── db.php                  # PDO connection singleton (ignored by git)
├── includes/
│   ├── header.php              # Global navigation bar (role-aware)
│   ├── footer.php              # Global footer layout
│   ├── auth.php                # Authentication guards (require_login, require_role)
│   ├── csrf.php                # Token generation & verification helpers
│   ├── functions.php           # Shared utilities (formatting, stock & audit calls)
│   └── errors.php              # Custom global error & exception handlers
├── public/
│   ├── index.php               # Landing portal and public menu view
│   ├── css/
│   │   └── style.css           # Global responsive CSS stylesheet
│   └── uploads/                # Sanitized dish image uploads
├── auth/
│   ├── register.php            # Customer registration
│   ├── login.php               # System login with throttling
│   ├── logout.php              # Safe session destruction
│   ├── profile.php             # User profile update & password change
│   └── reset.php               # Token-based password recovery
├── admin/
│   ├── dashboard.php           # Admin control center
│   ├── users.php               # User role and status administration
│   ├── audit_log.php           # System-wide operational logs
│   └── reports.php             # Sales, revenue, and product analytics
├── menu/
│   ├── index.php               # Filterable, paginated catalogue
│   ├── detail.php              # Single dish view
│   ├── manage_dishes.php       # Staff dish CRUD & image management
│   ├── manage_categories.php   # Category CRUD
│   └── stock.php               # Inventory adjustments & movement records
├── orders/
│   ├── cart.php                # Shopping cart management
│   ├── checkout.php            # Transactional order placement
│   ├── confirmation.php        # Printable invoice / order receipt
│   ├── history.php             # Customer past order listing
│   ├── tracking.php            # Live order timeline tracker
│   └── kitchen_queue.php       # Kitchen staff order processing dashboard
├── database/
│   ├── urbaneats_db.sql        # Database schema, foreign keys & 20+ seed rows
│   └── generate_images.php     # Starter dish placeholder generator
├── logs/
│   └── error.log               # Server-side application error log
└── README.md

---

## ⚙️ Installation & Setup Instructions

1. **Prerequisites:**
   * PHP 8.1 or higher (native standard CLI/module).
   * MySQL / MariaDB (via XAMPP, WAMP, LAMP, or standalone).
   * Apache or PHP built-in web server.

2. **Database Import:**
   * Open phpMyAdmin (`http://localhost/phpmyadmin`) or MySQL CLI.
   * Import `database/urbaneats_db.sql`. This creates the `urbaneats_db` database, sets up all relational tables with indexes and constraints, and seeds 20+ realistic rows in each main table.

3. **Database Configuration:**
   * Ensure `config/db.php` exists. If configuring a custom local MySQL password, edit `config/db.php`:
     ```php
     define('DB_HOST', '127.0.0.1');
     define('DB_PORT', '3306');
     define('DB_NAME', 'urbaneats_db');
     define('DB_USER', 'root');
     define('DB_PASS', ''); // Set your MySQL password if any
     ```

4. **Running Locally:**
   * Start Apache and MySQL in XAMPP, or start PHP's built-in development server from the project root:
     ```bash
     php -S localhost:8000
     ```
   * Open your browser at `http://localhost:8000/`.

---

## 🔑 Test Login Credentials (Seed Accounts)

All pre-seeded test accounts use the password: `Password123!`

| Role | Username | Email | Access Scope |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@urbaneats.lk` | System governance, users, audit logs, reports |
| **Kitchen Staff** | `chef_kasun` | `kasun@urbaneats.lk` | Dishes CRUD, categories, stock adjustments, order fulfillment |
| **Kitchen Staff** | `chef_dilan` | `dilan@urbaneats.lk` | Menu catalogue management, kitchen order queue, inventory restock |
| **Customer** | `anuki99` | `anuki.f@gmail.com` | Browse menu, cart, checkout, order tracking |
| **Customer** | `kavindu_j` | `kavindu.j@outlook.com` | Regular customer ordering and order history |

---

## 📋 Testing & Quality Assurance Plan

In accordance with the assignment specifications (Section 6 & Group Report Deliverable), 15+ comprehensive test cases per component (functional, validation, security, and edge cases) are documented with step-by-step UI verification and screenshots in the submission report:

* **Component A (Identity & Security):** Password complexity, BCRYPT hash verification, brute-force lockout throttling (5 failed attempts), session fixation mitigation, role-based URL guards, and cryptographic password reset tokens.
* **Component B (Menu & Inventory):** Currency formatting, XSS contextual output escaping (`e()`), CSRF token lifecycle, SQL Injection mitigation on sorting/filtering parameters, secure media file uploads (MIME/size/extension), atomic stock deduction safeguards, and inventory classification.
* **Component C (Ordering & Tracking):** Cart dynamic totals calculation, dispatch fees vs. pickup waiving, coupon expiry and threshold validation, order code format compliance, order lifecycle transition validation (prevention of illegal state skipping), and transaction reference generation.

---

## 🎓 Component B Viva Defense Guide (Appendix B Questions)

### Q1: Show how the search and pagination query is built. How do you stop SQL injection through the sort parameter?
* **Answer:** All search terms and category IDs are passed through PDO prepared statements with parameter binding (`:search_name`, `:category_slug`). Because SQL does not allow binding column names or `ASC`/`DESC` directions in `ORDER BY` clauses via `?` or `:param`, we implement a **strict PHP whitelist map** (`$sortMap = ['newest' => 'd.id DESC', 'price_asc' => 'd.price ASC', ...]`). Any input that does not strictly match a key in the whitelist is rejected and falls back safely to `d.id DESC`. Thus, no client input ever reaches SQL concatenation.

### Q2: What checks does your image upload perform, and why is the file extension alone not enough?
* **Answer:** An extension check alone (e.g., checking if filename ends with `.jpg`) is vulnerable to file spoofing (e.g. an attacker uploading an executable script disguised as `exploit.php.jpg` or embedding PHP tags within an image file). Our upload routine in `upload_dish_image()` executes a multi-point defense:
  1. Checks standard `$_FILES['image']['error'] === UPLOAD_ERR_OK`.
  2. Enforces maximum 2MB file size ceiling.
  3. Checks extension against whitelisted set `['jpg', 'jpeg', 'png', 'webp']`.
  4. Performs **real binary inspection** using PHP's `finfo_file(FILEINFO_MIME_TYPE)` to examine magic bytes, strictly whitelisting `image/jpeg`, `image/png`, and `image/webp`.
  5. Cryptographically renames the file using `bin2hex(random_bytes(10))` to eliminate path traversal and null byte injections.
  6. Stores uploads in `public/uploads/` protected by a `.htaccess` file with `Require all denied` for executable script extensions.

### Q3: Explain why `reduce_stock()` uses a transaction or a conditional UPDATE, and what happens with two simultaneous orders for the last item.
* **Answer:** If two customers simultaneously attempt to buy the last remaining portion of a dish, a naive `SELECT quantity` followed by `UPDATE quantity = quantity - 1` causes a **race condition** (both see quantity = 1, both subtract 1, inventory drops to -1, resulting in overselling).  
  In `reduce_stock()`, we use an **atomic conditional UPDATE with row locking**:
  ```sql
  UPDATE inventory 
  SET quantity = quantity - :qty 
  WHERE dish_id = :dish_id AND quantity >= :qty
  ```
  MySQL's row-level write lock ensures that the first transaction locks the inventory row and deducts the stock to 0. When the second transaction acquires the row lock, its `WHERE quantity >= 1` condition evaluates to false, resulting in `rowCount() === 0`. The function detects 0 rows affected, rolls back, returns `false`, and alerts Component C that the dish is sold out, guaranteeing the stock never drops below zero.

