<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function app_log(string $level, string $message, array $context = []): void
{
    ensure_dir(dirname(LOG_FILE));

    $entry = [
        'time' => now_iso(),
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context,
    ];

    file_put_contents(
        LOG_FILE,
        json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
