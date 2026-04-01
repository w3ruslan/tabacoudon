// ══════════════════════════════════════════
// TABACOUDON — Catalogue public
// ══════════════════════════════════════════

const API = 'api/products.php';
let currentCat  = 0;
let pubScanner  = null;

const catColorMap = {
  'Goût Tabac':    'cat-tabac',
  'Goût Gourmand': 'cat-gourmand',
  'Fruité':        'cat-fruite',
  'Fruité Fresh':  'cat-fresh',
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
    btn.className = 'cat-btn';
    btn.dataset.cat = c.id;
    btn.textContent = c.icon + ' ' + c.name;
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

  const url = `${API}?action=list` + (catId > 0 ? `&category=${catId}` : '');
  const res = await fetch(url);
  allProducts   = await res.json();

  loading.style.display = 'none';

  if (!allProducts.length) {
    empty.style.display = 'block';
    return;
  }

  grid.innerHTML     = allProducts.map(renderCard).join('');
  grid.style.display = 'grid';
}

// ── Render card ───────────────────────────
let allProducts = [];

function renderCard(p) {
  const colorClass = catColorMap[p.category_name] || '';
  const img = p.image_url
    ? `<img src="${p.image_url}" alt="${p.name}" loading="lazy" onerror="this.parentElement.innerHTML='<span class=no-img>🌬️</span>'">`
    : `<span class="no-img">🌬️</span>`;
  const catLabel = p.category_name
    ? `<div class="card-cat">${p.category_icon || ''} ${p.category_name}</div>`
    : '';
  const price = p.price
    ? `<span class="card-price"><small>€</small>${parseFloat(p.price).toFixed(2)}</span>`
    : `<span class="card-price no-price">—</span>`;
  const brand = p.brand
    ? `<span class="card-badge">${p.brand}</span>`
    : '';

  return `
    <div class="product-card ${colorClass}" id="card-${p.id}" onclick="openCardDetail(${p.id})">
      <div class="card-img">${img}</div>
      <div class="card-body">
        ${catLabel}
        <div class="card-name">${p.name}</div>
        ${p.flavor ? `<div class="card-flavor">🍓 ${p.flavor}</div>` : ''}
      </div>
      <div class="card-footer">
        ${price}
        ${brand}
      </div>
      <div class="card-tap-hint">👆 Appuyez pour voir</div>
    </div>`;
}

// ── Card Detail Popup (Anime effect) ──────
function openCardDetail(productId) {
  const p = allProducts.find(x => x.id == productId);
  if (!p) return;

  const colorMap = {
    'Goût Tabac':    { bg: '#8B6914', light: '#fff8e7', accent: '#8B6914' },
    'Goût Gourmand': { bg: '#E67E22', light: '#fff4e6', accent: '#E67E22' },
    'Fruité':        { bg: '#E74C3C', light: '#fff0f0', accent: '#E74C3C' },
    'Fruité Fresh':  { bg: '#2ECC71', light: '#f0fff6', accent: '#2ECC71' },
  };
  const colors = colorMap[p.category_name] || { bg: '#e94560', light: '#fff0f3', accent: '#e94560' };

  // Description parts
  const parts = (p.description || '').split('\n\n');
  const shortDesc = parts[0] || '';
  const fullDesc  = parts[1] || '';

  // Flavor tags
  const flavorTags = p.flavor
    ? p.flavor.split(/[,\/\+]+/).map(f => `<span class="dtag">${f.trim()}</span>`).join('')
    : '';

  const popup = document.getElementById('cardDetailPopup');
  if (!popup) return;
  popup.innerHTML = `
    <div class="cdp-backdrop" onclick="closeCardDetail()"></div>
    <div class="cdp-card" style="--cat-color:${colors.bg}; --cat-light:${colors.light}">
      <button class="cdp-close" onclick="closeCardDetail()">✕</button>

      <div class="cdp-img-wrap">
        ${p.image_url
          ? `<img src="${p.image_url}" alt="${p.name}" onerror="this.src=''">`
          : `<span class="cdp-no-img">🌬️</span>`}
        <div class="cdp-glow"></div>
      </div>

      <div class="cdp-body">
        ${p.category_name ? `<div class="cdp-cat" style="background:${colors.bg}">${p.category_icon||''} ${p.category_name}</div>` : ''}
        <div class="cdp-name">${p.name}</div>
        ${p.brand ? `<div class="cdp-brand">${p.brand}${p.size ? ' · ' + p.size : ''}</div>` : ''}

        ${flavorTags ? `<div class="cdp-tags">${flavorTags}</div>` : ''}

        ${shortDesc ? `<div class="cdp-short-desc">"${shortDesc}"</div>` : ''}
        ${fullDesc  ? `<div class="cdp-full-desc">${fullDesc}</div>`   : ''}

        <div class="cdp-footer">
          <div class="cdp-price">
            ${p.price ? `<small>€</small>${parseFloat(p.price).toFixed(2)}` : '<span style="color:#bbb">Prix non défini</span>'}
          </div>
        </div>
      </div>
    </div>`;

  popup.style.display = 'flex';
  // Trigger animation
  requestAnimationFrame(() => {
    popup.querySelector('.cdp-card').classList.add('cdp-animate-in');
  });
}

function closeCardDetail() {
  const popup = document.getElementById('cardDetailPopup');
  const card  = popup.querySelector('.cdp-card');
  if (card) {
    card.classList.add('cdp-animate-out');
    setTimeout(() => { popup.style.display = 'none'; popup.innerHTML = ''; }, 300);
  } else {
    popup.style.display = 'none';
  }
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
  if (pubScanner) {
    pubScanner.stop().catch(() => {});
    pubScanner = null;
  }
  document.getElementById('scannerOverlay').style.display = 'none';
}

async function findProductByBarcode(barcode) {
  try {
    const res = await fetch(`${API}?action=find_barcode&barcode=${encodeURIComponent(barcode)}`);
    const p   = await res.json();
    closeScanner();
    if (p && p.id) {
      showFoundPopup(p);
    } else {
      showNotFound(barcode);
    }
  } catch(e) {
    closeScanner();
    showNotFound(barcode);
  }
}

function showFoundPopup(p) {
  const popup    = document.getElementById('foundPopup');
  const colorMap = { 'Goût Tabac':'#8B6914','Goût Gourmand':'#E67E22','Fruité':'#E74C3C','Fruité Fresh':'#2ECC71' };
  const color    = colorMap[p.category_name] || '#e94560';

  document.getElementById('foundImg').innerHTML = p.image_url
    ? `<img src="${p.image_url}" style="width:120px;height:120px;object-fit:contain;border-radius:12px;background:#f5f5f5;padding:8px">`
    : `<div style="font-size:72px">🌬️</div>`;
  document.getElementById('foundName').textContent   = p.name;
  document.getElementById('foundFlavor').textContent = p.flavor ? '🍓 ' + p.flavor : '';
  document.getElementById('foundPrice').textContent  = p.price  ? '€ ' + parseFloat(p.price).toFixed(2) : '';
  document.getElementById('foundCat').innerHTML = p.category_name
    ? `<span style="background:${color};color:white;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700">${p.category_icon||''} ${p.category_name}</span>`
    : '';
  popup.style.display = 'flex';
}

function showNotFound(barcode) {
  const popup = document.getElementById('foundPopup');
  document.getElementById('foundImg').innerHTML    = '<div style="font-size:64px">❓</div>';
  document.getElementById('foundName').textContent = 'Produit non trouvé';
  document.getElementById('foundFlavor').textContent = 'Barkod: ' + barcode;
  document.getElementById('foundPrice').textContent  = '';
  document.getElementById('foundCat').innerHTML       = '';
  popup.style.display = 'flex';
}

function closeFound() {
  document.getElementById('foundPopup').style.display = 'none';
}
