-- ===========================
-- System defaults
-- ===========================
USE `gym_707`;

INSERT IGNORE INTO `admins` (`id`, `username`, `password`)
VALUES (1, 'admin', '$2y$10$AkIiih8j2ZTfcVXQDpjEY.QjiDt8InDdLjb1EXbOXbQZRWQ2o347W');

INSERT IGNORE INTO `backup_settings` (`id`, `daily_enabled`) VALUES (1, 0);
