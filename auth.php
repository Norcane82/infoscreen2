<?php
declare(strict_types=1);

const AUTH_FILE = __DIR__ . '/data/auth.json';

function auth_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('infoscreen2_auth');
        session_start();
    }
}

function auth_default_config(): array
{
    return [
        'mode' => 'simple',
        'editorAccess' => [
            'enabled' => true,
            'requiresPin' => false,
            'pinHash' => '',
        ],
        'adminAccess' => [
            'enabled' => true,
            'passwordHash' => '',
        ],
        'futureUserSystem' => [
            'prepared' => true,
            'enabled' => false,
        ],
    ];
}

function auth_read_config(): array
{
    $default = auth_default_config();

    if (!is_file(AUTH_FILE)) {
        return $default;
    }

    $decoded = json_decode((string)@file_get_contents(AUTH_FILE), true);
    if (!is_array($decoded)) {
        return $default;
    }

    return array_replace_recursive($default, $decoded);
}

function auth_write_config(array $config): bool
{
    $dir = dirname(AUTH_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $tmp = AUTH_FILE . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }

    return @rename($tmp, AUTH_FILE);
}

function auth_admin_is_configured(): bool
{
    $config = auth_read_config();
    return trim((string)($config['adminAccess']['passwordHash'] ?? '')) !== '';
}

function auth_editor_requires_pin(): bool
{
    $config = auth_read_config();
    return !empty($config['editorAccess']['enabled']) && !empty($config['editorAccess']['requiresPin']);
}

function auth_current_role(): string
{
    auth_start();

    if (($_SESSION['infoscreen2_role'] ?? '') === 'admin') {
        return 'admin';
    }

    if (!auth_editor_requires_pin()) {
        return 'editor';
    }

    if (!empty($_SESSION['infoscreen2_editor_ok'])) {
        return 'editor';
    }

    return 'guest';
}

function auth_is_admin(): bool
{
    return auth_current_role() === 'admin';
}

function auth_is_editor(): bool
{
    return in_array(auth_current_role(), ['editor', 'admin'], true);
}

function auth_require_editor(): void
{
    if (auth_is_editor()) {
        return;
    }

    auth_redirect('editor_login.php');
}

function auth_require_admin(): void
{
    if (auth_is_admin()) {
        return;
    }

    auth_redirect('admin_login.php');
}

function auth_redirect(string $target): never
{
    if (!headers_sent()) {
        header('Location: ' . $target, true, 303);
    }

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">';
    echo '<title>Weiterleitung</title></head><body>';
    echo '<p>Weiterleitung… <a href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">Weiter</a></p>';
    echo '</body></html>';
    exit;
}

function auth_login_admin(string $password): bool
{
    auth_start();

    $config = auth_read_config();
    $hash = trim((string)($config['adminAccess']['passwordHash'] ?? ''));

    if ($hash === '' || !password_verify($password, $hash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['infoscreen2_role'] = 'admin';
    $_SESSION['infoscreen2_editor_ok'] = true;
    $_SESSION['infoscreen2_login_time'] = time();

    return true;
}

function auth_login_editor_with_pin(string $pin): bool
{
    auth_start();

    $config = auth_read_config();
    if (empty($config['editorAccess']['requiresPin'])) {
        $_SESSION['infoscreen2_editor_ok'] = true;
        return true;
    }

    $hash = trim((string)($config['editorAccess']['pinHash'] ?? ''));
    if ($hash === '' || !password_verify($pin, $hash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['infoscreen2_editor_ok'] = true;
    $_SESSION['infoscreen2_login_time'] = time();

    return true;
}

function auth_logout(bool $adminOnly = false): void
{
    auth_start();

    if ($adminOnly) {
        unset($_SESSION['infoscreen2_role']);
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }

    session_destroy();
}
