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

function failNative(string $message, string $log = ''): void {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    if ($log !== '') echo "\n\n" . $log;
    exit;
}

$raw = $_POST['ids'] ?? '';
$ids = array_filter(array_map('intval', json_decode($raw, true) ?: []));
if (!$ids) {
    failNative('Aucun produit sélectionné.');
}

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
foreach ($ids as $id) {
    foreach ($fetched as $p) {
        if ((int)$p['id'] === (int)$id) {
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

$workRoot = sys_get_temp_dir() . '/tabacoudon-tv-' . bin2hex(random_bytes(6));
$outDir = $workRoot . '/out';
if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    failNative('Impossible de créer le dossier temporaire.');
}

$dataPath = $workRoot . '/data.json';
$payload = ['screens' => array_chunk($products, 6)];
file_put_contents($dataPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$repoRoot = realpath(__DIR__ . '/..');
$script = $repoRoot . '/scripts/tv-export-native.mjs';
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
    failNative("TV export natif échoué. Vérifiez que Node.js et Puppeteer sont installés avec `npm install`.", (string)$log);
}

if (!class_exists('ZipArchive')) {
    failNative('Extension PHP ZipArchive manquante.', (string)$log);
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
