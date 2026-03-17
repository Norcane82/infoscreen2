<?php
require_once __DIR__ . '/functions.php';

$dir = __DIR__ . '/data/backups';
$files = [];
if (is_dir($dir)) {
    foreach (glob($dir . '/*.tar.gz') as $f) {
        $files[] = [
            'name' => basename($f),
            'size' => filesize($f),
            'mtime' => filemtime($f)
        ];
    }
    usort($files, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen2 Backups</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f5f5f5;color:#222}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
a.btn{display:inline-block;padding:8px 12px;background:#eee;color:#111;text-decoration:none;border-radius:8px}
</style>
</head>
<body>
<p><a class="btn" href="admin.php">Zurück zur Verwaltung</a></p>
<h1>Backups</h1>
<table>
<thead><tr><th>Datei</th><th>Größe</th><th>Datum</th><th>Download</th></tr></thead>
<tbody>
<?php foreach ($files as $f): ?>
<tr>
  <td><?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?></td>
  <td><?= round($f['size'] / 1024, 1) ?> KB</td>
  <td><?= date('Y-m-d H:i:s', (int)$f['mtime']) ?></td>
  <td><a href="<?= 'data/backups/' . rawurlencode($f['name']) ?>" download>Download</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
</head>
<body>
<p><a class="btn" href="admin.php">Zurück zur Verwaltung</a></p>
<h1>Backups</h1>
<table>
<thead><tr><th>Datei</th><th>Größe</th><th>Datum</th><th>Download</th></tr></thead>
<tbody>
<?php foreach ($files as $f): ?>
<tr>
  <td><?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?></td>
  <td><?= round($f['size'] / 1024, 1) ?> KB</td>
  <td><?= date('Y-m-d H:i:s', (int)$f['mtime']) ?></td>
  <td><a href="<?= 'data/backups/' . rawurlencode($f['name']) ?>" download>Download</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
