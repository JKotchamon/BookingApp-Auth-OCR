-- Migration to increase column sizes for RSA encrypted fields
-- RSA-2048 ciphertexts when Base64 encoded are 344 bytes long, so VARBINARY(128) is too small.

ALTER TABLE `tbl_kyc_records` 
  MODIFY `date_of_birth_enc` VARBINARY(512) DEFAULT NULL,
  MODIFY `document_number_enc` VARBINARY(512) DEFAULT NULL;
