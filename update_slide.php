<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$config = load_config();
$id = trim((string)($_POST['id'] ?? ''));

$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

foreach ($slides as &$item) {
    if ((string)($item['id'] ?? '') !== $id) {
        continue;
    }

    $type = (string)($item['type'] ?? '');

    $item['title'] = trim((string)($_POST['title'] ?? ($item['title'] ?? '')));
    $item['enabled'] = !empty($_POST['enabled']);
    $item['duration'] = max(1, (int)($_POST['duration'] ?? ($item['duration'] ?? 8)));

    if ($type === 'image') {
        $fit = (string)($_POST['fit'] ?? ($item['fit'] ?? 'contain'));
        $item['fit'] = in_array($fit, ['contain', 'cover'], true) ? $fit : 'contain';
        $item['fade'] = max(0, (float)($_POST['fade'] ?? ($item['fade'] ?? ($config['screen']['defaultFade'] ?? 1))));
    }

    if ($type === 'video') {
        $mode = (string)($_POST['videoMode'] ?? ($item['videoMode'] ?? 'until_end'));
        $item['videoMode'] = in_array($mode, ['until_end', 'fixed'], true) ? $mode : 'until_end';
        $item['muted'] = !empty($_POST['muted']);
    }

    if ($type === 'website') {
        $item['url'] = trim((string)($_POST['url'] ?? ($item['url'] ?? '')));
        $item['refreshSeconds'] = max(0, (int)($_POST['refreshSeconds'] ?? ($item['refreshSeconds'] ?? 0)));
    }

    if ($type === 'clock') {
        $item['duration'] = max(1, (int)($_POST['duration'] ?? ($config['clock']['defaultDuration'] ?? 10)));
    }

    break;
}
unset($item);

playlist_save_normalized($slides);

header('Location: admin.php');
exit;
