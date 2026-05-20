<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$availableLogs = [
    'app' => [
        'label' => 'App Log',
        'file' => __DIR__ . '/data/logs/app.log',
        'mode' => 'jsonl',
    ],
    'status' => [
        'label' => 'Status-Snapshots',
        'file' => __DIR__ . '/data/logs/app.log',
        'mode' => 'status_jsonl',
    ],
    'trace' => [
        'label' => 'Player Trace Log',
        'file' => __DIR__ . '/data/logs/player_trace.log',
        'mode' => 'trace_plain',
    ],
    'system_report' => [
        'label' => 'Systemauswertung',
        'file' => __DIR__ . '/data/logs/system_report.log',
        'mode' => 'plain_block',
    ],
];

$selectedKey = (string)($_GET['file'] ?? 'app');
if (!isset($availableLogs[$selectedKey])) {
    $selectedKey = 'app';
}

$selectedLog = $availableLogs[$selectedKey];
$logFile = $selectedLog['file'];
$entries = [];

function parse_trace_line(string $line): array
{
    $line = trim($line);

    if (preg_match('/^\[([^\]]+)\]\s+([A-Z]+)\s+(.*?)\s+\|\s+(\{.*\})$/', $line, $matches)) {
        $context = json_decode($matches[4], true);
        return [
            'time' => (string)$matches[1],
            'level' => strtoupper((string)$matches[2]),
            'message' => (string)$matches[3],
            'context' => is_array($context) ? $context : [],
            'raw' => $line,
            'summary' => '',
        ];
    }

    return [
        'time' => '',
        'level' => 'RAW',
        'message' => $line,
        'context' => [],
        'raw' => $line,
        'summary' => '',
    ];
}

function system_report_summary(string $raw): string
{
    $lines = preg_split('/\R/', $raw);
    if (!is_array($lines)) {
        return '';
    }

    $summary = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '- CPU/Load:')
            || str_starts_with($trimmed, '- RAM:')
            || str_starts_with($trimmed, '- Swap:')
            || str_starts_with($trimmed, '- Swap-Bewegung:')
            || str_starts_with($trimmed, '- Kiosk-Dienst:')) {
            $summary[] = ltrim($trimmed, '- ');
        }

        if (count($summary) >= 5) {
            break;
        }
    }

    return implode("\n", $summary);
}

function parse_plain_blocks(string $content): array
{
    $content = trim($content);
    if ($content === '') {
        return [];
    }

    $parts = preg_split('/\n={20,}\n/', "\n" . $content);
    if (!is_array($parts)) {
        return [];
    }

    $entries = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        $time = '';
        $message = 'Systemauswertung';

        if (preg_match('/SYSTEM REPORT\s+([^\n]+)/', $part, $matches)) {
            $time = trim((string)$matches[1]);
            $message = 'Systemauswertung ' . $time;
        }

        $entries[] = [
            'time' => $time,
            'level' => 'INFO',
            'message' => $message,
            'context' => [],
            'raw' => $part,
            'summary' => system_report_summary($part),
        ];
    }

    return $entries;
}

if (is_file($logFile)) {
    if ($selectedLog['mode'] === 'plain_block') {
        $content = (string)@file_get_contents($logFile);
        $entries = parse_plain_blocks($content);
    } else {
        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                if ($selectedLog['mode'] === 'jsonl' || $selectedLog['mode'] === 'status_jsonl') {
                    $decoded = json_decode($line, true);

                    if (is_array($decoded)) {
                        $message = (string)($decoded['message'] ?? '');
                        if ($selectedLog['mode'] === 'status_jsonl' && $message !== 'Status snapshot') {
                            continue;
                        }

                        $entries[] = [
                            'time' => (string)($decoded['time'] ?? ''),
                            'level' => strtoupper((string)($decoded['level'] ?? 'INFO')),
                            'message' => $message,
                            'context' => is_array($decoded['context'] ?? null) ? $decoded['context'] : [],
                            'raw' => $line,
                            'summary' => '',
                        ];
                        continue;
                    }
                }

                if ($selectedLog['mode'] === 'trace_plain') {
                    $entries[] = parse_trace_line($line);
                    continue;
                }

                $entries[] = [
                    'time' => '',
                    'level' => 'RAW',
                    'message' => $line,
                    'context' => [],
                    'raw' => $line,
                    'summary' => '',
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
body{
    font-family:Arial,Helvetica,sans-serif;
    margin:18px;
    background:#0f1115;
    color:#f3f4f6;
}
a{
    color:#93c5fd;
    text-decoration:none;
}
.btn{
    display:inline-block;
    padding:10px 14px;
    border-radius:10px;
    background:#2563eb;
    color:#fff;
    font-weight:700;
}
.btn.secondary{
    background:#374151;
}
.card{
    background:#171a21;
    border:1px solid #2a2f3a;
    border-radius:12px;
    padding:16px;
    margin-bottom:14px;
}
.card--compact{
    padding:14px;
}
.meta{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
    margin-bottom:10px;
    color:#cbd5e1;
    font-size:14px;
}
.level{
    display:inline-block;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}
.level-INFO{
    background:#1d4ed8;
    color:#dbeafe;
}
.level-WARN{
    background:#92400e;
    color:#ffedd5;
}
.level-ERROR{
    background:#991b1b;
    color:#fee2e2;
}
.level-RAW{
    background:#4b5563;
    color:#f3f4f6;
}
.level-DEBUG{
    background:#065f46;
    color:#d1fae5;
}
h1{
    margin:0 0 10px;
}
.small{
    color:#9ca3af;
    font-size:13px;
}
pre{
    white-space:pre-wrap;
    overflow-wrap:anywhere;
    word-break:break-word;
    background:#0b0d12;
    padding:12px;
    border-radius:8px;
    border:1px solid #222833;
    overflow-x:clip;
    overflow-y:auto;
    line-height:1.45;
    max-width:100%;
    box-sizing:border-box;
}
.table{
    width:100%;
    border-collapse:collapse;
    margin-top:8px;
}
.table th,
.table td{
    padding:8px 10px;
    border-top:1px solid #2a2f3a;
    vertical-align:top;
    text-align:left;
}
.table th{
    font-size:12px;
    text-transform:uppercase;
    color:#9ca3af;
}
code{
    background:#111827;
    padding:2px 6px;
    border-radius:6px;
    overflow-wrap:anywhere;
    word-break:break-word;
}
.toolbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    margin-bottom:14px;
}
.toolbar .active{
    background:#059669;
}
.summaryBox{
    margin-top:10px;
    padding:12px;
    border-radius:10px;
    background:#0f172a;
    border:1px solid #23314a;
    color:#dbeafe;
}
.logHeader{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    flex-wrap:wrap;
    margin-bottom:14px;
}
.reportSummary{
    margin-top:10px;
    padding:12px;
    border-radius:10px;
    background:#0f172a;
    border:1px solid #23314a;
    color:#dbeafe;
    white-space:pre-wrap;
    overflow-wrap:anywhere;
    line-height:1.45;
}
.reportDetails{
    margin-top:10px;
}
.reportDetails summary{
    cursor:pointer;
    color:#cbd5e1;
    font-weight:700;
}
.reportDetails pre{
    max-height:720px;
}
</style>
</head>
<body>

<div class="toolbar">
  <a class="btn secondary" href="admin.php?page=master">Zurück zur Master-Verwaltung</a>
  <a class="btn <?= $selectedKey === 'app' ? 'active' : '' ?>" href="view_log.php?file=app">App Log</a>
  <a class="btn <?= $selectedKey === 'status' ? 'active' : '' ?>" href="view_log.php?file=status">Status-Snapshots</a>
  <a class="btn <?= $selectedKey === 'trace' ? 'active' : '' ?>" href="view_log.php?file=trace">Player Trace Log</a>
  <a class="btn <?= $selectedKey === 'system_report' ? 'active' : '' ?>" href="view_log.php?file=system_report">Systemauswertung</a>
  <a class="btn secondary" href="view_log.php?file=<?= h($selectedKey) ?>">Neu laden</a>
</div>

<div class="logHeader">
    <div>
        <h1><?= h($selectedLog['label']) ?></h1>
        <p class="small">Datei: <code><?= h($logFile) ?></code></p>
        <p class="small">Angezeigt werden die letzten <?= count($entries) ?> Einträge.</p>
    </div>
    <?php if ($selectedKey === 'system_report'): ?>
        <div>
            <a class="btn" href="system_report.php">Neue Systemauswertung öffnen</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$entries): ?>
  <div class="card">Noch keine Logeinträge vorhanden.</div>
<?php else: ?>
  <?php foreach ($entries as $index => $entry): ?>
    <?php $flatContext = flatten_context($entry['context']); ?>
    <div class="card <?= $selectedKey === 'system_report' ? 'card--compact' : '' ?>">
      <div class="meta">
        <span><?= h($entry['time'] !== '' ? $entry['time'] : 'ohne Zeitstempel') ?></span>
        <span class="level level-<?= h($entry['level']) ?>"><?= h($entry['level']) ?></span>
      </div>

      <div><strong><?= h($entry['message']) ?></strong></div>

      <?php if (!empty($entry['context']['summary'])): ?>
        <div class="summaryBox"><?= h((string)$entry['context']['summary']) ?></div>
      <?php endif; ?>

      <?php if ($selectedKey === 'system_report'): ?>
        <?php if ((string)($entry['summary'] ?? '') !== ''): ?>
            <div class="reportSummary"><?= h((string)$entry['summary']) ?></div>
        <?php endif; ?>

        <details class="reportDetails">
          <summary>Komplette Systemauswertung anzeigen</summary>
          <pre><?= h($entry['raw']) ?></pre>
        </details>
      <?php elseif ($flatContext): ?>
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
        <details style="margin-top:10px">
          <summary class="small">Rohen Eintrag anzeigen</summary>
          <pre><?= h($entry['raw']) ?></pre>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
