<?php
require_once __DIR__ . '/functions.php';
$lockFile=fopen(__DIR__ . '/cache/watchdog.lock','c');
if(!$lockFile||!flock($lockFile,LOCK_EX|LOCK_NB)){exit(0);}

$config = loadConfig();
$sys = $config['system'] ?? [];
$svc = $config['services'] ?? [];

if (empty($sys['watchdogEnabled'])) exit(0);

$cpuLimit = max(1, (int)($sys['cpuLimit'] ?? 85));
$ramLimit = max(1, (int)($sys['ramLimit'] ?? 85));
$cooldown = max(30, (int)($sys['cooldownSeconds'] ?? 180));
$maxRestarts = max(1, (int)($sys['maxRestartsIn30Min'] ?? 3));
$playerRestartBeforeReboot = max(1, (int)($sys['rebootAfterPlayerRestarts'] ?? 2));
$consecutiveNeeded = max(1, (int)($sys['requireConsecutiveFails'] ?? 2));
$apacheCheck = !empty($sys['apacheHealthcheck']);
$apacheUrl = (string)($sys['apacheUrl'] ?? 'http://127.0.0.1/infoscreen2/index.php');
$apacheTimeout = max(1, (int)($sys['apacheTimeoutSeconds'] ?? 5));
$processName = trim((string)($svc['playerProcessName'] ?? 'chromium'));
$logFile = getRestartLogPath();
$state = readJsonFile($logFile, [
    'last_restart' => 0,
    'restarts' => [],
    'fallback_active' => false,
    'consecutive_failures' => 0,
    'last_action' => 'none'
]);

function readCpuPercent(): int {
    $line1 = @file('/proc/stat')[0] ?? '';
    if ($line1 === '') return 0;
    preg_match_all('/\d+/', $line1, $m1);
    $a = array_map('intval', $m1[0] ?? []);
    usleep(250000);
    $line2 = @file('/proc/stat')[0] ?? '';
    preg_match_all('/\d+/', $line2, $m2);
    $b = array_map('intval', $m2[0] ?? []);
    if (count($a) < 4 || count($b) < 4) return 0;

    $idle1 = ($a[3] ?? 0) + ($a[4] ?? 0);
    $idle2 = ($b[3] ?? 0) + ($b[4] ?? 0);
    $tot1 = array_sum($a);
    $tot2 = array_sum($b);
    $total = $tot2 - $tot1;
    $idle = $idle2 - $idle1;
    if ($total <= 0) return 0;
    $used = 100 - (($idle / $total) * 100);
    return max(0, min(100, (int)round($used)));
}

function readRamPercent(): int {
    $raw = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$raw) return 0;
    $vals = [];
    foreach ($raw as $line) {
        if (preg_match('/^([A-Za-z_]+):\s+(\d+)/', $line, $m)) {
            $vals[$m[1]] = (int)$m[2];
        }
    }
    $total = (int)($vals['MemTotal'] ?? 0);
    $avail = (int)($vals['MemAvailable'] ?? 0);
    if ($total <= 0) return 0;
    return (int)round((($total - $avail) / $total) * 100);
}

function apacheHealthy(string $url, int $timeout): bool {
    $ctx = stream_context_create([
        'http' => ['timeout' => $timeout, 'ignore_errors' => true]
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return false;
    $headers = $http_response_header ?? [];
    if (!$headers) return false;
    if (preg_match('#\s(\d{3})\s#', $headers[0], $m)) {
        $code = (int)$m[1];
        return $code >= 200 && $code < 400;
    }
    return true;
}

function stopPlayerProcesses(string $name): void {
    if ($name !== '') {
        @shell_exec('pkill -f ' . escapeshellarg($name) . ' >/dev/null 2>&1');
    }
    @shell_exec('pkill -f "chromium.*infoscreen2" >/dev/null 2>&1');
    @shell_exec('pkill -f "chromium-browser.*infoscreen2" >/dev/null 2>&1');
}

function restartPlayer(string $name): void {
    stopPlayerProcesses($name);
    @shell_exec('/usr/local/bin/infoscreen2-restart-player.sh >/dev/null 2>&1 &');
}
function rebootSystem(string $name): void {
    stopPlayerProcesses($name);
    @shell_exec('sync');
    @shell_exec('shutdown -r now >/dev/null 2>&1 &');
}

function activateFallback(array $svc, string $reason): void {
    appendLog('watchdog.log', 'Fallback aktiviert: ' . $reason);
    if (!empty($svc['stopScreenOnFallback'])) {
        @shell_exec('pkill -f chromium >/dev/null 2>&1');
        @shell_exec('pkill -f unclutter >/dev/null 2>&1');
    }
    if (!empty($svc['stopApacheOnFallback'])) {
        @shell_exec('systemctl stop apache2 >/dev/null 2>&1');
    }
    @file_put_contents(__DIR__ . '/cache/fallback_active.flag', date('Y-m-d H:i:s') . ' ' . $reason . PHP_EOL);
}

$cpu = readCpuPercent();
$ram = readRamPercent();
$apacheOk = $apacheCheck ? apacheHealthy($apacheUrl, $apacheTimeout) : true;
appendLog('watchdog.log', 'CPU=' . $cpu . '% RAM=' . $ram . '% APACHE=' . ($apacheOk ? 'OK' : 'FAIL'));
$now = time();
$state['restarts'] = array_values(array_filter(
    (array)($state['restarts'] ?? []),
    fn($t) => ((int)$t >= ($now - 1800))
));

if (!empty($state['fallback_active'])) {
    writeJsonFile($logFile, $state);
    exit(0);
}

$overloaded = ($cpu >= $cpuLimit) || ($ram >= $ramLimit);
$failed = $overloaded || !$apacheOk;

if ($failed) {
    $state['consecutive_failures'] = (int)($state['consecutive_failures'] ?? 0) + 1;
} else {
    $state['consecutive_failures'] = 0;
    writeJsonFile($logFile, $state);
    exit(0);
}

if ((int)$state['consecutive_failures'] < $consecutiveNeeded) {
    appendLog('watchdog.log', 'Fehler erkannt, warte auf Bestätigung');
    writeJsonFile($logFile, $state);
    exit(0);
}
$lastRestart = (int)($state['last_restart'] ?? 0);
if (($now - $lastRestart) < $cooldown) {
    appendLog('watchdog.log', 'Cooldown aktiv, kein Neustart');
    writeJsonFile($logFile, $state);
    exit(0);
}

if (count($state['restarts']) >= $maxRestarts) {
    activateFallback($svc, 'Zu viele Neustarts in 30 Minuten');
    $state['fallback_active'] = true;
    writeJsonFile($logFile, $state);
    exit(0);
}

$state['last_restart'] = $now;
$state['restarts'][] = $now;
$recentCount = count($state['restarts']);

if ($recentCount >= $playerRestartBeforeReboot) {
    $state['last_action'] = 'reboot';
    appendLog('watchdog.log', 'Eskalation: System-Reboot');
    writeJsonFile($logFile, $state);
    rebootSystem($processName);
    exit(0);
}
$state['last_action'] = 'player_restart';
appendLog('watchdog.log', 'Aktion: Player-Neustart');
writeJsonFile($logFile, $state);
restartPlayer($processName);
exit(0);
