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

app_log('info', 'Player loaded', [
    'slides_total' => count($slides),
]);

$buildTs = (string) max(
    @filemtime(__FILE__) ?: 0,
    @filemtime(__DIR__ . '/assets/css/screen.css') ?: 0,
    @filemtime(__DIR__ . '/assets/js/player.js') ?: 0,
    time()
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Infoscreen 2</title>
    <link rel="stylesheet" href="assets/css/screen.css?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div id="screen-root" class="screen-root">
        <div id="screen-app" class="screen-app">
            <div id="screen-stage" class="screen-stage">
                <div id="slide-layer-a" class="slide-layer slide-layer-a"></div>
                <div id="slide-layer-b" class="slide-layer slide-layer-b"></div>
            </div>
        </div>
    </div>

    <div id="transition-overlay-root" class="transition-overlay-root" aria-hidden="true"></div>

    <div id="root-overlay-diagnostic" class="root-overlay-diagnostic" aria-hidden="true">
        <div class="root-overlay-diagnostic__box">
            <div class="root-overlay-diagnostic__label">ROOT OVERLAY DIAG TEST</div>
            <div class="root-overlay-diagnostic__subline">feste Root-Ebene · fixed · maximaler z-index</div>
        </div>
    </div>

    <script>
        window.APP_CONFIG = <?php
        echo json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ?>;
        window.APP_PLAYLIST = <?php
        echo json_encode($playlist, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ?>;
    </script>
    <script src="assets/js/player.js?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
