<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

function redirect_admin_player_action(): void
{
    header('Location: admin.php');
    exit;
}

function load_health_state(): array
{
    return read_json_file(HEALTH_FILE, [
        'last_restart' => 0,
        'restarts' => [],
        'fallback_active' => false,
        'consecutive_failures' => 0,
        'last_action' => 'none',
        'requested_view' => 'index',
        'reload_requested_at' => 0,
    ]);
}

function save_health_state(array $state): void
{
    write_json_file(HEALTH_FILE, $state);
}

function request_player_refresh(string $requestedView, string $lastAction): void
{
    $state = load_health_state();
    $state['last_action'] = $lastAction;
    $state['requested_view'] = $requestedView;
    $state['reload_requested_at'] = time();
    save_health_state($state);

    if (function_exists('app_log')) {
        app_log('info', 'Player refresh requested', [
            'requested_view' => $requestedView,
            'last_action' => $lastAction,
        ]);
    }
}

$action = trim((string)($_POST['action'] ?? ''));
$fallbackFile = __DIR__ . '/cache/fallback_active.flag';

if ($action === 'restart_player') {
    request_player_refresh('index', 'manual_restart_player');
    redirect_admin_player_action();
}

if ($action === 'fallback_on') {
    ensure_dir(__DIR__ . '/cache');

    @file_put_contents(
        $fallbackFile,
        date('Y-m-d H:i:s') . ' Manuell aktiviert' . PHP_EOL,
        LOCK_EX
    );

    $state = load_health_state();
    $state['fallback_active'] = true;
    $state['requested_view'] = 'fallback';
    $state['reload_requested_at'] = time();
    $state['last_action'] = 'manual_fallback_on';
    save_health_state($state);

    if (function_exists('app_log')) {
        app_log('info', 'Manual fallback enabled', [
            'requested_view' => 'fallback',
        ]);
    }

    redirect_admin_player_action();
}

if ($action === 'fallback_off') {
    if (is_file($fallbackFile)) {
        @unlink($fallbackFile);
    }

    $state = load_health_state();
    $state['fallback_active'] = false;
    $state['consecutive_failures'] = 0;
    $state['requested_view'] = 'index';
    $state['reload_requested_at'] = time();
    $state['last_action'] = 'manual_fallback_off';
    save_health_state($state);

    if (function_exists('app_log')) {
        app_log('info', 'Manual fallback disabled', [
            'requested_view' => 'index',
        ]);
    }

    redirect_admin_player_action();
}

redirect_admin_player_action();
