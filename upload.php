<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: admin.php');
    exit;
}

$config = load_config();
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

$type = strtolower(trim((string)($_POST['type'] ?? '')));
$mode = strtolower(trim((string)($_POST['mode'] ?? '')));
$title = trim((string)($_POST['title'] ?? ''));
$duration = max(1, (int)($_POST['duration'] ?? ($config['screen']['defaultDuration'] ?? 8)));
$enabled = !empty($_POST['enabled']);

if ($mode === 'website' && $type === '') {
    $type = 'website';
}

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

function upload_log(string $level, string $message, array $context = []): void
{
    if (function_exists('app_log')) {
        app_log($level, $message, $context);
    }
}

function request_player_refresh_after_playlist_change(string $action): void
{
    $state = read_json_file(HEALTH_FILE, [
        'last_restart' => 0,
        'restarts' => [],
        'fallback_active' => false,
        'consecutive_failures' => 0,
        'last_action' => 'none',
        'requested_view' => 'index',
        'reload_requested_at' => 0,
    ]);

    $state['last_action'] = $action;
    $state['requested_view'] = !empty($state['fallback_active']) ? 'fallback' : 'index';
    $state['reload_requested_at'] = time();

    write_json_file(HEALTH_FILE, $state);
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
        upload_log('error', 'PDF render failed', [
            'file' => $pdfFile,
            'code' => $code,
            'output' => implode("\n", $output),
        ]);
        return [];
    }

    $files = glob($prefix . '-*.png');
    if ($files === false) {
        upload_log('error', 'PDF render glob failed', [
            'prefix' => $prefix,
        ]);
        return [];
    }

    natsort($files);
    return array_values($files);
}

function save_playlist_or_redirect(array $slides, string $successAction): void
{
    if (!playlist_save_normalized($slides)) {
        upload_log('error', 'Playlist save failed after upload', [
            'slides_count' => count($slides),
            'action' => $successAction,
        ]);
        redirect_admin();
    }

    request_player_refresh_after_playlist_change($successAction);
    redirect_admin();
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_OK => 'OK',
        UPLOAD_ERR_INI_SIZE => 'Datei groesser als upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'Datei groesser als MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'Datei nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
        UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht auf Datentraeger geschrieben werden',
        UPLOAD_ERR_EXTENSION => 'Upload durch PHP-Erweiterung gestoppt',
        default => 'Unbekannter Upload-Fehler',
    };
}

if ($type === 'website') {
    $url = trim((string)($_POST['url'] ?? ''));
    $refreshSeconds = max(0, (int)($_POST['refreshSeconds'] ?? 0));
    $timeout = max(1, (int)($_POST['timeoutSeconds'] ?? $_POST['timeout'] ?? 8));

    if ($url === '') {
        upload_log('error', 'Website upload aborted: empty URL');
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
        'refreshSeconds' => $refreshSeconds,
        'timeout' => $timeout,
    ], count($slides), $config);

    save_playlist_or_redirect($slides, 'upload_website');
}

if (!isset($_FILES['mediaFile']) || !is_array($_FILES['mediaFile'])) {
    upload_log('error', 'Upload aborted: mediaFile missing', [
        'post_keys' => array_keys($_POST),
        'file_keys' => array_keys($_FILES),
        'content_length' => (int)($_SERVER['CONTENT_LENGTH'] ?? 0),
    ]);
    redirect_admin();
}

$fileInfo = $_FILES['mediaFile'];
$uploadError = (int)($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);

if ($uploadError !== UPLOAD_ERR_OK) {
    upload_log('error', 'Upload aborted: PHP upload error', [
        'error_code' => $uploadError,
        'error_message' => upload_error_message($uploadError),
        'name' => (string)($fileInfo['name'] ?? ''),
        'size' => (int)($fileInfo['size'] ?? 0),
        'content_length' => (int)($_SERVER['CONTENT_LENGTH'] ?? 0),
        'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
        'post_max_size' => (string)ini_get('post_max_size'),
    ]);
    redirect_admin();
}

if (empty($fileInfo['name']) || empty($fileInfo['tmp_name'])) {
    upload_log('error', 'Upload aborted: file name or tmp_name empty', [
        'name' => (string)($fileInfo['name'] ?? ''),
        'tmp_name' => (string)($fileInfo['tmp_name'] ?? ''),
        'size' => (int)($fileInfo['size'] ?? 0),
    ]);
    redirect_admin();
}

if (!is_uploaded_file((string)$fileInfo['tmp_name'])) {
    upload_log('error', 'Upload aborted: tmp file is not a valid uploaded file', [
        'tmp_name' => (string)$fileInfo['tmp_name'],
        'name' => (string)$fileInfo['name'],
        'size' => (int)($fileInfo['size'] ?? 0),
    ]);
    redirect_admin();
}

$originalName = (string)$fileInfo['name'];
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
        upload_log('error', 'Upload aborted: unsupported extension', [
            'original_name' => $originalName,
            'extension' => $ext,
            'requested_type' => $type,
        ]);
        redirect_admin();
    }
}

$targetInfo = upload_target_for_type($type);
if ($targetInfo === null) {
    upload_log('error', 'Upload aborted: unknown target type', [
        'type' => $type,
    ]);
    redirect_admin();
}

if (!in_array($ext, $targetInfo['extensions'], true)) {
    upload_log('error', 'Upload aborted: extension not allowed for type', [
        'type' => $type,
        'extension' => $ext,
        'allowed' => $targetInfo['extensions'],
    ]);
    redirect_admin();
}

ensure_dir($targetInfo['dir']);

$base = sanitize_upload_basename($originalName, $type);
$fileName = $base . '_' . date('Ymd_His') . '.' . $ext;
$target = $targetInfo['dir'] . '/' . $fileName;

if (!move_uploaded_file((string)$fileInfo['tmp_name'], $target)) {
    upload_log('error', 'Upload move failed', [
        'target' => $target,
        'target_dir' => $targetInfo['dir'],
        'target_dir_exists' => is_dir($targetInfo['dir']),
        'target_dir_writable' => is_writable($targetInfo['dir']),
        'original' => $originalName,
        'type' => $type,
        'tmp_name' => (string)$fileInfo['tmp_name'],
        'size' => (int)($fileInfo['size'] ?? 0),
    ]);
    redirect_admin();
}

@chmod($target, 0664);

if ($type === 'pdf') {
    $renderDir = UPLOAD_DIR . '/pdf_rendered';
    $renderPrefix = $base . '_' . date('Ymd_His');
    $renderedFiles = render_pdf_pages_to_png($target, $renderDir, $renderPrefix);

    if ($renderedFiles === []) {
        upload_log('error', 'Upload aborted: PDF rendered no pages', [
            'target' => $target,
        ]);
        redirect_admin();
    }

    $sort = $newSort;
    $page = 1;

    foreach ($renderedFiles as $renderedFile) {
        @chmod($renderedFile, 0664);
        $renderedName = basename($renderedFile);
        $pageTitle = $title !== ''
            ? ($title . ' - Seite ' . $page)
            : ($base . ' - Seite ' . $page);

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

    save_playlist_or_redirect($slides, 'upload_pdf');
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

save_playlist_or_redirect($slides, 'upload_media');
