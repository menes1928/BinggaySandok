<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/AddMenuController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$controller = new AddMenuController();

// Support both multipart/form-data and JSON
if (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];
    $result = $controller->add($data, []);
    if (!$result['success']) {
        http_response_code(422);
    }
    echo json_encode($result);
    exit;
}

// Assume multipart/form-data
$result = $controller->add($_POST, $_FILES);
if (!$result['success']) {
    http_response_code(422);
}

echo json_encode($result);
