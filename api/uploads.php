<?php
// Upload file server proxy
$file = $_GET['file'] ?? '';
$file = basename($file); // Prevent directory traversal attacks

// Check persistent directory first, then fallback to serverless /tmp
$paths = [
    dirname(__DIR__) . '/uploads/' . $file,
    '/tmp/uploads/' . $file
];

foreach ($paths as $path) {
    if ($file && file_exists($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'image/jpeg';
        if ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'gif') {
            $mime = 'image/gif';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        }
        
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain');
echo "Image not found";
exit;
