<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$logFile = LOG_FILE;
$entries = [];

if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (is_array($lines)) {
        $lines = array_slice($lines, -300);

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $entries[] = [
                    'time' => (string)($decoded['time'] ?? ''),
                    'level' => (string)($decoded['level'] ?? ''),
                    'message' => (string)($decoded['message'] ?? ''),
                    'context' => $decoded['context'] ?? [],
                    'raw' => $line,
                ];
            } else {
                $entries[] = [
                    'time' => '',
                    'level' => 'RAW',
                    'message' => $line,
                    'context' => [],
                    'raw' => $line,
                ];
            }
        }
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
.btn{display:inline-block;padding:8px 12px;background:#333;color:#fff;text-decoration:none;border-radius:8px}
.card{background:#1b1b1b;padding:16px;border-radius:12px;border:1px solid #333;margin-bottom:12px}
.meta{font-size:13px;color:#bbb;margin-bottom:8px}
.level{display:inline-block;padding:3px 8px;border-radius:999px;background:#333;font-size:12px;margin-left:8px}
.level-INFO{background:#1f5f99}
.level-WARN{background:#8a6d1d}
.level-ERROR{background:#8b2e2e}
.level-RAW{background:#555}
pre{white-space:pre-wrap;word-break:break-word;background:#151515;padding:12px;border-radius:8px;border:1px solid #2d2d2d}
.small{color:#aaa;font-size:13px}
code{background:#1a1a1a;padding:2px 6px;border-radius:6px}
</style>
</head>
<body>
<p><a class="btn" href="admin.php">Zurück zur Verwaltung</a></p>

<h1>App Log</h1>
<p class="small">Datei: <code><?= h($logFile) ?></code></p>

<?php if (!$entries): ?>
  <div class="card">Noch keine Logeinträge vorhanden.</div>
<?php else: ?>
  <?php foreach (array_reverse($entries) as $entry): ?>
    <div class="card">
      <div class="meta">
        <?= h($entry['time'] !== '' ? $entry['time'] : 'ohne Zeitstempel') ?>
        <span class="level level-<?= h(strtoupper($entry['level'])) ?>"><?= h(strtoupper($entry['level'])) ?></span>
      </div>
      <div><strong><?= h($entry['message']) ?></strong></div>

      <?php if (!empty($entry['context'])): ?>
        <pre><?= h(json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
