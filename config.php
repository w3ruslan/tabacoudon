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
define('SHOP_TAGLINE', 'Votre spécialiste e-liquid OUDON');

// WhatsApp — numéro avec indicatif pays, sans + ni espaces (ex: 33612345678)
define('WHATSAPP_NUMBER', '33612345678');

// API Keys — fichier séparé (non versionné)
if (file_exists(__DIR__ . '/config.keys.php')) {
    require_once __DIR__ . '/config.keys.php';
} else {
    define('GROQ_API_KEY',   '');
    define('GEMINI_API_KEY', '');
}

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
        // Migration: add sur_commande column if it doesn't exist yet
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN sur_commande TINYINT(1) NOT NULL DEFAULT 0");
        } catch (PDOException $e) {
            // Column already exists — silently ignore
        }
    }
    return $pdo;
}
