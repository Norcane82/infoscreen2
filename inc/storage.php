<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function read_json_file(string $file, array $fallback = []): array
{
    if (!file_exists($file)) {
        return $fallback;
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_file(string $file, array $data): bool
{
    ensure_dir(dirname($file));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
}

function load_config(): array
{
    return array_replace_recursive(app_defaults(), read_json_file(CONFIG_FILE, []));
}

function save_config(array $config): bool
{
    $defaults = app_defaults();

    $clean = [
        'screen' => [],
        'clock' => [],
        'system' => [],
    ];

    foreach ($defaults['screen'] as $key => $value) {
        $clean['screen'][$key] = $config['screen'][$key] ?? $value;
    }

    foreach ($defaults['clock'] as $key => $value) {
        $clean['clock'][$key] = $config['clock'][$key] ?? $value;
    }

    foreach ($defaults['system'] as $key => $value) {
        $clean['system'][$key] = $config['system'][$key] ?? $value;
    }

    if (isset($config['services']) && is_array($config['services'])) {
        $clean['services'] = $config['services'];
    }

    return write_json_file(CONFIG_FILE, $clean);
}

function load_playlist(): array
{
    $playlist = read_json_file(PLAYLIST_FILE, playlist_defaults());

    if (!isset($playlist['slides']) || !is_array($playlist['slides'])) {
        $playlist['slides'] = [];
    }

    if (!isset($playlist['version'])) {
        $playlist['version'] = 2;
    }

    return $playlist;
}

function save_playlist(array $playlist): bool
{
    $base = playlist_defaults();
    $base['version'] = $playlist['version'] ?? 2;
    $base['slides'] = array_values($playlist['slides'] ?? []);

    return write_json_file(PLAYLIST_FILE, $base);
}
