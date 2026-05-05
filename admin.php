<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$config = load_config();
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];
$health = read_json_file(HEALTH_FILE, ['last_restart'=>0,'restarts'=>[],'fallback_active'=>false,'consecutive_failures'=>0,'last_action'=>'none','requested_view'=>'index','reload_requested_at'=>0]);

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

function slideTypeLabel(array $item): string {
    $type = strtolower((string)($item['type'] ?? ''));
    if ($type === 'image' && (($item['sourceType'] ?? '') === 'pdf')) { return 'PDF-Seite'; }
    return ['clock'=>'Uhr','image'=>'Bild','video'=>'Video','website'=>'Webseite','pdf'=>'PDF'][$type] ?? $type;
}

function colorField(string $label, string $textName, string $pickerName, string $value): string {
    $safeLabel = h($label);
    $safeTextName = h($textName);
    $safePickerName = h($pickerName);
    $safeValue = h($value);

    return <<<HTML
<div class="field colorField">
    <label>{$safeLabel}</label>
    <div class="colorField__row">
        <input data-color-text type="text" name="{$safeTextName}" value="{$safeValue}">
        <input data-color-picker type="color" name="{$safePickerName}" value="{$safeValue}">
    </div>
</div>
HTML;
}

$adminCss = __DIR__ . '/assets/css/admin.css';
$adminCssVersion = is_file($adminCss) ? (int)filemtime($adminCss) : time();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen2 Verwaltung</title>
<link rel="stylesheet" href="assets/css/admin.css?v=<?= $adminCssVersion ?>">
</head>
<body>
<div class="layout">
<h1>Infoscreen2 Verwaltung</h1>

<div class="topLinks">
    <a class="btn" href="index.php" target="_blank">Player öffnen</a>
    <a class="btn" href="fallback.php" target="_blank">Fallback-Seite öffnen</a>
    <a class="btn" href="status.php" target="_blank">Status JSON</a>
    <a class="btn" href="view_log.php">Logs anzeigen</a>
    <a class="btn" href="backups.php">Backups</a>
    <form action="watchdog_reset.php" method="post" style="display:inline">
        <button class="secondary" type="submit">Watchdog zurücksetzen</button>
    </form>
</div>

<?php if ($cleanupMessage !== ''): ?>
    <div class="notice"><?= h($cleanupMessage) ?></div>
<?php endif; ?>

<div class="card">
    <div class="cardHeader">
        <div>
            <h2>Direkte Aktionen</h2>
            <p class="small">Häufige Wartungsaktionen. Kritische Funktionen sind geschützt im roten Bereich darunter.</p>
        </div>
    </div>

    <div class="actionZone">
        <div class="safeActions">
            <h3>Sichere Standardaktionen</h3>
            <div class="actions">
                <form action="player_action.php" method="post">
                    <input type="hidden" name="action" value="restart_player">
                    <button type="submit" class="restartGuard" data-lock-seconds="30">Player neu starten</button>
                </form>

                <form action="player_action.php" method="post">
                    <input type="hidden" name="action" value="restart_kiosk">
                    <button class="warn restartGuard" type="submit" data-lock-seconds="30">Kiosk-Dienst neu starten</button>
                </form>

                <form action="run_watchdog.php" method="post">
                    <button class="secondary" type="submit">Watchdog jetzt ausführen</button>
                </form>

                <form action="backup.php" method="post">
                    <button class="secondary" type="submit">Backup erstellen</button>
                </form>
            </div>
        </div>

        <details class="criticalPanel">
            <summary>Kritische Aktionen anzeigen</summary>
            <div class="criticalPanel__body">
                <p class="criticalHint">
                    Diese Aktionen beeinflussen den sichtbaren Bildschirm oder das gesamte System. Bitte nur ausführen, wenn es wirklich nötig ist.
                    Nach dem Klick werden kritische Buttons für 30 Sekunden gesperrt.
                </p>
                <div class="actions">
                    <form action="player_action.php" method="post" class="confirmForm" data-confirm="Fallback wirklich aktivieren? Der Bildschirm zeigt dann die Fallback-Seite.">
                        <input type="hidden" name="action" value="fallback_on">
                        <button class="danger restartGuard" type="submit" data-lock-seconds="30">Fallback aktivieren</button>
                    </form>

                    <form action="player_action.php" method="post" class="confirmForm" data-confirm="Fallback wirklich deaktivieren und zurück zum normalen Player wechseln?">
                        <input type="hidden" name="action" value="fallback_off">
                        <button class="warn restartGuard" type="submit" data-lock-seconds="30">Fallback deaktivieren</button>
                    </form>

                    <form action="player_action.php" method="post" class="confirmForm" data-confirm="Wirklich das gesamte System neu starten?">
                        <input type="hidden" name="action" value="reboot_system">
                        <button class="danger restartGuard" type="submit" data-lock-seconds="30">System neu starten</button>
                    </form>

                    <form action="cleanup_orphans.php" method="post" class="confirmForm" data-confirm="Wirklich verwaiste Dateien aufräumen?">
                        <button class="danger restartGuard" type="submit" data-lock-seconds="30">Verwaiste Dateien aufräumen</button>
                    </form>
                </div>
            </div>
        </details>
    </div>
</div>

<div class="card">
    <h2>Status</h2>

    <div class="statusGrid">
        <div class="statusTile">
            <span class="statusTile__label">Fallback</span>
            <span class="badge <?= !empty($health['fallback_active']) ? 'off' : 'ok' ?>">
                <?= !empty($health['fallback_active']) ? 'aktiv' : 'normal' ?>
            </span>
        </div>
        <div class="statusTile">
            <span class="statusTile__label">Letzte Aktion</span>
            <span class="statusTile__value"><code><?= h((string)($health['last_action'] ?? 'none')) ?></code></span>
        </div>
        <div class="statusTile">
            <span class="statusTile__label">Neustarts in 30 Min</span>
            <span class="statusTile__value"><?= count((array)($health['restarts'] ?? [])) ?></span>
        </div>
        <div class="statusTile">
            <span class="statusTile__label">Consecutive Failures</span>
            <span class="statusTile__value"><?= (int)($health['consecutive_failures'] ?? 0) ?></span>
        </div>
    </div>

    <?php if ($fallbackText !== ''): ?>
        <div class="notice">
            <strong>Fallback-Grund:</strong> <code><?= h($fallbackText) ?></code>
        </div>
    <?php endif; ?>

    <div id="liveStatusBox" class="statusBox">
        <div><strong>Live-Status:</strong> <span id="liveStatus">Lade Live-Status ...</span></div>
        <div><strong>Status-Zusammenfassung:</strong> <span id="liveStatusSummary">wird geladen ...</span></div>
        <div><strong>Letzter Snapshot:</strong> <span id="liveStatusSnapshot">noch keiner</span></div>
    </div>

    <div class="logLinks" style="margin-top:12px">
        <a class="btn secondary" href="view_log.php?file=app">App Log</a>
        <a class="btn secondary" href="view_log.php?file=trace">Player Trace Log</a>
        <a class="btn secondary" href="view_log.php?file=status">Status-Snapshots</a>
    </div>
</div>

<div class="card">
    <form action="save_settings.php" method="post" enctype="multipart/form-data">
        <h2>Allgemeine Einstellungen</h2>
        <div class="grid">
            <div class="field">
                <label>Standarddauer</label>
                <input type="number" name="defaultDuration" min="1" value="<?= (int)($config['screen']['defaultDuration'] ?? 8) ?>">
            </div>
            <div class="field">
                <label>Bild-Fade</label>
                <input type="number" step="0.1" name="defaultFade" min="0" value="<?= h((string)($config['screen']['defaultFade'] ?? 1.2)) ?>">
            </div>
            <div class="field">
                <label>Bild-Anpassung</label>
                <select name="fit">
                    <option value="contain" <?= ($config['screen']['fit'] ?? 'contain') === 'contain' ? 'selected' : '' ?>>contain</option>
                    <option value="cover" <?= ($config['screen']['fit'] ?? '') === 'cover' ? 'selected' : '' ?>>cover</option>
                </select>
            </div>
            <?= colorField('Hintergrundfarbe','background','backgroundPicker',(string)($config['screen']['background'] ?? '#ffffff')) ?>
        </div>

        <div class="sectionDivider"></div>

        <h2>Uhr</h2>
        <div class="grid">
            <div class="field">
                <label>Uhr aktiviert</label>
                <select name="clockEnabled">
                    <option value="1" <?= !empty($config['clock']['enabled']) ? 'selected' : '' ?>>Ja</option>
                    <option value="0" <?= empty($config['clock']['enabled']) ? 'selected' : '' ?>>Nein</option>
                </select>
            </div>
            <div class="field">
                <label>Uhr Dauer</label>
                <input type="number" name="clockDuration" min="1" value="<?= (int)($config['clock']['defaultDuration'] ?? 10) ?>">
            </div>
            <?= colorField('Uhr Hintergrund','clockBackground','clockBackgroundPicker',(string)($config['clock']['background'] ?? '#ffffff')) ?>
            <?= colorField('Uhr Textfarbe','clockTextColor','clockTextColorPicker',(string)($config['clock']['textColor'] ?? '#111111')) ?>
            <div class="field">
                <label>Sekunden anzeigen</label>
                <select name="clockShowSeconds">
                    <option value="1" <?= !empty($config['clock']['showSeconds']) ? 'selected' : '' ?>>Ja</option>
                    <option value="0" <?= empty($config['clock']['showSeconds']) ? 'selected' : '' ?>>Nein</option>
                </select>
            </div>
            <div class="field">
                <label>Logo für Uhr</label>
                <input type="file" name="clockLogo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
            </div>
            <div class="field">
                <label>Aktuell</label>
                <code><?= h((string)($config['clock']['logo'] ?? '')) ?></code>
            </div>
            <div class="field">
                <label>Logo-Höhe</label>
                <input type="number" name="clockLogoHeight" min="20" max="400" value="<?= (int)($config['clock']['logoHeight'] ?? 100) ?>">
            </div>
        </div>

        <div class="sectionDivider"></div>

        <details class="criticalPanel">
            <summary>Technische Watchdog-Einstellungen anzeigen</summary>
            <div class="criticalPanel__body">
                <p class="criticalHint">
                    Diese Werte steuern Neustarts, Healthchecks und automatische Schutzreaktionen. Nur ändern, wenn klar ist, was die Werte bewirken.
                </p>

                <div class="grid">
                    <div class="field">
                        <label>Watchdog aktiviert</label>
                        <select name="watchdogEnabled">
                            <option value="1" <?= !empty($config['system']['watchdogEnabled']) ? 'selected' : '' ?>>Ja</option>
                            <option value="0" <?= empty($config['system']['watchdogEnabled']) ? 'selected' : '' ?>>Nein</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>CPU Limit %</label>
                        <input type="number" name="cpuLimit" min="1" max="100" value="<?= (int)($config['system']['maxCpuPercent'] ?? 85) ?>">
                    </div>
                    <div class="field">
                        <label>RAM Limit %</label>
                        <input type="number" name="ramLimit" min="1" max="100" value="<?= (int)($config['system']['maxRamPercent'] ?? 85) ?>">
                    </div>
                    <div class="field">
                        <label>Cooldown Sekunden</label>
                        <input type="number" name="cooldownSeconds" min="30" value="<?= (int)($config['system']['restartCooldownSeconds'] ?? 180) ?>">
                    </div>
                    <div class="field">
                        <label>Max Restarts / 30 Min</label>
                        <input type="number" name="maxRestartsIn30Min" min="1" value="<?= (int)($config['system']['maxRestartsPer30Min'] ?? 3) ?>">
                    </div>
                    <div class="field">
                        <label>Consecutive Fails nötig</label>
                        <input type="number" name="requireConsecutiveFails" min="1" value="<?= (int)($config['system']['requireConsecutiveFails'] ?? 2) ?>">
                    </div>
                    <div class="field">
                        <label>Reboot nach Player-Restarts</label>
                        <input type="number" name="rebootAfterPlayerRestarts" min="1" value="<?= (int)($config['system']['rebootAfterPlayerRestarts'] ?? 2) ?>">
                    </div>
                    <div class="field">
                        <label>Apache Healthcheck</label>
                        <select name="apacheHealthcheck">
                            <option value="1" <?= !empty($config['system']['apacheHealthcheck']) ? 'selected' : '' ?>>Ja</option>
                            <option value="0" <?= empty($config['system']['apacheHealthcheck']) ? 'selected' : '' ?>>Nein</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Apache URL</label>
                        <input type="text" name="apacheUrl" value="<?= h((string)($config['system']['apacheUrl'] ?? 'http://127.0.0.1/infoscreen2/index.php')) ?>">
                    </div>
                    <div class="field">
                        <label>Apache Timeout Sekunden</label>
                        <input type="number" name="apacheTimeoutSeconds" min="1" value="<?= (int)($config['system']['apacheTimeoutSeconds'] ?? 8) ?>">
                    </div>
                    <div class="field">
                        <label>Apache im Fallback stoppen</label>
                        <select name="stopApacheInFallback">
                            <option value="1" <?= !empty($config['system']['stopApacheInFallback']) ? 'selected' : '' ?>>Ja</option>
                            <option value="0" <?= empty($config['system']['stopApacheInFallback']) ? 'selected' : '' ?>>Nein</option>
                        </select>
                    </div>
                </div>
            </div>
        </details>

        <div class="formActions">
            <button type="submit">Einstellungen speichern</button>
        </div>
    </form>
</div>
<div class="card">
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <h2>Neue Datei hochladen</h2>
        <div class="grid">
            <div class="field">
                <label>Datei</label>
                <input type="file" name="mediaFile" required>
            </div>
            <div class="field">
                <label>Titel</label>
                <input type="text" name="title" required>
            </div>
            <div class="field">
                <label>Typ</label>
                <select name="type">
                    <option value="image">Bild</option>
                    <option value="video">Video</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
            <div class="field">
                <label>Dauer</label>
                <input type="number" name="duration" min="1" value="10">
            </div>
            <div class="field">
                <label>Aktiviert</label>
                <select name="enabled">
                    <option value="1" selected>Ja</option>
                    <option value="0">Nein</option>
                </select>
            </div>
            <div class="field">
                <label>Bei Video: stumm</label>
                <select name="muted">
                    <option value="1" selected>Ja</option>
                    <option value="0">Nein</option>
                </select>
            </div>
            <div class="field">
                <label><input type="checkbox" name="hasValidity" value="1" data-validity-toggle> Gültigkeit aktiv</label>
            </div>
        </div>

        <div class="validityBox" data-validity-fields>
            <div class="validityBox__header">Gültigkeitszeitraum</div>
            <div class="grid grid--compact">
                <div class="field">
                    <label>Gültig von</label>
                    <input type="date" name="validFrom" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="field">
                    <label>Gültig bis</label>
                    <input type="date" name="validUntil" value="<?= date('Y-m-d', strtotime('+10 years')) ?>">
                </div>
            </div>
        </div>

        <p class="small uploadNote">PDF-Dateien werden beim Upload automatisch in Bildseiten umgewandelt.</p>
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
            <div class="field">
                <label>Titel</label>
                <input type="text" name="title" required>
            </div>
            <div class="field">
                <label>URL</label>
                <input type="url" name="url" required>
            </div>
            <div class="field">
                <label>Dauer</label>
                <input type="number" name="duration" min="1" value="10">
            </div>
            <div class="field">
                <label>Neuladen nach Sekunden</label>
                <input type="number" name="refreshSeconds" min="0" value="0">
            </div>
            <div class="field">
                <label>Timeout Sekunden</label>
                <input type="number" name="timeoutSeconds" min="1" value="8">
            </div>
            <div class="field">
                <label>Aktiviert</label>
                <select name="enabled">
                    <option value="1" selected>Ja</option>
                    <option value="0">Nein</option>
                </select>
            </div>
            <div class="field">
                <label><input type="checkbox" name="hasValidity" value="1" data-validity-toggle> Gültigkeit aktiv</label>
            </div>
        </div>

        <div class="validityBox" data-validity-fields>
            <div class="validityBox__header">Gültigkeitszeitraum</div>
            <div class="grid grid--compact">
                <div class="field">
                    <label>Gültig von</label>
                    <input type="date" name="validFrom" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="field">
                    <label>Gültig bis</label>
                    <input type="date" name="validUntil" value="<?= date('Y-m-d', strtotime('+10 years')) ?>">
                </div>
            </div>
        </div>

        <div class="formActions">
            <button type="submit">Webseiten-Folie speichern</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="cardHeader">
        <div>
            <h2>Playlist</h2>
            <p class="small">Aktive und inaktive Inhalte. Bearbeiten öffnet die Detailfelder direkt unter dem jeweiligen Eintrag.</p>
        </div>
    </div>

    <div class="playlistTableWrap">
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
                        $infoParts[] = 'Quelle: <span class="pathText">' . h((string)$item['file']) . '</span>';
                    }
                    if (!empty($item['url'])) {
                        $infoParts[] = 'URL: <span class="pathText">' . h((string)$item['url']) . '</span>';
                    }
                    if (isset($item['page'])) {
                        $infoParts[] = 'Seite: ' . h((string)$item['page']);
                    }
                    if (!empty($item['hasValidity'])) {
                        $infoParts[] = 'Gültig: ' . h((string)($item['validFrom'] ?? '')) . ' bis ' . h((string)($item['validUntil'] ?? ''));
                    }
                ?>
                <tr>
                    <td><?= (int)($item['sort'] ?? 0) ?></td>
                    <td><strong><?= h($title) ?></strong></td>
                    <td><?= h($typeLabel) ?></td>
                    <td><span class="badge <?= $enabled ? 'ok' : 'off' ?>"><?= $enabled ? 'aktiv' : 'inaktiv' ?></span></td>
                    <td class="infoCell"><?= implode('<br>', $infoParts) ?></td>
                    <td>
                        <div class="playlistActions">
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
                            <?php if (!empty($item['file'])): ?>
                                <a class="btn secondary" href="download_slide.php?id=<?= rawurlencode($id) ?>">Download</a>
                            <?php endif; ?>
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
                        <div class="editPanel">
                            <form action="update_slide.php" method="post">
                                <input type="hidden" name="id" value="<?= h($id) ?>">
                                <div class="grid">
                                    <div class="field">
                                        <label>Titel</label>
                                        <input type="text" name="title" value="<?= h((string)($item['title'] ?? '')) ?>">
                                    </div>
                                    <div class="field">
                                        <label>Dauer</label>
                                        <input type="number" min="1" name="duration" value="<?= (int)($item['duration'] ?? 10) ?>">
                                    </div>
                                    <div class="field">
                                        <label>Aktiv</label>
                                        <select name="enabled">
                                            <option value="1" <?= $enabled ? 'selected' : '' ?>>Ja</option>
                                            <option value="0" <?= !$enabled ? 'selected' : '' ?>>Nein</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Fit</label>
                                        <select name="fit">
                                            <option value="contain" <?= (($item['fit'] ?? 'contain') === 'contain') ? 'selected' : '' ?>>contain</option>
                                            <option value="cover" <?= (($item['fit'] ?? '') === 'cover') ? 'selected' : '' ?>>cover</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Fade</label>
                                        <input type="number" step="0.1" min="0" name="fade" value="<?= h((string)($item['fade'] ?? ($config['screen']['defaultFade'] ?? 1.2))) ?>">
                                    </div>
                                    <div class="field">
                                        <label>Video-Modus</label>
                                        <select name="videoMode">
                                            <option value="until_end" <?= (($item['videoMode'] ?? 'until_end') === 'until_end') ? 'selected' : '' ?>>bis Ende</option>
                                            <option value="fixed_duration" <?= (($item['videoMode'] ?? '') === 'fixed_duration' || ($item['videoMode'] ?? '') === 'fixed') ? 'selected' : '' ?>>feste Dauer</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Stumm</label>
                                        <select name="muted">
                                            <option value="1" <?= !isset($item['muted']) || !empty($item['muted']) ? 'selected' : '' ?>>Ja</option>
                                            <option value="0" <?= isset($item['muted']) && empty($item['muted']) ? 'selected' : '' ?>>Nein</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>URL</label>
                                        <input type="url" name="url" value="<?= h((string)($item['url'] ?? '')) ?>">
                                    </div>
                                    <div class="field">
                                        <label>Refresh Sekunden</label>
                                        <input type="number" min="0" name="refreshSeconds" value="<?= (int)($item['refreshSeconds'] ?? 0) ?>">
                                    </div>
                                    <div class="field">
                                        <label>Timeout Sekunden</label>
                                        <input type="number" min="1" name="timeoutSeconds" value="<?= (int)($item['timeout'] ?? 8) ?>">
                                    </div>
                                    <div class="field">
                                        <label>
                                            <input type="checkbox" name="hasValidity" value="1" data-validity-toggle <?= !empty($item['hasValidity']) ? 'checked' : '' ?>>
                                            Gültigkeit aktiv
                                        </label>
                                    </div>
                                </div>

                                <div class="validityBox" data-validity-fields>
                                    <div class="validityBox__header">Gültigkeitszeitraum</div>
                                    <div class="grid grid--compact">
                                        <div class="field">
                                            <label>Gültig von</label>
                                            <input type="date" name="validFrom" value="<?= h((string)($item['validFrom'] ?? date('Y-m-d'))) ?>">
                                        </div>
                                        <div class="field">
                                            <label>Gültig bis</label>
                                            <input type="date" name="validUntil" value="<?= h((string)($item['validUntil'] ?? date('Y-m-d', strtotime('+10 years')))) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="formActions">
                                    <button type="submit">Änderungen speichern</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
function toggleEdit(id){
    const row = document.getElementById(id);
    if (!row) return;
    row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
}

async function loadLiveStatus(){
    const target = document.getElementById('liveStatus');
    const summary = document.getElementById('liveStatusSummary');
    const snapshot = document.getElementById('liveStatusSnapshot');
    if (!target || !summary || !snapshot) return;

    try {
        const response = await fetch('status.php', {cache:'no-store'});
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
            ' | Ansicht: ' + (data.requested_view || 'index') +
            ' | Letzte Aktion: ' + (data.last_action || 'none');

        summary.textContent = data.status_summary || 'keine Zusammenfassung vorhanden';
        snapshot.textContent = data.last_status_snapshot_time || 'noch kein Snapshot';
    } catch (error) {
        target.textContent = 'Live-Status konnte nicht geladen werden.';
        summary.textContent = 'keine Zusammenfassung verfügbar';
        snapshot.textContent = 'kein Snapshot';
    }
}

function bindColorFields(){
    document.querySelectorAll('.colorField').forEach((field) => {
        const text = field.querySelector('[data-color-text]');
        const picker = field.querySelector('[data-color-picker]');
        if (!text || !picker) return;

        const normalize = (value) => {
            const trimmed = String(value || '').trim();
            return /^#[0-9a-fA-F]{6}$/.test(trimmed) ? trimmed : null;
        };

        const syncFromText = () => {
            const normalized = normalize(text.value);
            if (normalized) picker.value = normalized;
        };

        const syncFromPicker = () => {
            text.value = picker.value;
        };

        text.addEventListener('input', syncFromText);
        picker.addEventListener('input', syncFromPicker);
        syncFromText();
    });
}

function bindConfirmForms(){
    document.querySelectorAll('.confirmForm').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Wirklich ausführen?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}

function bindValidityFields(){
    document.querySelectorAll('form').forEach((form) => {
        const toggle = form.querySelector('[data-validity-toggle]');
        const fields = form.querySelector('[data-validity-fields]');
        if (!toggle || !fields) return;

        const update = () => {
            fields.style.display = toggle.checked ? 'block' : 'none';
        };

        toggle.addEventListener('change', update);
        update();
    });
}

function bindActionCooldowns(){
    document.querySelectorAll('.restartGuard').forEach((button) => {
        const form = button.closest('form');
        if (!form) return;

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            const seconds = parseInt(button.getAttribute('data-lock-seconds') || '30', 10);
            if (!Number.isFinite(seconds) || seconds < 1) return;

            button.disabled = true;
            button.dataset.originalText = button.textContent;
            let remaining = seconds;
            button.textContent = (button.dataset.originalText || 'Aktion') + ' (' + remaining + 's)';

            const timer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(timer);
                    button.disabled = false;
                    button.textContent = button.dataset.originalText || 'Aktion';
                    return;
                }
                button.textContent = (button.dataset.originalText || 'Aktion') + ' (' + remaining + 's)';
            }, 1000);
        });
    });
}

loadLiveStatus();
bindColorFields();
bindConfirmForms();
bindValidityFields();
bindActionCooldowns();
setInterval(loadLiveStatus, 15000);
</script>
</body>
</html>
