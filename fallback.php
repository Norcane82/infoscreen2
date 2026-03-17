<?php
require_once __DIR__ . '/functions.php';
$state = readJsonFile(getRestartLogPath(), [
    'last_restart' => 0,
    'restarts' => [],
    'fallback_active' => false,
    'consecutive_failures' => 0,
    'last_action' => 'none'
]);
$flag = __DIR__ . '/cache/fallback_active.flag';
$text = is_file($flag) ? trim((string)file_get_contents($flag)) : 'Fallback aktiv';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen2 Fallback</title>
<style>
html,body{margin:0;width:100%;height:100%;font-family:Arial,Helvetica,sans-serif;background:#111;color:#fff}
.wrap{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px;box-sizing:border-box}
h1{font-size:clamp(32px,5vw,72px);margin:0 0 20px}
p{font-size:clamp(18px,2vw,30px);margin:8px 0}
small{opacity:.8}
.card{margin-top:20px;padding:20px;border:1px solid rgba(255,255,255,.2);border-radius:14px;background:rgba(255,255,255,.06);max-width:900px}
a{color:#fff}
</style>
</head>
<body>
<div class="wrap">
  <h1>Infoscreen Sicherheitsmodus</h1>
  <p>Der Watchdog hat den Player gestoppt oder in den Fallback gesetzt.</p>
  <div class="card">
    <p><strong>Grund:</strong> <?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Letzte Aktion:</strong> <?= htmlspecialchars((string)($state['last_action'] ?? 'none'), ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Consecutive Failures:</strong> <?= (int)($state['consecutive_failures'] ?? 0) ?></p>
    <p><strong>Neustarts in 30 Minuten:</strong> <?= count((array)($state['restarts'] ?? [])) ?></p>
    <p><a href="admin.php">Zur Verwaltung</a></p>
  </div>
  <small>Bitte Ursache prüfen und danach den Watchdog zurücksetzen.</small>
</div>
</body>
</html>
