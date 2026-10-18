-- 707 GYM Database Seed Data
USE `gym_707`;

-- Default Admin User (username: admin, password: admin123)
-- Password hashed with PASSWORD_BCRYPT
REPLACE INTO `admins` (`id`, `username`, `email`, `password`, `name`, `created_at`) 
VALUES (1, 'admin', 'admin@707gym.com', '$2y$10$HOv7BsuocpSg7h2IFlyeUOllgxyvTCGRJpa4PMRojedG3x4uVzRMq', 'System Administrator', NOW());

-- Seed Membership Plans
REPLACE INTO `membership_plans` (`id`, `name`, `description`, `duration_days`, `price`, `status`) VALUES
(1, 'Monthly Unlimited', 'Full gym access, lockers, and showers for 30 days', 30, 800.00, 'active'),
(2, 'Quarterly Advantage', 'Full gym access plus 1 free PT session for 90 days', 90, 2200.00, 'active'),
(3, 'Annual VIP Membership', 'Year-round unlimited access with guest passes', 365, 7500.00, 'active');

-- Seed Sample Members
REPLACE INTO `members` (`id`, `first_name`, `last_name`, `email`, `phone`, `gender`, `status`, `created_at`) VALUES
(1, 'Juan', 'Dela Cruz', 'juan.delacruz@example.com', '0917-123-4567', 'male', 'active', NOW()),
(2, 'Maria', 'Santos', 'maria.santos@example.com', '0918-234-5678', 'female', 'active', NOW()),
(3, 'Carlos', 'Reyes', 'carlos.reyes@example.com', '0919-345-6789', 'male', 'active', NOW()),
(4, 'Elena', 'Torres', 'elena.torres@example.com', '0920-456-7890', 'female', 'active', NOW()),
(5, 'Gabriel', 'Mendoza', 'gabriel.mendoza@example.com', '0921-567-8901', 'male', 'active', NOW());

-- Seed Sample Memberships
REPLACE INTO `memberships` (`id`, `member_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 1, 1, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY), 'active', NOW()),
(2, 2, 2, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 90 DAY), 'active', NOW()),
(3, 3, 3, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 365 DAY), 'active', NOW()),
(4, 4, 1, DATE_SUB(CURRENT_DATE(), INTERVAL 40 DAY), DATE_SUB(CURRENT_DATE(), INTERVAL 10 DAY), 'expired', DATE_SUB(CURRENT_DATE(), INTERVAL 40 DAY)),
(5, 5, 1, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY), 'active', NOW());

-- Seed Sample Payments
REPLACE INTO `payments` (`id`, `transaction_id`, `transaction_type`, `member_id`, `membership_id`, `amount`, `payment_method`, `notes`, `recorded_by`, `payment_date`, `status`) VALUES
(1, 'TXN-707-1001', 'membership_renewal', 1, 1, 800.00, 'cash', 'Monthly activation', 1, CURRENT_DATE(), 'paid'),
(2, 'TXN-707-1002', 'membership_renewal', 2, 2, 2200.00, 'gcash', 'Quarterly subscription', 1, CURRENT_DATE(), 'paid'),
(3, 'TXN-707-1003', 'membership_renewal', 3, 3, 7500.00, 'card', 'Annual VIP membership', 1, CURRENT_DATE(), 'paid'),
(4, 'TXN-707-1004', 'membership_renewal', 5, 5, 800.00, 'gcash', 'Monthly activation', 1, CURRENT_DATE(), 'paid');