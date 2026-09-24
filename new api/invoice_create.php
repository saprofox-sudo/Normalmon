<?php
 
// ============================
// api/invoice_create.php — إنشاء فاتورة جديدة
// POST { amount, currency, purposeKey, note }
// يحتاج جلسة داشبورد
// ============================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$amount     = isset($body['amount'])     ? (float) $body['amount']        : 0;
$currency   = trim($body['currency']     ?? '');
$purposeKey = trim($body['purposeKey']   ?? '');
$note       = trim($body['note']         ?? '');

$validPurposes = [
    'refund', 'payment', 'complete_order',
    'monthly_plan', 'subscription_fee', 'delivery_fee',
];

// ── Validation ──
$errors = [];
if ($amount <= 0)                            $errors[] = 'المبلغ يجب أن يكون أكبر من صفر';
if ($currency === '')                        $errors[] = 'العملة مطلوبة';
if (!in_array($purposeKey, $validPurposes)) $errors[] = 'غرض الدفعة غير صحيح';

if ($errors) {
    jsonResponse(['success' => false, 'errors' => $errors], 422);
}

// ── بناء كائن الفاتورة ──
$invoice = [
    'id'            => generateId(),
    'transactionId' => generateId(),
    'amount'        => $amount,
    'currency'      => $currency,
    'purposeKey'    => $purposeKey,
    'note'          => $note,
    'createdAt'     => date('c'),   // ISO 8601
];

// ── حفظ في invoices.json ──
$list   = readInvoices();
$list[] = $invoice;

if (!writeInvoices($list)) {
    jsonResponse(['success' => false, 'error' => 'تعذّر حفظ الفاتورة'], 500);
}

jsonResponse(['success' => true] + $invoice);
