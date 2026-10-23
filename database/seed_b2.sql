-- Mock data for branch 2. Import only into the separate branch 2 database.
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

REPLACE INTO `admins` (`id`, `username`, `password`, `last_login`, `created_at`)
VALUES (1, 'admin', '$2y$10$HOv7BsuocpSg7h2IFlyeUOllgxyvTCGRJpa4PMRojedG3x4uVzRMq', NULL, NOW());
REPLACE INTO `branch_analytics_settings` (`id`, `branch_name`)
VALUES (1, '707_GYM_2');

REPLACE INTO `membership_plans` (`id`, `name`, `description`, `duration_days`, `price`, `status`) VALUES
(1, 'Monthly Unlimited', 'Full gym access for 30 days', 30, 800.00, 'active'),
(2, 'Quarterly Advantage', 'Full gym access for 90 days', 90, 2200.00, 'active'),
(3, 'Annual VIP Membership', 'Full gym access for one year', 365, 7500.00, 'active');

REPLACE INTO `members`
    (`id`, `first_name`, `last_name`, `phone`, `gender`, `date_of_birth`, `photo`,
     `status`, `created_at`, `updated_at`)
VALUES
(1, 'Nathan', 'Mercado', '0917-331-1001', 'male', '1993-01-22', NULL, 'active', '2026-07-04 09:00:00', NOW()),
(2, 'Andrea', 'Salazar', '0917-331-1002', 'female', '1997-04-13', NULL, 'active', '2026-07-21 09:00:00', NOW()),
(3, 'Luis', 'Domingo', '0917-331-1003', 'male', '1987-08-08', NULL, 'active', '2026-08-05 09:00:00', NOW()),
(4, 'Mikaela', 'Estrada', '0917-331-1004', 'female', '2000-11-28', NULL, 'active', '2026-08-23 09:00:00', NOW()),
(5, 'Joshua', 'Pascual', '0917-331-1005', 'male', '1994-06-16', NULL, 'active', '2026-09-09 09:00:00', NOW()),
(6, 'Bianca', 'Soriano', '0917-331-1006', 'female', '1991-02-04', NULL, 'active', '2026-09-25 09:00:00', NOW()),
(7, 'Gabriel', 'Ocampo', '0917-331-1007', 'male', '1999-07-12', NULL, 'active', '2026-10-12 09:00:00', NOW()),
(8, 'Trisha', 'Manalo', '0917-331-1008', 'female', '1989-12-20', NULL, 'active', '2026-07-28 09:00:00', NOW());

REPLACE INTO `memberships`
    (`id`, `member_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`)
VALUES
(1, 1, 2, '2026-07-04', '2026-10-02', 'expired', '2026-07-04 09:00:00', NOW()),
(2, 1, 2, '2026-10-02', '2026-12-31', 'active', '2026-10-02 09:00:00', NOW()),
(3, 2, 1, '2026-07-21', '2026-08-20', 'expired', '2026-07-21 09:00:00', NOW()),
(4, 2, 1, '2026-08-20', '2026-09-19', 'expired', '2026-08-20 09:00:00', NOW()),
(5, 2, 1, '2026-09-19', '2026-10-20', 'active', '2026-09-19 09:00:00', NOW()),
(6, 3, 3, '2026-08-05', '2027-08-05', 'active', '2026-08-05 09:00:00', NOW()),
(7, 4, 1, '2026-08-23', '2026-09-22', 'expired', '2026-08-23 09:00:00', NOW()),
(8, 5, 1, '2026-09-09', '2026-10-09', 'expired', '2026-09-09 09:00:00', NOW()),
(9, 5, 1, '2026-10-09', '2026-11-08', 'active', '2026-10-09 09:00:00', NOW()),
(10, 6, 2, '2026-09-25', '2026-12-24', 'active', '2026-09-25 09:00:00', NOW()),
(11, 7, 1, '2026-10-12', '2026-11-11', 'active', '2026-10-12 09:00:00', NOW()),
(12, 8, 3, '2026-07-28', '2027-07-28', 'active', '2026-07-28 09:00:00', NOW());

REPLACE INTO `payments`
    (`transaction_id`, `transaction_type`, `member_id`, `membership_id`, `amount`,
     `payment_method`, `notes`, `recorded_by`, `payment_date`, `status`, `created_at`)
VALUES
('TXN-B2-M-0001', 'membership_renewal', 1, 1, 2200.00, 'bank_transfer', 'Quarterly activation', 1, '2026-07-04', 'paid', '2026-07-04 09:00:00'),
('TXN-B2-M-0002', 'membership_renewal', 1, 2, 2200.00, 'gcash', 'Quarterly renewal', 1, '2026-10-02', 'paid', '2026-10-02 09:00:00'),
('TXN-B2-M-0003', 'membership_renewal', 2, 3, 800.00, 'cash', 'Monthly activation', 1, '2026-07-21', 'paid', '2026-07-21 09:00:00'),
('TXN-B2-M-0004', 'membership_renewal', 2, 4, 800.00, 'gcash', 'Monthly renewal', 1, '2026-08-20', 'paid', '2026-08-20 09:00:00'),
('TXN-B2-M-0005', 'membership_renewal', 2, 5, 800.00, 'cash', 'Monthly renewal', 1, '2026-09-19', 'paid', '2026-09-19 09:00:00'),
('TXN-B2-M-0006', 'membership_renewal', 3, 6, 7500.00, 'card', 'Annual VIP activation', 1, '2026-08-05', 'paid', '2026-08-05 09:00:00'),
('TXN-B2-M-0007', 'membership_renewal', 4, 7, 800.00, 'cash', 'Monthly activation', 1, '2026-08-23', 'paid', '2026-08-23 09:00:00'),
('TXN-B2-M-0008', 'membership_renewal', 5, 8, 800.00, 'gcash', 'Monthly activation', 1, '2026-09-09', 'paid', '2026-09-09 09:00:00'),
('TXN-B2-M-0009', 'membership_renewal', 5, 9, 800.00, 'cash', 'Monthly renewal', 1, '2026-10-09', 'paid', '2026-10-09 09:00:00'),
('TXN-B2-M-0010', 'membership_renewal', 6, 10, 2200.00, 'bank_transfer', 'Quarterly activation', 1, '2026-09-25', 'paid', '2026-09-25 09:00:00'),
('TXN-B2-M-0011', 'membership_renewal', 7, 11, 800.00, 'cash', 'Monthly activation', 1, '2026-10-12', 'paid', '2026-10-12 09:00:00'),
('TXN-B2-M-0012', 'membership_renewal', 8, 12, 7500.00, 'card', 'Annual VIP activation', 1, '2026-07-28', 'paid', '2026-07-28 09:00:00');

REPLACE INTO `payments`
    (`transaction_id`, `transaction_type`, `member_id`, `membership_id`, `amount`,
     `payment_method`, `notes`, `recorded_by`, `payment_date`, `status`, `created_at`)
SELECT CONCAT('TXN-B2-W-', DATE_FORMAT(w.visit_date, '%Y%m%d')), 'walk_in_pass',
       (w.day_offset MOD 8) + 1, NULL, 500.00 + (w.day_offset MOD 3) * 50.00,
       CASE w.day_offset MOD 4 WHEN 0 THEN 'cash' WHEN 1 THEN 'gcash' WHEN 2 THEN 'bank_transfer' ELSE 'card' END,
       'Mock walk-in pass', 1, w.visit_date, 'paid',
       IF(w.visit_date = CURDATE(), NOW(), TIMESTAMP(w.visit_date, '13:00:00'))
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
  AND w.day_offset MOD 4 = 0;

REPLACE INTO `attendance` (`member_id`, `check_in`, `check_out`, `date`, `created_at`)
SELECT a.member_id, a.check_in,
       IF(a.visit_date = CURDATE() AND a.slot_no = 0, NULL,
          DATE_ADD(a.check_in, INTERVAL (40 + a.day_offset MOD 110) MINUTE)),
       a.visit_date, a.check_in
FROM (
    SELECT ((d.day_offset * 5 + s.slot_no * 3) MOD 8) + 1 member_id,
           d.visit_date, d.day_offset, s.slot_no,
           IF(d.visit_date = CURDATE(), DATE_SUB(NOW(), INTERVAL (35 + s.slot_no * 6) MINUTE),
              DATE_ADD(TIMESTAMP(d.visit_date, '07:00:00'), INTERVAL ((d.day_offset * 19 + s.slot_no * 53) MOD 600) MINUTE)) check_in
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
