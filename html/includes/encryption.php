<?php
/**
 * HBMS Security Engine - Asymmetric Hybrid Encryption Module
 * 
 * This file handles encrypting personal data and passport images securely.
 * It uses Public Key cryptography so the server can encrypt data, but
 * the server cannot decrypt it. Only the Admin's private key can decrypt.
 */

/**
 * Gets the RSA Public Key from the server folder.
 * 
 * @return string|false The public key file contents, or false if not found.
 */
function getPublicKey() {
    $keyPath = __DIR__ . '/../keys/kyc_public_key.pem';
    if (file_exists($keyPath)) {
        return file_get_contents($keyPath);
    }
    return false;
}

/**
 * Encrypts short text (like Name or Passport Number) using the Public Key.
 * 
 * @param string $data The plain text to encrypt.
 * @return string|null The encrypted text (Base64 format), or null if failed.
 */
function encryptTextRSA($data) {
    if (empty($data)) return null;
    $pubKey = getPublicKey();
    if (!$pubKey) return null;

    $encrypted = '';
    // Encrypt the text using the Public Key
    if (openssl_public_encrypt($data, $encrypted, $pubKey)) {
        return base64_encode($encrypted);
    }
    return null;
}

/**
 * Encrypts large files (like images) using a mix of AES and RSA (Hybrid).
 * RSA is too slow for big files, so we encrypt the file with a random AES key,
 * and then encrypt that random AES key with RSA.
 * 
 * @param string $sourceFilePath Where the original image is stored.
 * @param string $destFilePath   Where to save the encrypted image.
 * @return array|false Returns the encrypted AES key and IV, or false if failed.
 */
function encryptFileHybrid($sourceFilePath, $destFilePath) {
    $pubKey = getPublicKey();
    if (!$pubKey || !file_exists($sourceFilePath)) return false;

    // 1. Generate a random AES key and an IV (Initialization Vector)
    $cipher = "aes-256-gcm";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $symmetricKey = openssl_random_pseudo_bytes(32); // 256-bit AES key

    // 2. Encrypt the image file using the random AES key
    $plaintext = file_get_contents($sourceFilePath);
    $ciphertext = openssl_encrypt($plaintext, $cipher, $symmetricKey, OPENSSL_RAW_DATA, $iv, $tag);
    
    // Save the encrypted image and the security tag to the new file path
    file_put_contents($destFilePath, $ciphertext . $tag);

    // 3. Encrypt the random AES key using the RSA Public Key
    $encryptedKey = '';
    if (!openssl_public_encrypt($symmetricKey, $encryptedKey, $pubKey)) {
        return false;
    }

    // 4. Delete the original, unencrypted image file permanently
    unlink($sourceFilePath);

    return [
        'encrypted_key' => base64_encode($encryptedKey),
        'iv' => base64_encode($iv)
    ];
}

/**
 * Creates a unique, non-reversible hash to check for duplicates
 * without revealing the actual passport number.
 * 
 * @param string $data The text to hash (e.g., Passport Number).
 * @return string|null The resulting hash string.
 */
function computeBlindIndex($data) {
    if (empty($data)) return null;
    $pepper = getenv('KYC_BLIND_INDEX_PEPPER') ?: 'HBMS_PEPPER_2024_STRICT';
    return hash_hmac('sha256', strtoupper(trim($data)), $pepper);
}
?>
