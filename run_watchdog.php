<?php
require_once __DIR__ . '/functions.php';

appendLog('watchdog.log', 'Manuelle Aktion: Watchdog jetzt ausführen');
@shell_exec('/usr/bin/php ' . escapeshellarg(__DIR__ . '/watchdog.php') . ' >/dev/null 2>&1');

header('Location: admin.php');
exit;
