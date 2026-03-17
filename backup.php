<?php
require_once __DIR__ . '/functions.php';

$backupDir = __DIR__ . '/data/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 2775, true);
}

$file = 'infoscreen2_backup_' . date('Ymd_His') . '.tar.gz';
$full = $backupDir . '/' . $file;

$cmd = 'tar -czf ' . escapeshellarg($full)
    . ' ' . escapeshellarg(__DIR__)
    . ' ' . escapeshellarg('/usr/local/bin/infoscreen2-kiosk.sh')
    . ' ' . escapeshellarg('/usr/local/bin/infoscreen2-restart-player.sh')
    . ' ' . escapeshellarg('/etc/systemd/system/infoscreen2-kiosk.service')
    . ' >/dev/null 2>&1';

@system($cmd, $rc);

if ($rc === 0 && is_file($full)) {
    appendLog('watchdog.log', 'Manuelle Aktion: Backup erstellt: ' . $file);
}
header('Location: admin.php');
exit;
