<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/playlist.php';

function redirect_admin_move(): void
{
    header('Location: admin.php');
    exit;
}

function is_pdf_group_slide(array $item): bool
{
    return (($item['type'] ?? '') === 'image') && (($item['sourceType'] ?? '') === 'pdf');
}

function build_slide_groups(array $slides): array
{
    $groups = [];
    $currentPdfGroup = null;

    foreach ($slides as $item) {
        $isPdf = is_pdf_group_slide($item);
        $sourceFile = (string)($item['sourceFile'] ?? '');

        if ($isPdf && $sourceFile !== '') {
            if ($currentPdfGroup !== null && $currentPdfGroup['kind'] === 'pdf' && $currentPdfGroup['sourceFile'] === $sourceFile) {
                $currentPdfGroup['items'][] = $item;
                $groups[count($groups) - 1] = $currentPdfGroup;
                continue;
            }

            $currentPdfGroup = [
                'kind' => 'pdf',
                'sourceFile' => $sourceFile,
                'items' => [$item],
            ];
            $groups[] = $currentPdfGroup;
            continue;
        }

        $currentPdfGroup = null;
        $groups[] = [
            'kind' => 'single',
            'sourceFile' => null,
            'items' => [$item],
        ];
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

$id = trim((string)($_POST['id'] ?? ''));
$dir = trim((string)($_POST['dir'] ?? ''));

$playlistData = playlist_load_normalized();
$slides = array_values($playlistData['slides'] ?? []);

if ($id === '' || !in_array($dir, ['up', 'down'], true)) {
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
    redirect_admin_move();
}

if ($dir === 'up' && $groupIndex > 0) {
    $tmp = $groups[$groupIndex - 1];
    $groups[$groupIndex - 1] = $groups[$groupIndex];
    $groups[$groupIndex] = $tmp;
}

if ($dir === 'down' && $groupIndex < count($groups) - 1) {
    $tmp = $groups[$groupIndex + 1];
    $groups[$groupIndex + 1] = $groups[$groupIndex];
    $groups[$groupIndex] = $tmp;
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

playlist_save_normalized($newSlides);

redirect_admin_move();
