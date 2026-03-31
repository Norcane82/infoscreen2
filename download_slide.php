<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

$id = trim((string)($_GET['id'] ?? ''));
$playlist = playlist_load_normalized();
$slide = playlist_find_slide($playlist, $id);
if (!$slide || empty($slide['file'])) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

$relative = ltrim((string)$slide['file'], '/');
$path = __DIR__ . '/' . $relative;
$real = realpath($path);
$root = realpath(__DIR__ . '/uploads');
if ($real === false || $root === false || strncmp($real, $root, strlen($root)) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

$filename = basename($real);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . (string)filesize($real));
readfile($real);
exit;
