<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
$contact = trim((string)($data['contact'] ?? ''));
$company = trim((string)($data['company'] ?? ''));
$note = trim((string)($data['note'] ?? ''));
$intent = trim((string)($data['intent'] ?? 'Запросить демо / пилот'));
$page = trim((string)($data['page'] ?? '/'));
$source = trim((string)($data['source'] ?? 'unknown'));
$timestamp = trim((string)($data['timestamp'] ?? gmdate('c')));

if ($name === '' || $contact === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'validation']);
    exit;
}

$configPath = __DIR__ . '/lead-config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}

$config = require $configPath;
$token = trim((string)($config['token'] ?? ''));
$chatId = trim((string)($config['chat_id'] ?? ''));

if ($token === '' || $chatId === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}

$lines = [
    'Новая заявка с сайта Oasis Defender',
    '',
    'Тип: ' . $intent,
    'Страница: ' . $page,
    'Источник кнопки: ' . $source,
    'Имя: ' . $name,
    'Компания: ' . ($company !== '' ? $company : '-'),
    'Контакт: ' . $contact,
    'Комментарий: ' . ($note !== '' ? $note : '-'),
    'Время: ' . $timestamp,
];

$payload = [
    'chat_id' => $chatId,
    'text' => implode("\n", $lines),
    'disable_web_page_preview' => true,
];

$endpoint = 'https://api.telegram.org/bot' . $token . '/sendMessage';
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$responseBody = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_errno($ch);
curl_close($ch);

if ($curlErr !== 0 || $httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'telegram_error',
        'code' => $httpCode,
        'details' => $responseBody,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true]);
