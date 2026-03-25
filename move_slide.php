<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function redirect_admin_move(): void
{
    header('Location: admin.php');
    exit;
}

function load_health_state(): array
{
    return read_json_file(HEALTH_FILE, [
        'last_restart' => 0,
        'restarts' => [],
        'fallback_active' => false,
        'consecutive_failures' => 0,
        'last_action' => 'none',
        'requested_view' => 'index',
        'reload_requested_at' => 0,
    ]);
}

function save_health_state(array $state): void
{
    write_json_file(HEALTH_FILE, $state);
}

function request_player_refresh_after_move(): void
{
    $state = load_health_state();
    $state['last_action'] = 'move_slide';
    $state['requested_view'] = !empty($state['fallback_active']) ? 'fallback' : 'index';
    $state['reload_requested_at'] = time();
    save_health_state($state);

    if (function_exists('app_log')) {
        app_log('info', 'Player refresh requested after move', [
            'requested_view' => $state['requested_view'],
        ]);
    }
}

function detect_move_direction(): string
{
    $candidates = [
        $_POST['dir'] ?? '',
        $_POST['direction'] ?? '',
        $_POST['move'] ?? '',
        $_POST['move_dir'] ?? '',
        $_GET['dir'] ?? '',
        $_GET['direction'] ?? '',
        $_GET['move'] ?? '',
        $_GET['move_dir'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $dir = strtolower(trim((string)$candidate));
        if (in_array($dir, ['up', 'down'], true)) {
            return $dir;
        }
    }

    if (isset($_POST['up']) || isset($_GET['up'])) {
        return 'up';
    }

    if (isset($_POST['down']) || isset($_GET['down'])) {
        return 'down';
    }

    $action = strtolower(trim((string)($_POST['action'] ?? $_GET['action'] ?? '')));
    if (in_array($action, ['up', 'down'], true)) {
        return $action;
    }

    return '';
}

function build_slide_groups(array $slides): array
{
    $groups = [];
    $currentPdfGroup = null;

    foreach ($slides as $item) {
        $sourceType = (string)($item['sourceType'] ?? '');
        $sourceFile = (string)($item['sourceFile'] ?? '');

        if ($sourceType === 'pdf' && $sourceFile !== '') {
            if (
                $currentPdfGroup !== null
                && (string)($currentPdfGroup['sourceFile'] ?? '') === $sourceFile
            ) {
                $currentPdfGroup['items'][] = $item;
                continue;
            }

            if ($currentPdfGroup !== null) {
                $groups[] = $currentPdfGroup;
            }

            $currentPdfGroup = [
                'kind' => 'pdf',
                'sourceFile' => $sourceFile,
                'items' => [$item],
            ];
            continue;
        }

        if ($currentPdfGroup !== null) {
            $groups[] = $currentPdfGroup;
            $currentPdfGroup = null;
        }

        $groups[] = [
            'kind' => 'single',
            'sourceFile' => null,
            'items' => [$item],
        ];
    }

    if ($currentPdfGroup !== null) {
        $groups[] = $currentPdfGroup;
    }

    return $groups;
}

function group_contains_slide(array $group, string $slideId): bool
{
    foreach (($group['items'] ?? []) as $item) {
        if ((string)($item['id'] ?? '') === $slideId) {
            return true;
        }
    }
    return false;
}

$id = trim((string)($_POST['id'] ?? $_GET['id'] ?? ''));
$dir = detect_move_direction();

$playlistData = playlist_load_normalized();
$slides = array_values($playlistData['slides'] ?? []);

if ($id === '' || !in_array($dir, ['up', 'down'], true)) {
    if (function_exists('app_log')) {
        app_log('error', 'Move slide aborted: invalid input', [
            'id' => $id,
            'dir' => $dir,
            'post_keys' => array_keys($_POST),
            'get_keys' => array_keys($_GET),
        ]);
    }
    redirect_admin_move();
}

$groups = build_slide_groups($slides);
$groupIndex = null;

foreach ($groups as $i => $group) {
    if (group_contains_slide($group, $id)) {
        $groupIndex = $i;
        break;
    }
}

if ($groupIndex === null) {
    if (function_exists('app_log')) {
        app_log('error', 'Move slide aborted: slide not found in groups', [
            'id' => $id,
            'dir' => $dir,
            'groups' => count($groups),
        ]);
    }
    redirect_admin_move();
}

$oldIndex = $groupIndex;

if ($dir === 'up' && $groupIndex > 0) {
    $tmp = $groups[$groupIndex - 1];
    $groups[$groupIndex - 1] = $groups[$groupIndex];
    $groups[$groupIndex] = $tmp;
    $groupIndex--;
} elseif ($dir === 'down' && $groupIndex < count($groups) - 1) {
    $tmp = $groups[$groupIndex + 1];
    $groups[$groupIndex + 1] = $groups[$groupIndex];
    $groups[$groupIndex] = $tmp;
    $groupIndex++;
}

$newSlides = [];
foreach ($groups as $group) {
    foreach (($group['items'] ?? []) as $item) {
        $newSlides[] = $item;
    }
}

foreach ($newSlides as $i => &$item) {
    $item['sort'] = ($i + 1) * 10;
}
unset($item);

$saveOk = playlist_save_normalized($newSlides);

if (function_exists('app_log')) {
    app_log($saveOk ? 'info' : 'error', 'Move slide processed', [
        'id' => $id,
        'dir' => $dir,
        'old_group_index' => $oldIndex,
        'new_group_index' => $groupIndex,
        'slides_total' => count($newSlides),
        'save_ok' => $saveOk,
    ]);
}

if ($saveOk) {
    request_player_refresh_after_move();
}

redirect_admin_move();
