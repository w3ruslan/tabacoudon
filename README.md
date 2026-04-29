# Tabacoudon

Catalogue PHP/MySQL pour produits e-liquide avec espace admin, gestion des catégories, recherche d'images, aide IA, panier WhatsApp et impression d'étiquettes.

## Installation

1. Importer `db/schema.sql` dans MySQL.
2. Copier `config.local.example.php` vers `config.local.php`.
3. Renseigner les accès DB et les clés API dans `config.local.php`.
4. Générer un mot de passe admin hashé :

```bash
php -r "echo password_hash('change-me', PASSWORD_DEFAULT), PHP_EOL;"
```

5. Placer le hash dans `ADMIN_PASSWORD_HASH`.

## Sécurité

- Ne jamais commiter `config.local.php` ni les clés API.
- Après une fuite de secrets, changer le mot de passe DB et le mot de passe admin côté hébergeur.
- Les actions admin utilisent un jeton CSRF.
- Les images distantes sont limitées à HTTP/HTTPS public et 5 Mo.

## Fichiers principaux

- `index.php` : catalogue public.
- `admin/` : interface admin.
- `api/` : endpoints JSON.
- `db/schema.sql` : schéma complet pour nouvelle installation.
