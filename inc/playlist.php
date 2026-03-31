<?php
declare(strict_types=1);

if (!defined('PLAYLIST_FILE')) {
    define('PLAYLIST_FILE', dirname(__DIR__) . '/data/playlist.json');
}

if (!function_exists('playlist_read_raw')) {
    function playlist_read_raw(): array
    {
        if (!is_file(PLAYLIST_FILE)) {
            return [
                'version' => 2,
                'slides' => [],
            ];
        }

        $json = file_get_contents(PLAYLIST_FILE);
        if ($json === false || trim($json) === '') {
            return [
                'version' => 2,
                'slides' => [],
            ];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [
                'version' => 2,
                'slides' => [],
            ];
        }

        if (!isset($data['slides']) || !is_array($data['slides'])) {
            $data['slides'] = [];
        }

        if (!isset($data['version'])) {
            $data['version'] = 2;
        }

        return $data;
    }
}

if (!function_exists('playlist_write_raw')) {
    function playlist_write_raw(array $data): void
    {
        $data['version'] = 2;
        $data['slides'] = array_values(array_map('playlist_normalize_slide', (array)($data['slides'] ?? [])));

        $dir = dirname(PLAYLIST_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            PLAYLIST_FILE,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}

if (!function_exists('playlist_today_vienna')) {
    function playlist_today_vienna(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna'));
    }
}

if (!function_exists('playlist_normalize_date')) {
    function playlist_normalize_date(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('Europe/Vienna'));
        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d');
    }
}

if (!function_exists('playlist_day_start_vienna')) {
    function playlist_day_start_vienna(string $ymd): ?DateTimeImmutable
    {
        $normalized = playlist_normalize_date($ymd);
        if ($normalized === null) {
            return null;
        }

        return new DateTimeImmutable($normalized . ' 00:00:00', new DateTimeZone('Europe/Vienna'));
    }
}

if (!function_exists('playlist_day_end_vienna')) {
    function playlist_day_end_vienna(string $ymd): ?DateTimeImmutable
    {
        $normalized = playlist_normalize_date($ymd);
        if ($normalized === null) {
            return null;
        }

        return new DateTimeImmutable($normalized . ' 23:59:59', new DateTimeZone('Europe/Vienna'));
    }
}

if (!function_exists('playlist_normalize_slide')) {
    function playlist_normalize_slide(array $slide): array
    {
        $slide['id'] = (string)($slide['id'] ?? '');
        $slide['type'] = (string)($slide['type'] ?? 'image');
        $slide['title'] = (string)($slide['title'] ?? '');
        $slide['enabled'] = (bool)($slide['enabled'] ?? true);
        $slide['duration'] = max(1, (int)($slide['duration'] ?? 10));
        $slide['sort'] = (int)($slide['sort'] ?? 0);
        $slide['fade'] = max(0, (float)($slide['fade'] ?? 1.2));
        $slide['bg'] = (string)($slide['bg'] ?? '#ffffff');
        $slide['fit'] = in_array((string)($slide['fit'] ?? 'contain'), ['contain', 'cover'], true)
            ? (string)$slide['fit']
            : 'contain';

        $slide['hasValidity'] = (bool)($slide['hasValidity'] ?? false);
        $slide['validFrom'] = playlist_normalize_date((string)($slide['validFrom'] ?? ''));
        $slide['validUntil'] = playlist_normalize_date((string)($slide['validUntil'] ?? ''));

        if (!$slide['hasValidity']) {
            $slide['validFrom'] = null;
            $slide['validUntil'] = null;
        } else {
            if ($slide['validFrom'] === null) {
                $slide['validFrom'] = playlist_today_vienna()->format('Y-m-d');
            }
            if ($slide['validUntil'] === null) {
                $slide['validUntil'] = playlist_today_vienna()->modify('+10 years')->format('Y-m-d');
            }
        }

        return $slide;
    }
}

if (!function_exists('playlist_load_normalized')) {
    function playlist_load_normalized(): array
    {
        $data = playlist_read_raw();
        $slides = array_map('playlist_normalize_slide', (array)($data['slides'] ?? []));

        usort($slides, static function (array $a, array $b): int {
            $sortCompare = ((int)($a['sort'] ?? 0)) <=> ((int)($b['sort'] ?? 0));
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
        });

        return [
            'version' => 2,
            'slides' => array_values($slides),
        ];
    }
}

if (!function_exists('playlist_save_normalized')) {
    function playlist_save_normalized(array $slides): void
    {
        playlist_write_raw([
            'version' => 2,
            'slides' => array_values($slides),
        ]);
    }
}

if (!function_exists('playlist_slide_is_visible')) {
    function playlist_slide_is_visible(array $slide, ?DateTimeImmutable $now = null): bool
    {
        $slide = playlist_normalize_slide($slide);

        if (empty($slide['enabled'])) {
            return false;
        }

        if (empty($slide['hasValidity'])) {
            return true;
        }

        $now = $now ?? playlist_today_vienna();

        $from = isset($slide['validFrom']) && $slide['validFrom'] !== null
            ? playlist_day_start_vienna((string)$slide['validFrom'])
            : null;

        $until = isset($slide['validUntil']) && $slide['validUntil'] !== null
            ? playlist_day_end_vienna((string)$slide['validUntil'])
            : null;

        if ($from !== null && $now < $from) {
            return false;
        }

        if ($until !== null && $now > $until) {
            return false;
        }

        return true;
    }
}

if (!function_exists('playlist_disable_expired_slides')) {
    function playlist_disable_expired_slides(array $slides, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? playlist_today_vienna();
        $changed = false;

        foreach ($slides as &$slide) {
            $slide = playlist_normalize_slide((array)$slide);

            if (empty($slide['enabled']) || empty($slide['hasValidity']) || empty($slide['validUntil'])) {
                continue;
            }

            $until = playlist_day_end_vienna((string)$slide['validUntil']);
            if ($until !== null && $now > $until) {
                $slide['enabled'] = false;
                $changed = true;
            }
        }
        unset($slide);

        if ($changed) {
            playlist_save_normalized($slides);
        }

        return $slides;
    }
}
