-- ══════════════════════════════════════════
-- TABACOUDON — Base de données
-- À exécuter une seule fois dans phpMyAdmin
-- ══════════════════════════════════════════

CREATE TABLE IF NOT EXISTS categories (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    icon         VARCHAR(10)  DEFAULT '📦',
    display_order INT         DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    brand        VARCHAR(100),
    flavor       VARCHAR(255),
    category_id  INT,
    price        DECIMAL(10,2),
    image_url    TEXT,
    active       TINYINT      DEFAULT 1,
    display_order INT         DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Catégories par défaut
INSERT INTO categories (name, icon, display_order) VALUES
('Goût Tabac',    '🚬', 1),
('Goût Gourmand', '🍮', 2),
('Fruité',        '🍓', 3),
('Fruité Fresh',  '🍃', 4);
