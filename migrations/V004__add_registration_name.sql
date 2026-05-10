-- Migration V004: Adding registration_name anchor
-- This column stores the user's name as it was during signup (from Google/Microsoft or registration form).
-- It acts as a non-editable "Source of Truth" for KYC fuzzy matching.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbluser' AND COLUMN_NAME = 'registration_name');
SET @sql := IF(@col = 0, 'ALTER TABLE `tbluser` ADD COLUMN `registration_name` VARCHAR(200) NULL DEFAULT NULL AFTER `FullName`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- For existing users, backfill registration_name from FullName
UPDATE `tbluser` SET `registration_name` = `FullName` WHERE `registration_name` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
