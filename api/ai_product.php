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

Réponds UNIQUEMENT avec ce JSON strict, sans texte avant ou après :
{
  \"brand\": \"marque du produit\",
  \"flavor\": \"parfum court (ex: Blueberry Ice)\",
  \"card_description\": \"résumé accrocheur du goût pour la fiche produit, MAXIMUM 150 caractères, en français, donne envie d'acheter (ex: Myrtilles juteuses avec une vague de fraîcheur glacée. Un best-seller incontournable !)\",
  \"full_description\": \"description complète du goût en 2-3 phrases en français, détaillée et appétissante\",
  \"category\": \"Goût Tabac OU Goût Gourmand OU Fruité OU Fruité Fresh\",
  \"image_search_query\": \"requête en anglais pour trouver l'image du produit (ex: Elfbar 600 Blueberry Ice vape)\"
}";

$payload = [
    'model'    => 'llama-3.3-70b-versatile',
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.4,
    'max_tokens'  => 1024,
];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response) {
    echo json_encode(['error' => 'Erreur réseau Groq']);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 200) {
    $msg = $result['error']['message'] ?? 'Erreur inconnue';
    echo json_encode(['error' => 'Groq API: ' . $msg]);
    exit;
}

$text = $result['choices'][0]['message']['content'] ?? '';

// Nettoyer les balises markdown
$text = preg_replace('/```json\s*/i', '', $text);
$text = preg_replace('/```\s*/i', '', $text);
$text = trim($text);

// Extraire le JSON
if (preg_match('/\{.*\}/s', $text, $m)) {
    $text = $m[0];
}

$parsed = json_decode($text, true);

if (!$parsed) {
    echo json_encode(['error' => 'Impossible de parser la réponse IA', 'raw' => $text]);
    exit;
}

echo json_encode(['ok' => true, 'data' => $parsed]);
