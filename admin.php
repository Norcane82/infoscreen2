<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$config = load_config();
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

$restartData = read_json_file(DATA_DIR . '/restart_log.json', [
    'count_30m' => 0,
    'restarts' => [],
    'fallback_active' => false,
    'consecutive_failures' => 0,
    'last_action' => 'none',
]);

$fallbackText = '';
$fallbackFile = __DIR__ . '/cache/fallback_active.flag';
if (is_file($fallbackFile)) {
    $fallbackText = trim((string)file_get_contents($fallbackFile));
}

$cleanupMessage = '';
if (isset($_GET['cleanup'])) {
    $deleted = (int)($_GET['deleted'] ?? 0);
    $kept = (int)($_GET['kept'] ?? 0);
    $cleanupMessage = 'Aufräumen abgeschlossen. Gelöscht: ' . $deleted . ' | Behalten: ' . $kept;
}

function slideTypeLabel(array $item): string
{
    $type = strtolower((string)($item['type'] ?? ''));
    if ($type === 'image' && (($item['sourceType'] ?? '') === 'pdf')) {
        return 'PDF-Seite';
    }

    $map = [
        'clock' => 'Uhr',
        'image' => 'Bild',
        'video' => 'Video',
        'website' => 'Webseite',
        'pdf' => 'PDF',
    ];

    return $map[$type] ?? $type;
}

function colorField(string $label, string $textName, string $pickerName, string $value): string
{
    $safeLabel = h($label);
    $safeTextName = h($textName);
    $safePickerName = h($pickerName);
    $safeValue = h($value);

    return <<<HTML
    <div>
      <label>{$safeLabel}</label>
      <div class="colorField">
        <input type="text" name="{$safeTextName}" value="{$safeValue}" data-color-text>
        <input type="color" name="{$safePickerName}" value="{$safeValue}" data-color-picker>
      </div>
    </div>
HTML;
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Infoscreen2 Verwaltung</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:18px;background:#f4f6f8;color:#1f2937}
h1,h2{margin:0 0 12px}
.card{background:#fff;border:1px solid #d7dce2;border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
label{display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:#475569}
input[type=text],input[type=url],input[type=number],select,input[type=file]{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}
input[type=color]{width:56px;min-width:56px;height:42px;padding:3px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer}
button,.btn{appearance:none;border:0;border-radius:10px;padding:10px 14px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
button.secondary,.btn.secondary{background:#475569}
button.warn,.btn.warn{background:#d97706}
button.danger,.btn.danger{background:#dc2626}
.actions{display:flex;flex-wrap:wrap;gap:8px}
small,.small{color:#64748b}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 8px;border-top:1px solid #e5e7eb;vertical-align:top;text-align:left}
th{font-size:12px;text-transform:uppercase;color:#64748b}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;background:#e2e8f0;color:#0f172a}
.ok{background:#dcfce7;color:#166534}
.off{background:#fee2e2;color:#991b1b}
.toolbar{display:flex;flex-wrap:wrap;gap:8px}
.editRow{display:none;background:#f8fafc}
code{background:#eef2ff;padding:2px 6px;border-radius:6px}
.statusLine{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.notice{background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;padding:12px 14px;border-radius:10px;margin-bottom:16px}
.colorField{display:flex;gap:8px;align-items:center}
.colorField input[type=text]{flex:1 1 auto}
.formActions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
.logLinks{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.statusBox{margin-top:12px;padding:12px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0}
.statusBox strong{display:inline-block;min-width:140px}
</style>
</head>
<body>

<h1>Infoscreen2 Verwaltung</h1>

<?php if ($cleanupMessage !== ''): ?>
<div class="notice"><?= h($cleanupMessage) ?></div>
<?php endif; ?>

<div class="toolbar" style="margin-bottom:16px">
  <a class="btn secondary" href="index.php" target="_blank">Player öffnen</a>
  <a class="btn secondary" href="fallback.php" target="_blank">Fallback-Seite öffnen</a>
  <a class="btn secondary" href="status.php" target="_blank">Status JSON</a>
  <a class="btn secondary" href="view_log.php">Logs anzeigen</a>
  <a class="btn secondary" href="view_log.php?file=status">Status-Snapshots</a>
  <a class="btn secondary" href="backups.php">Backups</a>
  <form action="watchdog_reset.php" method="post" style="display:inline">
    <button class="secondary" type="submit">Watchdog zurücksetzen</button>
  </form>
</div>

<div class="card">
  <h2>Direkte Aktionen</h2>
  <div class="actions" style="margin-top:12px">
    <form action="player_action.php" method="post">
      <input type="hidden" name="action" value="restart_player">
      <button type="submit">Player jetzt neu starten</button>
    </form>

    <form action="player_action.php" method="post">
      <input type="hidden" name="action" value="fallback_on">
      <button class="warn" type="submit">Fallback aktivieren</button>
    </form>

    <form action="player_action.php" method="post">
      <input type="hidden" name="action" value="fallback_off">
      <button class="secondary" type="submit">Fallback deaktivieren</button>
    </form>

    <form action="player_action.php" method="post" class="confirmForm" data-confirm="Wirklich das gesamte System neu starten?">
      <input type="hidden" name="action" value="reboot_system">
      <button class="danger" type="submit">System neu starten</button>
    </form>

    <form action="run_watchdog.php" method="post">
      <button class="secondary" type="submit">Watchdog jetzt ausführen</button>
    </form>

    <form action="backup.php" method="post">
      <button class="secondary" type="submit">Backup erstellen</button>
    </form>

    <form action="cleanup_orphans.php" method="post" class="confirmForm" data-confirm="Wirklich verwaiste Dateien aufräumen?">
      <button class="danger" type="submit">Verwaiste Dateien aufräumen</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>Status</h2>
  <div class="statusLine" style="margin-top:10px">
    <span class="badge <?= !empty($restartData['fallback_active']) ? 'off' : 'ok' ?>">Fallback <?= !empty($restartData['fallback_active']) ? 'aktiv' : 'normal' ?></span>
    <span>Letzte Aktion: <code><?= h((string)($restartData['last_action'] ?? 'none')) ?></code></span>
    <span>Neustarts in 30 Min: <strong><?= (int)($restartData['count_30m'] ?? 0) ?></strong></span>
    <span>Consecutive Failures: <strong><?= (int)($restartData['consecutive_failures'] ?? 0) ?></strong></span>
    <?php if ($fallbackText !== ''): ?>
      <span>Fallback-Grund: <code><?= h($fallbackText) ?></code></span>
    <?php endif; ?>
  </div>

  <div id="liveStatusBox" class="statusBox">
    <div><strong>Live-Status:</strong> <span id="liveStatus">Lade Live-Status ...</span></div>
    <div style="margin-top:8px"><strong>Status-Zusammenfassung:</strong> <span id="liveStatusSummary">wird geladen ...</span></div>
    <div style="margin-top:8px"><strong>Letzter Snapshot:</strong> <span id="liveStatusSnapshot">noch keiner</span></div>
  </div>

  <div class="logLinks">
    <a class="btn secondary" href="view_log.php?file=app">App Log</a>
    <a class="btn secondary" href="view_log.php?file=trace">Player Trace Log</a>
    <a class="btn secondary" href="view_log.php?file=status">Status-Snapshots</a>
  </div>
</div>

<div class="card">
  <form action="save_settings.php" method="post" enctype="multipart/form-data">
    <h2>Allgemeine Einstellungen</h2>
    <div class="grid">
      <div>
        <label>Standarddauer</label>
        <input type="number" name="defaultDuration" min="1" value="<?= (int)($config['screen']['defaultDuration'] ?? 8) ?>">
      </div>

      <div>
        <label>Bild-Fade</label>
        <input type="number" step="0.1" name="defaultFade" min="0" value="<?= h((string)($config['screen']['defaultFade'] ?? 1.2)) ?>">
      </div>

      <div>
        <label>Bild-Anpassung</label>
        <select name="fit">
          <option value="contain" <?= ($config['screen']['fit'] ?? 'contain') === 'contain' ? 'selected' : '' ?>>contain</option>
          <option value="cover" <?= ($config['screen']['fit'] ?? '') === 'cover' ? 'selected' : '' ?>>cover</option>
        </select>
      </div>

      <?= colorField(
          'Hintergrundfarbe',
          'background',
          'backgroundPicker',
          (string)($config['screen']['background'] ?? '#ffffff')
      ) ?>
    </div>

    <h2 style="margin-top:20px">Uhr</h2>
    <div class="grid">
      <div>
        <label>Uhr aktiviert</label>
        <select name="clockEnabled">
          <option value="1" <?= !empty($config['clock']['enabled']) ? 'selected' : '' ?>>Ja</option>
          <option value="0" <?= empty($config['clock']['enabled']) ? 'selected' : '' ?>>Nein</option>
        </select>
      </div>

      <div>
        <label>Uhr Dauer</label>
        <input type="number" name="clockDuration" min="1" value="<?= (int)($config['clock']['defaultDuration'] ?? 10) ?>">
      </div>

      <?= colorField(
          'Uhr Hintergrund',
          'clockBackground',
          'clockBackgroundPicker',
          (string)($config['clock']['background'] ?? '#ffffff')
      ) ?>

      <?= colorField(
          'Uhr Textfarbe',
          'clockTextColor',
          'clockTextColorPicker',
          (string)($config['clock']['textColor'] ?? '#111111')
      ) ?>

      <div>
        <label>Sekunden anzeigen</label>
        <select name="clockShowSeconds">
          <option value="1" <?= !empty($config['clock']['showSeconds']) ? 'selected' : '' ?>>Ja</option>
          <option value="0" <?= empty($config['clock']['showSeconds']) ? 'selected' : '' ?>>Nein</option>
        </select>
      </div>

      <div>
        <label>Logo für Uhr</label>
        <input type="file" name="clockLogo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
      </div>

      <div>
        <label>Aktuell:</label>
        <code><?= h((string)($config['clock']['logo'] ?? '')) ?></code>
      </div>

      <div>
        <label>Logo-Höhe</label>
        <input type="number" name="clockLogoHeight" min="20" max="400" value="<?= (int)($config['clock']['logoHeight'] ?? 100) ?>">
      </div>
    </div>

    <h2 style="margin-top:20px">Watchdog</h2>
    <div class="grid">
      <div><label>Watchdog aktiviert</label><select name="watchdogEnabled"><option value="1" <?= !empty($config['system']['watchdogEnabled']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= empty($config['system']['watchdogEnabled']) ? 'selected' : '' ?>>Nein</option></select></div>
      <div><label>CPU Limit %</label><input type="number" name="cpuLimit" min="1" max="100" value="<?= (int)($config['system']['maxCpuPercent'] ?? 85) ?>"></div>
      <div><label>RAM Limit %</label><input type="number" name="ramLimit" min="1" max="100" value="<?= (int)($config['system']['maxRamPercent'] ?? 85) ?>"></div>
      <div><label>Cooldown Sekunden</label><input type="number" name="cooldownSeconds" min="30" value="<?= (int)($config['system']['restartCooldownSeconds'] ?? 180) ?>"></div>
      <div><label>Max Restarts / 30 Min</label><input type="number" name="maxRestartsIn30Min" min="1" value="<?= (int)($config['system']['maxRestartsPer30Min'] ?? 3) ?>"></div>
      <div><label>Consecutive Fails nötig</label><input type="number" name="requireConsecutiveFails" min="1" value="<?= (int)($config['system']['requireConsecutiveFails'] ?? 2) ?>"></div>
      <div><label>Reboot nach Player-Restarts</label><input type="number" name="rebootAfterPlayerRestarts" min="1" value="<?= (int)($config['system']['rebootAfterPlayerRestarts'] ?? 2) ?>"></div>
      <div><label>Apache Healthcheck</label><select name="apacheHealthcheck"><option value="1" <?= !empty($config['system']['apacheHealthcheck']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= empty($config['system']['apacheHealthcheck']) ? 'selected' : '' ?>>Nein</option></select></div>
      <div><label>Apache URL</label><input type="text" name="apacheUrl" value="<?= h((string)($config['system']['apacheUrl'] ?? 'http://127.0.0.1/infoscreen2/index.php')) ?>"></div>
      <div><label>Apache Timeout Sekunden</label><input type="number" name="apacheTimeoutSeconds" min="1" value="<?= (int)($config['system']['apacheTimeoutSeconds'] ?? 5) ?>"></div>
      <div><label>Apache im Fallback stoppen</label><select name="stopApacheOnFallback"><option value="1" <?= !empty($config['services']['stopApacheOnFallback']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= empty($config['services']['stopApacheOnFallback']) ? 'selected' : '' ?>>Nein</option></select></div>
    </div>

    <div class="formActions">
      <button type="submit">Einstellungen speichern</button>
    </div>
  </form>
</div>

<div class="card">
  <form action="upload.php" method="post" enctype="multipart/form-data">
    <h2>Neue Datei hochladen</h2>
    <div class="grid">
      <div><label>Datei</label><input type="file" name="mediaFile" required></div>
      <div><label>Titel</label><input type="text" name="title" required></div>
      <div><label>Typ</label><select name="type"><option value="image">Bild</option><option value="video">Video</option><option value="pdf">PDF</option></select></div>
      <div><label>Dauer</label><input type="number" name="duration" min="1" value="10"></div>
      <div><label>Aktiviert</label><select name="enabled"><option value="1" selected>Ja</option><option value="0">Nein</option></select></div>
      <div><label>Bei Video: stumm</label><select name="muted"><option value="1" selected>Ja</option><option value="0">Nein</option></select></div>
    </div>
    <p class="small" style="margin-top:10px">PDF-Dateien werden beim Upload automatisch in Bildseiten umgewandelt.</p>
    <div class="formActions">
      <button type="submit">Datei hochladen</button>
    </div>
  </form>
</div>

<div class="card">
  <form action="upload.php" method="post">
    <input type="hidden" name="mode" value="website">
    <h2>Neue Webseiten-Folie</h2>
    <div class="grid">
      <div><label>Titel</label><input type="text" name="title" required></div>
      <div><label>URL</label><input type="url" name="url" required></div>
      <div><label>Dauer</label><input type="number" name="duration" min="1" value="10"></div>
      <div><label>Neuladen nach Sekunden</label><input type="number" name="refreshSeconds" min="0" value="0"></div>
      <div><label>Timeout Sekunden</label><input type="number" name="timeoutSeconds" min="1" value="8"></div>
      <div><label>Aktiviert</label><select name="enabled"><option value="1" selected>Ja</option><option value="0">Nein</option></select></div>
    </div>
    <div class="formActions">
      <button type="submit">Webseiten-Folie speichern</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Playlist</h2>
  <table>
    <thead>
      <tr>
        <th>Sort</th>
        <th>Titel</th>
        <th>Typ</th>
        <th>Status</th>
        <th>Info</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($slides as $item): ?>
      <?php
        $id = (string)($item['id'] ?? '');
        $enabled = !empty($item['enabled']);
        $title = (string)($item['title'] ?? '');
        $typeLabel = slideTypeLabel($item);
        $infoParts = [];
        if (isset($item['duration'])) {
            $infoParts[] = 'Dauer: ' . h((string)$item['duration']) . ' s';
        }
        if (!empty($item['file'])) {
            $infoParts[] = 'Quelle: ' . h((string)$item['file']);
        }
        if (!empty($item['url'])) {
            $infoParts[] = 'URL: ' . h((string)$item['url']);
        }
        if (isset($item['page'])) {
            $infoParts[] = 'Seite: ' . h((string)$item['page']);
        }
      ?>
      <tr>
        <td><?= (int)($item['sort'] ?? 0) ?></td>
        <td><?= h($title) ?></td>
        <td><?= h($typeLabel) ?></td>
        <td><span class="badge <?= $enabled ? 'ok' : 'off' ?>"><?= $enabled ? 'aktiv' : 'inaktiv' ?></span></td>
        <td><?= implode('<br>', $infoParts) ?></td>
        <td>
          <div class="actions">
            <form action="move_slide.php" method="post">
              <input type="hidden" name="id" value="<?= h($id) ?>">
              <input type="hidden" name="direction" value="up">
              <button class="secondary" type="submit">Hoch</button>
            </form>
            <form action="move_slide.php" method="post">
              <input type="hidden" name="id" value="<?= h($id) ?>">
              <input type="hidden" name="direction" value="down">
              <button class="secondary" type="submit">Runter</button>
            </form>
            <form action="toggle_slide.php" method="post">
              <input type="hidden" name="id" value="<?= h($id) ?>">
              <button class="secondary" type="submit"><?= $enabled ? 'Deaktivieren' : 'Aktivieren' ?></button>
            </form>
            <button class="secondary" type="button" onclick="toggleEdit('edit-<?= h($id) ?>')">Bearbeiten</button>
            <form action="delete_slide.php" method="post" class="confirmForm" data-confirm="Folie wirklich löschen?">
              <input type="hidden" name="id" value="<?= h($id) ?>">
              <button class="danger" type="submit">Löschen</button>
            </form>
          </div>
        </td>
      </tr>
      <tr id="edit-<?= h($id) ?>" class="editRow">
        <td colspan="6">
          <form action="update_slide.php" method="post">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <div class="grid">
              <div><label>Titel</label><input type="text" name="title" value="<?= h((string)($item['title'] ?? '')) ?>"></div>
              <div><label>Dauer</label><input type="number" min="1" name="duration" value="<?= (int)($item['duration'] ?? 10) ?>"></div>
              <div><label>Aktiv</label><select name="enabled"><option value="1" <?= $enabled ? 'selected' : '' ?>>Ja</option><option value="0" <?= !$enabled ? 'selected' : '' ?>>Nein</option></select></div>
              <div><label>Fit</label><select name="fit"><option value="contain" <?= (($item['fit'] ?? 'contain') === 'contain') ? 'selected' : '' ?>>contain</option><option value="cover" <?= (($item['fit'] ?? '') === 'cover') ? 'selected' : '' ?>>cover</option></select></div>
              <div><label>Fade</label><input type="number" step="0.1" min="0" name="fade" value="<?= h((string)($item['fade'] ?? ($config['screen']['defaultFade'] ?? 1.2))) ?>"></div>
              <div><label>Video-Modus</label><select name="videoMode"><option value="until_end" <?= (($item['videoMode'] ?? 'until_end') === 'until_end') ? 'selected' : '' ?>>bis Ende</option><option value="fixed_duration" <?= (($item['videoMode'] ?? '') === 'fixed_duration') ? 'selected' : '' ?>>feste Dauer</option></select></div>
              <div><label>Stumm</label><select name="muted"><option value="1" <?= !isset($item['muted']) || !empty($item['muted']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= isset($item['muted']) && empty($item['muted']) ? 'selected' : '' ?>>Nein</option></select></div>
              <div><label>URL</label><input type="url" name="url" value="<?= h((string)($item['url'] ?? '')) ?>"></div>
              <div><label>Refresh Sekunden</label><input type="number" min="0" name="refreshSeconds" value="<?= (int)($item['refreshSeconds'] ?? 0) ?>"></div>
              <div><label>Timeout Sekunden</label><input type="number" min="1" name="timeoutSeconds" value="<?= (int)($item['timeoutSeconds'] ?? 8) ?>"></div>
            </div>
            <div class="formActions">
              <button type="submit">Änderungen speichern</button>
            </div>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function toggleEdit(id) {
  const row = document.getElementById(id);
  if (!row) {
    return;
  }
  row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
}

async function loadLiveStatus() {
  const target = document.getElementById('liveStatus');
  const summary = document.getElementById('liveStatusSummary');
  const snapshot = document.getElementById('liveStatusSnapshot');

  if (!target || !summary || !snapshot) {
    return;
  }

  try {
    const response = await fetch('status.php', { cache: 'no-store' });
    const data = await response.json();

    if (!data || data.ok !== true) {
      target.textContent = 'Live-Status konnte nicht geladen werden.';
      summary.textContent = 'keine Zusammenfassung verfügbar';
      snapshot.textContent = 'kein Snapshot';
      return;
    }

    target.textContent =
      'Player: ' + (data.player_running ? 'läuft' : 'steht') +
      ' | Apache: ' + (data.apache_running ? 'läuft' : 'steht') +
      ' | Aktivierte Folien: ' + data.enabled_slides +
      ' | Angeforderte Ansicht: ' + (data.requested_view || 'index') +
      ' | Letzte Aktion: ' + (data.last_action || 'none');

    summary.textContent = data.status_summary || 'keine Zusammenfassung vorhanden';
    snapshot.textContent = data.last_status_snapshot_time || 'noch kein Snapshot';
  } catch (error) {
    target.textContent = 'Live-Status konnte nicht geladen werden.';
    summary.textContent = 'keine Zusammenfassung verfügbar';
    snapshot.textContent = 'kein Snapshot';
  }
}

function bindColorFields() {
  document.querySelectorAll('.colorField').forEach((field) => {
    const text = field.querySelector('[data-color-text]');
    const picker = field.querySelector('[data-color-picker]');

    if (!text || !picker) {
      return;
    }

    const normalize = (value) => {
      const trimmed = String(value || '').trim();
      return /^#[0-9a-fA-F]{6}$/.test(trimmed) ? trimmed : null;
    };

    const syncFromText = () => {
      const normalized = normalize(text.value);
      if (normalized) {
        picker.value = normalized;
      }
    };

    const syncFromPicker = () => {
      text.value = picker.value;
    };

    text.addEventListener('input', syncFromText);
    picker.addEventListener('input', syncFromPicker);

    syncFromText();
  });
}

function bindConfirmForms() {
  document.querySelectorAll('.confirmForm').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.getAttribute('data-confirm') || 'Wirklich ausführen?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
}

loadLiveStatus();
bindColorFields();
bindConfirmForms();
setInterval(loadLiveStatus, 15000);
</script>

</body>
</html>
