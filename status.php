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

function load_health_state_status(): array
{
    return read_json_file(HEALTH_FILE, [
        'last_restart' => 0,
        'restarts' => [],
        'fallback_active' => false,
        'consecutive_failures' => 0,
        'last_action' => 'none',
        'requested_view' => 'index',
        'reload_requested_at' => 0,
        'last_status_snapshot_hash' => '',
        'last_status_snapshot_time' => 0,
    ]);
}

function save_health_state_status(array $state): void
{
    write_json_file(HEALTH_FILE, $state);
}

function build_status_summary(array $payload): string
{
    return sprintf(
        'Player=%s | Apache=%s | Fallback=%s | View=%s | Slides=%d | LastAction=%s | ReloadAt=%d',
        !empty($payload['player_running']) ? 'läuft' : 'steht',
        !empty($payload['apache_running']) ? 'läuft' : 'steht',
        !empty($payload['fallback_active']) ? 'aktiv' : 'normal',
        (string)($payload['requested_view'] ?? 'index'),
        (int)($payload['enabled_slides'] ?? 0),
        (string)($payload['last_action'] ?? 'none'),
        (int)($payload['reload_requested_at'] ?? 0)
    );
}

$config = load_config();
$playlist = playlist_load_normalized();
$state = load_health_state_status();

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

$payload = [
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
];

$payload['status_summary'] = build_status_summary($payload);

$hashInput = [
    'fallback_active' => $payload['fallback_active'],
    'fallback_reason' => $payload['fallback_reason'],
    'last_action' => $payload['last_action'],
    'apache_running' => $payload['apache_running'],
    'player_running' => $payload['player_running'],
    'enabled_slides' => $payload['enabled_slides'],
    'requested_view' => $payload['requested_view'],
    'reload_requested_at' => $payload['reload_requested_at'],
];
$statusHash = sha1(json_encode($hashInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
$lastSnapshotHash = (string)($state['last_status_snapshot_hash'] ?? '');
$lastSnapshotTime = (int)($state['last_status_snapshot_time'] ?? 0);
$now = time();

if ($statusHash !== $lastSnapshotHash || ($now - $lastSnapshotTime) >= 300) {
    if (function_exists('app_log')) {
        app_log('info', 'Status snapshot', [
            'summary' => $payload['status_summary'],
            'player_running' => $payload['player_running'],
            'apache_running' => $payload['apache_running'],
            'fallback_active' => $payload['fallback_active'],
            'requested_view' => $payload['requested_view'],
            'enabled_slides' => $payload['enabled_slides'],
            'last_action' => $payload['last_action'],
            'reload_requested_at' => $payload['reload_requested_at'],
            'playlist_mtime' => $payload['playlist_mtime'],
            'watchdog_enabled' => $payload['watchdog_enabled'],
            'consecutive_failures' => $payload['consecutive_failures'],
            'restart_count_30m' => $payload['restart_count_30m'],
        ]);
    }

    $state['last_status_snapshot_hash'] = $statusHash;
    $state['last_status_snapshot_time'] = $now;
    save_health_state_status($state);
    $lastSnapshotTime = $now;
}

$payload['last_status_snapshot_time'] = $lastSnapshotTime > 0
    ? date('Y-m-d H:i:s', $lastSnapshotTime)
    : '';

header('Content-Type: application/json; charset=utf-8');

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
