<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$message = '';
$error = '';

$config = auth_read_config();
$isInitialSetup = !auth_admin_is_configured();

if (!$isInitialSetup) {
    auth_require_admin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = auth_read_config();

    if ($isInitialSetup) {
        $newAdminPassword = (string)($_POST['newAdminPassword'] ?? '');
        $newAdminPassword2 = (string)($_POST['newAdminPassword2'] ?? '');

        if (strlen($newAdminPassword) < 8) {
            $error = 'Das Admin-Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($newAdminPassword !== $newAdminPassword2) {
            $error = 'Die Admin-Passwörter stimmen nicht überein.';
        } else {
            $config['adminAccess']['passwordHash'] = password_hash($newAdminPassword, PASSWORD_DEFAULT);
            $config['editorAccess']['requiresPin'] = false;
            $config['editorAccess']['pinHash'] = '';
            $config['mode'] = 'simple';
            $config['futureUserSystem']['prepared'] = true;
            $config['futureUserSystem']['enabled'] = false;

            if (auth_write_config($config)) {
                auth_login_admin($newAdminPassword);
                auth_redirect('rechte_admin.php');
            }

            $error = 'Die Zugriffseinstellungen konnten nicht gespeichert werden.';
        }
    } else {
        $requiresPin = isset($_POST['editorRequiresPin']);
        $newEditorPin = trim((string)($_POST['newEditorPin'] ?? ''));
        $newEditorPin2 = trim((string)($_POST['newEditorPin2'] ?? ''));
        $newAdminPassword = (string)($_POST['newAdminPassword'] ?? '');
        $newAdminPassword2 = (string)($_POST['newAdminPassword2'] ?? '');

        $config['mode'] = 'simple';
        $config['editorAccess']['enabled'] = true;
        $config['editorAccess']['requiresPin'] = $requiresPin;
        $config['futureUserSystem']['prepared'] = true;
        $config['futureUserSystem']['enabled'] = false;

        if ($requiresPin) {
            $hasExistingPin = trim((string)($config['editorAccess']['pinHash'] ?? '')) !== '';

            if ($newEditorPin !== '') {
                if (strlen($newEditorPin) < 4) {
                    $error = 'Der Pflege-PIN muss mindestens 4 Zeichen haben.';
                } elseif ($newEditorPin !== $newEditorPin2) {
                    $error = 'Die Pflege-PIN-Eingaben stimmen nicht überein.';
                } else {
                    $config['editorAccess']['pinHash'] = password_hash($newEditorPin, PASSWORD_DEFAULT);
                }
            } elseif (!$hasExistingPin) {
                $error = 'Bitte einen Pflege-PIN setzen, wenn die PIN-Pflicht aktiviert wird.';
            }
        } else {
            $config['editorAccess']['pinHash'] = '';
        }

        if ($error === '' && $newAdminPassword !== '') {
            if (strlen($newAdminPassword) < 8) {
                $error = 'Das neue Admin-Passwort muss mindestens 8 Zeichen haben.';
            } elseif ($newAdminPassword !== $newAdminPassword2) {
                $error = 'Die neuen Admin-Passwörter stimmen nicht überein.';
            } else {
                $config['adminAccess']['passwordHash'] = password_hash($newAdminPassword, PASSWORD_DEFAULT);
            }
        }

        if ($error === '') {
            if (auth_write_config($config)) {
                $message = 'Zugriffseinstellungen wurden gespeichert.';
            } else {
                $error = 'Die Zugriffseinstellungen konnten nicht gespeichert werden.';
            }
        }
    }
}

$config = auth_read_config();

function ra_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rechteverwaltung</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#f3f5f7;--card:#fff;--text:#1f2933;--muted:#667085;--line:#d9dee5;--line-soft:#edf0f3;--shadow:0 8px 24px rgba(15,23,42,.08);--radius:16px;--primary:#2563eb;--button:#e9edf2;--button-hover:#dfe5ec}
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:18px;background:var(--bg);color:var(--text)}
.layout{max-width:1100px;margin:0 auto}
.card{background:var(--card);border:1px solid var(--line-soft);border-radius:var(--radius);padding:20px;margin:0 0 18px 0;box-shadow:var(--shadow)}
h1{margin:0 0 16px 0} h2{margin:0 0 12px 0}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.field label,label{display:block;font-weight:700;margin-bottom:6px}
input,select{width:100%;padding:9px 10px;border:1px solid #cbd5df;border-radius:9px;background:#fff;font:inherit}
input[type=checkbox]{width:auto}
.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:9px 13px;border:0;border-radius:9px;background:var(--button);color:#111;text-decoration:none;cursor:pointer;font:inherit;white-space:nowrap}
.btn:hover,button:hover{background:var(--button-hover)}
.primary{background:var(--primary);color:#fff}.primary:hover{background:#1d4ed8}
.topLinks,.formActions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.notice{padding:10px 12px;background:#e8f0ff;border:1px solid #c8dafd;border-radius:12px;margin:0 0 16px 0}
.error{padding:10px 12px;background:#fee2e2;border:1px solid #fecaca;border-radius:12px;margin:0 0 16px 0}
.small{font-size:.9rem;color:var(--muted)}
.badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;background:#dcfce7;color:#166534;font-weight:800}
.badge.off{background:#fef3c7;color:#92400e}
</style>
</head>
<body>
<div class="layout">
    <div class="topLinks">
        <a class="btn" href="admin.php?page=master">Zur Master-Verwaltung</a>
        <?php if (!$isInitialSetup): ?>
            <a class="btn" href="logout.php?mode=admin">Adminmodus verlassen</a>
        <?php endif; ?>
    </div>

    <h1>Rechteverwaltung</h1>

    <?php if ($isInitialSetup): ?>
        <div class="notice">
            <strong>Ersteinrichtung:</strong> Es ist noch kein Admin-Passwort gesetzt. Bitte jetzt ein Admin-Passwort erstellen.
        </div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= ra_h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error"><?= ra_h($error) ?></div>
    <?php endif; ?>

    <?php if ($isInitialSetup): ?>
        <form method="post">
            <div class="card">
                <h2>Admin-Passwort erstellen</h2>
                <div class="grid">
                    <div class="field">
                        <label>Neues Admin-Passwort</label>
                        <input type="password" name="newAdminPassword" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label>Admin-Passwort wiederholen</label>
                        <input type="password" name="newAdminPassword2" required autocomplete="new-password">
                    </div>
                </div>
                <p class="small">Mindestens 8 Zeichen. Dieses Passwort aktiviert später den Adminmodus.</p>
            </div>
            <div class="formActions">
                <button class="primary" type="submit">Admin-Passwort setzen</button>
            </div>
        </form>
    <?php else: ?>
        <form method="post">
            <div class="card">
                <h2>Zugriffsmodus</h2>
                <div class="grid">
                    <div class="field">
                        <label>Aktiver Modus</label>
                        <select disabled>
                            <option>Einfacher Modus: Inhaltsmodus + Adminmodus</option>
                        </select>
                        <p class="small">Das spätere Benutzersystem ist vorbereitet, aber noch nicht aktiv.</p>
                    </div>
                    <div class="field">
                        <label>Benutzersystem</label>
                        <span class="badge off">vorbereitet</span>
                        <p class="small">Später können daraus Benutzerkonten mit Rollen entstehen, ohne die komplette Rechtebasis umzubauen.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Inhaltsmodus / Pflege-PIN</h2>
                <label>
                    <input type="checkbox" name="editorRequiresPin" value="1" <?= !empty($config['editorAccess']['requiresPin']) ? 'checked' : '' ?>>
                    Pflege-PIN für Inhaltsmodus aktivieren
                </label>
                <p class="small">Wenn deaktiviert, kann die Inhaltsverwaltung ohne PIN geöffnet werden. Wenn aktiviert, wird vor der Inhaltsverwaltung ein Pflege-PIN abgefragt.</p>

                <div class="grid">
                    <div class="field">
                        <label>Neuer Pflege-PIN</label>
                        <input type="password" name="newEditorPin" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label>Pflege-PIN wiederholen</label>
                        <input type="password" name="newEditorPin2" autocomplete="new-password">
                    </div>
                </div>
                <p class="small">Leer lassen, wenn ein vorhandener PIN behalten werden soll. Mindestens 4 Zeichen.</p>
            </div>

            <div class="card">
                <h2>Admin-Passwort ändern</h2>
                <div class="grid">
                    <div class="field">
                        <label>Neues Admin-Passwort</label>
                        <input type="password" name="newAdminPassword" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label>Admin-Passwort wiederholen</label>
                        <input type="password" name="newAdminPassword2" autocomplete="new-password">
                    </div>
                </div>
                <p class="small">Leer lassen, wenn das Admin-Passwort unverändert bleiben soll.</p>
            </div>

            <div class="formActions">
                <button class="primary" type="submit">Zugriffseinstellungen speichern</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
