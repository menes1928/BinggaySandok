<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/database.php';
$db = new database();
$pdo = $db->opencon();

$action = isset($_GET['action']) ? (string)$_GET['action'] : (isset($_POST['action']) ? (string)$_POST['action'] : '');
$etId = isset($_GET['event_type_id']) ? (int)$_GET['event_type_id'] : (isset($_POST['event_type_id']) ? (int)$_POST['event_type_id'] : 0);

try {
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT et.event_type_id, et.name, et.min_package_pax, et.max_package_pax, et.notes, et.created_at, et.updated_at,
                                    COALESCE((SELECT COUNT(*) FROM event_type_packages ep WHERE ep.event_type_id=et.event_type_id),0) AS package_count
                             FROM event_types et
                             ORDER BY et.name ASC");
        echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
        exit;
    }

    if ($action === 'list_packages') {
        $rows = $pdo->query("SELECT package_id, name, pax, base_price, is_active FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success'=>true,'data'=>$rows]);
        exit;
    }

    if ($action === 'get' && $etId > 0) {
        $g = $pdo->prepare("SELECT * FROM event_types WHERE event_type_id=?");
        $g->execute([$etId]);
        $row = $g->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
        $m = $pdo->prepare("SELECT package_id FROM event_type_packages WHERE event_type_id=? ORDER BY package_id");
        $m->execute([$etId]);
        $pkgIds = array_map('intval', array_column($m->fetchAll(PDO::FETCH_ASSOC) ?: [], 'package_id'));
        echo json_encode(['success'=>true,'data'=>$row,'package_ids'=>$pkgIds]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
}
// end of file
