// ══════════════════════════════════════════
// TABACOUDON — Catalogue public
// ══════════════════════════════════════════

const API = 'api/products.php';
let currentCat = 0;

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

  grid.innerHTML    = products.map(renderCard).join('');
  grid.style.display = 'grid';
}

// ── Render card ───────────────────────────
function renderCard(p) {
  const colorClass = catColorMap[p.category_name] || '';
  const price      = p.price
    ? `<span class="card-price"><small>€</small>${parseFloat(p.price).toFixed(2)}</span>`
    : `<span class="card-price" style="color:#bbb;font-size:14px">—</span>`;
  const img = p.image_url
    ? `<img src="${p.image_url}" alt="${p.name}" loading="lazy" onerror="this.parentElement.innerHTML='<span class=no-img>🌬️</span>'">`
    : `<span class="no-img">🌬️</span>`;
  const catLabel = p.category_name
    ? `<div class="card-cat">${p.category_icon || ''} ${p.category_name}</div>`
    : '';
  const flavor   = p.flavor
    ? `<div class="card-flavor">🍓 ${p.flavor}</div>`
    : '';
  const rawDesc = p.description ? p.description.split('\n\n')[0] : '';
  const desc = rawDesc
    ? `<div class="card-desc">${rawDesc.length > 150 ? rawDesc.slice(0,148) + '…' : rawDesc}</div>`
    : '';
  const brand    = p.brand
    ? `<span class="card-badge">${p.brand}</span>`
    : '';

  return `
    <div class="product-card ${colorClass}">
      <div class="card-img">${img}</div>
      <div class="card-body">
        ${catLabel}
        <div class="card-name">${p.name}</div>
        ${flavor}
        ${desc}
      </div>
      <div class="card-footer">
        ${price}
        ${brand}
      </div>
    </div>`;
}
