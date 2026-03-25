<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

function posted_color(string $textKey, string $pickerKey, string $fallback): string
{
    $candidates = [
        trim((string)($_POST[$textKey] ?? '')),
        trim((string)($_POST[$pickerKey] ?? '')),
    ];

    foreach ($candidates as $candidate) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) === 1) {
            return strtolower($candidate);
        }
    }

    return $fallback;
}

$config = load_config();

$config['screen']['defaultDuration'] = max(1, (int)($_POST['defaultDuration'] ?? 8));
$config['screen']['defaultFade'] = max(
    0,
    (float)($_POST['defaultFade'] ?? $_POST['imageFade'] ?? 1.2)
);
$config['screen']['fit'] = in_array((string)($_POST['fit'] ?? 'contain'), ['contain', 'cover'], true)
    ? (string)$_POST['fit']
    : 'contain';
$config['screen']['background'] = posted_color('background', 'backgroundPicker', '#ffffff');

$config['clock']['enabled'] = !empty($_POST['clockEnabled']);
$config['clock']['defaultDuration'] = max(1, (int)($_POST['clockDuration'] ?? 10));
$config['clock']['background'] = posted_color('clockBackground', 'clockBackgroundPicker', '#ffffff');
$config['clock']['textColor'] = posted_color('clockTextColor', 'clockTextColorPicker', '#111111');
$config['clock']['showSeconds'] = !empty($_POST['clockShowSeconds']);
$config['clock']['logoHeight'] = max(20, min(400, (int)($_POST['clockLogoHeight'] ?? 100)));

if (!empty($_FILES['clockLogo']['name']) && is_uploaded_file((string)$_FILES['clockLogo']['tmp_name'])) {
    $ext = strtolower(pathinfo((string)$_FILES['clockLogo']['name'], PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];

    if (in_array($ext, $allowed, true)) {
        $clockUploadDir = UPLOAD_DIR . '/clock';
        ensure_dir($clockUploadDir);

        $name = 'clock_logo_' . date('Ymd_His') . '.' . $ext;
        $target = $clockUploadDir . '/' . $name;

        if (move_uploaded_file((string)$_FILES['clockLogo']['tmp_name'], $target)) {
            @chmod($target, 0664);
            $config['clock']['logo'] = 'uploads/clock/' . $name;
        }
    }
}

$config['system']['watchdogEnabled'] = !empty($_POST['watchdogEnabled']);
$config['system']['maxCpuPercent'] = max(1, min(100, (int)($_POST['cpuLimit'] ?? 85)));
$config['system']['maxRamPercent'] = max(1, min(100, (int)($_POST['ramLimit'] ?? 85)));
$config['system']['restartCooldownSeconds'] = max(30, (int)($_POST['cooldownSeconds'] ?? 180));
$config['system']['maxRestartsPer30Min'] = max(1, (int)($_POST['maxRestartsIn30Min'] ?? 3));
$config['system']['apacheHealthcheck'] = !empty($_POST['apacheHealthcheck']);
$config['system']['apacheUrl'] = trim((string)($_POST['apacheUrl'] ?? 'http://127.0.0.1/infoscreen2/index.php'));
$config['system']['apacheTimeoutSeconds'] = max(1, (int)($_POST['apacheTimeoutSeconds'] ?? 5));
$config['system']['rebootAfterPlayerRestarts'] = max(1, (int)($_POST['rebootAfterPlayerRestarts'] ?? 2));
$config['system']['requireConsecutiveFails'] = max(1, (int)($_POST['requireConsecutiveFails'] ?? 2));

$config['services']['stopApacheOnFallback'] = !empty($_POST['stopApacheOnFallback']);

save_config($config);

if (function_exists('app_log')) {
    app_log('info', 'Settings updated', [
        'screen.defaultDuration' => $config['screen']['defaultDuration'],
        'screen.defaultFade' => $config['screen']['defaultFade'],
        'screen.background' => $config['screen']['background'],
        'clock.enabled' => $config['clock']['enabled'],
        'clock.background' => $config['clock']['background'],
        'clock.textColor' => $config['clock']['textColor'],
        'clock.logo' => (string)($config['clock']['logo'] ?? ''),
        'clock.logoHeight' => $config['clock']['logoHeight'],
    ]);
}

header('Location: admin.php');
exit;
