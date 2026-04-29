-- ══════════════════════════════════════════
-- TABACOUDON — Base de données
-- À exécuter une seule fois dans phpMyAdmin
-- ══════════════════════════════════════════

CREATE TABLE IF NOT EXISTS categories (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    icon         VARCHAR(10)  DEFAULT '📦',
    color        VARCHAR(20)  DEFAULT '#e94560',
    display_order INT         DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    brand        VARCHAR(100),
    flavor       VARCHAR(255),
    size         VARCHAR(50),
    barcode      VARCHAR(100),
    category_id  INT,
    price        DECIMAL(10,2),
    image_url    TEXT,
    description  TEXT,
    sur_commande TINYINT(1)  NOT NULL DEFAULT 0,
    active       TINYINT      DEFAULT 1,
    display_order INT         DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL DEFAULT ''
);

-- Catégories par défaut
INSERT INTO categories (name, icon, display_order) VALUES
('Goût Tabac',    '🚬', 1),
('Goût Gourmand', '🍮', 2),
('Fruité',        '🍓', 3),
('Fruité Fresh',  '🍃', 4);
