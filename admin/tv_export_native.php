<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

function tvNativeColor($value): string {
    $color = trim((string)($value ?? ''));
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#22c55e';
}

function tvNativeNotes(array $product): array {
    $source = trim((string)($product['flavor'] ?? ''));
    if ($source === '') $source = trim((string)($product['category_name'] ?? ''));
    if ($source === '') return [];
    $parts = preg_split('/[,\/]+/', $source) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts)));
    return array_slice($parts, 0, 2);
}

function tvNativeLower(string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function tvNativeProductKey(array $product): string {
    $barcode = preg_replace('/\D+/', '', (string)($product['barcode'] ?? ''));
    if ($barcode !== '') {
        return 'barcode:' . $barcode;
    }
    $parts = [
        trim((string)($product['name'] ?? '')),
        trim((string)($product['brand'] ?? '')),
        trim((string)($product['size'] ?? '')),
        trim((string)($product['image_url'] ?? '')),
    ];
    return 'product:' . tvNativeLower(implode('|', $parts));
}

function browserFallback(string $idsJson, string $message, string $log = ''): void {
    header('Content-Type: text/html; charset=utf-8');
    $safeIds = htmlspecialchars($idsJson, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeLog = htmlspecialchars($log, ENT_QUOTES, 'UTF-8');
    $messageJson = json_encode($message, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT);
    $logJson = json_encode($log, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT);
    echo <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TV Export</title>
  <style>
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f4f6;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#0f172a}
    .box{width:min(680px,calc(100vw - 32px));background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:26px;box-shadow:0 18px 48px rgba(15,23,42,.12)}
    h1{margin:0 0 10px;font-size:22px}
    p{margin:0 0 14px;color:#475569;line-height:1.45}
    small{display:block;white-space:pre-wrap;max-height:180px;overflow:auto;background:#f8fafc;border-radius:12px;padding:12px;color:#64748b}
  </style>
</head>
<body>
  <div class="box">
    <h1>TV Export hazırlanıyor...</h1>
    <p>Native Puppeteer export canlı sunucuda çalışmadı, 500 vermemek için otomatik browser export moduna geçiyorum.</p>
    <p><strong>Sebep:</strong> {$safeMessage}</p>
    <small>{$safeLog}</small>
  </div>
  <form id="fallbackForm" action="tv_export.php" method="POST">
    <input type="hidden" name="ids" value="{$safeIds}">
  </form>
  <script>
    console.warn('TV native export fallback:', {$messageJson});
    console.warn('TV native export log:', {$logJson});
    setTimeout(function(){ document.getElementById('fallbackForm').submit(); }, 900);
  </script>
</body>
</html>
HTML;
    exit;
}

function failNative(string $message, string $log = ''): void {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    if ($log !== '') echo "\n\n" . $log;
    exit;
}

$raw = $_POST['ids'] ?? '';
$ids = [];
foreach (array_map('intval', json_decode($raw, true) ?: []) as $id) {
    if ($id > 0 && !in_array($id, $ids, true)) {
        $ids[] = $id;
    }
}
if (!$ids) {
    failNative('Aucun produit sélectionné.');
}
$idsJson = json_encode($ids);

$db = getDB();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare(
    "SELECT p.*, c.name AS category_name, c.color AS category_color
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id IN ($placeholders)"
);
$stmt->execute(array_values($ids));
$fetched = $stmt->fetchAll();

$products = [];
$seenProducts = [];
foreach ($ids as $id) {
    foreach ($fetched as $p) {
        if ((int)$p['id'] === (int)$id) {
            $productKey = tvNativeProductKey($p);
            if (isset($seenProducts[$productKey])) {
                break;
            }
            $seenProducts[$productKey] = true;
            $products[] = [
                'id' => (int)$p['id'],
                'name' => trim((string)($p['name'] ?? '')) ?: 'Produit',
                'brand' => trim((string)($p['brand'] ?? '')),
                'size' => trim((string)($p['size'] ?? '')),
                'image_url' => trim((string)($p['image_url'] ?? '')),
                'price_text' => ($p['price'] ?? '') !== '' && $p['price'] !== null ? '€' . number_format((float)$p['price'], 2) : '',
                'category_color' => tvNativeColor($p['category_color'] ?? ''),
                'sur_commande' => !empty($p['sur_commande']),
                'notes' => tvNativeNotes($p),
            ];
            break;
        }
    }
}

if (!$products) {
    failNative('Aucun produit trouvé.');
}

if (!function_exists('shell_exec')) {
    browserFallback($idsJson, 'shell_exec PHP tarafında kapalı.', '');
}

if (!class_exists('ZipArchive')) {
    browserFallback($idsJson, 'PHP ZipArchive extension canlı sunucuda yüklü değil.', '');
}

$repoRoot = realpath(__DIR__ . '/..');
$script = $repoRoot . '/scripts/tv-export-native.mjs';
if (!is_file($script)) {
    browserFallback($idsJson, 'Native export script bulunamadı.', '');
}

if (!is_dir($repoRoot . '/node_modules/puppeteer')) {
    browserFallback($idsJson, 'Puppeteer kurulu değil. Sunucuda proje klasöründe npm install çalıştırılmalı.', '');
}

$workRoot = sys_get_temp_dir() . '/tabacoudon-tv-' . bin2hex(random_bytes(6));
$outDir = $workRoot . '/out';
if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    failNative('Impossible de créer le dossier temporaire.');
}

$dataPath = $workRoot . '/data.json';
$payload = ['screens' => array_chunk($products, 6)];
file_put_contents($dataPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$node = getenv('NODE_BINARY') ?: 'node';
$cmd = escapeshellcmd($node)
    . ' ' . escapeshellarg($script)
    . ' --data=' . escapeshellarg($dataPath)
    . ' --out=' . escapeshellarg($outDir)
    . ' --root=' . escapeshellarg($repoRoot)
    . ' 2>&1';

$log = shell_exec($cmd);
file_put_contents($outDir . '/tv-export-console.log', (string)$log);

if (!is_string($log) || !preg_match('/actual image width:\s*3840/', $log) || !preg_match('/actual image height:\s*2160/', $log)) {
    browserFallback($idsJson, "TV export natif échoué. Vérifiez que Node.js et Puppeteer sont installés avec `npm install`.", (string)$log);
}

$zipPath = $workRoot . '/tabacoudon-tv-export.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    failNative('Impossible de créer le ZIP.', (string)$log);
}

foreach (glob($outDir . '/tv-screen-*.png') ?: [] as $png) {
    $zip->addFile($png, basename($png));
}
$zip->addFile($outDir . '/tv-export-console.log', 'tv-export-console.log');
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="tabacoudon-tv-export.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
