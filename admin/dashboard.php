<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

$db         = getDB();
$products   = $db->query('SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY c.display_order, p.name')->fetchAll();
$categories = $db->query('SELECT * FROM categories ORDER BY display_order')->fetchAll();
$total      = count($products);
$active     = count(array_filter($products, fn($p) => $p['active']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= SHOP_NAME ?></title>
  <link rel="stylesheet" href="assets/admin.css">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
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

  <!-- Product table -->
  <div class="table-wrap">
    <table class="prod-table">
      <thead>
        <tr>
          <th style="width:60px">Image</th>
          <th>Nom</th>
          <th>Parfum</th>
          <th>Catégorie</th>
          <th>Prix</th>
          <th>Visible</th>
          <th style="width:120px">Actions</th>
        </tr>
      </thead>
      <tbody id="productTableBody">
        <?php foreach ($products as $p): ?>
        <tr id="row-<?= $p['id'] ?>">
          <td>
            <?php if ($p['image_url']): ?>
              <img src="<?= htmlspecialchars($p['image_url']) ?>" class="thumb" alt="">
            <?php else: ?>
              <span class="no-thumb">🌬️</span>
            <?php endif; ?>
          </td>
          <td class="td-name"><?= htmlspecialchars($p['name']) ?></td>
          <td class="td-muted"><?= htmlspecialchars($p['flavor'] ?? '') ?></td>
          <td>
            <?php if ($p['cat_name']): ?>
              <span class="cat-badge"><?= htmlspecialchars($p['cat_name']) ?></span>
            <?php endif; ?>
          </td>
          <td class="td-price">
            <?= $p['price'] ? '€ ' . number_format($p['price'], 2) : '<span class="na">—</span>' ?>
          </td>
          <td>
            <span class="badge-<?= $p['active'] ? 'on' : 'off' ?>">
              <?= $p['active'] ? 'Oui' : 'Non' ?>
            </span>
          </td>
          <td>
            <button class="btn-edit" onclick='editProduct(<?= json_encode($p) ?>)'>✏️</button>
            <button class="btn-del"  onclick="deleteProduct(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')">🗑️</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?>
        <tr><td colspan="7" class="empty-row">Aucun produit. Commencez par en ajouter un !</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══════════════ MODAL ══════════════ -->
<div id="modal" class="modal-overlay" style="display:none" onclick="closeModalOutside(event)">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="modalTitle">Ajouter un produit</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <!-- ── STEP 0 : AI ANALYSE ── -->
    <div id="step0" style="padding:24px 28px">
      <div class="form-group" style="margin-bottom:14px">
        <label>Nom du produit *</label>
        <div class="ai-input-row">
          <input type="text" id="aiName" placeholder="ex: Elfbar 600 Blueberry Ice" onkeydown="if(event.key==='Enter')analyzeWithAI()">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Taille / Format</label>
          <input type="text" id="aiSize" placeholder="ex: 600 puffs, 10ml, 2mg" onkeydown="if(event.key==='Enter')analyzeWithAI()">
        </div>
        <div class="form-group">
          <label>Prix (€)</label>
          <div class="price-wrap">
            <span>€</span>
            <input type="number" id="aiPrice" placeholder="9.90" step="0.10" min="0">
          </div>
        </div>
      </div>

      <button class="btn-ai" onclick="analyzeWithAI()" id="aiBtn">🤖 Analyser avec Gemini AI</button>

      <!-- Résultat AI -->
      <div id="aiResult" class="ai-result">
        <div class="ai-result-title">✨ Résultat IA — Vérifiez et confirmez</div>
        <div id="aiResultBody"></div>
        <button class="btn-ai-confirm" onclick="confirmAI()">✅ Confirmer et choisir une image →</button>
      </div>

      <div class="skip-ai">
        <button onclick="skipAI()">Passer l'IA et remplir manuellement</button>
      </div>
    </div>

    <!-- ── STEP 1 : IMAGE ── -->
    <div id="step1" style="display:none; padding:24px 28px">
      <div class="form-group">
        <label>Rechercher une image</label>
        <div class="search-row">
          <input type="text" id="searchQuery" placeholder="ex: Elfbar 600 Blueberry Ice..." onkeydown="if(event.key==='Enter')searchImages()">
          <button class="btn-search" onclick="searchImages()" id="searchBtn">🔍 Chercher</button>
        </div>
        <div id="searchStatus" class="search-status"></div>
      </div>
      <div id="imgGrid" class="img-grid"></div>
      <div style="margin-top:14px; text-align:right">
        <button class="btn-cancel" onclick="backToAI()">↩ Retour</button>
      </div>
    </div>

    <!-- ── STEP 2 : FORMULAIRE ── -->
    <div id="step2" style="display:none">
      <div style="padding:24px 28px 0">
        <div class="selected-preview">
          <img id="selectedImg" src="" alt="">
          <button class="btn-change" onclick="backToSearch()">↩ Changer l'image</button>
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
const CATEGORIES = <?= json_encode($categories) ?>;
let selectedImgUrl = '';
let isEditing      = false;
let aiData         = null;

// ─── Modal open/close ────────────────────────────
function openAddModal() {
  isEditing = false;
  aiData    = null;
  document.getElementById('modalTitle').textContent = 'Ajouter un produit';
  document.getElementById('editId').value = '';
  document.getElementById('aiName').value  = '';
  document.getElementById('aiSize').value  = '';
  document.getElementById('aiPrice').value = '';
  document.getElementById('aiResult').style.display = 'none';
  document.getElementById('aiBtn').disabled = false;
  document.getElementById('aiBtn').textContent = '🤖 Analyser avec Gemini AI';
  selectedImgUrl = '';
  clearForm();
  showStep('step0');
  document.getElementById('modal').style.display = 'flex';
  setTimeout(() => document.getElementById('aiName').focus(), 100);
}

function closeModal() { document.getElementById('modal').style.display = 'none'; }
function closeModalOutside(e) { if (e.target.id === 'modal') closeModal(); }

function showStep(step) {
  ['step0','step1','step2'].forEach(s => {
    document.getElementById(s).style.display = s === step ? 'block' : 'none';
  });
}

function backToAI()     { showStep('step0'); }
function backToSearch() { showStep('step1'); }

// ─── AI Analyse ──────────────────────────────────
async function analyzeWithAI() {
  const name = document.getElementById('aiName').value.trim();
  if (!name) { alert('Entrez le nom du produit.'); return; }

  const btn = document.getElementById('aiBtn');
  btn.disabled    = true;
  btn.textContent = '⏳ Analyse en cours...';
  document.getElementById('aiResult').style.display = 'none';

  try {
    const res  = await fetch('../api/ai_product.php', {
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
    ? `<span class="ai-cat-badge">${catMatch.icon} ${catMatch.name}</span>`
    : `<span class="ai-tag">${d.category}</span>`;

  document.getElementById('aiResultBody').innerHTML = `
    <div style="margin-bottom:8px">
      ${d.brand ? `<span class="ai-tag">🏷️ ${d.brand}</span>` : ''}
      ${d.flavor ? `<span class="ai-tag">🍓 ${d.flavor}</span>` : ''}
      ${catHtml}
    </div>
    <div class="ai-desc-preview">"${d.card_description || ''}"</div>
    ${d.full_description ? `<div class="ai-desc-preview" style="margin-top:6px;font-style:normal;font-size:12px;color:#777">${d.full_description}</div>` : ''}
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
    const res  = await fetch('../api/search.php', { method: 'POST', body: fd });
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
    d.innerHTML = `<img src="${r.thumbnail || r.imageUrl}" loading="lazy" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌬️</text></svg>'">`;
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

function selectImg(url, query) {
  selectedImgUrl = url;
  document.getElementById('selectedImg').src = url;
  showStep('step2');
  document.getElementById('fPrice').focus();
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

  selectedImgUrl = p.image_url || '';
  if (selectedImgUrl) document.getElementById('selectedImg').src = selectedImgUrl;

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
    image_url:   selectedImgUrl,
    active:      parseInt(document.getElementById('fActive').value),
  };

  try {
    const res  = await fetch(`../api/products.php?action=${action}`, {
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
  await fetch(`../api/products.php?action=delete&id=${id}`, { method: 'DELETE' });
  document.getElementById('row-' + id)?.remove();
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

function clearForm() {
  ['fName','fBrand','fFlavor','fSize','fBarcode','fPrice'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fDesc').value     = '';
  document.getElementById('fCategory').value = '';
  document.getElementById('fActive').value   = '1';
  document.getElementById('selectedImg').src = '';
}
</script>
</body>
</html>
