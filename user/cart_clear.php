<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
require_once __DIR__ . '/../classes/database.php';

try {
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
    $pdo->prepare('DELETE FROM user_cart_items WHERE user_id = ?')->execute([$uid]);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
