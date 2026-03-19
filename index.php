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

$buildTs = (string) @filemtime(__FILE__);
if ($buildTs === '' || $buildTs === '0') {
    $buildTs = (string) time();
}

/*
 * Root-Overlay-Diagnosetest:
 * - absichtlich fest in index.php auf Root-Ebene verankert
 * - komplett außerhalb der eigentlichen Slide-Struktur
 * - per Tastatur "R" manuell testbar
 * - beim Start automatisch 2x sichtbar
 *
 * Ziel:
 * prüfen, ob ein Overlay mit fixed + extrem hohem z-index überhaupt sichtbar gerendert wird.
 */

$diagnosticConfig = [
    'rootOverlayTest' => true,
    'rootOverlayAutoRun' => true,
    'rootOverlayAutoRunDelayMs' => 1200,
    'rootOverlayAutoRunRepeatMs' => 4200,
    'rootOverlayShowMs' => 900,
    'rootOverlayLabel' => 'ROOT OVERLAY DIAG TEST',
];
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
                <div id="slide-layer-a" class="slide-layer slide-layer-a"></div>
                <div id="slide-layer-b" class="slide-layer slide-layer-b"></div>
            </div>
        </div>
    </div>

    <!--
        ROOT-OVERLAY-DIAGNOSE
        Dieses Element sitzt direkt unter body und NICHT innerhalb der Slide-Layer.
        Genau das soll getestet werden.
    -->
    <div
        id="root-overlay-diagnostic"
        class="root-overlay-diagnostic"
        aria-hidden="true"
    >
        <div class="root-overlay-diagnostic__box">
            <div class="root-overlay-diagnostic__label">
                <?php echo htmlspecialchars((string) $diagnosticConfig['rootOverlayLabel'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="root-overlay-diagnostic__subline">
                feste Root-Ebene · fixed · maximaler z-index
            </div>
        </div>
    </div>

    <script>
        window.APP_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.APP_PLAYLIST = <?php echo json_encode($playlist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ROOT_OVERLAY_DIAGNOSTIC = <?php echo json_encode($diagnosticConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
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

            function trace(message) {
                try {
                    const line = '[root-overlay-diag] ' + message;
                    console.log(line);

                    /*
                     * Optional: falls dein bestehendes Logging schon einen Endpoint hat,
                     * kannst du hier denselben Endpoint verwenden.
                     * Der Test funktioniert aber auch komplett ohne Server-Logging.
                     */
                    if (navigator.sendBeacon) {
                        const blob = new Blob([line + '\n'], { type: 'text/plain' });
                        navigator.sendBeacon('player_trace.log', blob);
                    }
                } catch (err) {
                    console.warn('root-overlay trace failed', err);
                }
            }

            function showOverlay(reason) {
                overlay.classList.add('is-visible');
                overlay.setAttribute('data-reason', reason || 'manual');

                if (hideTimer) {
                    clearTimeout(hideTimer);
                }

                trace('VISIBLE ON [' + (reason || 'manual') + ']');

                hideTimer = window.setTimeout(() => {
                    overlay.classList.remove('is-visible');
                    trace('VISIBLE OFF [' + (reason || 'manual') + ']');
                }, showMs);
            }

            window.rootOverlayDiagnosticShow = showOverlay;

            document.addEventListener('keydown', function (event) {
                if (event.key === 'r' || event.key === 'R') {
                    showOverlay('keyboard');
                }
            });

            window.addEventListener('load', function () {
                trace('INIT');

                if (cfg.rootOverlayAutoRun) {
                    window.setTimeout(() => {
                        showOverlay('autorun-1');
                    }, Number(cfg.rootOverlayAutoRunDelayMs || 1200));

                    window.setTimeout(() => {
                        showOverlay('autorun-2');
                    }, Number(cfg.rootOverlayAutoRunRepeatMs || 4200));
                }
            });
        })();
    </script>

    <script src="assets/js/player.js?v=<?php echo htmlspecialchars($buildTs, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
