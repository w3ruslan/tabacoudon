// ══════════════════════════════════════════
// TABACOUDON — Catalogue public
// ══════════════════════════════════════════

const API = 'api/products.php';
let currentCat = 0;
let pubScanner = null;

const catColorMap = {
  'Goût Tabac':    'cat-tabac',
  'Goût Gourmand': 'cat-gourmand',
  'Fruité':        'cat-fruite',
  'Fruité Fresh':  'cat-fresh',
};

const catColors = {
  'Goût Tabac':    '#8B6914',
  'Goût Gourmand': '#E67E22',
  'Fruité':        '#E74C3C',
  'Fruité Fresh':  '#2ECC71',
};

// ── Init ──────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  loadCategories();
  loadProducts(0);
});

// ── Charger catégories ────────────────────
async function loadCategories() {
  const res  = await fetch(`${API}?action=categories`);
  const cats = await res.json();
  const wrap = document.getElementById('catButtons');
  cats.forEach(c => {
    const btn = document.createElement('button');
    btn.className    = 'cat-btn';
    btn.dataset.cat  = c.id;
    btn.textContent  = c.icon + ' ' + c.name;
    btn.addEventListener('click', () => switchCat(c.id, btn));
    wrap.appendChild(btn);
  });
}

// ── Changer catégorie ─────────────────────
function switchCat(catId, btn) {
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentCat = catId;
  loadProducts(catId);
}

// ── Charger produits ──────────────────────
async function loadProducts(catId) {
  const loading = document.getElementById('loading');
  const grid    = document.getElementById('productGrid');
  const empty   = document.getElementById('emptyState');

  loading.style.display = 'block';
  grid.style.display    = 'none';
  empty.style.display   = 'none';

  const url      = `${API}?action=list` + (catId > 0 ? `&category=${catId}` : '');
  const res      = await fetch(url);
  const products = await res.json();

  loading.style.display = 'none';

  if (!products.length) {
    empty.style.display = 'block';
    return;
  }

  grid.innerHTML     = products.map(renderCard).join('');
  grid.style.display = 'grid';
}

// ── Render card ───────────────────────────
function renderCard(p) {
  const catColor  = catColors[p.category_name] || '#e94560';
  const imgFront  = p.image_url
    ? `<img src="${p.image_url}" alt="${p.name}" onerror="this.style.display='none'">`
    : `<span class="no-img">🌬️</span>`;
  const price = p.price
    ? `<span class="fc-price"><small>€</small>${parseFloat(p.price).toFixed(2)}</span>`
    : `<span class="fc-price nd">—</span>`;

  return `
    <div class="fc-card" onclick="showDetail(this)" style="--cc:${catColor}">
      <div class="fc-stripe"></div>
      <div class="fc-img">${imgFront}</div>
      <div class="fc-body">
        ${p.category_name ? `<div class="fc-cat">${p.category_icon||''} ${p.category_name}</div>` : ''}
        <div class="fc-name">${p.name}</div>
        ${p.flavor ? `<div class="fc-flavor">🍓 ${p.flavor}</div>` : ''}
      </div>
      <div class="fc-footer">
        ${price}
        ${p.brand ? `<span class="fc-brand">${p.brand}</span>` : ''}
      </div>
      <div class="fc-tap">👆 Touchez pour les détails</div>

      <!-- Data cachée -->
      <script type="application/json" class="fc-data">${JSON.stringify(p)}<\/script>
    </div>`;
}

// ── Afficher détail (animation kart) ──────
function showDetail(card) {
  const raw = card.querySelector('.fc-data');
  if (!raw) return;
  const p        = JSON.parse(raw.textContent);
  const catColor = catColors[p.category_name] || '#e94560';

  const parts     = (p.description || '').split('\n\n');
  const shortDesc = parts[0] || '';
  const fullDesc  = parts[1] || '';
  const flavorTags = p.flavor
    ? p.flavor.split(/[,\/]+/).map(f =>
        `<span class="dt-tag">${f.trim()}</span>`).join('')
    : '';

  const overlay = document.getElementById('detailOverlay');
  overlay.innerHTML = `
    <div class="dt-bg" onclick="closeDetail()"></div>
    <div class="dt-panel" id="dtPanel" style="--cc:${catColor}">
      <div class="dt-header" style="background:linear-gradient(135deg,${catColor},#1a1a2e)">
        <button class="dt-close" onclick="closeDetail()">✕</button>
        ${p.image_url ? `<img class="dt-img" src="${p.image_url}" alt="${p.name}">` : '<div class="dt-no-img">🌬️</div>'}
        <div class="dt-hname">${p.name}</div>
        ${p.brand ? `<div class="dt-hbrand">${p.brand}${p.size?' · '+p.size:''}</div>` : ''}
      </div>
      <div class="dt-body">
        ${p.category_name ? `<span class="dt-cat" style="background:${catColor}">${p.category_icon||''} ${p.category_name}</span>` : ''}
        ${flavorTags ? `<div class="dt-tags">${flavorTags}</div>` : ''}
        ${shortDesc ? `<div class="dt-short">"${shortDesc}"</div>` : ''}
        ${fullDesc  ? `<div class="dt-full">${fullDesc}</div>`    : ''}
        <div class="dt-price">
          ${p.price ? `<small>€</small>${parseFloat(p.price).toFixed(2)}` : '<span style="color:#bbb;font-size:16px">—</span>'}
        </div>
      </div>
    </div>`;

  overlay.style.display = 'flex';
  // animate in
  setTimeout(() => {
    document.getElementById('dtPanel').classList.add('dt-in');
  }, 10);
}

function closeDetail() {
  const panel = document.getElementById('dtPanel');
  if (!panel) return;
  panel.classList.remove('dt-in');
  panel.classList.add('dt-out');
  setTimeout(() => {
    document.getElementById('detailOverlay').style.display = 'none';
    document.getElementById('detailOverlay').innerHTML = '';
  }, 320);
}

// ── Barcode Scanner ───────────────────────
function openScanner() {
  const overlay = document.getElementById('scannerOverlay');
  const status  = document.getElementById('scannerStatus');
  overlay.style.display = 'flex';
  status.textContent    = '';

  pubScanner = new Html5Qrcode('scannerBox');
  pubScanner.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 280, height: 140 } },
    async (barcode) => {
      status.textContent = '🔍 Recherche...';
      await findProductByBarcode(barcode);
    },
    () => {}
  ).catch(err => {
    status.textContent = '❌ Caméra inaccessible';
    console.warn(err);
  });
}

function closeScanner() {
  if (pubScanner) { pubScanner.stop().catch(() => {}); pubScanner = null; }
  document.getElementById('scannerOverlay').style.display = 'none';
}

async function findProductByBarcode(barcode) {
  try {
    const res = await fetch(`${API}?action=find_barcode&barcode=${encodeURIComponent(barcode)}`);
    const p   = await res.json();
    closeScanner();
    if (p && p.id) showFoundPopup(p);
    else           showNotFound(barcode);
  } catch(e) { closeScanner(); showNotFound(barcode); }
}

function showFoundPopup(p) {
  const popup = document.getElementById('foundPopup');
  const color = catColors[p.category_name] || '#e94560';
  document.getElementById('foundImg').innerHTML = p.image_url
    ? `<img src="${p.image_url}" style="width:120px;height:120px;object-fit:contain">`
    : '<div style="font-size:72px">🌬️</div>';
  document.getElementById('foundName').textContent   = p.name;
  document.getElementById('foundFlavor').textContent = p.flavor ? '🍓 '+p.flavor : '';
  document.getElementById('foundPrice').textContent  = p.price ? '€ '+parseFloat(p.price).toFixed(2) : '';
  document.getElementById('foundCat').innerHTML = p.category_name
    ? `<span style="background:${color};color:white;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700">${p.category_icon||''} ${p.category_name}</span>`
    : '';
  popup.style.display = 'flex';
}

function showNotFound(barcode) {
  const popup = document.getElementById('foundPopup');
  document.getElementById('foundImg').innerHTML      = '<div style="font-size:64px">❓</div>';
  document.getElementById('foundName').textContent   = 'Produit non trouvé';
  document.getElementById('foundFlavor').textContent = 'Barkod: ' + barcode;
  document.getElementById('foundPrice').textContent  = '';
  document.getElementById('foundCat').innerHTML      = '';
  popup.style.display = 'flex';
}

function closeFound() {
  document.getElementById('foundPopup').style.display = 'none';
}
