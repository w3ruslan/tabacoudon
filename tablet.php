<?php
require_once __DIR__ . '/config.php';
$db = getDB();

$categories = $db->query('SELECT * FROM categories ORDER BY display_order, name')->fetchAll(PDO::FETCH_ASSOC);
$products   = $db->query(
  'SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
   FROM products p
   LEFT JOIN categories c ON p.category_id = c.id
   WHERE p.active = 1
   ORDER BY c.display_order, p.display_order, p.name'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Tabacoudon</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  display: flex;
  height: 100dvh;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #f0f2f5;
  overflow: hidden;
}

/* ── Sidebar ── */
#sidebar {
  width: 210px;
  flex-shrink: 0;
  background: #0f172a;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

#sidebar-logo {
  padding: 20px 16px 16px;
  font-size: 20px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.5px;
  border-bottom: 1px solid rgba(255,255,255,.08);
  flex-shrink: 0;
}

#sidebar-logo span {
  color: #22c55e;
}

#cat-list {
  overflow-y: auto;
  flex: 1;
  padding: 8px 0;
}

.cat-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 18px;
  background: none;
  border: none;
  color: rgba(255,255,255,.65);
  font-size: 14px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  border-radius: 0;
  transition: background .15s, color .15s;
  border-left: 3px solid transparent;
}

.cat-btn:hover {
  background: rgba(255,255,255,.06);
  color: #fff;
}

.cat-btn.active {
  background: rgba(34,197,94,.12);
  color: #22c55e;
  border-left-color: #22c55e;
}

.cat-btn .cat-count {
  margin-left: auto;
  font-size: 11px;
  background: rgba(255,255,255,.12);
  border-radius: 20px;
  padding: 1px 7px;
  color: rgba(255,255,255,.5);
}

.cat-btn.active .cat-count {
  background: rgba(34,197,94,.2);
  color: #22c55e;
}

/* ── Main area ── */
#main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

#search-bar {
  padding: 14px 20px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

#search-bar input {
  flex: 1;
  padding: 8px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  font-size: 15px;
  outline: none;
  background: #f9fafb;
}

#search-bar input:focus { border-color: #22c55e; background: #fff; }

#cat-title {
  font-size: 15px;
  font-weight: 700;
  color: #374151;
  white-space: nowrap;
}

#product-count {
  font-size: 13px;
  color: #9ca3af;
  white-space: nowrap;
}

#grid-wrap {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px;
}

#product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 14px;
}

/* ── Product card ── */
.p-card {
  background: #fff;
  border-radius: 14px;
  border: 3px solid transparent;
  background-clip: padding-box;
  box-shadow: 0 2px 8px rgba(0,0,0,.06), 0 8px 20px rgba(0,0,0,.05);
  cursor: pointer;
  transition: transform .18s, box-shadow .18s;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
}

.p-card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 14px;
  border: 3px solid var(--cc, #22c55e);
  pointer-events: none;
  z-index: 1;
  background: linear-gradient(148deg, var(--cc, #22c55e) 0%, #061a2e 100%);
  -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: destination-out;
  mask-composite: exclude;
}

.p-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(0,0,0,.1), 0 18px 40px rgba(0,0,0,.1);
}

.p-img-wrap {
  aspect-ratio: 1;
  overflow: hidden;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid #f3f4f6;
}

.p-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  transform: scale(var(--zoom, 1));
  transform-origin: center;
  display: block;
}

.p-img-wrap .p-no-img {
  font-size: 48px;
  opacity: .2;
}

.p-info {
  padding: 10px 12px 12px;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.p-name {
  font-size: 13px;
  font-weight: 900;
  color: #111827;
  text-transform: uppercase;
  line-height: 1.15;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.p-brand {
  font-size: 12px;
  font-weight: 700;
  color: var(--cc, #22c55e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.p-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 8px;
  gap: 6px;
}

.p-size {
  font-size: 11px;
  font-weight: 700;
  color: var(--cc, #22c55e);
  border: 1.5px solid var(--cc, #22c55e);
  border-radius: 20px;
  padding: 2px 8px;
  white-space: nowrap;
}

.p-price {
  font-size: 14px;
  font-weight: 900;
  color: #fff;
  background: linear-gradient(160deg, rgba(255,255,255,.16), rgba(0,0,0,.3)), var(--cc, #22c55e);
  border-radius: 8px;
  padding: 3px 9px;
  white-space: nowrap;
}

/* ── Detail overlay ── */
#overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.6);
  z-index: 100;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

#overlay.open { display: flex; }

#overlay-panel {
  background: #fff;
  border-radius: 24px;
  width: min(520px, 100%);
  max-height: 90dvh;
  overflow-y: auto;
  box-shadow: 0 24px 80px rgba(0,0,0,.3);
}

#ov-header {
  padding: 28px 24px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #fff;
  border-radius: 24px 24px 0 0;
}

#ov-img-wrap {
  width: 200px;
  height: 200px;
  border-radius: 20px;
  border: 2px solid rgba(255,255,255,.4);
  background: #fff;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  clip-path: inset(0 round 20px);
}

#ov-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

#ov-name  { font-size: 22px; font-weight: 900; text-align: center; }
#ov-brand { font-size: 13px; opacity: .75; margin-top: 4px; }

#ov-body {
  padding: 20px 24px 28px;
}

#ov-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.ov-tag {
  padding: 5px 13px;
  border-radius: 20px;
  border: 1.5px solid #e5e7eb;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

#ov-notes {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ov-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 14px;
  color: #374151;
  line-height: 1.4;
}

.ov-note::before {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--ov-cc, #22c55e);
  flex-shrink: 0;
  margin-top: 5px;
}

#ov-desc {
  margin-top: 14px;
  font-size: 13px;
  color: #6b7280;
  line-height: 1.6;
  font-style: italic;
  border-left: 3px solid var(--ov-cc, #22c55e);
  padding-left: 12px;
}

#ov-close {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: rgba(255,255,255,.2);
  border: none;
  color: #fff;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ── Empty state ── */
#empty {
  display: none;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 300px;
  color: #9ca3af;
  font-size: 15px;
  gap: 10px;
}
#empty span { font-size: 48px; }
</style>
</head>
<body>

<!-- Sidebar -->
<div id="sidebar">
  <div id="sidebar-logo">Taba<span>coudon</span></div>
  <div id="cat-list">
    <button class="cat-btn active" data-cat="0" onclick="filterCat(0)">
      🛍️ Tous les produits
      <span class="cat-count" id="count-0"><?= count($products) ?></span>
    </button>
    <?php foreach ($categories as $cat): ?>
    <?php $n = array_reduce($products, fn($c,$p) => $c + ($p['category_name']===$cat['name']?1:0), 0); ?>
    <button class="cat-btn" data-cat="<?= (int)$cat['id'] ?>" onclick="filterCat(<?= (int)$cat['id'] ?>)"
            style="--btn-cc:<?= htmlspecialchars($cat['color'] ?? '#22c55e') ?>">
      <?= htmlspecialchars($cat['icon'] ?? '') ?> <?= htmlspecialchars($cat['name']) ?>
      <span class="cat-count" id="count-<?= (int)$cat['id'] ?>"><?= $n ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- Main -->
<div id="main">
  <div id="search-bar">
    <input type="search" id="search" placeholder="Rechercher un produit…" oninput="filterSearch(this.value)">
    <span id="cat-title">Tous les produits</span>
    <span id="product-count"></span>
  </div>
  <div id="grid-wrap">
    <div id="product-grid"></div>
    <div id="empty"><span>🔍</span>Aucun produit trouvé</div>
  </div>
</div>

<!-- Detail overlay -->
<div id="overlay" onclick="if(event.target===this)closeOv()">
  <div id="overlay-panel">
    <div id="ov-header" style="position:relative">
      <button id="ov-close" onclick="closeOv()">✕</button>
      <div id="ov-img-wrap"><img id="ov-img" src="" alt=""></div>
      <div id="ov-name"></div>
      <div id="ov-brand"></div>
    </div>
    <div id="ov-body">
      <div id="ov-tags"></div>
      <div id="ov-notes"></div>
      <div id="ov-desc"></div>
    </div>
  </div>
</div>

<script>
var ALL = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
var activeCat = 0;
var searchQ   = '';

function safeImg(url) {
  if (!url) return '';
  if (/^uploads\//.test(url)) return url;
  if (/^https?:\/\//.test(url)) return url;
  return '';
}

function renderGrid(products) {
  var grid = document.getElementById('product-grid');
  var empty = document.getElementById('empty');
  document.getElementById('product-count').textContent = products.length + ' produit' + (products.length!==1?'s':'');

  if (!products.length) {
    grid.innerHTML = '';
    empty.style.display = 'flex';
    return;
  }
  empty.style.display = 'none';

  grid.innerHTML = products.map(function(p) {
    var cc   = p.category_color || '#22c55e';
    var zoom = Math.max(1, parseFloat(p.image_zoom) || 1);
    var img  = safeImg(p.image_url);
    return '<div class="p-card" style="--cc:' + cc + '" onclick="openOv(' + p.id + ')">'
      + '<div class="p-img-wrap">'
      + (img ? '<img src="' + img + '" style="--zoom:' + zoom + '" loading="lazy" onerror="this.style.display=\'none\'">'
             : '<div class="p-no-img">🌬️</div>')
      + '</div>'
      + '<div class="p-info">'
      + '<div class="p-name">' + esc(p.name) + '</div>'
      + (p.brand ? '<div class="p-brand">' + esc(p.brand) + '</div>' : '')
      + '<div class="p-meta">'
      + (p.size  ? '<span class="p-size">' + esc(p.size) + '</span>' : '<span></span>')
      + '<span class="p-price">€' + parseFloat(p.price).toFixed(2) + '</span>'
      + '</div>'
      + '</div>'
      + '</div>';
  }).join('');
}

function esc(s) {
  return String(s||'').replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});
}

function filterCat(catId) {
  activeCat = catId;
  document.querySelectorAll('.cat-btn').forEach(function(b){
    b.classList.toggle('active', parseInt(b.dataset.cat) === catId);
  });
  var catName = catId === 0 ? 'Tous les produits'
    : (document.querySelector('.cat-btn[data-cat="'+catId+'"]').textContent.trim().split('\n')[0]);
  document.getElementById('cat-title').textContent = catName.trim();
  applyFilter();
}

function filterSearch(q) {
  searchQ = q.toLowerCase().trim();
  applyFilter();
}

function applyFilter() {
  var list = ALL.filter(function(p) {
    var catOk = activeCat === 0 || p.category_id == activeCat;
    var qOk   = !searchQ
      || (p.name||'').toLowerCase().includes(searchQ)
      || (p.brand||'').toLowerCase().includes(searchQ)
      || (p.flavor||'').toLowerCase().includes(searchQ);
    return catOk && qOk;
  });
  renderGrid(list);
}

function openOv(id) {
  var p = ALL.find(function(x){ return x.id == id; });
  if (!p) return;
  var cc = p.category_color || '#22c55e';

  document.getElementById('ov-header').style.background = 'linear-gradient(135deg,' + cc + ' 0%,#1a1a2e 70%)';
  document.getElementById('overlay-panel').style.setProperty('--ov-cc', cc);

  var img = safeImg(p.image_url);
  var imgEl = document.getElementById('ov-img');
  if (img) {
    imgEl.src = img;
    imgEl.style.transform = 'scale(' + Math.max(1, parseFloat(p.image_zoom)||1) + ')';
    document.getElementById('ov-img-wrap').style.display = 'flex';
  } else {
    document.getElementById('ov-img-wrap').style.display = 'none';
  }

  document.getElementById('ov-name').textContent  = p.name || '';
  document.getElementById('ov-brand').textContent = (p.brand||'') + (p.size ? ' · ' + p.size : '');

  var tags = '';
  if (p.flavor) {
    p.flavor.split(/[,\/]+/).forEach(function(f){
      f = f.trim();
      if (f) tags += '<span class="ov-tag">' + esc(f) + '</span>';
    });
  }
  document.getElementById('ov-tags').innerHTML = tags;

  var notes = '';
  if (p.description) {
    var lines = p.description.split('\n').filter(function(l){ return l.trim(); });
    notes = lines.map(function(l){ return '<div class="ov-note">' + esc(l.trim()) + '</div>'; }).join('');
  }
  document.getElementById('ov-notes').innerHTML = notes;
  document.getElementById('ov-desc').style.display = 'none';

  document.getElementById('overlay').classList.add('open');
}

function closeOv() {
  document.getElementById('overlay').classList.remove('open');
}

document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeOv(); });

renderGrid(ALL);
</script>
</body>
</html>
