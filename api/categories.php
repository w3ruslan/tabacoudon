<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');
$db     = getDB();
$action = $_GET['action'] ?? '';

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

// ── POST: ekle ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare(
        'INSERT INTO categories (name, icon, display_order) VALUES (:name, :icon, :display_order)'
    );
    $maxOrder = $db->query('SELECT COALESCE(MAX(display_order),0)+1 FROM categories')->fetchColumn();
    $stmt->execute([
        ':name'          => trim($data['name']),
        ':icon'          => trim($data['icon'] ?? '📦'),
        ':display_order' => (int)($data['display_order'] ?? $maxOrder),
    ]);
    echo json_encode(['id' => $db->lastInsertId()]);
    exit;
}

// ── PUT: düzenle ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $action === 'edit') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare(
        'UPDATE categories SET name=:name, icon=:icon, display_order=:display_order WHERE id=:id'
    );
    $stmt->execute([
        ':name'          => trim($data['name']),
        ':icon'          => trim($data['icon'] ?? '📦'),
        ':display_order' => (int)$data['display_order'],
        ':id'            => (int)$data['id'],
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE: sil ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    // Kategorideki ürünlerin category_id'sini null yap
    $db->prepare('UPDATE products SET category_id=NULL WHERE category_id=:id')->execute([':id' => $id]);
    $db->prepare('DELETE FROM categories WHERE id=:id')->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
