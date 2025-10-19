<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
require_once __DIR__ . '/../classes/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $menu_id = isset($input['menu_id']) ? (int)$input['menu_id'] : 0;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
    if ($menu_id <= 0) { throw new Exception('Invalid menu item'); }

    $db = new database();
    $pdo = $db->opencon();
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_cart_items (
        user_id INT NOT NULL,
        menu_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, menu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $uid = (int)$_SESSION['user_id'];
    if ($quantity <= 0) {
        $del = $pdo->prepare('DELETE FROM user_cart_items WHERE user_id = ? AND menu_id = ?');
        $del->execute([$uid, $menu_id]);
    } else {
        $up = $pdo->prepare('INSERT INTO user_cart_items (user_id, menu_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)');
        $up->execute([$uid, $menu_id, $quantity]);
    }
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
