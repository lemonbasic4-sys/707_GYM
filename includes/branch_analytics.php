<?php
/**
 * Shared helpers for branch analytics snapshots.
 */

function isValidAnalyticsMonth(string $month): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)) {
        return false;
    }

    return checkdate((int) $matches[2], 1, (int) $matches[1]);
}

function isValidBranchName(string $branchName): bool
{
    return preg_match('/^707_GYM_(?:MAIN|[1-9]\d*)$/', $branchName) === 1;
}

function hasBranchAnalyticsSchema(PDO $db): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN ('branch_analytics_settings', 'branch_analytics_snapshots')"
    );
    $stmt->execute();

    return (int) $stmt->fetchColumn() === 2;
}

function getLocalBranchName(PDO $db): string
{
    $branchName = $db->query(
        "SELECT branch_name FROM branch_analytics_settings WHERE id = 1"
    )->fetchColumn();

    if (!is_string($branchName) || !isValidBranchName($branchName)) {
        throw new RuntimeException('The local branch identity is missing or invalid.');
    }

    return $branchName;
}

function getCurrentBranchAnalytics(PDO $db, string $month): array
{
    if (!isValidAnalyticsMonth($month)) {
        throw new InvalidArgumentException('A valid YYYY-MM analytics month is required.');
    }

    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
    $stmt = $db->prepare(
        "SELECT
            (SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE status = 'paid' AND payment_date >= :start_date AND payment_date < :end_date) AS revenue,
            (SELECT COUNT(*) FROM members
             WHERE created_at >= :start_time AND created_at < :end_time) AS new_members,
            (SELECT COUNT(*) FROM members WHERE status = 'active') AS active_members,
            (SELECT COUNT(*) FROM attendance
             WHERE date >= :start_date AND date < :end_date) AS attendance_visits,
            (SELECT COUNT(*) FROM memberships WHERE status = 'active') AS active_memberships"
    );
    $stmt->execute([
        ':start_date' => $start,
        ':end_date' => $end,
        ':start_time' => $start . ' 00:00:00',
        ':end_time' => $end . ' 00:00:00',
    ]);

    $metrics = $stmt->fetch();
    if (!$metrics) {
        throw new RuntimeException('Unable to calculate branch analytics.');
    }

    return [
        'revenue' => (float) $metrics['revenue'],
        'new_members' => (int) $metrics['new_members'],
        'active_members' => (int) $metrics['active_members'],
        'attendance_visits' => (int) $metrics['attendance_visits'],
        'active_memberships' => (int) $metrics['active_memberships'],
    ];
}
