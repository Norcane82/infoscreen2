<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';

function playlist_normalize_slide(array $slide, int $index = 0, array $config = []): array
{
    $screen = $config['screen'] ?? [];
    $clock = $config['clock'] ?? [];

    $type = strtolower((string)($slide['type'] ?? 'image'));
    $defaultDuration = (float)($screen['defaultDuration'] ?? 8);
    $defaultFade = (float)($screen['defaultFade'] ?? 1);
    $defaultBackground = (string)($screen['background'] ?? '#ffffff');
    $defaultFit = (string)($screen['fit'] ?? 'contain');
    $defaultClockDuration = (float)($clock['defaultDuration'] ?? 10);

    $normalized = [
        'id' => (string)($slide['id'] ?? ('slide_' . ($index + 1))),
        'type' => $type,
        'title' => (string)($slide['title'] ?? ('Slide ' . ($index + 1))),
        'enabled' => array_key_exists('enabled', $slide) ? (bool)$slide['enabled'] : true,
        'duration' => isset($slide['duration'])
            ? (float)$slide['duration']
            : ($type === 'clock' ? $defaultClockDuration : $defaultDuration),
        'fade' => isset($slide['fade']) ? (float)$slide['fade'] : $defaultFade,
        'sort' => isset($slide['sort']) ? (int)$slide['sort'] : (($index + 1) * 10),
        'bg' => (string)($slide['bg'] ?? $defaultBackground),
        'fit' => (string)($slide['fit'] ?? $defaultFit),
    ];

    if (isset($slide['file'])) {
        $normalized['file'] = (string)$slide['file'];
    }

    if (isset($slide['url'])) {
        $normalized['url'] = (string)$slide['url'];
    }

    if ($type === 'website') {
        $normalized['refreshSeconds'] = isset($slide['refreshSeconds']) ? max(0, (int)$slide['refreshSeconds']) : 0;
        $normalized['timeout'] = isset($slide['timeout']) ? max(1, (int)$slide['timeout']) : 8;
    }

    if ($type === 'clock') {
        $normalized['clock'] = is_array($slide['clock'] ?? null) ? $slide['clock'] : [];
    }

    if (isset($slide['sourceType'])) {
        $normalized['sourceType'] = (string)$slide['sourceType'];
    }

    if (isset($slide['sourceFile'])) {
        $normalized['sourceFile'] = (string)$slide['sourceFile'];
    }

    if (isset($slide['sourceTitle'])) {
        $normalized['sourceTitle'] = (string)$slide['sourceTitle'];
    }

    if (isset($slide['page'])) {
        $normalized['page'] = (int)$slide['page'];
    }

    return $normalized;
}

function playlist_load_normalized(): array
{
    $config = load_config();
    $playlist = load_playlist();
    $slides = $playlist['slides'] ?? [];

    $normalizedSlides = [];
    foreach ($slides as $index => $slide) {
        if (!is_array($slide)) {
            continue;
        }

        $normalizedSlides[] = playlist_normalize_slide($slide, $index, $config);
    }

    usort($normalizedSlides, static function (array $a, array $b): int {
        return (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0);
    });

    return [
        'version' => 2,
        'slides' => $normalizedSlides,
    ];
}

function playlist_find_slide_index(array $playlist, string $slideId): ?int
{
    foreach (($playlist['slides'] ?? []) as $index => $slide) {
        if ((string)($slide['id'] ?? '') === $slideId) {
            return $index;
        }
    }

    return null;
}

function playlist_find_slide(array $playlist, string $slideId): ?array
{
    $index = playlist_find_slide_index($playlist, $slideId);
    if ($index === null) {
        return null;
    }

    return $playlist['slides'][$index] ?? null;
}

function playlist_save_normalized(array $slides): bool
{
    $config = load_config();
    $normalizedSlides = [];

    foreach (array_values($slides) as $index => $slide) {
        if (!is_array($slide)) {
            continue;
        }

        $normalizedSlides[] = playlist_normalize_slide($slide, $index, $config);
    }

    usort($normalizedSlides, static function (array $a, array $b): int {
        return (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0);
    });

    return save_playlist([
        'version' => 2,
        'slides' => $normalizedSlides,
    ]);
}
