<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$message = '';

if (!auth_editor_requires_pin()) {
    auth_start();
    $_SESSION['infoscreen2_editor_ok'] = true;
    auth_redirect('admin.php?page=master');
}

if (auth_is_editor()) {
    auth_redirect('admin.php?page=master');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = (string)($_POST['pin'] ?? '');

    if (auth_login_editor_with_pin($pin)) {
        auth_redirect('admin.php?page=master');
    }

    $message = 'Pflege-PIN ist nicht korrekt.';
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Inhaltsmodus anmelden</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f3f5f7;color:#1f2933;padding:24px}
.card{max-width:520px;margin:8vh auto;background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
h1{margin:0 0 14px 0}
label{display:block;font-weight:700;margin-bottom:6px}
input{width:100%;padding:11px 12px;border:1px solid #cbd5df;border-radius:9px;font:inherit}
button,.btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border:0;border-radius:9px;background:#2563eb;color:#fff;text-decoration:none;cursor:pointer;font:inherit}
.btn{background:#e9edf2;color:#111}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
.notice{background:#fee2e2;border:1px solid #fecaca;border-radius:12px;padding:10px 12px;margin:0 0 14px 0}
.small{color:#667085;font-size:.92rem}
</style>
</head>
<body>
<div class="card">
    <h1>Inhaltsmodus anmelden</h1>
    <p class="small">Bitte Pflege-PIN eingeben, um Inhalte zu bearbeiten.</p>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="pin">Pflege-PIN</label>
        <input id="pin" type="password" name="pin" required autofocus inputmode="numeric" autocomplete="current-password">
        <div class="actions">
            <button type="submit">Inhaltsmodus öffnen</button>
        </div>
    </form>
</div>
</body>
</html>
