<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$id = trim((string)($_POST['id'] ?? ''));
$dir = trim((string)($_POST['dir'] ?? ''));

$playlistData = playlist_load_normalized();
$slides = array_values($playlistData['slides'] ?? []);

$index = null;
foreach ($slides as $i => $item) {
    if ((string)($item['id'] ?? '') === $id) {
        $index = $i;
        break;
    }
}

if ($index !== null) {
    if ($dir === 'up' && $index > 0) {
        $tmp = $slides[$index - 1];
        $slides[$index - 1] = $slides[$index];
        $slides[$index] = $tmp;
    }

    if ($dir === 'down' && $index < count($slides) - 1) {
        $tmp = $slides[$index + 1];
        $slides[$index + 1] = $slides[$index];
        $slides[$index] = $tmp;
    }

    foreach ($slides as $i => &$item) {
        $item['sort'] = ($i + 1) * 10;
    }
    unset($item);

    playlist_save_normalized($slides);
}

header('Location: admin.php');
exit;
