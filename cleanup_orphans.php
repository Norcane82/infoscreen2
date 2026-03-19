<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function redirect_admin_cleanup(int $deletedCount = 0, int $keptCount = 0): void
{
    header('Location: admin.php?cleanup=1&deleted=' . $deletedCount . '&kept=' . $keptCount);
    exit;
}

function normalize_relative_path(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    return ltrim($path, '/');
}

function collect_referenced_files(array $config, array $slides): array
{
    $referenced = [];

    $clockLogo = (string)($config['clock']['logo'] ?? '');
    if ($clockLogo !== '') {
        $referenced[normalize_relative_path($clockLogo)] = true;
    }

    foreach ($slides as $slide) {
        if (!is_array($slide)) {
            continue;
        }

        foreach (['file', 'sourceFile'] as $key) {
            $value = trim((string)($slide[$key] ?? ''));
            if ($value !== '') {
                $referenced[normalize_relative_path($value)] = true;
            }
        }

        $clockLogoSlide = trim((string)($slide['clock']['logo'] ?? ''));
        if ($clockLogoSlide !== '') {
            $referenced[normalize_relative_path($clockLogoSlide)] = true;
        }
    }

    return $referenced;
}

function collect_candidate_files(array $directories): array
{
    $files = [];

    foreach ($directories as $relativeDir) {
        $fullDir = __DIR__ . '/' . $relativeDir;
        if (!is_dir($fullDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $fullPath = str_replace('\\', '/', $item->getPathname());
            $root = str_replace('\\', '/', __DIR__) . '/';
            if (str_starts_with($fullPath, $root)) {
                $files[] = substr($fullPath, strlen($root));
            }
        }
    }

    $files = array_map('normalize_relative_path', $files);
    $files = array_values(array_unique($files));
    sort($files);

    return $files;
}

function write_cleanup_log(array $deletedFiles, array $keptFiles): void
{
    $entry = [
        'time' => date('c'),
        'level' => 'INFO',
        'message' => 'Orphan cleanup finished',
        'context' => [
            'deleted_count' => count($deletedFiles),
            'kept_count' => count($keptFiles),
            'deleted_files' => $deletedFiles,
        ],
    ];

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
redirect_admin_cleanup(count($deletedFiles), count($keptFiles));
