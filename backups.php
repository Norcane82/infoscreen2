<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$dir = __DIR__ . '/data/backups';
$files = [];
if (is_dir($dir)) {
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (!is_file($path)) {
            continue;
        }
        $files[] = ['name' => $name, 'size' => filesize($path) ?: 0, 'mtime' => filemtime($path) ?: 0];
    }
}
usort($files, static fn(array $a, array $b): int => (int)$b['mtime'] <=> (int)$a['mtime']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen2 Backups</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f5f5f5;color:#222}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}a.btn,button.btn{display:inline-block;padding:8px 12px;background:#eee;color:#111;text-decoration:none;border-radius:8px;border:0;cursor:pointer}form.inline{display:inline}
</style>
</head>
<body>
<p><a class="btn" href="admin.php">Zurück zur Verwaltung</a></p>
<h1>Backups</h1>
<table>
<thead><tr><th>Datei</th><th>Größe</th><th>Datum</th><th>Aktionen</th></tr></thead>
<tbody>
<?php foreach ($files as $f): ?>
<tr>
<td><?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= round($f['size'] / 1024, 1) ?> KB</td>
<td><?= date('Y-m-d H:i:s', (int)$f['mtime']) ?></td>
<td>
<a class="btn" href="<?= 'data/backups/' . rawurlencode($f['name']) ?>" download>Download</a>
<form class="inline" action="delete_backup.php" method="post" onsubmit="return confirm('Backup wirklich löschen?');">
<input type="hidden" name="name" value="<?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>">
<button class="btn" type="submit">Löschen</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
