<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }

require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../config/app.php';

function normalize_menu_pic($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return 'https://placehold.co/800x600?text=Menu+Photo';
    $raw = str_replace('\\', '/', $raw);
    if (preg_match('~^https?://~i', $raw)) return $raw;
    $base = app_base_prefix();
    if (strpos($raw, '/') === false) return $base . '/menu/' . $raw;
    if (preg_match('~^(menu|images|uploads)(/|$)~i', $raw)) return $base . '/' . ltrim($raw, '/');
    if (preg_match('~(?:^|/)(?:Binggay|BinggaySandok)/(.+)$~i', $raw, $m)) return $base . '/' . ltrim($m[1], '/');
    return $base . '/' . ltrim($raw, '/');
}

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
    $stmt = $pdo->prepare("SELECT u.menu_id, u.quantity, m.menu_name, m.menu_desc, m.menu_price, m.menu_pic, m.menu_pax, m.menu_avail
                            FROM user_cart_items u
                            JOIN menu m ON m.menu_id = u.menu_id
                            WHERE u.user_id = ?");
    $stmt->execute([$uid]);
    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'id' => (int)$row['menu_id'],
            'name' => (string)($row['menu_name'] ?? ''),
            'description' => (string)($row['menu_desc'] ?? ''),
            'price' => (float)$row['menu_price'],
            'image' => normalize_menu_pic($row['menu_pic'] ?? ''),
            'servings' => (string)($row['menu_pax'] ?? ''),
            'available' => ((int)$row['menu_avail'] === 1),
            'quantity' => (int)$row['quantity'],
        ];
    }
    echo json_encode(['ok'=>true,'items'=>$items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
