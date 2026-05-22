-- Migration to add key_fingerprint column to tbl_kyc_records
-- Tracks which asymmetric public key was used to encrypt each customer's data

SET NAMES utf8mb4;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_kyc_records' AND COLUMN_NAME = 'key_fingerprint');
SET @sql := IF(@col = 0, 'ALTER TABLE `tbl_kyc_records` ADD COLUMN `key_fingerprint` VARCHAR(64) DEFAULT NULL AFTER `iv`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
