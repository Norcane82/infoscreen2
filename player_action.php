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
    ]);
}

function save_health_state(array $state): void
{
    write_json_file(HEALTH_FILE, $state);
}

function restart_player_process(): void
{
    @shell_exec('/usr/local/bin/infoscreen2-restart-player.sh >/dev/null 2>&1 &');
}

$action = trim((string)($_POST['action'] ?? ''));
$fallbackFile = __DIR__ . '/cache/fallback_active.flag';

if ($action === 'restart_player') {
    $state = load_health_state();
    $state['last_action'] = 'manual_restart_player';
    save_health_state($state);

    if (function_exists('log_message')) {
        log_message('INFO', 'Manual action: player restart');
    }

    restart_player_process();
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
    $state['last_action'] = 'manual_fallback_on';
    save_health_state($state);

    if (function_exists('log_message')) {
        log_message('INFO', 'Manual action: fallback enabled');
    }

    restart_player_process();
    redirect_admin_player_action();
}

if ($action === 'fallback_off') {
    if (is_file($fallbackFile)) {
        @unlink($fallbackFile);
    }

    $state = load_health_state();
    $state['fallback_active'] = false;
    $state['consecutive_failures'] = 0;
    $state['last_action'] = 'manual_fallback_off';
    save_health_state($state);

    if (function_exists('log_message')) {
        log_message('INFO', 'Manual action: fallback disabled');
    }

    restart_player_process();
    redirect_admin_player_action();
}

redirect_admin_player_action();
