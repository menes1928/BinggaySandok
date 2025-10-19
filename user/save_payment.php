<?php

session_start();
header('Content-Type: application/json');
require_once('../classes/database.php');
$con = new database();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pay_amount = $data['pay_amount'] ?? 0;
$pay_method = $data['pay_method'] ?? '';
$pay_status = 'Pending';
$pay_date = date('Y-m-d');
$order_needed = $data['order_needed'] ?? date('Y-m-d');
$oa_street = $data['oa_street'] ?? '';
$oa_city = $data['oa_city'] ?? '';
$oa_province = $data['oa_province'] ?? '';
$cart = $data['cart'] ?? [];

// 1. Create the order
$order_status = 'pending'; // match your enum values
$order_id = $con->addOrder($user_id, $pay_date, $order_status, $pay_amount, $order_needed);

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order creation failed']);
    exit;
}

// 2. Save the payment with the new order_id
$cp_id = null; // Set as needed

$success = $con->savePayment($order_id, $cp_id, $user_id, $pay_date, $pay_amount, $pay_method, $pay_status);

if ($success !== true) {
    echo json_encode(['success' => false, 'message' => $success]);
    exit;
}

// 3. Save the order address
try {
    $db = $con->opencon();
    $stmt = $db->prepare("INSERT INTO orderaddress (order_id, oa_street, oa_city, oa_province) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $oa_street, $oa_city, $oa_province]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to save address: ' . $e->getMessage()]);
    exit;
}

// 4. Save order items
try {
    $db = $con->opencon();
    foreach ($cart as $item) {
        // You need to get the menu_id based on menu_name and pack (pax)
        $stmt = $db->prepare("SELECT menu_id FROM menu WHERE menu_name = ? AND menu_pax = ?");
        $stmt->execute([$item['name'], $item['pack']]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($menu) {
            $menu_id = $menu['menu_id'];
            $oi_quantity = 1; // You can enhance this to allow quantity selection
            $oi_price = $item['price'];
            $stmt2 = $db->prepare("INSERT INTO orderitems (order_id, menu_id, oi_quantity, oi_price) VALUES (?, ?, ?, ?)");
            $stmt2->execute([$order_id, $menu_id, $oi_quantity, $oi_price]);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to save order items: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true]);
?>