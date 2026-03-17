<?php
require_once __DIR__ . '/functions.php';

$config = loadConfig();

$config['player']['defaultDuration'] = max(1, (int)($_POST['defaultDuration'] ?? 8));
$config['player']['imageFade'] = max(0, (float)($_POST['imageFade'] ?? 1.2));
$config['player']['fit'] = in_array(($_POST['fit'] ?? 'contain'), ['contain', 'cover'], true)
    ? $_POST['fit']
    : 'contain';
$config['player']['background'] = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['background'] ?? '')
    ? $_POST['background']
    : '#ffffff';
$config['player']['videoMode'] = in_array(($_POST['videoMode'] ?? 'until_end'), ['until_end', 'fixed'], true)
    ? $_POST['videoMode']
    : 'until_end';
$config['player']['startMuted'] = !empty($_POST['startMuted']);

$config['clock']['enabled'] = !empty($_POST['clockEnabled']);
$config['clock']['duration'] = max(1, (int)($_POST['clockDuration'] ?? 10));
$config['clock']['background'] = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['clockBackground'] ?? '')
    ? $_POST['clockBackground']
    : '#ffffff';
$config['clock']['textColor'] = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['clockTextColor'] ?? '')
    ? $_POST['clockTextColor']
    : '#111111';
$config['clock']['showSeconds'] = !empty($_POST['clockShowSeconds']);
$config['clock']['logoHeight'] = max(20, min(400, (int)($_POST['clockLogoHeight'] ?? 100)));

if (!empty($_FILES['clockLogo']['name']) && is_uploaded_file($_FILES['clockLogo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['clockLogo']['name'], PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
    if (in_array($ext, $allowed, true)) {
        $name = 'clock_logo_' . date('Ymd_His') . '.' . $ext;
        $target = __DIR__ . '/uploads/' . $name;
        if (move_uploaded_file($_FILES['clockLogo']['tmp_name'], $target)) {
            @chmod($target, 0664);
            $config['clock']['logo'] = $name;
        }
    }
}

$config['system']['watchdogEnabled'] = !empty($_POST['watchdogEnabled']);
$config['system']['cpuLimit'] = max(1, min(100, (int)($_POST['cpuLimit'] ?? 85)));
$config['system']['ramLimit'] = max(1, min(100, (int)($_POST['ramLimit'] ?? 85)));
$config['system']['cooldownSeconds'] = max(30, (int)($_POST['cooldownSeconds'] ?? 180));
$config['system']['maxRestartsIn30Min'] = max(1, (int)($_POST['maxRestartsIn30Min'] ?? 3));
$config['system']['apacheHealthcheck'] = !empty($_POST['apacheHealthcheck']);
$config['system']['apacheUrl'] = trim((string)($_POST['apacheUrl'] ?? 'http://127.0.0.1/infoscreen2/index.php'));
$config['system']['apacheTimeoutSeconds'] = max(1, (int)($_POST['apacheTimeoutSeconds'] ?? 5));
$config['system']['rebootAfterPlayerRestarts'] = max(1, (int)($_POST['rebootAfterPlayerRestarts'] ?? 2));
$config['system']['requireConsecutiveFails'] = max(1, (int)($_POST['requireConsecutiveFails'] ?? 2));
$config['services']['stopApacheOnFallback'] = !empty($_POST['stopApacheOnFallback']);

saveConfig($config);
header('Location: admin.php');
exit;
