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

function hasBranchDataSnapshotsSchema(PDO $db): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'branch_data_snapshots'"
    );
    $stmt->execute();

    return (int) $stmt->fetchColumn() === 1;
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

function getImportedBranchAnalytics(array $data, string $month): array
{
    if (!isValidAnalyticsMonth($month)) {
        throw new InvalidArgumentException('A valid YYYY-MM analytics month is required.');
    }

    $metrics = [
        'revenue' => 0.0,
        'new_members' => 0,
        'active_members' => 0,
        'attendance_visits' => 0,
        'active_memberships' => 0,
    ];
    foreach ($data['members'] ?? [] as $member) {
        if (($member['status'] ?? '') === 'active') {
            $metrics['active_members']++;
        }
        if (isset($member['created_at']) && substr((string) $member['created_at'], 0, 7) === $month) {
            $metrics['new_members']++;
        }
    }
    foreach ($data['memberships'] ?? [] as $membership) {
        if (($membership['status'] ?? '') === 'active') {
            $metrics['active_memberships']++;
        }
    }
    foreach ($data['attendance'] ?? [] as $attendance) {
        if (isset($attendance['date']) && substr((string) $attendance['date'], 0, 7) === $month) {
            $metrics['attendance_visits']++;
        }
    }
    foreach ($data['payments'] ?? [] as $payment) {
        if (($payment['status'] ?? '') === 'paid'
            && isset($payment['payment_date'])
            && substr((string) $payment['payment_date'], 0, 7) === $month) {
            $metrics['revenue'] += (float) ($payment['amount'] ?? 0);
        }
    }

    return $metrics;
}
