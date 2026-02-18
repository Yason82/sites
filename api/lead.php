<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendTelegramMessage(string $token, string $chatId, string $text): void
{
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
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
        respond(502, [
            'ok' => false,
            'error' => 'telegram_error',
            'code' => $httpCode,
            'details' => $responseBody,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    respond(400, ['ok' => false, 'error' => 'invalid_json']);
}

$configPath = __DIR__ . '/lead-config.php';
if (!is_file($configPath)) {
    respond(500, ['ok' => false, 'error' => 'not_configured']);
}

$config = require $configPath;
$token = trim((string)($config['token'] ?? ''));
$chatId = trim((string)($config['chat_id'] ?? ''));
$webhookSecret = trim((string)($config['webhook_secret'] ?? ''));

if ($token === '' || $chatId === '') {
    respond(500, ['ok' => false, 'error' => 'not_configured']);
}

$isTelegramUpdate = is_array($data['message'] ?? null)
    || is_array($data['edited_message'] ?? null)
    || is_array($data['channel_post'] ?? null);
$isAnyTelegramUpdate = array_key_exists('update_id', $data);

if ($isTelegramUpdate) {
    if ($webhookSecret !== '') {
        $secretHeader = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if (!hash_equals($webhookSecret, $secretHeader)) {
            respond(403, ['ok' => false, 'error' => 'forbidden']);
        }
    }

    $message = $data['message'] ?? $data['edited_message'] ?? $data['channel_post'] ?? [];
    $from = is_array($message['from'] ?? null) ? $message['from'] : [];
    $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
    $chatType = (string)($chat['type'] ?? '');

    // Forward only direct user -> bot messages, avoid group loops.
    if ($chatType !== 'private' || (bool)($from['is_bot'] ?? false)) {
        respond(200, ['ok' => true, 'ignored' => true]);
    }

    $firstName = trim((string)($from['first_name'] ?? ''));
    $lastName = trim((string)($from['last_name'] ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);
    $username = trim((string)($from['username'] ?? ''));
    $userId = (string)($from['id'] ?? '-');

    $text = trim((string)($message['text'] ?? $message['caption'] ?? ''));
    if ($text === '') {
        $text = '[не текстовое сообщение]';
    }

    $date = (int)($message['date'] ?? time());
    $forwardLines = [
        'Новое сообщение в Telegram-бота Oasis Defender',
        '',
        'От: ' . ($fullName !== '' ? $fullName : 'Без имени'),
        'Username: ' . ($username !== '' ? '@' . $username : '-'),
        'User ID: ' . $userId,
        'Текст: ' . $text,
        'Время: ' . gmdate('c', $date),
    ];

    sendTelegramMessage($token, $chatId, implode("\n", $forwardLines));
    respond(200, ['ok' => true, 'forwarded' => true]);
}

if ($isAnyTelegramUpdate) {
    // Ignore other update types (e.g. my_chat_member, callback_query).
    respond(200, ['ok' => true, 'ignored' => true]);
}

$name = trim((string)($data['name'] ?? ''));
$contact = trim((string)($data['contact'] ?? ''));
$company = trim((string)($data['company'] ?? ''));
$note = trim((string)($data['note'] ?? ''));
$intent = trim((string)($data['intent'] ?? 'Запросить демо'));
$page = trim((string)($data['page'] ?? '/'));
$source = trim((string)($data['source'] ?? 'unknown'));
$timestamp = trim((string)($data['timestamp'] ?? gmdate('c')));

if ($name === '' || $contact === '') {
    respond(400, ['ok' => false, 'error' => 'validation']);
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

sendTelegramMessage($token, $chatId, implode("\n", $lines));
respond(200, ['ok' => true]);
