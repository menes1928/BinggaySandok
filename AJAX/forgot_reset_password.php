<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/database.php';

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
    $pass1 = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['confirm'] ?? '');
    if ($email === '') { json_response(['ok' => false, 'error' => 'Email is required'], 400); }
    if ($pass1 === '' || $pass2 === '') { json_response(['ok' => false, 'error' => 'Password and confirm are required'], 400); }
    if (!hash_equals($pass1, $pass2)) { json_response(['ok' => false, 'error' => 'Passwords do not match'], 400); }

    // Enforce policy: min 8, 1 uppercase, 1 lowercase, 1 digit, 1 special character
    $policyRegex = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
    if (!preg_match($policyRegex, $pass1)) {
        json_response(['ok' => false, 'error' => 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character'], 400);
    }

    $key = strtolower($email);
    if (!isset($_SESSION['forgot'][$key])) {
        json_response(['ok' => false, 'error' => 'No OTP session'], 400);
    }
    $rec = $_SESSION['forgot'][$key];
    $now = time();
    if (empty($rec['verified']) || $now > (int)$rec['expires']) {
        unset($_SESSION['forgot'][$key]);
        json_response(['ok' => false, 'error' => 'OTP not verified or expired'], 400);
    }

    $db = (new database())->opencon();
    $stmt = $db->prepare('SELECT user_id FROM users WHERE user_email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['forgot'][$key]);
        json_response(['ok' => false, 'error' => 'Account not found'], 404);
    }

    $hash = password_hash($pass1, PASSWORD_BCRYPT);
    $upd = $db->prepare('UPDATE users SET user_password = ?, updated_at = NOW() WHERE user_id = ?');
    $ok = $upd->execute([$hash, (int)$user['user_id']]);
    unset($_SESSION['forgot'][$key]);
    if (!$ok) {
        json_response(['ok' => false, 'error' => 'Failed to update password'], 500);
    }
    json_response(['ok' => true, 'message' => 'Password updated successfully']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
