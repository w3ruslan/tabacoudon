<?php
require_once __DIR__ . '/../config.php';
session_start();

// Déjà connecté
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['password'] ?? '') === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php'); exit;
    } else {
        $error = 'Mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — <?= SHOP_NAME ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Segoe UI', system-ui, sans-serif;
    }
    .box {
      background: white;
      border-radius: 20px;
      padding: 48px 40px;
      width: 380px;
      box-shadow: 0 20px 60px rgba(0,0,0,.4);
      text-align: center;
    }
    .icon { font-size: 48px; margin-bottom: 12px; }
    h1 { font-size: 22px; color: #1a1a2e; margin-bottom: 4px; }
    .sub { font-size: 14px; color: #888; margin-bottom: 32px; }
    label { display: block; text-align: left; font-size: 12px; font-weight: 700;
            color: #555; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
    input[type=password] {
      width: 100%; padding: 13px 16px; border: 2px solid #e0e0e0;
      border-radius: 10px; font-size: 16px; outline: none; margin-bottom: 16px;
      transition: border-color .2s;
    }
    input[type=password]:focus { border-color: #e94560; }
    button {
      width: 100%; padding: 14px; background: #1a1a2e; color: white;
      border: none; border-radius: 10px; font-size: 16px; font-weight: 700;
      cursor: pointer; transition: background .2s;
    }
    button:hover { background: #e94560; }
    .error {
      background: #fff0f0; color: #e53935; border-radius: 8px;
      padding: 10px; font-size: 14px; margin-bottom: 14px;
    }
  </style>
</head>
<body>
<div class="box">
  <div class="icon">🔐</div>
  <h1>Espace Admin</h1>
  <p class="sub"><?= SHOP_NAME ?></p>
  <?php if ($error): ?>
    <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <label>Mot de passe</label>
    <input type="password" name="password" placeholder="••••••••" autofocus>
    <button type="submit">Connexion →</button>
  </form>
</div>
</body>
</html>
