<?php
/**
 * 707 GYM - General Helper Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize a string for safe output (HTML-encode).
 *
 * @param mixed $input
 * @return string
 */
function sanitize(mixed $input): string
{
    return htmlspecialchars(trim((string) $input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redirect to a URL and exit.
 *
 * @param string $url  Absolute or relative URL
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Format a date string or timestamp.
 *
 * @param string|int $date    Parseable date string or Unix timestamp
 * @param string     $format  PHP date format
 * @return string
 */
function formatDate(string|int $date, string $format = 'M d, Y'): string
{
    if (empty($date)) return '—';
    $ts = is_numeric($date) ? (int) $date : strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

/**
 * Format a number as a generic currency string.
 *
 * @param float|string $amount
 * @return string  e.g. "1,200.00"
 */
function formatCurrency(float|string $amount): string
{
    return number_format((float) $amount, 2);
}

/**
 * Format an amount in Philippine Peso with the peso sign.
 *
 * @param float|string $amount
 * @return string  e.g. "P1,200.00"
 */
function formatPhilippineCurrency(float|string $amount): string
{
    return "\u{20B1}" . number_format((float) $amount, 2);
}

/**
 * Return a human-readable "time ago" string.
 *
 * @param string $datetime  MySQL datetime string
 * @return string  e.g. "3 minutes ago"
 */
function timeAgo(string $datetime): string
{
    if (empty($datetime)) return '—';

    $now  = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    $then = new DateTimeImmutable($datetime, new DateTimeZone(APP_TIMEZONE));
    $diff = $now->diff($then);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Calculate pagination metadata.
 *
 * @param int $totalItems    Total number of records
 * @param int $currentPage   1-based current page
 * @param int $perPage       Records per page
 * @return array{total:int, per_page:int, current_page:int, total_pages:int, offset:int, has_prev:bool, has_next:bool}
 */
function getPaginationData(int $totalItems, int $currentPage, int $perPage = 20): array
{
    $currentPage = max(1, $currentPage);
    $totalPages  = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'        => $totalItems,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}

/**
 * Log an admin activity to the activity_logs table.
 *
 * @param int    $adminId  Admin performing the action
 * @param string $action   Short action name (e.g. 'member.create')
 * @param string $details  Optional detail text
 */
function logActivity(int $adminId, string $action, string $details = ''): void
{
    try {
        $db   = Database::getConnection();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $db->prepare(
            "INSERT INTO activity_logs (admin_id, action, details, ip_address, created_at)
             VALUES (:admin_id, :action, :details, :ip, NOW())"
        );
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action'   => $action,
            ':details'  => $details,
            ':ip'       => $ip,
        ]);
    } catch (Throwable) {
        // Never let logging break the main flow
    }
}

/**
 * Get the initials from a full name (max 2 characters).
 *
 * @param string $name
 * @return string  e.g. "JD"
 */
function getInitials(string $name): string
{
    $parts  = array_filter(explode(' ', trim($name)));
    $result = '';
    foreach ($parts as $part) {
        $result .= strtoupper($part[0]);
        if (strlen($result) >= 2) break;
    }
    return $result ?: '?';
}

/**
 * Return number of days remaining until a date (negative if past).
 *
 * @param string $endDate  MySQL date string
 * @return int
 */
function daysRemaining(string $endDate): int
{
    $now = new DateTimeImmutable('today', new DateTimeZone(APP_TIMEZONE));
    $end = new DateTimeImmutable($endDate, new DateTimeZone(APP_TIMEZONE));
    return (int) $now->diff($end)->days * ($end >= $now ? 1 : -1);
}
