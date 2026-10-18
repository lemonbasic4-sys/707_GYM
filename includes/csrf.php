<?php
/**
 * 707 GYM - CSRF Protection
 * Token generation and verification for all form submissions.
 */

require_once __DIR__ . '/../config/session.php';

/**
 * Generate a CSRF token and store it in the session.
 * Returns the same token if one already exists for this request cycle.
 *
 * @return string  64-character hex token
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token against the session token.
 * Uses hash_equals to prevent timing attacks.
 *
 * @param string $token  Token from form submission
 * @return bool
 */
function verifyCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Invalidate the current CSRF token and generate a fresh one.
 * Call after a successful form submission to prevent replay.
 */
function rotateCsrfToken(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Render a hidden <input> field containing the CSRF token.
 *
 * @return string  HTML string
 */
function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
