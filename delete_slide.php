<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

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
    header('Location: admin.php');
    exit;
}

$isPdfRenderedImage = (($targetSlide['type'] ?? '') === 'image') && (($targetSlide['sourceType'] ?? '') === 'pdf');
$pdfSourceFile = $isPdfRenderedImage ? (string)($targetSlide['sourceFile'] ?? '') : '';
$pdfRenderedFiles = [];
$pdfOriginalFile = '';

if ($isPdfRenderedImage) {
    $pdfOriginalFile = $pdfSourceFile;

    foreach ($slides as $item) {
        if ((($item['type'] ?? '') === 'image') && (($item['sourceType'] ?? '') === 'pdf') && ((string)($item['sourceFile'] ?? '') === $pdfSourceFile)) {
            $file = (string)($item['file'] ?? '');
            if ($file !== '') {
                $pdfRenderedFiles[] = $file;
            }
            continue;
        }

        $newSlides[] = $item;
    }
} else {
    foreach ($slides as $item) {
        if ((string)($item['id'] ?? '') === $id) {
            $file = (string)($item['file'] ?? '');

            if ($file !== '' && str_starts_with($file, 'uploads/')) {
                $fullPath = __DIR__ . '/' . $file;
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            continue;
        }

        $newSlides[] = $item;
    }
}

if ($isPdfRenderedImage) {
    foreach ($pdfRenderedFiles as $file) {
        if ($file !== '' && str_starts_with($file, 'uploads/')) {
            $fullPath = __DIR__ . '/' . $file;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    if ($pdfOriginalFile !== '' && str_starts_with($pdfOriginalFile, 'uploads/')) {
        $fullPath = __DIR__ . '/' . $pdfOriginalFile;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

foreach ($newSlides as $i => &$item) {
    $item['sort'] = ($i + 1) * 10;
}
unset($item);

playlist_save_normalized($newSlides);

header('Location: admin.php');
exit;
