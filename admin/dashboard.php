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
</head>
<body>

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
    <a href="logout.php" class="nav-item logout">🚪 Déconnexion</a>
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

<!-- ══════════════ MODAL AJOUT/EDIT ══════════════ -->
<div id="modal" class="modal-overlay" style="display:none" onclick="closeModalOutside(event)">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="modalTitle">Ajouter un produit</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <!-- STEP 1: RECHERCHE IMAGE -->
    <div id="step1">
      <div class="form-group">
        <label>Rechercher le produit</label>
        <div class="search-row">
          <input type="text" id="searchQuery" placeholder="ex: Elfbar 600 Blueberry Ice..." onkeydown="if(event.key==='Enter')searchImages()">
          <button class="btn-search" onclick="searchImages()" id="searchBtn">🔍 Chercher</button>
        </div>
        <div id="searchStatus" class="search-status"></div>
      </div>
      <div id="imgGrid" class="img-grid"></div>
    </div>

    <!-- STEP 2: FORMULAIRE -->
    <div id="step2" style="display:none">
      <div class="selected-preview">
        <img id="selectedImg" src="" alt="">
        <button class="btn-change" onclick="backToSearch()">↩ Changer l'image</button>
      </div>

      <input type="hidden" id="editId">

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
          <label>Catégorie</label>
          <select id="fCategory">
            <option value="">— Choisir —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Prix (€)</label>
          <div class="price-wrap">
            <span>€</span>
            <input type="number" id="fPrice" placeholder="9.90" step="0.10" min="0">
          </div>
        </div>
        <div class="form-group">
          <label>Visible sur le site</label>
          <select id="fActive">
            <option value="1">✅ Oui</option>
            <option value="0">❌ Non</option>
          </select>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn-cancel" onclick="closeModal()">Annuler</button>
        <button class="btn-save"   onclick="saveProduct()" id="saveBtn">💾 Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script>
// ─── Data ───────────────────────────────
const CATEGORIES = <?= json_encode($categories) ?>;
let selectedImgUrl = '';
let isEditing      = false;

// ─── Modal ──────────────────────────────
function openAddModal() {
  isEditing = false;
  document.getElementById('modalTitle').textContent = 'Ajouter un produit';
  document.getElementById('editId').value = '';
  document.getElementById('searchQuery').value = '';
  document.getElementById('searchStatus').textContent = '';
  document.getElementById('imgGrid').innerHTML = '';
  clearForm();
  selectedImgUrl = '';
  document.getElementById('step1').style.display = 'block';
  document.getElementById('step2').style.display = 'none';
  document.getElementById('modal').style.display = 'flex';
  setTimeout(() => document.getElementById('searchQuery').focus(), 100);
}

function closeModal() {
  document.getElementById('modal').style.display = 'none';
}
function closeModalOutside(e) {
  if (e.target.id === 'modal') closeModal();
}

function backToSearch() {
  document.getElementById('step1').style.display = 'block';
  document.getElementById('step2').style.display = 'none';
}

// ─── Recherche images ────────────────────
async function searchImages() {
  const query  = document.getElementById('searchQuery').value.trim();
  if (!query) return;
  const btn    = document.getElementById('searchBtn');
  const status = document.getElementById('searchStatus');
  const grid   = document.getElementById('imgGrid');

  btn.disabled = true;
  btn.textContent = '⏳ Recherche...';
  status.textContent = 'Recherche en cours...';
  status.className = 'search-status loading';
  grid.innerHTML = '';

  try {
    const fd = new FormData();
    fd.append('query', query);
    const res  = await fetch('../api/search.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.results || !data.results.length) {
      status.textContent = 'Aucune image trouvée. Entrez une URL manuellement.';
      status.className = 'search-status error';
      showManualOption(query);
    } else {
      status.textContent = data.results.length + ' résultats — cliquez sur une image.';
      status.className = 'search-status ok';
      renderImgGrid(data.results, query);
    }
  } catch(e) {
    status.textContent = 'Erreur réseau.';
    status.className = 'search-status error';
  } finally {
    btn.disabled = false;
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
  // Option URL manuelle
  const m = document.createElement('div');
  m.className = 'img-option img-manual';
  m.innerHTML = '<span>🔗</span><small>URL manuelle</small>';
  m.onclick = () => askManualUrl(query);
  grid.appendChild(m);
}

function showManualOption(query) {
  const grid = document.getElementById('imgGrid');
  const m = document.createElement('div');
  m.className = 'img-option img-manual';
  m.innerHTML = '<span>🔗</span><small>Coller URL image</small>';
  m.onclick = () => askManualUrl(query);
  grid.appendChild(m);
}

function askManualUrl(query) {
  const url = prompt('Collez l\'URL de l\'image :');
  if (url && url.startsWith('http')) selectImg(url, query);
}

function selectImg(url, query) {
  selectedImgUrl = url;
  document.getElementById('selectedImg').src = url;
  // Auto-fill name
  if (!document.getElementById('fName').value) {
    document.getElementById('fName').value = query;
    const words = query.split(' ');
    document.getElementById('fBrand').value   = words[0] || '';
    document.getElementById('fFlavor').value  = words.slice(2).join(' ');
  }
  document.getElementById('step1').style.display = 'none';
  document.getElementById('step2').style.display = 'block';
  document.getElementById('fPrice').focus();
}

// ─── Éditer produit ──────────────────────
function editProduct(p) {
  isEditing = true;
  document.getElementById('modalTitle').textContent = 'Modifier le produit';
  document.getElementById('editId').value  = p.id;
  document.getElementById('fName').value   = p.name    || '';
  document.getElementById('fBrand').value  = p.brand   || '';
  document.getElementById('fFlavor').value = p.flavor  || '';
  document.getElementById('fPrice').value  = p.price   || '';
  document.getElementById('fActive').value = p.active;
  document.getElementById('fCategory').value = p.category_id || '';

  selectedImgUrl = p.image_url || '';
  if (selectedImgUrl) {
    document.getElementById('selectedImg').src = selectedImgUrl;
  }

  document.getElementById('step1').style.display = selectedImgUrl ? 'none' : 'block';
  document.getElementById('step2').style.display = 'block';
  document.getElementById('modal').style.display = 'flex';
}

// ─── Enregistrer ────────────────────────
async function saveProduct() {
  const name = document.getElementById('fName').value.trim();
  if (!name) { alert('Le nom est obligatoire.'); return; }

  const btn  = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.textContent = '⏳ Enregistrement...';

  const id     = document.getElementById('editId').value;
  const method = id ? 'PUT' : 'POST';
  const action = id ? 'edit' : 'add';

  const payload = {
    id:          id || undefined,
    name:        name,
    brand:       document.getElementById('fBrand').value.trim(),
    flavor:      document.getElementById('fFlavor').value.trim(),
    category_id: document.getElementById('fCategory').value || null,
    price:       document.getElementById('fPrice').value || null,
    image_url:   selectedImgUrl,
    active:      parseInt(document.getElementById('fActive').value),
  };

  try {
    await fetch(`../api/products.php?action=${action}`, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    closeModal();
    window.location.reload();
  } catch(e) {
    alert('Erreur lors de l\'enregistrement.');
    btn.disabled = false;
    btn.textContent = '💾 Enregistrer';
  }
}

// ─── Supprimer ───────────────────────────
async function deleteProduct(id, name) {
  if (!confirm(`Supprimer "${name}" ?`)) return;
  await fetch(`../api/products.php?action=delete&id=${id}`, { method: 'DELETE' });
  document.getElementById('row-' + id)?.remove();
}

function clearForm() {
  ['fName','fBrand','fFlavor','fPrice'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fCategory').value = '';
  document.getElementById('fActive').value   = '1';
  document.getElementById('selectedImg').src  = '';
}
</script>
<link rel="stylesheet" href="assets/admin.css">
</body>
</html>
