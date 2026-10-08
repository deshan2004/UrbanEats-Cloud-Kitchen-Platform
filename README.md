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
│   └── urbaneats_db.sql        # Database schema, foreign keys & 20+ seed rows
├── logs/
│   └── error.log               # Server-side application error log
└── README.md
