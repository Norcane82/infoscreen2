<?php
require_once __DIR__ . '/functions.php';

$id = (string)($_POST['id'] ?? '');
$config = loadConfig();
$playlist = loadPlaylist();
$new = [];

foreach ($playlist as $item) {
    if (($item['id'] ?? '') === $id) {
        if (!empty($item['file'])) {
            $file = __DIR__ . '/uploads/' . basename((string)$item['file']);
            if (is_file($file)) {
                @unlink($file);
            }
        }
        continue;
    }
    $new[] = $item;
}

savePlaylist(normalizePlaylist($new, $config));
header('Location: admin.php');
exit;
