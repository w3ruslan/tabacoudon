<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

$db         = getDB();
$products   = $db->query('SELECT p.*, c.name AS cat_name, c.icon AS cat_icon, c.color AS cat_color FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY c.display_order, p.display_order, p.name')->fetchAll();
$categories = $db->query('SELECT * FROM categories ORDER BY display_order')->fetchAll();
$total      = count($products);
$active     = count(array_filter($products, fn($p) => $p['active']));

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function adminCategoryColor($value): string {
    $color = trim((string)($value ?? ''));
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#22c55e';
}

function adminProductNotes(array $p): array {
    $raw = trim((string)($p['flavor'] ?? ''));
    if ($raw === '') {
        $raw = trim((string)($p['cat_name'] ?? ''));
    }
    if ($raw === '') return [];
    $parts = preg_split('/[,\/]+/', $raw) ?: [];
    $notes = array_values(array_filter(array_map('trim', $parts)));
    return array_slice($notes, 0, 3);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= SHOP_NAME ?></title>
  <link rel="stylesheet" href="../assets/product-card.css?v=<?= filemtime(__DIR__.'/../assets/product-card.css') ?>">
  <link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(__DIR__.'/assets/admin.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
  <style>
    /* ── AI Step ── */
    .ai-input-row { display:flex; gap:10px; margin-bottom:14px; }
    .ai-input-row input { flex:1; padding:12px 16px; border:2px solid var(--border); border-radius:10px; font-size:15px; outline:none; transition:border-color .2s; }
    .ai-input-row input:focus { border-color:#8b5cf6; }
    .btn-ai {
      padding:12px 20px; background:linear-gradient(135deg,#8b5cf6,#6d28d9);
      color:white; border:none; border-radius:10px;
      font-size:14px; font-weight:700; cursor:pointer;
      white-space:nowrap; transition:opacity .2s;
    }
    .btn-ai:hover { opacity:.88; }
    .btn-ai:disabled { opacity:.5; cursor:not-allowed; }

    .ai-result {
      background:linear-gradient(135deg,#f5f3ff,#ede9fe);
      border:2px solid #c4b5fd; border-radius:14px;
      padding:16px 20px; margin-top:14px; display:none;
    }
    .ai-result-title { font-size:12px; font-weight:800; color:#7c3aed; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
    .ai-tag { display:inline-block; background:#ede9fe; color:#6d28d9; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; margin:3px 4px 3px 0; }
    .ai-desc-preview { font-size:13px; color:#555; margin-top:8px; line-height:1.5; font-style:italic; }
    .ai-cat-badge { background:#6d28d9; color:#fff; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; }

    .btn-ai-confirm {
      width:100%; margin-top:14px; padding:13px;
      background:linear-gradient(135deg,#8b5cf6,#6d28d9);
      color:white; border:none; border-radius:10px;
      font-size:15px; font-weight:700; cursor:pointer; transition:opacity .2s;
    }
    .btn-ai-confirm:hover { opacity:.88; }

    .skip-ai { text-align:center; margin-top:8px; }
    .skip-ai button { background:none; border:none; color:var(--muted); font-size:13px; cursor:pointer; text-decoration:underline; }

    /* Sur commande badge on admin card */
    .sc-badge {
      display: inline-flex; align-items: center; gap: 4px;
      background: #fff3e0; color: #d97706;
      border: 1px solid #fcd34d;
      border-radius: 20px; padding: 2px 8px;
      font-size: 10px; font-weight: 700;
      margin-top: 4px; white-space: nowrap;
    }

    /* Description field */
    textarea#fDesc {
      width:100%; padding:11px 14px;
      border:2px solid var(--border); border-radius:9px;
      font-size:14px; outline:none; transition:border-color .2s;
      background:white; resize:vertical; min-height:72px; font-family:inherit;
    }
    textarea#fDesc:focus { border-color:var(--accent); }
  </style>
  <style>
    .scanner-overlay {
      position:fixed; inset:0; background:rgba(0,0,0,.85);
      z-index:999; display:flex; flex-direction:column;
      align-items:center; justify-content:center; gap:16px;
    }
    .scanner-box { width:320px; max-width:90vw; }
    .scanner-title { color:#fff; font-size:18px; font-weight:700; }
    .scanner-hint  { color:rgba(255,255,255,.6); font-size:13px; }
    .btn-close-scanner {
      background:#e94560; color:#fff; border:none;
      padding:12px 32px; border-radius:10px; font-size:15px;
      font-weight:700; cursor:pointer;
    }
  </style>
</head>
<body>

<!-- SCANNER OVERLAY ADMIN -->
<div id="adminScannerOverlay" class="scanner-overlay" style="display:none">
  <div class="scanner-title">📷 Scanner le barkod</div>
  <div class="scanner-hint">Pointez la caméra vers le code-barres</div>
  <div id="adminScannerBox" class="scanner-box"></div>
  <button class="btn-close-scanner" onclick="closeAdminScanner()">✕ Fermer</button>
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <span>🌬️</span>
    <div>
      <div class="s-name"><?= SHOP_NAME ?></div>
      <div class="s-role">Admin</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"  class="nav-item active">📦 Produits</a>
    <a href="categories.php" class="nav-item">🏷️ Catégories</a>
    <a href="settings.php"   class="nav-item">⚙️ Paramètres</a>
    <a href="../index.php"   class="nav-item" target="_blank">🌐 Voir le site</a>
    <a href="logout.php"     class="nav-item logout">🚪 Déconnexion</a>
  </nav>
</aside>

<!-- MAIN -->
<div class="main">

  <!-- Top bar -->
  <div class="topbar">
    <h1>Gestion des produits</h1>
    <button class="btn-primary" onclick="openAddModal()">+ Ajouter un produit</button>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-num"><?= $total ?></div>
      <div class="stat-lbl">Total produits</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $active ?></div>
      <div class="stat-lbl">Visibles</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= count($categories) ?></div>
      <div class="stat-lbl">Catégories</div>
    </div>
  </div>

  <!-- Bulk actions bar -->
  <div class="bulk-bar" id="bulkBar" style="display:none">
    <span id="bulkCount">0 sélectionné(s)</span>
    <button class="bulk-btn bulk-show"  onclick="bulkSetActive(1)">👁️ Afficher</button>
    <button class="bulk-btn bulk-hide"  onclick="bulkSetActive(0)">🙈 Masquer</button>
    <button class="bulk-btn bulk-price" onclick="bulkPricePrompt()">💶 Changer prix</button>
    <button class="bulk-btn bulk-del"   onclick="bulkDelete()">🗑️ Supprimer</button>
    <button class="bulk-btn" style="background:#1a1a2e;color:#fff;border-color:#1a1a2e" onclick="bulkExportPDF()">PDF</button>
    <button class="bulk-btn bulk-clear" onclick="clearSelection()">✕ Désélectionner</button>
  </div>

  <!-- Hidden form for product label print page (opens new tab) -->
  <form id="pdfForm" action="print_cards.php" method="POST" target="_blank" style="display:none">
    <input type="hidden" id="pdfIds" name="ids">
  </form>

  <!-- Search + category filter -->
  <div class="admin-filter-bar">
    <input type="text" id="adminSearch" placeholder="🔍 Rechercher un produit..." oninput="filterAdminCards()" class="admin-search-input">
    <div class="admin-cat-tabs" id="adminCatTabs">
      <button class="admin-cat-tab active" data-cat="0" onclick="filterAdminCat(0,this)">Tous (<?= $total ?>)</button>
      <?php foreach ($categories as $c): ?>
        <?php $cnt = count(array_filter($products, fn($p) => $p['category_id'] == $c['id'])); ?>
        <button class="admin-cat-tab" data-cat="<?= $c['id'] ?>" onclick="filterAdminCat(<?= $c['id'] ?>,this)">
          <?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?> (<?= $cnt ?>)
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Sélectionner tout -->
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--muted);cursor:pointer">
      <input type="checkbox" id="checkAll" onchange="toggleAll(this)" style="width:16px;height:16px">
      Tout sélectionner
    </label>
    <span id="adminVisibleCount" style="font-size:12px;color:var(--muted)"></span>
  </div>

  <!-- Product card grid -->
  <div class="prod-grid" id="productTableBody">
    <?php foreach ($products as $p): ?>
    <?php
      $imgSrc = $p['image_url'] ?? '';
      if ($imgSrc && strpos($imgSrc, 'uploads/') === 0) $imgSrc = '../' . $imgSrc;
      $categoryColor = adminCategoryColor($p['cat_color'] ?? '');
      $notes = adminProductNotes($p);
      $price = !empty($p['price']) ? '€' . number_format((float)$p['price'], 2) : '';
      $name = trim((string)($p['name'] ?? '')) ?: 'Produit';
      $brand = trim((string)($p['brand'] ?? ''));
      $size = trim((string)($p['size'] ?? ''));
      $barcode = trim((string)($p['barcode'] ?? ''));
    ?>
    <div class="admin-card <?= $p['active'] ? '' : 'inactive' ?>" id="row-<?= (int)$p['id'] ?>" data-id="<?= (int)$p['id'] ?>" data-cat="<?= (int)($p['category_id'] ?? 0) ?>" data-name="<?= h(strtolower($name.' '.($p['flavor']??'').' '.$brand.' '.($p['cat_name']??''))) ?>">
      <div class="admin-card-tools" aria-label="Actions admin">
        <div class="admin-card-tool-left">
          <span class="drag-handle" title="Glisser pour réordonner">⠿</span>
          <label class="admin-select-pill" title="Sélectionner">
            <input type="checkbox" class="row-check" value="<?= (int)$p['id'] ?>" onchange="updateBulkBar()">
          </label>
        </div>
        <div class="admin-card-tool-actions">
          <button class="ac-eye-btn <?= $p['active'] ? '' : 'eye-off' ?>"
            id="eye-<?= (int)$p['id'] ?>"
            onclick="toggleSingleActive(<?= (int)$p['id'] ?>, <?= (int)$p['active'] ?>)"
            title="<?= $p['active'] ? 'Masquer' : 'Afficher' ?>">
            <?= $p['active'] ? '👁️' : '🙈' ?>
          </button>
          <button class="btn-edit" title="Modifier" onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>✏️</button>
          <button class="btn-del" title="Supprimer" onclick='deleteProduct(<?= (int)$p['id'] ?>, <?= json_encode($name, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>🗑️</button>
        </div>
      </div>

      <article class="tc-card admin-storefront-card <?= $notes ? 'tc-has-specs' : 'tc-no-specs' ?>" style="--cc: <?= h($categoryColor) ?>; --category-color: <?= h($categoryColor) ?>">
        <div class="tc-card-top">
          <div class="tc-img-box">
            <div class="tc-product-visual">
              <?php if ($imgSrc): ?>
                <img src="<?= h($imgSrc) ?>" alt="<?= h($name) ?>" loading="lazy" onerror="this.style.display='none'">
              <?php else: ?>
                <span class="tc-no-img">🌬️</span>
              <?php endif; ?>
            </div>
            <?php if ($price): ?>
              <div class="tc-photo-price"><?= h($price) ?></div>
            <?php endif; ?>
            <button class="tc-photo-cart tc-cart-btn admin-visual-cart" type="button" onclick="event.stopPropagation()" aria-label="Ajouter au panier aperçu">
              <span>🛒</span><strong>AJOUTER<br>AU PANIER</strong>
            </button>
          </div>
        </div>

        <div class="tc-card-bot">
          <div class="tc-bot-left">
            <div class="tc-card-name"><?= h($name) ?></div>
            <?php if ($brand): ?>
              <div class="tc-card-brand"><?= h($brand) ?></div>
            <?php endif; ?>
            <div class="tc-bot-tags">
              <?php if ($size): ?>
                <span class="tc-size-label"><?= h($size) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['sur_commande'])): ?>
                <span class="tc-sc-pill">📦 Sur cmd</span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($notes): ?>
            <div class="tc-bot-right">
              <div class="tc-spec-title">NOTES</div>
              <div class="tc-spec-chips">
                <?php foreach ($notes as $note): ?>
                  <span class="tc-spec-chip"><?= h($note) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="tc-horizontal-barcode-wrap <?= $barcode ? 'has-barcode' : 'no-barcode' ?>">
          <?php if ($barcode): ?>
            <svg class="tc-horizontal-barcode-svg" data-barcode="<?= h($barcode) ?>"></svg>
          <?php endif; ?>
        </div>
      </article>
    </div>
    <?php endforeach; ?>
    <?php if (!$products): ?>
    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">Aucun produit.</div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════ MODAL ══════════════ -->
<div id="modal" class="modal-overlay" style="display:none" onclick="closeModalOutside(event)">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="modalTitle">Ajouter un produit</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <!-- ── STEP 2 : FORMULAIRE ── -->
    <div id="step2">
      <div style="padding:20px 28px 0">
        <div class="img-url-row">
          <div class="img-preview-box">
            <img id="selectedImg" src="" alt="" style="display:none">
            <span id="imgPlaceholder">📷</span>
          </div>
          <div class="img-url-inputs">
            <label>URL de l'image</label>
            <div style="display:flex;gap:8px">
              <input type="text" id="imgUrlInput" placeholder="Coller l'URL de l'image ici..." oninput="previewImageUrl(this.value)">
              <button type="button" class="btn-load-img" onclick="loadImageFromUrl()" title="Charger">⬇️</button>
            </div>
            <div id="imgUrlStatus" style="font-size:11px;margin-top:4px;color:#888"></div>
          </div>
        </div>
      </div>

      <input type="hidden" id="editId">

      <div style="padding:0 28px 24px">
        <div class="form-row">
          <div class="form-group">
            <label>Nom du produit *</label>
            <input type="text" id="fName" placeholder="ex: Elfbar 600 Blueberry Ice">
          </div>
          <div class="form-group">
            <label>Marque</label>
            <input type="text" id="fBrand" placeholder="ex: Elfbar">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Parfum / Saveur</label>
            <input type="text" id="fFlavor" placeholder="ex: Blueberry Ice">
          </div>
          <div class="form-group">
            <label>Taille / Format</label>
            <input type="text" id="fSize" placeholder="ex: 600 puffs">
          </div>
        </div>

        <div class="form-group" style="margin-bottom:14px">
          <label>Barkod</label>
          <div id="barcodeDuplicateWarning" style="display:none;margin:0 0 8px;padding:10px 12px;border:1px solid #f59e0b;border-radius:10px;background:#fffbeb;color:#92400e;font-size:12px;font-weight:700;line-height:1.35"></div>
          <div style="display:flex;gap:8px">
            <input type="text" id="fBarcode" placeholder="ex: 3760246640108" style="flex:1">
            <button type="button" onclick="openAdminScanner()" style="padding:11px 14px;background:#1a1a2e;color:white;border:none;border-radius:9px;cursor:pointer;font-size:18px;" title="Scanner">📷</button>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:14px">
          <label>Description (générée par IA)</label>
          <textarea id="fDesc" placeholder="Description du goût et accroche marketing..."></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Catégorie</label>
            <select id="fCategory">
              <option value="">— Choisir —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Visible sur le site</label>
            <select id="fActive">
              <option value="1">✅ Oui</option>
              <option value="0">❌ Non</option>
            </select>
          </div>
        </div>

        <!-- Sur commande toggle -->
        <div class="form-group" style="margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:12px;cursor:pointer;user-select:none" onclick="toggleSC()">
            <div style="position:relative;width:48px;height:26px;flex-shrink:0" onclick="event.stopPropagation();toggleSC()">
              <input type="checkbox" id="fSurCommande" style="opacity:0;width:0;height:0;position:absolute" tabindex="-1">
              <span id="scSlider" style="
                position:absolute;inset:0;border-radius:26px;background:#ddd;
                transition:.25s;pointer-events:none;
              ">
                <span id="scKnob" style="
                  position:absolute;width:20px;height:20px;border-radius:50%;
                  background:white;top:3px;left:3px;transition:.25s;
                  box-shadow:0 1px 4px rgba(0,0,0,.3);
                "></span>
              </span>
            </div>
            <div>
              <div style="font-size:14px;font-weight:700;color:#1a1a2e">📦 Sur commande uniquement</div>
              <div style="font-size:11px;color:#888;margin-top:2px">Le client verra un badge "Sur commande" sur la carte produit</div>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:20px">
          <label>Prix (€)</label>
          <div class="price-wrap">
            <span>€</span>
            <input type="number" id="fPrice" placeholder="9.90" step="0.10" min="0">
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn-cancel" onclick="closeModal()">Annuler</button>
          <button class="btn-save"   onclick="saveProduct()" id="saveBtn">💾 Enregistrer</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrfToken(), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const CATEGORIES = <?= json_encode($categories, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
async function adminFetch(url, options) {
  options = options || {};
  options.headers = Object.assign({}, options.headers || {}, { 'X-CSRF-Token': CSRF_TOKEN });
  return fetch(url, options);
}
function escapeHtml(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
  });
}
function safeImageUrl(value) {
  const url = String(value || '').trim();
  if (/^https?:\/\//i.test(url)) return url.replace(/"/g, '%22').replace(/</g, '%3C').replace(/>/g, '%3E');
  return '';
}

// ─── Admin search + category filter ─────────────
var adminActiveCat = 0;

function filterAdminCat(catId, btn) {
  adminActiveCat = catId;
  document.querySelectorAll('.admin-cat-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  filterAdminCards();
}

function filterAdminCards() {
  var q    = (document.getElementById('adminSearch').value || '').toLowerCase().trim();
  var cards = document.querySelectorAll('#productTableBody .admin-card');
  var shown = 0;
  cards.forEach(function(card) {
    var catMatch  = adminActiveCat === 0 || parseInt(card.dataset.cat) === adminActiveCat;
    var nameMatch = !q || (card.dataset.name || '').includes(q);
    var visible   = catMatch && nameMatch;
    card.style.setProperty('display', visible ? 'flex' : 'none', 'important');
    if (visible) shown++;
  });
  var cnt = document.getElementById('adminVisibleCount');
  if (cnt) cnt.textContent = shown + ' produit(s)';
  updateBulkBar();
}

// ─── Drag-and-drop sort ───────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  initAdminBarcodes();

  Sortable.create(document.getElementById('productTableBody'), {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'sortable-ghost',
    onEnd: async function() {
      const ids = [...document.querySelectorAll('#productTableBody [data-id]')]
        .map(el => el.dataset.id);
      await adminFetch('../api/products.php?action=reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids })
      });
    }
  });
});

function initAdminBarcodes() {
  if (typeof JsBarcode === 'undefined') return;
  document.querySelectorAll('svg.tc-horizontal-barcode-svg[data-barcode]').forEach(function(svg) {
    const code = svg.getAttribute('data-barcode');
    if (!code || svg.getAttribute('data-bc-done')) return;
    svg.setAttribute('data-bc-done', '1');
    const digits = code.replace(/\D/g, '');
    const format = /^\d{13}$/.test(digits) ? 'EAN13' : 'CODE128';
    const opts = {
      width: 2,
      height: 45,
      displayValue: true,
      fontSize: 9,
      textMargin: 2,
      textPosition: 'bottom',
      margin: 8,
      background: '#ffffff',
      lineColor: '#111827'
    };
    try {
      JsBarcode(svg, format === 'EAN13' ? digits : code, Object.assign({}, opts, { format: format }));
    } catch (e) {
      try {
        JsBarcode(svg, code, Object.assign({}, opts, { format: 'CODE128' }));
      } catch (e2) {
        const wrap = svg.closest('.tc-horizontal-barcode-wrap');
        if (wrap) wrap.style.visibility = 'hidden';
      }
    }
  });
}

// ─── Toggle single product visibility ────────────
async function toggleSingleActive(id, currentActive) {
  const newVal = currentActive ? 0 : 1;
  await adminFetch('../api/products.php?action=bulk_active', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ids: [String(id)], active: newVal })
  });
  const card = document.getElementById('row-' + id);
  const btn  = document.getElementById('eye-' + id);
  if (newVal === 0) {
    card.classList.add('inactive');
    btn.textContent = '🙈';
    btn.classList.add('eye-off');
    btn.title = 'Afficher';
    btn.setAttribute('onclick', 'toggleSingleActive(' + id + ', 0)');
  } else {
    card.classList.remove('inactive');
    btn.textContent = '👁️';
    btn.classList.remove('eye-off');
    btn.title = 'Masquer';
    btn.setAttribute('onclick', 'toggleSingleActive(' + id + ', 1)');
  }
}
let selectedImgUrl = '';
let isEditing      = false;
let aiData         = null;
let barcodeCheckTimer = null;
let barcodeDuplicateProduct = null;

function setBarcodeWarning(product) {
  barcodeDuplicateProduct = product || null;
  const box = document.getElementById('barcodeDuplicateWarning');
  if (!box) return;
  if (!product) {
    box.style.display = 'none';
    box.innerHTML = '';
    return;
  }
  const bits = [
    product.name || 'Produit sans nom',
    product.brand ? 'Marque: ' + product.brand : '',
    product.size ? 'Format: ' + product.size : '',
    product.price ? 'Prix: €' + parseFloat(product.price).toFixed(2) : ''
  ].filter(Boolean);
  box.innerHTML = '⚠️ Ce code-barres est déjà enregistré sur un autre produit.<br>' + escapeHtml(bits.join(' · '));
  box.style.display = 'block';
}

async function checkBarcodeDuplicateNow() {
  const input = document.getElementById('fBarcode');
  if (!input) return null;
  const barcode = input.value.trim();
  const currentId = document.getElementById('editId').value || '';
  if (!barcode) {
    setBarcodeWarning(null);
    return null;
  }
  try {
    const res = await adminFetch('../api/products.php?action=check_barcode&barcode=' + encodeURIComponent(barcode) + '&exclude_id=' + encodeURIComponent(currentId));
    const product = await res.json();
    const duplicate = product && !product.error ? product : null;
    setBarcodeWarning(duplicate);
    return duplicate;
  } catch (e) {
    return null;
  }
}

function scheduleBarcodeDuplicateCheck() {
  clearTimeout(barcodeCheckTimer);
  barcodeCheckTimer = setTimeout(checkBarcodeDuplicateNow, 280);
}

// ─── Modal open/close ────────────────────────────
function openAddModal() {
  isEditing = false;
  aiData    = null;
  document.getElementById('modalTitle').textContent = 'Ajouter un produit';
  document.getElementById('editId').value = '';
  selectedImgUrl = '';
  clearForm();
  showStep('step2');
  document.getElementById('modal').style.display = 'flex';
  setTimeout(() => document.getElementById('fName').focus(), 100);
}

function closeModal() { document.getElementById('modal').style.display = 'none'; }
function closeModalOutside(e) { if (e.target.id === 'modal') closeModal(); }

function showStep(step) {
  const el = document.getElementById(step);
  if (el) el.style.display = 'block';
}

// ─── AI Analyse ──────────────────────────────────
async function analyzeWithAI() {
  const name = document.getElementById('aiName').value.trim();
  if (!name) { alert('Entrez le nom du produit.'); return; }

  const btn = document.getElementById('aiBtn');
  btn.disabled    = true;
  btn.textContent = '⏳ Analyse en cours...';
  document.getElementById('aiResult').style.display = 'none';

  try {
    const res  = await adminFetch('../api/ai_product.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        name: name,
        size: document.getElementById('aiSize').value.trim()
      })
    });
    const json = await res.json();

    if (json.error) {
      alert('Erreur IA : ' + json.error);
      btn.disabled = false;
      btn.textContent = '🤖 Analyser avec Gemini AI';
      return;
    }

    aiData = json.data;
    renderAIResult(aiData);
    document.getElementById('aiResult').style.display = 'block';

  } catch(e) {
    alert('Erreur réseau.');
  } finally {
    btn.disabled    = false;
    btn.textContent = '🤖 Analyser avec Gemini AI';
  }
}

function renderAIResult(d) {
  const catMatch = CATEGORIES.find(c => c.name === d.category);
  const catHtml  = catMatch
    ? `<span class="ai-cat-badge">${escapeHtml(catMatch.icon)} ${escapeHtml(catMatch.name)}</span>`
    : `<span class="ai-tag">${escapeHtml(d.category)}</span>`;

  document.getElementById('aiResultBody').innerHTML = `
    <div style="margin-bottom:8px">
      ${d.brand ? `<span class="ai-tag">🏷️ ${escapeHtml(d.brand)}</span>` : ''}
      ${d.flavor ? `<span class="ai-tag">🍓 ${escapeHtml(d.flavor)}</span>` : ''}
      ${catHtml}
    </div>
    <div class="ai-desc-preview">"${escapeHtml(d.card_description || '')}"</div>
    ${d.full_description ? `<div class="ai-desc-preview" style="margin-top:6px;font-style:normal;font-size:12px;color:#777">${escapeHtml(d.full_description)}</div>` : ''}
  `;
}

function confirmAI() {
  if (!aiData) return;

  // Pré-remplir le formulaire
  const name  = document.getElementById('aiName').value.trim();
  const size  = document.getElementById('aiSize').value.trim();
  const price = document.getElementById('aiPrice').value;

  document.getElementById('fName').value   = name;
  document.getElementById('fBrand').value  = aiData.brand   || '';
  document.getElementById('fFlavor').value = aiData.flavor  || '';
  document.getElementById('fSize').value   = size;
  document.getElementById('fPrice').value  = price;

  // Description : card_description (150 chars) + full_description
  let desc = '';
  if (aiData.card_description)  desc += aiData.card_description;
  if (aiData.full_description)  desc += (desc ? '\n\n' : '') + aiData.full_description;
  document.getElementById('fDesc').value = desc;

  // Catégorie auto
  const catMatch = CATEGORIES.find(c => c.name === aiData.category);
  if (catMatch) document.getElementById('fCategory').value = catMatch.id;

  // Préparer la recherche image
  document.getElementById('searchQuery').value = aiData.image_search_query || name;
  document.getElementById('searchStatus').textContent = '';
  document.getElementById('imgGrid').innerHTML = '';

  showStep('step1');
  searchImages();
}

function skipAI() {
  const name  = document.getElementById('aiName').value.trim();
  const size  = document.getElementById('aiSize').value.trim();
  const price = document.getElementById('aiPrice').value;

  document.getElementById('fName').value   = name;
  document.getElementById('fSize').value   = size;
  document.getElementById('fPrice').value  = price;
  document.getElementById('searchQuery').value = name;
  document.getElementById('searchStatus').textContent = '';
  document.getElementById('imgGrid').innerHTML = '';

  showStep('step1');
  if (name) searchImages();
}

// ─── Recherche images ────────────────────────────
async function searchImages() {
  const query  = document.getElementById('searchQuery').value.trim();
  if (!query) return;
  const btn    = document.getElementById('searchBtn');
  const status = document.getElementById('searchStatus');
  const grid   = document.getElementById('imgGrid');

  btn.disabled    = true;
  btn.textContent = '⏳ Recherche...';
  status.textContent = 'Recherche en cours...';
  status.className   = 'search-status loading';
  grid.innerHTML     = '';

  try {
    const fd = new FormData();
    fd.append('query', query);
    const res  = await adminFetch('../api/search.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.results || !data.results.length) {
      status.textContent = 'Aucune image trouvée. Entrez une URL manuellement.';
      status.className   = 'search-status error';
      showManualOption(query);
    } else {
      status.textContent = data.results.length + ' résultats — cliquez sur une image.';
      status.className   = 'search-status ok';
      renderImgGrid(data.results, query);
    }
  } catch(e) {
    status.textContent = 'Erreur réseau.';
    status.className   = 'search-status error';
  } finally {
    btn.disabled    = false;
    btn.textContent = '🔍 Chercher';
  }
}

function renderImgGrid(results, query) {
  const grid = document.getElementById('imgGrid');
  grid.innerHTML = '';
  results.forEach(r => {
    const d = document.createElement('div');
    d.className = 'img-option';
    d.innerHTML = `<img src="${safeImageUrl(r.thumbnail || r.imageUrl)}" loading="lazy" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌬️</text></svg>'">`;
    d.onclick = () => selectImg(r.imageUrl, query);
    grid.appendChild(d);
  });
  const m = document.createElement('div');
  m.className = 'img-option img-manual';
  m.innerHTML = '<span>🔗</span><small>URL manuelle</small>';
  m.onclick   = () => askManualUrl(query);
  grid.appendChild(m);
}

function showManualOption(query) {
  const grid = document.getElementById('imgGrid');
  const m = document.createElement('div');
  m.className = 'img-option img-manual';
  m.innerHTML = '<span>🔗</span><small>Coller URL image</small>';
  m.onclick   = () => askManualUrl(query);
  grid.appendChild(m);
}

function askManualUrl(query) {
  const url = prompt('Collez l\'URL de l\'image :');
  if (url && url.startsWith('http')) selectImg(url, query);
}

async function selectImg(url, query) {
  // Show preview immediately with external URL
  document.getElementById('selectedImg').src = url;
  selectedImgUrl = url;
  showStep('step2');
  document.getElementById('fPrice').focus();

  // Save image to server in background
  try {
    const res  = await adminFetch('../api/save_image.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ url }),
    });
    const json = await res.json();
    if (json.path) {
      selectedImgUrl = json.path;
      document.getElementById('selectedImg').src = '../' + json.path;
    }
  } catch (e) {
    // Keep external URL as fallback
  }
}

// ─── Éditer produit ──────────────────────────────
function editProduct(p) {
  isEditing = true;
  aiData    = null;
  document.getElementById('modalTitle').textContent = 'Modifier le produit';
  document.getElementById('editId').value   = p.id;
  document.getElementById('fName').value    = p.name    || '';
  document.getElementById('fBrand').value   = p.brand   || '';
  document.getElementById('fFlavor').value  = p.flavor  || '';
  document.getElementById('fSize').value    = p.size    || '';
  document.getElementById('fPrice').value   = p.price   || '';
  document.getElementById('fActive').value  = p.active;
  document.getElementById('fCategory').value = p.category_id || '';
  const descEl    = document.getElementById('fDesc');
  const barcodeEl = document.getElementById('fBarcode');
  if (descEl)    descEl.value    = p.description || '';
  if (barcodeEl) barcodeEl.value = p.barcode     || '';
  setBarcodeWarning(null);
  if (barcodeEl && barcodeEl.value) scheduleBarcodeDuplicateCheck();
  var scEl = document.getElementById('fSurCommande');
  if (scEl) { scEl.checked = parseInt(p.sur_commande || 0) === 1; updateScSlider(); }

  selectedImgUrl = p.image_url || '';
  const imgEl = document.getElementById('selectedImg');
  const ph    = document.getElementById('imgPlaceholder');
  const urlInput  = document.getElementById('imgUrlInput');
  const urlStatus = document.getElementById('imgUrlStatus');
  if (selectedImgUrl) {
    imgEl.src           = selectedImgUrl.startsWith('uploads/') ? '../' + selectedImgUrl : selectedImgUrl;
    imgEl.style.display = 'block';
    ph.style.display    = 'none';
    if (urlInput) urlInput.value = selectedImgUrl;
  } else {
    imgEl.src           = '';
    imgEl.style.display = 'none';
    ph.style.display    = '';
    if (urlInput) urlInput.value = '';
  }
  if (urlStatus) urlStatus.textContent = '';

  showStep('step2');
  document.getElementById('modal').style.display = 'flex';
}

// ─── Enregistrer ─────────────────────────────────
async function saveProduct() {
  const name = document.getElementById('fName').value.trim();
  if (!name) { alert('Le nom est obligatoire.'); return; }

  const btn = document.getElementById('saveBtn');
  btn.disabled    = true;
  btn.textContent = '⏳ Enregistrement...';

  const id     = document.getElementById('editId').value;
  const method = id ? 'PUT' : 'POST';
  const action = id ? 'edit' : 'add';

  const payload = {
    id:          id || undefined,
    name,
    brand:       document.getElementById('fBrand').value.trim(),
    flavor:      document.getElementById('fFlavor').value.trim(),
    size:        document.getElementById('fSize').value.trim(),
    description: document.getElementById('fDesc').value.trim(),
    barcode:     document.getElementById('fBarcode').value.trim(),
    category_id: document.getElementById('fCategory').value || null,
    price:       document.getElementById('fPrice').value || null,
    image_url:    selectedImgUrl,
    active:       parseInt(document.getElementById('fActive').value),
    sur_commande: document.getElementById('fSurCommande').checked ? 1 : 0,
  };

  const duplicate = await checkBarcodeDuplicateNow();
  if (duplicate) {
    alert('⚠️ Ce code-barres existe déjà sur : ' + (duplicate.name || 'Produit'));
    btn.disabled    = false;
    btn.textContent = '💾 Enregistrer';
    return;
  }

  try {
    const res  = await adminFetch(`../api/products.php?action=${action}`, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.error) {
      alert('❌ Erreur : ' + json.error);
      btn.disabled    = false;
      btn.textContent = '💾 Enregistrer';
      return;
    }
    closeModal();
    window.location.reload();
  } catch(e) {
    alert('❌ Erreur réseau : ' + e.message);
    btn.disabled    = false;
    btn.textContent = '💾 Enregistrer';
  }
}

// ─── Supprimer ───────────────────────────────────
async function deleteProduct(id, name) {
  if (!confirm(`Supprimer "${name}" ?`)) return;
  await adminFetch(`../api/products.php?action=delete&id=${id}`, { method: 'DELETE' });
  document.getElementById('row-' + id)?.remove();
}

// ─── Bulk actions ────────────────────────────────
function getVisibleCards() {
  return [...document.querySelectorAll('#productTableBody .admin-card')]
    .filter(card => card.style.display !== 'none');
}

function getVisibleChecks() {
  return getVisibleCards()
    .map(card => card.querySelector('.row-check'))
    .filter(Boolean);
}

function getSelectedIds() {
  return [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
}

function updateBulkBar() {
  const ids = getSelectedIds();
  const visibleChecks = getVisibleChecks();
  const visibleChecked = visibleChecks.filter(c => c.checked);
  const bar = document.getElementById('bulkBar');
  bar.style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulkCount').textContent = ids.length + ' sélectionné(s)';
  document.getElementById('checkAll').indeterminate =
    visibleChecked.length > 0 && visibleChecked.length < visibleChecks.length;
  document.getElementById('checkAll').checked =
    visibleChecks.length > 0 && visibleChecked.length === visibleChecks.length;
}

function toggleAll(cb) {
  getVisibleChecks().forEach(c => c.checked = cb.checked);
  updateBulkBar();
}

function clearSelection() {
  document.querySelectorAll('.row-check').forEach(c => c.checked = false);
  document.getElementById('checkAll').checked = false;
  updateBulkBar();
}

async function bulkSetActive(val) {
  const ids = getSelectedIds();
  if (!ids.length) return;
  const label = val ? 'afficher' : 'masquer';
  if (!confirm(`${label.charAt(0).toUpperCase()+label.slice(1)} ${ids.length} produit(s) ?`)) return;
  await adminFetch('../api/products.php?action=bulk_active', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ids, active: val })
  });
  location.reload();
}

async function bulkDelete() {
  const ids = getSelectedIds();
  if (!ids.length) return;
  if (!confirm(`Supprimer définitivement ${ids.length} produit(s) ?`)) return;
  await adminFetch('../api/products.php?action=bulk_delete', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ids })
  });
  location.reload();
}

function bulkExportPDF() {
  var ids = getSelectedIds();
  if (!ids.length) { alert('Aucun produit sélectionné.'); return; }
  document.getElementById('pdfIds').value = JSON.stringify(ids);
  document.getElementById('pdfForm').submit();
}

function bulkPricePrompt() {
  const ids = getSelectedIds();
  if (!ids.length) return;
  document.getElementById('bulkPriceModal').style.display = 'flex';
  document.getElementById('bulkPriceDesc').textContent = ids.length + ' produit(s) sélectionné(s)';
  document.getElementById('bulkPriceInput').value = '';
  document.getElementById('bulkPriceInput').focus();
}

function closeBulkPrice() {
  document.getElementById('bulkPriceModal').style.display = 'none';
}

async function applyBulkPrice() {
  const ids   = getSelectedIds();
  const price = parseFloat(document.getElementById('bulkPriceInput').value);
  if (!ids.length || isNaN(price) || price < 0) return;
  await adminFetch('../api/products.php?action=bulk_price', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ids, price })
  });
  closeBulkPrice();
  location.reload();
}

// ─── Admin Barcode Scanner ───────────────────────
let adminScanner = null;

function openAdminScanner() {
  document.getElementById('adminScannerOverlay').style.display = 'flex';
  adminScanner = new Html5Qrcode('adminScannerBox');
  adminScanner.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 280, height: 140 } },
    (decodedText) => {
      document.getElementById('fBarcode').value = decodedText;
      checkBarcodeDuplicateNow();
      closeAdminScanner();
    },
    () => {}
  ).catch(err => {
    alert('Caméra inaccessible : ' + err);
    closeAdminScanner();
  });
}

function closeAdminScanner() {
  if (adminScanner) {
    adminScanner.stop().catch(() => {});
    adminScanner = null;
  }
  document.getElementById('adminScannerOverlay').style.display = 'none';
}

// ─── Sur commande toggle ──────────────────────
function toggleSC() {
  var cb = document.getElementById('fSurCommande');
  cb.checked = !cb.checked;
  updateScSlider();
}
function updateScSlider() {
  var checked = document.getElementById('fSurCommande').checked;
  var slider  = document.getElementById('scSlider');
  var knob    = document.getElementById('scKnob');
  if (slider) slider.style.background = checked ? '#e94560' : '#ddd';
  if (knob)   knob.style.left         = checked ? '25px'    : '3px';
}

function clearForm() {
  ['fName','fBrand','fFlavor','fSize','fBarcode','fPrice'].forEach(id => document.getElementById(id).value = '');
  setBarcodeWarning(null);
  document.getElementById('fDesc').value     = '';
  document.getElementById('fCategory').value = '';
  document.getElementById('fActive').value   = '1';
  var scEl = document.getElementById('fSurCommande');
  if (scEl) { scEl.checked = false; updateScSlider(); }
  const imgEl = document.getElementById('selectedImg');
  imgEl.src = '';
  imgEl.style.display = 'none';
  document.getElementById('imgPlaceholder').style.display = '';
  const urlInput = document.getElementById('imgUrlInput');
  if (urlInput) urlInput.value = '';
  const urlStatus = document.getElementById('imgUrlStatus');
  if (urlStatus) urlStatus.textContent = '';
}

document.addEventListener('DOMContentLoaded', function() {
  const barcodeInput = document.getElementById('fBarcode');
  if (!barcodeInput) return;
  barcodeInput.addEventListener('input', scheduleBarcodeDuplicateCheck);
  barcodeInput.addEventListener('blur', checkBarcodeDuplicateNow);
});

function previewImageUrl(val) {
  const imgEl = document.getElementById('selectedImg');
  const ph    = document.getElementById('imgPlaceholder');
  if (val && val.startsWith('http')) {
    imgEl.src          = val;
    imgEl.style.display = 'block';
    ph.style.display   = 'none';
  } else {
    imgEl.src          = '';
    imgEl.style.display = 'none';
    ph.style.display   = '';
  }
}

async function loadImageFromUrl() {
  const url    = document.getElementById('imgUrlInput').value.trim();
  const status = document.getElementById('imgUrlStatus');
  if (!url || !url.startsWith('http')) {
    status.textContent = 'Veuillez saisir une URL valide.';
    status.style.color = '#e94560';
    return;
  }
  status.textContent = '⏳ Chargement...';
  status.style.color = '#888';

  // Show preview immediately
  const imgEl = document.getElementById('selectedImg');
  const ph    = document.getElementById('imgPlaceholder');
  imgEl.src           = url;
  imgEl.style.display = 'block';
  ph.style.display    = 'none';
  selectedImgUrl      = url;

  // Save to server in background
  try {
    const res  = await adminFetch('../api/save_image.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ url }),
    });
    const json = await res.json();
    if (json.path) {
      selectedImgUrl      = json.path;
      imgEl.src           = '../' + json.path;
      status.textContent  = '✅ Image enregistrée.';
      status.style.color  = '#16a34a';
    } else {
      status.textContent = '⚠️ Image utilisée telle quelle (URL externe).';
      status.style.color = '#d97706';
    }
  } catch (e) {
    status.textContent = '⚠️ Erreur réseau — URL externe conservée.';
    status.style.color = '#d97706';
  }
}
</script>

<!-- Bulk Price Modal -->
<div id="bulkPriceModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:28px;width:320px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <h3 style="margin:0 0 16px;font-size:18px;">💶 Changer le prix</h3>
    <p style="margin:0 0 16px;font-size:13px;color:#666;" id="bulkPriceDesc"></p>
    <div style="display:flex;align-items:center;border:2px solid #e8e8e8;border-radius:10px;overflow:hidden;margin-bottom:20px;">
      <span style="padding:12px 14px;background:#f5f5f5;font-weight:700;font-size:16px;">€</span>
      <input type="number" id="bulkPriceInput" placeholder="14.90" step="0.10" min="0"
        style="flex:1;border:none;outline:none;padding:12px;font-size:16px;"
        onkeydown="if(event.key==='Enter')applyBulkPrice()">
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button onclick="closeBulkPrice()" style="padding:10px 20px;background:#f5f5f5;border:none;border-radius:8px;cursor:pointer;font-weight:600;">Annuler</button>
      <button onclick="applyBulkPrice()" style="padding:10px 20px;background:#00C896;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:700;">Appliquer</button>
    </div>
  </div>
</div>
</body>
</html>
