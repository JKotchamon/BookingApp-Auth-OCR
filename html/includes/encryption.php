<?php
/**
 * HBMS Security Engine - Production Hardening (Ranveer's Part)
 * Implements AES-256-GCM with Versioned Key Rotation
 */

const CURRENT_KEY_VERSION = "v1";

function getKycKey($version = CURRENT_KEY_VERSION) {
    // In production, these would be separate env variables: KYC_KEY_V1, KYC_KEY_V2, etc.
    $envVar = 'KYC_KEY_' . strtoupper($version);
    $key = getenv($envVar) ?: 'HBMS_SUPER_SECRET_KEY_123_V1'; 
    return hash('sha256', $key, true);
}

/**
 * Encrypts a field and prepends the Key Version
 */
function encryptField($data) {
    if (empty($data)) return null;
    
    $key = getKycKey(CURRENT_KEY_VERSION);
    $cipher = "aes-256-gcm";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    
    $ciphertext = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    // Format: VERSION (2 bytes) | IV | TAG | CIPHERTEXT
    // This allows the decryptor to see "v1" and know which key to grab.
    return CURRENT_KEY_VERSION . $iv . $tag . $ciphertext;
}

/**
 * Decrypts a field by detecting its Key Version
 */
function decryptField($data) {
    if (empty($data) || strlen($data) < 2) return null;
    
    // Extract version from the first 2 characters
    $version = substr($data, 0, 2);
    $payload = substr($data, 2);
    
    $key = getKycKey($version);
    $cipher = "aes-256-gcm";
    $ivlen = openssl_cipher_iv_length($cipher);
    
    $iv         = substr($payload, 0, $ivlen);
    $tag        = substr($payload, $ivlen, 16);
    $ciphertext = substr($payload, $ivlen + 16);
    
    return openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
}

/**
 * Blind Index (Fraud Prevention)
 */
function computeBlindIndex($data) {
    if (empty($data)) return null;
    // We use a separate PEPPER for hashing so it's different from the encryption key
    $pepper = getenv('KYC_BLIND_INDEX_PEPPER') ?: 'HBMS_PEPPER_2024_STRICT';
    return hash_hmac('sha256', strtoupper(trim($data)), $pepper);
}
