-- Migration V005: Add display_name to tbluser
-- This separates the user's editable display name from their strict legal FullName.

ALTER TABLE `tbluser`
ADD COLUMN `display_name` varchar(120) DEFAULT NULL AFTER `FullName`;

-- Backfill display_name with existing FullName
UPDATE `tbluser`
SET `display_name` = `FullName`
WHERE `display_name` IS NULL;
