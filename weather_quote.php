<?php
declare(strict_types=1);

/*
 * Infoscreen2 Wetter- und Spruch-Folie
 *
 * Nutzung als Webseiten-Folie:
 * http://127.0.0.1/infoscreen2/weather_quote.php
 *
 * Wetterdaten:
 * - Open-Meteo Forecast API
 * - lokale Cache-Datei als Fallback bei Internetproblemen
 *
 * Spruch:
 * - data/quotes.json
 * - Modus: daily, weekly oder monthly
 */

$settings = [
    'locationName' => 'Innsbruck',
    'latitude' => 47.2692,
    'longitude' => 11.4041,
    'timezone' => 'Europe/Berlin',
    'weatherCacheFile' => __DIR__ . '/data/weather_cache.json',
    'quotesFile' => __DIR__ . '/data/quotes.json',
    'weatherRefreshSeconds' => 1800,
    'httpTimeoutSeconds' => 5,
];

function wh(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function read_json_assoc(string $file, array $fallback = []): array
{
    if (!is_file($file)) {
        return $fallback;
    }

    $json = (string)@file_get_contents($file);
    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_atomic(string $file, array $data): bool
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

function weather_code_label(int $code): string
{
    $labels = [
        0 => 'Klarer Himmel',
        1 => 'Überwiegend klar',
        2 => 'Teilweise bewölkt',
        3 => 'Bewölkt',
        45 => 'Nebel',
        48 => 'Reifnebel',
        51 => 'Leichter Nieselregen',
        53 => 'Mäßiger Nieselregen',
        55 => 'Starker Nieselregen',
        56 => 'Leichter gefrierender Nieselregen',
        57 => 'Starker gefrierender Nieselregen',
        61 => 'Leichter Regen',
        63 => 'Mäßiger Regen',
        65 => 'Starker Regen',
        66 => 'Leichter gefrierender Regen',
        67 => 'Starker gefrierender Regen',
        71 => 'Leichter Schneefall',
        73 => 'Mäßiger Schneefall',
        75 => 'Starker Schneefall',
        77 => 'Schneekörner',
        80 => 'Leichte Regenschauer',
        81 => 'Mäßige Regenschauer',
        82 => 'Starke Regenschauer',
        85 => 'Leichte Schneeschauer',
        86 => 'Starke Schneeschauer',
        95 => 'Gewitter',
        96 => 'Gewitter mit leichtem Hagel',
        99 => 'Gewitter mit starkem Hagel',
    ];

    return $labels[$code] ?? 'Wetterlage unbekannt';
}

function weather_icon(int $code): string
{
    if ($code === 0) { return '☀️'; }
    if (in_array($code, [1, 2], true)) { return '🌤️'; }
    if ($code === 3) { return '☁️'; }
    if (in_array($code, [45, 48], true)) { return '🌫️'; }
    if (($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82)) { return '🌧️'; }
    if (($code >= 71 && $code <= 77) || ($code >= 85 && $code <= 86)) { return '❄️'; }
    if ($code >= 95) { return '⛈️'; }

    return '🌡️';
}

function format_number(float|int|null $value, int $decimals = 0): string
{
    if ($value === null) {
        return '–';
    }

    return number_format((float)$value, $decimals, ',', '.');
}

function fetch_weather_from_api(array $settings): array
{
    $query = http_build_query([
        'latitude' => $settings['latitude'],
        'longitude' => $settings['longitude'],
        'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m',
        'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max,sunrise,sunset',
        'timezone' => $settings['timezone'],
        'forecast_days' => 1,
    ]);

    $url = 'https://api.open-meteo.com/v1/forecast?' . $query;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => (int)$settings['httpTimeoutSeconds'],
            'header' => "Accept: application/json\r\nUser-Agent: infoscreen2-weather-quote/1.0\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || trim($raw) === '') {
        return [
            'ok' => false,
            'error' => 'Wetter-API nicht erreichbar.',
            'url' => $url,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Wetter-API lieferte keine gültigen JSON-Daten.',
            'url' => $url,
        ];
    }

    return [
        'ok' => true,
        'url' => $url,
        'data' => $decoded,
    ];
}

function load_weather(array $settings): array
{
    $cacheFile = (string)$settings['weatherCacheFile'];
    $cache = read_json_assoc($cacheFile, []);
    $now = time();

    $cacheFetchedAt = isset($cache['fetchedAt']) ? strtotime((string)$cache['fetchedAt']) : 0;
    $cacheAge = $cacheFetchedAt > 0 ? ($now - $cacheFetchedAt) : PHP_INT_MAX;
    $hasUsableCache = isset($cache['data']) && is_array($cache['data']);

    if ($hasUsableCache && $cacheAge < (int)$settings['weatherRefreshSeconds']) {
        return [
            'source' => 'cache',
            'stale' => false,
            'ageSeconds' => $cacheAge,
            'fetchedAt' => (string)($cache['fetchedAt'] ?? ''),
            'data' => $cache['data'],
            'error' => '',
        ];
    }

    $fresh = fetch_weather_from_api($settings);

    if (!empty($fresh['ok']) && isset($fresh['data']) && is_array($fresh['data'])) {
        $newCache = [
            'fetchedAt' => date('c'),
            'locationName' => $settings['locationName'],
            'latitude' => $settings['latitude'],
            'longitude' => $settings['longitude'],
            'url' => $fresh['url'] ?? '',
            'data' => $fresh['data'],
        ];

        write_json_atomic($cacheFile, $newCache);

        return [
            'source' => 'api',
            'stale' => false,
            'ageSeconds' => 0,
            'fetchedAt' => $newCache['fetchedAt'],
            'data' => $fresh['data'],
            'error' => '',
        ];
    }

    if ($hasUsableCache) {
        return [
            'source' => 'cache',
            'stale' => true,
            'ageSeconds' => $cacheAge,
            'fetchedAt' => (string)($cache['fetchedAt'] ?? ''),
            'data' => $cache['data'],
            'error' => (string)($fresh['error'] ?? 'Aktualisierung fehlgeschlagen.'),
        ];
    }

    return [
        'source' => 'none',
        'stale' => true,
        'ageSeconds' => 0,
        'fetchedAt' => '',
        'data' => [],
        'error' => (string)($fresh['error'] ?? 'Keine Wetterdaten verfügbar.'),
    ];
}

function default_quotes(): array
{
    return [
        'mode' => 'weekly',
        'quotes' => [
            ['text' => 'Kleine Schritte bringen dich weiter als perfektes Warten.', 'author' => ''],
            ['text' => 'Ruhe ist oft der beste Neustart.', 'author' => ''],
            ['text' => 'Wer klar sieht, handelt besser.', 'author' => ''],
            ['text' => 'Ein guter Tag beginnt mit einem aufmerksamen Blick.', 'author' => ''],
            ['text' => 'Verlässlichkeit entsteht durch kleine Dinge, die jeden Tag funktionieren.', 'author' => ''],
            ['text' => 'Was gepflegt wird, bleibt stark.', 'author' => ''],
            ['text' => 'Der beste Zeitpunkt für Ordnung ist, bevor es dringend wird.', 'author' => ''],
            ['text' => 'Auch ein ruhiger Fortschritt ist Fortschritt.', 'author' => ''],
            ['text' => 'Gute Lösungen sind oft die einfachen, die zuverlässig laufen.', 'author' => ''],
            ['text' => 'Ein klarer Blick spart viele unnötige Schritte.', 'author' => ''],
            ['text' => 'Stabilität entsteht nicht durch Zufall, sondern durch Aufmerksamkeit.', 'author' => ''],
            ['text' => 'Heute ist ein guter Tag, um etwas ein Stück besser zu machen.', 'author' => ''],
        ],
    ];
}

function normalize_quotes(array $config): array
{
    $fallback = default_quotes();

    $mode = strtolower((string)($config['mode'] ?? $fallback['mode']));
    if (!in_array($mode, ['daily', 'weekly', 'monthly'], true)) {
        $mode = 'weekly';
    }

    $quotes = $config['quotes'] ?? $fallback['quotes'];
    if (!is_array($quotes) || count($quotes) === 0) {
        $quotes = $fallback['quotes'];
    }

    $normalized = [];
    foreach ($quotes as $quote) {
        if (is_string($quote)) {
            $text = trim($quote);
            $author = '';
        } elseif (is_array($quote)) {
            $text = trim((string)($quote['text'] ?? ''));
            $author = trim((string)($quote['author'] ?? ''));
        } else {
            continue;
        }

        if ($text === '') {
            continue;
        }

        $normalized[] = [
            'text' => $text,
            'author' => $author,
        ];
    }

    if (!$normalized) {
        $normalized = $fallback['quotes'];
    }

    return [
        'mode' => $mode,
        'quotes' => $normalized,
    ];
}

function pick_quote(array $quoteConfig): array
{
    $quotes = $quoteConfig['quotes'];
    $count = max(1, count($quotes));
    $mode = (string)$quoteConfig['mode'];

    if ($mode === 'daily') {
        $indexBase = (int)date('z');
        $label = 'Spruch des Tages';
    } elseif ($mode === 'monthly') {
        $indexBase = ((int)date('Y') * 12) + (int)date('n');
        $label = 'Spruch des Monats';
    } else {
        $indexBase = ((int)date('o') * 53) + (int)date('W');
        $label = 'Spruch der Woche';
    }

    $quote = $quotes[$indexBase % $count];

    return [
        'label' => $label,
        'mode' => $mode,
        'text' => (string)($quote['text'] ?? ''),
        'author' => (string)($quote['author'] ?? ''),
    ];
}

function cache_age_label(int $seconds): string
{
    if ($seconds < 60) {
        return 'gerade eben';
    }

    if ($seconds < 3600) {
        return floor($seconds / 60) . ' Min.';
    }

    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' Std.';
    }

    return floor($seconds / 86400) . ' Tage';
}

$weather = load_weather($settings);
$quotesConfig = normalize_quotes(read_json_assoc((string)$settings['quotesFile'], default_quotes()));
$quote = pick_quote($quotesConfig);

$data = is_array($weather['data'] ?? null) ? $weather['data'] : [];
$current = is_array($data['current'] ?? null) ? $data['current'] : [];
$daily = is_array($data['daily'] ?? null) ? $data['daily'] : [];

$weatherCode = isset($current['weather_code']) ? (int)$current['weather_code'] : -1;
$weatherLabel = $weatherCode >= 0 ? weather_code_label($weatherCode) : 'Wetterdaten nicht verfügbar';
$weatherIcon = $weatherCode >= 0 ? weather_icon($weatherCode) : '⚠️';

$temp = isset($current['temperature_2m']) ? (float)$current['temperature_2m'] : null;
$apparentTemp = isset($current['apparent_temperature']) ? (float)$current['apparent_temperature'] : null;
$humidity = isset($current['relative_humidity_2m']) ? (int)$current['relative_humidity_2m'] : null;
$precipitation = isset($current['precipitation']) ? (float)$current['precipitation'] : null;
$wind = isset($current['wind_speed_10m']) ? (float)$current['wind_speed_10m'] : null;

$minTemp = isset($daily['temperature_2m_min'][0]) ? (float)$daily['temperature_2m_min'][0] : null;
$maxTemp = isset($daily['temperature_2m_max'][0]) ? (float)$daily['temperature_2m_max'][0] : null;
$precipProbability = isset($daily['precipitation_probability_max'][0]) ? (int)$daily['precipitation_probability_max'][0] : null;

$fetchedAt = (string)($weather['fetchedAt'] ?? '');
$ageSeconds = (int)($weather['ageSeconds'] ?? 0);
$isStale = !empty($weather['stale']);
$source = (string)($weather['source'] ?? 'none');
$error = (string)($weather['error'] ?? '');

$statusText = 'Live-Daten aktualisiert';
if ($source === 'cache' && !$isStale) {
    $statusText = 'Daten aus Zwischenspeicher';
} elseif ($source === 'cache' && $isStale) {
    $statusText = 'Offline-Fallback: letzte gespeicherte Wetterdaten';
} elseif ($source === 'none') {
    $statusText = 'Keine Wetterdaten verfügbar';
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Wetter & Spruch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="600">
<style>
:root{
    --bg1:#0f172a;
    --bg2:#1e3a5f;
    --card:rgba(255,255,255,.12);
    --line:rgba(255,255,255,.22);
    --text:#ffffff;
    --muted:rgba(255,255,255,.76);
    --accent:#bfdbfe;
    --warn:#fde68a;
}
*{box-sizing:border-box}
html,body{width:100%;height:100%;margin:0}
body{
    font-family:Arial,Helvetica,sans-serif;
    background:radial-gradient(circle at 20% 10%,rgba(96,165,250,.38),transparent 32%),
        radial-gradient(circle at 85% 18%,rgba(14,165,233,.24),transparent 28%),
        linear-gradient(135deg,var(--bg1),var(--bg2));
    color:var(--text);
    overflow:hidden;
}
.slide{
    min-height:100vh;
    display:grid;
    grid-template-rows:auto 1fr auto;
    padding:clamp(28px,4vw,64px);
    gap:clamp(22px,3vw,42px);
}
.header{display:flex;justify-content:space-between;align-items:flex-start;gap:24px}
.kicker{
    margin:0 0 8px 0;
    color:var(--accent);
    font-size:clamp(16px,1.4vw,24px);
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight:800;
}
h1{margin:0;font-size:clamp(42px,5vw,90px);line-height:1}
.status{text-align:right;color:var(--muted);font-size:clamp(15px,1.15vw,21px);line-height:1.35}
.main{display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,58px);align-items:stretch}
.weatherCard,.quoteCard{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:32px;
    padding:clamp(28px,4vw,58px);
    box-shadow:0 28px 80px rgba(0,0,0,.25);
    backdrop-filter:blur(10px);
}
.weatherTop{display:flex;align-items:center;gap:clamp(18px,3vw,44px);margin-bottom:clamp(20px,3vw,38px)}
.weatherIcon{font-size:clamp(82px,9vw,160px);line-height:1}
.temperature{font-size:clamp(76px,10vw,170px);font-weight:900;line-height:.9;letter-spacing:-.06em}
.condition{font-size:clamp(26px,3vw,54px);font-weight:800;margin:0 0 10px 0}
.feels{color:var(--muted);font-size:clamp(18px,1.6vw,28px)}
.weatherGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.metric{background:rgba(15,23,42,.28);border:1px solid rgba(255,255,255,.14);border-radius:20px;padding:18px}
.metricLabel{color:var(--muted);font-size:clamp(14px,1.1vw,20px);margin-bottom:6px}
.metricValue{font-size:clamp(22px,2.2vw,38px);font-weight:800}
.quoteCard{display:flex;flex-direction:column;justify-content:center}
.quoteLabel{color:var(--accent);text-transform:uppercase;letter-spacing:.08em;font-size:clamp(16px,1.3vw,23px);font-weight:800;margin-bottom:24px}
.quoteText{font-size:clamp(34px,4vw,74px);line-height:1.12;font-weight:800}
.quoteAuthor{margin-top:26px;color:var(--muted);font-size:clamp(18px,1.6vw,30px)}
.footer{display:flex;justify-content:space-between;gap:24px;color:var(--muted);font-size:clamp(13px,1vw,18px)}
.notice{color:var(--warn)}
@media (max-width:900px){
    body{overflow:auto}
    .slide{min-height:100vh}
    .header,.footer{flex-direction:column}
    .status{text-align:left}
    .main{grid-template-columns:1fr}
}
</style>
</head>
<body>
<main class="slide">
    <header class="header">
        <div>
            <p class="kicker">Wetter aktuell</p>
            <h1><?= wh((string)$settings['locationName']) ?></h1>
        </div>
        <div class="status">
            <div><?= wh($statusText) ?></div>
            <?php if ($fetchedAt !== ''): ?>
                <div>Stand: <?= wh(date('d.m.Y H:i', strtotime($fetchedAt) ?: time())) ?> · <?= wh(cache_age_label($ageSeconds)) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="notice"><?= wh($error) ?></div>
            <?php endif; ?>
        </div>
    </header>

    <section class="main">
        <article class="weatherCard">
            <div class="weatherTop">
                <div class="weatherIcon"><?= wh($weatherIcon) ?></div>
                <div>
                    <div class="temperature"><?= wh(format_number($temp, 0)) ?>°</div>
                    <p class="condition"><?= wh($weatherLabel) ?></p>
                    <div class="feels">Gefühlt <?= wh(format_number($apparentTemp, 0)) ?>°C</div>
                </div>
            </div>

            <div class="weatherGrid">
                <div class="metric">
                    <div class="metricLabel">Tiefst / Höchst</div>
                    <div class="metricValue"><?= wh(format_number($minTemp, 0)) ?>° / <?= wh(format_number($maxTemp, 0)) ?>°</div>
                </div>
                <div class="metric">
                    <div class="metricLabel">Luftfeuchtigkeit</div>
                    <div class="metricValue"><?= wh($humidity !== null ? (string)$humidity : '–') ?>%</div>
                </div>
                <div class="metric">
                    <div class="metricLabel">Niederschlag</div>
                    <div class="metricValue"><?= wh(format_number($precipitation, 1)) ?> mm</div>
                </div>
                <div class="metric">
                    <div class="metricLabel">Wind</div>
                    <div class="metricValue"><?= wh(format_number($wind, 0)) ?> km/h</div>
                </div>
                <div class="metric">
                    <div class="metricLabel">Regenwahrscheinlichkeit</div>
                    <div class="metricValue"><?= wh($precipProbability !== null ? (string)$precipProbability : '–') ?>%</div>
                </div>
                <div class="metric">
                    <div class="metricLabel">Modus Spruch</div>
                    <div class="metricValue"><?= wh($quote['mode']) ?></div>
                </div>
            </div>
        </article>

        <article class="quoteCard">
            <div class="quoteLabel"><?= wh($quote['label']) ?></div>
            <div class="quoteText">„<?= wh($quote['text']) ?>“</div>
            <?php if ($quote['author'] !== ''): ?>
                <div class="quoteAuthor">— <?= wh($quote['author']) ?></div>
            <?php endif; ?>
        </article>
    </section>

    <footer class="footer">
        <div>Wetterdaten: Open-Meteo.com · Zwischenspeicher: <?= wh((string)$settings['weatherRefreshSeconds']) ?> Sekunden</div>
        <div><?= $isStale ? '<span class="notice">Fallback aktiv: letzte gespeicherte Daten</span>' : 'Online-/Cache-Status normal' ?></div>
    </footer>
</main>
</body>
</html>
