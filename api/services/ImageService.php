<?php
/**
 * ImageService
 * Validates, processes, and persists uploaded images.
 * Checks magic bytes, enforces MIME type whitelist, and validates file size.
 */
class ImageService {
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    public static function save(?string $data, string $prefix = 'item'): string {
        if (!$data) {
            return '';
        }

        if (!preg_match('/^data:(image\/(png|jpeg|jpg|gif|webp));base64,(.+)$/s', $data, $m)) {
            Response::fail('Invalid image format. Only JPEG, PNG, GIF, and WEBP are supported.');
        }

        $declaredMime = $m[1] === 'image/jpg' ? 'image/jpeg' : $m[1];
        $base64Payload = $m[3];

        $raw = base64_decode($base64Payload, true);
        if ($raw === false) {
            Response::fail('Could not decode image payload.');
        }

        if (strlen($raw) > 2 * 1024 * 1024) {
            Response::fail('Image exceeds the maximum allowed size of 2 MB.');
        }

        // Deep validation: verify image binary magic bytes
        $imgInfo = @getimagesizefromstring($raw);
        if (!$imgInfo || empty($imgInfo['mime'])) {
            Response::fail('Invalid or corrupted image content.');
        }

        $detectedMime = $imgInfo['mime'];
        if (!array_key_exists($detectedMime, self::ALLOWED_MIMES)) {
            Response::fail('Unsupported image MIME type: ' . htmlspecialchars($detectedMime));
        }

        $ext = self::ALLOWED_MIMES[$detectedMime];
        $targetDir = dirname(__DIR__, 2) . '/uploads';

        // If persistent uploads directory is writable, write sanitized file
        if (is_dir($targetDir) || @mkdir($targetDir, 0777, true)) {
            $name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $filepath = $targetDir . '/' . $name;
            if (@file_put_contents($filepath, $raw)) {
                return 'uploads/' . $name;
            }
        }

        // Reconstruct safe data URI with verified MIME type
        return 'data:' . $detectedMime . ';base64,' . base64_encode($raw);
    }
}
