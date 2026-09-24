<?php
declare(strict_types=1);

const INVOICES_FILE = __DIR__ . '/../data/invoices.json';
const TELEGRAM_STATE_FILE = __DIR__ . '/../data/telegram-state.json';

function env_value(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : trim($value);
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function read_json_file(string $file, $fallback)
{
    if (!is_file($file)) {
        return $fallback;
    }

    $contents = file_get_contents($file);
    $data = json_decode($contents ?: '', true);
    return json_last_error() === JSON_ERROR_NONE ? $data : $fallback;
}

function write_json_file(string $file, $data): void
{
    $directory = dirname($file);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $handle = fopen($file, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open storage file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock storage file.');
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function invoices(): array
{
    $invoices = read_json_file(INVOICES_FILE, []);
    return is_array($invoices) ? array_values($invoices) : [];
}

function save_invoices(array $invoices): void
{
    write_json_file(INVOICES_FILE, array_values($invoices));
}

function find_invoice(array $invoices, string $id): ?array
{
    foreach ($invoices as $invoice) {
        if (($invoice['id'] ?? '') === $id) {
            return $invoice;
        }
    }
    return null;
}

function make_invoice_id(): string
{
    return 'INV-' . strtoupper(base_convert((string) time(), 10, 36)) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function invoice_url(array $invoice): string
{
    $base = rtrim(env_value('PUBLIC_BASE_URL'), '/');
    return $base . '/payment-request.html?invoice=' . rawurlencode((string) $invoice['id']);
}

function limit_text(string $text, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length);
}

function telegram_request(string $method, array $payload): array
{
    $token = env_value('TELEGRAM_BOT_TOKEN');
    if ($token === '') {
        throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $result = file_get_contents('https://api.telegram.org/bot' . $token . '/' . $method, false, $context);
    if ($result === false) {
        throw new RuntimeException('Telegram request failed.');
    }

    $decoded = json_decode($result, true);
    if (!is_array($decoded) || empty($decoded['ok'])) {
        throw new RuntimeException('Telegram API returned an error.');
    }
    return $decoded;
}

function telegram_send(string $chatId, string $text, ?array $keyboard = null): void
{
    $payload = ['chat_id' => $chatId, 'text' => $text];
    if ($keyboard !== null) {
        $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
    }
    telegram_request('sendMessage', $payload);
}

function telegram_state(string $chatId): ?array
{
    $states = read_json_file(TELEGRAM_STATE_FILE, []);
    return is_array($states) && isset($states[$chatId]) ? $states[$chatId] : null;
}

function save_telegram_state(string $chatId, ?array $state): void
{
    $states = read_json_file(TELEGRAM_STATE_FILE, []);
    if (!is_array($states)) {
        $states = [];
    }
    if ($state === null) {
        unset($states[$chatId]);
    } else {
        $states[$chatId] = $state;
    }
    write_json_file(TELEGRAM_STATE_FILE, $states);
}

function require_telegram_admin(array $message): ?string
{
    $chatId = (string) ($message['chat']['id'] ?? '');
    $allowed = env_value('TELEGRAM_ADMIN_CHAT_ID');
    if ($chatId === '' || $allowed === '' || !hash_equals($allowed, $chatId)) {
        return null;
    }
    return $chatId;
}
