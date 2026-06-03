<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$config = load_config();

$eventsFile = __DIR__ . '/data/events.json';

function es_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function es_read_json(string $file, array $fallback = []): array
{
    if (!is_file($file)) {
        return $fallback;
    }

    $decoded = json_decode((string)@file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function es_logo_url(array $config): string
{
    $logo = trim((string)($config['clock']['logo'] ?? ''));
    if ($logo === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $logo)) {
        return $logo;
    }

    return $logo;
}

function es_date_label(string $date): string
{
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }

    return date('d.m.Y', $ts);
}

function es_is_future_or_today(array $event): bool
{
    $date = trim((string)($event['date'] ?? ''));
    if ($date === '') {
        return true;
    }

    $eventTs = strtotime($date . ' 23:59:59');
    if (!$eventTs) {
        return true;
    }

    return $eventTs >= strtotime('today 00:00:00');
}

function es_clamp_opacity(mixed $value): int
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

function es_normalize_color(string $value): string
{
    $value = trim($value);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
        return strtolower($value);
    }

    return '#000000';
}

$data = es_read_json($eventsFile, [
    'title' => 'Aktuelle Veranstaltungen',
    'maxItems' => 3,
    'panelOpacity' => 85,
    'events' => [],
]);

$title = trim((string)($data['title'] ?? 'Aktuelle Veranstaltungen'));
if ($title === '') {
    $title = 'Aktuelle Veranstaltungen';
}

$maxItems = (int)($data['maxItems'] ?? 3);
if ($maxItems < 1 || $maxItems > 3) {
    $maxItems = 3;
}

$panelOpacityPercent = es_clamp_opacity($data['panelOpacity'] ?? 85);
$panelOpacity = max(0, min(1, $panelOpacityPercent / 100));

$events = is_array($data['events'] ?? null) ? $data['events'] : [];
$filtered = [];

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }

    if (empty($event['enabled'])) {
        continue;
    }

    if (!es_is_future_or_today($event)) {
        continue;
    }

    $filtered[] = $event;
}

usort($filtered, static function (array $a, array $b): int {
    $dateA = trim((string)($a['date'] ?? '9999-12-31'));
    $dateB = trim((string)($b['date'] ?? '9999-12-31'));
    $timeA = trim((string)($a['time'] ?? '23:59'));
    $timeB = trim((string)($b['time'] ?? '23:59'));

    return strcmp($dateA . ' ' . $timeA, $dateB . ' ' . $timeB);
});

$visibleEvents = array_slice($filtered, 0, $maxItems);
$logoUrl = es_logo_url($config);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= es_h($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="300">
<style>
:root{
    --bg:#ffffff;
    --card:rgba(59,130,246,<?= es_h((string)$panelOpacity) ?>);
    --line:rgba(37,99,235,.28);
    --text:#000000;
    --muted:#1f2937;
    --accent:#000000;
    --accent2:#0f172a;
}
*{
    box-sizing:border-box;
}
html,
body{
    width:100%;
    height:100%;
    margin:0;
}
body{
    font-family:Arial,Helvetica,sans-serif;
    background:var(--bg);
    color:var(--text);
    overflow:hidden;
}
.slide{
    min-height:100vh;
    padding:clamp(34px,4.5vw,72px);
    display:grid;
    grid-template-rows:auto 1fr auto;
    gap:clamp(24px,3vw,46px);
}
.header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:28px;
}
h1{
    margin:0;
    color:var(--text);
    font-size:clamp(44px,5.3vw,96px);
    line-height:1;
}
.logoBox{
    display:flex;
    justify-content:flex-end;
    align-items:flex-start;
    min-width:180px;
}
.logoBox img{
    max-width:clamp(120px,12vw,240px);
    max-height:clamp(70px,8vw,150px);
    object-fit:contain;
    filter:drop-shadow(0 10px 18px rgba(0,0,0,.18));
}
.events{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:clamp(18px,2vw,32px);
    align-items:stretch;
}
.eventCard{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:32px;
    padding:clamp(24px,3vw,44px);
    box-shadow:0 18px 44px rgba(15,23,42,.14);
    min-height:48vh;
    display:flex;
    flex-direction:column;
}
.eventDate{
    color:var(--accent2);
    font-size:clamp(24px,2.3vw,44px);
    line-height:1.1;
    font-weight:900;
    margin-bottom:8px;
}
.eventTime{
    color:var(--accent);
    font-size:clamp(18px,1.5vw,28px);
    font-weight:800;
    margin-bottom:clamp(18px,2vw,30px);
}
.eventTitle{
    color:var(--text);
    font-size:clamp(30px,3.3vw,62px);
    line-height:1.08;
    font-weight:900;
    margin-bottom:clamp(16px,2vw,28px);
}
.eventLocation{
    color:var(--muted);
    font-size:clamp(18px,1.5vw,28px);
    font-weight:700;
    margin-bottom:18px;
}
.eventDescription{
    color:var(--text);
    font-size:clamp(18px,1.45vw,28px);
    line-height:1.35;
    margin-top:auto;
}
.empty{
    grid-column:1/-1;
    background:var(--card);
    border:1px solid var(--line);
    border-radius:32px;
    min-height:48vh;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    padding:48px;
    color:var(--text);
    font-size:clamp(30px,3vw,56px);
    font-weight:800;
    box-shadow:0 18px 44px rgba(15,23,42,.14);
}
.footer{
    color:var(--muted);
    font-size:clamp(13px,1vw,18px);
    display:flex;
    justify-content:space-between;
    gap:24px;
}
@media (max-width:1000px){
    body{
        overflow:auto;
    }
    .header,
    .footer{
        flex-direction:column;
    }
    .events{
        grid-template-columns:1fr;
    }
    .eventCard{
        min-height:auto;
    }
    .logoBox{
        justify-content:flex-start;
    }
}
</style>
</head>
<body>
<main class="slide">
    <header class="header">
        <div>
            <h1><?= es_h($title) ?></h1>
        </div>
        <?php if ($logoUrl !== ''): ?>
            <div class="logoBox">
                <img src="<?= es_h($logoUrl) ?>" alt="Logo">
            </div>
        <?php endif; ?>
    </header>

    <section class="events">
        <?php if (!$visibleEvents): ?>
            <div class="empty">Derzeit sind keine aktuellen Veranstaltungen eingetragen.</div>
        <?php else: ?>
            <?php foreach ($visibleEvents as $event): ?>
                <?php
                    $date = trim((string)($event['date'] ?? ''));
                    $time = trim((string)($event['time'] ?? ''));
                    $eventTitle = trim((string)($event['title'] ?? ''));
                    $eventTitleBold = !array_key_exists('titleBold', $event) || !empty($event['titleBold']);
                    $eventTitleColor = es_normalize_color((string)($event['titleColor'] ?? '#000000'));
                    $location = trim((string)($event['location'] ?? ''));
                    $description = trim((string)($event['description'] ?? ''));
                ?>
                <article class="eventCard">
                    <div class="eventDate"><?= es_h(es_date_label($date)) ?></div>
                    <?php if ($time !== ''): ?>
                        <div class="eventTime"><?= es_h($time) ?></div>
                    <?php endif; ?>
                    <div class="eventTitle" style="color:<?= es_h($eventTitleColor) ?>;font-weight:<?= $eventTitleBold ? '900' : '500' ?>"><?= es_h($eventTitle !== '' ? $eventTitle : 'Veranstaltung') ?></div>
                    <?php if ($location !== ''): ?>
                        <div class="eventLocation"><?= es_h($location) ?></div>
                    <?php endif; ?>
                    <?php if ($description !== ''): ?>
                        <div class="eventDescription"><?= nl2br(es_h($description)) ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <footer class="footer">
        <div></div>
        <div>Stand: <?= es_h(date('d.m.Y H:i')) ?></div>
    </footer>
</main>
</body>
</html>
