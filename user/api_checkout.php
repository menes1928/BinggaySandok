<?php
// API: Create order + items + address + payment from cart
// Input: JSON { items:[{id,quantity,price}], order_needed: 'YYYY-MM-DD', address:{street,city,province}, pay_method: 'Cash'|'Gcash'|'Card'|'Paypal'|'Paymaya' }
// Output: JSON { ok:true, order_id, payment_id } or { ok:false, error }

header('Content-Type: application/json');

// Start session to read logged in user
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new Exception('Invalid JSON payload');
    }

    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $orderNeeded = isset($data['order_needed']) ? trim((string)$data['order_needed']) : '';
    $address = isset($data['address']) && is_array($data['address']) ? $data['address'] : [];
    $payMethod = isset($data['pay_method']) ? trim((string)$data['pay_method']) : '';

    if (count($items) === 0) throw new Exception('Cart is empty');
    if ($orderNeeded === '') throw new Exception('Please select a date needed');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderNeeded)) throw new Exception('Invalid date format');
    // Must be at least 1 day in advance (no same-day orders)
    $tz = new DateTimeZone('Asia/Manila');
    $minDate = new DateTime('tomorrow', $tz); // start of next day
    $needed = DateTime::createFromFormat('Y-m-d', $orderNeeded, $tz);
    if (!$needed) throw new Exception('Invalid date');
    $needed->setTime(0, 0, 0);
    if ($needed < $minDate) {
        throw new Exception('Orders must be placed at least 1 day in advance.');
    }
    $allowedMethods = ['Cash','Gcash','Card','Paypal','Paymaya'];
    if (!in_array($payMethod, $allowedMethods, true)) throw new Exception('Invalid payment method');

    $street = isset($address['street']) ? trim((string)$address['street']) : '';
    $city = isset($address['city']) ? trim((string)$address['city']) : '';
    $province = isset($address['province']) ? trim((string)$address['province']) : '';
    if ($street === '' || $city === '' || $province === '') throw new Exception('Please complete the address');

    // Compute totals and sanitize items
    $sanitizedItems = [];
    $total = 0.0;
    foreach ($items as $it) {
        $menuId = isset($it['id']) ? (int)$it['id'] : 0;
        $qty = isset($it['quantity']) ? (float)$it['quantity'] : 0.0;
        $price = isset($it['price']) ? (float)$it['price'] : 0.0;
        if ($menuId <= 0 || $qty <= 0 || $price < 0) continue;
        $line = round($qty * $price, 2);
        $total += $line;
        $sanitizedItems[] = [ 'menu_id' => $menuId, 'qty' => $qty, 'price' => $price ];
    }
    if (count($sanitizedItems) === 0) throw new Exception('Nothing to checkout');
    $total = round($total, 2);

    require_once __DIR__ . '/../classes/database.php';
    $db = new database();
    $pdo = $db->opencon();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Transaction
    $pdo->beginTransaction();

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Insert into orders
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_status, order_amount, order_needed, created_at, updated_at) VALUES (?, 'pending', ?, ?, NOW(), NOW())");
    $stmt->execute([$userId, $total, $orderNeeded]);
    $orderId = (int)$pdo->lastInsertId();

    // Insert order items
    $oi = $pdo->prepare("INSERT INTO orderitems (order_id, menu_id, oi_quantity, oi_price) VALUES (?, ?, ?, ?)");
    foreach ($sanitizedItems as $si) {
        $oi->execute([$orderId, $si['menu_id'], $si['qty'], $si['price']]);
    }

    // Insert order address (unique per order)
    $oa = $pdo->prepare("INSERT INTO orderaddress (order_id, oa_street, oa_city, oa_province, created_at) VALUES (?, ?, ?, ?, NOW())");
    $oa->execute([$orderId, $street, $city, $province]);

    // Insert payment record (one per order)
    $payDate = date('Y-m-d');
    $pay = $pdo->prepare("INSERT INTO payments (order_id, user_id, pay_date, pay_amount, pay_method, pay_status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
    $pay->execute([$orderId, $userId, $payDate, $total, $payMethod]);
    $paymentId = (int)$pdo->lastInsertId();

    $pdo->commit();

    echo json_encode(['ok' => true, 'order_id' => $orderId, 'payment_id' => $paymentId]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
