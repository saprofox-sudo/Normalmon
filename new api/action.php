<?php
 
// ============================
// api/action.php
// رابط مباشر للقبول أو الرفض
// GET ?sessionId=XXX&action=approved|rejected&token=SECRET
// ============================

require_once __DIR__ . '/../config.php';

$sessionId = trim($_GET['sessionId'] ?? '');
$action    = trim($_GET['action']    ?? '');
$token     = trim($_GET['token']     ?? '');

// توكن أمان بسيط — نفس الـ DASH_PASS
if ($token !== DASH_PASS) {
    http_response_code(403);
    die('Forbidden');
}

if (!$sessionId || !in_array($action, ['approved', 'rejected'])) {
    http_response_code(400);
    die('Bad Request');
}

// احفظ في approvals.json
$file = __DIR__ . '/../data/approvals.json';
$dir  = dirname($file);

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// قراءة الملف مع file locking
$fp   = fopen($file, 'c+');
flock($fp, LOCK_EX);
$raw  = stream_get_contents($fp);
$data = json_decode($raw, true);
if (!is_array($data)) $data = [];

$data[$sessionId] = [
    'status'    => $action,
    'timestamp' => time(),
];

// احتفظ بآخر 200 فقط
if (count($data) > 200) {
    $data = array_slice($data, -200, null, true);
}

rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
flock($fp, LOCK_UN);
fclose($fp);

$label = $action === 'approved' ? '✅ تمت الموافقة' : '❌ تم الرفض';
echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#111;color:#fff;}
h1{font-size:2rem;}</style></head>
<body><h1>{$label} — يمكنك إغلاق هذه الصفحة</h1></body></html>";
