<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$url  = trim($data['url'] ?? '');

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'URL invalide']);
    exit;
}

// Only allow http/https
$parsed = parse_url($url);
if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
    echo json_encode(['error' => 'Protocole non autorisé']);
    exit;
}

// Create uploads dir
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Download image
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_MAXREDIRS      => 5,
]);
$imageData = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$mimeType  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if (!$imageData || $httpCode !== 200) {
    echo json_encode(['error' => 'Téléchargement échoué (HTTP ' . $httpCode . ')']);
    exit;
}

// Validate it's actually an image
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mime    = strtolower(explode(';', $mimeType)[0]);
if (!in_array($mime, $allowed)) {
    // Try finfo as fallback
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->buffer($imageData);
    if (!in_array($mime, $allowed)) {
        echo json_encode(['error' => 'Type de fichier non autorisé: ' . $mime]);
        exit;
    }
}

// Determine extension
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$ext      = $extMap[$mime] ?? 'jpg';
$filename = uniqid('img_', true) . '.' . $ext;
$filepath = $uploadDir . $filename;

if (file_put_contents($filepath, $imageData) === false) {
    echo json_encode(['error' => 'Impossible de sauvegarder le fichier']);
    exit;
}

echo json_encode(['path' => 'uploads/' . $filename]);
