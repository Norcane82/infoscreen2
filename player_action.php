<?php
require_once __DIR__ . '/functions.php';

$action = (string)($_POST['action'] ?? '');

if ($action === 'restart_player') {
    appendLog('watchdog.log', 'Manuelle Aktion: Player-Neustart');
    @shell_exec('/usr/local/bin/infoscreen2-restart-player.sh >/dev/null 2>&1 &');
    header('Location: admin.php');
    exit;
}

if ($action === 'fallback_on') {
    @file_put_contents(
        __DIR__ . '/cache/fallback_active.flag',
        date('Y-m-d H:i:s') . ' Manuell aktiviert' . PHP_EOL
    );

    $state = readJsonFile(getRestartLogPath(), [
        'last_restart' => 0,
        'restarts' => [],
        'fallback_active' => false,
        'consecutive_failures' => 0,
        'last_action' => 'none'
    ]);

    $state['fallback_active'] = true;
    $state['last_action'] = 'manual_fallback_on';

    writeJsonFile(getRestartLogPath(), $state);
    appendLog('watchdog.log', 'Manuelle Aktion: Fallback aktiviert');
    @shell_exec('/usr/local/bin/infoscreen2-restart-player.sh >/dev/null 2>&1 &');

    header('Location: admin.php');
    exit;
}

if ($action === 'fallback_off') {
    @unlink(__DIR__ . '/cache/fallback_active.flag');

    $state = readJsonFile(getRestartLogPath(), [
        'last_restart' => 0,
        'restarts' => [],
        'fallback_active' => false,
        'consecutive_failures' => 0,
        'last_action' => 'none'
    ]);

    $state['fallback_active'] = false;
    $state['consecutive_failures'] = 0;
    $state['last_action'] = 'manual_fallback_off';

    writeJsonFile(getRestartLogPath(), $state);
    appendLog('watchdog.log', 'Manuelle Aktion: Fallback deaktiviert');
    @shell_exec('/usr/local/bin/infoscreen2-restart-player.sh >/dev/null 2>&1 &');

    header('Location: admin.php');
    exit;
}

header('Location: admin.php');
exit;
