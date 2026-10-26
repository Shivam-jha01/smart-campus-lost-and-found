<?php
/**
 * ImageService
 * Validates, processes, and persists uploaded images.
 * Supports file system persistence or serverless data URI fallback.
 */
class ImageService {
    public static function save(?string $data, string $prefix = 'item'): string {
        if (!$data) {
            return '';
        }

        if (!preg_match('/^data:image\/(png|jpeg|jpg|gif|webp);base64,/', $data, $m)) {
            Response::fail('Invalid image format');
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $commaPos = strpos($data, ',');
        $raw = base64_decode(substr($data, $commaPos + 1));

        if ($raw === false) {
            Response::fail('Could not read image');
        }

        if (strlen($raw) > 2 * 1024 * 1024) {
            Response::fail('Image must be smaller than 2 MB');
        }

        $targetDir = dirname(__DIR__, 2) . '/uploads';

        // If persistent uploads directory is writable (local / traditional server), save to file
        if (is_dir($targetDir) || @mkdir($targetDir, 0777, true)) {
            $name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $filepath = $targetDir . '/' . $name;
            if (@file_put_contents($filepath, $raw)) {
                return 'uploads/' . $name;
            }
        }

        // In serverless / read-only environment like Vercel:
        // Return data URI directly so image persists in PostgreSQL across lambdas
        return $data;
    }
}
