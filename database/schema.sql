-- 707 GYM Database Schema
-- Centralized Gym Record-Keeping and Management System

CREATE DATABASE IF NOT EXISTS `gym_707` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gym_707`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `branches`;

-- 1. Admins Table
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Membership Plans Table
DROP TABLE IF EXISTS `membership_plans`;
CREATE TABLE `membership_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `duration_days` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Members Table
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `gender` ENUM('male', 'female', 'other') DEFAULT 'other',
    `date_of_birth` DATE DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('active', 'archived') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Memberships Table
DROP TABLE IF EXISTS `memberships`;
CREATE TABLE `memberships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `plan_id` INT NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('active', 'expired', 'inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_memberships_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_memberships_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Attendance Table
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `check_in` DATETIME NOT NULL,
    `check_out` DATETIME DEFAULT NULL,
    `date` DATE NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_member_date` (`member_id`, `date`),
    CONSTRAINT `fk_attendance_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Payments Table
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` VARCHAR(50) NOT NULL UNIQUE,
    `transaction_type` VARCHAR(40) NOT NULL DEFAULT 'manual',
    `member_id` INT NOT NULL,
    `membership_id` INT DEFAULT NULL,
    `amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash', 'gcash', 'bank_transfer', 'card') DEFAULT 'cash',
    `notes` TEXT DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `payment_date` DATE NOT NULL,
    `status` ENUM('paid', 'partial', 'unpaid') DEFAULT 'paid',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payments_membership` FOREIGN KEY (`membership_id`) REFERENCES `memberships` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_admin` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. CSV Import Logs Table
DROP TABLE IF EXISTS `import_logs`;
CREATE TABLE `import_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `import_type` VARCHAR(50) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `total_rows` INT DEFAULT 0,
    `imported` INT DEFAULT 0,
    `updated` INT DEFAULT 0,
    `skipped` INT DEFAULT 0,
    `errors` INT DEFAULT 0,
    `error_details` LONGTEXT DEFAULT NULL,
    `imported_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_import_admin` FOREIGN KEY (`imported_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Branch Analytics Snapshots
DROP TABLE IF EXISTS `branch_analytics_snapshots`;
DROP TABLE IF EXISTS `branch_analytics_settings`;
CREATE TABLE `branch_analytics_settings` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `branch_name` VARCHAR(40) NOT NULL DEFAULT '707_GYM_MAIN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `branch_analytics_settings` (`id`, `branch_name`) VALUES (1, '707_GYM_MAIN');

CREATE TABLE `branch_analytics_snapshots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_name` VARCHAR(40) NOT NULL,
    `period_month` CHAR(7) NOT NULL,
    `captured_at` DATETIME NOT NULL,
    `revenue` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `new_members` INT UNSIGNED NOT NULL DEFAULT 0,
    `active_members` INT UNSIGNED NOT NULL DEFAULT 0,
    `attendance_visits` INT UNSIGNED NOT NULL DEFAULT 0,
    `active_memberships` INT UNSIGNED NOT NULL DEFAULT 0,
    `source_file` VARCHAR(255) NOT NULL,
    `imported_by` INT DEFAULT NULL,
    `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_branch_analytics_branch_month` (`branch_name`, `period_month`),
    CONSTRAINT `fk_branch_analytics_admin` FOREIGN KEY (`imported_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Activity Logs / Audit Trail Table
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
