<?php
require_once __DIR__ . '/functions.php';

$config = loadConfig();
$state = readJsonFile(getRestartLogPath(), [
    'last_restart' => 0,
    'restarts' => [],
    'fallback_active' => false,
    'consecutive_failures' => 0,
    'last_action' => 'none'
]);

$fallbackFile = __DIR__ . '/cache/fallback_active.flag';
$fallbackReason = is_file($fallbackFile) ? trim((string)file_get_contents($fallbackFile)) : '';

$logFile = __DIR__ . '/logs/watchdog.log';
$lastLog = '';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines && count($lines) > 0) {
        $lastLog = (string)$lines[count($lines)-1];
    }
}

function isProcessRunning(string $pattern): bool {
    $pattern = trim($pattern);
    if ($pattern === '') return false;
    $out = @shell_exec('pgrep -af ' . escapeshellarg($pattern) . ' 2>/dev/null');
    return is_string($out) && trim($out) !== '';
}
$chromiumRunning = isProcessRunning('infoscreen2');
$apacheRunning = false;
$apacheOut = @shell_exec('systemctl is-active apache2 2>/dev/null');
if (is_string($apacheOut) && trim($apacheOut) === 'active') {
    $apacheRunning = true;
}

$playlist = normalizePlaylist(loadPlaylist(), $config);
$enabledCount = 0;
foreach ($playlist as $item) {
    if (!empty($item['enabled'])) $enabledCount++;
}

jsonResponse([
    'ok' => true,
    'time' => date('Y-m-d H:i:s'),
    'fallback_active' => !empty($state['fallback_active']),
    'fallback_reason' => $fallbackReason,
    'last_action' => (string)($state['last_action'] ?? 'none'),
    'consecutive_failures' => (int)($state['consecutive_failures'] ?? 0),
    'restart_count_30m' => count((array)($state['restarts'] ?? [])),
    'last_restart' => (int)($state['last_restart'] ?? 0),
    'apache_running' => $apacheRunning,
    'player_running' => $chromiumRunning,
    'enabled_slides' => $enabledCount,
    'watchdog_enabled' => !empty($config['system']['watchdogEnabled']),
    'last_log_line' => $lastLog
]);
