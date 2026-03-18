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
    'last_action' => 'none'
]);

$fallbackText = '';
$fallbackFile = __DIR__ . '/cache/fallback_active.flag';
if (is_file($fallbackFile)) {
    $fallbackText = trim((string)file_get_contents($fallbackFile));
}

function slideTypeLabel(array $item): string {
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
</style>
</head>
<body>

<h1>Infoscreen2 Verwaltung</h1>

<div class="toolbar" style="margin-bottom:16px">
  <a class="btn secondary" href="index.php" target="_blank">Player öffnen</a>
  <a class="btn secondary" href="fallback.php" target="_blank">Fallback-Seite öffnen</a>
  <a class="btn secondary" href="status.php" target="_blank">Status JSON</a>
  <a class="btn secondary" href="view_log.php">Logs anzeigen</a>
  <a class="btn secondary" href="backups.php">Backups</a>
  <form action="watchdog_reset.php" method="post" style="display:inline">
    <button class="secondary" type="submit">Watchdog zurücksetzen</button>
  </form>
</div>

<div class="card">
  <h2>Direkte Aktionen</h2>
  <div class="actions" style="margin-top:12px">
    <form action="player_restart.php" method="post"><button type="submit">Player jetzt neu starten</button></form>
    <form action="fallback_toggle.php" method="post"><input type="hidden" name="mode" value="on"><button class="warn" type="submit">Fallback aktivieren</button></form>
    <form action="fallback_toggle.php" method="post"><input type="hidden" name="mode" value="off"><button class="secondary" type="submit">Fallback deaktivieren</button></form>
    <form action="run_watchdog.php" method="post"><button class="secondary" type="submit">Watchdog jetzt ausführen</button></form>
    <form action="backup.php" method="post"><button class="secondary" type="submit">Backup erstellen</button></form>
    <form action="cleanup_uploads.php" method="post"><button class="danger" type="submit">Verwaiste Dateien aufräumen</button></form>
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
  <p id="liveStatus" class="small" style="margin-top:12px">Lade Live-Status ...</p>
</div>

<div class="card">
  <form action="save_config.php" method="post" enctype="multipart/form-data">
    <h2>Allgemeine Einstellungen</h2>
    <div class="grid">
      <div><label>Standarddauer</label><input type="number" name="defaultDuration" min="1" value="<?= (int)($config['screen']['defaultDuration'] ?? 8) ?>"></div>
      <div><label>Bild-Fade</label><input type="number" step="0.1" name="defaultFade" min="0" value="<?= h((string)($config['screen']['defaultFade'] ?? 1.2)) ?>"></div>
      <div><label>Bild-Anpassung</label><select name="fit"><option value="contain" <?= ($config['screen']['fit'] ?? 'contain') === 'contain' ? 'selected' : '' ?>>contain</option><option value="cover" <?= ($config['screen']['fit'] ?? '') === 'cover' ? 'selected' : '' ?>>cover</option></select></div>
      <div><label>Hintergrundfarbe</label><input type="text" name="background" value="<?= h((string)($config['screen']['background'] ?? '#ffffff')) ?>"></div>
    </div>

    <h2 style="margin-top:20px">Uhr</h2>
    <div class="grid">
      <div><label>Uhr aktiviert</label><select name="clockEnabled"><option value="1" <?= !empty($config['clock']['enabled']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= empty($config['clock']['enabled']) ? 'selected' : '' ?>>Nein</option></select></div>
      <div><label>Uhr Dauer</label><input type="number" name="clockDuration" min="1" value="<?= (int)($config['clock']['defaultDuration'] ?? 10) ?>"></div>
      <div><label>Uhr Hintergrund</label><input type="text" name="clockBackground" value="<?= h((string)($config['clock']['background'] ?? '#ffffff')) ?>"></div>
      <div><label>Uhr Textfarbe</label><input type="text" name="clockTextColor" value="<?= h((string)($config['clock']['textColor'] ?? '#111111')) ?>"></div>
      <div><label>Sekunden anzeigen</label><select name="clockShowSeconds"><option value="1" <?= !empty($config['clock']['showSeconds']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= empty($config['clock']['showSeconds']) ? 'selected' : '' ?>>Nein</option></select></div>
      <div><label>Logo für Uhr</label><input type="file" name="clockLogo" accept=".png,.jpg,.jpeg,.webp,.gif"></div>
      <div><label>Aktuell:</label><code><?= h((string)($config['clock']['logo'] ?? '')) ?></code></div>
      <div><label>Logo-Höhe</label><input type="number" name="clockLogoHeight" min="20" value="<?= (int)($config['clock']['logoHeight'] ?? 100) ?>"></div>
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

    <p style="margin-top:16px"><button type="submit">Einstellungen speichern</button></p>
  </form>
</div>

<div class="card">
  <h2>Neue Datei hochladen</h2>
  <form action="upload.php" method="post" enctype="multipart/form-data">
    <div class="grid">
      <div><label>Datei</label><input type="file" name="mediaFile" accept=".png,.jpg,.jpeg,.webp,.gif,.mp4,.webm,.mov,.pdf"></div>
      <div><label>Titel</label><input type="text" name="title" placeholder="z. B. Empfang"></div>
      <div><label>Typ</label><select name="type"><option value="image">Bild</option><option value="video">Video</option><option value="pdf">PDF</option></select></div>
      <div><label>Dauer</label><input type="number" name="duration" min="1" value="<?= (int)($config['screen']['defaultDuration'] ?? 8) ?>"></div>
      <div><label>Aktiviert</label><select name="enabled"><option value="1" selected>Ja</option><option value="0">Nein</option></select></div>
      <div><label>Bei Video: stumm</label><select name="muted"><option value="1" selected>Ja</option><option value="0">Nein</option></select></div>
    </div>
    <p class="small" style="margin-top:10px">PDF-Dateien werden beim Upload automatisch in Bildseiten umgewandelt.</p>
    <p style="margin-top:16px"><button type="submit">Datei hochladen</button></p>
  </form>
</div>

<div class="card">
  <h2>Neue Webseiten-Folie</h2>
  <form action="upload.php" method="post">
    <input type="hidden" name="type" value="website">
    <div class="grid">
      <div><label>Titel</label><input type="text" name="title" placeholder="z. B. Caritas Webseite"></div>
      <div><label>URL</label><input type="url" name="url" placeholder="https://www.example.org" required></div>
      <div><label>Dauer</label><input type="number" name="duration" min="1" value="<?= (int)($config['screen']['defaultDuration'] ?? 8) ?>"></div>
      <div><label>Neuladen nach Sekunden</label><input type="number" name="refreshSeconds" min="0" value="0"></div>
      <div><label>Timeout Sekunden</label><input type="number" name="timeout" min="1" value="8"></div>
      <div><label>Aktiviert</label><select name="enabled"><option value="1" selected>Ja</option><option value="0">Nein</option></select></div>
    </div>
    <p style="margin-top:16px"><button type="submit">Webseiten-Folie speichern</button></p>
  </form>
</div>

<div class="card">
  <h2>Playlist</h2>
  <table>
    <thead><tr><th>Sort</th><th>Titel</th><th>Typ</th><th>Status</th><th>Info</th><th>Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($slides as $item): ?>
    <tr>
      <td><?= (int)($item['sort'] ?? 0) ?></td>
      <td><?= h($item['title'] ?? '-') ?></td>
      <td><span class="badge"><?= h(slideTypeLabel($item)) ?></span></td>
      <td><?php if (!empty($item['enabled'])): ?><span class="badge ok">aktiv</span><?php else: ?><span class="badge off">inaktiv</span><?php endif; ?></td>
      <td>
        <?php if (($item['type'] ?? '') === 'website'): ?>
          <code><?= h($item['url'] ?? '') ?></code>
        <?php else: ?>
          <code><?= h($item['file'] ?? '') ?></code>
        <?php endif; ?>

        <div class="small">Dauer: <?= (int)($item['duration'] ?? 0) ?>s</div>

        <?php if ((($item['type'] ?? '') === 'image') && (($item['sourceType'] ?? '') === 'pdf')): ?>
          <div class="small">Quelle: <?= h($item['sourceTitle'] ?? 'PDF') ?></div>
          <div class="small">Seite: <?= (int)($item['page'] ?? 0) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <div class="actions">
          <form action="move_slide.php" method="post"><input type="hidden" name="id" value="<?= h($item['id'] ?? '') ?>"><input type="hidden" name="dir" value="up"><button class="btn" type="submit">Hoch</button></form>
          <form action="move_slide.php" method="post"><input type="hidden" name="id" value="<?= h($item['id'] ?? '') ?>"><input type="hidden" name="dir" value="down"><button class="btn" type="submit">Runter</button></form>
          <form action="toggle_slide.php" method="post"><input type="hidden" name="id" value="<?= h($item['id'] ?? '') ?>"><button class="btn" type="submit"><?= !empty($item['enabled']) ? 'Deaktivieren' : 'Aktivieren' ?></button></form>
          <button class="btn" type="button" onclick="toggleEdit('edit-<?= h($item['id'] ?? '') ?>')">Bearbeiten</button>
          <form action="delete_slide.php" method="post" onsubmit="return confirm('Folie wirklich löschen?');"><input type="hidden" name="id" value="<?= h($item['id'] ?? '') ?>"><button class="btn" type="submit">Löschen</button></form>
        </div>
      </td>
    </tr>
    <tr id="edit-<?= h($item['id'] ?? '') ?>" class="editRow">
      <td colspan="6">
        <form action="update_slide.php" method="post">
          <input type="hidden" name="id" value="<?= h($item['id'] ?? '') ?>">
          <div class="grid">
            <div><label>Titel</label><input type="text" name="title" value="<?= h($item['title'] ?? '') ?>"></div>
            <div><label>Dauer</label><input type="number" name="duration" min="1" value="<?= (int)($item['duration'] ?? 8) ?>"></div>
            <div><label>Aktiv</label><select name="enabled"><option value="1" <?= !empty($item['enabled']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= empty($item['enabled']) ? 'selected' : '' ?>>Nein</option></select></div>

            <?php if (($item['type'] ?? '') === 'image'): ?>
            <div><label>Fit</label><select name="fit"><option value="contain" <?= ($item['fit'] ?? '') === 'contain' ? 'selected' : '' ?>>contain</option><option value="cover" <?= ($item['fit'] ?? '') === 'cover' ? 'selected' : '' ?>>cover</option></select></div>
            <div><label>Fade</label><input type="number" step="0.1" name="fade" min="0" value="<?= h((string)($item['fade'] ?? 1.2)) ?>"></div>
            <?php endif; ?>

            <?php if (($item['type'] ?? '') === 'video'): ?>
            <div><label>Video-Modus</label><select name="videoMode"><option value="until_end" <?= ($item['videoMode'] ?? '') === 'until_end' ? 'selected' : '' ?>>bis Ende</option><option value="fixed" <?= ($item['videoMode'] ?? '') === 'fixed' ? 'selected' : '' ?>>feste Dauer</option></select></div>
            <div><label>Stumm</label><select name="muted"><option value="1" <?= !empty($item['muted']) ? 'selected' : '' ?>>Ja</option><option value="0" <?= empty($item['muted']) ? 'selected' : '' ?>>Nein</option></select></div>
            <?php endif; ?>

            <?php if (($item['type'] ?? '') === 'website'): ?>
            <div><label>URL</label><input type="url" name="url" value="<?= h($item['url'] ?? '') ?>"></div>
            <div><label>Refresh Sekunden</label><input type="number" name="refreshSeconds" min="0" value="<?= (int)($item['refreshSeconds'] ?? 0) ?>"></div>
            <div><label>Timeout Sekunden</label><input type="number" name="timeout" min="1" value="<?= (int)($item['timeout'] ?? 8) ?>"></div>
            <?php endif; ?>
          </div>
          <p style="margin-top:12px"><button type="submit">Änderungen speichern</button></p>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function toggleEdit(id){
  const el=document.getElementById(id);
  if(!el)return;
  el.style.display=(el.style.display==='table-row')?'none':'table-row';
}
async function refreshLiveStatus(){
  try{
    const r=await fetch('status.php?_=' + Date.now());
    const j=await r.json();
    const el=document.getElementById('liveStatus');
    if(!el)return;
    el.textContent='Zeit: '+j.time+' | Player: '+(j.player_running?'RUN':'STOP')+' | Apache: '+(j.apache_running?'RUN':'STOP')+' | Fallback: '+(j.fallback_active?'AN':'AUS')+' | Slides: '+j.enabled_slides+' | Letzte Logzeile: '+j.last_log_line;
  }catch(e){
    const el=document.getElementById('liveStatus');
    if(el) el.textContent='Live-Status konnte nicht geladen werden';
  }
}
refreshLiveStatus();
setInterval(refreshLiveStatus, 10000);
</script>

</body>
</html>
