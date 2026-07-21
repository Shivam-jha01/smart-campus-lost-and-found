<?php
// Ephemeral upload file server proxy for Vercel Serverless Functions
$file = $_GET['file'] ?? '';
$file = basename($file); // Prevent directory traversal attacks

$path = '/tmp/uploads/' . $file;

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

http_response_code(404);
echo "Image not found";
exit;
