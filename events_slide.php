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
    return $ts ? date('d.m.Y', $ts) : $date;
}

function es_is_future_or_today(array $event): bool
{
    $date = trim((string)($event['date'] ?? ''));
    if ($date === '') {
        return true;
    }

    $eventTs = strtotime($date . ' 23:59:59');
    return !$eventTs || $eventTs >= strtotime('today 00:00:00');
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
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : '#000000';
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
    if (!is_array($event) || empty($event['enabled']) || !es_is_future_or_today($event)) {
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
*{box-sizing:border-box}
html,body{width:100%;height:100%;margin:0}
body{
    font-family:Arial,Helvetica,sans-serif;
    background:var(--bg);
    color:var(--text);
    overflow:hidden;
}
.scaleViewport{
    position:fixed;
    inset:0;
    overflow:hidden;
    background:var(--bg);
}
.slide{
    position:absolute;
    top:0;
    left:0;
    width:1920px;
    height:1080px;
    padding:42px;
    display:grid;
    grid-template-rows:120px 1fr 36px;
    gap:22px;
    transform-origin:top left;
    background:var(--bg);
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
    font-size:72px;
    line-height:1;
    letter-spacing:-1.5px;
}
.logoBox{
    display:flex;
    justify-content:flex-end;
    align-items:flex-start;
    min-width:220px;
    padding-top:4px;
}
.logoBox img{
    max-width:220px;
    max-height:82px;
    object-fit:contain;
    filter:drop-shadow(0 10px 18px rgba(0,0,0,.18));
}
.events{
    display:grid;
    grid-template-columns:1fr;
    gap:18px;
    min-height:0;
}
.events.count-0,
.events.count-1{grid-template-rows:1fr}
.events.count-2{grid-template-rows:repeat(2,1fr)}
.events.count-3{grid-template-rows:repeat(3,1fr)}
.eventCard{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:24px;
    padding:28px 34px;
    box-shadow:0 18px 44px rgba(15,23,42,.12);
    display:flex;
    flex-direction:column;
    min-height:0;
    overflow:hidden;
}
.eventMeta{
    display:flex;
    align-items:flex-start;
    gap:30px;
    padding-bottom:18px;
    margin-bottom:18px;
    border-bottom:1px solid rgba(0,0,0,.16);
}
.eventMetaItem{
    min-width:0;
}
.eventMetaItem.date{
    min-width:250px;
}
.eventMetaItem.time{
    min-width:230px;
}
.eventDate{
    color:var(--accent2);
    font-size:44px;
    line-height:1.05;
    font-weight:900;
}
.eventTime{
    color:var(--accent);
    font-size:32px;
    line-height:1.12;
    font-weight:900;
    padding-top:6px;
}
.eventLocation{
    color:var(--muted);
    font-size:30px;
    font-weight:900;
    line-height:1.18;
    padding-top:6px;
}
.eventContent{
    min-width:0;
    overflow:hidden;
}
.eventTitle{
    color:var(--text);
    font-size:50px;
    line-height:1.08;
    font-weight:900;
    margin-bottom:18px;
}
.eventDescription{
    color:var(--text);
    font-size:26px;
    line-height:1.32;
    overflow:hidden;
}
.events.count-1 .eventTitle{
    font-size:54px;
}
.events.count-1 .eventDescription{
    font-size:28px;
    line-height:1.34;
}
.events.count-2 .eventCard{
    padding:24px 30px;
}
.events.count-2 .eventMeta{
    gap:24px;
    padding-bottom:14px;
    margin-bottom:14px;
}
.events.count-2 .eventDate{font-size:38px}
.events.count-2 .eventTime{font-size:28px}
.events.count-2 .eventLocation{font-size:26px}
.events.count-2 .eventTitle{font-size:42px;margin-bottom:12px}
.events.count-2 .eventDescription{font-size:22px;line-height:1.27}
.events.count-3{gap:14px}
.events.count-3 .eventCard{
    padding:17px 24px;
}
.events.count-3 .eventMeta{
    gap:20px;
    padding-bottom:10px;
    margin-bottom:10px;
}
.events.count-3 .eventMetaItem.date{
    min-width:190px;
}
.events.count-3 .eventMetaItem.time{
    min-width:170px;
}
.events.count-3 .eventDate{font-size:31px}
.events.count-3 .eventTime{font-size:22px;padding-top:4px}
.events.count-3 .eventLocation{font-size:21px;padding-top:4px}
.events.count-3 .eventTitle{font-size:34px;margin-bottom:8px}
.events.count-3 .eventDescription{font-size:18px;line-height:1.21}
.empty{
    grid-column:1/-1;
    background:var(--card);
    border:1px solid var(--line);
    border-radius:28px;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    padding:48px;
    color:var(--text);
    font-size:46px;
    font-weight:900;
    box-shadow:0 18px 44px rgba(15,23,42,.14);
}
.footer{
    color:var(--muted);
    font-size:17px;
    display:flex;
    justify-content:space-between;
    gap:24px;
    align-items:end;
}
</style>
</head>
<body>
<div class="scaleViewport">
<main class="slide" id="eventSlide">
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
                        <div class="eventMetaItem date">
                            <div class="eventDate"><?= es_h(es_date_label($date)) ?></div>
                        </div>

                        <?php if ($time !== ''): ?>
                            <div class="eventMetaItem time">
                                <div class="eventTime"><?= es_h($time) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($location !== ''): ?>
                            <div class="eventMetaItem location">
                                <div class="eventLocation"><?= es_h($location) ?></div>
                            </div>
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
</div>

<script>
function fitEventSlide(){
    const slide = document.getElementById('eventSlide');
    if (!slide) return;

    const designWidth = 1920;
    const designHeight = 1080;
    const scale = Math.min(window.innerWidth / designWidth, window.innerHeight / designHeight);

    slide.style.transform = 'scale(' + scale + ')';

    const visualWidth = designWidth * scale;
    const visualHeight = designHeight * scale;

    slide.style.left = Math.max(0, (window.innerWidth - visualWidth) / 2) + 'px';
    slide.style.top = Math.max(0, (window.innerHeight - visualHeight) / 2) + 'px';
}

window.addEventListener('resize', fitEventSlide);
window.addEventListener('load', fitEventSlide);
fitEventSlide();
</script>
</body>
</html>
