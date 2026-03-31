<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function redirect_admin(): never { header('Location: admin.php'); exit; }
function mark_reload(string $action): void {
    $state = read_json_file(HEALTH_FILE, ['last_restart'=>0,'restarts'=>[],'fallback_active'=>false,'consecutive_failures'=>0,'last_action'=>'none','requested_view'=>'index','reload_requested_at'=>0]);
    $state['last_action'] = $action;
    $state['requested_view'] = !empty($state['fallback_active']) ? 'fallback' : 'index';
    $state['reload_requested_at'] = time();
    write_json_file(HEALTH_FILE, $state);
}
function sanitize_upload_basename(string $name, string $fallback): string {
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($name, PATHINFO_FILENAME));
    $base = trim((string)$base, '_');
    return $base !== '' ? $base : $fallback;
}
function upload_target_for_type(string $type): ?array {
    return match ($type) {
        'image' => ['dir' => UPLOAD_DIR . '/images', 'webPrefix' => 'uploads/images/', 'extensions' => ['png','jpg','jpeg','webp','gif']],
        'video' => ['dir' => UPLOAD_DIR . '/videos', 'webPrefix' => 'uploads/videos/', 'extensions' => ['mp4','webm','mov']],
        'pdf' => ['dir' => UPLOAD_DIR . '/pdf', 'webPrefix' => 'uploads/pdf/', 'extensions' => ['pdf']],
        default => null,
    };
}
function render_pdf_pages_to_png(string $pdfFile, string $outputDir, string $outputBase): array {
    ensure_dir($outputDir);
    $prefix = rtrim($outputDir, '/') . '/' . $outputBase;
    exec('pdftoppm -png ' . escapeshellarg($pdfFile) . ' ' . escapeshellarg($prefix) . ' 2>&1', $output, $code);
    if ($code !== 0) { return []; }
    $files = glob($prefix . '-*.png') ?: [];
    sort($files, SORT_NATURAL);
    return array_values($files);
}

$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];
$mode = (string)($_POST['mode'] ?? 'file');
$hasValidity = (string)($_POST['hasValidity'] ?? '0') === '1';
$validFrom = playlist_normalize_date((string)($_POST['validFrom'] ?? '')) ?: playlist_today_vienna()->format('Y-m-d');
$validUntil = playlist_normalize_date((string)($_POST['validUntil'] ?? '')) ?: playlist_today_vienna()->modify('+10 years')->format('Y-m-d');

if ($mode === 'website') {
    $title = trim((string)($_POST['title'] ?? ''));
    $url = trim((string)($_POST['url'] ?? ''));
    if ($title === '' || $url === '') { redirect_admin(); }
    $slides[] = [
        'id' => 'website-' . date('YmdHis'),
        'type' => 'website',
        'title' => $title,
        'enabled' => (string)($_POST['enabled'] ?? '1') === '1',
        'duration' => max(1, (int)($_POST['duration'] ?? 10)),
        'refreshSeconds' => max(0, (int)($_POST['refreshSeconds'] ?? 0)),
        'timeout' => max(1, (int)($_POST['timeoutSeconds'] ?? 8)),
        'sort' => (count($slides) + 1) * 10,
        'url' => $url,
        'hasValidity' => $hasValidity,
        'validFrom' => $hasValidity ? $validFrom : null,
        'validUntil' => $hasValidity ? $validUntil : null,
    ];
    playlist_save_normalized($slides); mark_reload('upload_website'); redirect_admin();
}

$fileInfo = $_FILES['mediaFile'] ?? null;
if (!is_array($fileInfo) || (int)($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { redirect_admin(); }
$type = strtolower((string)($_POST['type'] ?? 'image'));
$targetInfo = upload_target_for_type($type);
if ($targetInfo === null) { redirect_admin(); }
$originalName = (string)($fileInfo['name'] ?? 'file');
$ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, $targetInfo['extensions'], true)) { redirect_admin(); }
ensure_dir($targetInfo['dir']);
$base = sanitize_upload_basename($originalName, $type);
$fileName = $base . '_' . date('Ymd_His') . '.' . $ext;
$target = $targetInfo['dir'] . '/' . $fileName;
if (!move_uploaded_file((string)$fileInfo['tmp_name'], $target)) { redirect_admin(); }
@chmod($target, 0664);
$title = trim((string)($_POST['title'] ?? $base));
$enabled = (string)($_POST['enabled'] ?? '1') === '1';
$duration = max(1, (int)($_POST['duration'] ?? 10));

if ($type === 'pdf') {
    $renderDir = UPLOAD_DIR . '/pdf_rendered';
    $renderPrefix = $base . '_' . date('Ymd_His');
    $renderedFiles = render_pdf_pages_to_png($target, $renderDir, $renderPrefix);
    $page = 1;
    foreach ($renderedFiles as $renderedFile) {
        $slides[] = [
            'id' => 'image-' . date('YmdHis') . '-' . $page,
            'type' => 'image',
            'title' => $title . ' - Seite ' . $page,
            'sourceTitle' => $title,
            'sourceType' => 'pdf',
            'sourceFile' => 'uploads/pdf/' . $fileName,
            'page' => $page,
            'enabled' => $enabled,
            'duration' => $duration,
            'fade' => 1.2,
            'sort' => (count($slides) + 1) * 10,
            'fit' => 'contain',
            'file' => 'uploads/pdf_rendered/' . basename($renderedFile),
            'hasValidity' => $hasValidity,
            'validFrom' => $hasValidity ? $validFrom : null,
            'validUntil' => $hasValidity ? $validUntil : null,
        ];
        $page++;
    }
} else {
    $slides[] = [
        'id' => $type . '-' . date('YmdHis'),
        'type' => $type,
        'title' => $title,
        'enabled' => $enabled,
        'duration' => $duration,
        'sort' => (count($slides) + 1) * 10,
        'file' => $targetInfo['webPrefix'] . $fileName,
        'fit' => 'contain',
        'fade' => 1.2,
        'muted' => (string)($_POST['muted'] ?? '1') === '1',
        'videoMode' => 'until_end',
        'hasValidity' => $hasValidity,
        'validFrom' => $hasValidity ? $validFrom : null,
        'validUntil' => $hasValidity ? $validUntil : null,
    ];
}

playlist_save_normalized($slides); mark_reload('upload_file'); redirect_admin();
