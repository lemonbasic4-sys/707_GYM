-- Removes member email and address data and columns.
-- Back up the database before applying this migration.
ALTER TABLE `members`
    DROP COLUMN `email`,
    DROP COLUMN `address`;
