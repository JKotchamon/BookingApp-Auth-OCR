<?php
/**
 * Mocks an OCR microservice request.
 * In a real environment, this would use curl to send the image to Python.
 */
function sendToOcr($imageData, $mimeType) {
    // Read from .env for production flexibility
    $url    = getenv('KYC_OCR_URL')     ?: 'http://host.docker.internal:5001/api/ocr/passport';
    $apiKey = getenv('KYC_OCR_API_KEY') ?: 'internal-secret-key';

    // Temporary file for the image to be sent via CURL
    $tmpFile = tempnam(sys_get_temp_dir(), 'ocr_');
    file_put_contents($tmpFile, $imageData);

    $ch = curl_init();
    $curlFile = new CURLFile($tmpFile, $mimeType, 'passport_image.jpg');

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['passport_image' => $curlFile]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // DEBUG: Echo raw response to screen to see structure
    if ($response) {
        // echo "<!-- DEBUG OCR RESPONSE: " . htmlspecialchars($response) . " -->";
        // If you want to see it visibly on the page for a moment:
        echo "<pre style='background:#eee;padding:10px;border:1px solid #ccc;'>DEBUG RAW: " . htmlspecialchars($response) . "</pre>";
    }

    // Cleanup temp file
    unlink($tmpFile);

    if ($response === false) {
        return [
            'success' => false,
            'error'   => 'Connection to OCR service failed: ' . $error
        ];
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200 || !isset($data['success']) || !$data['success']) {
        return [
            'success' => false,
            'error'   => $data['error'] ?? 'OCR service returned error code ' . $httpCode
        ];
    }

    $ext = $data['data'] ?? [];

    // Helper to convert YYMMDD to YYYY-MM-DD
    $formatDate = function($yyyymmdd, $isExpiry = false) {
        if (!$yyyymmdd || strlen($yyyymmdd) !== 6) return $yyyymmdd;
        $yy = substr($yyyymmdd, 0, 2);
        $mm = substr($yyyymmdd, 2, 2);
        $dd = substr($yyyymmdd, 4, 2);
        
        // Simple logic: if YY > 50, assume 19xx, else 20xx
        // For expiry, it's almost always 20xx
        $year = (int)$yy;
        if ($isExpiry) {
            $year += 2000;
        } else {
            $year += ($year > date('y') + 5) ? 1900 : 2000;
        }
        return "$year-$mm-$dd";
    };

    // Combine names
    $fullName = trim(($ext['given_names'] ?? '') . ' ' . ($ext['surname'] ?? ''));

    return [
        'success'         => true,
        'name'            => $fullName,
        'dob'             => $formatDate($ext['date_of_birth'] ?? null),
        'passport_number' => $ext['passport_number'] ?? null,
        'nationality'     => $ext['nationality']     ?? null,
        'expiry_date'     => $formatDate($ext['expiry_date'] ?? null, true)
    ];
}

/**
 * Checks if a passport number already exists for a DIFFERENT user.
 */
function checkBlindIndex($dbh, $passportNumber, $currentUserId) {
    require_once 'encryption.php';
    
    $blindHash = computeBlindIndex($passportNumber);
    
    $stmt = $dbh->prepare("
        SELECT COUNT(*) 
        FROM tbl_kyc_records 
        WHERE document_number_hash = :hash 
        AND user_id != :uid
        AND is_current = 1
    ");
    
    $stmt->execute([
        ':hash' => $blindHash,
        ':uid'  => $currentUserId
    ]);
    
    return (int)$stmt->fetchColumn() > 0;
}
?>
