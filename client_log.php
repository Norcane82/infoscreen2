<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || trim($raw) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'empty_body']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$level = strtoupper(trim((string)($data['level'] ?? 'INFO')));
$allowedLevels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];
if (!in_array($level, $allowedLevels, true)) {
    $level = 'INFO';
}

$message = trim((string)($data['message'] ?? 'Client event'));
if ($message === '') {
    $message = 'Client event';
}

$context = is_array($data['context'] ?? null) ? $data['context'] : [];
$context['source'] = 'player';
$context['ip'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');

$entry = [
    'time' => date('c'),
    'level' => $level,
    'message' => $message,
    'context' => $context,
];

$line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($line === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'encode_failed']);
    exit;
}

$logFile = __DIR__ . '/data/logs/app.log';
$result = @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'write_failed']);
    exit;
}

echo json_encode(['ok' => true]);
