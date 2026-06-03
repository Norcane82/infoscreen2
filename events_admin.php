<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$eventsFile = __DIR__ . '/data/events.json';
$message = '';

function ea_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ea_read_json(string $file, array $fallback = []): array
{
    if (!is_file($file)) {
        return $fallback;
    }

    $decoded = json_decode((string)@file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function ea_write_json(string $file, array $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $tmp = $file . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }

    return @rename($tmp, $file);
}

function ea_default_data(): array
{
    return [
        'title' => 'Aktuelle Veranstaltungen',
        'maxItems' => 3,
        'panelOpacity' => 85,
        'totalDescriptionLimit' => 3000,
        'events' => [
            [
                'date' => '',
                'time' => '',
                'title' => '',
                'location' => '',
                'description' => '',
                'titleBold' => true,
                'titleColor' => '#000000',
                'enabled' => false,
            ],
            [
                'date' => '',
                'time' => '',
                'title' => '',
                'location' => '',
                'description' => '',
                'titleBold' => true,
                'titleColor' => '#000000',
                'enabled' => false,
            ],
            [
                'date' => '',
                'time' => '',
                'title' => '',
                'location' => '',
                'description' => '',
                'titleBold' => true,
                'titleColor' => '#000000',
                'enabled' => false,
            ],
        ],
    ];
}

function ea_clamp_opacity(mixed $value): int
{
    $opacity = (int)$value;

    if ($opacity < 0) {
        return 0;
    }

    if ($opacity > 100) {
        return 100;
    }

    return $opacity;
}

function ea_clamp_total_description_limit(mixed $value): int
{
    $limit = (int)$value;

    if ($limit < 300) {
        return 300;
    }

    if ($limit > 9000) {
        return 9000;
    }

    return $limit;
}

function ea_normalize_color(string $value): string
{
    $value = trim($value);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
        return strtolower($value);
    }

    return '#000000';
}

function ea_normalize_data(array $data): array
{
    $default = ea_default_data();

    $title = trim((string)($data['title'] ?? $default['title']));
    if ($title === '') {
        $title = $default['title'];
    }

    $panelOpacity = ea_clamp_opacity($data['panelOpacity'] ?? $default['panelOpacity']);
    $totalDescriptionLimit = ea_clamp_total_description_limit($data['totalDescriptionLimit'] ?? $default['totalDescriptionLimit']);

    $events = is_array($data['events'] ?? null) ? $data['events'] : [];
    $normalizedEvents = [];

    for ($i = 0; $i < 3; $i++) {
        $event = is_array($events[$i] ?? null) ? $events[$i] : [];

        $normalizedEvents[] = [
            'date' => trim((string)($event['date'] ?? '')),
            'time' => trim((string)($event['time'] ?? '')),
            'title' => trim((string)($event['title'] ?? '')),
            'location' => trim((string)($event['location'] ?? '')),
            'description' => trim((string)($event['description'] ?? '')),
            'titleBold' => !array_key_exists('titleBold', $event) || !empty($event['titleBold']),
            'titleColor' => ea_normalize_color((string)($event['titleColor'] ?? '#000000')),
            'enabled' => !empty($event['enabled']),
        ];
    }

    return [
        'title' => $title,
        'maxItems' => 3,
        'panelOpacity' => $panelOpacity,
        'totalDescriptionLimit' => $totalDescriptionLimit,
        'events' => $normalizedEvents,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $events = [];

    for ($i = 0; $i < 3; $i++) {
        $events[] = [
            'date' => trim((string)($_POST['date'][$i] ?? '')),
            'time' => trim((string)($_POST['time'][$i] ?? '')),
            'title' => trim((string)($_POST['title'][$i] ?? '')),
            'location' => trim((string)($_POST['location'][$i] ?? '')),
            'description' => trim((string)($_POST['description'][$i] ?? '')),
            'titleBold' => isset($_POST['titleBold'][$i]),
            'titleColor' => ea_normalize_color((string)($_POST['titleColor'][$i] ?? '#000000')),
            'enabled' => isset($_POST['enabled'][$i]),
        ];
    }

    $dataToSave = [
        'title' => trim((string)($_POST['pageTitle'] ?? 'Aktuelle Veranstaltungen')),
        'maxItems' => 3,
        'panelOpacity' => ea_clamp_opacity($_POST['panelOpacity'] ?? 85),
        'totalDescriptionLimit' => ea_clamp_total_description_limit($_POST['totalDescriptionLimit'] ?? 3000),
        'events' => $events,
    ];

    if ($dataToSave['title'] === '') {
        $dataToSave['title'] = 'Aktuelle Veranstaltungen';
    }

    if (ea_write_json($eventsFile, $dataToSave)) {
        $message = 'Veranstaltungen wurden gespeichert.';
    } else {
        $message = 'Fehler: Veranstaltungen konnten nicht gespeichert werden.';
    }
}

$data = ea_normalize_data(ea_read_json($eventsFile, ea_default_data()));
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Veranstaltungen verwalten</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
    --bg:#f3f5f7;
    --card:#ffffff;
    --text:#1f2933;
    --muted:#667085;
    --line:#d9dee5;
    --line-soft:#edf0f3;
    --shadow:0 8px 24px rgba(15,23,42,.08);
    --radius:16px;
    --primary:#2563eb;
    --button:#e9edf2;
    --button-hover:#dfe5ec;
    --warn:#b45309;
}
*{
    box-sizing:border-box;
}
body{
    font-family:Arial,Helvetica,sans-serif;
    margin:0;
    padding:18px;
    background:var(--bg);
    color:var(--text);
}
.layout{
    max-width:1500px;
    margin:0 auto;
}
h1{
    margin:0 0 16px 0;
}
h2{
    margin:0 0 12px 0;
}
.card{
    background:var(--card);
    border:1px solid var(--line-soft);
    border-radius:var(--radius);
    padding:20px;
    margin:0 0 18px 0;
    box-shadow:var(--shadow);
}
.topLinks,
.formActions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
    margin-bottom:14px;
}
.btn,
button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:9px 13px;
    border:0;
    border-radius:9px;
    background:var(--button);
    color:#111;
    text-decoration:none;
    cursor:pointer;
    font:inherit;
    white-space:nowrap;
}
.btn:hover,
button:hover{
    background:var(--button-hover);
}
.primary{
    background:var(--primary);
    color:#fff;
}
.primary:hover{
    background:#1d4ed8;
}
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:14px;
}
.field label,
label{
    display:block;
    font-weight:700;
    margin-bottom:6px;
}
input[type=text],
input[type=date],
input[type=time],
input[type=number],
input[type=color],
textarea{
    width:100%;
    padding:9px 10px;
    border:1px solid #cbd5df;
    border-radius:9px;
    background:#fff;
    font:inherit;
}
input[type=color]{
    height:40px;
    padding:4px;
}
textarea{
    min-height:140px;
    resize:vertical;
    line-height:1.45;
}
.notice{
    padding:10px 12px;
    background:#e8f0ff;
    border:1px solid #c8dafd;
    border-radius:12px;
    margin:0 0 16px 0;
}
.small{
    font-size:.9rem;
    color:var(--muted);
}
.eventHeader{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:center;
    margin-bottom:12px;
}
.eventNumber{
    font-weight:800;
    color:var(--primary);
}
.opacityPreview{
    min-height:42px;
    border-radius:12px;
    border:1px solid #93c5fd;
    background:rgba(59,130,246,.85);
    margin-top:8px;
}
.textareaHeader{
    display:flex;
    justify-content:space-between;
    gap:10px;
    align-items:center;
    margin-bottom:6px;
}
.charCounter{
    color:var(--muted);
    font-weight:700;
    white-space:nowrap;
}
.charCounter.is-warn{
    color:var(--warn);
}
.charCounter.is-over{
    color:#b91c1c;
}
</style>
</head>
<body>
<div class="layout">
    <div class="topLinks">
        <a class="btn" href="admin.php?page=upload">Zurück zu Neue Datei hochladen</a>
        <a class="btn" href="events_slide.php" target="_blank">Veranstaltungsfolie öffnen</a>
        <a class="btn" href="admin.php?page=master">Zur Master-Verwaltung</a>
    </div>

    <h1>Aktuelle Veranstaltungen verwalten</h1>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= ea_h($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="card">
            <h2>Allgemein</h2>
            <div class="grid">
                <div class="field">
                    <label>Titel der Folie</label>
                    <input type="text" name="pageTitle" value="<?= ea_h((string)$data['title']) ?>">
                </div>

                <div class="field">
                    <label>Maximale Anzeige</label>
                    <input type="text" value="3 Veranstaltungen" disabled>
                    <p class="small">Die Folie zeigt automatisch maximal drei aktive, nicht abgelaufene Veranstaltungen.</p>
                </div>

                <div class="field">
                    <label>Deckkraft Veranstaltungsfenster</label>
                    <input type="number" name="panelOpacity" min="0" max="100" step="1" value="<?= (int)$data['panelOpacity'] ?>">
                    <p class="small">Empfohlen: 85 %. 100 % ist vollständig deckend, 0 % ist komplett transparent.</p>
                    <div class="opacityPreview" style="background:rgba(59,130,246,<?= ((int)$data['panelOpacity']) / 100 ?>)"></div>
                </div>

                <div class="field">
                    <label>Gesamtes Zeichenbudget</label>
                    <input type="number" name="totalDescriptionLimit" min="300" max="9000" step="100" value="<?= (int)$data['totalDescriptionLimit'] ?>">
                    <p class="small">Dieses Gesamtbudget wird automatisch durch die Anzahl der aktiven Veranstaltungen geteilt.</p>
                </div>
            </div>
        </div>

        <?php foreach ($data['events'] as $i => $event): ?>
            <div class="card">
                <div class="eventHeader">
                    <h2>Veranstaltung <?= $i + 1 ?></h2>
                    <span class="eventNumber">Slot <?= $i + 1 ?> von 3</span>
                </div>

                <div class="grid">
                    <div class="field">
                        <label>Aktiv</label>
                        <label>
                            <input type="checkbox" name="enabled[<?= $i ?>]" value="1" data-event-enabled <?= !empty($event['enabled']) ? 'checked' : '' ?>>
                            anzeigen
                        </label>
                    </div>
                    <div class="field">
                        <label>Datum</label>
                        <input type="date" name="date[<?= $i ?>]" value="<?= ea_h((string)$event['date']) ?>">
                    </div>
                    <div class="field">
                        <label>Uhrzeit / Text</label>
                        <input type="text" name="time[<?= $i ?>]" value="<?= ea_h((string)$event['time']) ?>" placeholder="z. B. 09:30 oder ganztägig">
                    </div>
                    <div class="field">
                        <label>Titel</label>
                        <input type="text" name="title[<?= $i ?>]" value="<?= ea_h((string)$event['title']) ?>">
                    </div>
                    <div class="field">
                        <label>Titel fett</label>
                        <label>
                            <input type="checkbox" name="titleBold[<?= $i ?>]" value="1" <?= !empty($event['titleBold']) ? 'checked' : '' ?>>
                            Titel fett anzeigen
                        </label>
                    </div>
                    <div class="field">
                        <label>Titel-Schriftfarbe</label>
                        <input type="color" name="titleColor[<?= $i ?>]" value="<?= ea_h((string)$event['titleColor']) ?>">
                    </div>
                    <div class="field">
                        <label>Ort</label>
                        <input type="text" name="location[<?= $i ?>]" value="<?= ea_h((string)$event['location']) ?>">
                    </div>
                </div>

                <div class="field" style="margin-top:14px">
                    <div class="textareaHeader">
                        <label>Beschreibung</label>
                        <span class="charCounter" data-counter-for="description-<?= $i ?>">0 / 3000</span>
                    </div>
                    <textarea id="description-<?= $i ?>" name="description[<?= $i ?>]" data-description><?= ea_h((string)$event['description']) ?></textarea>
                    <p class="small">Das Gesamtbudget wird durch die aktiven Veranstaltungen geteilt. Beispiel bei 3000 Zeichen: 1 aktiv = 3000, 2 aktiv = 1500, 3 aktiv = 1000 Zeichen je Beschreibung.</p>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="formActions">
            <button class="primary" type="submit">Veranstaltungen speichern</button>
        </div>
    </form>
</div>

<script>
function getDescriptionLimit(){
    const activeCount = Array.from(document.querySelectorAll('[data-event-enabled]')).filter((box) => box.checked).length;
    const budgetInput = document.querySelector('[name="totalDescriptionLimit"]');
    const totalBudget = parseInt(budgetInput ? budgetInput.value : '3000', 10);
    const safeTotalBudget = Number.isFinite(totalBudget) ? Math.max(300, Math.min(9000, totalBudget)) : 3000;
    const divisor = Math.max(1, activeCount);

    return Math.floor(safeTotalBudget / divisor);
}

function updateCounters(){
    const limit = getDescriptionLimit();

    document.querySelectorAll('[data-description]').forEach((textarea) => {
        textarea.maxLength = limit;

        const counter = document.querySelector('[data-counter-for="' + textarea.id + '"]');
        if (!counter) {
            return;
        }

        const length = textarea.value.length;
        counter.textContent = length + ' / ' + limit;
        counter.classList.toggle('is-warn', length > limit * 0.85 && length <= limit);
        counter.classList.toggle('is-over', length > limit);
    });
}

document.querySelectorAll('[data-event-enabled]').forEach((box) => {
    box.addEventListener('change', updateCounters);
});

document.querySelectorAll('[data-description]').forEach((textarea) => {
    textarea.addEventListener('input', updateCounters);
});

const totalBudgetInput = document.querySelector('[name="totalDescriptionLimit"]');
if (totalBudgetInput) {
    totalBudgetInput.addEventListener('input', updateCounters);
}

updateCounters();
</script>
</body>
</html>
