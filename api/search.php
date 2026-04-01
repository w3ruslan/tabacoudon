<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = trim($_POST['query'] ?? $_GET['query'] ?? '');
if (!$query) { echo json_encode(['results' => []]); exit; }

$search = $query . ' e-liquid eliquid vape cigarette electronique';
$ua     = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120 Safari/537.36';

// 1. Token DuckDuckGo
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://duckduckgo.com/?q=' . urlencode($search) . '&iax=images&ia=images',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => $ua,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html || !preg_match('/vqd=([\d-]+)/', $html, $m)) {
    echo json_encode(['results' => [], 'error' => 'Token introuvable']);
    exit;
}
$vqd = $m[1];

// 2. Recherche images
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://duckduckgo.com/i.js?l=fr-fr&o=json&q=' . urlencode($search) . '&vqd=' . $vqd . '&f=,,,,,&p=1',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => $ua,
    CURLOPT_REFERER        => 'https://duckduckgo.com/',
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$json = curl_exec($ch);
curl_close($ch);

$data    = json_decode($json, true);
$raw     = $data['results'] ?? [];
$results = [];

foreach (array_slice($raw, 0, 8) as $r) {
    $results[] = [
        'imageUrl'  => $r['image']     ?? '',
        'thumbnail' => $r['thumbnail'] ?? $r['image'] ?? '',
        'title'     => $r['title']     ?? '',
    ];
}

echo json_encode(['results' => $results]);
