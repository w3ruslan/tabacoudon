<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

$db = getDB();

// ── GET — public read ─────────────────────────────
if ($method === 'GET') {
    $key = trim($_GET['key'] ?? '');
    if ($key) {
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key`=?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        echo json_encode(['key' => $key, 'value' => $row ? $row['value'] : '']);
    } else {
        $rows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[$r['key']] = $r['value'];
        echo json_encode($out);
    }
    exit;
}

// ── POST — admin write ────────────────────────────
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}
verifyCsrf();

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $allowed = ['whatsapp_number', 'shop_name', 'shop_tagline'];
    $saved = [];
    foreach ($allowed as $k) {
        if (isset($data[$k])) {
            $val  = trim($data[$k]);
            $stmt = $db->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?");
            $stmt->execute([$k, $val, $val]);
            $saved[$k] = $val;
        }
    }
    echo json_encode(['ok' => true, 'saved' => $saved]);
    exit;
}

echo json_encode(['error' => 'Méthode non autorisée']);
