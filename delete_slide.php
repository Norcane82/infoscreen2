<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$id = trim((string)($_POST['id'] ?? ''));
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];
$newSlides = [];

foreach ($slides as $item) {
    if ((string)($item['id'] ?? '') === $id) {
        $file = (string)($item['file'] ?? '');

        if ($file !== '' && str_starts_with($file, 'uploads/')) {
            $fullPath = __DIR__ . '/' . $file;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        continue;
    }

    $newSlides[] = $item;
}

foreach ($newSlides as $i => &$item) {
    $item['sort'] = ($i + 1) * 10;
}
unset($item);

playlist_save_normalized($newSlides);

header('Location: admin.php');
exit;
