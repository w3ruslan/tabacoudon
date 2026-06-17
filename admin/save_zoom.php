<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); exit; }

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$id   = (int)($data['id'] ?? 0);
$zoom = max(1.0, min(2.5, (float)($data['image_zoom'] ?? 1)));

if (!$id) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }

$db   = getDB();
$stmt = $db->prepare('UPDATE products SET image_zoom = ? WHERE id = ?');
$stmt->execute([$zoom, $id]);

echo json_encode(['ok' => true, 'image_zoom' => $zoom]);
