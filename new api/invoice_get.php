<?php
 
// ============================
// api/invoice_get.php — جلب فاتورة واحدة
// GET ?invoiceId=XXXX
// عام — لا يحتاج جلسة (تستخدمه صفحة الدفع)
// ============================

require_once __DIR__ . '/../config.php';

$invoiceId = trim($_GET['invoiceId'] ?? '');

if ($invoiceId === '') {
    jsonResponse(['error' => 'invoiceId مطلوب'], 400);
}

$list    = readInvoices();
$invoice = null;

foreach ($list as $inv) {
    if (($inv['id'] ?? '') === $invoiceId) {
        $invoice = $inv;
        break;
    }
}

if (!$invoice) {
    jsonResponse(['error' => 'الفاتورة غير موجودة'], 404);
}

jsonResponse(['success' => true] + $invoice);
