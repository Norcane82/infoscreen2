<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function is_process_running(string $pattern): bool
{
    $pattern = trim($pattern);
    if ($pattern === '') {
        return false;
    }

    $output = @shell_exec('pgrep -af ' . escapeshellarg($pattern) . ' 2>/dev/null');
    return is_string($output) && trim($output) !== '';
}

$config = load_config();
$playlist = playlist_load_normalized();

$state = read_json_file(HEALTH_FILE, [
    'last_restart' => 0,
    'restarts' => [],
    'fallback_active' => false,
    'consecutive_failures' => 0,
    'last_action' => 'none',
    'requested_view' => 'index',
    'reload_requested_at' => 0,
]);

$fallbackFile = __DIR__ . '/cache/fallback_active.flag';
$fallbackReason = is_file($fallbackFile)
    ? trim((string)file_get_contents($fallbackFile))
    : '';

$lastLog = '';
if (is_file(LOG_FILE)) {
    $lines = @file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines) && $lines !== []) {
        $lastLog = (string)$lines[count($lines) - 1];
    }
}

$enabledCount = 0;
foreach (($playlist['slides'] ?? []) as $item) {
    if (!empty($item['enabled'])) {
        $enabledCount++;
    }
}

$apacheRunning = false;
$apacheOut = @shell_exec('systemctl is-active apache2 2>/dev/null');
if (is_string($apacheOut) && trim($apacheOut) === 'active') {
    $apacheRunning = true;
}

$playerRunning = (
    is_process_running('infoscreen2') ||
    is_process_running('chromium-browser') ||
    is_process_running('chromium')
);

$requestedView = (string)($state['requested_view'] ?? 'index');
if (!in_array($requestedView, ['index', 'fallback'], true)) {
    $requestedView = !empty($state['fallback_active']) ? 'fallback' : 'index';
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'time' => date('Y-m-d H:i:s'),
    'fallback_active' => !empty($state['fallback_active']),
    'fallback_reason' => $fallbackReason,
    'last_action' => (string)($state['last_action'] ?? 'none'),
    'consecutive_failures' => (int)($state['consecutive_failures'] ?? 0),
    'restart_count_30m' => count((array)($state['restarts'] ?? [])),
    'last_restart' => (int)($state['last_restart'] ?? 0),
    'apache_running' => $apacheRunning,
    'player_running' => $playerRunning,
    'enabled_slides' => $enabledCount,
    'watchdog_enabled' => !empty($config['system']['watchdogEnabled']),
    'last_log_line' => $lastLog,
    'requested_view' => $requestedView,
    'reload_requested_at' => (int)($state['reload_requested_at'] ?? 0),
    'playlist_mtime' => is_file(PLAYLIST_FILE) ? (int)@filemtime(PLAYLIST_FILE) : 0,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
