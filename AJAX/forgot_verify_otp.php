<?php
session_start();
header('Content-Type: application/json');

function json_response($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $email = trim((string)($_POST['email'] ?? ''));
    $code  = trim((string)($_POST['code'] ?? ''));
    if ($email === '' || $code === '') {
        json_response(['ok' => false, 'error' => 'Email and code are required'], 400);
    }
    $key = strtolower($email);
    if (!isset($_SESSION['forgot'][$key])) {
        json_response(['ok' => false, 'error' => 'No OTP requested for this email'], 400);
    }
    $rec = &$_SESSION['forgot'][$key];
    $now = time();
    if ($now > (int)$rec['expires']) {
        unset($_SESSION['forgot'][$key]);
        json_response(['ok' => false, 'error' => 'OTP expired'], 400);
    }
    $rec['attempts'] = (int)($rec['attempts'] ?? 0) + 1;
    if ($rec['attempts'] > 10) {
        unset($_SESSION['forgot'][$key]);
        json_response(['ok' => false, 'error' => 'Too many attempts'], 429);
    }
    if (!hash_equals((string)$rec['code'], $code)) {
        json_response(['ok' => false, 'error' => 'Invalid OTP'], 400);
    }
    $rec['verified'] = true;
    // Shorten validity post-verify to 5 more minutes for reset
    $rec['expires'] = min($rec['expires'], $now + 300);
    json_response(['ok' => true, 'message' => 'OTP verified']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
