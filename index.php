<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= SHOP_NAME ?> — Catalogue E-Liquid</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- HEADER -->
<header class="site-header">
  <div class="header-inner">
    <div class="logo">
      <span class="logo-icon">🌬️</span>
      <div>
        <div class="logo-name"><?= SHOP_NAME ?></div>
        <div class="logo-tag"><?= SHOP_TAGLINE ?></div>
      </div>
    </div>
    <button class="btn-print" onclick="window.print()">
      🖨️ Télécharger PDF
    </button>
  </div>
</header>

<!-- CATEGORY TABS -->
<nav class="cat-nav no-print">
  <button class="cat-btn active" data-cat="0">Tous les produits</button>
  <div id="catButtons"></div>
</nav>

<!-- CATALOG -->
<main class="catalog-main">

  <!-- Print header (PDF only) -->
  <div class="print-only print-header">
    <h1><?= SHOP_NAME ?></h1>
    <p>Catalogue E-Liquid — <?= date('d/m/Y') ?></p>
  </div>

  <!-- Loading -->
  <div id="loading" class="loading-state">
    <div class="spinner"></div>
    <p>Chargement du catalogue...</p>
  </div>

  <!-- Empty -->
  <div id="emptyState" class="empty-state" style="display:none">
    <div style="font-size:64px">📭</div>
    <p>Aucun produit dans cette catégorie.</p>
  </div>

  <!-- Grid -->
  <div id="productGrid" class="product-grid" style="display:none"></div>

</main>

<!-- FOOTER -->
<footer class="site-footer no-print">
  <p>© <?= date('Y') ?> <?= SHOP_NAME ?> · Catalogue en ligne</p>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
