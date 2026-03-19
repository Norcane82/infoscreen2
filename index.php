<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$config = load_config();
$playlist = playlist_load_normalized();

$slides = array_values(array_filter(
    $playlist['slides'] ?? [],
    static fn(array $slide): bool => (bool)($slide['enabled'] ?? true)
));

usort($slides, static function (array $a, array $b): int {
    return (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0);
});

$screen = $config['screen'] ?? [];
$clock = $config['clock'] ?? [];
$website = $config['website'] ?? [];

$playerConfig = [
    'screen' => [
        'defaultDuration' => (float)($screen['defaultDuration'] ?? 8),
        'defaultFade' => (float)($screen['defaultFade'] ?? 1),
        'background' => (string)($screen['background'] ?? '#ffffff'),
        'fit' => (string)($screen['fit'] ?? 'contain'),
    ],
    'clock' => [
        'enabled' => (bool)($clock['enabled'] ?? true),
        'defaultDuration' => (float)($clock['defaultDuration'] ?? 10),
        'timezone' => (string)($clock['timezone'] ?? 'Europe/Vienna'),
        'background' => (string)($clock['background'] ?? '#ffffff'),
        'textColor' => (string)($clock['textColor'] ?? '#111111'),
        'showSeconds' => (bool)($clock['showSeconds'] ?? false),
        'logo' => (string)($clock['logo'] ?? ''),
        'logoHeight' => (int)($clock['logoHeight'] ?? 100),
    ],
    'website' => [
        'timeout' => (float)($website['timeout'] ?? 8),
    ],
];

app_log('info', 'Player loaded', [
    'slides_total' => count($slides),
]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen 2</title>
<link rel="stylesheet" href="assets/css/screen.css">
</head>
<body>
<div id="screen" class="screen">
    <div id="slide-stage" class="slide-stage"></div>
</div>

<div id="transition-overlay-root" class="transition-overlay-root" aria-hidden="true"></div>

<script>
window.INFOSCREEN_CONFIG = <?php
echo json_encode($playerConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>;
window.INFOSCREEN_SLIDES = <?php
echo json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="assets/js/player.js"></script>
</body>
</html>
