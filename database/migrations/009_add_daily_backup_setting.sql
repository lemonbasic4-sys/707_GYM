-- Adds an application-controlled switch for scheduled daily backups.
CREATE TABLE IF NOT EXISTS `backup_settings` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `daily_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `backup_settings` (`id`, `daily_enabled`) VALUES (1, 0);
