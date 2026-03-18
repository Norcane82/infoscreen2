<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$id = trim((string)($_POST['id'] ?? ''));
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

foreach ($slides as &$item) {
    if ((string)($item['id'] ?? '') === $id) {
        $item['enabled'] = empty($item['enabled']);
        break;
    }
}
unset($item);

playlist_save_normalized($slides);

header('Location: admin.php');
exit;
