-- =============================================================================
-- Migration V001 — OAuth user columns + support tables
-- =============================================================================
-- Baseline: hbms_backup.sql  (tbluser has only 6 columns — no OAuth fields)
-- Result  : tbluser gains auth_method, oauth_provider, oauth_id, DateOfBirth,
--           ProfilePhoto; three new tables are created for OAuth linking, set-
--           password tokens, and email/account-link verification tokens.
--
-- SAFE TO RE-RUN: every ALTER uses IF NOT EXISTS; every CREATE uses
--                 IF NOT EXISTS so this is fully idempotent.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. tbluser — add OAuth columns (idempotent: IF NOT EXISTS each column)
-- ---------------------------------------------------------------------------

ALTER TABLE `tbluser`
  ADD COLUMN IF NOT EXISTS `auth_method`    VARCHAR(20)  COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'local'
      AFTER `Password`,
  ADD COLUMN IF NOT EXISTS `oauth_provider` VARCHAR(20)  COLLATE utf8mb4_general_ci DEFAULT NULL
      AFTER `auth_method`,
  ADD COLUMN IF NOT EXISTS `oauth_id`       VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL
      AFTER `oauth_provider`,
  ADD COLUMN IF NOT EXISTS `DateOfBirth`    DATE                                    DEFAULT NULL
      AFTER `oauth_id`,
  ADD COLUMN IF NOT EXISTS `ProfilePhoto`   VARCHAR(500) COLLATE utf8mb4_general_ci DEFAULT NULL
      AFTER `DateOfBirth`;

-- Add indexes if they don't already exist
-- (MySQL will error on duplicate key names so we use a procedure to guard)
SET @exists_email_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbluser'
    AND INDEX_NAME = 'idx_tbluser_email'
);
SET @sql_email_idx := IF(@exists_email_idx = 0,
  'ALTER TABLE `tbluser` ADD KEY `idx_tbluser_email` (`Email`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_email_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_oauth_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbluser'
    AND INDEX_NAME = 'idx_tbluser_oauth'
);
SET @sql_oauth_idx := IF(@exists_oauth_idx = 0,
  'ALTER TABLE `tbluser` ADD KEY `idx_tbluser_oauth` (`oauth_provider`, `oauth_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_oauth_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. tbl_oauth_links — one row per (user, provider) pair
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tbl_oauth_links` (
  `ID`             INT          NOT NULL AUTO_INCREMENT,
  `UserID`         INT          NOT NULL,
  `Provider`       VARCHAR(20)  COLLATE utf8mb4_general_ci NOT NULL,
  `ProviderUserID` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ProviderEmail`  VARCHAR(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `EmailVerified`  TINYINT(1)   NOT NULL DEFAULT '0',
  `LinkedAt`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uniq_user_provider` (`UserID`, `Provider`),
  KEY `idx_provider_lookup` (`Provider`, `ProviderUserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 3. tbl_password_set_tokens — one-time tokens for OAuth → local password
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tbl_password_set_tokens` (
  `ID`        INT      NOT NULL AUTO_INCREMENT,
  `Token`     CHAR(64) COLLATE utf8mb4_general_ci NOT NULL,
  `UserID`    INT      NOT NULL,
  `ExpiresAt` DATETIME NOT NULL,
  `UsedAt`    DATETIME DEFAULT NULL,
  `CreatedAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Token` (`Token`),
  KEY `idx_user` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 4. tbl_email_verifications — consent tokens for Case 2 account linking
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tbl_email_verifications` (
  `ID`             INT          NOT NULL AUTO_INCREMENT,
  `Token`          CHAR(64)     COLLATE utf8mb4_general_ci NOT NULL,
  `UserID`         INT          NOT NULL,
  `Provider`       VARCHAR(20)  COLLATE utf8mb4_general_ci NOT NULL,
  `ProviderUserID` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ProviderEmail`  VARCHAR(200) COLLATE utf8mb4_general_ci NOT NULL,
  `EmailVerified`  TINYINT(1)   NOT NULL DEFAULT '0',
  `FullName`       VARCHAR(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `PhotoPath`      VARCHAR(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `DateOfBirth`    DATE                                    DEFAULT NULL,
  `ExpiresAt`      DATETIME NOT NULL,
  `UsedAt`         DATETIME DEFAULT NULL,
  `CreatedAt`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Token` (`Token`),
  KEY `idx_user_provider` (`UserID`, `Provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- End of V001
