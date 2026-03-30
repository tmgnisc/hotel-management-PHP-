<?php
// Cloudinary configuration
// These can be overridden by environment variables when deployed.
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'dxudm3c5s');
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '122467231781282');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: 'QjhRIcEMpQBj7IjGkTWPH7gkMZk');
define('CLOUDINARY_FOLDER', getenv('CLOUDINARY_FOLDER') ?: 'pos');

function cloudinaryBuildSignature(array $params): string {
    ksort($params);
    $pairs = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $pairs[] = $key . '=' . $value;
    }
    $toSign = implode('&', $pairs);
    return sha1($toSign . CLOUDINARY_API_SECRET);
}

function cloudinaryUploadImage(string $localFilePath, ?string $folder = null): array {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL extension is not enabled on server.'];
    }

    if (!is_file($localFilePath)) {
        return ['success' => false, 'error' => 'Uploaded temp file not found.'];
    }

    $timestamp = time();
    $uploadFolder = trim((string)($folder ?: CLOUDINARY_FOLDER));

    $signatureParams = [
        'folder' => $uploadFolder,
        'timestamp' => $timestamp,
    ];

    $signature = cloudinaryBuildSignature($signatureParams);
    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/upload';

    $payload = [
        'file' => new CURLFile($localFilePath),
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
        'folder' => $uploadFolder,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => $curlError ?: 'Cloudinary upload failed.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Invalid response from Cloudinary.'];
    }

    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['secure_url']) && !empty($data['public_id'])) {
        return [
            'success' => true,
            'secure_url' => $data['secure_url'],
            'public_id' => $data['public_id'],
            'raw' => $data,
        ];
    }

    $errorMessage = $data['error']['message'] ?? 'Cloudinary upload failed.';
    return ['success' => false, 'error' => $errorMessage, 'raw' => $data];
}

function cloudinaryDeleteImage(string $publicId): array {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL extension is not enabled on server.'];
    }

    $publicId = trim($publicId);
    if ($publicId === '') {
        return ['success' => false, 'error' => 'public_id is required for delete.'];
    }

    $timestamp = time();
    $signatureParams = [
        'public_id' => $publicId,
        'timestamp' => $timestamp,
    ];

    $signature = cloudinaryBuildSignature($signatureParams);
    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/destroy';

    $payload = [
        'public_id' => $publicId,
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => $curlError ?: 'Cloudinary delete failed.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Invalid response from Cloudinary delete API.'];
    }

    if ($httpCode >= 200 && $httpCode < 300 && (($data['result'] ?? '') === 'ok' || ($data['result'] ?? '') === 'not found')) {
        return ['success' => true, 'raw' => $data];
    }

    $errorMessage = $data['error']['message'] ?? 'Cloudinary delete failed.';
    return ['success' => false, 'error' => $errorMessage, 'raw' => $data];
}
