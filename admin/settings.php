<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

// Load current settings from DB
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL DEFAULT ''
)");
$rows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
$settings = [];
foreach ($rows as $r) $settings[$r['key']] = $r['value'];

// Fallbacks from config.php
$wa     = $settings['whatsapp_number'] ?? WHATSAPP_NUMBER;
$sname  = $settings['shop_name']       ?? SHOP_NAME;
$stag   = $settings['shop_tagline']    ?? SHOP_TAGLINE;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paramètres — <?= SHOP_NAME ?></title>
  <link rel="stylesheet" href="assets/admin.css">
  <style>
    .settings-card {
      background:#fff;
      border-radius:16px;
      border:1px solid var(--border);
      padding:28px 32px;
      max-width:560px;
      margin-bottom:24px;
    }
    .settings-card h3 {
      font-size:16px;
      font-weight:700;
      color:var(--text);
      margin:0 0 20px;
      padding-bottom:12px;
      border-bottom:1px solid var(--border);
    }
    .settings-group { margin-bottom:16px; }
    .settings-group label {
      display:block;
      font-size:13px;
      font-weight:600;
      color:var(--muted);
      margin-bottom:6px;
    }
    .settings-group input {
      width:100%;
      padding:11px 14px;
      border:2px solid var(--border);
      border-radius:9px;
      font-size:15px;
      outline:none;
      transition:border-color .2s;
      box-sizing:border-box;
    }
    .settings-group input:focus { border-color:var(--accent); }
    .settings-hint {
      font-size:11px;
      color:#aaa;
      margin-top:4px;
    }
    .btn-save-settings {
      padding:12px 28px;
      background:var(--accent);
      color:#fff;
      border:none;
      border-radius:10px;
      font-size:15px;
      font-weight:700;
      cursor:pointer;
      transition:opacity .2s;
    }
    .btn-save-settings:hover { opacity:.88; }
    .save-feedback {
      display:inline-block;
      margin-left:14px;
      font-size:13px;
      font-weight:600;
      color:#16a34a;
      opacity:0;
      transition:opacity .3s;
    }
    .save-feedback.show { opacity:1; }
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
    <a href="categories.php" class="nav-item">🏷️ Catégories</a>
    <a href="settings.php"   class="nav-item active">⚙️ Paramètres</a>
    <a href="../index.php"   class="nav-item" target="_blank">🌐 Voir le site</a>
    <a href="logout.php"     class="nav-item logout">🚪 Déconnexion</a>
  </nav>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <h1>⚙️ Paramètres</h1>
  </div>

  <!-- WhatsApp -->
  <div class="settings-card">
    <h3>📱 WhatsApp</h3>
    <div class="settings-group">
      <label>Numéro WhatsApp</label>
      <input type="text" id="waNumber" value="<?= htmlspecialchars($wa) ?>" placeholder="ex: 905551234567">
      <div class="settings-hint">Format : indicatif pays + numéro, sans + ni espaces. Turquie : 90… &nbsp;|&nbsp; France : 33…</div>
    </div>
    <button class="btn-save-settings" onclick="saveSettings()">💾 Enregistrer</button>
    <span class="save-feedback" id="saveFeedback">✅ Enregistré !</span>
  </div>

  <!-- Shop info -->
  <div class="settings-card">
    <h3>🏪 Informations du magasin</h3>
    <div class="settings-group">
      <label>Nom du magasin</label>
      <input type="text" id="shopName" value="<?= htmlspecialchars($sname) ?>" placeholder="Tabacoudon">
    </div>
    <div class="settings-group">
      <label>Slogan</label>
      <input type="text" id="shopTagline" value="<?= htmlspecialchars($stag) ?>" placeholder="Votre spécialiste e-liquid OUDON">
    </div>
    <button class="btn-save-settings" onclick="saveSettings()">💾 Enregistrer</button>
    <span class="save-feedback" id="saveFeedback2">✅ Enregistré !</span>
  </div>
</div>

<script>
async function saveSettings() {
  const body = {
    whatsapp_number: document.getElementById('waNumber').value.trim(),
    shop_name:       document.getElementById('shopName').value.trim(),
    shop_tagline:    document.getElementById('shopTagline').value.trim(),
  };
  try {
    const res = await fetch('../api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const json = await res.json();
    if (json.ok) {
      ['saveFeedback','saveFeedback2'].forEach(id => {
        const el = document.getElementById(id);
        el.classList.add('show');
        setTimeout(() => el.classList.remove('show'), 2500);
      });
    } else {
      alert('Erreur lors de l\'enregistrement.');
    }
  } catch(e) {
    alert('Erreur réseau.');
  }
}
</script>
</body>
</html>
