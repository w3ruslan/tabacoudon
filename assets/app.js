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
  const colorClass = catColorMap[p.category_name] || '';
  const colors = {
    'Goût Tabac':    '#8B6914',
    'Goût Gourmand': '#E67E22',
    'Fruité':        '#E74C3C',
    'Fruité Fresh':  '#2ECC71',
  };
  const catColor = colors[p.category_name] || '#e94560';

  const imgFront = p.image_url
    ? `<img src="${p.image_url}" alt="${p.name}" onerror="this.style.display='none'">`
    : `<span class="no-img">🌬️</span>`;

  const price = p.price
    ? `<span class="flip-price"><small>€</small>${parseFloat(p.price).toFixed(2)}</span>`
    : `<span class="flip-price" style="color:#bbb;font-size:16px">—</span>`;

  const parts     = (p.description || '').split('\n\n');
  const shortDesc = parts[0] || '';
  const fullDesc  = parts[1] || '';

  const flavorTags = p.flavor
    ? p.flavor.split(/[,\/\+]+/).map(f =>
        `<span class="flip-tag">${f.trim()}</span>`
      ).join('')
    : '';

  return `
    <div class="flip-wrap" onclick="flipCard(this)">
      <div class="flip-inner">

        <!-- FRONT -->
        <div class="flip-front" style="border-top: 5px solid ${catColor}">
          <div class="flip-front-img">${imgFront}</div>
          <div class="flip-front-body">
            ${p.category_name ? `<div class="flip-cat" style="color:${catColor}">${p.category_icon||''} ${p.category_name}</div>` : ''}
            <div class="flip-name">${p.name}</div>
            ${p.flavor ? `<div class="flip-flavor">🍓 ${p.flavor}</div>` : ''}
          </div>
          <div class="flip-front-footer">
            ${price}
            ${p.brand ? `<span class="flip-brand">${p.brand}</span>` : ''}
          </div>
          <div class="flip-hint">👆 Touchez pour voir</div>
        </div>

        <!-- BACK -->
        <div class="flip-back" style="background: linear-gradient(160deg, ${catColor} 0%, #1a1a2e 60%)">
          <button class="flip-back-close" onclick="event.stopPropagation();flipCard(this.closest('.flip-wrap'))">✕</button>
          ${p.image_url ? `<img class="flip-back-img" src="${p.image_url}" alt="${p.name}">` : ''}
          <div class="flip-back-name">${p.name}</div>
          ${p.brand ? `<div class="flip-back-brand">${p.brand}${p.size ? ' · '+p.size : ''}</div>` : ''}
          ${flavorTags ? `<div class="flip-tags">${flavorTags}</div>` : ''}
          ${shortDesc ? `<div class="flip-desc-short">"${shortDesc}"</div>` : ''}
          ${fullDesc  ? `<div class="flip-desc-full">${fullDesc}</div>`   : ''}
          <div class="flip-back-price">
            ${p.price ? `€${parseFloat(p.price).toFixed(2)}` : ''}
          </div>
        </div>

      </div>
    </div>`;
}

// ── Flip card ────────────────────────────
function flipCard(wrap) {
  const inner = wrap.querySelector('.flip-inner');
  const isFlipped = inner.classList.contains('flipped');
  document.querySelectorAll('.flip-inner.flipped').forEach(el => el.classList.remove('flipped'));
  if (!isFlipped) inner.classList.add('flipped');
}
// legacy - keep empty to avoid errors
const colorMap = {};


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
