<?php
/**
 * 707 GYM - Session Management
 * Configures and starts the session; provides auth helpers.
 */

require_once __DIR__ . '/config.php';

// ─── Session ini settings (must be set BEFORE session_start) ─────────────────
ini_set('session.cookie_httponly',  '1');
ini_set('session.use_strict_mode',  '1');
ini_set('session.cookie_samesite',  'Lax');
ini_set('session.gc_maxlifetime',   (string) SESSION_LIFETIME);
ini_set('session.cookie_lifetime',  '0'); // browser session cookie

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ─── Auth helpers ─────────────────────────────────────────────────────────────

/**
 * Check whether an admin is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Redirect to login page if the visitor is not authenticated.
 * Call at the top of every protected page.
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Regenerate session ID (call after successful login to prevent fixation).
 */
function regenerateSession(): void
{
    session_regenerate_id(true);
}

// ─── Flash messages ──────────────────────────────────────────────────────────

/**
 * Store a one-time flash message.
 *
 * @param string $type  success | error | warning | info
 * @param string $msg   Human-readable message
 */
function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
}

/**
 * Retrieve and clear the flash message.
 *
 * @return array{type:string,message:string}|null
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
