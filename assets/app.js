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

  // Event delegation — kart tıklamaları
  document.getElementById('productGrid').addEventListener('click', function(e) {
    var el = e.target;
    // "Fermer" butonuna basıldıysa sadece kart kapat
    if (el.classList && el.classList.contains('tc-back-close')) {
      var card = el.closest('.tc-card');
      if (card) card.classList.remove('flipped');
      return;
    }
    // "Détails" butonuna basıldıysa modal aç
    if (el.classList && el.classList.contains('tc-back-btn')) {
      var card = el.closest('.tc-card');
      if (card) showDetail(card.getAttribute('data-id'));
      return;
    }
    while (el && el !== this) {
      if (el.classList && el.classList.contains('tc-card')) {
        if (el.classList.contains('flipped')) {
          el.classList.remove('flipped');
        } else {
          document.querySelectorAll('.tc-card.flipped').forEach(function(c){ c.classList.remove('flipped'); });
          el.classList.add('flipped');
        }
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
  var catLabel = p.category_name || '';
  var catIcon  = p.category_icon || '';

  var imgHtml = p.image_url
    ? '<img src="' + p.image_url + '" alt="' + p.name + '" onerror="this.style.display=\'none\'">'
    : '<span class="tc-no-img">🌬️</span>';

  var desc = (p.description || '').split('\n\n')[0];
  if (desc.length > 150) desc = desc.slice(0, 148) + '…';

  var price = p.price ? '€' + parseFloat(p.price).toFixed(2) : '—';

  var flavorTags = '';
  if (p.flavor) {
    p.flavor.split(/[,\/]+/).forEach(function(f){
      flavorTags += '<span class="tc-back-tag">' + f.trim() + '</span>';
    });
  }

  var backImg = p.image_url
    ? '<img class="tc-back-img" src="' + p.image_url + '" alt="' + p.name + '" onerror="this.style.display=\'none\'">'
    : '';

  return '<div class="tc-card" data-id="' + p.id + '" style="--cc:' + catColor + '">'
    + '<div class="tc-card-inner">'
    // ── Ön yüz ──
    + '<div class="tc-card-front">'
    + '<div class="tc-frame">'
    + '<div class="tc-header">' + catIcon + ' ' + catLabel + '</div>'
    + '<div class="tc-img-wrap">' + imgHtml + '</div>'
    + '<div class="tc-namebar">'
    + '<span class="tc-name">' + p.name + '</span>'
    + '<span class="tc-price">' + price + '</span>'
    + '</div>'
    + (p.flavor ? '<div class="tc-flavor">🍓 ' + p.flavor + '</div>' : '')
    + (desc ? '<div class="tc-desc">' + desc + '</div>' : '')
    + '</div>'
    + '</div>'
    // ── Arka yüz ──
    + '<div class="tc-card-back" style="background:linear-gradient(160deg,var(--cc) 0%,#0d0d1a 75%)">'
    + backImg
    + '<div class="tc-back-cat">' + catIcon + ' ' + catLabel + '</div>'
    + '<div class="tc-back-name">' + p.name + '</div>'
    + (flavorTags ? '<div class="tc-back-tags">' + flavorTags + '</div>' : '')
    + (desc ? '<div class="tc-back-desc">"' + desc + '"</div>' : '')
    + '<div class="tc-back-price">' + price + '</div>'
    + '<div class="tc-back-actions">'
    + '<button class="tc-back-btn">Voir détails</button>'
    + '<button class="tc-back-close">✕</button>'
    + '</div>'
    + '</div>'
    + '</div>'
    + '</div>';
}

function addToCache(p) {
  for (var i = 0; i < productsCache.length; i++) {
    if (String(productsCache[i].id) === String(p.id)) return;
  }
  productsCache.push(p);
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

// ── Scanner (Quagga2) ─────────────────────
var _scanRunning = false;

function openScanner() {
  document.getElementById('scannerOverlay').style.display = 'flex';
  document.getElementById('scannerStatus').textContent = '📷 Démarrage...';
  _scanRunning = true;

  Quagga.init({
    inputStream: {
      name: 'Live',
      type: 'LiveStream',
      target: document.getElementById('scannerBox'),
      constraints: {
        facingMode: 'environment',
        width: { ideal: 1280 },
        height: { ideal: 720 }
      }
    },
    locator: { patchSize: 'medium', halfSample: true },
    numOfWorkers: 0,
    frequency: 15,
    decoder: {
      readers: [
        'ean_reader', 'ean_8_reader', 'upc_reader',
        'upc_e_reader', 'code_128_reader', 'code_39_reader'
      ]
    },
    locate: true
  }, function(err) {
    if (err) {
      document.getElementById('scannerStatus').textContent = '❌ Caméra inaccessible';
      return;
    }
    document.getElementById('scannerStatus').textContent = '🎯 Pointez le code-barres';
    Quagga.start();
  });

  var _lastCode = '';
  var _lastTime = 0;
  Quagga.onDetected(function(result) {
    if (!_scanRunning) return;
    var code = result.codeResult.code;
    var now = Date.now();
    // Debounce: same code twice within 1.5s
    if (code === _lastCode && now - _lastTime < 1500) return;
    _lastCode = code;
    _lastTime = now;
    // Require at least 2 consistent reads
    var decodedList = result.codeResult;
    _scanRunning = false;
    findProductByBarcode(code);
  });
}

function closeScanner() {
  _scanRunning = false;
  Quagga.offDetected();
  try { Quagga.stop(); } catch(e) {}
  document.getElementById('scannerOverlay').style.display = 'none';
  // Clear the video element
  var box = document.getElementById('scannerBox');
  if (box) box.innerHTML = '';
}

function findProductByBarcode(barcode) {
  var status = document.getElementById('scannerStatus');
  status.textContent = '🔍 Recherche...';
  // Normalize: strip leading zeros for fallback
  var stripped = barcode.replace(/^0+/, '') || barcode;
  fetch(API + '?action=find_barcode&barcode=' + encodeURIComponent(barcode))
    .then(function(r){ return r.json(); })
    .then(function(p){
      if (p && p.id) { closeScanner(); addToCache(p); showDetail(p.id); return; }
      // Retry with stripped leading zeros
      if (stripped !== barcode) {
        return fetch(API + '?action=find_barcode&barcode=' + encodeURIComponent(stripped))
          .then(function(r){ return r.json(); })
          .then(function(p2){
            closeScanner();
            if (p2 && p2.id) { addToCache(p2); showDetail(p2.id); } else showNotFound(barcode);
          });
      }
      closeScanner();
      showNotFound(barcode);
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
