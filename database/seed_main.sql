-- Mock data for the main 707 GYM branch.
-- Intended for a fresh development database. This replaces existing demo data.
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
ON DUPLICATE KEY UPDATE
    `username` = VALUES(`username`),
    `password` = VALUES(`password`),
    `last_login` = NULL;

INSERT INTO `branch_analytics_settings` (`id`, `branch_name`)
VALUES (1, '707_GYM_MAIN')
ON DUPLICATE KEY UPDATE `branch_name` = VALUES(`branch_name`);

INSERT INTO `membership_plans` (`id`, `name`, `description`, `duration_days`, `price`, `status`) VALUES
(1, 'Monthly Unlimited', 'Full gym access for 30 days', 30, 800.00, 'active'),
(2, 'Quarterly Advantage', 'Full gym access for 90 days', 90, 2200.00, 'active'),
(3, 'Annual VIP Membership', 'Full gym access for one year', 365, 7500.00, 'active');

-- Member details are complete for all fields currently used by the system.
INSERT INTO `members`
    (`id`, `first_name`, `last_name`, `phone`, `gender`, `date_of_birth`, `photo`,
     `emergency_contact_name`, `emergency_contact_phone`, `status`, `created_at`, `updated_at`)
VALUES
(1, 'Juan', 'Dela Cruz', '0917-111-1001', 'male', '1994-03-12', NULL, 'Rosa Dela Cruz', '0917-111-2001', 'active', '2026-07-01 09:00:00', NOW()),
(2, 'Maria', 'Santos', '0917-111-1002', 'female', '1998-08-24', NULL, 'Luis Santos', '0917-111-2002', 'active', '2026-07-15 09:00:00', NOW()),
(3, 'Carlos', 'Reyes', '0917-111-1003', 'male', '1991-11-05', NULL, 'Elena Reyes', '0917-111-2003', 'active', '2026-08-01 09:00:00', NOW()),
(4, 'Elena', 'Torres', '0917-111-1004', 'female', '2000-01-17', NULL, 'Marco Torres', '0917-111-2004', 'active', '2026-07-05 09:00:00', NOW()),
(5, 'Gabriel', 'Mendoza', '0917-111-1005', 'male', '1996-06-29', NULL, 'Ana Mendoza', '0917-111-2005', 'active', '2026-09-20 09:00:00', NOW()),
(6, 'Sofia', 'Villanueva', '0917-111-1006', 'female', '1993-04-02', NULL, 'Ramon Villanueva', '0917-111-2006', 'active', '2026-08-25 09:00:00', NOW()),
(7, 'Miguel', 'Castillo', '0917-111-1007', 'male', '1989-12-14', NULL, 'Teresa Castillo', '0917-111-2007', 'active', '2026-10-15 09:00:00', NOW()),
(8, 'Isabella', 'Garcia', '0917-111-1008', 'female', '2001-07-08', NULL, 'Rafael Garcia', '0917-111-2008', 'active', '2026-07-10 09:00:00', NOW()),
(9, 'Andres', 'Ramos', '0917-111-1009', 'male', '1997-02-19', NULL, 'Lucia Ramos', '0917-111-2009', 'active', '2026-08-10 09:00:00', NOW()),
(10, 'Camila', 'Flores', '0917-111-1010', 'female', '1995-10-31', NULL, 'Pedro Flores', '0917-111-2010', 'active', '2026-09-01 09:00:00', NOW()),
(11, 'Daniel', 'Aquino', '0917-111-1011', 'male', '2002-05-23', NULL, 'Mila Aquino', '0917-111-2011', 'active', '2026-10-19 09:00:00', NOW()),
(12, 'Patricia', 'Navarro', '0917-111-1012', 'female', '1988-09-09', NULL, 'Jose Navarro', '0917-111-2012', 'archived', '2026-07-20 09:00:00', NOW());

INSERT INTO `memberships`
    (`id`, `member_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`)
VALUES
(1, 1, 1, '2026-07-01', '2026-07-31', 'expired', '2026-07-01 09:00:00', NOW()),
(2, 1, 1, '2026-07-31', '2026-08-30', 'expired', '2026-07-31 09:00:00', NOW()),
(3, 1, 1, '2026-08-30', '2026-09-29', 'expired', '2026-08-30 09:00:00', NOW()),
(4, 1, 1, '2026-09-29', '2026-10-29', 'active', '2026-09-29 09:00:00', NOW()),
(5, 2, 2, '2026-07-15', '2026-10-13', 'expired', '2026-07-15 09:00:00', NOW()),
(6, 2, 2, '2026-10-13', '2027-01-11', 'active', '2026-10-13 09:00:00', NOW()),
(7, 3, 3, '2026-08-01', '2027-08-01', 'active', '2026-08-01 09:00:00', NOW()),
(8, 4, 1, '2026-07-05', '2026-08-04', 'expired', '2026-07-05 09:00:00', NOW()),
(9, 5, 1, '2026-09-20', '2026-10-20', 'active', '2026-09-20 09:00:00', NOW()),
(10, 6, 2, '2026-08-25', '2026-11-23', 'active', '2026-08-25 09:00:00', NOW()),
(11, 7, 1, '2026-10-15', '2026-11-14', 'active', '2026-10-15 09:00:00', NOW()),
(12, 8, 3, '2026-07-10', '2027-07-10', 'active', '2026-07-10 09:00:00', NOW()),
(13, 9, 1, '2026-08-10', '2026-09-09', 'expired', '2026-08-10 09:00:00', NOW()),
(14, 9, 1, '2026-09-09', '2026-10-09', 'expired', '2026-09-09 09:00:00', NOW()),
(15, 9, 1, '2026-10-09', '2026-11-08', 'active', '2026-10-09 09:00:00', NOW()),
(16, 10, 2, '2026-09-01', '2026-11-30', 'active', '2026-09-01 09:00:00', NOW()),
(17, 11, 1, '2026-10-19', '2026-11-18', 'active', '2026-10-19 09:00:00', NOW()),
(18, 12, 1, '2026-07-20', '2026-08-19', 'expired', '2026-07-20 09:00:00', NOW());

INSERT INTO `payments`
    (`transaction_id`, `transaction_type`, `member_id`, `membership_id`, `amount`,
     `payment_method`, `notes`, `recorded_by`, `payment_date`, `status`, `created_at`)
VALUES
('TXN-MAIN-M-0001', 'membership_renewal', 1, 1, 800.00, 'cash', 'Monthly plan activation', 1, '2026-07-01', 'paid', '2026-07-01 09:00:00'),
('TXN-MAIN-M-0002', 'membership_renewal', 1, 2, 800.00, 'gcash', 'Monthly plan renewal', 1, '2026-07-31', 'paid', '2026-07-31 09:00:00'),
('TXN-MAIN-M-0003', 'membership_renewal', 1, 3, 800.00, 'cash', 'Monthly plan renewal', 1, '2026-08-30', 'paid', '2026-08-30 09:00:00'),
('TXN-MAIN-M-0004', 'membership_renewal', 1, 4, 800.00, 'gcash', 'Monthly plan renewal', 1, '2026-09-29', 'paid', '2026-09-29 09:00:00'),
('TXN-MAIN-M-0005', 'membership_renewal', 2, 5, 2200.00, 'bank_transfer', 'Quarterly plan activation', 1, '2026-07-15', 'paid', '2026-07-15 09:00:00'),
('TXN-MAIN-M-0006', 'membership_renewal', 2, 6, 2200.00, 'gcash', 'Quarterly plan renewal', 1, '2026-10-13', 'paid', '2026-10-13 09:00:00'),
('TXN-MAIN-M-0007', 'membership_renewal', 3, 7, 7500.00, 'card', 'Annual VIP activation', 1, '2026-08-01', 'paid', '2026-08-01 09:00:00'),
('TXN-MAIN-M-0008', 'membership_renewal', 4, 8, 800.00, 'cash', 'Monthly plan activation', 1, '2026-07-05', 'paid', '2026-07-05 09:00:00'),
('TXN-MAIN-M-0009', 'membership_renewal', 5, 9, 800.00, 'gcash', 'Monthly plan activation', 1, '2026-09-20', 'paid', '2026-09-20 09:00:00'),
('TXN-MAIN-M-0010', 'membership_renewal', 6, 10, 2200.00, 'bank_transfer', 'Quarterly plan activation', 1, '2026-08-25', 'paid', '2026-08-25 09:00:00'),
('TXN-MAIN-M-0011', 'membership_renewal', 7, 11, 800.00, 'cash', 'Monthly plan activation', 1, '2026-10-15', 'paid', '2026-10-15 09:00:00'),
('TXN-MAIN-M-0012', 'membership_renewal', 8, 12, 7500.00, 'card', 'Annual VIP activation', 1, '2026-07-10', 'paid', '2026-07-10 09:00:00'),
('TXN-MAIN-M-0013', 'membership_renewal', 9, 13, 800.00, 'cash', 'Monthly plan activation', 1, '2026-08-10', 'paid', '2026-08-10 09:00:00'),
('TXN-MAIN-M-0014', 'membership_renewal', 9, 14, 800.00, 'gcash', 'Monthly plan renewal', 1, '2026-09-09', 'paid', '2026-09-09 09:00:00'),
('TXN-MAIN-M-0015', 'membership_renewal', 9, 15, 800.00, 'cash', 'Monthly plan renewal', 1, '2026-10-09', 'paid', '2026-10-09 09:00:00'),
('TXN-MAIN-M-0016', 'membership_renewal', 10, 16, 2200.00, 'bank_transfer', 'Quarterly plan activation', 1, '2026-09-01', 'paid', '2026-09-01 09:00:00'),
('TXN-MAIN-M-0017', 'membership_renewal', 11, 17, 800.00, 'gcash', 'Monthly plan activation', 1, '2026-10-19', 'paid', NOW()),
('TXN-MAIN-M-0018', 'membership_renewal', 12, 18, 800.00, 'cash', 'Monthly plan activation', 1, '2026-07-20', 'paid', '2026-07-20 09:00:00');

-- Add a paid walk-in transaction every five days from July 2026 through today.
INSERT INTO `payments`
    (`transaction_id`, `transaction_type`, `member_id`, `membership_id`, `amount`,
     `payment_method`, `notes`, `recorded_by`, `payment_date`, `status`, `created_at`)
SELECT
    CONCAT('TXN-MAIN-W-', DATE_FORMAT(day_rows.visit_date, '%Y%m%d')),
    'walk_in_pass',
    ((day_rows.day_offset * 5) % 12) + 1,
    NULL,
    400.00 + (day_rows.day_offset MOD 5) * 50.00,
    CASE day_rows.day_offset MOD 4
        WHEN 0 THEN 'cash'
        WHEN 1 THEN 'gcash'
        WHEN 2 THEN 'bank_transfer'
        ELSE 'card'
    END,
    'Mock walk-in pass',
    1,
    day_rows.visit_date,
    'paid',
    IF(day_rows.visit_date = CURDATE(), NOW(), TIMESTAMP(day_rows.visit_date, '12:00:00'))
FROM (
    SELECT DATE_ADD('2026-07-01', INTERVAL (ones.n + tens.n * 10 + hundreds.n * 100) DAY) AS visit_date,
           (ones.n + tens.n * 10 + hundreds.n * 100) AS day_offset
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) tens
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) hundreds
) day_rows
WHERE day_rows.day_offset <= DATEDIFF(CURDATE(), '2026-07-01')
  AND day_rows.day_offset MOD 5 = 2;

-- Generate weekday and weekend visits for the full date range. Today's sample
-- check-ins use the current time, with one member left checked in.
INSERT INTO `attendance` (`member_id`, `check_in`, `check_out`, `date`, `created_at`)
SELECT
    visit_rows.member_id,
    visit_rows.check_in,
    CASE
        WHEN visit_rows.visit_date = CURDATE() AND visit_rows.slot_no = 0 THEN NULL
        ELSE DATE_ADD(visit_rows.check_in, INTERVAL (45 + (visit_rows.day_offset + visit_rows.slot_no * 11) MOD 90) MINUTE)
    END,
    visit_rows.visit_date,
    visit_rows.check_in
FROM (
    SELECT
        ((day_rows.day_offset * 5 + slots.slot_no * 7) MOD 12) + 1 AS member_id,
        day_rows.visit_date,
        day_rows.day_offset,
        slots.slot_no,
        CASE
            WHEN day_rows.visit_date = CURDATE() THEN DATE_SUB(NOW(), INTERVAL (30 + slots.slot_no * 5) MINUTE)
            ELSE DATE_ADD(TIMESTAMP(day_rows.visit_date, '06:30:00'), INTERVAL ((day_rows.day_offset * 13 + slots.slot_no * 47) MOD 600) MINUTE)
        END AS check_in
    FROM (
        SELECT DATE_ADD('2026-07-01', INTERVAL (ones.n + tens.n * 10 + hundreds.n * 100) DAY) AS visit_date,
               (ones.n + tens.n * 10 + hundreds.n * 100) AS day_offset
        FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
        CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) tens
        CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) hundreds
    ) day_rows
    CROSS JOIN (
        SELECT 0 slot_no UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
        UNION ALL SELECT 4 UNION ALL SELECT 5
    ) slots
    WHERE day_rows.day_offset <= DATEDIFF(CURDATE(), '2026-07-01')
      AND slots.slot_no < CASE WHEN DAYOFWEEK(day_rows.visit_date) IN (1, 7) THEN 3 ELSE 6 END
) visit_rows;

COMMIT;

