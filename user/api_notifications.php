<?php
// user/api_notifications.php
// Returns recent order notifications for the logged-in user

declare(strict_types=1);

// Ensure session for user_id
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Only allow logged-in users
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../classes/database.php';

try {
    $db = new database();
    $pdo = $db->opencon();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userId = (int)$_SESSION['user_id'];

    // Optional query params: all=1 to fetch all, or limit=N (max 200)
    $fetchAll = isset($_GET['all']) && $_GET['all'] === '1';
    $limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 200)) : 10;

    $sql = "
        SELECT 
            o.order_id,
            o.order_status,
            o.order_amount,
            o.order_date,
            o.order_needed,
            o.updated_at,
            p.pay_status,
            p.pay_method,
            p.pay_amount
        FROM orders o
        LEFT JOIN payments p ON p.order_id = o.order_id
        WHERE o.user_id = ?
        ORDER BY o.updated_at DESC, o.order_date DESC
    ";
    if (!$fetchAll) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $normalizeStatus = function (?string $s): array {
        $s = strtolower(trim((string)$s));
        $label = 'Pending';
        $type = 'pending';
        switch ($s) {
            case 'pending':
                $label = 'Pending'; $type = 'pending'; break;
            case 'in progress':
                $label = 'In Progress'; $type = 'in-progress'; break;
            case 'completed':
                $label = 'Completed'; $type = 'completed'; break;
            case 'canceled':
            case 'cancelled':
                $label = 'Canceled'; $type = 'canceled'; break;
            default:
                if ($s !== '') { $label = ucfirst($s); $type = $s; }
        }
        return ['label' => $label, 'type' => $type];
    };

    $data = array_map(function($r) use ($normalizeStatus) {
        $st = $normalizeStatus($r['order_status'] ?? 'pending');
        return [
            'order_id' => (int)$r['order_id'],
            'order_status' => $st['type'],
            'order_status_label' => $st['label'],
            'order_amount' => isset($r['order_amount']) ? (float)$r['order_amount'] : 0.0,
            'order_date' => $r['order_date'] ?? null,
            'order_needed' => $r['order_needed'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
            'payment' => [
                'status' => $r['pay_status'] ?? null,
                'method' => $r['pay_method'] ?? null,
                'amount' => isset($r['pay_amount']) ? (float)$r['pay_amount'] : null,
            ],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'items' => $data], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
