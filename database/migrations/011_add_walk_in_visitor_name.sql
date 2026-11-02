-- Apply once to existing databases before using visitor walk-in checkout.
ALTER TABLE `payments`
    MODIFY COLUMN `member_id` INT DEFAULT NULL,
    ADD COLUMN `visitor_name` VARCHAR(100) DEFAULT NULL AFTER `member_id`;
