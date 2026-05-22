-- Migration V002: Adding KYC support
-- This one sets up everything we need for identity verification (KYC).
-- We're adding some status flags to the user and creating tables for document records.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Adding KYC status columns to tbluser. 
-- Again, using the information_schema check because MySQL 8 is picky about ALTER.

-- 1. Current KYC status (unverified, pending, verified, etc.)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbluser' AND COLUMN_NAME = 'kyc_status');
SET @sql := IF(@col = 0, "ALTER TABLE `tbluser` ADD COLUMN `kyc_status` ENUM('unverified','pending','verified','rejected','expired','blocked') NOT NULL DEFAULT 'unverified' AFTER `ProfilePhoto`","SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. When they were verified
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbluser' AND COLUMN_NAME = 'kyc_verified_at');
SET @sql := IF(@col = 0, 'ALTER TABLE `tbluser` ADD COLUMN `kyc_verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `kyc_status`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. When the ID expires
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbluser' AND COLUMN_NAME = 'kyc_expiry_date');
SET @sql := IF(@col = 0, 'ALTER TABLE `tbluser` ADD COLUMN `kyc_expiry_date` DATE NULL DEFAULT NULL AFTER `kyc_verified_at`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Table to store the actual KYC attempts and document data
-- We encrypt the sensitive stuff (name, dob, doc number) in PHP before storing here.
CREATE TABLE IF NOT EXISTS `tbl_kyc_records` (
  `ID`                   INT          NOT NULL AUTO_INCREMENT,
  `user_id`              INT          NOT NULL,
  `version`              INT          NOT NULL DEFAULT 1,
  `is_current`           TINYINT(1)   NOT NULL DEFAULT 0,
  `document_type`        VARCHAR(10)  DEFAULT NULL,
  `full_name_encrypted`  VARBINARY(512)  DEFAULT NULL, -- Encrypted with AES-256
  `nationality`          VARCHAR(3)   DEFAULT NULL,
  `date_of_birth_enc`    VARBINARY(512)  DEFAULT NULL, -- Encrypted DOB
  `document_number_enc`  VARBINARY(512)  DEFAULT NULL, -- Encrypted Doc Number
  `document_number_hash` VARCHAR(64)  NOT NULL,        -- Hashed for fast lookups/duplicates
  `expiry_date`          DATE         DEFAULT NULL,
  `gender`               CHAR(1)      DEFAULT NULL,
  `issuing_country`      VARCHAR(3)   DEFAULT NULL,
  `verification_status`  ENUM('pending','verified','rejected','expired','superseded','blocked') NOT NULL DEFAULT 'pending',
  `verification_method`  VARCHAR(50)  DEFAULT NULL,
  `verified_at`          TIMESTAMP    NULL DEFAULT NULL,
  `verified_by`          VARCHAR(50)  DEFAULT NULL,
  `rejection_reason`     VARCHAR(500) DEFAULT NULL,
  `mrz_checksum_valid`   TINYINT(1)   NOT NULL DEFAULT 0,
  `name_match_score`     DECIMAL(5,2) DEFAULT NULL,
  `created_at`           TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_doc_hash`     (`document_number_hash`),
  KEY `idx_user_current` (`user_id`, `is_current`),
  CONSTRAINT `fk_kyc_records_user` FOREIGN KEY (`user_id`) REFERENCES `tbluser` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit log to keep track of what happened during KYC
CREATE TABLE IF NOT EXISTS `tbl_kyc_audit_log` (
  `ID`         INT          NOT NULL AUTO_INCREMENT,
  `user_id`    INT          NOT NULL,
  `action`     VARCHAR(100) DEFAULT NULL,
  `details`    TEXT,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_audit_user` (`user_id`),
  CONSTRAINT `fk_kyc_audit_user` FOREIGN KEY (`user_id`) REFERENCES `tbluser` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Flags to catch risky bookings based on KYC
CREATE TABLE IF NOT EXISTS `tbl_booking_risk_flags` (
  `ID`          INT          NOT NULL AUTO_INCREMENT,
  `booking_id`  INT          NOT NULL,
  `user_id`     INT          NOT NULL,
  `risk_reason` VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_risk_booking` (`booking_id`),
  KEY `idx_risk_user`    (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
