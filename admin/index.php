<?php
// Central gate for /admin access
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($uid <= 0) {
    header('Location: ../index');
    exit;
}
$utype = isset($_SESSION['user_type']) ? (int)$_SESSION['user_type'] : 0;
if ($utype !== 1) {
    header('Location: ../user/index');
    exit;
}
// Admin OK: send to admin dashboard
header('Location: ./admin');
exit;
