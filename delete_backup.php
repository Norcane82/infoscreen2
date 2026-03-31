<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

function delete_backup_redirect(string $target = 'backups.php'): never
{
    if (!headers_sent()) {
        header('Location: ' . $target, true, 303);
    }

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">';
    echo '<title>Weiterleitung…</title></head><body>';
    echo '<script>window.location.replace(' . json_encode($target) . ');</script>';
    echo '<p>Weiterleitung… <a href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">Zurück</a></p>';
    echo '</body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    delete_backup_redirect('backups.php');
}

$name = basename((string)($_POST['name'] ?? ''));
$path = __DIR__ . '/data/backups/' . $name;

if ($name !== '' && is_file($path)) {
    @unlink($path);
}

delete_backup_redirect('backups.php');
