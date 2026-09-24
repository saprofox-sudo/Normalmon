<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$chatId = TELEGRAM_ADMIN_CHAT_ID;
$token = TELEGRAM_BOT_TOKEN;

$msg = '✅ Telegram test message [' . date('Y-m-d H:i:s') . ']';

$url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
$payload = [
    'chat_id' => $chatId,
    'text' => $msg,
];

$result = [
    'chat_id' => $chatId,
    'token_prefix' => substr($token, 0, 10) . '...'
];

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result['curl_error'] = $err;
    $result['http_code'] = $http;
    $result['api_response'] = $res;
} else {
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($payload),
        'timeout' => 10,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    $result['api_response'] = $res;
}

// Parse Telegram API response
$decoded = json_decode($res, true);
if (is_array($decoded)) {
    $result['ok'] = $decoded['ok'] ?? false;
    $result['description'] = $decoded['description'] ?? null;
} else {
    $result['ok'] = false;
    $result['description'] = 'No valid JSON response';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
