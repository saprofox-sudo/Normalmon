<?php
 
// ============================
// api/logout.php — تسجيل الخروج
// POST
// ============================

require_once __DIR__ . '/../config.php';

session_start();
session_destroy();

jsonResponse(['success' => true]);
