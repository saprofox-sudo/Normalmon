<?php
 
// ============================
// api/telegram_webhook.php
// تيليغرام يبعث هنا كل رسالة
// سجّل الـ webhook مرة وحدة:
// ============================

require_once __DIR__ . '/../config.php';

$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── callback_query (ضغط على زر inline) ──
if (!empty($body['callback_query'])) {
    $cb     = $body['callback_query'];
    $chatId = (string)($cb['message']['chat']['id'] ?? '');
    $fromId = (string)($cb['from']['id'] ?? '');
    $data   = trim($cb['data'] ?? '');
    $cbId   = $cb['id'] ?? '';

    // تأكد من حسابك فقط
    if ($fromId !== (string)TELEGRAM_CHAT_ID && $chatId !== (string)TELEGRAM_CHAT_ID) {
        http_response_code(403); exit;
    }

    // أجب على الـ callback فوراً (يزيل علامة التحميل على الزر)
    // أوقف "التحميل" على زر تيليجرام بدون أي رسالة/Alert
    answerCallback($cbId);

    if (preg_match('/^APPROVED:(\S+)$/i', $data, $m)) {
        saveApproval($m[1], 'approved');
        sendTelegram("✅ تمت الموافقة على الجلسة: {$m[1]}");
    } elseif (preg_match('/^REJECTED:(\S+)$/i', $data, $m)) {
        saveApproval($m[1], 'rejected');
        sendTelegram("❌ تم رفض الجلسة: {$m[1]}");
    }

    http_response_code(200); echo 'ok'; exit;
}

// ── رسالة نصية عادية (احتياطي) ──
$text   = trim($body['message']['text']   ?? '');
$chatId = (string)($body['message']['chat']['id'] ?? '');
$fromId = (string)($body['message']['from']['id'] ?? '');

if ($fromId !== (string)TELEGRAM_CHAT_ID && $chatId !== (string)TELEGRAM_CHAT_ID) {
    http_response_code(403); exit;
}

if (preg_match('/^APPROVED:(\S+)$/i', $text, $m)) {
    saveApproval($m[1], 'approved');
    sendTelegram("✅ تمت الموافقة على الجلسة: {$m[1]}");
} elseif (preg_match('/^REJECTED:(\S+)$/i', $text, $m)) {
    saveApproval($m[1], 'rejected');
    sendTelegram("❌ تم رفض الجلسة: {$m[1]}");
} elseif (strtoupper($text) === 'APPROVED') {
    $pending = getLastPending();
    if ($pending) {
        saveApproval($pending, 'approved');
        sendTelegram("✅ تمت الموافقة على الجلسة الأخيرة: {$pending}");
    } else {
        sendTelegram("⚠️ لا توجد جلسات في الانتظار.");
    }
}

http_response_code(200);
echo 'ok';

// ============================
// Helpers
// ============================

function saveApproval(string $sessionId, string $status): void {
    $file = __DIR__ . '/../data/approvals.json';
    $data = readJson($file);
    // أول قرار يثبت (منع تحويل "رفض" إلى "قبول" أو العكس)
    if (isset($data[$sessionId])) {
        return;
    }
    $data[$sessionId] = [
        'status'    => $status,
        'timestamp' => time(),
    ];
    // احتفظ بآخر 200 جلسة فقط
    if (count($data) > 200) {
        $data = array_slice($data, -200, null, true);
    }
    writeJson($file, $data);
}

function getLastPending(): ?string {
    $approvalsFile = __DIR__ . '/../data/approvals.json';
    $pendingFile   = __DIR__ . '/../data/pending.json';
    $approvals     = readJson($approvalsFile);
    $pending       = readJson($pendingFile);

    // أحدث جلسة في pending ليست في approvals بعد
    krsort($pending);
    foreach ($pending as $sessionId => $info) {
        if (!isset($approvals[$sessionId])) {
            return $sessionId;
        }
    }
    return null;
}

function answerCallback(string $cbId, string $text = '', bool $showAlert = false): void {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/answerCallbackQuery';
    $payload = ['callback_query_id' => $cbId];
    if ($text !== '') {
        $payload['text'] = $text;
        $payload['show_alert'] = $showAlert ? 1 : 0;
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($payload),
        'timeout' => 5,
    ]]);
    @file_get_contents($url, false, $ctx);
}

function sendTelegram(string $msg): void {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['chat_id' => TELEGRAM_CHAT_ID, 'text' => $msg]),
        'timeout' => 5,
    ]]);
    @file_get_contents($url, false, $ctx);
}

function readJson(string $file): array {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeJson(string $file, array $data): void {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/.htaccess', "Deny from all\n");
    }
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
