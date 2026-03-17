<?php

define('INFOSCREEN2_BASE', __DIR__);
define('INFOSCREEN2_DATA', INFOSCREEN2_BASE . '/data');
define('INFOSCREEN2_UPLOADS', INFOSCREEN2_BASE . '/uploads');
define('INFOSCREEN2_CACHE', INFOSCREEN2_BASE . '/cache');
define('INFOSCREEN2_LOGS', INFOSCREEN2_BASE . '/logs');

function ensureDirectories(): void {
    $dirs = [
        INFOSCREEN2_DATA,
        INFOSCREEN2_UPLOADS,
        INFOSCREEN2_CACHE,
        INFOSCREEN2_LOGS
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}

function getConfigPath(): string {
    return INFOSCREEN2_DATA . '/config.json';
}

function getPlaylistPath(): string {
    return INFOSCREEN2_DATA . '/playlist.json';
}

function getRestartLogPath(): string {
    return INFOSCREEN2_DATA . '/restart_log.json';
}
function defaultConfig(): array {
    return [
        'player' => [
            'defaultDuration' => 8,
            'imageFade' => 1.2,
            'videoMode' => 'until_end',
            'background' => '#ffffff',
            'fit' => 'contain',
            'shuffle' => false,
            'loop' => true,
            'startMuted' => true,
            'preloadNext' => true
        ],
        'clock' => [
            'enabled' => true,
            'duration' => 10,
            'background' => '#ffffff',
            'textColor' => '#111111',
            'showSeconds' => true,
            'fontScale' => 1,
            'logo' => '',
            'logoHeight' => 100
        ],
        'system' => [
            'watchdogEnabled' => true,
            'cpuLimit' => 90,
            'ramLimit' => 90,
            'checkIntervalSeconds' => 30,
            'cooldownSeconds' => 120,
            'maxRestartsIn30Min' => 3,
            'fallbackMode' => 'stop_services'
        ],
        'services' => [
            'playerProcessName' => 'chromium',
            'stopApacheOnFallback' => true,
            'stopScreenOnFallback' => true
        ]
    ];
}

function defaultPlaylist(): array {
    return [
        [
            'id' => 'clock-main',
            'type' => 'clock',
            'title' => 'Uhr',
            'enabled' => true,
            'duration' => 10,
            'sort' => 10
        ]
    ];
}

function readJsonFile(string $path, $fallback) {
    if (!file_exists($path)) {
        return $fallback;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function writeJsonFile(string $path, array $data): bool {
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        return false;
    }

    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }

    return @rename($tmp, $path);
}

function loadConfig(): array {
    $config = readJsonFile(getConfigPath(), defaultConfig());
    return array_replace_recursive(defaultConfig(), $config);
}

function saveConfig(array $config): bool {
    return writeJsonFile(getConfigPath(), $config);
}

function loadPlaylist(): array {
    $playlist = readJsonFile(getPlaylistPath(), defaultPlaylist());

    usort($playlist, function ($a, $b) {
        return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
    });

    return $playlist;
}

function savePlaylist(array $playlist): bool {
    foreach ($playlist as $i => &$item) {
        if (!isset($item['sort'])) {
            $item['sort'] = ($i + 1) * 10;
        }
        if (!isset($item['enabled'])) {
            $item['enabled'] = true;
        }
    }
    unset($item);

    usort($playlist, function ($a, $b) {
        return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
    });

    return writeJsonFile(getPlaylistPath(), array_values($playlist));
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function generateId(string $prefix = 'item'): string {
    return $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
}
function normalizeSlide(array $item, array $config): array {
    $type = $item['type'] ?? 'image';
    $defaultDuration = (int)($config['player']['defaultDuration'] ?? 8);

    $base = [
        'id' => $item['id'] ?? generateId($type),
        'type' => $type,
        'title' => $item['title'] ?? ucfirst($type),
        'enabled' => isset($item['enabled']) ? (bool)$item['enabled'] : true,
        'duration' => isset($item['duration']) ? (int)$item['duration'] : $defaultDuration,
        'sort' => isset($item['sort']) ? (int)$item['sort'] : 9999
    ];

    switch ($type) {
        case 'clock':
            return array_merge($base, [
                'duration' => isset($item['duration'])
                    ? (int)$item['duration']
                    : (int)($config['clock']['duration'] ?? 10)
            ]);

        case 'image':
            return array_merge($base, [
                'file' => $item['file'] ?? '',
                'fit' => $item['fit'] ?? ($config['player']['fit'] ?? 'contain'),
                'fade' => isset($item['fade'])
                    ? (float)$item['fade']
                    : (float)($config['player']['imageFade'] ?? 1.2)
            ]);

        case 'video':
            return array_merge($base, [
                'file' => $item['file'] ?? '',
                'videoMode' => $item['videoMode'] ?? ($config['player']['videoMode'] ?? 'until_end'),
                'muted' => isset($item['muted'])
                    ? (bool)$item['muted']
                    : (bool)($config['player']['startMuted'] ?? true)
            ]);

        case 'website':
            return array_merge($base, [
                'url' => $item['url'] ?? '',
                'refreshSeconds' => isset($item['refreshSeconds']) ? (int)$item['refreshSeconds'] : 0
            ]);

        default:
            return $base;
    }
}

function normalizePlaylist(array $playlist, array $config): array {
    $out = [];

    foreach ($playlist as $item) {
        if (!is_array($item)) {
            continue;
        }
        $out[] = normalizeSlide($item, $config);
    }

    usort($out, function ($a, $b) {
        return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
    });

    return array_values($out);
}

function appendLog(string $file, string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(INFOSCREEN2_LOGS . '/' . $file, $line, FILE_APPEND);
}

ensureDirectories();
