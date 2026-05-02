import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import process from 'node:process';
import puppeteer from 'puppeteer';

const TV_WIDTH = 3840;
const TV_HEIGHT = 2160;
// Keep this at 1 for exact 3840x2160 PNG output. Puppeteer multiplies the
// final bitmap by deviceScaleFactor, so dSF=2 produces 7680x4320.
const DEVICE_SCALE_FACTOR = Number(process.env.TV_DEVICE_SCALE_FACTOR || 1);

function argValue(name) {
  const prefix = `${name}=`;
  const found = process.argv.find((arg) => arg.startsWith(prefix));
  return found ? found.slice(prefix.length) : '';
}

function e(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[ch]);
}

function cssFile(root, relPath) {
  return pathToFileURL(path.join(root, relPath)).href;
}

function imageSrc(root, image) {
  const src = String(image || '').trim();
  if (!src) return '';
  if (/^https?:\/\//i.test(src)) return src;
  if (src.startsWith('uploads/')) {
    return pathToFileURL(path.join(root, src)).href;
  }
  return src;
}

function pngSize(bytes) {
  if (bytes.length < 24 || bytes.toString('ascii', 1, 4) !== 'PNG') {
    throw new Error('Screenshot is not a PNG');
  }
  return {
    width: bytes.readUInt32BE(16),
    height: bytes.readUInt32BE(20),
  };
}

function cardHtml(product, root) {
  const notes = (product.notes || []).slice(0, 2);
  const image = imageSrc(root, product.image_url);
  const price = product.price_text || '';
  const classes = notes.length ? 'tc-has-specs' : 'tc-no-specs';
  return `
    <div class="tv-cell">
      <article class="tc-card ${classes}" data-product-id="${e(product.id || '')}" style="--cc:${e(product.category_color || '#22c55e')};--category-color:${e(product.category_color || '#22c55e')}">
        <div class="tc-card-top">
          <div class="tc-img-box">
            <div class="tc-product-visual">
              ${image ? `<img src="${e(image)}" alt="${e(product.name || 'Produit')}">` : '<span class="tc-no-img">🌬️</span>'}
            </div>
            ${price ? `<div class="tc-photo-price">${e(price)}</div>` : ''}
            <button class="tc-photo-cart tc-cart-btn" type="button"><span>🛒</span><strong>AJOUTER<br>AU PANIER</strong></button>
          </div>
        </div>
        <section class="tc-card-bot">
          <div class="tc-bot-left">
            <div class="tc-card-name">${e(product.name || 'Produit')}</div>
            ${product.brand ? `<div class="tc-card-brand">${e(product.brand)}</div>` : ''}
            <div class="tc-bot-tags">
              ${product.size ? `<span class="tc-size-label">${e(product.size)}</span>` : ''}
              ${product.sur_commande ? '<span class="tc-sc-pill">📦 Sur cmd</span>' : ''}
            </div>
          </div>
          ${notes.length ? `
            <aside class="tc-bot-right">
              <div class="tc-spec-title">NOTES</div>
              <div class="tc-spec-chips">
                ${notes.map((note) => `<span class="tc-spec-chip">${e(note)}</span>`).join('')}
              </div>
            </aside>
          ` : ''}
        </section>
        <div class="tc-horizontal-barcode-wrap no-barcode"></div>
      </article>
    </div>
  `;
}

function screenHtml(products, root) {
  return `<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=${TV_WIDTH}, initial-scale=1">
  <link rel="stylesheet" href="${cssFile(root, 'assets/product-card.css')}">
  <link rel="stylesheet" href="${cssFile(root, 'admin/assets/tv-export.css')}">
  <style>
    html, body { width:${TV_WIDTH}px; height:${TV_HEIGHT}px; overflow:hidden; margin:0; }
    .tv-toolbar, .tv-preview-list, .tv-preview-shell { all: unset; }
    .tv-screen { transform:none !important; }
  </style>
</head>
<body>
  <main class="tv-screen">
    ${products.map((product) => cardHtml(product, root)).join('')}
  </main>
</body>
</html>`;
}

async function main() {
  const dataPath = argValue('--data');
  const outDir = argValue('--out');
  const root = argValue('--root') || process.cwd();
  if (!dataPath || !outDir) {
    throw new Error('Usage: node scripts/tv-export-native.mjs --data=/tmp/data.json --out=/tmp/out --root=/repo');
  }

  const payload = JSON.parse(await fs.readFile(dataPath, 'utf8'));
  const screens = payload.screens || [];
  await fs.mkdir(outDir, { recursive: true });

  const browser = await puppeteer.launch({
    headless: 'new',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      `--force-device-scale-factor=${DEVICE_SCALE_FACTOR}`,
    ],
    defaultViewport: {
      width: TV_WIDTH,
      height: TV_HEIGHT,
      deviceScaleFactor: DEVICE_SCALE_FACTOR,
    },
  });

  try {
    for (let index = 0; index < screens.length; index += 1) {
      const page = await browser.newPage();
      const screenIds = screens[index].map((product) => product.id).filter(Boolean).join(',');
      console.log(`tv-screen-${String(index + 1).padStart(2, '0')} product ids: ${screenIds}`);
      await page.setViewport({
        width: TV_WIDTH,
        height: TV_HEIGHT,
        deviceScaleFactor: DEVICE_SCALE_FACTOR,
      });
      page.on('console', (msg) => console.log(`[browser] ${msg.text()}`));
      const htmlPath = path.join(outDir, `tv-screen-${String(index + 1).padStart(2, '0')}.html`);
      await fs.writeFile(htmlPath, screenHtml(screens[index], root), 'utf8');
      await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'networkidle0' });
      await page.evaluate(async () => {
        if (document.fonts && document.fonts.ready) await document.fonts.ready;
        await Promise.all(Array.from(document.images).map((img) => {
          if (img.complete && img.naturalWidth > 0) return Promise.resolve();
          return new Promise((resolve) => {
            img.addEventListener('load', resolve, { once: true });
            img.addEventListener('error', resolve, { once: true });
          });
        }));
        const screen = document.querySelector('.tv-screen').getBoundingClientRect();
        const ids = Array.from(document.querySelectorAll('.tc-card[data-product-id]')).map((card) => card.dataset.productId);
        console.log(`rendered product ids: ${ids.join(',')}`);
        console.log(`rendered screen rect: ${Math.round(screen.width)}x${Math.round(screen.height)}`);
        document.querySelectorAll('.tc-card').forEach((card, i) => {
          const rect = card.getBoundingClientRect();
          console.log(`card-${i + 1} rect: ${Math.round(rect.width)}x${Math.round(rect.height)}`);
        });
      });

      const outputPath = path.join(outDir, `tv-screen-${String(index + 1).padStart(2, '0')}.png`);
      const bytes = await page.screenshot({
        path: outputPath,
        type: 'png',
        fullPage: false,
        captureBeyondViewport: false,
      });
      const size = pngSize(Buffer.from(bytes));
      console.log(`actual image width: ${size.width}`);
      console.log(`actual image height: ${size.height}`);
      if (size.width !== TV_WIDTH || size.height !== TV_HEIGHT) {
        throw new Error(`Invalid PNG size ${size.width}x${size.height}; expected ${TV_WIDTH}x${TV_HEIGHT}`);
      }
      await page.close();
    }
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error.stack || error.message || error);
  process.exit(1);
});
