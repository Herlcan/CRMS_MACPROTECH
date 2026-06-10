<?php

if (!function_exists('save_uploaded_image')) {
    function save_uploaded_image(array $file, string $targetDirectory, string $prefix = 'img'): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new Exception('Image upload error.');
        }

        $maxSize = 10 * 1024 * 1024;
        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new Exception('Invalid image upload.');
        }

        if ((int) ($file['size'] ?? 0) > $maxSize) {
            throw new Exception('Image is too large. Maximum size is 10MB.');
        }

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $originalExtension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($originalExtension === 'jpeg') {
            $originalExtension = 'jpg';
        }

        if (!in_array($originalExtension, ['jpg', 'png', 'webp'], true)) {
            throw new Exception('Invalid image extension. Only JPG, PNG, and WEBP are allowed.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!isset($extensionMap[$mimeType]) || $extensionMap[$mimeType] !== $originalExtension) {
            throw new Exception('Invalid image type. Only JPG, PNG, and WEBP are allowed.');
        }

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true)) {
            throw new Exception('Upload folder could not be prepared.');
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'img';
        $filename = $safePrefix . '_' . bin2hex(random_bytes(16)) . '.' . $originalExtension;
        $targetPath = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new Exception('Failed to upload image.');
        }

        chmod($targetPath, 0644);

        return $filename;
    }
}

?>
