<?php
require_once __DIR__ . '/functions.php';

$config = loadConfig();
$playlist = loadPlaylist();
$id = (string)($_POST['id'] ?? '');

foreach ($playlist as &$item) {
    if (($item['id'] ?? '') === $id) {
        $item['enabled'] = empty($item['enabled']);
        break;
    }
}
unset($item);

savePlaylist(normalizePlaylist($playlist, $config));
header('Location: admin.php');
exit;
