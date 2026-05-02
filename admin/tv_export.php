<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

$raw = $_POST['ids'] ?? '';
$ids = [];
foreach (array_map('intval', json_decode($raw, true) ?: []) as $id) {
    if ($id > 0 && !in_array($id, $ids, true)) {
        $ids[] = $id;
    }
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tvCategoryColor($value): string {
    $color = trim((string)($value ?? ''));
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#22c55e';
}

function tvImagePath($image, int $productId = 0): string {
    $image = trim((string)$image);
    if ($image === '') return '';
    $stamp = $productId > 0 ? (string)$productId : md5($image);
    if (strpos($image, 'uploads/') === 0 || preg_match('#^https?://#i', $image)) {
        return 'tv_image.php?src=' . rawurlencode($image) . '&pid=' . rawurlencode($stamp);
    }
    $separator = strpos($image, '?') === false ? '?' : '&';
    return $image . $separator . 'pid=' . rawurlencode($stamp);
}

function tvProductNotes(array $product): array {
    $source = trim((string)($product['flavor'] ?? ''));
    if ($source === '') {
        $source = trim((string)($product['category_name'] ?? ''));
    }
    if ($source === '') return [];
    $parts = preg_split('/[,\/]+/', $source) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts)));
    return array_slice($parts, 0, 2);
}

function tvLower(string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function tvProductKey(array $product): string {
    $barcode = preg_replace('/\D+/', '', (string)($product['barcode'] ?? ''));
    if ($barcode !== '') {
        return 'barcode:' . $barcode;
    }
    $parts = [
        trim((string)($product['name'] ?? '')),
        trim((string)($product['brand'] ?? '')),
        trim((string)($product['size'] ?? '')),
        trim((string)($product['image_url'] ?? '')),
    ];
    return 'product:' . tvLower(implode('|', $parts));
}

if (!$ids) {
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:system-ui;padding:40px;color:#64748b">Aucun produit sélectionné.</div>';
    exit;
}

$db = getDB();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare(
    "SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id IN ($placeholders)"
);
$stmt->execute(array_values($ids));
$fetched = $stmt->fetchAll();

$products = [];
$seenProducts = [];
foreach ($ids as $id) {
    foreach ($fetched as $p) {
        if ((int)$p['id'] === (int)$id) {
            $productKey = tvProductKey($p);
            if (isset($seenProducts[$productKey])) {
                break;
            }
            $seenProducts[$productKey] = true;
            $products[] = $p;
            break;
        }
    }
}

$screens = array_chunk($products, 6);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Samsung Business TV Export — <?= e(SHOP_NAME) ?></title>
  <link rel="stylesheet" href="../assets/product-card.css?v=<?= filemtime(__DIR__ . '/../assets/product-card.css') ?>">
  <link rel="stylesheet" href="assets/tv-export.css?v=<?= filemtime(__DIR__ . '/assets/tv-export.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
</head>
<body>
<?php if (!$products): ?>
  <div class="tv-empty">Aucun produit trouvé.</div>
<?php else: ?>
  <div class="tv-toolbar">
    <h1>Samsung Business TV Export</h1>
    <span><?= count($products) ?> produit(s) · <?= count($screens) ?> écran(s) · PNG 3840×2160</span>
    <span class="tv-status" id="tvStatus">Prêt</span>
    <button type="button" onclick="exportTvScreens()">Télécharger ZIP</button>
    <button type="button" onclick="window.close()">Fermer</button>
  </div>

  <main class="tv-preview-list">
    <?php foreach ($screens as $screenIndex => $screenProducts): ?>
      <section>
        <div style="font:900 13px/1 system-ui;margin:0 0 10px;color:#475569">tv-screen-<?= str_pad((string)($screenIndex + 1), 2, '0', STR_PAD_LEFT) ?>.png</div>
        <div class="tv-preview-shell">
          <div class="tv-screen" data-screen-index="<?= (int)$screenIndex ?>">
            <?php foreach ($screenProducts as $p):
              $name = trim((string)($p['name'] ?? '')) ?: 'Produit';
              $brand = trim((string)($p['brand'] ?? ''));
              $size = trim((string)($p['size'] ?? ''));
              $price = ($p['price'] ?? '') !== '' && $p['price'] !== null ? '€' . number_format((float)$p['price'], 2) : '';
              $image = tvImagePath($p['image_url'] ?? '', (int)$p['id']);
              $notes = tvProductNotes($p);
              $categoryColor = tvCategoryColor($p['category_color'] ?? '');
              $surCommande = !empty($p['sur_commande']);
            ?>
              <div class="tv-cell">
                <article class="tc-card <?= $notes ? 'tc-has-specs' : 'tc-no-specs' ?>" data-product-id="<?= (int)$p['id'] ?>" style="--cc: <?= e($categoryColor) ?>; --category-color: <?= e($categoryColor) ?>">
                  <div class="tc-card-top">
                    <div class="tc-img-box">
                      <div class="tc-product-visual">
                        <?php if ($image): ?>
                          <img src="<?= e($image) ?>" alt="<?= e($name) ?>" crossorigin="anonymous" data-original-src="<?= e($image) ?>">
                        <?php else: ?>
                          <span class="tc-no-img">🌬️</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($price): ?><div class="tc-photo-price"><?= e($price) ?></div><?php endif; ?>
                      <button class="tc-photo-cart tc-cart-btn" type="button"><span>🛒</span><strong>AJOUTER<br>AU PANIER</strong></button>
                    </div>
                  </div>

                  <section class="tc-card-bot">
                    <div class="tc-bot-left">
                      <div class="tc-card-name"><?= e($name) ?></div>
                      <?php if ($brand): ?><div class="tc-card-brand"><?= e($brand) ?></div><?php endif; ?>
                      <div class="tc-bot-tags">
                        <?php if ($size): ?><span class="tc-size-label"><?= e($size) ?></span><?php endif; ?>
                        <?php if ($surCommande): ?><span class="tc-sc-pill">📦 Sur cmd</span><?php endif; ?>
                      </div>
                    </div>

                    <?php if ($notes): ?>
                      <aside class="tc-bot-right">
                        <div class="tc-spec-title">NOTES</div>
                        <div class="tc-spec-chips">
                          <?php foreach ($notes as $note): ?>
                            <span class="tc-spec-chip"><?= e($note) ?></span>
                          <?php endforeach; ?>
                        </div>
                      </aside>
                    <?php endif; ?>
                  </section>

                  <div class="tc-horizontal-barcode-wrap no-barcode"></div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </main>
<?php endif; ?>

<script>
const TV_WIDTH = 3840;
const TV_HEIGHT = 2160;
let tvExportRunning = false;

function setStatus(text) {
  const status = document.getElementById('tvStatus');
  if (status) status.textContent = text;
}

function waitForImages(root) {
  const images = Array.from(root.querySelectorAll('img'));
  return Promise.all(images.map(function(img) {
    if (img.complete && img.naturalWidth > 0) return Promise.resolve();
    return new Promise(function(resolve) {
      img.addEventListener('load', resolve, { once: true });
      img.addEventListener('error', resolve, { once: true });
    });
  }));
}

function blobToDataUrl(blob) {
  return new Promise(function(resolve, reject) {
    const reader = new FileReader();
    reader.onload = function() { resolve(reader.result); };
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

async function inlineScreenImages(screen) {
  const images = Array.from(screen.querySelectorAll('img'));
  await Promise.all(images.map(async function(img) {
    const original = img.getAttribute('data-original-src') || img.currentSrc || img.src;
    if (!original || original.startsWith('data:')) return;
    try {
      const response = await fetch(original, { cache: 'reload', credentials: 'same-origin' });
      if (!response.ok) throw new Error('Image fetch failed: ' + response.status);
      const dataUrl = await blobToDataUrl(await response.blob());
      img.src = dataUrl;
      img.setAttribute('data-export-inlined', '1');
    } catch (error) {
      console.warn('TV image inline failed:', original, error);
    }
  }));
  await waitForImages(screen);
}

async function captureScreen(screen) {
  const ids = Array.from(screen.querySelectorAll('.tc-card[data-product-id]')).map(card => card.dataset.productId);
  console.log('capturing screen product ids:', ids.join(','));
  await waitForImages(screen);
  await inlineScreenImages(screen);
  if (document.fonts && document.fonts.ready) await document.fonts.ready;
  await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  const previousTransform = screen.style.transform;
  screen.style.transform = 'none';
  try {
    return await htmlToImage.toBlob(screen, {
      width: TV_WIDTH,
      height: TV_HEIGHT,
      canvasWidth: TV_WIDTH,
      canvasHeight: TV_HEIGHT,
      pixelRatio: 1,
      backgroundColor: '#f8fafc',
      cacheBust: true,
      imagePlaceholder: ''
    });
  } finally {
    screen.style.transform = previousTransform;
  }
}

async function exportTvScreens() {
  if (tvExportRunning) return;
  if (typeof htmlToImage === 'undefined' || typeof JSZip === 'undefined' || typeof saveAs === 'undefined') {
    alert('Export libraries could not be loaded.');
    return;
  }
  tvExportRunning = true;
  const screens = Array.from(document.querySelectorAll('.tv-screen'));
  if (!screens.length) {
    tvExportRunning = false;
    return;
  }
  const zip = new JSZip();
  setStatus('Préparation...');
  try {
    for (let i = 0; i < screens.length; i++) {
      setStatus('Export ' + (i + 1) + '/' + screens.length + '...');
      const blob = await captureScreen(screens[i]);
      if (!blob) throw new Error('PNG vide');
      const name = 'tv-screen-' + String(i + 1).padStart(2, '0') + '.png';
      zip.file(name, blob);
    }
    setStatus('ZIP...');
    const zipBlob = await zip.generateAsync({ type: 'blob', compression: 'STORE' });
    saveAs(zipBlob, 'tabacoudon-tv-export.zip');
    setStatus('Terminé');
  } catch (err) {
    console.error(err);
    setStatus('Erreur');
    alert('Export impossible: ' + (err && err.message ? err.message : err));
  } finally {
    tvExportRunning = false;
  }
}

window.addEventListener('load', function() {
  waitForImages(document).then(function() {
    document.querySelectorAll('.tv-screen').forEach(function(screen, index) {
      const ids = Array.from(screen.querySelectorAll('.tc-card[data-product-id]')).map(card => card.dataset.productId);
      console.log('tv-screen-' + String(index + 1).padStart(2, '0') + ' product ids:', ids.join(','));
    });
    setStatus('Prêt · export automatique...');
    setTimeout(exportTvScreens, 300);
  });
});
</script>
</body>
</html>
