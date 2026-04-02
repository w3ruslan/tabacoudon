<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = trim($_POST['query'] ?? $_GET['query'] ?? '');
if (!$query) { echo json_encode(['results' => []]); exit; }

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

$results = [];

// ── Source 1: DuckDuckGo ──────────────────────────
$searchDDG = $query . ' e-liquid vape';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://duckduckgo.com/?q=' . urlencode($searchDDG) . '&iax=images&ia=images',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => $ua,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Accept-Language: fr-FR,fr;q=0.9,en;q=0.8'],
]);
$html = curl_exec($ch);
curl_close($ch);

if ($html && preg_match('/vqd=([\d-]+)/', $html, $m)) {
    $vqd = $m[1];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://duckduckgo.com/i.js?l=fr-fr&o=json&q=' . urlencode($searchDDG) . '&vqd=' . $vqd . '&f=,,,,,&p=1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_REFERER        => 'https://duckduckgo.com/',
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $json = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($json, true);
    foreach (array_slice($data['results'] ?? [], 0, 20) as $r) {
        if (!empty($r['image'])) {
            $results[] = [
                'imageUrl'  => $r['image'],
                'thumbnail' => $r['thumbnail'] ?? $r['image'],
                'title'     => $r['title'] ?? '',
                'source'    => 'ddg',
            ];
        }
    }
}

// ── Source 2: Bing Images ─────────────────────────
$searchBing = $query . ' e-liquid eliquid';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://www.bing.com/images/search?q=' . urlencode($searchBing) . '&form=HDRSC2&first=1&count=30',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => $ua,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Accept-Language: fr-FR,fr;q=0.9',
        'Accept: text/html,application/xhtml+xml',
    ],
]);
$bingHtml = curl_exec($ch);
curl_close($ch);

if ($bingHtml) {
    // Extract murl (media url = full image) from Bing JSON blobs
    preg_match_all('/"murl":"([^"]+)"/', $bingHtml, $murls);
    preg_match_all('/"turl":"([^"]+)"/', $bingHtml, $turls);
    $murls = $murls[1] ?? [];
    $turls = $turls[1] ?? [];
    foreach (array_slice($murls, 0, 20) as $i => $url) {
        if (empty($url)) continue;
        $results[] = [
            'imageUrl'  => $url,
            'thumbnail' => $turls[$i] ?? $url,
            'title'     => '',
            'source'    => 'bing',
        ];
    }
}

// ── Deduplicate by imageUrl ───────────────────────
$seen = [];
$final = [];
foreach ($results as $r) {
    $key = md5($r['imageUrl']);
    if (!isset($seen[$key]) && !empty($r['imageUrl'])) {
        $seen[$key] = true;
        $final[] = $r;
    }
    if (count($final) >= 30) break;
}

echo json_encode(['results' => $final]);
