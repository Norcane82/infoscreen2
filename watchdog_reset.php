<?php
require_once __DIR__ . '/functions.php';

writeJsonFile(getRestartLogPath(), [
    'last_restart' => 0,
    'restarts' => [],
    'fallback_active' => false
]);

@unlink(__DIR__ . '/cache/fallback_active.flag');
appendLog('watchdog.log', 'Watchdog manuell zurückgesetzt');

header('Location: admin.php');
exit;
