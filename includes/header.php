<?php
/**
 * 707 GYM - HTML Header Include
 * Opens the HTML document, loads assets, defines CSS variables.
 *
 * Expected variable in including file:
 *   $pageTitle  string  e.g. "Dashboard"
 */

$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
$flash     = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Application stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <!-- Inline CSS variables + token overrides -->
    <style>
        :root {
            --accent:       #FF4D00;
            --accent-hover: #E64600;
            --bg:           #0B0C0E;
            --surface:      #141619;
            --surface-2:    #1E2024;
            --text:         #F3F4F6;
            --muted:        #8B9099;
            --border:       rgba(255, 255, 255, 0.07);
            --glass:        rgba(16, 17, 20, 0.96);
            --radius:       10px;
            --sidebar-width: 248px;
            --transition:   0.2s ease;
        }
    </style>

    <!-- Lucide Icons (CDN) -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body>
<div class="app-wrapper">

<?php if ($flash): ?>
<!-- Flash message injected into DOM; JS will auto-dismiss it -->
<?php
$flashIcons = [
    'success' => 'circle-check',
    'error'   => 'circle-x',
    'warning' => 'triangle-alert',
    'info'    => 'info',
];
$flashIcon = $flashIcons[$flash['type']] ?? 'info';
?>
<div class="alert alert-<?= sanitize($flash['type']) ?> flash-message" role="alert">
    <span class="flash-icon" aria-hidden="true"><i data-lucide="<?= $flashIcon ?>"></i></span>
    <span class="flash-copy"><?= sanitize($flash['message']) ?></span>
    <button class="alert-close" type="button" aria-label="Close notification">
        <i data-lucide="x"></i>
    </button>
</div>
<?php endif; ?>
