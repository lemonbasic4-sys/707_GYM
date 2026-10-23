<?php
/**
 * Redirect membership activation to the payment checkout workflow.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$query = ['type' => 'membership_renewal'];
$memberId = filter_input(INPUT_GET, 'member_id', FILTER_VALIDATE_INT);
if ($memberId !== false && $memberId !== null && $memberId > 0) {
    $query['member_id'] = $memberId;
}

redirect(BASE_URL . '/pages/payments/record.php?' . http_build_query($query));
