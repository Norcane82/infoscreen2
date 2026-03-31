<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$config = load_config();
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

$slides = playlist_disable_expired_slides($slides);
$now = playlist_today_vienna();

$visibleSlides = array_values(array_filter(
    $slides,
    static fn(array $slide): bool => playlist_slide_is_visible($slide, $now)
));

$playlistForPlayer = [
    'version' => (int)($playlistData['version'] ?? 2),
    'slides' => $visibleSlides,
];

$health = read_json_file(HEALTH_FILE, []);
$currentView = (string)($health['requested_view'] ?? 'index');
$reloadRequestedAt = (int)($health['reload_requested_at'] ?? 0);

$version = (string)@filemtime(__FILE__);
if ($version === '' || $version === '0') {
    $version = (string)time();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen 2</title>
<link rel="stylesheet" href="assets/css/screen.css?v=<?php echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?>">
<style>html,body{cursor:none!important;overflow:hidden}*{cursor:none!important}</style>
</head>
<body>
<div id="screen-root" class="screen-root">
    <div id="slide-layer-a" class="slide-layer slide-layer--contain"></div>
    <div id="slide-layer-b" class="slide-layer slide-layer--contain"></div>
</div>

<div id="root-overlay-diagnostic" class="root-overlay-diagnostic" aria-hidden="true">
    <div class="root-overlay-diagnostic__box">
        <div class="root-overlay-diagnostic__label">ROOT OVERLAY DIAG TEST</div>
        <div class="root-overlay-diagnostic__subline">feste Root-Ebene · fixed · maximaler z-index</div>
    </div>
</div>

<script>
window.APP_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.APP_PLAYLIST = <?php echo json_encode($playlistForPlayer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.APP_RUNTIME = {
    currentView: <?php echo json_encode($currentView, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
    statusUrl: 'status.php',
    reloadRequestedAt: <?php echo (int)$reloadRequestedAt; ?>
};
</script>
<script src="assets/js/player.js?v=<?php echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="assets/runtime_sync.js?v=<?php echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
