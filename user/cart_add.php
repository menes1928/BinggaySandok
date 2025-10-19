<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
require_once __DIR__ . '/../classes/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $menu_id = isset($input['menu_id']) ? (int)$input['menu_id'] : 0;
    $delta = isset($input['delta']) ? (int)$input['delta'] : 1; // default increment by 1
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

    // Verify menu exists and available (optional)
    $chk = $pdo->prepare('SELECT menu_avail FROM menu WHERE menu_id = ?');
    $chk->execute([$menu_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Item not found');

    $uid = (int)$_SESSION['user_id'];
    $pdo->beginTransaction();
    // Upsert quantity
    $ins = $pdo->prepare('INSERT INTO user_cart_items (user_id, menu_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = GREATEST(1, quantity + VALUES(quantity))');
    $ins->execute([$uid, $menu_id, max(1, $delta)]);
    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
