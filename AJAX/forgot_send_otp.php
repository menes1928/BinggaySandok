<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/Mailer.php';

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
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'error' => 'Valid email is required'], 400);
    }

    $db = (new database())->opencon();
    $stmt = $db->prepare('SELECT user_id, COALESCE(NULLIF(CONCAT(TRIM(user_fn), " ", TRIM(user_ln)), " "), user_username, user_email) AS name FROM users WHERE user_email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        // Explicit error when email is not registered (as requested)
        json_response(['ok' => false, 'error' => 'Email is not registered'], 404);
    }

    // Basic rate-limit: allow one new OTP per 60 seconds per email
    $key = strtolower($email);
    $now = time();
    if (!isset($_SESSION['forgot'])) { $_SESSION['forgot'] = []; }
    $rec = $_SESSION['forgot'][$key] ?? null;
    if ($rec && isset($rec['sent_at']) && ($now - (int)$rec['sent_at']) < 60) {
        $retryAfter = 60 - ($now - (int)$rec['sent_at']);
        json_response(['ok' => false, 'error' => 'Please wait before requesting another OTP', 'retry_after' => $retryAfter], 429);
    }

    // Generate 6-character alphanumeric OTP
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $otp = '';
    for ($i = 0; $i < 6; $i++) {
        $otp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    // Store in session with 10-minute expiry
    $_SESSION['forgot'][$key] = [
        'email' => $email,
        'code' => $otp,
        'expires' => $now + 600,
        'verified' => false,
        'sent_at' => $now,
        'attempts' => 0,
        'user_id' => (int)$user['user_id'],
    ];

    $mailer = new Mailer();
    $subject = 'Your Sandok ni Binggay password reset code';
    $brand = 'Sandok ni Binggay';
    $primary = '#1B4332'; $gold = '#D4AF37'; $text = '#0b2016';
    $html = "<!doctype html><html><body style=\"margin:0;padding:0;background:#f5f7f9;font-family:Segoe UI,Roboto,Arial,sans-serif;color:$text\"><table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background:#f5f7f9;padding:24px'><tr><td align='center'><table role='presentation' width='640' cellspacing='0' cellpadding='0' style='max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.08)'><tr><td style='background:$primary;padding:20px 24px'><h1 style='margin:0;font-size:20px;color:#fff'>$brand</h1><div style='margin-top:4px;font-size:12px;color:#e5f2ec'>Password Reset</div></td></tr><tr><td style='padding:24px'><p style='margin:0 0 12px'>Here is your one-time code:</p><div style='font-size:28px;letter-spacing:6px;font-weight:700;color:$primary;background:#f0f6f3;border:1px solid #d9e9e1;border-radius:10px;display:inline-block;padding:12px 16px'>$otp</div><p style='margin:16px 0 0;font-size:12px;color:#5b7268'>This code will expire in 10 minutes.</p></td></tr><tr><td style='background:#f0f6f3;padding:12px 24px;font-size:12px;color:#5b7268'>If you did not request this, you can safely ignore this email.</td></tr></table></td></tr></table></body></html>";

    $sent = $mailer->send($email, (string)($user['name'] ?? $email), $subject, $html);
    if (!$sent) {
        json_response(['ok' => false, 'error' => 'Failed to send email. Try again later.'], 500);
    }
    json_response(['ok' => true, 'message' => 'OTP sent successfully.']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
