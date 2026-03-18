<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$config = load_config();
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

$type = strtolower(trim((string)($_POST['type'] ?? '')));
$title = trim((string)($_POST['title'] ?? ''));
$duration = max(1, (int)($_POST['duration'] ?? ($config['screen']['defaultDuration'] ?? 8)));
$enabled = !empty($_POST['enabled']);

$maxSort = 0;
foreach ($slides as $item) {
    $maxSort = max($maxSort, (int)($item['sort'] ?? 0));
}
$newSort = $maxSort + 10;

function redirect_admin(): void
{
    header('Location: admin.php');
    exit;
}

function sanitize_upload_basename(string $name, string $fallback): string
{
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($name, PATHINFO_FILENAME));
    $base = trim((string)$base, '_');
    return $base !== '' ? $base : $fallback;
}

function upload_target_for_type(string $type): ?array
{
    return match ($type) {
        'image' => [
            'dir' => UPLOAD_DIR . '/images',
            'webPrefix' => 'uploads/images/',
            'extensions' => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        ],
        'video' => [
            'dir' => UPLOAD_DIR . '/videos',
            'webPrefix' => 'uploads/videos/',
            'extensions' => ['mp4', 'webm', 'mov'],
        ],
        'pdf' => [
            'dir' => UPLOAD_DIR . '/pdf',
            'webPrefix' => 'uploads/pdf/',
            'extensions' => ['pdf'],
        ],
        default => null,
    };
}

function render_pdf_pages_to_png(string $pdfFile, string $outputDir, string $outputBase): array
{
    ensure_dir($outputDir);

    $prefix = rtrim($outputDir, '/') . '/' . $outputBase;
    $cmd = 'pdftoppm -png ' . escapeshellarg($pdfFile) . ' ' . escapeshellarg($prefix) . ' 2>&1';

    exec($cmd, $output, $code);

    if ($code !== 0) {
        return [];
    }

    $files = glob($prefix . '-*.png');
    if ($files === false) {
        return [];
    }

    natsort($files);
    return array_values($files);
}

if ($type === 'website') {
    $url = trim((string)($_POST['url'] ?? ''));

    if ($url === '') {
        redirect_admin();
    }

    $slides[] = playlist_normalize_slide([
        'id' => uuid_like('website'),
        'type' => 'website',
        'title' => $title !== '' ? $title : $url,
        'url' => $url,
        'duration' => $duration,
        'enabled' => $enabled,
        'sort' => $newSort,
        'bg' => $config['screen']['background'] ?? '#ffffff',
    ], count($slides), $config);

    playlist_save_normalized($slides);
    redirect_admin();
}

if (empty($_FILES['mediaFile']['name']) || !is_uploaded_file($_FILES['mediaFile']['tmp_name'])) {
    redirect_admin();
}

$originalName = (string)$_FILES['mediaFile']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$imageExt = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
$videoExt = ['mp4', 'webm', 'mov'];
$pdfExt = ['pdf'];

if (!in_array($type, ['image', 'video', 'pdf'], true)) {
    if (in_array($ext, $imageExt, true)) {
        $type = 'image';
    } elseif (in_array($ext, $videoExt, true)) {
        $type = 'video';
    } elseif (in_array($ext, $pdfExt, true)) {
        $type = 'pdf';
    } else {
        redirect_admin();
    }
}

$targetInfo = upload_target_for_type($type);
if ($targetInfo === null) {
    redirect_admin();
}

if (!in_array($ext, $targetInfo['extensions'], true)) {
    redirect_admin();
}

ensure_dir($targetInfo['dir']);

$base = sanitize_upload_basename($originalName, $type);
$fileName = $base . '_' . date('Ymd_His') . '.' . $ext;
$target = $targetInfo['dir'] . '/' . $fileName;

if (!move_uploaded_file((string)$_FILES['mediaFile']['tmp_name'], $target)) {
    redirect_admin();
}

@chmod($target, 0664);

if ($type === 'pdf') {
    $renderDir = UPLOAD_DIR . '/pdf_rendered';
    $renderPrefix = $base . '_' . date('Ymd_His');
    $renderedFiles = render_pdf_pages_to_png($target, $renderDir, $renderPrefix);

    if ($renderedFiles === []) {
        redirect_admin();
    }

    $sort = $newSort;
    $page = 1;

    foreach ($renderedFiles as $renderedFile) {
        @chmod($renderedFile, 0664);

        $renderedName = basename($renderedFile);
        $pageTitle = $title !== '' ? ($title . ' - Seite ' . $page) : ($base . ' - Seite ' . $page);

        $slides[] = playlist_normalize_slide([
            'id' => uuid_like('pdfimg'),
            'type' => 'image',
            'title' => $pageTitle,
            'file' => 'uploads/pdf_rendered/' . $renderedName,
            'duration' => $duration,
            'enabled' => $enabled,
            'sort' => $sort,
            'bg' => $config['screen']['background'] ?? '#ffffff',
            'fit' => $config['screen']['fit'] ?? 'contain',
            'sourceType' => 'pdf',
            'sourceFile' => $targetInfo['webPrefix'] . $fileName,
            'sourceTitle' => $title !== '' ? $title : $base,
            'page' => $page,
        ], count($slides), $config);

        $sort += 10;
        $page++;
    }

    playlist_save_normalized($slides);
    redirect_admin();
}

$item = [
    'id' => uuid_like($type),
    'type' => $type,
    'title' => $title !== '' ? $title : $base,
    'file' => $targetInfo['webPrefix'] . $fileName,
    'duration' => $duration,
    'enabled' => $enabled,
    'sort' => $newSort,
    'bg' => $config['screen']['background'] ?? '#ffffff',
    'fit' => $config['screen']['fit'] ?? 'contain',
];

if ($type === 'video') {
    $item['muted'] = !empty($_POST['muted']);
}

$slides[] = playlist_normalize_slide($item, count($slides), $config);
playlist_save_normalized($slides);

redirect_admin();
