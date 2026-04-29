<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

$raw = $_POST['ids'] ?? '';
$ids = array_filter(array_map('intval', json_decode($raw, true) ?: []));

if (empty($ids)) {
    echo '<p style="font-family:sans-serif;padding:40px;color:#0b4f4a">Aucun produit sélectionné.</p>';
    exit;
}

$db = getDB();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare(
    "SELECT p.*, c.name AS category_name, c.color AS category_color
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id IN ($placeholders)"
);
$stmt->execute(array_values($ids));
$fetched = $stmt->fetchAll();

$products = [];
foreach ($ids as $id) {
    foreach ($fetched as $p) {
        if ((int)$p['id'] === (int)$id) { $products[] = $p; break; }
    }
}

$pages = array_chunk($products, 9);

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function imagePath(?string $image): string {
    $image = trim((string)$image);
    if ($image !== '' && strpos($image, 'uploads/') === 0) {
        return '../' . $image;
    }
    return $image;
}

function productNotes(array $product): array {
    $source = trim((string)($product['flavor'] ?? ''));
    if ($source === '') {
        $source = trim((string)($product['description'] ?? ''));
    }
    $source = preg_replace('/https?:\/\/\S+/i', '', $source);
    $source = preg_replace('/\s+/', ' ', (string)$source);
    $parts = preg_split('/[,;|\n]+/', (string)$source);
    $parts = array_map('trim', $parts ?: []);
    $parts = array_values(array_filter($parts, fn($part) => $part !== ''));
    return array_slice($parts, 0, 3);
}

function categoryTheme(?string $category): string {
    $category = function_exists('mb_strtolower')
        ? mb_strtolower((string)$category, 'UTF-8')
        : strtolower((string)$category);
    $fireworkTerms = ['feux d’artifice', 'feux d\'artifice', 'artifice', 'havai fişek', 'havai fisek', 'firework'];
    foreach ($fireworkTerms as $term) {
        if (strpos($category, $term) !== false) {
            return 'firework';
        }
    }
    return 'default';
}

function categoryColor(?string $color): string {
    $color = trim((string)$color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#1E8E5A';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Étiquettes produits — <?= e(SHOP_NAME) ?></title>
  <link rel="stylesheet" href="assets/product-label.css?v=<?= filemtime(__DIR__ . '/assets/product-label.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
</head>
<body>

<div class="label-toolbar">
  <h1>Aperçu des étiquettes</h1>
  <span><?= count($products) ?> produit(s) · <?= count($pages) ?> page(s) A4 · 9 cartes / page</span>
  <button type="button" onclick="window.print()">Imprimer / PDF</button>
  <button type="button" onclick="window.close()">Fermer</button>
</div>

<main class="label-pages">
<?php foreach ($pages as $page): ?>
  <section class="label-page">
    <?php foreach ($page as $p):
      $name = trim((string)($p['name'] ?? '')) ?: 'Produit';
      $brand = trim((string)($p['brand'] ?? ''));
      $size = trim((string)($p['size'] ?? ''));
      $barcode = trim((string)($p['barcode'] ?? ''));
      $category = trim((string)($p['category_name'] ?? ''));
      $price = ($p['price'] ?? '') !== '' && $p['price'] !== null ? '€' . number_format((float)$p['price'], 2) : '';
      $image = imagePath($p['image_url'] ?? '');
      $notes = productNotes($p);
      $theme = categoryTheme($category);
      $categoryColor = categoryColor($p['category_color'] ?? '');
    ?>
    <article class="product-print-label label-theme-<?= e($theme) ?>" style="--category-color: <?= e($categoryColor) ?>">
      <div class="label-frame">
        <header class="label-photo-panel">
          <div class="label-photo-box <?= $barcode ? 'has-barcode' : 'no-barcode' ?>">
            <div class="label-image-box">
              <?php if ($image): ?>
                <img src="<?= e($image) ?>" alt="<?= e($name) ?>">
              <?php else: ?>
                <span class="label-photo-fallback"><?= e($name) ?></span>
              <?php endif; ?>
            </div>
            <div class="label-vertical-barcode-box <?= $barcode ? 'has-barcode' : 'no-barcode' ?>">
              <?php if ($barcode): ?>
                <div class="label-vertical-barcode-rotator">
                  <svg class="label-vertical-barcode" data-barcode="<?= e($barcode) ?>"></svg>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($category): ?>
            <div class="label-category"><?= e($category) ?></div>
          <?php endif; ?>
        </header>

        <section class="label-info-grid <?= $notes ? 'label-has-notes' : 'label-no-notes' ?>">
          <div class="label-detail-row">
            <div class="label-left">
              <div class="label-left-main">
                <h2><?= e($name) ?></h2>
                <?php if ($brand): ?><p class="label-brand"><?= e($brand) ?></p><?php endif; ?>
                <?php if ($size): ?><span class="label-size"><?= e($size) ?></span><?php endif; ?>
              </div>
            </div>

            <?php if ($notes): ?>
            <aside class="label-notes">
              <h3>NOTES</h3>
              <div class="label-note-list">
                <?php foreach ($notes as $note): ?>
                  <span><?= e($note) ?></span>
                <?php endforeach; ?>
              </div>
            </aside>
            <?php endif; ?>
          </div>

          <?php if ($price): ?><div class="label-price"><?= e($price) ?></div><?php endif; ?>

        </section>
      </div>
    </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</main>

<script>
document.querySelectorAll('svg[data-barcode]').forEach(function(svg) {
  var code = svg.getAttribute('data-barcode');
  if (!code || typeof JsBarcode === 'undefined') return;
  var digits = code.replace(/\D/g, '');
  var format = /^\d{13}$/.test(digits) ? 'EAN13' : 'CODE128';
  var opts = {
    width: 2.4,
    height: 52,
    displayValue: false,
    margin: 4,
    background: '#ffffff',
    lineColor: '#111827'
  };
  try {
    JsBarcode(svg, format === 'EAN13' ? digits : code, Object.assign({}, opts, { format: format }));
  } catch (e) {
    try { JsBarcode(svg, code, Object.assign({}, opts, { format: 'CODE128' })); }
    catch (e2) { svg.closest('.label-vertical-barcode-box').style.visibility = 'hidden'; }
  }
});
console.log('Product label size: 63.333mm x 92.333mm; A4 grid: 3 columns x 3 rows; page padding: 6mm; gutter: 4mm.');
</script>
</body>
</html>
