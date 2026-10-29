<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Utility Functions
 */

declare(strict_types=1);

/**
 * Escapes output safely for HTML rendering to prevent XSS.
 *
 * @param mixed $value
 * @return string
 */
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Calculates a reliable absolute web path for local assets and routing.
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        // Find project root directory relative to web root
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appRoot = str_replace('\\', '/', dirname(__DIR__));
        
        if (!empty($docRoot) && strpos($appRoot, $docRoot) === 0) {
            $subPath = substr($appRoot, strlen($docRoot));
            $base = rtrim($subPath, '/');
        } else {
            // Fallback: estimate from script name
            $scriptDir = dirname($scriptName);
            $base = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
            // If inside auth/ or includes/, step up
            $base = preg_replace('/(\/(auth|admin|menu|orders|public|includes))$/', '', $base);
        }
    }
    return ($base ?: '') . '/' . ltrim($path, '/');
}

/**
 * Redirect to a specific URL and stop script execution.
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Set a session flash message.
 *
 * @param string $type 'success', 'error', 'warning', 'info'
 * @param string $message
 * @return void
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the session flash message if any.
 *
 * @return array|null
 */
function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Retrieves the client's IP address safely.
 *
 * @return string
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/**
 * Component A Contract Function: System-Wide Security Audit Logger.
 * 
 * DESIGN & SECURITY RATIONALE:
 * Shared service contract utilized across Components A, B, and C to enforce
 * forensic traceability and non-repudiation across the entire cloud kitchen platform.
 * 
 * KEY SAFEGUARDS:
 * 1. Foreign Key Integrity: Validates $user_id against the users table before insertion.
 *    If invalid or unauthenticated (e.g. failed login, guest checkout), gracefully stores NULL.
 * 2. Client Metadata Capture: Automatically attaches the client's validated IP address
 *    (via get_client_ip()) and browser User-Agent string.
 * 3. Boundary Truncation: Restricts strings using mb_substr() to prevent database buffer overflows.
 *
 * @param PDO $pdo Active PDO instance
 * @param int|null $user_id Authenticated user ID, or null for anonymous/system actions
 * @param string $action Standardized uppercase action tag (e.g., 'LOGIN_SUCCESS', 'ORDER_CREATED')
 * @param string|null $details Descriptive context or audit payload
 * @return bool True if logged successfully, false on error
 */
function log_action(PDO $pdo, ?int $user_id, string $action, ?string $details = null): bool {
    try {
        if ($user_id !== null) {
            if ($user_id <= 0) {
                $user_id = null;
            } else {
                $uCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $uCheck->execute([$user_id]);
                if (!$uCheck->fetch()) {
                    $user_id = null;
                }
            }
        }
        $sql = "INSERT INTO audit_log (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id'    => $user_id,
            ':action'     => mb_substr($action, 0, 100),
            ':details'    => $details,
            ':ip_address' => get_client_ip(),
            ':user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255),
        ]);
    } catch (PDOException $e) {
        error_log('[Audit Log Error] Failed to log action: ' . $e->getMessage());
        return false;
    }
}

/**
 * =========================================================================
 * COMPONENT B: MENU, CATALOGUE & INVENTORY SHARED CONTRACT
 * =========================================================================
 */

/**
 * Formats a numeric currency value into standardized Sri Lankan Rupee (LKR) notation.
 *
 * @param float|int|string $amount
 * @return string
 */
function format_price(float|int|string $amount): string {
    return 'LKR ' . number_format((float)$amount, 2);
}

/**
 * Component B contract function: Transaction-safe stock deduction.
 * Called by Component C during checkout. Refuses to go below zero.
 *
 * Uses an atomic conditional UPDATE (`WHERE dish_id = :id AND quantity >= :check_qty`).
 * If two concurrent checkouts attempt to claim the last remaining item, MySQL's
 * row-level write lock ensures the first transaction updates the row, reducing quantity
 * to 0. The second transaction's WHERE condition (`quantity >= 1`) then evaluates to false,
 * affecting 0 rows, triggering safe refusal and rollback without overselling.
 *
 * @param PDO $pdo
 * @param int $dish_id
 * @param int $qty
 * @param string $order_reference
 * @return bool True if stock was successfully deducted; false if insufficient stock or error.
 */
function reduce_stock(PDO $pdo, int $dish_id, int $qty, string $order_reference = 'Order Checkout'): bool {
    if ($qty <= 0) {
        return false;
    }

    $inActiveTransaction = $pdo->inTransaction();
    if (!$inActiveTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // 1. Fetch current stock with row lock
        $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE dish_id = :dish_id FOR UPDATE");
        $stmt->execute([':dish_id' => $dish_id]);
        $current = $stmt->fetchColumn();

        if ($current === false || (int)$current < $qty) {
            if (!$inActiveTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        $prevQty = (int)$current;
        $newQty  = $prevQty - $qty;

        // 2. Perform atomic conditional update with distinct parameter markers for native prepared statements
        $updateStmt = $pdo->prepare(
            "UPDATE inventory 
             SET quantity = quantity - :deduct_qty, updated_at = NOW() 
             WHERE dish_id = :dish_id AND quantity >= :check_qty"
        );
        $updateStmt->execute([
            ':deduct_qty' => $qty,
            ':dish_id'    => $dish_id,
            ':check_qty'  => $qty,
        ]);

        if ($updateStmt->rowCount() === 0) {
            if (!$inActiveTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        // 3. Resolve user_id for stock_movements (safeguarding against missing foreign key)
        $movUserId = (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) ? (int)$_SESSION['user_id'] : null;
        if ($movUserId !== null) {
            $uCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $uCheck->execute([$movUserId]);
            if (!$uCheck->fetch()) {
                $movUserId = null;
            }
        }

        // 4. Record immutable stock movement
        $movStmt = $pdo->prepare(
            "INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity_change, previous_quantity, new_quantity, reason, created_at)
             VALUES (:dish_id, :user_id, 'order_deduction', :qty_change, :prev_qty, :new_qty, :reason, NOW())"
        );
        $movStmt->execute([
            ':dish_id'    => $dish_id,
            ':user_id'    => $movUserId,
            ':qty_change' => -$qty,
            ':prev_qty'   => $prevQty,
            ':new_qty'    => $newQty,
            ':reason'     => $order_reference,
        ]);

        if (!$inActiveTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $e) {
        if (!$inActiveTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[reduce_stock Error] " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves the current live inventory balance and status badge classification for a dish.
 *
 * @param PDO $pdo
 * @param int $dish_id
 * @return array
 */
function get_dish_stock(PDO $pdo, int $dish_id): array {
    $stmt = $pdo->prepare(
        "SELECT quantity, reorder_level 
         FROM inventory 
         WHERE dish_id = :dish_id 
         LIMIT 1"
    );
    $stmt->execute([':dish_id' => $dish_id]);
    $inv = $stmt->fetch();

    if (!$inv) {
        return [
            'quantity'      => 0,
            'reorder_level' => 10,
            'status'        => 'out_of_stock',
            'label'         => 'Out of Stock',
            'badge_class'   => 'stock-badge-out',
        ];
    }

    $qty     = (int)$inv['quantity'];
    $reorder = (int)$inv['reorder_level'];

    if ($qty <= 0) {
        $status = 'out_of_stock';
        $label  = 'Out of Stock';
        $badge  = 'stock-badge-out';
    } elseif ($qty <= $reorder) {
        $status = 'low_stock';
        $label  = 'Low Stock (' . $qty . ' left)';
        $badge  = 'stock-badge-low';
    } else {
        $status = 'in_stock';
        $label  = 'In Stock (' . $qty . ' available)';
        $badge  = 'stock-badge-in';
    }

    return [
        'quantity'      => $qty,
        'reorder_level' => $reorder,
        'status'        => $status,
        'label'         => $label,
        'badge_class'   => $badge,
    ];
}

/**
 * Records a staff inventory adjustment (restock, wastage, manual count correction).
 *
 * @param PDO $pdo
 * @param int $dish_id
 * @param int|null $user_id Staff user making adjustment
 * @param string $movement_type 'restock', 'wastage', 'adjustment'
 * @param int $quantity_change Positive or negative change
 * @param string $reason Explanation note
 * @return bool
 */
function adjust_stock(PDO $pdo, int $dish_id, ?int $user_id, string $movement_type, int $quantity_change, string $reason): bool {
    if (!in_array($movement_type, ['restock', 'wastage', 'adjustment'], true)) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE dish_id = :id FOR UPDATE");
        $stmt->execute([':id' => $dish_id]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            // Create inventory row if missing
            $ins = $pdo->prepare("INSERT INTO inventory (dish_id, quantity, reorder_level) VALUES (:id, 0, 10)");
            $ins->execute([':id' => $dish_id]);
            $prevQty = 0;
        } else {
            $prevQty = (int)$current;
        }

        $newQty = $prevQty + $quantity_change;
        if ($newQty < 0) {
            $pdo->rollBack();
            return false; // Stock cannot drop below zero
        }

        $upd = $pdo->prepare("UPDATE inventory SET quantity = :qty, last_restocked_at = CASE WHEN :type = 'restock' THEN NOW() ELSE last_restocked_at END WHERE dish_id = :id");
        $upd->execute([
            ':qty'  => $newQty,
            ':type' => $movement_type,
            ':id'   => $dish_id,
        ]);

        $mov = $pdo->prepare(
            "INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity_change, previous_quantity, new_quantity, reason, created_at)
             VALUES (:dish_id, :user_id, :type, :change, :prev, :new, :reason, NOW())"
        );
        $mov->execute([
            ':dish_id' => $dish_id,
            ':user_id' => $user_id,
            ':type'    => $movement_type,
            ':change'  => $quantity_change,
            ':prev'    => $prevQty,
            ':new'     => $newQty,
            ':reason'  => mb_substr($reason, 0, 255),
        ]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[adjust_stock Error] " . $e->getMessage());
        return false;
    }
}

/**
 * Validates and securely processes dish image uploads.
 *
 * Security checks:
 * 1. Checks standard $_FILES upload error codes.
 * 2. Whitelists file extension (jpg, jpeg, png, webp).
 * 3. Inspects real binary MIME type via finfo_file (protects against extension spoofing).
 * 4. Limits file size to 2 MB (2,097,152 bytes).
 * 5. Generates randomized filename to prevent directory traversal and overwrite attacks.
 * 6. Stores outside script executable execution paths.
 *
 * @param array $file $_FILES['image'] item
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function upload_dish_image(array $file): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'path' => null, 'error' => 'Invalid file upload parameters.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'error' => null]; // Optional image
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'Upload failed with code: ' . $file['error']];
    }

    // 1. File size constraint (Max 2MB)
    $maxBytes = 2 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'path' => null, 'error' => 'Uploaded image exceeds maximum allowable limit of 2MB.'];
    }

    // 2. Extension whitelisting
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'path' => null, 'error' => 'Only JPG, JPEG, PNG, and WEBP image formats are supported.'];
    }

    // 3. Real binary MIME-type check (finfo inspection)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg'   => 'jpg',
        'image/pjpeg'  => 'jpg',
        'image/png'    => 'png',
        'image/x-png'  => 'png',
        'image/webp'   => 'webp',
    ];

    if (!isset($allowedMimes[$realMime])) {
        return ['success' => false, 'path' => null, 'error' => 'File contents do not match a valid image format (JPEG, PNG, WEBP).'];
    }

    // Normalize extension strictly to the genuine binary payload type to guarantee security & compatibility
    $extension = $allowedMimes[$realMime];

    // 4. Secure random filename generation
    $targetDir = dirname(__DIR__) . '/public/uploads';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $uniqueName = 'dish_' . bin2hex(random_bytes(10)) . '.' . $extension;
    $targetPath = $targetDir . '/' . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'path' => null, 'error' => 'Failed to save uploaded image to destination directory.'];
    }

    return [
        'success' => true,
        'path'    => 'uploads/' . $uniqueName,
        'error'   => null,
    ];
}
