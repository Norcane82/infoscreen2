<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function redirect_admin_cleanup(): void
{
    header('Location: admin.php');
    exit;
}

function normalize_relative_upload_path(string $path): ?string
{
    $path = trim(str_replace('\\', '/', $path));

    if ($path === '' || !str_starts_with($path, 'uploads/')) {
        return null;
    }

    return $path;
}

function collect_referenced_files(array $config, array $slides): array
{
    $referenced = [];

    $add = static function ($path) use (&$referenced): void {
        if (!is_string($path)) {
            return;
        }

        $normalized = normalize_relative_upload_path($path);
        if ($normalized === null) {
            return;
        }

        $referenced[$normalized] = true;
    };

    $add($config['clock']['logo'] ?? null);

    foreach ($slides as $slide) {
        if (!is_array($slide)) {
            continue;
        }

        $add($slide['file'] ?? null);
        $add($slide['sourceFile'] ?? null);

        if (($slide['type'] ?? '') === 'clock' && isset($slide['clock']) && is_array($slide['clock'])) {
            $add($slide['clock']['logo'] ?? null);
        }
    }

    return $referenced;
}

function collect_candidate_files(array $directories): array
{
    $files = [];

    foreach ($directories as $dir) {
        $fullDir = __DIR__ . '/' . $dir;
        if (!is_dir($fullDir)) {
            continue;
        }

        $entries = scandir($fullDir);
        if ($entries === false) {
            continue;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }

            $relativePath = $dir . '/' . $entry;
            $fullPath = __DIR__ . '/' . $relativePath;

            if (is_file($fullPath)) {
                $files[] = str_replace('\\', '/', $relativePath);
            }
        }
    }

    sort($files);
    return $files;
}

function write_cleanup_log(array $deletedFiles, array $keptFiles): void
{
    $line = json_encode([
        'time' => date('c'),
        'level' => 'INFO',
        'message' => 'Orphan cleanup finished',
        'context' => [
            'deleted_count' => count($deletedFiles),
            'kept_count' => count($keptFiles),
            'deleted_files' => $deletedFiles,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($line === false) {
        return;
    }

    $logFile = __DIR__ . '/data/logs/app.log';
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$config = load_config();
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

$referencedFiles = collect_referenced_files($config, $slides);

$candidateDirectories = [
    'uploads',
    'uploads/images',
    'uploads/videos',
    'uploads/pdf',
    'uploads/pdf_rendered',
    'uploads/websites',
    'uploads/clock',
];

$candidateFiles = collect_candidate_files($candidateDirectories);

$deletedFiles = [];
$keptFiles = [];

foreach ($candidateFiles as $relativePath) {
    if (isset($referencedFiles[$relativePath])) {
        $keptFiles[] = $relativePath;
        continue;
    }

    $fullPath = __DIR__ . '/' . $relativePath;
    if (is_file($fullPath) && @unlink($fullPath)) {
        $deletedFiles[] = $relativePath;
    }
}

write_cleanup_log($deletedFiles, $keptFiles);

redirect_admin_cleanup();
