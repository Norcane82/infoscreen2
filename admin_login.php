<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$message = '';

if (auth_is_admin()) {
    auth_redirect('admin.php?page=master');
}

if (!auth_admin_is_configured()) {
    auth_redirect('rechte_admin.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');

    if (auth_login_admin($password)) {
        auth_redirect('admin.php?page=master');
    }

    $message = 'Admin-Passwort ist nicht korrekt.';
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin anmelden</title>
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
    <h1>Admin anmelden</h1>
    <p class="small">Nach der Anmeldung wird der Vollzugriff für diese Sitzung aktiviert.</p>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="password">Admin-Passwort</label>
        <input id="password" type="password" name="password" required autofocus autocomplete="current-password">
        <div class="actions">
            <button type="submit">Adminmodus aktivieren</button>
            <a class="btn" href="admin.php?page=master">Zurück</a>
        </div>
    </form>
</div>
</body>
</html>
