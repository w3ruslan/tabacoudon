-- Ajouter colonne barcode à la table products
ALTER TABLE `products`
  ADD COLUMN `barcode` VARCHAR(100) NULL AFTER `size`;
