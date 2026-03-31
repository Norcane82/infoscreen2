<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function update_slide_redirect(string $target = 'admin.php'): never
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

function update_slide_mark_reload(string $action = 'update_slide'): void
{
    $state = read_json_file(HEALTH_FILE, [
        'last_restart' => 0,
        'restarts' => [],
        'fallback_active' => false,
        'consecutive_failures' => 0,
        'last_action' => 'none',
        'requested_view' => 'index',
        'reload_requested_at' => 0,
    ]);

    $state['last_action'] = $action;
    $state['requested_view'] = !empty($state['fallback_active']) ? 'fallback' : 'index';
    $state['reload_requested_at'] = time();

    write_json_file(HEALTH_FILE, $state);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    update_slide_redirect('admin.php');
}

$config = load_config();
$id = trim((string)($_POST['id'] ?? ''));

$playlistData = playlist_load_normalized();
$slides = $playlistData['slides'] ?? [];

$targetSlide = null;
foreach ($slides as $item) {
    if ((string)($item['id'] ?? '') === $id) {
        $targetSlide = $item;
        break;
    }
}

if ($targetSlide === null) {
    update_slide_redirect('admin.php');
}

$enabled = (string)($_POST['enabled'] ?? '1') === '1';
$duration = max(1, (int)($_POST['duration'] ?? ($targetSlide['duration'] ?? 8)));
$fade = max(0, (float)($_POST['fade'] ?? ($targetSlide['fade'] ?? ($config['screen']['defaultFade'] ?? 1.2))));
$fit = (string)($_POST['fit'] ?? ($targetSlide['fit'] ?? 'contain'));
$fit = in_array($fit, ['contain', 'cover'], true) ? $fit : 'contain';

$hasValidity = isset($_POST['hasValidity']) && (string)$_POST['hasValidity'] === '1';
$validFrom = playlist_normalize_date((string)($_POST['validFrom'] ?? ''));
$validUntil = playlist_normalize_date((string)($_POST['validUntil'] ?? ''));

if ($hasValidity && $validFrom === null) {
    $validFrom = playlist_today_vienna()->format('Y-m-d');
}
if ($hasValidity && $validUntil === null) {
    $validUntil = playlist_today_vienna()->modify('+10 years')->format('Y-m-d');
}
if (!$hasValidity) {
    $validFrom = null;
    $validUntil = null;
}

$isPdfRenderedImage = (($targetSlide['type'] ?? '') === 'image') && (($targetSlide['sourceType'] ?? '') === 'pdf');

if ($isPdfRenderedImage) {
    $groupSourceFile = (string)($targetSlide['sourceFile'] ?? '');
    $groupSourceTitle = trim((string)($_POST['title'] ?? ($targetSlide['sourceTitle'] ?? $targetSlide['title'] ?? '')));

    foreach ($slides as &$item) {
        $samePdfGroup =
            (($item['type'] ?? '') === 'image') &&
            (($item['sourceType'] ?? '') === 'pdf') &&
            ((string)($item['sourceFile'] ?? '') === $groupSourceFile);

        if (!$samePdfGroup) {
            continue;
        }

        $page = (int)($item['page'] ?? 0);
        $item['sourceTitle'] = $groupSourceTitle;
        $item['title'] = $groupSourceTitle . ' - Seite ' . $page;
        $item['enabled'] = $enabled;
        $item['duration'] = $duration;
        $item['fit'] = $fit;
        $item['fade'] = $fade;
        $item['hasValidity'] = $hasValidity;
        $item['validFrom'] = $validFrom;
        $item['validUntil'] = $validUntil;
    }
    unset($item);

    playlist_save_normalized($slides);
    update_slide_mark_reload('update_slide_pdf_group');
    update_slide_redirect('admin.php');
}

foreach ($slides as &$item) {
    if ((string)($item['id'] ?? '') !== $id) {
        continue;
    }

    $type = (string)($item['type'] ?? '');
    $item['title'] = trim((string)($_POST['title'] ?? ($item['title'] ?? '')));
    $item['enabled'] = $enabled;
    $item['duration'] = $duration;
    $item['hasValidity'] = $hasValidity;
    $item['validFrom'] = $validFrom;
    $item['validUntil'] = $validUntil;

    if ($type === 'image') {
        $item['fit'] = $fit;
        $item['fade'] = $fade;
    }

    if ($type === 'video') {
        $mode = (string)($_POST['videoMode'] ?? ($item['videoMode'] ?? 'until_end'));
        $item['videoMode'] = in_array($mode, ['until_end', 'fixed_duration', 'fixed'], true) ? $mode : 'until_end';
        $item['muted'] = (string)($_POST['muted'] ?? '1') === '1';
    }

    if ($type === 'website') {
        $url = trim((string)($_POST['url'] ?? ($item['url'] ?? '')));
        if ($url !== '') {
            $item['url'] = $url;
        }
        $item['refreshSeconds'] = max(0, (int)($_POST['refreshSeconds'] ?? ($item['refreshSeconds'] ?? 0)));
        $item['timeout'] = max(1, (int)($_POST['timeoutSeconds'] ?? ($item['timeout'] ?? 8)));
    }

    break;
}
unset($item);

playlist_save_normalized($slides);
update_slide_mark_reload('update_slide');
update_slide_redirect('admin.php');
