<?php
 
require_once __DIR__ . '/../config.php';

$sessionId = trim($_GET['sessionId'] ?? '');
if ($sessionId === '') {
    jsonResponse(['error' => 'sessionId مطلوب'], 400);
}

$approvalsFile = __DIR__ . '/../data/approvals.json';
$pendingFile   = __DIR__ . '/../data/pending.json';

// سجّل في pending
$rawP    = @file_get_contents($pendingFile);
$pending = ($rawP ? json_decode($rawP, true) : null) ?? [];
if (!isset($pending[$sessionId])) {
    $pending[$sessionId] = ['createdAt' => time()];
    @file_put_contents($pendingFile, json_encode($pending), LOCK_EX);
}

// قراءة approvals
$fp = @fopen($approvalsFile, 'r');
if (!$fp) {
    jsonResponse(['approved' => false, 'rejected' => false]);
}
flock($fp, LOCK_SH);
$raw = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);

$data = json_decode($raw, true);
if (!is_array($data)) $data = [];

if (isset($data[$sessionId])) {
    $status    = $data[$sessionId]['status']    ?? '';
    $readCount = ($data[$sessionId]['readCount'] ?? 0) + 1;

    // نبقّي الـ session في الملف لـ 3 قراءات قبل ما نمسحه
    // هيك نضمن إن الـ polling يلتقطه
    if ($readCount >= 3) {
        unset($data[$sessionId]);
        unset($pending[$sessionId]);
        $fp2 = fopen($approvalsFile, 'c');
        flock($fp2, LOCK_EX);
        ftruncate($fp2, 0); rewind($fp2);
        fwrite($fp2, json_encode($data));
        flock($fp2, LOCK_UN);
        fclose($fp2);
        @file_put_contents($pendingFile, json_encode($pending), LOCK_EX);
    } else {
        // زوّد عداد القراءات
        $data[$sessionId]['readCount'] = $readCount;
        $fp2 = fopen($approvalsFile, 'c');
        flock($fp2, LOCK_EX);
        ftruncate($fp2, 0); rewind($fp2);
        fwrite($fp2, json_encode($data));
        flock($fp2, LOCK_UN);
        fclose($fp2);
    }

    if ($status === 'approved') {
        jsonResponse(['approved' => true,  'rejected' => false]);
    } elseif ($status === 'rejected') {
        jsonResponse(['approved' => false, 'rejected' => true]);
    } else {
        // status غير معروف — تعامل معه كـ pending
        jsonResponse(['approved' => false, 'rejected' => false]);
    }
}

jsonResponse(['approved' => false, 'rejected' => false]);