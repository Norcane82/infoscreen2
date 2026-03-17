<?php
require_once __DIR__ . '/functions.php';

$backupDir = __DIR__ . '/data/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 2775, true);
    @chmod($backupDir, 02775);
}

$file = 'infoscreen2_backup_' . date('Ymd_His') . '.tar.gz';
$full = $backupDir . '/' . $file;

$cmd = 'tar -czf ' . escapeshellarg($full)
    . ' -C ' . escapeshellarg(dirname(__DIR__))
    . ' ' . escapeshellarg(basename(__DIR__))
    . ' 2>&1';

exec($cmd, $output, $rc);

if ($rc === 0 && is_file($full)) {
    @chmod($full, 0664);
    appendLog('watchdog.log', 'Manuelle Aktion: Backup erstellt: ' . $file);
} else {
    appendLog('watchdog.log', 'Backup fehlgeschlagen: ' . implode(' | ', $output));
}

header('Location: admin.php');
exit;
