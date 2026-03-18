<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/logger.php';

date_default_timezone_set('Europe/Vienna');

ensure_dir(DATA_DIR);
ensure_dir(UPLOAD_DIR);
ensure_dir(TMP_DIR);
ensure_dir(DATA_DIR . '/logs');
ensure_dir(DATA_DIR . '/backups');

if (!file_exists(CONFIG_FILE)) {
    save_config(app_defaults());
}

if (!file_exists(PLAYLIST_FILE)) {
    save_playlist(playlist_defaults());
}
