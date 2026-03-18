pi@anthias-pi:/var/www/html/infoscreen2 $ cd /var/www/html/infoscreen2
sed -n '1,220p' toggle_slide.php
printf '\n---- MOVE ----\n'
sed -n '1,260p' move_slide.php
printf '\n---- DELETE ----\n'
sed -n '1,220p' delete_slide.php
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

---- MOVE ----
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

---- DELETE ----
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
pi@anthias-pi:/var/www/html/infoscreen2 $
