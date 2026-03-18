<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$state = [
    'last_restart' => 0,
    'restarts' => [],
    'fallback_active' => false,
    'consecutive_failures' => 0,
    'last_action' => 'manual_reset',
];

write_json_file(HEALTH_FILE, $state);

$fallbackFile = __DIR__ . '/cache/fallback_active.flag';
if (is_file($fallbackFile)) {
    @unlink($fallbackFile);
}

if (function_exists('log_message')) {
    log_message('INFO', 'Watchdog manually reset', [
        'fallback_file_removed' => !is_file($fallbackFile),
    ]);
}

header('Location: admin.php');
exit;
