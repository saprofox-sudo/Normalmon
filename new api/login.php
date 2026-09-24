<?php
 
// ============================
// api/login.php — تسجيل الدخول للداشبورد
// POST { username, password }
// ============================

require_once __DIR__ . '/../config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === DASH_USER && $password === DASH_PASS) {
    $_SESSION['dashboard_auth'] = true;
    $_SESSION['auth_time']      = time();
    jsonResponse(['success' => true]);
} else {
    jsonResponse(['success' => false, 'error' => 'بيانات الدخول غير صحيحة'], 401);
}
