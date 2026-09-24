<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

$expectedSecret = env_value('TELEGRAM_WEBHOOK_SECRET');
$receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($expectedSecret === '' || !hash_equals($expectedSecret, $receivedSecret)) {
    http_response_code(403);
    exit('Forbidden');
}

$update = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($update)) {
    json_response(['ok' => true]);
}

$message = $update['message'] ?? null;
if (!is_array($message)) {
    json_response(['ok' => true]);
}

$chatId = require_telegram_admin($message);
if ($chatId === null) {
    json_response(['ok' => true]);
}

$text = trim((string) ($message['text'] ?? ''));
if ($text === '') {
    json_response(['ok' => true]);
}

try {
    handle_message($chatId, $text);
} catch (Throwable $error) {
    telegram_send($chatId, 'حدث خطأ أثناء تنفيذ الطلب. تحقق من إعدادات الخادم ثم حاول مرة أخرى.');
}

json_response(['ok' => true]);

function handle_message(string $chatId, string $text): void
{
    if ($text === '/start' || $text === '/help') {
        save_telegram_state($chatId, null);
        telegram_send($chatId, "لوحة تحكم الفواتير\n\n/new إضافة فاتورة\n/list عرض الفواتير\n/link ID إرسال رابط الدفع\n/stop ID إيقاف فاتورة\n/start_invoice ID تشغيل فاتورة\n/delete ID حذف فاتورة\n/cancel إلغاء العملية");
        return;
    }

    if ($text === '/cancel') {
        save_telegram_state($chatId, null);
        telegram_send($chatId, 'تم إلغاء العملية.');
        return;
    }

    if ($text === '/new') {
        save_telegram_state($chatId, ['step' => 'merchantName', 'invoice' => []]);
        telegram_send($chatId, 'أرسل اسم المستفيد:');
        return;
    }

    if ($text === '/list') {
        send_invoice_list($chatId);
        return;
    }

    if (preg_match('/^\/(link|stop|start_invoice|delete)\s+([A-Za-z0-9-]+)$/', $text, $matches)) {
        handle_invoice_command($chatId, $matches[1], $matches[2]);
        return;
    }

    $state = telegram_state($chatId);
    if ($state === null) {
        telegram_send($chatId, 'الأمر غير معروف. أرسل /help لرؤية الأوامر المتاحة.');
        return;
    }

    continue_create_invoice($chatId, $text, $state);
}

function continue_create_invoice(string $chatId, string $text, array $state): void
{
    $invoice = $state['invoice'] ?? [];
    $step = $state['step'] ?? '';

    if ($step === 'merchantName') {
        $invoice['merchantName'] = limit_text($text, 80);
        save_telegram_state($chatId, ['step' => 'amount', 'invoice' => $invoice]);
        telegram_send($chatId, 'أرسل المبلغ، مثل: 125.500');
        return;
    }

    if ($step === 'amount') {
        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $text) || (float) $text <= 0) {
            telegram_send($chatId, 'المبلغ غير صحيح. أرسله كرقم موجب وبحد أقصى 3 منازل عشرية.');
            return;
        }
        $invoice['amount'] = number_format((float) $text, 3, '.', '');
        save_telegram_state($chatId, ['step' => 'currency', 'invoice' => $invoice]);
        telegram_send($chatId, 'أرسل العملة: KD أو SAR أو AED أو USD');
        return;
    }

    if ($step === 'currency') {
        $currency = strtoupper($text);
        if (!in_array($currency, ['KD', 'SAR', 'AED', 'USD'], true)) {
            telegram_send($chatId, 'العملة غير صحيحة. اختر KD أو SAR أو AED أو USD.');
            return;
        }
        $invoice['currency'] = $currency;
        save_telegram_state($chatId, ['step' => 'message', 'invoice' => $invoice]);
        telegram_send($chatId, 'أرسل رسالة للعميل، أو أرسل /skip لتخطيها:');
        return;
    }

    if ($step === 'message') {
        $invoice['customerMessage'] = $text === '/skip' ? '' : limit_text($text, 140);
        $invoice['id'] = make_invoice_id();
        $invoice['active'] = true;
        $invoice['createdAt'] = gmdate('c');

        $invoices = invoices();
        array_unshift($invoices, $invoice);
        save_invoices($invoices);
        save_telegram_state($chatId, null);
        telegram_send($chatId, "تم إنشاء الفاتورة {$invoice['id']}\nالرابط:\n" . invoice_url($invoice));
    }
}

function send_invoice_list(string $chatId): void
{
    $invoices = invoices();
    if (!$invoices) {
        telegram_send($chatId, 'لا توجد فواتير محفوظة. أرسل /new لإضافة فاتورة.');
        return;
    }

    $lines = ['الفواتير:'];
    foreach (array_slice($invoices, 0, 30) as $invoice) {
        $status = ($invoice['active'] ?? true) ? 'مفعلة' : 'متوقفة';
        $lines[] = sprintf('%s | %s %s | %s | %s', $invoice['id'], $invoice['amount'], $invoice['currency'], $status, $invoice['merchantName']);
    }
    telegram_send($chatId, implode("\n", $lines));
}

function handle_invoice_command(string $chatId, string $command, string $id): void
{
    $invoices = invoices();
    $invoice = find_invoice($invoices, $id);
    if ($invoice === null) {
        telegram_send($chatId, 'لم يتم العثور على هذه الفاتورة. أرسل /list للتأكد من الرقم.');
        return;
    }

    if ($command === 'link') {
        telegram_send($chatId, invoice_url($invoice));
        return;
    }

    if ($command === 'delete') {
        save_invoices(array_values(array_filter($invoices, static function (array $item) use ($id): bool {
            return ($item['id'] ?? '') !== $id;
        })));
        telegram_send($chatId, 'تم حذف الفاتورة ' . $id . '.');
        return;
    }

    foreach ($invoices as &$item) {
        if (($item['id'] ?? '') === $id) {
            $item['active'] = $command === 'start_invoice';
            $item['updatedAt'] = gmdate('c');
            break;
        }
    }
    unset($item);
    save_invoices($invoices);
    telegram_send($chatId, ($command === 'start_invoice' ? 'تم تشغيل ' : 'تم إيقاف ') . $id . '.');
}
