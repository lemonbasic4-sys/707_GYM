-- Apply once to existing databases before using walk-in check-out.
ALTER TABLE `payments`
    ADD COLUMN `visitor_check_in` DATETIME DEFAULT NULL AFTER `visitor_name`,
    ADD COLUMN `visitor_check_out` DATETIME DEFAULT NULL AFTER `visitor_check_in`,
    ADD INDEX `idx_payments_visitor_visit` (`transaction_type`, `visitor_check_out`, `visitor_check_in`);
