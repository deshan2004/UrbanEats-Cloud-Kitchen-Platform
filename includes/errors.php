<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Global Error and Exception Handling
 */

declare(strict_types=1);

// Ensure logs directory exists
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/error.log');
error_reporting(E_ALL);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $message = sprintf("[%s] PHP Error [%d]: %s in %s on line %d\n", date('Y-m-d H:i:s'), $errno, $errstr, $errfile, $errline);
    error_log($message);
    return true;
});

set_exception_handler(function (Throwable $exception) {
    $message = sprintf(
        "[%s] Uncaught Exception: %s in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    error_log($message);

    // If HTTP headers not sent yet, return 500 status
    if (!headers_sent()) {
        http_response_code(500);
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>System Error - UrbanEats</title>';
    echo '<style>body{font-family:sans-serif;background:#0d1117;color:#f0f6fc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}';
    echo '.card{background:#161b22;padding:2.5rem;border-radius:12px;border:1px solid #30363d;max-width:480px;text-align:center;}';
    echo 'h1{color:#f85149;margin-bottom:1rem;}p{color:#8b949e;line-height:1.6;}a{color:#58a6ff;text-decoration:none;font-weight:600;}</style></head>';
    echo '<body><div class="card"><h1>An Unexpected Error Occurred</h1>';
    echo '<p>Our engineering team has been notified. Please try again later or return to safety.</p>';
    echo '<p><a href="javascript:history.back()">&larr; Go Back</a></p></div></body></html>';
    exit;
});
