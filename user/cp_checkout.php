<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/Mailer.php';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
        exit;
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        echo json_encode(['success' => false, 'message' => 'Invalid payload']);
        exit;
    }

    $db = new database();
    $pdo = $db->opencon();

    $user_id = (int)$_SESSION['user_id'];
    $full_name = trim($payload['full_name'] ?? '');
    $phone = trim($payload['phone'] ?? '');
    $street = trim($payload['street'] ?? '');
    $barangay = trim($payload['barangay'] ?? '');
    $municipality = trim($payload['municipality'] ?? '');
    $province = trim($payload['province'] ?? '');
    $event_date = $payload['event_date'] ?? null; // YYYY-MM-DD
    $email = trim($payload['email'] ?? '');

    $package_id = (int)($payload['package_id'] ?? 0);
    $package_name = trim($payload['package_name'] ?? '');
    $package_pax = (int)($payload['package_pax'] ?? 0);
    $base_price = (float)($payload['base_price'] ?? 0);
    $addon_pax = max(0, (int)($payload['addon_pax'] ?? 0));
    $chairs = max(0, (int)($payload['chairs'] ?? 0));
    $tables = max(0, (int)($payload['tables'] ?? 0));
    $notes = trim($payload['notes'] ?? '');

    $total_price = (float)($payload['total_price'] ?? 0);
    $deposit_amount = (float)($payload['deposit_amount'] ?? 0);
    $pay_now = isset($payload['pay_now']) ? (float)$payload['pay_now'] : null; // allow 50%..100%
    $pay_type = trim($payload['pay_type'] ?? '');
    $pay_number = trim($payload['pay_number'] ?? '');

    if (!$full_name || !$phone || !$email || !$street || !$barangay || !$municipality || !$province || !$event_date || !$package_name || $base_price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    if (strpos($email, '@') === false || strpos($email, '.') === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }

    // Build cp_place as "Street , Barangay , Municipality , Province"
    $cp_place = $street . ' , ' . $barangay . ' , ' . $municipality . ' , ' . $province;

    // Calculate total and deposit server-side to prevent tampering
    $calc_total = $base_price + ($addon_pax * 200);
    $calc_deposit = round($calc_total * 0.5, 2);
    if (abs($calc_total - $total_price) > 0.01 || abs($calc_deposit - $deposit_amount) > 0.01) {
        echo json_encode(['success' => false, 'message' => 'Invalid totals submitted']);
        exit;
    }

    // Validate pay_now range if provided; default to 50% if missing
    if ($pay_now === null) { $pay_now = $calc_deposit; }
    $pay_now = round($pay_now, 2);
    if ($pay_now < $calc_deposit - 0.01 || $pay_now > $calc_total + 0.01) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount. It must be between 50% and 100% of total.']);
        exit;
    }

    // Check date availability (no overlapping with existing eventbookings or cateringpackages)
    try {
        $day = date('Y-m-d', strtotime($event_date));
        if (!$day) { throw new Exception('Invalid date'); }
        $chk1 = $pdo->prepare("SELECT COUNT(*) FROM eventbookings WHERE DATE(eb_date)=? AND COALESCE(LOWER(eb_status),'') NOT IN ('completed','canceled','cancelled')");
        $chk1->execute([$day]);
        $bCount = (int)$chk1->fetchColumn();
        $chk2 = $pdo->prepare("SELECT COUNT(*) FROM cateringpackages WHERE cp_date = ?");
        $chk2->execute([$day]);
        $cCount = (int)$chk2->fetchColumn();
        if (($bCount + $cCount) > 0) {
            echo json_encode(['success'=>false,'message'=>'Sorry, that date is no longer available. Please choose another date.']);
            exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Unable to verify date availability. Please try again.']);
        exit;
    }

    $pdo->beginTransaction();

    // 1) Insert into cateringpackages
    // Build labeled CSV for cp_addon_pax: e.g., "5 pax, 3 tables"
    $addonParts = [];
    if ($addon_pax > 0) { $addonParts[] = $addon_pax . ' ' . 'pax'; }
    if ($tables > 0) { $addonParts[] = $tables . ' ' . ($tables === 1 ? 'table' : 'tables'); }
    if ($chairs > 0) { $addonParts[] = $chairs . ' ' . ($chairs === 1 ? 'chair' : 'chairs'); }
    $cp_addon_pax = $addonParts ? implode(', ', $addonParts) : null;

    $stmt = $pdo->prepare("INSERT INTO cateringpackages (user_id, cp_name, cp_phone, cp_email, cp_place, cp_date, cp_price, cp_addon_pax, cp_notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $full_name, $phone, $email, $cp_place, $event_date, $calc_total, $cp_addon_pax, $notes]);
    $cp_id = (int)$pdo->lastInsertId();

    // 2) Create order (optional for alignment with payments table)
    $order_status = 'pending';
    $order_needed = $event_date;
    $order_id = 0;
    try {
        $order_id = $db->addOrder($user_id, date('Y-m-d'), $order_status, $calc_total, $order_needed);
    } catch (Throwable $e) {
        // continue without order if schema differs
        $order_id = null;
    }

    // 3) Insert payment as partial/full depending on pay_now
    // Map selected pay_type to stored pay_method (preserve common labels)
    $typeNorm = strtolower($pay_type);
    $methodMap = [
        'gcash'   => 'GCash',
        'paymaya' => 'PayMaya',
        'paypal'  => 'PayPal',
        'card'    => 'Card',
        // legacy/fallbacks
        'cash'    => 'Cash',
        'online'  => 'Online',
        'credit'  => 'Credit',
    ];
    $pay_method = $methodMap[$typeNorm] ?? 'Online';
    $pay_status = 'Pending';
    $pay_date = date('Y-m-d');

    $res = $db->savePayment($order_id, $cp_id, $user_id, $pay_date, $pay_now, $pay_method, $pay_status);
    if ($res !== true) {
        throw new Exception(is_string($res) ? $res : 'Payment save failed');
    }

    $pdo->commit();

    // Send Partial payment email to user
    try {
    $userEmail = (string)($_SESSION['user_email'] ?? $email);
        $userFn = trim((string)($_SESSION['user_fn'] ?? ''));
        $userLn = trim((string)($_SESSION['user_ln'] ?? ''));
        $toName = trim($userFn . ' ' . $userLn);
        $mailer = new Mailer();
        $edata = [
            'full_name'   => $full_name ?: $toName,
            'event_date'  => $event_date,
            'place'       => $cp_place,
            'phone'       => $phone,
            'total_price' => $calc_total,
            'deposit'     => $pay_now,
            'addons'      => $cp_addon_pax,
            'notes'       => $notes,
        ];
    // Initial submission should be acknowledged as Pending until admin verifies payment
    [$subject,$html] = $mailer->renderCateringEmail($edata, 'Pending');
        if ($userEmail) { $mailer->send($userEmail, $toName ?: $full_name, $subject, $html); }
    } catch (Throwable $e) { /* ignore mail errors */ }

    echo json_encode(['success' => true, 'cp_id' => $cp_id, 'order_id' => $order_id]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
