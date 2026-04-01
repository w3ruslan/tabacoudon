-- Ajouter les colonnes description et size à la table products
-- À exécuter dans phpMyAdmin

ALTER TABLE `products`
  ADD COLUMN `description` TEXT NULL AFTER `image_url`,
  ADD COLUMN `size`        VARCHAR(50) NULL AFTER `flavor`;
