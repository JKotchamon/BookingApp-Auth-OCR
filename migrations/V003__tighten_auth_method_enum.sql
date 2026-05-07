-- =============================================================================
-- Migration V003 — Tighten auth_method to ENUM + add ON DELETE CASCADE
-- =============================================================================
-- Depends on: V001 (tbl_oauth_links and auth_method column must exist)
--
-- Two changes inspired by comparing the original draft migration:
--
--   1. auth_method VARCHAR(20) → ENUM('local','oauth','both')
--      Why: the database itself now rejects any value outside the three
--      valid states, eliminating silent typo bugs. The three values map
--      exactly to the business states the application uses:
--        local  — email+password only, no OAuth linked
--        oauth  — OAuth only, no local password (Password = '')
--        both   — local password AND at least one OAuth provider linked
--
--   2. tbl_oauth_links gets ON DELETE CASCADE on its FK to tbluser
--      Why: without this, deleting a user leaves orphaned rows in
--      tbl_oauth_links. CASCADE ensures automatic cleanup.
--
-- SAFE TO RE-RUN:
--   - MODIFY COLUMN is idempotent (MySQL silently no-ops if already ENUM).
--   - FK addition is guarded by an information_schema check.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. Change auth_method from VARCHAR(20) to ENUM('local','oauth','both')
--
--    Existing data safety: the application only ever writes 'local', 'oauth',
--    or 'both' — so no row will be rejected by the stricter type.
--    Any row written by V001's DEFAULT 'local' is already valid.
-- ---------------------------------------------------------------------------

ALTER TABLE `tbluser`
  MODIFY COLUMN `auth_method`
    ENUM('local', 'oauth', 'both')
    COLLATE utf8mb4_general_ci
    NOT NULL
    DEFAULT 'local';

-- ---------------------------------------------------------------------------
-- 2. Add ON DELETE CASCADE to tbl_oauth_links.fk_oauth_links_user
--
--    V001 created tbl_oauth_links without a foreign key at all.
--    We add it here, guarded so re-running is safe.
--
--    Steps:
--      a) Drop the FK if it somehow already exists (handles re-runs).
--      b) Re-create it with ON DELETE CASCADE.
-- ---------------------------------------------------------------------------

-- Drop existing FK if present (idempotent guard)
SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA    = DATABASE()
    AND TABLE_NAME      = 'tbl_oauth_links'
    AND CONSTRAINT_NAME = 'fk_oauth_links_user'
    AND REFERENCED_TABLE_NAME IS NOT NULL
);

SET @drop_fk := IF(
  @fk_exists > 0,
  'ALTER TABLE `tbl_oauth_links` DROP FOREIGN KEY `fk_oauth_links_user`',
  'SELECT 1'
);
PREPARE stmt FROM @drop_fk; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add FK with ON DELETE CASCADE
ALTER TABLE `tbl_oauth_links`
  ADD CONSTRAINT `fk_oauth_links_user`
    FOREIGN KEY (`UserID`)
    REFERENCES `tbluser` (`ID`)
    ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- End of V003
