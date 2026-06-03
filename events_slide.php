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
$activeCount = count($visibleEvents);
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
    padding:clamp(30px,3.5vw,58px);
    display:grid;
    grid-template-rows:auto 1fr auto;
    gap:clamp(18px,2vw,30px);
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
    font-size:clamp(44px,5.3vw,92px);
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
    grid-template-columns:1fr;
    gap:clamp(14px,1.6vw,24px);
    align-content:stretch;
}
.events.count-1{
    grid-template-rows:1fr;
}
.events.count-2{
    grid-template-rows:repeat(2,1fr);
}
.events.count-3{
    grid-template-rows:repeat(3,1fr);
}
.eventCard{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:28px;
    padding:clamp(18px,2vw,34px);
    box-shadow:0 18px 44px rgba(15,23,42,.14);
    display:grid;
    grid-template-columns:minmax(210px,.34fr) minmax(0,1fr);
    gap:clamp(18px,2.4vw,42px);
    align-items:start;
    min-height:0;
    overflow:hidden;
}
.eventMeta{
    border-right:1px solid rgba(0,0,0,.16);
    padding-right:clamp(14px,2vw,30px);
}
.eventDate{
    color:var(--accent2);
    font-size:clamp(26px,2.8vw,52px);
    line-height:1.05;
    font-weight:900;
    margin-bottom:8px;
}
.eventTime{
    color:var(--accent);
    font-size:clamp(19px,1.8vw,32px);
    font-weight:900;
    margin-bottom:clamp(14px,1.6vw,24px);
}
.eventLocation{
    color:var(--muted);
    font-size:clamp(18px,1.45vw,28px);
    font-weight:800;
    line-height:1.25;
}
.eventContent{
    min-width:0;
    overflow:hidden;
}
.eventTitle{
    color:var(--text);
    font-size:clamp(28px,3.2vw,58px);
    line-height:1.08;
    font-weight:900;
    margin-bottom:clamp(12px,1.5vw,22px);
}
.eventDescription{
    color:var(--text);
    font-size:clamp(17px,1.35vw,25px);
    line-height:1.32;
    overflow:hidden;
}
.events.count-1 .eventDescription{
    font-size:clamp(20px,1.55vw,30px);
    line-height:1.36;
}
.events.count-2 .eventTitle{
    font-size:clamp(26px,2.6vw,48px);
}
.events.count-2 .eventDescription{
    font-size:clamp(16px,1.22vw,23px);
}
.events.count-3 .eventCard{
    padding:clamp(14px,1.5vw,26px);
}
.events.count-3 .eventTitle{
    font-size:clamp(22px,2.1vw,38px);
    margin-bottom:10px;
}
.events.count-3 .eventDescription{
    font-size:clamp(14px,1.08vw,20px);
    line-height:1.24;
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
    .eventCard{
        grid-template-columns:1fr;
    }
    .eventMeta{
        border-right:0;
        border-bottom:1px solid rgba(0,0,0,.16);
        padding-right:0;
        padding-bottom:14px;
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

    <section class="events count-<?= (int)$activeCount ?>">
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
                    <div class="eventMeta">
                        <div class="eventDate"><?= es_h(es_date_label($date)) ?></div>
                        <?php if ($time !== ''): ?>
                            <div class="eventTime"><?= es_h($time) ?></div>
                        <?php endif; ?>
                        <?php if ($location !== ''): ?>
                            <div class="eventLocation"><?= es_h($location) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="eventContent">
                        <div class="eventTitle" style="color:<?= es_h($eventTitleColor) ?>;font-weight:<?= $eventTitleBold ? '900' : '500' ?>"><?= es_h($eventTitle !== '' ? $eventTitle : 'Veranstaltung') ?></div>
                        <?php if ($description !== ''): ?>
                            <div class="eventDescription"><?= nl2br(es_h($description)) ?></div>
                        <?php endif; ?>
                    </div>
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
