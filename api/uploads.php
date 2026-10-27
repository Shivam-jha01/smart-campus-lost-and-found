<?php
// Secure image proxy for uploaded media
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$file = $_GET['file'] ?? '';
$file = basename($file); // Prevent directory traversal

if (empty($file)) {
    http_response_code(400);
    exit("Filename is required.");
}

// Enforce strict extension whitelist
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$allowedExts = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp'
];

if (!array_key_exists($ext, $allowedExts)) {
    http_response_code(403);
    exit("Forbidden file type.");
}

$mime = $allowedExts[$ext];

$paths = [
    dirname(__DIR__) . '/uploads/' . $file,
    '/tmp/uploads/' . $file
];

foreach ($paths as $path) {
    if (file_exists($path) && is_file($path)) {
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain');
echo "Image not found.";
exit;
