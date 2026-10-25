# IT3113: Advanced Web Technologies
## Individual Project Report: Component A (Identity, Access & Administration)
**Module:** IT3113 Advanced Web Technologies (Year 3, Semester 1)  
**Academic Year:** 2026 / Semester 1  
**Assessment Type:** Individual Report (Deliverable 5 — 2 to 4 Pages)  
**Student Name:** [Your Name Here]  
**Student ID:** [Your Student ID Here]  
**Assigned Component:** **Component A — Identity, Access Control & System Administration**  
**Repository:** `https://github.com/deshan2004/UrbanEats-Cloud-Kitchen-Platform`  

---

## 1. Component Description & Scope

As the owner of **Component A**, I was personally responsible for designing, developing, testing, and securing the identity foundation, role-based access control (RBAC), user governance, and system-wide audit logging for the UrbanEats Cloud Kitchen platform. 

Component A serves as the critical security perimeter and shared foundation for the entire platform. Without Component A, Components B (Menu & Inventory) and C (Ordering & Payments) cannot establish user context or enforce permission barriers.

### Core Functional Scope:
* **User Authentication & Session Lifecycle:** Secure customer registration, login with brute-force throttling, session fixation mitigation, and secure logout.
* **Shared Infrastructure & Layout:** Global role-aware navigation bar (`includes/header.php`), common layout footer (`includes/footer.php`), and a centralized HTTP 403 Access Denied view (`auth/access_denied.php`).
* **Access Control Guard Middleware:** Reusable security guards (`includes/auth.php`) providing `require_login()` and `require_role()`, utilized across every protected script in the system.
* **Account Recovery & Self-Service:** Cryptographically secure, time-limited token-based password reset (`auth/reset.php`), user profile updates, and password change mechanisms (`auth/profile.php`).
* **Governance & Audit Trail:** Administrator user management console with live keyword search, role filtering, status toggling (activate/deactivate), and a central audit logging engine (`log_action()`) consumed by all three team members.

---

## 2. Statement of What I Personally Built

| Area | Personally Developed Deliverables & Files | Key Features Implemented |
| :--- | :--- | :--- |
| **Front-End Views** | `auth/register.php`<br>`auth/login.php`<br>`auth/profile.php`<br>`auth/reset.php`<br>`auth/access_denied.php`<br>`admin/users.php`<br>`admin/audit_log.php` | Responsive split-screen auth interfaces, dynamic password validation cues, role-filtering tables, modal dialogs, and accessible forms adhering to vanilla CSS variables. |
| **Shared Layout** | `includes/header.php`<br>`includes/footer.php` | Dynamic navigation that changes according to authenticated role (`guest`, `customer`, `kitchen_staff`, `admin`), active badge indicators, mobile navigation toggle. |
| **Back-End Logic** | `includes/auth.php`<br>`includes/functions.php` (`log_action`) | `require_login()`, `require_role()`, `current_user()`, brute-force attempt counter, time-limited token generation with SHA-256 hashing. |
| **Database Design** | `roles`, `users`, `login_attempts`, `audit_log`, `password_resets` | 5 relational tables normalized to 3NF, BCRYPT hashes, foreign keys with `ON UPDATE CASCADE`, compound indexes on `(username, attempted_at)`. |

---

## 3. Key Code Walkthrough & Explanations

### 3.1 Authentication & Session Regeneration (`auth/login.php`)
To mitigate session fixation attacks, the session identifier is immediately rotated upon verifying credentials before storing identity details in the session array:

```php
// Verify user credentials using native BCRYPT
if ($user && password_verify($password, $user['password_hash'])) {
    // Check if account is suspended
    if ((int)$user['is_active'] !== 1) {
        $errorMessage = "Your account has been deactivated. Please contact support.";
    } else {
        // Prevent Session Fixation: Regenerate session ID and delete old session file
        session_regenerate_id(true);

        // Store identity context in session
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role_name'];
        $_SESSION['role_id']  = (int)$user['role_id'];

        // Reset failed login attempts upon success
        clear_failed_attempts($pdo, $username, $clientIp);

        // Record compliance audit log
        log_action($pdo, $user['id'], 'USER_LOGIN', 'User successfully authenticated.');

        // Role-based redirection
        redirect_by_role($user['role_name']);
    }
}
```

### 3.2 Role Guard Security Middleware (`includes/auth.php`)
Every protected view calls `require_role()`. If an unauthorized customer directly types an administrative URL (e.g., `/admin/users.php`), the guard halts execution and routes them to the standardized access denied page:

```php
function require_role(array|string $allowedRoles): void {
    require_login(); // Ensure user is authenticated first

    $userRole = $_SESSION['role'] ?? '';
    $allowed  = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($userRole, $allowed, true)) {
        http_response_code(403);
        // Log unauthorized privilege escalation attempt
        if (isset($_SESSION['user_id'])) {
            global $pdo;
            log_action($pdo, $_SESSION['user_id'], 'UNAUTHORIZED_ACCESS_ATTEMPT', 
                       "User attempted to access restricted route: " . $_SERVER['REQUEST_URI']);
        }
        header("Location: /UrbanEats-Cloud-Kitchen-Platform/auth/access_denied.php");
        exit;
    }
}
```

### 3.3 Brute-Force Login Throttling
To protect against automated credential stuffing and dictionary attacks, consecutive failed attempts are recorded in `login_attempts`:

```php
function check_login_lockout(PDO $pdo, string $username, string $ip): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts 
        WHERE (username = :username OR ip_address = :ip)
          AND is_successful = 0
          AND attempted_at > (NOW() - INTERVAL 15 MINUTE)
    ");
    $stmt->execute([':username' => $username, ':ip' => $ip]);
    $failedCount = (int)$stmt->fetchColumn();

    // Lock account temporarily after 5 consecutive failed attempts
    return $failedCount >= 5;
}
```

### 3.4 Cryptographic Password Recovery (`auth/reset.php`)
Password reset relies on cryptographically secure pseudorandom tokens (`random_bytes(32)`) hashed with SHA-256 in the database with an exact 1-hour expiration timestamp (`NOW() + INTERVAL 1 HOUR`):

```php
$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
$expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour validity

$stmt = $pdo->prepare("
    INSERT INTO password_resets (user_id, token_hash, expires_at)
    VALUES (:user_id, :token_hash, :expires_at)
");
$stmt->execute([
    ':user_id'    => $user['id'],
    ':token_hash' => $tokenHash,
    ':expires_at' => $expiresAt
]);
// Reset link displayed securely on-screen (per assignment specification)
$resetUrl = "http://localhost/UrbanEats-Cloud-Kitchen-Platform/auth/reset.php?token=" . $rawToken;
```

---

## 4. Key SQL Queries & Database Implementation

### 4.1 Filtered User Administration with Role Joins
Used in `admin/users.php` to search users by name, email, or username while filtering dynamically by role:

```sql
SELECT u.id, u.username, u.email, u.full_name, u.phone, u.is_active, u.created_at,
       r.role_name, r.id AS role_id
FROM users u
INNER JOIN roles r ON u.role_id = r.id
WHERE (:role_filter = '' OR r.role_name = :role_filter)
  AND (:search_query = '' OR (
       u.username LIKE :search_like OR 
       u.email LIKE :search_like OR 
       u.full_name LIKE :search_like
  ))
ORDER BY u.created_at DESC
LIMIT :limit OFFSET :offset;
```
* **Performance Optimization:** Supported by a foreign key index on `idx_users_role_id` and unique indexes on `uq_users_username` and `uq_users_email`.

### 4.2 Cross-Component Audit Trail Logging (`log_action`)
Called across the application whenever important state changes take place (e.g., dish additions in Component B, checkout in Component C):

```sql
INSERT INTO audit_log (user_id, action, details, ip_address, user_agent, created_at)
VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW());
```

---

## 5. Security & Design Justifications

1. **Why `password_hash()` (BCRYPT) instead of MD5 or SHA-1?**  
   MD5 and SHA-1 are broken, general-purpose cryptographic hash functions vulnerable to collision attacks and rapid brute-forcing (billions of hashes per second via modern GPUs). `password_hash()` utilizes **BCRYPT**, a key-derivation function that incorporates an automatic cryptographic salt and a configurable computational cost factor (work factor: 12), ensuring high resistance against rainbow table and offline GPU cracking attacks.
2. **What does `session_regenerate_id()` prevent?**  
   It eliminates **Session Fixation attacks**. In a fixation attack, an adversary tricks a victim into authenticating using a pre-allocated session identifier. By calling `session_regenerate_id(true)` upon successful credential validation, the previous session token is deleted and replaced with a newly generated, unpredictable session identifier.
3. **Defense-in-Depth for Authorization:**  
   Component A does not rely on hiding UI buttons. Even if an attacker knows the URL to `admin/users.php` or `orders/kitchen_queue.php`, the server-side guard `require_role()` executes before any business logic or SQL queries run, rejecting unauthorized requests with HTTP 403.

---

## 6. Challenges Encountered & Solutions

| Challenge | Technical Bottleneck | Engineered Solution |
| :--- | :--- | :--- |
| **Component Synchronization** | Other components were being developed in parallel and needed immediate access to logged-in user context. | Formulated a strict Week 1 Session Contract establishing `$_SESSION['user_id']` and `$_SESSION['role']`, providing teammate stubs and mock logins so development remained unblocked. |
| **Email Server Absence on Local XAMPP** | Local development environments (XAMPP/WAMP) typically lack configured SMTP servers (Sendmail) for password reset emails. | Adhered to the assignment allowance: generated cryptographically secure reset tokens and displayed the one-time reset link directly on-screen inside a secure notification banner. |
| **Brute-Force DoS vs. Legitimate Users** | Locking accounts purely by username could allow malicious actors to deliberately lock out legitimate users (Denial of Service). | Coupled the lockout logic to both `username` AND client `ip_address` over a rolling 15-minute sliding window, ensuring attackers cannot easily lock out others. |

---

## 7. Viva Defense Preparation (Appendix B Sample Questions)

### Q1: Walk through what happens from the moment the login form is submitted to the moment the dashboard loads.
1. The client submits a POST request containing `username`, `password`, and `csrf_token`.
2. `verify_csrf_token()` validates that the form token matches `$_SESSION['csrf_token']`.
3. `check_login_lockout()` queries `login_attempts` to ensure fewer than 5 failures occurred in the last 15 minutes.
4. A prepared statement queries `users` matching the username.
5. `password_verify()` compares the plaintext password against `password_hash`.
6. Upon validation, `session_regenerate_id(true)` generates a fresh session identifier.
7. User context (`user_id`, `role`, `username`) is assigned to `$_SESSION`, failed attempts are cleared, and `log_action()` records `USER_LOGIN`.
8. The server issues a redirect header sending Administrators to `/admin/dashboard.php`, Kitchen Staff to `/orders/kitchen_queue.php`, and Customers to `/index.php`.

### Q2: Why password_hash() instead of MD5 or SHA-1? What does session_regenerate_id() prevent?
* *Explained in detail in Section 5 (Resistance to rainbow tables, work factor adaptability, and prevention of session fixation attacks).*

### Q3: How does require_role() protect a page, and what happens if a user types an admin URL directly?
* `require_role()` runs at line 1 of every protected page. It verifies `$_SESSION['role']`. If the user is unauthenticated, it redirects to `auth/login.php`. If authenticated but lacking permissions (e.g., Customer typing `/admin/users.php`), it logs an `UNAUTHORIZED_ACCESS_ATTEMPT` in `audit_log`, emits an HTTP 403 status code, and displays `auth/access_denied.php`. No sensitive administrative data is ever retrieved or rendered.
