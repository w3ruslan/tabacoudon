<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$size = trim($data['size'] ?? '');

if (!$name) {
    echo json_encode(['error' => 'Nom manquant']);
    exit;
}

$productStr = $name . ($size ? " $size" : '');

$prompt = "Tu es un expert en e-liquides et cigarettes électroniques (vape). Analyse ce produit : \"$productStr\"

Recherche des informations depuis plusieurs sources web et fournis une analyse complète.

Les 4 catégories disponibles :
- \"Goût Tabac\" : tabac blond, brun, cigarette classique
- \"Goût Gourmand\" : desserts, bonbons, gâteaux, crème, vanille, chocolat, café, caramel
- \"Fruité\" : fruits (sans menthe/fraîcheur/ice)
- \"Fruité Fresh\" : fruits + menthe, ice, cool, frais, glacé

Réponds UNIQUEMENT avec ce JSON strict, sans texte avant ou après :
{
  \"brand\": \"marque du produit\",
  \"flavor\": \"parfum court (ex: Blueberry Ice)\",
  \"flavor_description\": \"description du goût en 2-3 phrases appétissantes en français, comme un expert qui décrit les saveurs\",
  \"short_description\": \"slogan marketing accrocheur en français, 1 phrase max (ex: Un nuage de myrtilles avec une touche glacée irrésistible.)\",
  \"category\": \"Goût Tabac OU Goût Gourmand OU Fruité OU Fruité Fresh\",
  \"image_search_query\": \"requête en anglais pour trouver l'image du produit (ex: Elfbar 600 Blueberry Ice vape)\"
}";

$payload = [
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => $prompt]]]
    ],
    'generationConfig' => [
        'temperature'     => 0.4,
        'maxOutputTokens' => 1024,
    ]
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response) {
    echo json_encode(['error' => 'Erreur réseau Gemini']);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 200) {
    $msg = $result['error']['message'] ?? 'Erreur inconnue';
    echo json_encode(['error' => 'Gemini API: ' . $msg]);
    exit;
}

$text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Nettoyer les balises markdown si présentes
$text = preg_replace('/```json\s*/i', '', $text);
$text = preg_replace('/```\s*/i', '', $text);
$text = trim($text);

// Extraire le JSON si entouré d'autre texte
if (preg_match('/\{.*\}/s', $text, $m)) {
    $text = $m[0];
}

$parsed = json_decode($text, true);

if (!$parsed) {
    echo json_encode(['error' => 'Impossible de parser la réponse IA', 'raw' => $text]);
    exit;
}

echo json_encode(['ok' => true, 'data' => $parsed]);
