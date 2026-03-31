<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function playlist_vienna_timezone(): DateTimeZone
{
    static $tz = null;
    if ($tz instanceof DateTimeZone) {
        return $tz;
    }
    $tz = new DateTimeZone('Europe/Vienna');
    return $tz;
}

function playlist_today_vienna(): DateTimeImmutable
{
    return new DateTimeImmutable('now', playlist_vienna_timezone());
}

function playlist_normalize_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value, playlist_vienna_timezone());
    return $dt instanceof DateTimeImmutable ? $dt->format('Y-m-d') : null;
}

function playlist_validity_payload(array $slide): array
{
    $hasValidity = !empty($slide['hasValidity']);
    $validFrom = playlist_normalize_date((string)($slide['validFrom'] ?? ''));
    $validUntil = playlist_normalize_date((string)($slide['validUntil'] ?? ''));

    if ($hasValidity && $validFrom === null) {
        $validFrom = playlist_today_vienna()->format('Y-m-d');
    }
    if ($hasValidity && $validUntil === null) {
        $validUntil = playlist_today_vienna()->modify('+10 years')->format('Y-m-d');
    }
    if ($hasValidity && $validFrom !== null && $validUntil !== null && $validUntil < $validFrom) {
        [$validFrom, $validUntil] = [$validUntil, $validFrom];
    }

    return [
        'hasValidity' => $hasValidity,
        'validFrom' => $validFrom,
        'validUntil' => $validUntil,
    ];
}

function playlist_slide_is_visible(array $slide, ?DateTimeImmutable $now = null): bool
{
    $now ??= playlist_today_vienna();
    $validity = playlist_validity_payload($slide);
    if (!$validity['hasValidity']) {
        return true;
    }
    $today = $now->format('Y-m-d');
    if ($validity['validFrom'] !== null && $today < $validity['validFrom']) {
        return false;
    }
    if ($validity['validUntil'] !== null && $today > $validity['validUntil']) {
        return false;
    }
    return true;
}

function playlist_slide_is_expired(array $slide, ?DateTimeImmutable $now = null): bool
{
    $now ??= playlist_today_vienna();
    $validity = playlist_validity_payload($slide);
    return $validity['hasValidity'] && $validity['validUntil'] !== null && $now->format('Y-m-d') > $validity['validUntil'];
}

function playlist_apply_validity_rules(array $slides, bool &$changed = false): array
{
    $changed = false;
    $now = playlist_today_vienna();

    foreach ($slides as $index => $slide) {
        if (!is_array($slide)) {
            continue;
        }
        $validity = playlist_validity_payload($slide);
        $slides[$index]['hasValidity'] = $validity['hasValidity'];
        if ($validity['validFrom'] !== null) {
            $slides[$index]['validFrom'] = $validity['validFrom'];
        } else {
            unset($slides[$index]['validFrom']);
        }
        if ($validity['validUntil'] !== null) {
            $slides[$index]['validUntil'] = $validity['validUntil'];
        } else {
            unset($slides[$index]['validUntil']);
        }
        if (playlist_slide_is_expired($slides[$index], $now) && !empty($slides[$index]['enabled'])) {
            $slides[$index]['enabled'] = false;
            $changed = true;
        }
    }

    return $slides;
}

function playlist_normalize_slide(array $slide, int $index, array $config = []): array
{
    $screen = $config['screen'] ?? [];
    $clock = $config['clock'] ?? [];
    $type = strtolower((string)($slide['type'] ?? 'image'));

    $normalized = [
        'id' => (string)($slide['id'] ?? ('slide_' . ($index + 1))),
        'type' => $type,
        'title' => (string)($slide['title'] ?? ('Slide ' . ($index + 1))),
        'enabled' => array_key_exists('enabled', $slide) ? (bool)$slide['enabled'] : true,
        'duration' => isset($slide['duration']) ? (float)$slide['duration'] : ($type === 'clock' ? (float)($clock['defaultDuration'] ?? 10) : (float)($screen['defaultDuration'] ?? 8)),
        'fade' => isset($slide['fade']) ? (float)$slide['fade'] : (float)($screen['defaultFade'] ?? 1.2),
        'sort' => isset($slide['sort']) ? (int)$slide['sort'] : (($index + 1) * 10),
        'bg' => (string)($slide['bg'] ?? ($screen['background'] ?? '#ffffff')),
        'fit' => (string)($slide['fit'] ?? ($screen['fit'] ?? 'contain')),
    ];

    foreach (['file','url','sourceType','sourceFile','sourceTitle'] as $key) {
        if (isset($slide[$key])) {
            $normalized[$key] = (string)$slide[$key];
        }
    }
    if (isset($slide['page'])) {
        $normalized['page'] = (int)$slide['page'];
    }
    if (isset($slide['refreshSeconds'])) {
        $normalized['refreshSeconds'] = max(0, (int)$slide['refreshSeconds']);
    }
    if (isset($slide['timeout'])) {
        $normalized['timeout'] = max(1, (int)$slide['timeout']);
    }
    if (isset($slide['videoMode'])) {
        $normalized['videoMode'] = (string)$slide['videoMode'];
    }
    if (isset($slide['muted'])) {
        $normalized['muted'] = (bool)$slide['muted'];
    }
    if ($type === 'clock') {
        $normalized['clock'] = is_array($slide['clock'] ?? null) ? $slide['clock'] : [];
    }

    $validity = playlist_validity_payload($slide);
    $normalized['hasValidity'] = $validity['hasValidity'];
    if ($validity['validFrom'] !== null) {
        $normalized['validFrom'] = $validity['validFrom'];
    }
    if ($validity['validUntil'] !== null) {
        $normalized['validUntil'] = $validity['validUntil'];
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
        if (is_array($slide)) {
            $normalizedSlides[] = playlist_normalize_slide($slide, $index, $config);
        }
    }

    $normalizedSlides = playlist_apply_validity_rules($normalizedSlides, $changed);
    usort($normalizedSlides, static fn(array $a, array $b): int => (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0));

    if ($changed) {
        save_playlist(['version' => 2, 'slides' => $normalizedSlides]);
    }

    return ['version' => 2, 'slides' => $normalizedSlides];
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
    return $index === null ? null : ($playlist['slides'][$index] ?? null);
}

function playlist_save_normalized(array $slides): bool
{
    $config = load_config();
    $normalizedSlides = [];
    foreach (array_values($slides) as $index => $slide) {
        if (is_array($slide)) {
            $normalizedSlides[] = playlist_normalize_slide($slide, $index, $config);
        }
    }
    $normalizedSlides = playlist_apply_validity_rules($normalizedSlides, $changed);
    usort($normalizedSlides, static fn(array $a, array $b): int => (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0));
    return save_playlist(['version' => 2, 'slides' => $normalizedSlides]);
}
