-- Removes editable admin email and display-name fields.
-- Back up the database before applying this migration.
ALTER TABLE `admins`
    DROP COLUMN `email`,
    DROP COLUMN `name`;
