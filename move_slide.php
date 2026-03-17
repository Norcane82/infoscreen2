<?php
require_once __DIR__ . '/functions.php';

$id = (string)($_POST['id'] ?? '');
$dir = (string)($_POST['dir'] ?? '');
$config = loadConfig();
$playlist = normalizePlaylist(loadPlaylist(), $config);

$index = null;
foreach ($playlist as $i => $item) {
    if (($item['id'] ?? '') === $id) {
        $index = $i;
        break;
    }
}

if ($index !== null) {
    if ($dir === 'up' && $index > 0) {
        $tmp = $playlist[$index - 1];
        $playlist[$index - 1] = $playlist[$index];
        $playlist[$index] = $tmp;
    }
    if ($dir === 'down' && $index < count($playlist) - 1) {
        $tmp = $playlist[$index + 1];
        $playlist[$index + 1] = $playlist[$index];
        $playlist[$index] = $tmp;
    }

    foreach ($playlist as $i => &$item) {
        $item['sort'] = ($i + 1) * 10;
    }
    unset($item);

    savePlaylist($playlist);
}

header('Location: admin.php');
exit;
