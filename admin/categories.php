<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

$db         = getDB();
$categories = $db->query('SELECT * FROM categories ORDER BY display_order')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catégories — <?= SHOP_NAME ?></title>
  <link rel="stylesheet" href="assets/admin.css">
  <style>
    .cat-table { width:100%; border-collapse:collapse; font-size:14px; }
    .cat-table th {
      background:#f8f9fa; padding:14px 16px;
      text-align:left; font-size:12px; font-weight:700;
      color:var(--muted); text-transform:uppercase; letter-spacing:.5px;
      border-bottom:1px solid var(--border);
    }
    .cat-table td { padding:14px 16px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .cat-table tr:last-child td { border-bottom:none; }
    .cat-table tr:hover td { background:#fafafa; }
    .icon-preview { font-size:28px; }
    .order-num { font-size:13px; color:var(--muted); font-weight:700; }
    .color-swatch {
      display:inline-block; width:24px; height:24px;
      border-radius:50%; border:2px solid rgba(0,0,0,.12);
      vertical-align:middle;
    }

    /* Modal form */
    .form-modal { padding:24px 28px; }
    .form-modal .form-row { display:flex; gap:16px; margin-bottom:14px; }
    .form-modal .form-group { flex:1; }
    .emoji-hint { font-size:12px; color:var(--muted); margin-top:4px; }

    .icon-picker {
      display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;
    }
    .icon-btn {
      font-size:20px; padding:5px 8px;
      border:2px solid var(--border); border-radius:8px;
      background:white; cursor:pointer; transition:border-color .15s, transform .1s;
    }
    .icon-btn:hover  { border-color:var(--accent); transform:scale(1.15); }
    .icon-btn.active { border-color:var(--accent); background:#fff0f3; }

    /* Color picker */
    .color-label { font-size:13px; font-weight:600; color:#333; margin-bottom:6px; display:block; }
    .color-picker-wrap { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
    .color-presets { display:flex; flex-wrap:wrap; gap:8px; }
    .color-preset {
      width:30px; height:30px; border-radius:50%;
      border:3px solid transparent; cursor:pointer;
      transition:transform .1s, border-color .1s;
    }
    .color-preset:hover { transform:scale(1.15); }
    .color-preset.active { border-color:#1a1a2e; transform:scale(1.1); }
    input[type="color"] {
      width:40px; height:40px; border:2px solid var(--border);
      border-radius:8px; cursor:pointer; padding:2px;
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <span>🌬️</span>
    <div>
      <div class="s-name"><?= SHOP_NAME ?></div>
      <div class="s-role">Admin</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"  class="nav-item">📦 Produits</a>
    <a href="categories.php" class="nav-item active">🏷️ Catégories</a>
    <a href="../index.php"   class="nav-item" target="_blank">🌐 Voir le site</a>
    <a href="logout.php"     class="nav-item logout">🚪 Déconnexion</a>
  </nav>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <h1>Gestion des catégories</h1>
    <button class="btn-primary" onclick="openAddModal()">+ Ajouter une catégorie</button>
  </div>

  <div class="table-wrap">
    <table class="cat-table">
      <thead>
        <tr>
          <th style="width:60px">Icône</th>
          <th style="width:50px">Couleur</th>
          <th>Nom</th>
          <th style="width:100px">Ordre</th>
          <th style="width:120px">Actions</th>
        </tr>
      </thead>
      <tbody id="catTableBody">
        <?php foreach ($categories as $c): ?>
        <tr id="crow-<?= $c['id'] ?>">
          <td><span class="icon-preview"><?= htmlspecialchars($c['icon']) ?></span></td>
          <td><span class="color-swatch" style="background:<?= htmlspecialchars($c['color'] ?? '#e94560') ?>"></span></td>
          <td class="td-name"><?= htmlspecialchars($c['name']) ?></td>
          <td class="order-num">#<?= $c['display_order'] ?></td>
          <td>
            <button class="btn-edit" onclick='editCat(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>✏️</button>
            <button class="btn-del"  onclick="deleteCat(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')">🗑️</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?>
        <tr><td colspan="5" class="empty-row">Aucune catégorie.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══════════ MODAL ══════════ -->
<div id="modal" class="modal-overlay" style="display:none" onclick="closeModalOutside(event)">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <h2 id="modalTitle">Ajouter une catégorie</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <div class="form-modal">
      <input type="hidden" id="editId">

      <div class="form-row">
        <div class="form-group">
          <label>Nom de la catégorie *</label>
          <input type="text" id="fName" placeholder="ex: Fruité Fresh">
        </div>
        <div class="form-group">
          <label>Ordre d'affichage</label>
          <input type="number" id="fOrder" placeholder="1" min="1">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:14px">
        <label>Icône (emoji)</label>
        <input type="text" id="fIcon" placeholder="🍓" maxlength="4">
        <div class="emoji-hint">Ou cliquez sur un emoji ci-dessous :</div>
        <div class="icon-picker">
          <?php
          $emojis = [
            // Tabac & vape
            '🚬','💨','🌫️','🔥','⚡',
            // Fruits
            '🍓','🍋','🍇','🥭','🫐','🍉','🍑','🍊','🍎','🍒','🍈','🥝','🍍','🌴','🍌',
            // Fresh / menthe
            '🍃','🌿','🧊','❄️','💧',
            // Gourmand
            '🍦','🍰','🍫','🍮','🍬','🍭','🧁','🍩','🍪','🌰','🥐',
            // Boissons
            '☕','🍵','🧃','🥤','🍹','🌊',
            // Autres
            '💎','⭐','🎯','🏆','🌸',
          ];
          foreach ($emojis as $e): ?>
          <button type="button" class="icon-btn" onclick="pickIcon('<?= $e ?>')"><?= $e ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Couleur -->
      <div class="form-group" style="margin-bottom:16px">
        <label class="color-label">Couleur de la catégorie</label>
        <div class="color-picker-wrap">
          <input type="color" id="fColor" value="#e94560" oninput="syncColorFromPicker()">
          <div class="color-presets" id="colorPresets">
            <?php
            $presets = [
              '#8B6914','#A0522D','#5D4037', // tabac / brun
              '#E74C3C','#e94560','#C0392B', // rouge
              '#E67E22','#F39C12','#FF8C00', // orange
              '#F1C40F','#DAA520',           // jaune
              '#2ECC71','#27AE60','#00BCD4', // vert / fresh
              '#3498DB','#2980B9','#9B59B6', // bleu / violet
              '#1a1a2e','#2C3E50','#7F8C8D', // sombre
            ];
            foreach ($presets as $color): ?>
            <div class="color-preset" style="background:<?= $color ?>"
                 onclick="pickColor('<?= $color ?>')" title="<?= $color ?>"></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn-cancel" onclick="closeModal()">Annuler</button>
        <button class="btn-save" onclick="saveCat()" id="saveBtn">💾 Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script>
let isEditing = false;

function openAddModal() {
  isEditing = false;
  document.getElementById('modalTitle').textContent = 'Ajouter une catégorie';
  document.getElementById('editId').value  = '';
  document.getElementById('fName').value   = '';
  document.getElementById('fIcon').value   = '📦';
  document.getElementById('fOrder').value  = '';
  pickColor('#e94560');
  clearIconActive();
  document.getElementById('modal').style.display = 'flex';
  setTimeout(() => document.getElementById('fName').focus(), 100);
}

function closeModal() { document.getElementById('modal').style.display = 'none'; }
function closeModalOutside(e) { if (e.target.id === 'modal') closeModal(); }

function pickIcon(emoji) {
  document.getElementById('fIcon').value = emoji;
  clearIconActive();
  event.target.classList.add('active');
}
function clearIconActive() {
  document.querySelectorAll('.icon-btn').forEach(b => b.classList.remove('active'));
}

function pickColor(hex) {
  document.getElementById('fColor').value = hex;
  document.querySelectorAll('.color-preset').forEach(el => {
    el.classList.toggle('active', el.style.background === hex || el.getAttribute('onclick').includes(hex));
  });
}
function syncColorFromPicker() {
  document.querySelectorAll('.color-preset').forEach(el => el.classList.remove('active'));
}

function editCat(c) {
  isEditing = true;
  document.getElementById('modalTitle').textContent = 'Modifier la catégorie';
  document.getElementById('editId').value  = c.id;
  document.getElementById('fName').value   = c.name;
  document.getElementById('fIcon').value   = c.icon;
  document.getElementById('fOrder').value  = c.display_order;
  pickColor(c.color || '#e94560');
  clearIconActive();
  document.getElementById('modal').style.display = 'flex';
}

async function saveCat() {
  const name = document.getElementById('fName').value.trim();
  if (!name) { alert('Le nom est obligatoire.'); return; }

  const btn = document.getElementById('saveBtn');
  btn.disabled = true; btn.textContent = '⏳...';

  const id     = document.getElementById('editId').value;
  const method = id ? 'PUT' : 'POST';
  const action = id ? 'edit' : 'add';

  const payload = {
    id:            id || undefined,
    name:          name,
    icon:          document.getElementById('fIcon').value || '📦',
    color:         document.getElementById('fColor').value || '#e94560',
    display_order: parseInt(document.getElementById('fOrder').value) || 99,
  };

  try {
    await fetch(`../api/categories.php?action=${action}`, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    closeModal();
    window.location.reload();
  } catch(e) {
    alert('Erreur lors de l\'enregistrement.');
  } finally {
    btn.disabled = false; btn.textContent = '💾 Enregistrer';
  }
}

async function deleteCat(id, name) {
  if (!confirm(`Supprimer la catégorie "${name}" ?\n\nLes produits de cette catégorie ne seront PAS supprimés, mais leur catégorie sera retirée.`)) return;
  await fetch(`../api/categories.php?action=delete&id=${id}`, { method: 'DELETE' });
  document.getElementById('crow-' + id)?.remove();
}
</script>
</body>
</html>
