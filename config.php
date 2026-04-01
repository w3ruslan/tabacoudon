<?php
// ══════════════════════════════════════════
// CONFIG — Modifier avant mise en ligne
// ══════════════════════════════════════════

// Base de données Hostinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'u870017612_tabacoudon');
define('DB_USER', 'u870017612_admin');
define('DB_PASS', '1234566Ruslan-');

// Mot de passe admin (changer après la première connexion)
define('ADMIN_PASSWORD', 'admin123');

// Nom du magasin
define('SHOP_NAME',    'Tabacoudon');
define('SHOP_TAGLINE', 'Votre spécialiste e-liquid à Paris');

// Gemini AI API Key
define('GEMINI_API_KEY', 'AIzaSyDmdvE9kdmJp9BFfnUcRLmy328dLWn-wf0');

// Connexion PDO
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Connexion BDD échouée']));
        }
    }
    return $pdo;
}
