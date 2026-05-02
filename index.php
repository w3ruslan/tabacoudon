<?php
require_once 'config.php';
// Read WhatsApp number from DB settings (falls back to config constant)
function getSetting(string $key, string $default = ''): string {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key`=?");
        $stmt->execute([$key]);
        $row  = $stmt->fetch();
        return ($row && $row['value'] !== '') ? $row['value'] : $default;
    } catch (Exception $e) { return $default; }
}
$waNumber = getSetting('whatsapp_number', WHATSAPP_NUMBER);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= SHOP_NAME ?> — Catalogue E-Liquid</title>
  <link rel="stylesheet" href="assets/product-card.css?v=<?= filemtime(__DIR__.'/assets/product-card.css') ?>">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__.'/assets/style.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.4/dist/quagga.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
</head>
<body class="storefront-page">

<!-- ══ AGE GATE ══ -->
<div id="ageGate" class="age-gate-overlay">
  <div class="age-gate-box">
    <div class="age-gate-logo">🌬️</div>
    <div class="age-gate-brand"><?= SHOP_NAME ?></div>
    <div class="age-gate-title">Vérification d'âge</div>
    <p class="age-gate-text">
      Ce site vend des produits à base de nicotine.<br>
      L'accès est <strong>strictement réservé aux personnes majeures</strong>.
    </p>
    <p class="age-gate-question">Avez-vous <strong>18 ans ou plus</strong> ?</p>
    <div class="age-gate-btns">
      <button class="age-gate-yes" onclick="ageAccept()">✅ Oui, j'ai 18 ans ou plus</button>
      <button class="age-gate-no"  onclick="ageDecline()">❌ Non, j'ai moins de 18 ans</button>
    </div>
    <p class="age-gate-legal">En entrant sur ce site, vous confirmez avoir l'âge légal requis et acceptez nos conditions d'utilisation.</p>
  </div>
</div>

<script>
(function() {
  // Show every session (sessionStorage resets when browser/tab is closed)
  if (!sessionStorage.getItem('age_ok')) {
    document.getElementById('ageGate').style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
})();
function ageAccept() {
  sessionStorage.setItem('age_ok', '1');
  var g = document.getElementById('ageGate');
  g.classList.add('age-gate-hide');
  setTimeout(function(){ g.style.display = 'none'; document.body.style.overflow = ''; }, 400);
}
function ageDecline() {
  document.getElementById('ageGate').innerHTML =
    '<div class="age-gate-box" style="text-align:center">' +
    '<div style="font-size:56px">🚫</div>' +
    '<h2 style="margin:16px 0 10px;color:#e94560">Accès refusé</h2>' +
    '<p style="color:#888;font-size:15px">Désolé, ce site est réservé aux personnes majeures.<br>Vous allez être redirigé(e).</p>' +
    '</div>';
  setTimeout(function(){ window.location.href = 'https://www.google.fr'; }, 2500);
}
</script>

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
    <div class="header-actions">
      <button class="btn-cart" onclick="openCart()" title="Mon panier">
        🛒 <span class="cart-badge" id="cartBadge" style="display:none">0</span>
      </button>
      <button class="btn-scan" onclick="openScanner()" title="Scanner un produit">
        📷 Scanner
      </button>
    </div>
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

<!-- ══ CART PANEL ══ -->
<div id="cartOverlay" class="cart-overlay" style="display:none" onclick="closeCartOutside(event)">
  <div class="cart-panel" id="cartPanel">
    <div class="cart-header">
      <h2>🛒 Mon Panier</h2>
      <button class="cart-close" onclick="closeCart()">✕</button>
    </div>
    <div class="cart-items" id="cartItems"></div>
    <div class="cart-footer" id="cartFooter" style="display:none">
      <div class="cart-total">
        <span>Total</span>
        <span id="cartTotal">€0.00</span>
      </div>
      <a id="whatsappBtn" class="btn-whatsapp" href="#" target="_blank">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Commander via WhatsApp
      </a>
      <button class="btn-clear-cart" onclick="clearCart()">🗑️ Vider le panier</button>
    </div>
    <div class="cart-empty" id="cartEmpty">
      <div style="font-size:48px">🛒</div>
      <p>Votre panier est vide</p>
    </div>
  </div>
</div>

<script>const WHATSAPP_NUMBER = '<?= htmlspecialchars($waNumber, ENT_QUOTES) ?>';</script>
<script src="assets/app.js?v=<?= filemtime(__DIR__.'/assets/app.js') ?>"></script>
</body>
</html>
