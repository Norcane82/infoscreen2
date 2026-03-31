<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$config = load_config();
$playlist = playlist_load_normalized();
$health = read_json_file(HEALTH_FILE, [
    'last_restart' => 0,
    'restarts' => [],
    'fallback_active' => false,
    'consecutive_failures' => 0,
    'last_action' => 'none',
    'requested_view' => 'index',
    'reload_requested_at' => 0,
]);

$slides = array_values(array_filter(
    $playlist['slides'] ?? [],
    static fn(array $slide): bool => (bool)($slide['enabled'] ?? true) && playlist_slide_is_visible($slide)
));

usort($slides, static fn(array $a, array $b): int => (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0));

$screen = $config['screen'] ?? [];
$clock = $config['clock'] ?? [];
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
];

app_log('info', 'Player loaded', ['slides_total' => count($slides)]);
$buildTs = (string) max(@filemtime(__FILE__) ?: 0, @filemtime(__DIR__ . '/assets/css/screen.css') ?: 0, @filemtime(__DIR__ . '/assets/js/player.js') ?: 0, @filemtime(__DIR__ . '/assets/runtime_sync.js') ?: 0, time());
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen 2</title>
<link rel="stylesheet" href="assets/css/screen.css?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>">
<style>html,body{cursor:none!important;overflow:hidden}*{cursor:none!important}</style>
</head>
<body>
<div id="screen-root" class="screen-root"></div>
<div id="root-overlay-diagnostic" class="root-overlay-diagnostic" aria-hidden="true"><div class="root-overlay-diagnostic__box"><div class="root-overlay-diagnostic__label">ROOT OVERLAY DIAG TEST</div><div class="root-overlay-diagnostic__subline">feste Root-Ebene · fixed · maximaler z-index</div></div></div>
<script>
window.APP_CONFIG = <?php echo json_encode($playerConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.APP_PLAYLIST = <?php echo json_encode(['version' => 2, 'slides' => $slides], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.APP_RUNTIME = { currentView: 'index', statusUrl: 'status.php', reloadRequestedAt: <?php echo (int)($health['reload_requested_at'] ?? 0); ?> };
</script>
<script src="assets/js/player.js?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="assets/runtime_sync.js?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
