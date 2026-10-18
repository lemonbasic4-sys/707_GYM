CREATE TABLE IF NOT EXISTS `branch_analytics_settings` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `branch_name` VARCHAR(40) NOT NULL DEFAULT '707_GYM_MAIN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `branch_analytics_settings` (`id`, `branch_name`)
VALUES (1, '707_GYM_MAIN')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

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
