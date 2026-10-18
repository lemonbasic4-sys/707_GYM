-- Mock data for branch 1. Import only into the separate branch 1 database.
-- This replaces existing demo records in the selected database.
USE `gym_707`;

START TRANSACTION;
DELETE FROM `branch_analytics_snapshots`;
DELETE FROM `attendance`;
DELETE FROM `payments`;
DELETE FROM `memberships`;
DELETE FROM `import_logs`;
DELETE FROM `activity_logs`;
DELETE FROM `members`;
DELETE FROM `membership_plans`;

INSERT INTO `admins` (`id`, `username`, `password`, `last_login`, `created_at`)
VALUES (1, 'admin', '$2y$10$HOv7BsuocpSg7h2IFlyeUOllgxyvTCGRJpa4PMRojedG3x4uVzRMq', NULL, NOW())
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `password` = VALUES(`password`), `last_login` = NULL;
INSERT INTO `branch_analytics_settings` (`id`, `branch_name`)
VALUES (1, '707_GYM_1')
ON DUPLICATE KEY UPDATE `branch_name` = VALUES(`branch_name`);

INSERT INTO `membership_plans` (`id`, `name`, `description`, `duration_days`, `price`, `status`) VALUES
(1, 'Monthly Unlimited', 'Full gym access for 30 days', 30, 800.00, 'active'),
(2, 'Quarterly Advantage', 'Full gym access for 90 days', 90, 2200.00, 'active'),
(3, 'Annual VIP Membership', 'Full gym access for one year', 365, 7500.00, 'active');

INSERT INTO `members`
    (`id`, `first_name`, `last_name`, `phone`, `gender`, `date_of_birth`, `photo`,
     `emergency_contact_name`, `emergency_contact_phone`, `status`, `created_at`, `updated_at`)
VALUES
(1, 'Paolo', 'Bautista', '0917-221-1001', 'male', '1992-02-14', NULL, 'Liza Bautista', '0917-221-2001', 'active', '2026-07-02 09:00:00', NOW()),
(2, 'Angela', 'Cruz', '0917-221-1002', 'female', '1999-05-26', NULL, 'Mario Cruz', '0917-221-2002', 'active', '2026-07-18 09:00:00', NOW()),
(3, 'Rafael', 'Lim', '0917-221-1003', 'male', '1988-10-03', NULL, 'Cecilia Lim', '0917-221-2003', 'active', '2026-08-03 09:00:00', NOW()),
(4, 'Nicole', 'Garcia', '0917-221-1004', 'female', '1996-12-11', NULL, 'Oscar Garcia', '0917-221-2004', 'active', '2026-08-19 09:00:00', NOW()),
(5, 'Enzo', 'Aquino', '0917-221-1005', 'male', '2001-03-30', NULL, 'Mina Aquino', '0917-221-2005', 'active', '2026-09-07 09:00:00', NOW()),
(6, 'Beatrice', 'Ramos', '0917-221-1006', 'female', '1990-07-21', NULL, 'Leo Ramos', '0917-221-2006', 'active', '2026-09-22 09:00:00', NOW()),
(7, 'Marco', 'Dizon', '0917-221-1007', 'male', '1995-01-09', NULL, 'Nora Dizon', '0917-221-2007', 'active', '2026-10-10 09:00:00', NOW()),
(8, 'Celine', 'Flores', '0917-221-1008', 'female', '1993-09-16', NULL, 'Rico Flores', '0917-221-2008', 'active', '2026-07-25 09:00:00', NOW());

INSERT INTO `memberships`
    (`id`, `member_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`)
VALUES
(1, 1, 1, '2026-07-02', '2026-08-01', 'expired', '2026-07-02 09:00:00', NOW()),
(2, 1, 1, '2026-08-01', '2026-08-31', 'expired', '2026-08-01 09:00:00', NOW()),
(3, 1, 1, '2026-08-31', '2026-09-30', 'expired', '2026-08-31 09:00:00', NOW()),
(4, 1, 1, '2026-09-30', '2026-10-30', 'active', '2026-09-30 09:00:00', NOW()),
(5, 2, 2, '2026-07-18', '2026-10-16', 'expired', '2026-07-18 09:00:00', NOW()),
(6, 2, 2, '2026-10-16', '2027-01-14', 'active', '2026-10-16 09:00:00', NOW()),
(7, 3, 3, '2026-08-03', '2027-08-03', 'active', '2026-08-03 09:00:00', NOW()),
(8, 4, 1, '2026-08-19', '2026-09-18', 'expired', '2026-08-19 09:00:00', NOW()),
(9, 5, 1, '2026-09-07', '2026-10-07', 'expired', '2026-09-07 09:00:00', NOW()),
(10, 5, 1, '2026-10-07', '2026-11-06', 'active', '2026-10-07 09:00:00', NOW()),
(11, 6, 2, '2026-09-22', '2026-12-21', 'active', '2026-09-22 09:00:00', NOW()),
(12, 7, 1, '2026-10-10', '2026-11-09', 'active', '2026-10-10 09:00:00', NOW()),
(13, 8, 3, '2026-07-25', '2027-07-25', 'active', '2026-07-25 09:00:00', NOW());

INSERT INTO `payments`
    (`transaction_id`, `transaction_type`, `member_id`, `membership_id`, `amount`,
     `payment_method`, `notes`, `recorded_by`, `payment_date`, `status`, `created_at`)
VALUES
('TXN-B1-M-0001', 'membership_renewal', 1, 1, 800.00, 'cash', 'Monthly activation', 1, '2026-07-02', 'paid', '2026-07-02 09:00:00'),
('TXN-B1-M-0002', 'membership_renewal', 1, 2, 800.00, 'gcash', 'Monthly renewal', 1, '2026-08-01', 'paid', '2026-08-01 09:00:00'),
('TXN-B1-M-0003', 'membership_renewal', 1, 3, 800.00, 'cash', 'Monthly renewal', 1, '2026-08-31', 'paid', '2026-08-31 09:00:00'),
('TXN-B1-M-0004', 'membership_renewal', 1, 4, 800.00, 'gcash', 'Monthly renewal', 1, '2026-09-30', 'paid', '2026-09-30 09:00:00'),
('TXN-B1-M-0005', 'membership_renewal', 2, 5, 2200.00, 'bank_transfer', 'Quarterly activation', 1, '2026-07-18', 'paid', '2026-07-18 09:00:00'),
('TXN-B1-M-0006', 'membership_renewal', 2, 6, 2200.00, 'gcash', 'Quarterly renewal', 1, '2026-10-16', 'paid', '2026-10-16 09:00:00'),
('TXN-B1-M-0007', 'membership_renewal', 3, 7, 7500.00, 'card', 'Annual activation', 1, '2026-08-03', 'paid', '2026-08-03 09:00:00'),
('TXN-B1-M-0008', 'membership_renewal', 4, 8, 800.00, 'cash', 'Monthly activation', 1, '2026-08-19', 'paid', '2026-08-19 09:00:00'),
('TXN-B1-M-0009', 'membership_renewal', 5, 9, 800.00, 'gcash', 'Monthly activation', 1, '2026-09-07', 'paid', '2026-09-07 09:00:00'),
('TXN-B1-M-0010', 'membership_renewal', 5, 10, 800.00, 'cash', 'Monthly renewal', 1, '2026-10-07', 'paid', '2026-10-07 09:00:00'),
('TXN-B1-M-0011', 'membership_renewal', 6, 11, 2200.00, 'bank_transfer', 'Quarterly activation', 1, '2026-09-22', 'paid', '2026-09-22 09:00:00'),
('TXN-B1-M-0012', 'membership_renewal', 7, 12, 800.00, 'cash', 'Monthly activation', 1, '2026-10-10', 'paid', '2026-10-10 09:00:00'),
('TXN-B1-M-0013', 'membership_renewal', 8, 13, 7500.00, 'card', 'Annual activation', 1, '2026-07-25', 'paid', '2026-07-25 09:00:00');

INSERT INTO `payments`
    (`transaction_id`, `transaction_type`, `member_id`, `membership_id`, `amount`,
     `payment_method`, `notes`, `recorded_by`, `payment_date`, `status`, `created_at`)
SELECT CONCAT('TXN-B1-W-', DATE_FORMAT(w.visit_date, '%Y%m%d')), 'walk_in_pass',
       (w.day_offset MOD 8) + 1, NULL, 350.00 + (w.day_offset MOD 4) * 50.00,
       CASE w.day_offset MOD 3 WHEN 0 THEN 'cash' WHEN 1 THEN 'gcash' ELSE 'card' END,
       'Mock walk-in pass', 1, w.visit_date, 'paid',
       IF(w.visit_date = CURDATE(), NOW(), TIMESTAMP(w.visit_date, '12:00:00'))
FROM (
    SELECT DATE_ADD('2026-07-01', INTERVAL n DAY) visit_date, n day_offset
    FROM (
        SELECT ones.n + tens.n * 10 + hundreds.n * 100 n
        FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
        CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) tens
        CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) hundreds
    ) offsets
) w
WHERE w.day_offset <= DATEDIFF(CURDATE(), '2026-07-01')
  AND w.day_offset MOD 3 = 1;

INSERT INTO `attendance` (`member_id`, `check_in`, `check_out`, `date`, `created_at`)
SELECT a.member_id, a.check_in,
       IF(a.visit_date = CURDATE() AND a.slot_no = 0, NULL,
          DATE_ADD(a.check_in, INTERVAL (50 + a.day_offset MOD 100) MINUTE)),
       a.visit_date, a.check_in
FROM (
    SELECT ((d.day_offset * 3 + s.slot_no * 5) MOD 8) + 1 member_id,
           d.visit_date, d.day_offset, s.slot_no,
           IF(d.visit_date = CURDATE(), DATE_SUB(NOW(), INTERVAL (25 + s.slot_no * 7) MINUTE),
              DATE_ADD(TIMESTAMP(d.visit_date, '06:00:00'), INTERVAL ((d.day_offset * 17 + s.slot_no * 61) MOD 660) MINUTE)) check_in
    FROM (
        SELECT DATE_ADD('2026-07-01', INTERVAL n DAY) visit_date, n day_offset
        FROM (
            SELECT ones.n + tens.n * 10 + hundreds.n * 100 n
            FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
            CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) tens
            CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) hundreds
        ) offsets
    ) d
    CROSS JOIN (SELECT 0 slot_no UNION ALL SELECT 1 UNION ALL SELECT 2) s
    WHERE d.day_offset <= DATEDIFF(CURDATE(), '2026-07-01')
      AND s.slot_no < IF(DAYOFWEEK(d.visit_date) IN (1, 7), 2, 3)
) a;

COMMIT;
