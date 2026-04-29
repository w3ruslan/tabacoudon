const API = 'api/products.php';
let currentCat   = 0;
let pubScanner   = null;
let productsCache = [];

function escapeHtml(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
  });
}

function safeColor(value) {
  var color = String(value || '');
  return /^#[0-9a-f]{3,8}$/i.test(color) ? color : '#e94560';
}

function safeImageUrl(value) {
  var url = String(value || '').trim();
  if (!url) return '';
  if (/^uploads\/[A-Za-z0-9._/-]+\.(jpe?g|png|gif|webp)$/i.test(url)) return url;
  if (/^https?:\/\//i.test(url)) return url.replace(/"/g, '%22').replace(/</g, '%3C').replace(/>/g, '%3E');
  return '';
}

// ─── Cart ─────────────────────────────────────────
var cart = JSON.parse(localStorage.getItem('tc_cart') || '[]');

function saveCart() {
  localStorage.setItem('tc_cart', JSON.stringify(cart));
  updateCartBadge();
}

function updateCartBadge() {
  var total = cart.reduce(function(s, i) { return s + i.qty; }, 0);
  var badge = document.getElementById('cartBadge');
  if (!badge) return;
  badge.textContent = total;
  badge.style.display = total > 0 ? 'flex' : 'none';
}

function addToCart(id, name, price, size, event) {
  if (event) event.stopPropagation();
  var existing = cart.find(function(i) { return i.id === id; });
  if (existing) {
    existing.qty++;
  } else {
    cart.push({ id: id, name: name, price: price, size: size, qty: 1 });
  }
  saveCart();
  // Flash feedback on button
  var btn = document.querySelector('.tc-cart-btn[data-id="' + id + '"]');
  if (btn) {
    var origBg   = btn.style.background;
    var origHTML = btn.innerHTML;
    btn.innerHTML = '✓';
    btn.style.background = '#2ecc71';
    setTimeout(function() {
      btn.innerHTML = origHTML;
      btn.style.background = origBg;
    }, 900);
  }
}

function openCart() {
  renderCartPanel();
  document.getElementById('cartOverlay').style.display = 'flex';
  setTimeout(function() {
    document.getElementById('cartPanel').classList.add('cart-open');
  }, 10);
}

function closeCart() {
  document.getElementById('cartPanel').classList.remove('cart-open');
  setTimeout(function() {
    document.getElementById('cartOverlay').style.display = 'none';
  }, 300);
}

function closeCartOutside(e) {
  if (e.target === document.getElementById('cartOverlay')) closeCart();
}

function renderCartPanel() {
  var itemsEl  = document.getElementById('cartItems');
  var footerEl = document.getElementById('cartFooter');
  var emptyEl  = document.getElementById('cartEmpty');

  if (!cart.length) {
    itemsEl.innerHTML = '';
    footerEl.style.display = 'none';
    emptyEl.style.display  = 'flex';
    return;
  }

  emptyEl.style.display  = 'none';
  footerEl.style.display = 'block';

  var total = 0;
  itemsEl.innerHTML = cart.map(function(item) {
    var subtotal = item.price * item.qty;
    var itemId = String(item.id).replace(/[^A-Za-z0-9_-]/g, '');
    total += subtotal;
    return '<div class="cart-item">'
      + '<div class="cart-item-info">'
      + '<div class="cart-item-name">' + escapeHtml(item.name) + '</div>'
      + (item.size ? '<div class="cart-item-size">' + escapeHtml(item.size) + '</div>' : '')
      + '<div class="cart-item-price">€' + item.price.toFixed(2) + ' / unité</div>'
      + '</div>'
      + '<div class="cart-item-controls">'
      + '<button onclick="changeQty(\'' + itemId + '\', -1)">−</button>'
      + '<span>' + item.qty + '</span>'
      + '<button onclick="changeQty(\'' + itemId + '\', 1)">+</button>'
      + '<button class="cart-item-del" onclick="removeFromCart(\'' + itemId + '\')">🗑️</button>'
      + '</div>'
      + '</div>';
  }).join('');

  document.getElementById('cartTotal').textContent = '€' + total.toFixed(2);

  // WhatsApp link
  var msg = 'Bonjour ! Je voudrais commander :\n\n';
  cart.forEach(function(item) {
    msg += '• ' + item.name + (item.size ? ' (' + item.size + ')' : '') + ' x' + item.qty + ' - €' + (item.price * item.qty).toFixed(2) + '\n';
  });
  msg += '\nTotal : €' + total.toFixed(2) + '\n\nMerci !';
  document.getElementById('whatsappBtn').href =
    'https://wa.me/' + WHATSAPP_NUMBER + '?text=' + encodeURIComponent(msg);
}

function changeQty(id, delta) {
  var item = cart.find(function(i) { return i.id === id; });
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) cart = cart.filter(function(i) { return i.id !== id; });
  saveCart();
  renderCartPanel();
}

function removeFromCart(id) {
  cart = cart.filter(function(i) { return i.id !== id; });
  saveCart();
  renderCartPanel();
}

function clearCart() {
  cart = [];
  saveCart();
  renderCartPanel();
}

const catColors = {}; // DB'den doldurulur

window.addEventListener('DOMContentLoaded', function() {
  // Önce kategorileri yükle, sonra ürünleri — renk eşleşmesi için
  loadCategories().then(function() { loadProducts(0); });
  updateCartBadge();

  // Event delegation — kart tıklamaları
  document.getElementById('productGrid').addEventListener('click', function(e) {
    var el = e.target;
    while (el && el !== this) {
      if (el.classList && el.classList.contains('tc-cart-btn')) {
        addToCart(el.dataset.id, el.dataset.name, parseFloat(el.dataset.price || '0'), el.dataset.size || '', e);
        return;
      }
      if (el.classList && el.classList.contains('tc-card')) {
        showDetail(el.getAttribute('data-id'), el);
        return;
      }
      el = el.parentElement;
    }
  });
});

function loadCategories() {
  return fetch('api/categories.php?action=list')
    .then(function(r){ return r.json(); })
    .then(function(cats){
      cats.forEach(function(c){
        catColors[c.name] = safeColor(c.color);
      });
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

  // Clear search on category switch
  var searchInput = document.getElementById('searchInput');
  if (searchInput) { searchInput.value = ''; document.getElementById('searchClear').style.display = 'none'; }

  var url = API + '?action=list' + (catId > 0 ? '&category=' + catId : '');
  fetch(url)
    .then(function(r){ return r.json(); })
    .then(function(products){
      loading.style.display = 'none';
      productsCache = products;
      if (!products.length) { empty.style.display = 'block'; return; }
      grid.innerHTML     = products.map(renderCard).join('');
      grid.style.display = 'grid';
      initBarcodes();
    });
}

function filterProducts(query) {
  var clear = document.getElementById('searchClear');
  var grid  = document.getElementById('productGrid');
  var empty = document.getElementById('emptyState');
  clear.style.display = query ? 'flex' : 'none';

  var q = query.trim().toLowerCase();
  if (!q) {
    grid.innerHTML = productsCache.map(renderCard).join('');
    grid.style.display = productsCache.length ? 'grid' : 'none';
    empty.style.display = productsCache.length ? 'none' : 'block';
    return;
  }

  var filtered = productsCache.filter(function(p) {
    return (p.name   || '').toLowerCase().indexOf(q) !== -1
        || (p.flavor || '').toLowerCase().indexOf(q) !== -1
        || (p.brand  || '').toLowerCase().indexOf(q) !== -1;
  });

  if (!filtered.length) {
    grid.style.display  = 'none';
    empty.style.display = 'block';
  } else {
    grid.innerHTML     = filtered.map(renderCard).join('');
    grid.style.display = 'grid';
    empty.style.display = 'none';
    initBarcodes();
  }
}

function clearSearch() {
  var input = document.getElementById('searchInput');
  input.value = '';
  filterProducts('');
  input.focus();
}

function renderCard(p) {
  var catColor = safeColor(catColors[p.category_name]);
  var catLabel = escapeHtml(p.category_name || '');
  var catIcon  = escapeHtml(p.category_icon || '');
  var imageUrl = safeImageUrl(p.image_url);
  var name     = escapeHtml(p.name || '');
  var brand    = escapeHtml(p.brand || '');
  var size     = escapeHtml(p.size || '');
  var barcode  = escapeHtml(p.barcode || '');

  var imgHtml = imageUrl
    ? '<img src="' + imageUrl + '" alt="' + name + '" loading="lazy" onerror="this.style.display=\'none\'">'
    : '<span class="tc-no-img">🌬️</span>';

  var price   = p.price ? '€' + parseFloat(p.price).toFixed(2) : '';
  var surCmde = p.sur_commande ? '<span class="tc-sc-pill">📦 Sur cmd</span>' : '';

  // Right column: flavor specs
  var flavors = (p.flavor || '').split(/[,\/]+/).map(function(f){ return f.trim(); }).filter(Boolean);
  var specsHtml = '';
  if (flavors.length) {
    specsHtml = '<div class="tc-spec-title">NOTES</div>'
      + '<div class="tc-spec-chips">'
      + flavors.slice(0, 3).map(function(f){
          return '<span class="tc-spec-chip" style="background:' + catColor + '18;color:' + catColor + ';border:1px solid ' + catColor + '35">' + escapeHtml(f) + '</span>';
        }).join('')
      + '</div>';
  } else if (catLabel) {
    specsHtml = '<div class="tc-spec-title">Catégorie</div>'
      + '<div class="tc-spec-chips"><span class="tc-spec-chip" style="background:' + catColor + '18;color:' + catColor + ';border:1px solid ' + catColor + '35">' + catLabel + '</span></div>';
  }

  return '<div class="tc-card ' + (specsHtml ? 'tc-has-specs' : 'tc-no-specs') + '" data-id="' + p.id + '" style="--cc:' + catColor + '">'
    // ── Top gradient + image (price moved to bottom) ──
    + '<div class="tc-card-top">'
    + '<div class="tc-img-box ' + (barcode ? 'has-barcode' : 'no-barcode') + '">'
    + imgHtml
    + '<div class="tc-vertical-barcode-wrap ' + (barcode ? 'has-barcode' : 'no-barcode') + '">'
    + (barcode ? '<div class="tc-vertical-barcode-rotator"><svg class="tc-vertical-barcode-svg" data-barcode="' + barcode + '"></svg></div>' : '')
    + '</div>'
    + '</div>'
    + '</div>'
    // ── Bottom two columns ──
    + '<div class="tc-card-bot">'
    + '<div class="tc-bot-left">'
    + '<div class="tc-card-name">' + name + '</div>'
    + (brand ? '<div class="tc-card-brand">' + brand + '</div>' : '')
    + '<div class="tc-bot-tags">'
    + (size ? '<span class="tc-size-label">' + size + '</span>' : '')
    + surCmde
    + '</div>'
    + '</div>'
    + (specsHtml ? '<div class="tc-bot-right">' + specsHtml + '</div>' : '')
    + '</div>'
    // ── Absolute overlay: combined cart + price pill (bottom-left) ──
    + '<button class="tc-cart-btn" data-id="' + escapeHtml(p.id) + '" data-name="' + escapeHtml(p.name || '') + '" data-price="' + (parseFloat(p.price)||0) + '" data-size="' + escapeHtml(p.size || '') + '" style="background:' + catColor + '">🛒'
    + (price ? '<span class="tc-pill-price">' + price + '</span>' : '')
    + '</button>'
    + '</div>';
}


function initBarcodes() {
  if (typeof JsBarcode === 'undefined') return;
  document.querySelectorAll('svg.tc-vertical-barcode-svg[data-barcode]').forEach(function(svg) {
    var code = svg.getAttribute('data-barcode');
    if (!code || svg.getAttribute('data-bc-done')) return;
    svg.setAttribute('data-bc-done', '1');
    var digits = code.replace(/\D/g, '');
    var format = /^\d{13}$/.test(digits) ? 'EAN13' : 'CODE128';
    var opts = { width: 2, height: 45, displayValue: true, fontSize: 9, textMargin: 2, textPosition: 'bottom',
                 margin: 4, background: '#ffffff', lineColor: '#111827' };
    try {
      JsBarcode(svg, format === 'EAN13' ? digits : code, Object.assign({}, opts, { format: format }));
    } catch(e) {
      try { JsBarcode(svg, code, Object.assign({}, opts, { format: 'CODE128' })); }
      catch(e2) { svg.closest('.tc-vertical-barcode-wrap').style.visibility = 'hidden'; }
    }
  });
}

function addToCache(p) {
  for (var i = 0; i < productsCache.length; i++) {
    if (String(productsCache[i].id) === String(p.id)) return;
  }
  productsCache.push(p);
}

function showDetail(id, sourceCard) {
  var p = null;
  for (var i = 0; i < productsCache.length; i++) {
    if (String(productsCache[i].id) === String(id)) { p = productsCache[i]; break; }
  }
  if (!p) { alert('Erreur: produit introuvable'); return; }

  var catColor  = safeColor(catColors[p.category_name]);
  var desc      = p.description || '';

  // ── YouTube embed detection ──────────────────────
  function getYoutubeId(text) {
    var m = text.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{11})/);
    return m ? m[1] : null;
  }
  var ytId      = getYoutubeId(desc);
  var cleanDesc = desc.replace(/https?:\/\/[^\s]*(youtube\.com|youtu\.be)[^\s]*/g, '').trim();
  var parts     = cleanDesc.split('\n\n');
  var shortDesc = parts[0] || '';
  var fullDesc  = parts[1] || '';

  var flavorTags = '';
  if (p.flavor) {
    p.flavor.split(/[,\/]+/).forEach(function(f){
      flavorTags += '<span class="dt-tag">' + escapeHtml(f.trim()) + '</span>';
    });
  }

  var detailImg = safeImageUrl(p.image_url);
  var imgHtml = detailImg
    ? '<img class="dt-img" src="' + detailImg + '" alt="' + escapeHtml(p.name || '') + '">'
    : '<div class="dt-no-img">🌬️</div>';

  // If there's a YouTube video, hide the product image and show the embed instead
  var ytHtml = '';
  if (ytId) {
    imgHtml = ''; // hide static image when video present
    ytHtml  = '<div class="dt-yt-wrap">'
            + '<iframe class="dt-yt-frame" src="https://www.youtube.com/embed/' + ytId + '?rel=0&playsinline=1" '
            + 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
            + '</div>';
  }

  var overlay = document.getElementById('detailOverlay');
  var frontHtml = sourceCard ? sourceCard.outerHTML : '';
  overlay.innerHTML =
    '<div class="dt-bg" onclick="closeDetail()"></div>'
  + '<div class="dt-flip-stage" id="dtFlipStage">'
  + '<div class="dt-card-face dt-card-front">' + frontHtml + '</div>'
  + '<div class="dt-card-face dt-card-back">'
  + '<div class="dt-panel" id="dtPanel">'
  + '  <div class="dt-header" style="background:linear-gradient(135deg,' + catColor + ' 0%,#1a1a2e 70%)">'
  + '    <button class="dt-close" onclick="closeDetail()">✕</button>'
  +      imgHtml
  + '    <div class="dt-hname">' + escapeHtml(p.name || '') + '</div>'
  + (p.brand ? '<div class="dt-hbrand">' + escapeHtml(p.brand) + (p.size ? ' · '+escapeHtml(p.size) : '') + '</div>' : '')
  + '  </div>'
  + '  <div class="dt-body">'
  + (p.category_name ? '<span class="dt-cat" style="background:' + catColor + '">' + escapeHtml(p.category_icon||'') + ' ' + escapeHtml(p.category_name) + '</span>' : '')
  + (flavorTags ? '<div class="dt-tags">' + flavorTags + '</div>' : '')
  +    ytHtml
  + (shortDesc ? '<div class="dt-short" style="border-color:' + catColor + '">"' + escapeHtml(shortDesc) + '"</div>' : '')
  + (fullDesc  ? '<div class="dt-full">' + escapeHtml(fullDesc) + '</div>'  : '')
  + '    <div class="dt-price">'
  + (p.price ? '<small>€</small>' + parseFloat(p.price).toFixed(2) : '<span style="color:#bbb;font-size:16px">Prix non défini</span>')
  + '    </div>'
  + '  </div>'
  + '</div>'
  + '</div>'
  + '</div>';

  overlay.style.display = 'flex';
  var stage = document.getElementById('dtFlipStage');
  if (stage && sourceCard && sourceCard.getBoundingClientRect) {
    var rect = sourceCard.getBoundingClientRect();
    var cardCenterX = rect.left + rect.width / 2;
    var cardCenterY = rect.top + rect.height / 2;
    var viewCenterX = window.innerWidth / 2;
    var viewCenterY = window.innerHeight / 2;
    stage.style.setProperty('--dt-start-x', (cardCenterX - viewCenterX) + 'px');
    stage.style.setProperty('--dt-start-y', (cardCenterY - viewCenterY) + 'px');
    stage.style.setProperty('--dt-start-scale', Math.min(1, rect.width / 420).toFixed(3));
  }
  setTimeout(function(){
    var activeStage = document.getElementById('dtFlipStage');
    if (activeStage) activeStage.classList.add('dt-in');
  }, 10);
}

function closeDetail() {
  var stage = document.getElementById('dtFlipStage');
  if (!stage) return;
  stage.classList.remove('dt-in');
  stage.classList.add('dt-out');
  setTimeout(function(){
    var ov = document.getElementById('detailOverlay');
    ov.style.display = 'none';
    ov.innerHTML = '';
  }, 520);
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
  var color = safeColor(catColors[p.category_name]);
  var img = safeImageUrl(p.image_url);
  document.getElementById('foundImg').innerHTML = img
    ? '<img src="' + img + '" style="width:120px;height:120px;object-fit:contain">'
    : '<div style="font-size:72px">🌬️</div>';
  document.getElementById('foundName').textContent   = p.name;
  document.getElementById('foundFlavor').textContent = p.flavor ? '🍓 ' + p.flavor : '';
  document.getElementById('foundPrice').textContent  = p.price ? '€ ' + parseFloat(p.price).toFixed(2) : '';
  document.getElementById('foundCat').innerHTML = p.category_name
    ? '<span style="background:' + color + ';color:white;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700">' + escapeHtml(p.category_icon||'') + ' ' + escapeHtml(p.category_name) + '</span>'
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
