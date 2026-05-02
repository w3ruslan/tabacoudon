<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit;
}

$src = trim((string)($_GET['src'] ?? ''));
if ($src === '') {
    http_response_code(404);
    exit;
}

$bytes = null;
$contentType = null;

if (strpos($src, 'uploads/') === 0) {
    $path = realpath(__DIR__ . '/../' . $src);
    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    if ($path && $uploadsRoot && strpos($path, $uploadsRoot) === 0 && is_file($path)) {
        $bytes = file_get_contents($path);
        $contentType = mime_content_type($path) ?: 'image/png';
    }
} elseif (preg_match('#^https?://#i', $src)) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 12,
            'follow_location' => 1,
            'user_agent' => 'Tabacoudon TV Export/1.0',
        ],
    ]);
    $bytes = @file_get_contents($src, false, $context);
    $headers = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?: [])
        : ($http_response_header ?? []);
    if ($bytes !== false && $headers) {
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
                break;
            }
        }
    }
}

if (!$bytes) {
    http_response_code(404);
    exit;
}

if (!$contentType || stripos($contentType, 'image/') !== 0) {
    $info = @getimagesizefromstring($bytes);
    $contentType = $info['mime'] ?? 'image/png';
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400');
echo $bytes;
