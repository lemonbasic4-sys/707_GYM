<?php
/**
 * 707 GYM - Sidebar Navigation Include
 * Responsive, collapsible sidebar with Lucide icons.
 *
 * Requires: $pageTitle or nothing — active link is detected from REQUEST_URI.
 */

$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$adminName   = $_SESSION['admin_name']  ?? 'Admin';
$adminEmail  = $_SESSION['admin_email'] ?? '';
$initials    = getInitials($adminName);

/**
 * Helper: return 'active' class if $segment is in the current URL path.
 */
function navActive(string $segment): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    return str_contains($path, $segment) ? 'active' : '';
}

$isDashboard = (str_contains($currentPath, 'dashboard') || rtrim(parse_url($currentPath, PHP_URL_PATH), '/') === BASE_URL);
?>

<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <img class="brand-icon" src="<?= BASE_URL ?>/assets/img/gym_logo.png" alt="">
        <span class="brand-name"><?= APP_NAME ?></span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" aria-label="Main navigation">
        <ul>
            <li>
                <a href="<?= BASE_URL ?>/pages/dashboard.php"
                   class="nav-link <?= $isDashboard ? 'active' : '' ?>">
                    <i data-lucide="home" class="nav-icon"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/pages/members/index.php"
                   class="nav-link <?= navActive('/members/') ?>">
                    <i data-lucide="users" class="nav-icon"></i>
                    <span>Members</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/pages/memberships/index.php"
                   class="nav-link <?= navActive('/memberships/') ?>">
                    <i data-lucide="credit-card" class="nav-icon"></i>
                    <span>Memberships</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/pages/attendance/index.php"
                   class="nav-link <?= navActive('/attendance/') ?>">
                    <i data-lucide="calendar-check" class="nav-icon"></i>
                    <span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/pages/payments/index.php"
                   class="nav-link <?= navActive('/payments/') ?>">
                    <i data-lucide="dollar-sign" class="nav-icon"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/pages/imports/index.php"
                   class="nav-link <?= navActive('/imports/') ?>">
                    <i data-lucide="upload" class="nav-icon"></i>
                    <span>CSV Import</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/pages/analytics/index.php"
                   class="nav-link <?= navActive('/analytics/') ?>">
                    <i data-lucide="bar-chart-2" class="nav-icon"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/pages/admin/audit-log.php"
                   class="nav-link <?= navActive('/audit-log.php') ?>">
                    <i data-lucide="clipboard-list" class="nav-icon"></i>
                    <span>Audit Trail</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <li>
                <a href="<?= BASE_URL ?>/pages/admin/settings.php"
                   class="nav-link <?= navActive('/settings.php') ?>">
                    <i data-lucide="settings" class="nav-icon"></i>
                    <span>System</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Admin Profile -->
    <div class="sidebar-footer">
        <div class="admin-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
        <div class="admin-info">
            <span class="admin-name"><?= sanitize($adminName) ?></span>
            <span class="admin-email"><?= sanitize($adminEmail) ?></span>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn-icon" title="Logout">
            <i data-lucide="log-out"></i>
        </a>
    </div>
</aside>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Mobile toggle button (rendered into main-content header) -->
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
    <i data-lucide="menu"></i>
</button>

<main class="main-content" id="mainContent">
