<?php
$f = basename($_GET['f'] ?? '');
if (!$f || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) {
    http_response_code(404);
    exit;
}

$path = dirname($_SERVER['DOCUMENT_ROOT']) . '/tabacoudon_uploads/' . $f;

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($path);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed)) {
    http_response_code(403);
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000');
header('Content-Length: ' . filesize($path));
readfile($path);
