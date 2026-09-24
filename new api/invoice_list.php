<?php
 
// ============================
// api/invoice_list.php — قائمة الفواتير
// GET — يحتاج جلسة داشبورد
// ============================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

$list = readInvoices();

// ترتيب تنازلي حسب تاريخ الإنشاء
usort($list, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

// إرجاع أحدث 100 فاتورة فقط
$list = array_slice($list, 0, 100);

jsonResponse(['success' => true, 'invoices' => array_values($list)]);
