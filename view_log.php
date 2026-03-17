<?php
require_once __DIR__ . '/functions.php';

$logFile = __DIR__ . '/logs/watchdog.log';
$content = 'Noch keine Logdatei vorhanden.';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $lines = array_slice($lines, -300);
        $content = implode("\n", $lines);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen2 Log</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#111;color:#eee}
a{color:#fff}
pre{white-space:pre-wrap;word-break:break-word;background:#1b1b1b;padding:16px;border-radius:12px;border:1px solid #333}
.btn{display:inline-block;padding:8px 12px;background:#333;color:#fff;text-decoration:none;border-radius:8px}
</style>
</head>
<body>
<p><a class="btn" href="admin.php">Zurück zur Verwaltung</a></p>
<h1>Watchdog Log</h1>
<pre><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></pre>
</body>
</html>
