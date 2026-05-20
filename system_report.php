<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$cooldownSeconds = 180;
$logDir = __DIR__ . '/data/logs';
$lastFile = __DIR__ . '/data/system_report_last.json';
$logFile = $logDir . '/system_report.log';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

function runFixedCommand(string $label, string $command, int $timeoutSeconds = 12): array {
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $start = microtime(true);
    $process = @proc_open($command, $descriptorSpec, $pipes, __DIR__);

    if (!is_resource($process)) {
        return [
            'label' => $label,
            'command' => $command,
            'exitCode' => -1,
            'durationMs' => 0,
            'output' => 'Befehl konnte nicht gestartet werden.',
        ];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $timedOut = false;

    while (true) {
        $status = proc_get_status($process);
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);

        if (!$status['running']) {
            break;
        }

        if ((microtime(true) - $start) > $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process);
            break;
        }

        usleep(100000);
    }

    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $durationMs = (int)round((microtime(true) - $start) * 1000);

    $output = trim($stdout);
    if (trim($stderr) !== '') {
        $output .= ($output !== '' ? "\n\n[stderr]\n" : "[stderr]\n") . trim($stderr);
    }

    if ($timedOut) {
        $output .= ($output !== '' ? "\n\n" : '') . 'Befehl wurde wegen Zeitlimit beendet.';
        $exitCode = 124;
    }

    return [
        'label' => $label,
        'command' => $command,
        'exitCode' => $exitCode,
        'durationMs' => $durationMs,
        'output' => $output !== '' ? $output : '(keine Ausgabe)',
    ];
}

function readLastReport(string $lastFile): ?array {
    if (!is_file($lastFile)) {
        return null;
    }

    $data = json_decode((string)file_get_contents($lastFile), true);
    return is_array($data) ? $data : null;
}

function readMemInfo(): array {
    $result = [
        'memTotalMb' => 0,
        'memAvailableMb' => 0,
        'swapTotalMb' => 0,
        'swapFreeMb' => 0,
    ];

    $content = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($content)) {
        return $result;
    }

    foreach ($content as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }

        [$key, $value] = explode(':', $line, 2);
        $kb = (int)preg_replace('/[^0-9]/', '', $value);
        $mb = (int)round($kb / 1024);

        if ($key === 'MemTotal') {
            $result['memTotalMb'] = $mb;
        } elseif ($key === 'MemAvailable') {
            $result['memAvailableMb'] = $mb;
        } elseif ($key === 'SwapTotal') {
            $result['swapTotalMb'] = $mb;
        } elseif ($key === 'SwapFree') {
            $result['swapFreeMb'] = $mb;
        }
    }

    return $result;
}

function readVmstatCounters(): array {
    $result = [
        'pswpin' => 0,
        'pswpout' => 0,
        'pgpgin' => 0,
        'pgpgout' => 0,
    ];

    $content = @file('/proc/vmstat', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($content)) {
        return $result;
    }

    foreach ($content as $line) {
        [$key, $value] = array_pad(explode(' ', trim($line), 2), 2, '0');
        if (array_key_exists($key, $result)) {
            $result[$key] = (int)$value;
        }
    }

    return $result;
}

function readCpuCoreCount(): int {
    $cpuInfo = @file('/proc/cpuinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($cpuInfo)) {
        return 1;
    }

    $count = 0;
    foreach ($cpuInfo as $line) {
        if (str_starts_with($line, 'processor')) {
            $count++;
        }
    }

    return max(1, $count);
}

function buildAssessment(array $metrics, ?array $previous): array {
    $items = [];

    $loadRatio = $metrics['load1'] / max(1, $metrics['cpuCores']);
    if ($loadRatio < 0.75) {
        $loadStatus = 'ok';
        $loadText = 'Gut';
    } elseif ($loadRatio < 1.25) {
        $loadStatus = 'warn';
        $loadText = 'Erhöht';
    } else {
        $loadStatus = 'bad';
        $loadText = 'Hoch';
    }

    $items[] = [
        'label' => 'CPU/Load',
        'status' => $loadStatus,
        'value' => number_format($metrics['load1'], 2, ',', '.') . ' / ' . $metrics['cpuCores'] . ' Kerne',
        'note' => $loadText . '. Unter 1,0 pro Kern ist für den Pi meist sauber.',
    ];

    if ($metrics['memUsedPercent'] < 75) {
        $memStatus = 'ok';
        $memText = 'Gut';
    } elseif ($metrics['memUsedPercent'] < 88) {
        $memStatus = 'warn';
        $memText = 'Beobachten';
    } else {
        $memStatus = 'bad';
        $memText = 'Hoch';
    }

    $items[] = [
        'label' => 'RAM',
        'status' => $memStatus,
        'value' => $metrics['memUsedPercent'] . ' % belegt',
        'note' => $memText . '. Verfügbar: ' . $metrics['memAvailableMb'] . ' MB von ' . $metrics['memTotalMb'] . ' MB.',
    ];

    if ($metrics['swapUsedMb'] < 160) {
        $swapStatus = 'ok';
        $swapText = 'Gut';
    } elseif ($metrics['swapUsedMb'] < 300) {
        $swapStatus = 'warn';
        $swapText = 'Beobachten';
    } else {
        $swapStatus = 'bad';
        $swapText = 'Kritisch prüfen';
    }

    $items[] = [
        'label' => 'Swap',
        'status' => $swapStatus,
        'value' => $metrics['swapUsedMb'] . ' MB belegt',
        'note' => $swapText . '. Ziel: nicht dauerhaft Richtung 300-500 MB steigen lassen.',
    ];

    $swapDeltaText = 'Kein Vergleich vorhanden.';
    $swapDeltaStatus = 'ok';
    if (is_array($previous) && isset($previous['metrics']['vmstat'])) {
        $previousVm = (array)$previous['metrics']['vmstat'];
        $deltaIn = $metrics['vmstat']['pswpin'] - (int)($previousVm['pswpin'] ?? 0);
        $deltaOut = $metrics['vmstat']['pswpout'] - (int)($previousVm['pswpout'] ?? 0);
        $swapDeltaText = 'Seit letzter Prüfung: pswpin +' . max(0, $deltaIn) . ', pswpout +' . max(0, $deltaOut) . '.';
        if ($deltaIn > 0 || $deltaOut > 0) {
            $swapDeltaStatus = 'warn';
        }
    }

    $items[] = [
        'label' => 'Swap-Bewegung',
        'status' => $swapDeltaStatus,
        'value' => $swapDeltaStatus === 'ok' ? 'ruhig' : 'Bewegung erkannt',
        'note' => $swapDeltaText,
    ];

    if ($metrics['kioskActive']) {
        $kioskStatus = 'ok';
        $kioskValue = 'aktiv';
        $kioskNote = 'infoscreen2-kiosk.service läuft.';
    } else {
        $kioskStatus = 'bad';
        $kioskValue = 'nicht aktiv';
        $kioskNote = 'Kiosk-Dienst prüfen.';
    }

    $items[] = [
        'label' => 'Kiosk-Dienst',
        'status' => $kioskStatus,
        'value' => $kioskValue,
        'note' => $kioskNote,
    ];

    return $items;
}

function createReport(?array $previous): array {
    $mem = readMemInfo();
    $load = sys_getloadavg();
    $cpuCores = readCpuCoreCount();
    $vmstat = readVmstatCounters();

    $memTotalMb = max(1, (int)$mem['memTotalMb']);
    $memAvailableMb = max(0, (int)$mem['memAvailableMb']);
    $memUsedPercent = (int)round((($memTotalMb - $memAvailableMb) / $memTotalMb) * 100);

    $swapTotalMb = max(0, (int)$mem['swapTotalMb']);
    $swapFreeMb = max(0, (int)$mem['swapFreeMb']);
    $swapUsedMb = max(0, $swapTotalMb - $swapFreeMb);

    $kioskActiveRaw = trim((string)@shell_exec('systemctl is-active infoscreen2-kiosk.service 2>/dev/null'));
    $kioskActive = $kioskActiveRaw === 'active';

    $metrics = [
        'createdAt' => date('c'),
        'load1' => (float)($load[0] ?? 0),
        'load5' => (float)($load[1] ?? 0),
        'load15' => (float)($load[2] ?? 0),
        'cpuCores' => $cpuCores,
        'memTotalMb' => $memTotalMb,
        'memAvailableMb' => $memAvailableMb,
        'memUsedPercent' => $memUsedPercent,
        'swapTotalMb' => $swapTotalMb,
        'swapUsedMb' => $swapUsedMb,
        'kioskActive' => $kioskActive,
        'vmstat' => $vmstat,
    ];

    $commands = [
        runFixedCommand('Laufzeit und Load', 'uptime', 5),
        runFixedCommand('Speicher', 'free -h', 5),
        runFixedCommand('Top-Prozesse nach CPU', "ps -eo pid,ppid,user,%mem,%cpu,rss,stat,cmd --sort=-%cpu | head -n 20", 8),
        runFixedCommand('VMStat Kurzprüfung', 'vmstat 1 5', 8),
        runFixedCommand('Dateisysteme', 'df -h / /var/www/html 2>/dev/null', 5),
        runFixedCommand('Kiosk-Service', 'systemctl status infoscreen2-kiosk.service --no-pager -l', 10),
    ];

    return [
        'createdAt' => date('c'),
        'createdAtDisplay' => date('d.m.Y H:i:s'),
        'metrics' => $metrics,
        'assessment' => buildAssessment($metrics, $previous),
        'commands' => $commands,
        'note' => 'sudo iotop wird bewusst nicht über den Webbutton ausgeführt. Dafür wäre eine gezielte sudoers-Freigabe für den Apache-Benutzer nötig.',
    ];
}

function appendReportLog(string $logFile, array $report): void {
    $line = str_repeat('=', 100) . "\n";
    $line .= 'SYSTEM REPORT ' . ($report['createdAt'] ?? date('c')) . "\n";
    $line .= str_repeat('=', 100) . "\n";
    $line .= 'Bewertung:' . "\n";

    foreach ((array)($report['assessment'] ?? []) as $item) {
        $line .= '- ' . ($item['label'] ?? '') . ': ' . ($item['value'] ?? '') . ' | ' . ($item['note'] ?? '') . "\n";
    }

    $line .= "\nHinweis: " . ($report['note'] ?? '') . "\n\n";

    foreach ((array)($report['commands'] ?? []) as $cmd) {
        $line .= '--- ' . ($cmd['label'] ?? 'Befehl') . ' ---' . "\n";
        $line .= '$ ' . ($cmd['command'] ?? '') . "\n";
        $line .= 'Exit: ' . ($cmd['exitCode'] ?? '') . ' | Dauer: ' . ($cmd['durationMs'] ?? '') . " ms\n";
        $line .= (string)($cmd['output'] ?? '') . "\n\n";
    }

    $line .= "\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

$previousReport = readLastReport($lastFile);
$now = time();
$lastTime = 0;

if (is_array($previousReport) && isset($previousReport['createdAt'])) {
    $lastTime = strtotime((string)$previousReport['createdAt']) ?: 0;
}

$remainingSeconds = max(0, $cooldownSeconds - ($now - $lastTime));
$generatedNew = false;
$report = $previousReport;

if (!is_array($report) || $remainingSeconds <= 0) {
    $report = createReport($previousReport);
    @file_put_contents($lastFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    appendReportLog($logFile, $report);
    $generatedNew = true;
    $remainingSeconds = $cooldownSeconds;
}

if (!is_array($report)) {
    $report = [
        'createdAt' => date('c'),
        'createdAtDisplay' => date('d.m.Y H:i:s'),
        'assessment' => [],
        'commands' => [],
        'note' => 'Noch keine Systemauswertung vorhanden.',
    ];
}

function statusClass(string $status): string {
    return match ($status) {
        'ok' => 'ok',
        'warn' => 'warningBadge',
        'bad' => 'off',
        default => 'warningBadge',
    };
}

function statusLabel(string $status): string {
    return match ($status) {
        'ok' => 'OK',
        'warn' => 'Beobachten',
        'bad' => 'Prüfen',
        default => 'Info',
    };
}

$adminCss = __DIR__ . '/assets/css/admin.css';
$adminCssVersion = is_file($adminCss) ? (int)filemtime($adminCss) : time();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen 2 Systemauswertung</title>
<link rel="stylesheet" href="assets/css/admin.css?v=<?= $adminCssVersion ?>">
<style>
.reportHeader{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    flex-wrap:wrap;
}
.reportMeta{
    color:#667085;
    font-size:.92rem;
}
.reportGrid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:12px;
}
.reportTile{
    border:1px solid var(--line-soft);
    background:#f8fafc;
    border-radius:14px;
    padding:14px;
    min-width:0;
}
.reportTile__top{
    display:flex;
    justify-content:space-between;
    gap:8px;
    align-items:center;
    margin-bottom:8px;
}
.reportTile__label{
    font-weight:800;
}
.reportTile__value{
    font-size:1.1rem;
    font-weight:800;
    margin-bottom:6px;
}
.commandBlock{
    border:1px solid var(--line-soft);
    border-radius:14px;
    margin-top:12px;
    overflow:hidden;
    background:#fff;
    max-width:100%;
    min-width:0;
}
.commandBlock summary{
    cursor:pointer;
    padding:12px 14px;
    font-weight:800;
    background:#f8fafc;
}
.commandBlock pre{
    display:block;
    width:100%;
    max-width:100%;
    min-width:0;
    box-sizing:border-box;
    margin:0;
    padding:14px;
    overflow-x:clip;
    overflow-y:auto;
    white-space:pre-wrap !important;
    overflow-wrap:anywhere !important;
    word-wrap:break-word !important;
    word-break:break-all;
    background:#0f172a;
    color:#e5e7eb;
    font-size:.86rem;
    line-height:1.45;
}
.commandBlock code{
    white-space:inherit;
    overflow-wrap:inherit;
    word-break:inherit;
}
</style>
</head>
<body>
<div class="layout">
    <div class="card">
        <div class="reportHeader">
            <div>
                <p class="eyebrow">Infoscreen 2</p>
                <h1>Systemauswertung</h1>
                <p class="small">Raspberry-Pi-Zustand mit Vergleich zur letzten Prüfung und Protokollspeicherung.</p>
            </div>
            <div class="topLinks">
                <a class="btn secondary" href="admin.php?page=master">Zur Master-Verwaltung</a>
                <a class="btn secondary" href="view_log.php?file=system_report">Protokoll öffnen</a>
            </div>
        </div>

        <div class="notice">
            <?php if ($generatedNew): ?>
                Neue Systemauswertung wurde erstellt.
            <?php else: ?>
                Es wird die letzte Systemauswertung angezeigt. Neue Abfrage möglich in ca. <?= (int)$remainingSeconds ?> Sekunden.
            <?php endif; ?>
            <br>
            <span class="reportMeta">Stand: <?= h((string)($report['createdAtDisplay'] ?? $report['createdAt'] ?? 'unbekannt')) ?></span>
        </div>

        <div class="reportGrid">
            <?php foreach ((array)($report['assessment'] ?? []) as $item): ?>
                <?php
                    $status = (string)($item['status'] ?? 'info');
                    $class = statusClass($status);
                ?>
                <div class="reportTile">
                    <div class="reportTile__top">
                        <span class="reportTile__label"><?= h((string)($item['label'] ?? '')) ?></span>
                        <span class="badge <?= h($class) ?>"><?= h(statusLabel($status)) ?></span>
                    </div>
                    <div class="reportTile__value"><?= h((string)($item['value'] ?? '')) ?></div>
                    <div class="small"><?= h((string)($item['note'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Befehlsausgaben</h2>
        <p class="small"><?= h((string)($report['note'] ?? '')) ?></p>

        <?php foreach ((array)($report['commands'] ?? []) as $cmd): ?>
            <details class="commandBlock">
                <summary>
                    <?= h((string)($cmd['label'] ?? 'Befehl')) ?>
                    · Exit <?= h((string)($cmd['exitCode'] ?? '')) ?>
                    · <?= h((string)($cmd['durationMs'] ?? '')) ?> ms
                </summary>
                <pre><code><?= h('$ ' . (string)($cmd['command'] ?? '') . "\n\n" . (string)($cmd['output'] ?? '')) ?></code></pre>
            </details>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
