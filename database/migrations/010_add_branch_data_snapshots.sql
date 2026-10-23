CREATE TABLE IF NOT EXISTS `branch_data_snapshots` (
    `branch_name` VARCHAR(40) NOT NULL PRIMARY KEY,
    `exported_at` DATETIME NOT NULL,
    `source_file` VARCHAR(255) NOT NULL,
    `snapshot_json` LONGTEXT NOT NULL,
    `imported_by` INT DEFAULT NULL,
    `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_branch_data_admin` FOREIGN KEY (`imported_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
