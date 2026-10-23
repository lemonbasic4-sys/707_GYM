-- Removes stored emergency contact details from member records.
-- Back up the database before applying this migration; the values are permanently deleted.
ALTER TABLE `members`
    DROP COLUMN `emergency_contact_name`,
    DROP COLUMN `emergency_contact_phone`;
