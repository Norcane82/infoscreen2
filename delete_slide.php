<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function redirect_admin_delete(): void
{
    header('Location: admin.php');
    exit;
}

function web_path_to_full_path(string $path): ?string
{
    $path = trim($path);

    if ($path === '' || !str_starts_with($path, 'uploads/')) {
        return null;
    }

    return __DIR__ . '/' . $path;
}

function delete_file_if_exists(string $path): void
{
    $fullPath = web_path_to_full_path($path);
    if ($fullPath === null) {
        return;
    }

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function rendered_prefix_from_source_file(string $sourceFile): ?string
{
    $baseName = pathinfo($sourceFile, PATHINFO_FILENAME);
    if ($baseName === '') {
        return null;
    }

    return $baseName;
}

$id = trim((string)($_POST['id'] ?? ''));
$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];
$newSlides = [];

$targetSlide = null;
foreach ($slides as $item) {
    if ((string)($item['id'] ?? '') === $id) {
        $targetSlide = $item;
        break;
    }
}

if ($targetSlide === null) {
    redirect_admin_delete();
}

$isPdfRenderedImage = (($targetSlide['type'] ?? '') === 'image') && (($targetSlide['sourceType'] ?? '') === 'pdf');

if ($isPdfRenderedImage) {
    $pdfSourceFile = (string)($targetSlide['sourceFile'] ?? '');
    $pdfRenderedFiles = [];

    foreach ($slides as $item) {
        $samePdfGroup =
            (($item['type'] ?? '') === 'image') &&
            (($item['sourceType'] ?? '') === 'pdf') &&
            ((string)($item['sourceFile'] ?? '') === $pdfSourceFile);

        if ($samePdfGroup) {
            $file = (string)($item['file'] ?? '');
            if ($file !== '') {
                $pdfRenderedFiles[] = $file;
            }
            continue;
        }

        $newSlides[] = $item;
    }

    foreach ($pdfRenderedFiles as $file) {
        delete_file_if_exists($file);
    }

    delete_file_if_exists($pdfSourceFile);

    $renderedPrefix = rendered_prefix_from_source_file($pdfSourceFile);
    if ($renderedPrefix !== null) {
        $matches = glob(__DIR__ . '/uploads/pdf_rendered/' . $renderedPrefix . '-*.png');
        if (is_array($matches)) {
            foreach ($matches as $match) {
                if (is_file($match)) {
                    @unlink($match);
                }
            }
        }
    }
} else {
    foreach ($slides as $item) {
        if ((string)($item['id'] ?? '') === $id) {
            $file = (string)($item['file'] ?? '');
            delete_file_if_exists($file);
            continue;
        }

        $newSlides[] = $item;
    }
}

foreach ($newSlides as $i => &$item) {
    $item['sort'] = ($i + 1) * 10;
}
unset($item);

playlist_save_normalized($newSlides);

redirect_admin_delete();
