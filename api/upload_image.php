<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}
verifyCsrf();

if (empty($_FILES['image'])) {
    echo json_encode(['error' => 'Aucun fichier reçu']);
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur)',
        UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite formulaire)',
        UPLOAD_ERR_PARTIAL    => 'Téléchargement incomplet',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
        UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque',
    ];
    echo json_encode(['error' => $msgs[$file['error']] ?? 'Erreur upload ' . $file['error']]);
    exit;
}

if ($file['size'] > 8 * 1024 * 1024) {
    echo json_encode(['error' => 'Image trop volumineuse (max 8 Mo)']);
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

if (!isset($allowed[$mime])) {
    echo json_encode(['error' => 'Type non autorisé: ' . $mime]);
    exit;
}

$uploadDir = dirname($_SERVER['DOCUMENT_ROOT']) . '/tabacoudon_uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = uniqid('img_', true) . '.' . $allowed[$mime];
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Impossible de déplacer le fichier']);
    exit;
}

echo json_encode(['path' => 'uploads/' . $filename]);
