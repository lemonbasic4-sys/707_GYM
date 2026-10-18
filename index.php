<?php
/**
 * 707 GYM – Application Entry Point
 * Redirects authenticated users to the dashboard; others to login.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/pages/dashboard.php');
} else {
    redirect(BASE_URL . '/login.php');
}
