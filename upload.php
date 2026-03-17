<?php
require_once __DIR__ . '/functions.php';

$config = loadConfig();
$playlist = loadPlaylist();

$type = $_POST['type'] ?? '';
$title = trim((string)($_POST['title'] ?? ''));
$duration = max(1, (int)($_POST['duration'] ?? ($config['player']['defaultDuration'] ?? 8)));
$enabled = !empty($_POST['enabled']);

$maxSort = 0;
foreach ($playlist as $item) {
    $maxSort = max($maxSort, (int)($item['sort'] ?? 0));
}
$newSort = $maxSort + 10;

if ($type === 'website') {
    $url = trim((string)($_POST['url'] ?? ''));
    if ($url !== '') {
        $playlist[] = normalizeSlide([
            'id' => generateId('website'),
            'type' => 'website',
            'title' => $title !== '' ? $title : $url,
            'url' => $url,
            'duration' => $duration,
            'refreshSeconds' => max(0, (int)($_POST['refreshSeconds'] ?? 0)),
            'enabled' => $enabled,
            'sort' => $newSort
        ], $config);
    }
    savePlaylist($playlist);
    header('Location: admin.php');
    exit;
}

if (empty($_FILES['mediaFile']['name']) || !is_uploaded_file($_FILES['mediaFile']['tmp_name'])) {
    header('Location: admin.php');
    exit;
}

$ext = strtolower(pathinfo($_FILES['mediaFile']['name'], PATHINFO_EXTENSION));
$imageExt = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
$videoExt = ['mp4', 'webm', 'mov'];

if ($type !== 'image' && $type !== 'video') {
    if (in_array($ext, $imageExt, true)) {
        $type = 'image';
    } elseif (in_array($ext, $videoExt, true)) {
        $type = 'video';
    } else {
        header('Location: admin.php');
        exit;
    }
}

$allowed = $type === 'video' ? $videoExt : $imageExt;
if (!in_array($ext, $allowed, true)) {
    header('Location: admin.php');
    exit;
}
$base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($_FILES['mediaFile']['name'], PATHINFO_FILENAME));
$base = trim((string)$base, '_');
if ($base === '') {
    $base = $type;
}

$fileName = $base . '_' . date('Ymd_His') . '.' . $ext;
$target = __DIR__ . '/uploads/' . $fileName;

if (!move_uploaded_file($_FILES['mediaFile']['tmp_name'], $target)) {
    header('Location: admin.php');
    exit;
}

@chmod($target, 0664);

$item = [
    'id' => generateId($type),
    'type' => $type,
    'title' => $title !== '' ? $title : $base,
    'file' => $fileName,
    'duration' => $duration,
    'enabled' => $enabled,
    'sort' => $newSort
];

if ($type === 'video') {
    $item['muted'] = !empty($_POST['muted']);
}
$playlist[] = normalizeSlide($item, $config);
savePlaylist($playlist);

header('Location: admin.php');
exit;
