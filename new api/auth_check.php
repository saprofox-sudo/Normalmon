<?php
 
// ============================
// api/auth_check.php
// include هذا الملف في أي endpoint يحتاج صلاحية
// ============================

session_start();

if (empty($_SESSION['dashboard_auth'])) {
    jsonResponse(['error' => 'غير مصرح', 'auth' => false], 401);
}

if (time() - ($_SESSION['auth_time'] ?? 0) > SESSION_LIFETIME) {
    session_destroy();
    jsonResponse(['error' => 'انتهت الجلسة', 'auth' => false], 401);
}

// تجديد وقت الجلسة عند كل طلب
$_SESSION['auth_time'] = time();
