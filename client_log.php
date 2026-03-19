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

$jsonLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($jsonLine === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'encode_failed']);
    exit;
}

$logDir = __DIR__ . '/data/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$appLogFile = $logDir . '/app.log';
$traceLogFile = $logDir . '/player_trace.log';

$appResult = @file_put_contents($appLogFile, $jsonLine . PHP_EOL, FILE_APPEND | LOCK_EX);

$traceLine = sprintf(
    "[%s] %-5s %s | %s\n",
    date('Y-m-d H:i:s'),
    $level,
    $message,
    json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);
@file_put_contents($traceLogFile, $traceLine, FILE_APPEND | LOCK_EX);

if ($appResult === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'write_failed']);
    exit;
}

echo json_encode(['ok' => true]);
