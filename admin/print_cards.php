<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

// Accept IDs via POST
$raw = $_POST['ids'] ?? '';
$ids = array_filter(array_map('intval', json_decode($raw, true) ?: []));

if (empty($ids)) {
    echo '<p style="font-family:sans-serif;padding:40px;color:#e94560">❌ Aucun produit sélectionné.</p>';
    exit;
}

$db = getDB();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare(
    "SELECT p.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id IN ($placeholders)"
);
$stmt->execute(array_values($ids));
$fetched = $stmt->fetchAll();

// Keep original selection order
$products = [];
foreach ($ids as $id) {
    foreach ($fetched as $p) {
        if ((int)$p['id'] === (int)$id) { $products[] = $p; break; }
    }
}

// Split into pages of 9
$pages = array_chunk($products, 9);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Étiquettes — <?= SHOP_NAME ?></title>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <style>
    /* ─────────────────────────────────────────────────────
       Font sizes mapped from customer CSS (×0.75 px→pt):
       14px name → 10.5pt  |  9px brand/size/chip → 6.75pt
       15px price → 11.25pt |  8px spec-title → 6pt
    ───────────────────────────────────────────────────── */
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body { background: #d4d4d4; font-family: 'Segoe UI', Arial, sans-serif; }

    /* ── Toolbar (screen only) ── */
    .toolbar {
      position: fixed; top: 0; left: 0; right: 0; z-index: 999;
      background: #1a1a2e; color: #fff;
      padding: 11px 24px; display: flex; align-items: center; gap: 14px;
    }
    .toolbar h2 { flex: 1; font-size: 15px; font-weight: 700; }
    .toolbar .info { font-size: 12px; color: rgba(255,255,255,.5); }
    .btn-print {
      background: #e94560; color: #fff; border: none; border-radius: 8px;
      padding: 9px 22px; font-size: 14px; font-weight: 700; cursor: pointer;
    }
    .btn-print:hover { opacity: .85; }
    .btn-close {
      background: #444; color: #fff; border: none; border-radius: 8px;
      padding: 9px 14px; font-size: 14px; cursor: pointer;
    }

    /*
      Layout maths (all exact):
        A4 = 210 × 297 mm
        @page margin = 13.5mm top/bottom,  12mm left/right
        Print area   = 186 × 270 mm
        Cards        = 60 × 88 mm  (3 per row/col)
        Gap          = 3mm  between every card
        3×60 + 2×3   = 186mm  ✓ width
        3×88 + 2×3   = 270mm  ✓ height
    */

    /* ── A4 page sheet (screen preview) ── */
    @media screen {
      .pages-wrap { padding: 76px 24px 48px; }
      .page-sheet {
        width: 210mm; min-height: 297mm;
        background: #fff;
        margin: 0 auto 28px;
        box-shadow: 0 6px 28px rgba(0,0,0,.22);
        padding: 13.5mm 12mm;          /* mirrors @page margin */
        display: grid;
        grid-template-columns: repeat(3, 60mm);
        grid-template-rows: repeat(3, 88mm);
        gap: 3mm;
        align-content: start;
      }
    }

    @media print {
      * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

      /* ── White page ── */
      html, body {
        background: white !important; background-color: white !important;
        margin: 0; padding: 0;
        /* Center the page-sheet horizontally regardless of actual
           printer hardware margins (which vary per printer model) */
        display: flex; flex-direction: column; align-items: center;
      }

      .toolbar { display: none !important; }

      .pages-wrap {
        padding: 0; margin: 0;
        background: white !important;
        display: flex; flex-direction: column; align-items: center;
        width: 100%;
      }

      /* ── Page sheet: auto-center, no fixed width ──
         Width is determined by the grid content (3×60 + 2×3 = 186mm).
         margin:auto centers it whatever the real print area width is.   */
      .page-sheet {
        display: grid;
        grid-template-columns: repeat(3, 60mm);
        grid-template-rows: repeat(3, 88mm);
        gap: 3mm;
        width: max-content;          /* = 186mm, always */
        margin: 0 auto;              /* center on page */
        padding: 0;
        background: white !important;
        page-break-after: always; break-after: page;
        height: auto;                /* don't force page height */
      }
      .page-sheet:last-child { page-break-after: avoid; break-after: avoid; }

      /* ── Cards: no shadow (bleeds into gaps), hairline border instead ── */
      .tc-card {
        box-shadow: none !important;
        border: 0.3mm solid rgba(0,0,0,.18) !important;
      }

      /* ── Photo box: no shadow halo under product image ── */
      .tc-img-box {
        box-shadow: none !important;
      }

      /* ── Barcode: white background so bars are readable ── */
      .tc-barcode-svg {
        background: white !important;
        background-color: white !important;
      }
    }
    /* Uniform 10mm margin — safe across all printers */
    @page { size: A4 portrait; margin: 10mm; }

    /* ══════════════════════════════════════════
       CARD  —  66.6 × 93.3 mm
       Replicates customer .tc-card exactly.
    ══════════════════════════════════════════ */
    .tc-card {
      width: 60mm;
      height: 88mm;
      background: #fff;
      border-radius: 4.2mm;          /* proportional to 18px */
      overflow: hidden;
      position: relative;
      display: flex;
      flex-direction: column;
      box-shadow: 0 1mm 4mm rgba(0,0,0,.13), 0 0 0 0.5px rgba(0,0,0,.08);
    }

    /* ── Top gradient ── */
    .tc-card-top {
      height: 50mm;                  /* 57% of 88mm */
      background: linear-gradient(145deg, var(--cc) 0%, #1a1a2e 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3.5mm;
      flex-shrink: 0;
      position: relative;
      border-bottom-left-radius: 3.7mm;
      border-bottom-right-radius: 3.7mm;
    }

    /* White image box */
    .tc-img-box {
      width: 70%;
      aspect-ratio: 1;
      background: #fff;
      border-radius: 4.2mm;          /* = 16px */
      padding: 2.1mm;                /* = 8px */
      box-shadow: 0 2.1mm 7.4mm rgba(0,0,0,.28);
      overflow: hidden;
      position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: center;
    }
    .tc-img-box img { width: 100%; height: 100%; object-fit: contain; }
    .tc-no-img { font-size: 20pt; opacity: .3; }

    /* Category icon top-left */
    .tc-cat-icon-sm {
      position: absolute; top: 2mm; left: 2mm;
      font-size: 8pt; opacity: .8;
    }

    /* Price pill bottom-right of card */
    .tc-price-tag {
      position: absolute; bottom: 2.5mm; right: 2.5mm;
      background: var(--cc);
      color: #fff;
      font-size: 10pt;
      font-weight: 900;
      padding: 1mm 2.4mm;
      border-radius: 10mm;
      letter-spacing: -.2px;
      white-space: nowrap;
      z-index: 5;
    }

    /* ── Bottom section ── */
    .tc-card-bot {
      flex: 1;
      background: #fff;
      padding: 2.6mm 3.2mm 3.2mm;   /* = 10px 12px 12px */
      display: flex;
      gap: 2.1mm;                    /* = 8px */
      overflow: hidden;
    }

    /* Left column — centered */
    .tc-bot-left {
      flex: 1;
      display: flex; flex-direction: column;
      align-items: center;           /* center children horizontally */
      text-align: center;
      gap: 1.05mm;
      min-width: 0;
    }

    .tc-card-name {
      font-size: 10.5pt;
      font-weight: 900;
      color: #1a1a2e;
      line-height: 1.2;
      letter-spacing: -.1px;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      text-align: center;
    }

    .tc-card-brand {
      font-size: 6.75pt;
      color: #bbb; font-weight: 600;
      letter-spacing: .2px;
      text-align: center;
    }

    .tc-bot-tags {
      display: flex; align-items: center;
      justify-content: center;       /* center pills */
      gap: 1mm; flex-wrap: wrap;
      margin-top: 0.5mm;
    }

    .tc-size-label {
      font-size: 6.75pt;
      font-weight: 700; color: #666;
      background: #f0f0f0;
      border-radius: 1.3mm;
      padding: 0.5mm 1.85mm;
    }

    .tc-sc-pill {
      font-size: 6pt; font-weight: 800;
      color: #d97706; background: #fff8e1;
      border: 0.2mm solid #fde68a;
      border-radius: 5mm; padding: 0.5mm 1.6mm;
    }

    /* Barcode — centered */
    .tc-barcode-area {
      margin-top: 1.5mm;
      display: flex; justify-content: center;
      width: 100%;
    }
    .tc-barcode-svg { width: 100%; max-width: 28mm; height: auto; display: block; }

    /* Right column — centered */
    .tc-bot-right {
      flex: 1;
      border-left: 0.5px solid #f0f0f0;
      padding-left: 2.4mm;
      padding-bottom: 7mm;           /* clear the absolute price pill */
      min-width: 0; overflow: hidden;
      display: flex; flex-direction: column;
      align-items: center;           /* center children */
      text-align: center;
    }

    .tc-spec-title {
      font-size: 6pt;
      font-weight: 900;
      color: var(--cc);
      text-transform: uppercase; letter-spacing: .9px;
      margin-bottom: 1.3mm;
      text-align: center;
    }

    .tc-spec-chips {
      display: flex; flex-direction: column;
      align-items: center;           /* center each chip */
      gap: 0.8mm;
      width: 100%;
    }

    .tc-spec-chip {
      display: block;
      border-radius: 5.3mm;
      padding: 0.8mm 2.4mm;
      font-size: 6.75pt;
      font-weight: 700;
      line-height: 1.35;
      word-break: break-word;
      text-align: center;
    }
  </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
  <h2>🖨️ Aperçu — <?= count($products) ?> produit(s) · <?= count($pages) ?> page(s) A4</h2>
  <span class="info">9 cartes / page · 66.6 × 93.3 mm</span>
  <button class="btn-print" onclick="window.print()">🖨️ Imprimer / PDF</button>
  <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<div class="pages-wrap">
<?php foreach ($pages as $pi => $page): ?>
  <div class="page-sheet">
    <?php foreach ($page as $p):
      $cc     = $p['category_color'] ?: '#e94560';
      $icon   = $p['category_icon']  ?: '';
      $label  = $p['category_name']  ?: '';
      $price  = $p['price'] ? '€' . number_format((float)$p['price'], 2) : '';

      $img = $p['image_url'] ?? '';
      if ($img && strpos($img, 'uploads/') === 0) $img = '../' . $img;

      $flavors = [];
      if (!empty($p['flavor'])) {
        $flavors = array_slice(
          array_filter(array_map('trim', preg_split('/[,\/]+/', $p['flavor']))),
          0, 4
        );
        $flavors = array_values($flavors);
      }
    ?>
    <div class="tc-card" style="--cc:<?= htmlspecialchars($cc) ?>">

      <!-- ── Top ── -->
      <div class="tc-card-top">
        <?php if ($icon): ?><span class="tc-cat-icon-sm"><?= $icon ?></span><?php endif; ?>
        <div class="tc-img-box">
          <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
          <?php else: ?>
            <span class="tc-no-img">🌬️</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Bottom ── -->
      <div class="tc-card-bot">

        <div class="tc-bot-left">
          <div class="tc-card-name"><?= htmlspecialchars($p['name']) ?></div>
          <?php if (!empty($p['brand'])): ?>
            <div class="tc-card-brand"><?= htmlspecialchars($p['brand']) ?></div>
          <?php endif; ?>
          <div class="tc-bot-tags">
            <?php if (!empty($p['size'])): ?>
              <span class="tc-size-label"><?= htmlspecialchars($p['size']) ?></span>
            <?php endif; ?>
            <?php if (!empty($p['sur_commande'])): ?>
              <span class="tc-sc-pill">📦 Sur cmd</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($p['barcode'])): ?>
          <div class="tc-barcode-area">
            <svg class="tc-barcode-svg"
                 data-barcode="<?= htmlspecialchars($p['barcode']) ?>"></svg>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($flavors || $label): ?>
        <div class="tc-bot-right">
          <?php if ($flavors): ?>
            <div class="tc-spec-title">NOTES</div>
            <div class="tc-spec-chips">
              <?php foreach ($flavors as $f): ?>
                <span class="tc-spec-chip"
                  style="background:<?= $cc ?>18;color:<?= $cc ?>;border:0.2mm solid <?= $cc ?>55">
                  <?= htmlspecialchars($f) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php elseif ($label): ?>
            <div class="tc-spec-title">Catégorie</div>
            <div class="tc-spec-chips">
              <span class="tc-spec-chip"
                style="background:<?= $cc ?>18;color:<?= $cc ?>;border:0.2mm solid <?= $cc ?>55">
                <?= htmlspecialchars($label) ?>
              </span>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div>
      <?php if ($price): ?><span class="tc-price-tag"><?= $price ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>

<script>
// Render barcodes
document.querySelectorAll('svg[data-barcode]').forEach(function(svg) {
  var code = svg.getAttribute('data-barcode');
  if (!code) return;
  var opts = {
    width: 1.5, height: 30,
    displayValue: true, fontSize: 8,
    margin: 10, background: '#ffffff', lineColor: '#000000'
  };
  try {
    JsBarcode(svg, code, Object.assign({}, opts, { format: 'auto' }));
  } catch(e) {
    try { JsBarcode(svg, code, Object.assign({}, opts, { format: 'CODE128' })); }
    catch(e2) { svg.style.display = 'none'; }
  }
});
</script>
</body>
</html>
