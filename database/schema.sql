-- 707 GYM Database Schema
-- Centralized Gym Record-Keeping and Management System

CREATE DATABASE IF NOT EXISTS `gym_707` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gym_707`;

-- Safe to re-run: existing tables and administrator records are preserved.
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admins` (`id`, `username`, `password`)
VALUES (1, 'admin', '$2y$10$AkIiih8j2ZTfcVXQDpjEY.QjiDt8InDdLjb1EXbOXbQZRWQ2o347W');

-- 2. Membership Plans Table
CREATE TABLE IF NOT EXISTS `membership_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `duration_days` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Members Table
CREATE TABLE IF NOT EXISTS `members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `gender` ENUM('male', 'female', 'other') DEFAULT 'other',
    `date_of_birth` DATE DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'archived') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Memberships Table
CREATE TABLE IF NOT EXISTS `memberships` (
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
CREATE TABLE IF NOT EXISTS `attendance` (
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
CREATE TABLE IF NOT EXISTS `payments` (
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
CREATE TABLE IF NOT EXISTS `import_logs` (
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

-- 8. Database Backup Settings
CREATE TABLE IF NOT EXISTS `backup_settings` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `daily_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `backup_settings` (`id`, `daily_enabled`) VALUES (1, 0);

-- 9. Branch Analytics Snapshots
CREATE TABLE IF NOT EXISTS `branch_analytics_settings` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `branch_name` VARCHAR(40) NOT NULL DEFAULT '707_GYM_MAIN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `branch_analytics_settings` (`id`, `branch_name`) VALUES (1, '707_GYM_MAIN');

CREATE TABLE IF NOT EXISTS `branch_analytics_snapshots` (
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

CREATE TABLE IF NOT EXISTS `branch_data_snapshots` (
    `branch_name` VARCHAR(40) NOT NULL PRIMARY KEY,
    `exported_at` DATETIME NOT NULL,
    `source_file` VARCHAR(255) NOT NULL,
    `snapshot_json` LONGTEXT NOT NULL,
    `imported_by` INT DEFAULT NULL,
    `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_branch_data_admin` FOREIGN KEY (`imported_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Activity Logs / Audit Trail Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
