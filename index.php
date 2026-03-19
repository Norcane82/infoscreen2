<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$baseDir = __DIR__;
$dataDir = $baseDir . '/data';

$configFile = $dataDir . '/config.json';
$playlistFile = $dataDir . '/playlist.json';

$config = [];
$playlist = [
    'version' => 1,
    'slides' => [],
];

if (is_file($configFile)) {
    $raw = file_get_contents($configFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $config = $decoded;
        }
    }
}

if (is_file($playlistFile)) {
    $raw = file_get_contents($playlistFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $playlist = $decoded;
        }
    }
}

$buildTs = (string) max(
    @filemtime(__FILE__) ?: 0,
    @filemtime($configFile) ?: 0,
    @filemtime($playlistFile) ?: 0,
    time()
);
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Infoscreen</title>
    <link rel="stylesheet" href="assets/css/screen.css?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div id="screen-root" class="screen-root">
        <div id="screen-app" class="screen-app">
            <div id="screen-stage" class="screen-stage">
                <div id="slide-layer-a" class="slide-layer slide-layer-a is-active"></div>
                <div id="slide-layer-b" class="slide-layer slide-layer-b"></div>
            </div>
        </div>
    </div>

    <!-- Echtes globales Transition-Overlay auf Root-Ebene -->
    <div id="transition-overlay-root" class="transition-overlay-root" aria-hidden="true"></div>

    <!-- optionale Root-Diagnose bleibt vorhanden, aber standardmäßig aus -->
    <div id="root-overlay-diagnostic" class="root-overlay-diagnostic" aria-hidden="true">
        <div class="root-overlay-diagnostic__box">
            <div class="root-overlay-diagnostic__label">ROOT OVERLAY DIAG TEST</div>
            <div class="root-overlay-diagnostic__subline">feste Root-Ebene · fixed · maximaler z-index</div>
        </div>
    </div>

    <script>
        window.APP_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.APP_PLAYLIST = <?php echo json_encode($playlist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ROOT_OVERLAY_DIAGNOSTIC = {
            rootOverlayTest: false,
            rootOverlayAutoRun: false,
            rootOverlayAutoRunDelayMs: 1200,
            rootOverlayAutoRunRepeatMs: 4200,
            rootOverlayShowMs: 900
        };
    </script>

    <script>
        (function () {
            const cfg = window.ROOT_OVERLAY_DIAGNOSTIC || {};
            const overlay = document.getElementById('root-overlay-diagnostic');
            if (!overlay || !cfg.rootOverlayTest) {
                return;
            }

            const showMs = Number(cfg.rootOverlayShowMs || 900);
            let hideTimer = null;

            function showOverlay(reason) {
                overlay.classList.add('is-visible');
                overlay.setAttribute('data-reason', reason || 'manual');

                if (hideTimer) {
                    clearTimeout(hideTimer);
                }

                hideTimer = window.setTimeout(() => {
                    overlay.classList.remove('is-visible');
                }, showMs);
            }

            window.rootOverlayDiagnosticShow = showOverlay;

            document.addEventListener('keydown', function (event) {
                if (event.key === 'r' || event.key === 'R') {
                    showOverlay('keyboard');
                }
            });

            window.addEventListener('load', function () {
                if (cfg.rootOverlayAutoRun) {
                    window.setTimeout(() => showOverlay('autorun-1'), Number(cfg.rootOverlayAutoRunDelayMs || 1200));
                    window.setTimeout(() => showOverlay('autorun-2'), Number(cfg.rootOverlayAutoRunRepeatMs || 4200));
                }
            });
        })();
    </script>

    <script src="assets/js/player.js?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
