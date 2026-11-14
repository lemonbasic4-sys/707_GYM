-- ===========================
-- Client membership plans
-- ===========================
USE `gym_707`;

REPLACE INTO `membership_plans` (`id`, `name`, `description`, `duration_days`, `price`, `status`)
VALUES (1, '6 Months Membership', 'Walk-in rate is PHP 70 while this membership is active.', 180, 300.00, 'active');

REPLACE INTO `membership_plans` (`id`, `name`, `description`, `duration_days`, `price`, `status`)
VALUES (2, '1 Year Membership', 'Walk-in rate is PHP 70 while this membership is active.', 365, 500.00, 'active');

REPLACE INTO `membership_plans` (`id`, `name`, `description`, `duration_days`, `price`, `status`)
VALUES (3, '1 Month Subscription', 'Requires an active 6-month or 1-year membership. No daily visit fee during the subscription.', 30, 1200.00, 'active');
