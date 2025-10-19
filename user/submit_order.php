<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../classes/database.php';

try {
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Please login to place an order.');
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new Exception('Invalid JSON'); }

    // Minimal validation
    $customer_name = trim($data['customer_name'] ?? '');
    $customer_phone = trim($data['customer_phone'] ?? '');
    $customer_email = trim($data['customer_email'] ?? '');
    $oa_street = trim($data['oa_street'] ?? '');
    $oa_city = trim($data['oa_city'] ?? '');
    $oa_province = trim($data['oa_province'] ?? '');
    $order_needed = trim($data['order_needed'] ?? '');
    $payment_method = trim($data['payment_method'] ?? 'Cash');
    $notes = trim($data['notes'] ?? '');
    $items = $data['items'] ?? [];
    $delivery_fee = isset($data['delivery_fee']) ? (float)$data['delivery_fee'] : 0.0;

    // Validate required fields
    if (!$customer_name || !$customer_phone || !$oa_street || !$oa_city || !$oa_province || !$order_needed) {
        throw new Exception('Missing required fields');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $order_needed)) {
        throw new Exception('Invalid order_needed date format (expected YYYY-MM-DD)');
    }
    // Enforce: no same-day orders. Must be at least tomorrow.
    $tz = new DateTimeZone('Asia/Manila');
    $minDate = new DateTime('tomorrow', $tz);
    $needed = DateTime::createFromFormat('Y-m-d', $order_needed, $tz);
    if (!$needed) {
        throw new Exception('Invalid order_needed date');
    }
    $needed->setTime(0, 0, 0);
    if ($needed < $minDate) {
        throw new Exception('Orders must be placed at least 1 day before the needed date.');
    }
    if (!is_array($items) || count($items) === 0) {
        throw new Exception('Cart is empty');
    }

    // Service area restriction: only Lipa City, Batangas
    if (strcasecmp($oa_city, 'Lipa City') !== 0 || strcasecmp($oa_province, 'Batangas') !== 0) {
        throw new Exception('We currently deliver only within Lipa City, Batangas.');
    }

    $db = new database();
    $pdo = $db->opencon();
    $pdo->beginTransaction();

    // User session (optional)
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Recalculate prices from DB to avoid client tampering
    $menuIds = [];
    foreach ($items as $it) {
        $mid = (int)($it['menu_id'] ?? 0);
        $qty = (float)($it['quantity'] ?? 0);
        if ($mid > 0 && $qty > 0) { $menuIds[$mid] = true; }
    }
    if (!$menuIds) { throw new Exception('No valid items in cart'); }

    $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
    $stmt = $pdo->prepare("SELECT menu_id, menu_price FROM menu WHERE menu_id IN ($placeholders)");
    $stmt->execute(array_keys($menuIds));
    $priceMap = [];
    foreach ($stmt->fetchAll() as $r) { $priceMap[(int)$r['menu_id']] = (float)$r['menu_price']; }

    $subtotalCalc = 0.0;
    foreach ($items as &$it) {
        $mid = (int)($it['menu_id'] ?? 0);
        $qty = (float)($it['quantity'] ?? 0);
        if ($mid <= 0 || $qty <= 0 || !isset($priceMap[$mid])) {
            throw new Exception('Invalid item in cart');
        }
        $it['price'] = $priceMap[$mid]; // enforce server price
        $subtotalCalc += $priceMap[$mid] * $qty;
    }
    unset($it);

    // Clamp delivery_fee to non-negative
    $delivery_fee = max(0.0, (float)$delivery_fee);
    $order_amount = round($subtotalCalc + $delivery_fee, 2);

    // Insert order (orders has default order_date)
    $order_status = 'pending';
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_status, order_amount, order_needed, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([$user_id, $order_status, $order_amount, $order_needed]);
    $order_id = (int)$pdo->lastInsertId();

    // Address
    $stmt = $pdo->prepare("INSERT INTO orderaddress (order_id, oa_street, oa_city, oa_province) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $oa_street, $oa_city, $oa_province]);

    // Items
    $oiStmt = $pdo->prepare("INSERT INTO orderitems (order_id, menu_id, oi_quantity, oi_price) VALUES (?, ?, ?, ?)");
    foreach ($items as $it) {
        $menu_id = (int)$it['menu_id'];
        $qty = (float)$it['quantity'];
        $price = (float)$it['price'];
        $oiStmt->execute([$order_id, $menu_id, $qty, $price]);
    }

    // Payment (single pending record per order)
    $rawMethod = strtolower($payment_method);
    switch ($rawMethod) {
        case 'cod':
            $method = 'Cash';
            break;
        case 'gcash':
        case 'bank':
            $method = 'Online';
            break;
        case 'card':
            $method = 'Credit';
            break;
        default:
            $method = 'Cash';
    }
    $stmt = $pdo->prepare("INSERT INTO payments (order_id, user_id, pay_date, pay_amount, pay_method, pay_status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$order_id, $user_id, date('Y-m-d'), $order_amount, $method, 'Pending']);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'reference' => 'SB-' . strtoupper(base_convert((string)$order_id, 10, 36))
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
