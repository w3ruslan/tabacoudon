<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');
$db     = getDB();
$action = $_GET['action'] ?? '';

function normalizeColor(string $color): string {
    $color = trim($color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#e94560';
}

// ── GET: liste ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $cats = $db->query('SELECT * FROM categories ORDER BY display_order')->fetchAll();
    echo json_encode($cats);
    exit;
}

// ── Aşağıdaki işlemler admin session gerektirir ─────────
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}
verifyCsrf();

// ── POST: ekle ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare(
        'INSERT INTO categories (name, icon, color, display_order) VALUES (:name, :icon, :color, :display_order)'
    );
    $maxOrder = $db->query('SELECT COALESCE(MAX(display_order),0)+1 FROM categories')->fetchColumn();
    $stmt->execute([
        ':name'          => trim($data['name']),
        ':icon'          => trim($data['icon'] ?? '📦'),
        ':color'         => normalizeColor($data['color'] ?? '#e94560'),
        ':display_order' => (int)($data['display_order'] ?? $maxOrder),
    ]);
    echo json_encode(['id' => $db->lastInsertId()]);
    exit;
}

// ── PUT: düzenle ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $action === 'edit') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare(
        'UPDATE categories SET name=:name, icon=:icon, color=:color, display_order=:display_order WHERE id=:id'
    );
    $stmt->execute([
        ':name'          => trim($data['name']),
        ':icon'          => trim($data['icon'] ?? '📦'),
        ':color'         => normalizeColor($data['color'] ?? '#e94560'),
        ':display_order' => (int)$data['display_order'],
        ':id'            => (int)$data['id'],
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE: sil ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $db->prepare('UPDATE products SET category_id=NULL WHERE category_id=:id')->execute([':id' => $id]);
    $db->prepare('DELETE FROM categories WHERE id=:id')->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
