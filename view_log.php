<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$logFile = __DIR__ . '/data/logs/app.log';
$entries = [];

if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $entries[] = [
                    'time' => (string)($decoded['time'] ?? ''),
                    'level' => strtoupper((string)($decoded['level'] ?? 'INFO')),
                    'message' => (string)($decoded['message'] ?? ''),
                    'context' => is_array($decoded['context'] ?? null) ? $decoded['context'] : [],
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

$entries = array_reverse($entries);
$entries = array_slice($entries, 0, 300);

function flatten_context(array $context, string $prefix = ''): array
{
    $rows = [];

    foreach ($context as $key => $value) {
        $fullKey = $prefix === '' ? (string)$key : ($prefix . '.' . $key);

        if (is_array($value)) {
            $rows = array_merge($rows, flatten_context($value, $fullKey));
            continue;
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif ($value === null) {
            $value = 'null';
        } else {
            $value = (string)$value;
        }

        $rows[] = [
            'key' => $fullKey,
            'value' => $value,
        ];
    }

    return $rows;
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Infoscreen2 Log</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:18px;background:#0f1115;color:#f3f4f6}
a{color:#93c5fd;text-decoration:none}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2563eb;color:#fff;font-weight:700}
.card{background:#171a21;border:1px solid #2a2f3a;border-radius:12px;padding:16px;margin-bottom:14px}
.meta{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;color:#cbd5e1;font-size:14px}
.level{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:700}
.level-INFO{background:#1d4ed8;color:#dbeafe}
.level-WARN{background:#92400e;color:#ffedd5}
.level-ERROR{background:#991b1b;color:#fee2e2}
.level-RAW{background:#4b5563;color:#f3f4f6}
.level-DEBUG{background:#065f46;color:#d1fae5}
h1{margin:0 0 10px}
.small{color:#9ca3af;font-size:13px}
pre{white-space:pre-wrap;word-break:break-word;background:#0b0d12;padding:12px;border-radius:8px;border:1px solid #222833;overflow:auto}
.table{width:100%;border-collapse:collapse;margin-top:8px}
.table th,.table td{padding:8px 10px;border-top:1px solid #2a2f3a;vertical-align:top;text-align:left}
.table th{font-size:12px;text-transform:uppercase;color:#9ca3af}
code{background:#111827;padding:2px 6px;border-radius:6px}
.toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
</style>
</head>
<body>

<div class="toolbar">
  <a class="btn" href="admin.php">Zurück zur Verwaltung</a>
  <a class="btn" href="view_log.php">Neu laden</a>
</div>

<h1>App Log</h1>
<p class="small">Datei: <code><?= h($logFile) ?></code></p>
<p class="small">Angezeigt werden die letzten <?= count($entries) ?> Einträge.</p>

<?php if (!$entries): ?>
  <div class="card">Noch keine Logeinträge vorhanden.</div>
<?php else: ?>
  <?php foreach ($entries as $entry): ?>
    <?php $flatContext = flatten_context($entry['context']); ?>
    <div class="card">
      <div class="meta">
        <span><?= h($entry['time'] !== '' ? $entry['time'] : 'ohne Zeitstempel') ?></span>
        <span class="level level-<?= h($entry['level']) ?>"><?= h($entry['level']) ?></span>
      </div>

      <div><strong><?= h($entry['message']) ?></strong></div>

      <?php if ($flatContext): ?>
        <table class="table">
          <thead>
            <tr>
              <th>Feld</th>
              <th>Wert</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($flatContext as $row): ?>
            <tr>
              <td><code><?= h($row['key']) ?></code></td>
              <td><?= h($row['value']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <details style="margin-top:10px">
          <summary class="small">Rohes JSON anzeigen</summary>
          <pre><?= h(json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
        </details>
      <?php else: ?>
        <p class="small" style="margin-top:10px">Kein Kontext vorhanden.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
