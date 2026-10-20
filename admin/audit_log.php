<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: System Audit Log Viewer
 * 
 * Styled according to the official Figma Design System:
 * - Clean warm light theme
 * - Integrated filter toolbar
 * - Formatted status badges and pagination
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Route guard: Administrator only
require_role('admin');

$searchKeyword = trim((string)($_GET['q'] ?? ''));
$filterAction  = trim((string)($_GET['action_filter'] ?? ''));

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$distinctActionsStmt = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC");
$distinctActions = $distinctActionsStmt->fetchAll(PDO::FETCH_COLUMN);

$whereClauses = [];
$params = [];

if ($searchKeyword !== '') {
    $whereClauses[] = "(a.details LIKE :kw OR a.ip_address LIKE :kw OR u.username LIKE :kw OR a.action LIKE :kw)";
    $params[':kw'] = '%' . $searchKeyword . '%';
}

if ($filterAction !== '') {
    $whereClauses[] = "a.action = :action";
    $params[':action'] = $filterAction;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$countSql = "SELECT COUNT(*) 
             FROM audit_log a 
             LEFT JOIN users u ON a.user_id = u.id 
             {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$dataSql = "SELECT a.*, u.username, u.full_name, r.role_name
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            {$whereSql}
            ORDER BY a.created_at DESC
            LIMIT :offset, :limit";

$stmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$logsList = $stmt->fetchAll();

$pageTitle = 'Audit Log';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; color: var(--ink-900);">
            System Audit Trail
        </h1>
        <p style="color: var(--ink-500); font-size: 1rem;">
            Immutable security records of user operations, authentication attempts, and system actions.
        </p>
    </div>
    <div>
        <a href="<?= e(url('admin/dashboard.php')); ?>" class="btn btn-secondary btn-sm">
            &larr; Back to Dashboard
        </a>
    </div>
</div>

<!-- Figma Search & Filter Bar -->
<div class="filter-bar">
    <form action="<?= e(url('admin/audit_log.php')); ?>" method="GET" style="display: flex; gap: 0.85rem; width: 100%; flex-wrap: wrap;">
        <div class="search-input-wrapper">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" 
                   name="q" 
                   class="search-input-field" 
                   placeholder="Search by details, IP, username, or action..." 
                   value="<?= e($searchKeyword); ?>">
        </div>

        <select name="action_filter" class="filter-select" onchange="this.form.submit()">
            <option value="">All Action Types</option>
            <?php foreach ($distinctActions as $act): ?>
                <option value="<?= e($act); ?>" <?= $filterAction === $act ? 'selected' : ''; ?>>
                    <?= e($act); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($searchKeyword !== '' || $filterAction !== ''): ?>
            <a href="<?= e(url('admin/audit_log.php')); ?>" class="btn btn-secondary btn-sm" style="padding: 0.75rem 1rem;">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Logs Data Table -->
<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logsList)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--ink-500); padding: 3rem;">
                        No audit log records match the search filter.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logsList as $row): ?>
                    <tr>
                        <td style="font-family: monospace; font-size: 0.82rem; color: var(--ink-500);">
                            #<?= e((string)$row['id']); ?>
                        </td>
                        <td style="white-space: nowrap; font-size: 0.83rem; color: var(--ink-500);">
                            <?= e(date('M d, Y H:i:s', strtotime($row['created_at']))); ?>
                        </td>
                        <td>
                            <?php if (!empty($row['username'])): ?>
                                <strong style="color: var(--ink-900);"><?= e($row['username']); ?></strong>
                                <div style="font-size: 0.75rem; color: var(--ink-500);">
                                    <?= e($row['role_name'] ? ucwords(str_replace('_', ' ', $row['role_name'])) : 'User #' . $row['user_id']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--ink-500); font-style: italic;">Anonymous / System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $act = $row['action'];
                                $badgeStyle = 'background: var(--ink-100); color: var(--ink-700);';
                                if (str_contains($act, 'SUCCESS')) $badgeStyle = 'background: var(--success-100); color: var(--success-500);';
                                elseif (str_contains($act, 'FAILED') || str_contains($act, 'LOCK') || str_contains($act, 'DENIED')) $badgeStyle = 'background: var(--danger-100); color: var(--danger-500);';
                                elseif (str_contains($act, 'UPDATE') || str_contains($act, 'CHANGE') || str_contains($act, 'ROLE')) $badgeStyle = 'background: var(--warning-100); color: var(--warning-500);';
                                elseif (str_contains($act, 'REGISTER') || str_contains($act, 'INIT')) $badgeStyle = 'background: var(--primary-100); color: var(--primary-600);';
                            ?>
                            <span style="display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: var(--radius-full); <?= $badgeStyle; ?>">
                                <?= e($act); ?>
                            </span>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--ink-900); max-width: 380px; word-break: break-word;">
                            <?= e($row['details'] ?? '—'); ?>
                        </td>
                        <td style="font-family: monospace; font-size: 0.8rem; color: var(--ink-500); white-space: nowrap;">
                            <?= e($row['ip_address']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Figma Pagination Controls -->
<div class="pagination-wrapper">
    <div class="pagination-info">
        <?php
            $start = $totalRows > 0 ? ($offset + 1) : 0;
            $end = min($offset + $perPage, $totalRows);
        ?>
        Showing <?= $start; ?>–<?= $end; ?> of <?= $totalRows; ?> log records
    </div>

    <?php if ($totalPages > 1): ?>
        <ul class="pagination-controls">
            <?php
                $queryParams = $_GET;
                $buildPageUrl = function($pageNum) use ($queryParams) {
                    $queryParams['page'] = $pageNum;
                    return url('admin/audit_log.php?' . http_build_query($queryParams));
                };
            ?>

            <li>
                <a href="<?= e($buildPageUrl(max(1, $page - 1))); ?>" 
                   class="page-btn btn-nav <?= $page <= 1 ? 'disabled' : ''; ?>">
                    Previous
                </a>
            </li>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li>
                    <a href="<?= e($buildPageUrl($i)); ?>" 
                       class="page-btn <?= $i === $page ? 'active' : ''; ?>">
                        <?= $i; ?>
                    </a>
                </li>
            <?php endfor; ?>

            <li>
                <a href="<?= e($buildPageUrl(min($totalPages, $page + 1))); ?>" 
                   class="page-btn btn-nav <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                    Next
                </a>
            </li>
        </ul>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
