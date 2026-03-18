<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function now_iso(): string
{
    return date('c');
}

function uuid_like(string $prefix = 'id'): string
{
    return $prefix . '_' . bin2hex(random_bytes(4));
}
