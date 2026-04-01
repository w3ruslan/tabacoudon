<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── GET — liste publique ──────────────────────────
if ($method === 'GET' && $action === 'list') {
    $db  = getDB();
    $cat = isset($_GET['category']) ? (int)$_GET['category'] : 0;

    if ($cat > 0) {
        $stmt = $db->prepare('SELECT p.*, c.name AS category_name, c.icon AS category_icon
            FROM products p LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.active = 1 AND p.category_id = ?
            ORDER BY p.display_order, p.name');
        $stmt->execute([$cat]);
    } else {
        $stmt = $db->query('SELECT p.*, c.name AS category_name, c.icon AS category_icon
            FROM products p LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.active = 1
            ORDER BY c.display_order, p.display_order, p.name');
    }
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── GET — recherche par barcode ───────────────────
if ($method === 'GET' && $action === 'find_barcode') {
    $barcode = trim($_GET['barcode'] ?? '');
    if (!$barcode) { echo json_encode(null); exit; }
    $db   = getDB();
    $stmt = $db->prepare('SELECT p.*, c.name AS category_name, c.icon AS category_icon
        FROM products p LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.active = 1 AND p.barcode = ? LIMIT 1');
    $stmt->execute([$barcode]);
    $product = $stmt->fetch();
    echo json_encode($product ?: null);
    exit;
}

// ── GET — catégories ──────────────────────────────
if ($method === 'GET' && $action === 'categories') {
    $db   = getDB();
    $stmt = $db->query('SELECT * FROM categories ORDER BY display_order, name');
    echo json_encode($stmt->fetchAll());
    exit;
}

// ══ Tout ce qui suit nécessite d'être connecté admin ══
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// ── POST — ajouter produit ────────────────────────
if ($method === 'POST' && $action === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $db   = getDB();
    $stmt = $db->prepare('INSERT INTO products
        (name, brand, flavor, size, barcode, category_id, price, image_url, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['name']        ?? '',
        $data['brand']       ?? '',
        $data['flavor']      ?? '',
        $data['size']        ?? '',
        $data['barcode']     ?? '',
        $data['category_id'] ?? null,
        $data['price']       ?? null,
        $data['image_url']   ?? '',
        $data['description'] ?? '',
    ]);
    echo json_encode(['id' => $db->lastInsertId(), 'ok' => true]);
    exit;
}

// ── PUT — modifier produit ────────────────────────
if ($method === 'PUT' && $action === 'edit') {
    $data = json_decode(file_get_contents('php://input'), true);
    $db   = getDB();
    $stmt = $db->prepare('UPDATE products
        SET name=?, brand=?, flavor=?, size=?, barcode=?, category_id=?, price=?, image_url=?, active=?, description=?
        WHERE id=?');
    $stmt->execute([
        $data['name']        ?? '',
        $data['brand']       ?? '',
        $data['flavor']      ?? '',
        $data['size']        ?? '',
        $data['barcode']     ?? '',
        $data['category_id'] ?? null,
        $data['price']       ?? null,
        $data['image_url']   ?? '',
        $data['active']      ?? 1,
        $data['description'] ?? '',
        $data['id'],
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE — supprimer produit ────────────────────
if ($method === 'DELETE' && $action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $db = getDB();
        $db->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        echo json_encode(['ok' => true]);
    }
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
