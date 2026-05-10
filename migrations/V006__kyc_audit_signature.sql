-- Migration V006: Add tamper-evident signature to KYC audit logs
-- Part of Production Hardening

ALTER TABLE tbl_kyc_audit_log 
ADD COLUMN log_signature VARCHAR(64) AFTER user_agent;
