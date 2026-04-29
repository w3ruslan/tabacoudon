<?php
declare(strict_types=1);

$localConfig = [];
if (file_exists(__DIR__ . '/config.local.php')) {
    $loaded = require __DIR__ . '/config.local.php';
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

function envValue(string $key, string $default = ''): string {
    global $localConfig;
    $value = getenv($key);
    if ($value !== false) {
        return (string)$value;
    }
    return array_key_exists($key, $localConfig) ? (string)$localConfig[$key] : $default;
}

define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_NAME', envValue('DB_NAME'));
define('DB_USER', envValue('DB_USER'));
define('DB_PASS', envValue('DB_PASS'));

define('ADMIN_PASSWORD_HASH', envValue('ADMIN_PASSWORD_HASH'));
define('ADMIN_PASSWORD', envValue('ADMIN_PASSWORD')); // Legacy fallback while migrating.

define('SHOP_NAME',    envValue('SHOP_NAME', 'Tabacoudon'));
define('SHOP_TAGLINE', envValue('SHOP_TAGLINE', 'Votre spécialiste e-liquid OUDON'));
define('WHATSAPP_NUMBER', envValue('WHATSAPP_NUMBER', '33612345678'));

define('GROQ_API_KEY',   envValue('GROQ_API_KEY'));
define('GEMINI_API_KEY', envValue('GEMINI_API_KEY'));

function isAdminPasswordValid(string $password): bool {
    if (ADMIN_PASSWORD_HASH !== '') {
        return password_verify($password, ADMIN_PASSWORD_HASH);
    }
    return ADMIN_PASSWORD !== '' && hash_equals(ADMIN_PASSWORD, $password);
}

function csrfToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        echo json_encode(['error' => 'Jeton CSRF invalide']);
        exit;
    }
}

function requireConfigValue(string $key, string $value): void {
    if ($value === '') {
        http_response_code(500);
        die(json_encode(['error' => 'Configuration manquante: ' . $key]));
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    } catch (PDOException $e) {
        // Avoid taking the whole site down if the host denies INFORMATION_SCHEMA.
    }
}

function ensureSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key`   VARCHAR(100) PRIMARY KEY,
        `value` TEXT NOT NULL DEFAULT ''
    )");

    ensureColumn($pdo, 'categories', 'color', "VARCHAR(20) DEFAULT '#e94560'");
    ensureColumn($pdo, 'products', 'size', 'VARCHAR(50) NULL AFTER `flavor`');
    ensureColumn($pdo, 'products', 'barcode', 'VARCHAR(100) NULL AFTER `size`');
    ensureColumn($pdo, 'products', 'description', 'TEXT NULL AFTER `image_url`');
    ensureColumn($pdo, 'products', 'sur_commande', 'TINYINT(1) NOT NULL DEFAULT 0');
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        requireConfigValue('DB_NAME', DB_NAME);
        requireConfigValue('DB_USER', DB_USER);
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
        ensureSchema($pdo);
    }
    return $pdo;
}
