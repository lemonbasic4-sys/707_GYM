<?php
/**
 * 707 GYM – Logout
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

$adminId = $_SESSION['admin_id'] ?? 0;

// Log before destroying session
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
logActivity($adminId, 'auth.logout', 'Admin logged out.');

$auth = new Auth();
$auth->logout();

redirect(BASE_URL . '/login.php');
