-- Migration to add columns for Hybrid Asymmetric Encryption
-- Adds columns to store the RSA-encrypted AES symmetric key and IV for each passport image.

ALTER TABLE tbl_kyc_records 
ADD COLUMN symmetric_key_enc TEXT DEFAULT NULL, 
ADD COLUMN iv VARCHAR(32) DEFAULT NULL,
ADD COLUMN temp_image_path VARCHAR(255) DEFAULT NULL;
