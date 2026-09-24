<?php

 
// ============================
// config.php — الإعدادات العامة
// ============================

define('TELEGRAM_BOT_TOKEN', '8386078805:AAG8dTjNF8G0MaRoDqOd1IbDiOeekzK_1TA');
define('TELEGRAM_CHAT_ID',   '-4815944148');

// بيانات الدخول للداشبورد
define('DASH_USER', 'admin');
define('DASH_PASS', '123456');

// مهلة الجلسة (ثانية) — ساعة واحدة
define('SESSION_LIFETIME', 3600);

// مسارات ملفات البيانات
define('DATA_DIR', __DIR__ . '/data');
define('INVOICES_FILE', DATA_DIR . '/invoices.json');
define('APPROVALS_FILE', DATA_DIR . '/approvals.json');
define('PENDING_FILE', DATA_DIR . '/pending.json');
define('TRANSACTIONS_FILE', DATA_DIR . '/transactions.json');
define('TELEGRAM_OFFSET_FILE', DATA_DIR . '/telegram_offset.txt');
define('SYSTEM_LOG_FILE', DATA_DIR . '/system.log');

// إعدادات بوت المعالجة (Polling)
define('TELEGRAM_ADMIN_CHAT_ID', '-4815944148');
define('TELEGRAM_LONG_POLLING_TIMEOUT', 20);
define('TRANSACTION_EXPIRY_SECONDS', 600);
define('TELEGRAM_NOTIFICATION_TEMPLATE_VERSION', 2);

function ensureDataDir(): void {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $htaccess = DATA_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
}

function readJsonFile(string $file): array {
    ensureDataDir();

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return [];
    }

    $data = [];
    if (flock($fp, LOCK_SH)) {
        rewind($fp);
        $raw = stream_get_contents($fp);
        $decoded = json_decode($raw ?: '[]', true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $data;
}

function writeJsonFile(string $file, array $data): bool {
    ensureDataDir();

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return false;
    }

    $ok = false;
    if (flock($fp, LOCK_EX)) {
        rewind($fp);
        ftruncate($fp, 0);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ok = ($json !== false) && (fwrite($fp, $json) !== false);
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $ok;
}

function updateJsonFile(string $file, callable $updater): array {
    ensureDataDir();

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return [];
    }

    $result = [];
    if (flock($fp, LOCK_EX)) {
        rewind($fp);
        $raw = stream_get_contents($fp);
        $decoded = json_decode($raw ?: '[]', true);
        $current = is_array($decoded) ? $decoded : [];

        $updated = $updater($current);
        $result = is_array($updated) ? $updated : $current;

        rewind($fp);
        ftruncate($fp, 0);
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json !== false) {
            fwrite($fp, $json);
            fflush($fp);
        }
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $result;
}

function generateTxId(?string $seed = null): string {
    $base = $seed !== null ? preg_replace('/[^a-zA-Z0-9-]/', '-', strtolower($seed)) : '';
    $base = trim((string)$base, '-');
    if ($base === '') {
        $base = substr(hash('sha1', uniqid((string)mt_rand(), true)), 0, 12);
    }
    return 'tx-' . $base;
}

function logSystem(string $event, array $context = []): void {
    ensureDataDir();
    $line = json_encode([
        'time' => date('c'),
        'event' => $event,
        'context' => $context,
    ], JSON_UNESCAPED_UNICODE);

    if ($line === false) {
        return;
    }

    file_put_contents(SYSTEM_LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ============================
// JSON Storage helpers
// ============================

/**
 * قراءة كل الفواتير من الملف
 */
function readInvoices(): array {
    return readJsonFile(INVOICES_FILE);
}

/**
 * حفظ قائمة الفواتير في الملف
 */
function writeInvoices(array $list): bool {
    return writeJsonFile(INVOICES_FILE, $list);
}

/**
 * إنشاء ID فريد للفاتورة
 */
function generateId(): string {
    return substr((string)time(), -8) . str_pad((string)rand(0, 999), 3, '0', STR_PAD_LEFT);
}

// ============================
// مساعد JSON response
// ============================
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================
// CORS (للتطوير المحلي)
// ============================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
