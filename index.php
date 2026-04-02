<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= SHOP_NAME ?> — Catalogue E-Liquid</title>
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__.'/assets/style.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.4/dist/quagga.min.js"></script>
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
    <button class="btn-scan" onclick="openScanner()" title="Scanner un produit">
      📷 Scanner
    </button>
  </div>
</header>

<!-- SEARCH BAR -->
<div class="search-wrap no-print">
  <div class="search-box">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInput" placeholder="Rechercher un arôme, un produit..." oninput="filterProducts(this.value)" autocomplete="off">
    <button class="search-clear" id="searchClear" onclick="clearSearch()" style="display:none">✕</button>
  </div>
</div>

<!-- CATEGORY TABS -->
<nav class="cat-nav no-print">
  <button class="cat-btn active" data-cat="0" onclick="switchCat(0, this)">Tous les produits</button>
  <div id="catButtons"></div>
</nav>

<!-- CATALOG -->
<main class="catalog-main">

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

<!-- ══ DETAIL OVERLAY ══ -->
<div id="detailOverlay" style="display:none"></div>

<!-- ══ SCANNER OVERLAY ══ -->
<div id="scannerOverlay" class="scanner-overlay" style="display:none">
  <button class="scanner-close-btn" onclick="closeScanner()">✕</button>
  <div class="scanner-inner">
    <div class="scanner-title">📷 Scanner un produit</div>
    <div class="scanner-hint">Pointez la caméra vers le code-barres du produit</div>
    <div id="scannerBox"></div>
    <div id="scannerStatus" class="scanner-status"></div>
  </div>
</div>

<!-- ══ PRODUCT FOUND POPUP ══ -->
<div id="foundPopup" class="found-popup" style="display:none">
  <div class="found-inner">
    <div class="found-check">✅</div>
    <div id="foundImg"></div>
    <div id="foundName" class="found-name"></div>
    <div id="foundFlavor" class="found-flavor"></div>
    <div id="foundPrice" class="found-price"></div>
    <div id="foundCat" class="found-cat"></div>
    <button class="btn-close-found" onclick="closeFound()">Fermer</button>
  </div>
</div>

<script src="assets/app.js?v=<?= filemtime(__DIR__.'/assets/app.js') ?>"></script>
</body>
</html>
