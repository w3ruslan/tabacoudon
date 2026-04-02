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

Les 4 catégories disponibles :
- \"Goût Tabac\" : tabac blond, brun, cigarette classique
- \"Goût Gourmand\" : desserts, bonbons, gâteaux, crème, vanille, chocolat, café, caramel
- \"Fruité\" : fruits (sans menthe/fraîcheur/ice)
- \"Fruité Fresh\" : fruits + menthe, ice, cool, frais, glacé

IMPORTANT : Si tu connais ce produit précisément, utilise ses vraies informations. Si tu ne le connais pas, déduis uniquement depuis le nom — n'invente PAS de fausses informations, laisse flavor et descriptions vides si incertain.

Réponds UNIQUEMENT avec ce JSON strict, sans texte avant ou après :
{
  \"brand\": \"marque du produit\",
  \"flavor\": \"arômes réels séparés par des virgules (ex: Fruits exotiques, Corossol) — vide si inconnu\",
  \"card_description\": \"résumé accrocheur du goût pour la fiche produit, MAXIMUM 150 caractères, en français, donne envie d'acheter — vide si inconnu\",
  \"full_description\": \"description complète du goût en 2-3 phrases en français, détaillée et appétissante — vide si inconnu\",
  \"category\": \"Goût Tabac OU Goût Gourmand OU Fruité OU Fruité Fresh\",
  \"image_search_query\": \"requête en anglais pour trouver l'image du produit (ex: Paperland Navy Drop Airmust vape eliquid)\"
}";

$payload = [
    'contents' => [
        ['parts' => [['text' => $prompt]]]
    ],
    'generationConfig' => [
        'temperature'     => 0.2,
        'maxOutputTokens' => 1024,
    ],
];

$apiKey = GEMINI_API_KEY;
$url    = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

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

// Clean markdown
$text = preg_replace('/```json\s*/i', '', $text);
$text = preg_replace('/```\s*/i', '', $text);
$text = trim($text);

// Extract JSON
if (preg_match('/\{.*\}/s', $text, $m)) {
    $text = $m[0];
}

$parsed = json_decode($text, true);

if (!$parsed) {
    echo json_encode(['error' => 'Impossible de parser la réponse IA', 'raw' => $text]);
    exit;
}

echo json_encode(['ok' => true, 'data' => $parsed]);
