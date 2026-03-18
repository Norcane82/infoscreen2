<?php
declare(strict_types=1);

const APP_NAME = 'infoscreen2';
const APP_VERSION = '0.1.0';

const DATA_DIR = __DIR__ . '/../data';
const UPLOAD_DIR = __DIR__ . '/../uploads';
const TMP_DIR = __DIR__ . '/../tmp';

const CONFIG_FILE = DATA_DIR . '/config.json';
const PLAYLIST_FILE = DATA_DIR . '/playlist.json';
const HEALTH_FILE = DATA_DIR . '/health.json';
const LOG_FILE = DATA_DIR . '/logs/app.log';

function app_defaults(): array
{
    return [
        'screen' => [
            'defaultDuration' => 8,
            'defaultFade' => 1.0,
            'background' => '#ffffff',
            'fit' => 'contain',
        ],
        'clock' => [
            'enabled' => true,
            'defaultDuration' => 10,
            'timezone' => 'Europe/Vienna',
        ],
        'system' => [
            'watchdogEnabled' => true,
            'maxCpuPercent' => 90,
            'maxRamPercent' => 90,
            'restartCooldownSeconds' => 120,
            'maxRestartsPer30Min' => 3,
        ],
    ];
}

function playlist_defaults(): array
{
    return [
        'version' => 2,
        'slides' => [],
    ];
}
