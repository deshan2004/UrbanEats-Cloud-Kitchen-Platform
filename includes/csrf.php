<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * CSRF (Cross-Site Request Forgery) Protection Helper (Component A)
 * 
 * Implements the OWASP Synchronizer Token Pattern:
 * 1. Generates a unique, cryptographically strong token bound to the user's active session.
 * 2. Injects the token into state-changing HTTP POST forms as a hidden field.
 * 3. Validates the submitted token using constant-time string comparison before processing actions.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate or retrieve an existing CSRF token for the active session.
 * 
 * SECURITY IMPLEMENTATION:
 * Uses random_bytes(32) which relies on the operating system's CSPRNG 
 * (Cryptographically Secure Pseudo-Random Number Generator, e.g. /dev/urandom).
 * This yields 256 bits of entropy, transformed into a 64-character hexadecimal
 * representation, rendering brute-force token prediction statistically impossible.
 *
 * @return string
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden input containing the CSRF token.
 * Uses htmlspecialchars() with ENT_QUOTES to prevent HTML/XSS injection.
 *
 * @return void
 */
function csrf_field(): void {
    $token = get_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifies that the provided token matches the active session token.
 * 
 * SECURITY RATIONALE (TIMING ATTACK MITIGATION):
 * Standard string comparison operators ($a === $b) terminate early at the first
 * mismatched character, causing detectable execution time differences (Timing Leakage).
 * Attacking systems can exploit this variance to reconstruct the token character by character.
 * hash_equals() executes in constant time regardless of where or whether characters differ.
 *
 * @param string|null $token Submitted token from form request
 * @return bool True if tokens match identically, false otherwise
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Backward-compatible alias for legacy calls that expect generate_csrf_token().
 *
 * @return string
 */
function generate_csrf_token(): string {
    return get_csrf_token();
}

/**
 * Backward-compatible alias for template calls that expect csrf_token().
 *
 * @return string
 */
function csrf_token(): string {
    return get_csrf_token();
}

