const API = 'api/products.php';
let currentCat   = 0;
let pubScanner   = null;
let productsCache = [];

const catColors = {
  'Goût Tabac':    '#8B6914',
  'Goût Gourmand': '#E67E22',
  'Fruité':        '#E74C3C',
  'Fruité Fresh':  '#2ECC71',
};

window.addEventListener('DOMContentLoaded', function() {
  loadCategories();
  loadProducts(0);

  // Event delegation — tüm kart tıklamalarını yakala
  document.getElementById('productGrid').addEventListener('click', function(e) {
    var el = e.target;
    // Karta tıklandığında en yakın .fc-card'ı bul
    while (el && el !== this) {
      if (el.classList && el.classList.contains('fc-card')) {
        showDetail(el.getAttribute('data-id'));
        return;
      }
      el = el.parentElement;
    }
  });
});

function loadCategories() {
  fetch(API + '?action=categories')
    .then(function(r){ return r.json(); })
    .then(function(cats){
      var wrap = document.getElementById('catButtons');
      cats.forEach(function(c){
        var btn = document.createElement('button');
        btn.className   = 'cat-btn';
        btn.dataset.cat = c.id;
        btn.textContent = c.icon + ' ' + c.name;
        btn.addEventListener('click', function(){ switchCat(c.id, btn); });
        wrap.appendChild(btn);
      });
    });
}

function switchCat(catId, btn) {
  document.querySelectorAll('.cat-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  currentCat = catId;
  loadProducts(catId);
}

function loadProducts(catId) {
  var loading = document.getElementById('loading');
  var grid    = document.getElementById('productGrid');
  var empty   = document.getElementById('emptyState');
  loading.style.display = 'block';
  grid.style.display    = 'none';
  empty.style.display   = 'none';

  var url = API + '?action=list' + (catId > 0 ? '&category=' + catId : '');
  fetch(url)
    .then(function(r){ return r.json(); })
    .then(function(products){
      loading.style.display = 'none';
      productsCache = products;
      if (!products.length) { empty.style.display = 'block'; return; }
      grid.innerHTML     = products.map(renderCard).join('');
      grid.style.display = 'grid';
    });
}

function renderCard(p) {
  var catColor = catColors[p.category_name] || '#e94560';
  var imgHtml  = p.image_url
    ? '<img src="' + p.image_url + '" alt="' + p.name + '" onerror="this.style.display=\'none\'">'
    : '<span class="no-img">🌬️</span>';
  var priceHtml = p.price
    ? '<span class="fc-price"><small>€</small>' + parseFloat(p.price).toFixed(2) + '</span>'
    : '<span class="fc-price" style="color:#bbb">—</span>';
  var brandHtml = p.brand ? '<span class="fc-brand">' + p.brand + '</span>' : '';
  var catHtml   = p.category_name ? '<div class="fc-cat">' + (p.category_icon||'') + ' ' + p.category_name + '</div>' : '';
  var flavHtml  = p.flavor ? '<div class="fc-flavor">🍓 ' + p.flavor + '</div>' : '';

  return '<div class="fc-card" data-id="' + p.id + '" style="border-top:5px solid ' + catColor + '">'
    + '<div class="fc-img">' + imgHtml + '</div>'
    + '<div class="fc-body">' + catHtml + '<div class="fc-name">' + p.name + '</div>' + flavHtml + '</div>'
    + '<div class="fc-footer">' + priceHtml + brandHtml + '</div>'
    + '<div class="fc-tap">👆 Touchez pour les détails</div>'
    + '</div>';
}

function showDetail(id) {
  var p = null;
  for (var i = 0; i < productsCache.length; i++) {
    if (String(productsCache[i].id) === String(id)) { p = productsCache[i]; break; }
  }
  if (!p) { alert('Erreur: produit introuvable'); return; }

  var catColor  = catColors[p.category_name] || '#e94560';
  var parts     = (p.description || '').split('\n\n');
  var shortDesc = parts[0] || '';
  var fullDesc  = parts[1] || '';

  var flavorTags = '';
  if (p.flavor) {
    p.flavor.split(/[,\/]+/).forEach(function(f){
      flavorTags += '<span class="dt-tag">' + f.trim() + '</span>';
    });
  }

  var imgHtml = p.image_url
    ? '<img class="dt-img" src="' + p.image_url + '" alt="' + p.name + '">'
    : '<div class="dt-no-img">🌬️</div>';

  var overlay = document.getElementById('detailOverlay');
  overlay.innerHTML =
    '<div class="dt-bg" onclick="closeDetail()"></div>'
  + '<div class="dt-panel" id="dtPanel">'
  + '  <div class="dt-header" style="background:linear-gradient(135deg,' + catColor + ' 0%,#1a1a2e 70%)">'
  + '    <button class="dt-close" onclick="closeDetail()">✕</button>'
  +      imgHtml
  + '    <div class="dt-hname">' + p.name + '</div>'
  + (p.brand ? '<div class="dt-hbrand">' + p.brand + (p.size ? ' · '+p.size : '') + '</div>' : '')
  + '  </div>'
  + '  <div class="dt-body">'
  + (p.category_name ? '<span class="dt-cat" style="background:' + catColor + '">' + (p.category_icon||'') + ' ' + p.category_name + '</span>' : '')
  + (flavorTags ? '<div class="dt-tags">' + flavorTags + '</div>' : '')
  + (shortDesc ? '<div class="dt-short" style="border-color:' + catColor + '">"' + shortDesc + '"</div>' : '')
  + (fullDesc  ? '<div class="dt-full">' + fullDesc + '</div>'  : '')
  + '    <div class="dt-price">'
  + (p.price ? '<small>€</small>' + parseFloat(p.price).toFixed(2) : '<span style="color:#bbb;font-size:16px">Prix non défini</span>')
  + '    </div>'
  + '  </div>'
  + '</div>';

  overlay.style.display = 'flex';
  setTimeout(function(){
    var panel = document.getElementById('dtPanel');
    if (panel) panel.classList.add('dt-in');
  }, 10);
}

function closeDetail() {
  var panel = document.getElementById('dtPanel');
  if (!panel) return;
  panel.classList.remove('dt-in');
  panel.classList.add('dt-out');
  setTimeout(function(){
    var ov = document.getElementById('detailOverlay');
    ov.style.display = 'none';
    ov.innerHTML = '';
  }, 350);
}

// ── Scanner ───────────────────────────────
function openScanner() {
  document.getElementById('scannerOverlay').style.display = 'flex';
  document.getElementById('scannerStatus').textContent = '';
  pubScanner = new Html5Qrcode('scannerBox');
  pubScanner.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 280, height: 140 } },
    function(barcode) { findProductByBarcode(barcode); },
    function() {}
  ).catch(function(err){
    document.getElementById('scannerStatus').textContent = '❌ Caméra inaccessible';
  });
}

function closeScanner() {
  if (pubScanner) { pubScanner.stop().catch(function(){}); pubScanner = null; }
  document.getElementById('scannerOverlay').style.display = 'none';
}

function findProductByBarcode(barcode) {
  document.getElementById('scannerStatus').textContent = '🔍 Recherche...';
  fetch(API + '?action=find_barcode&barcode=' + encodeURIComponent(barcode))
    .then(function(r){ return r.json(); })
    .then(function(p){
      closeScanner();
      if (p && p.id) showFoundPopup(p); else showNotFound(barcode);
    })
    .catch(function(){ closeScanner(); showNotFound(barcode); });
}

function showFoundPopup(p) {
  var color = catColors[p.category_name] || '#e94560';
  document.getElementById('foundImg').innerHTML = p.image_url
    ? '<img src="' + p.image_url + '" style="width:120px;height:120px;object-fit:contain">'
    : '<div style="font-size:72px">🌬️</div>';
  document.getElementById('foundName').textContent   = p.name;
  document.getElementById('foundFlavor').textContent = p.flavor ? '🍓 ' + p.flavor : '';
  document.getElementById('foundPrice').textContent  = p.price ? '€ ' + parseFloat(p.price).toFixed(2) : '';
  document.getElementById('foundCat').innerHTML = p.category_name
    ? '<span style="background:' + color + ';color:white;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700">' + (p.category_icon||'') + ' ' + p.category_name + '</span>'
    : '';
  document.getElementById('foundPopup').style.display = 'flex';
}

function showNotFound(barcode) {
  document.getElementById('foundImg').innerHTML      = '<div style="font-size:64px">❓</div>';
  document.getElementById('foundName').textContent   = 'Produit non trouvé';
  document.getElementById('foundFlavor').textContent = 'Barkod: ' + barcode;
  document.getElementById('foundPrice').textContent  = '';
  document.getElementById('foundCat').innerHTML      = '';
  document.getElementById('foundPopup').style.display = 'flex';
}

function closeFound() {
  document.getElementById('foundPopup').style.display = 'none';
}
