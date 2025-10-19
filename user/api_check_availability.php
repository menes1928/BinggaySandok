<?php
// Check if a given YYYY-MM-DD date is free across bookings and catering
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/database.php';

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $dateStr = '';
    if ($method === 'POST') {
        $dateStr = isset($_POST['date']) ? trim((string)$_POST['date']) : '';
    } else {
        $dateStr = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
    }
    if ($dateStr === '') { echo json_encode(['ok'=>false,'message'=>'Missing date']); exit; }

    // Validate YYYY-MM-DD
    $ts = strtotime($dateStr);
    if ($ts === false) { echo json_encode(['ok'=>false,'message'=>'Invalid date']); exit; }
    $normalized = date('Y-m-d', $ts);

    $db = new database();
    $pdo = $db->opencon();

    // Count bookings on this date (exclude clearly non-active states)
    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM eventbookings WHERE DATE(eb_date) = ? AND COALESCE(LOWER(eb_status), '') NOT IN ('completed','canceled','cancelled')");
    $stmt1->execute([$normalized]);
    $bookings = (int)$stmt1->fetchColumn();

    // Count catering on this date (no status column available; treat any as conflict)
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM cateringpackages WHERE cp_date = ?");
    $stmt2->execute([$normalized]);
    $catering = (int)$stmt2->fetchColumn();

    $total = $bookings + $catering;
    echo json_encode([
        'ok' => true,
        'date' => $normalized,
        'available' => $total === 0,
        'counts' => [ 'bookings' => $bookings, 'catering' => $catering, 'total' => $total ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Server error']);
}
// end of file
