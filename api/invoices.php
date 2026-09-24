<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

$invoices = invoices();
$id = trim((string) ($_GET['id'] ?? ''));

if ($id !== '') {
    $invoice = find_invoice($invoices, $id);
    json_response($invoice ?: ['error' => 'Invoice not found'], $invoice ? 200 : 404);
}

json_response($invoices);
