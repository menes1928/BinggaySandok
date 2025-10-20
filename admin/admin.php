<?php
// You can add PHP logic here for authentication, database connections, etc.
session_start();

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($uid <= 0) {
    header('Location: ../index');
    exit;
}
$utype = isset($_SESSION['user_type']) ? (int)$_SESSION['user_type'] : 0;
if ($utype !== 1) {
    header('Location: ../user/index');
    exit;
}

// Sample data arrays (in a real application, these would come from a database)
$salesData = [
    ['month' => 'Jan', 'revenue' => 45000, 'orders' => 156],
    ['month' => 'Feb', 'revenue' => 52000, 'orders' => 189],
    ['month' => 'Mar', 'revenue' => 48000, 'orders' => 167],
    ['month' => 'Apr', 'revenue' => 61000, 'orders' => 221],
    ['month' => 'May', 'revenue' => 55000, 'orders' => 198],
    ['month' => 'Jun', 'revenue' => 67000, 'orders' => 245]
];

// Removed dummy $recentOrders and $upcomingBookings arrays.

// Early action handling before any output (prevents header issues)
require_once __DIR__ . '/../classes/database.php';
$db = new database();
$sectionEarly = $_GET['section'] ?? '';
$sectionEarly = is_string($sectionEarly) ? strtolower($sectionEarly) : '';
if ($sectionEarly === 'products') {
    $action = $_GET['action'] ?? '';
    $mid = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;
    // Allow POST for AJAX actions
    if (!$action) { $action = $_POST['action'] ?? ''; }
    if ($mid <= 0 && isset($_POST['menu_id'])) { $mid = (int)$_POST['menu_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    if ($action && $mid > 0) {
        if ($action === 'get_menu' && $isAjaxAction) {
            header('Content-Type: application/json');
            try {
                $row = $db->viewMenuID($mid);
                if (!$row) { echo json_encode(['success'=>false,'message'=>'Menu not found']); exit; }
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'menu_id' => (int)($row['menu_id'] ?? $mid),
                        'menu_name' => (string)($row['menu_name'] ?? ''),
                        'menu_desc' => (string)($row['menu_desc'] ?? ''),
                        'menu_pax' => (string)($row['menu_pax'] ?? ''),
                        'menu_price' => (float)($row['menu_price'] ?? 0),
                        'menu_avail' => (int)($row['menu_avail'] ?? 1),
                        'menu_pic' => (string)($row['menu_pic'] ?? '')
                    ]
                ]);
            } catch (Throwable $e) {
                echo json_encode(['success'=>false,'message'=>'Error fetching menu']);
            }
            exit;
        }
        if ($action === 'update' && $isAjaxAction) {
            header('Content-Type: application/json');
            try {
                $name = trim((string)($_POST['name'] ?? ''));
                $desc = trim((string)($_POST['description'] ?? ''));
                $paxVal = trim((string)($_POST['pax'] ?? ''));
                $price = (float)($_POST['price'] ?? 0);
                $availVal = (string)($_POST['availability'] ?? '1');
                $availInt = ($availVal === '0' || $availVal === 0) ? 0 : 1;
                if ($name === '') { echo json_encode(['success'=>false,'message'=>'Name is required']); exit; }
                if ($price < 0) { echo json_encode(['success'=>false,'message'=>'Price must be non-negative']); exit; }
                $newPicName = null;
                if (!empty($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                    $orig = $_FILES['photo']['name'];
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','webp','gif','avif'];
                    if (!in_array($ext, $allowed)) { echo json_encode(['success'=>false,'message'=>'Unsupported image type']); exit; }
                    $newPicName = uniqid('menu_', true) . '.' . $ext;
                    $destDir = realpath(__DIR__ . '/../menu');
                    if ($destDir === false) { $destDir = __DIR__ . '/../menu'; }
                    @mkdir($destDir, 0775, true);
                    $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newPicName;
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                        echo json_encode(['success'=>false,'message'=>'Failed to save uploaded image']); exit;
                    }
                }
                $ok = false;
                if (method_exists($db, 'updateMenu')) {
                    try {
                        $ok = $db->updateMenu($mid, $name, $desc, $paxVal, $price, $availInt, $newPicName);
                    } catch (Throwable $e) { $ok = false; }
                }
                if (!$ok) {
                    $ok = true;
                    try { if (method_exists($db, 'updateMenuName')) $db->updateMenuName($mid, $name); } catch (Throwable $e) { $ok = false; }
                    try { if (method_exists($db, 'updateMenuDesc')) $db->updateMenuDesc($mid, $desc); } catch (Throwable $e) {}
                    try { if (method_exists($db, 'updateMenuPax')) $db->updateMenuPax($mid, $paxVal); } catch (Throwable $e) {}
                    try { if (method_exists($db, 'updateMenuPrice')) $db->updateMenuPrice($mid, $price); } catch (Throwable $e) {}
                    try { if (method_exists($db, 'setMenuAvailability')) $db->setMenuAvailability($mid, $availInt); } catch (Throwable $e) {}
                    try { if ($newPicName && method_exists($db, 'updateMenuPic')) $db->updateMenuPic($mid, $newPicName); } catch (Throwable $e) {}
                }
                if ($ok) { echo json_encode(['success'=>true]); } else { echo json_encode(['success'=>false,'message'=>'Update not supported by database layer']); }
            } catch (Throwable $e) {
                echo json_encode(['success'=>false,'message'=>'Error updating menu']);
            }
            exit;
        }
        if ($action === 'toggle') {
            try {
                $current = $db->viewMenuID($mid);
                if ($current) {
                    $newAvail = ((int)$current['menu_avail'] === 1) ? 0 : 1;
                    $db->setMenuAvailability($mid, $newAvail);
                }
            } catch (Throwable $e) {}
        } elseif ($action === 'archive' || $action === 'delete') {
            try { $db->archiveMenu($mid); } catch (Throwable $e) {}
        }
        if ($isAjaxAction) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } else {
            // After actions, return to Products with filters cleared
            header('Location: ?section=products');
            exit;
        }
    }
}
// Orders early actions (AJAX endpoints)
if ($sectionEarly === 'orders') {
    $action = $_GET['action'] ?? '';
    $oid = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    if (!$action) { $action = $_POST['action'] ?? ''; }
    if ($oid <= 0 && isset($_POST['order_id'])) { $oid = (int)$_POST['order_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

    if ($action && $oid > 0) {
        $pdo = $db->opencon();
        if ($action === 'get_order' && $isAjaxAction) {
            header('Content-Type: application/json');
            try {
                $stmt = $pdo->prepare("SELECT o.*, u.user_fn, u.user_ln, oa.oa_street, oa.oa_city, oa.oa_province, p.pay_method, p.pay_date, p.pay_status
                                        FROM orders o
                                        LEFT JOIN users u ON u.user_id = o.user_id
                                        LEFT JOIN orderaddress oa ON oa.order_id = o.order_id
                                        LEFT JOIN payments p ON p.order_id = o.order_id
                                        WHERE o.order_id = ?");
                $stmt->execute([$oid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) { echo json_encode(['success'=>false,'message'=>'Order not found']); exit; }
                // Fetch items for this order
                $sti = $pdo->prepare("SELECT oi.oi_quantity, oi.oi_price, m.menu_name FROM orderitems oi LEFT JOIN menu m ON m.menu_id=oi.menu_id WHERE oi.order_id=? ORDER BY oi.oi_id ASC");
                $sti->execute([$oid]);
                $items = $sti->fetchAll(PDO::FETCH_ASSOC);
                $row['items'] = $items;
                echo json_encode(['success'=>true,'data'=>$row]);
            } catch (Throwable $e) {
                echo json_encode(['success'=>false,'message'=>'Error fetching order']);
            }
            exit;
        }
    if ($action === 'update_order' && $isAjaxAction) {
            header('Content-Type: application/json');
            try {
                $status = (string)($_POST['order_status'] ?? 'pending');
                $needed = (string)($_POST['order_needed'] ?? date('Y-m-d'));
                $street = trim((string)($_POST['oa_street'] ?? ''));
                $city = trim((string)($_POST['oa_city'] ?? ''));
                $province = trim((string)($_POST['oa_province'] ?? ''));
                $pdo->beginTransaction();
                $ok1 = $pdo->prepare("UPDATE orders SET order_status = ?, order_needed = ? WHERE order_id = ?")
                          ->execute([$status, $needed, $oid]);
                // Upsert orderaddress
                $stmt = $pdo->prepare("SELECT oa_id FROM orderaddress WHERE order_id = ?");
                $stmt->execute([$oid]);
                if ($stmt->fetchColumn()) {
                    $pdo->prepare("UPDATE orderaddress SET oa_street=?, oa_city=?, oa_province=? WHERE order_id=?")
                        ->execute([$street, $city, $province, $oid]);
                } else {
                    $pdo->prepare("INSERT INTO orderaddress (order_id, oa_street, oa_city, oa_province) VALUES (?, ?, ?, ?)")
                        ->execute([$oid, $street, $city, $province]);
                }
                $pdo->commit();
                echo json_encode(['success'=>true]);
            } catch (Throwable $e) {
                try { $pdo->rollBack(); } catch (Throwable $e2) {}
                echo json_encode(['success'=>false,'message'=>'Error updating order']);
            }
            exit;
        }
        // Set only the order status (do not touch address or payments)
        if ($action === 'set_status') {
            try {
                $newStatus = strtolower(trim((string)($_POST['order_status'] ?? '')));
                $allowed = ['pending','in progress','completed','canceled','cancelled'];
                if (!in_array($newStatus, $allowed, true)) { $newStatus = 'pending'; }
                $pdo->prepare("UPDATE orders SET order_status=? WHERE order_id=?")->execute([$newStatus, $oid]);
            } catch (Throwable $e) {}
        }
        if ($action === 'mark_paid') {
            try {
                // Try to update existing payment record
                $upd = $pdo->prepare("UPDATE payments SET pay_status='Paid', pay_date=CURDATE() WHERE order_id=?");
                $upd->execute([$oid]);
                if ($upd->rowCount() === 0) {
                    // Create if not exists
                    $ord = $pdo->prepare("SELECT user_id, order_amount, order_date FROM orders WHERE order_id=?");
                    $ord->execute([$oid]);
                    $o = $ord->fetch(PDO::FETCH_ASSOC);
                    if ($o) {
                        $pdo->prepare("INSERT INTO payments (order_id, cp_id, user_id, pay_date, pay_amount, pay_method, pay_status) VALUES (?, NULL, ?, CURDATE(), ?, 'Cash', 'Paid')")
                            ->execute([$oid, (int)$o['user_id'], (float)$o['order_amount']]);
                    }
                }
                // Also mark the order itself as Completed
                $pdo->prepare("UPDATE orders SET order_status='Completed' WHERE order_id=?")
                    ->execute([$oid]);
            } catch (Throwable $e) {}
        } elseif ($action === 'delete') {
            try {
                // Remove payments first (no FK cascade in schema), then delete order (cascades items and address)
                $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$oid]);
                $pdo->prepare("DELETE FROM orders WHERE order_id = ?")->execute([$oid]);
            } catch (Throwable $e) {}
        }
        if ($isAjaxAction) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true]);
            exit;
        } else {
            header('Location: ?section=orders');
            exit;
        }
    }
}

// Dashboard early actions (AJAX endpoints)
if ($sectionEarly === 'dashboard') {
    $action = $_GET['action'] ?? '';
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    if ($action && $isAjaxAction) {
        header('Content-Type: application/json');
        try {
            $pdo = $db->opencon(); // Open database connection
            if ($action === 'get_total_orders') {
                // Orders only (exclude catering packages)
                $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
                $ordersOnly = (int)$stmt->fetchColumn();
                echo json_encode(['ok'=>true,'totalOrders'=>$ordersOnly]);
                exit;
            }
            if ($action === 'get_total_revenue') {
                // Sum of collected payments (Paid or Partial) for Orders (order_id) and Catering (cp_id), exclude booking-reserved rows (both NULL)
                $sql = "SELECT COALESCE(SUM(pay_amount),0) AS total
                        FROM payments
                        WHERE (pay_status IN ('Paid','Partial'))
                          AND (order_id IS NOT NULL OR cp_id IS NOT NULL)";
                $stmt = $pdo->query($sql);
                $total = (float)$stmt->fetchColumn();
                echo json_encode(['ok'=>true,'totalRevenue'=>$total]);
                exit;
            }
            if ($action === 'get_best_sellers') {
                // All-time best sellers (top 10) across all orders (excluding canceled)
                $sql = "SELECT oi.menu_id,
                               m.menu_name,
                               m.menu_pic,
                               SUM(COALESCE(oi.oi_quantity,1)) AS qty
                        FROM orderitems oi
                        JOIN orders o ON o.order_id = oi.order_id
                        JOIN menu m ON m.menu_id = oi.menu_id
                        WHERE (o.order_status IS NULL OR LOWER(o.order_status) <> 'canceled')
                        GROUP BY oi.menu_id, m.menu_name, m.menu_pic
                        ORDER BY qty DESC, m.menu_name ASC
                        LIMIT 10";
                $stmt = $pdo->query($sql);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $ranked = [];
                $rank = 1;
                foreach ($rows as $r) {
                    // Build image URL (assumes images are stored in ../images/menu/ or uploads/menu/). Adjust as needed.
                    $pic = isset($r['menu_pic']) ? trim((string)$r['menu_pic']) : '';
                    $imgUrl = '';
                    if ($pic !== '') {
                        if (file_exists(__DIR__ . '/../menu/' . $pic)) {
                            $imgUrl = '../menu/' . rawurlencode($pic);
                        } elseif (file_exists(__DIR__ . '/../uploads/' . $pic)) {
                            $imgUrl = '../uploads/' . rawurlencode($pic);
                        } else {
                            $imgUrl = '../menu/' . rawurlencode($pic); // fallback path guess
                        }
                    }
                    $ranked[] = [
                        'rank' => $rank++,
                        'menu_id' => (int)$r['menu_id'],
                        'name' => (string)$r['menu_name'],
                        'qty' => (float)$r['qty'],
                        'image' => $imgUrl,
                    ];
                }
                echo json_encode(['ok'=>true,'items'=>$ranked,'scope'=>'all-time']);
                exit;
            }
            if ($action === 'get_monthly_revenue') {
                // Produce last 12 months (including current) revenue buckets from payments (orders + catering only, Paid or Partial)
                // We'll aggregate by YYYY-MM and return an ordered list with month short labels.
                $sql = "SELECT DATE_FORMAT(pay_date,'%Y-%m') ym, DATE_FORMAT(pay_date,'%b') mon, COALESCE(SUM(pay_amount),0) amt
                        FROM payments
                        WHERE (pay_status IN ('Paid','Partial'))
                          AND (order_id IS NOT NULL OR cp_id IS NOT NULL)
                          AND pay_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
                        GROUP BY ym, mon
                        ORDER BY ym ASC";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                // Build a complete 12-month sequence to fill zeros where missing
                $map = [];
                foreach ($rows as $r) { $map[$r['ym']] = ['month'=>$r['mon'],'amount'=>(float)$r['amt']]; }
                $out = [];
                $now = new DateTime('first day of this month');
                for ($i=11; $i>=0; $i--) {
                    $dt = (clone $now)->modify("-{$i} months");
                    $ym = $dt->format('Y-m');
                    if (isset($map[$ym])) { $out[] = ['ym'=>$ym,'month'=>$map[$ym]['month'],'amount'=>$map[$ym]['amount']]; }
                    else { $out[] = ['ym'=>$ym,'month'=>$dt->format('M'),'amount'=>0.0]; }
                }
                echo json_encode(['ok'=>true,'months'=>$out,'current'=>end($out)['ym']]);
                exit;
            }
            if ($action === 'get_notifications') {
                // Returns new orders, catering packages, and event bookings since last seen IDs.
                $lastOrderId = isset($_GET['last_order_id']) ? (int)$_GET['last_order_id'] : 0;
                $lastCpId = isset($_GET['last_cp_id']) ? (int)$_GET['last_cp_id'] : 0;
                $lastBkId = isset($_GET['last_booking_id']) ? (int)$_GET['last_booking_id'] : 0;
                $limit = 10; // per type cap
                $resp = [
                    'ok' => true,
                    'orders' => [],
                    'catering' => [],
                    'bookings' => [],
                    'latest_ids' => [
                        'order' => $lastOrderId,
                        'cp' => $lastCpId,
                        'booking' => $lastBkId
                    ]
                ];
                // Orders
                try {
                    $stmt = $pdo->prepare("SELECT order_id, order_status, created_at FROM orders WHERE order_id > ? ORDER BY order_id DESC LIMIT $limit");
                    $stmt->execute([$lastOrderId]);
                    $rowsO = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($rowsO) {
                        foreach ($rowsO as $r) {
                            $resp['orders'][] = [
                                'type' => 'order',
                                'id' => (int)$r['order_id'],
                                'label' => 'New Order #' . (int)$r['order_id'],
                                'status' => (string)($r['order_status'] ?? ''),
                                'time' => $r['created_at'] ?? null
                            ];
                        }
                        $maxId = max(array_column($rowsO, 'order_id'));
                        if ($maxId > $resp['latest_ids']['order']) $resp['latest_ids']['order'] = (int)$maxId;
                    }
                } catch (Throwable $e) {}
                // Catering Packages
                try {
                    $stmt = $pdo->prepare("SELECT cp_id, cp_name, cp_date, created_at FROM cateringpackages WHERE cp_id > ? ORDER BY cp_id DESC LIMIT $limit");
                    $stmt->execute([$lastCpId]);
                    $rowsC = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($rowsC) {
                        foreach ($rowsC as $r) {
                            $resp['catering'][] = [
                                'type' => 'catering',
                                'id' => (int)$r['cp_id'],
                                'label' => 'New Catering #' . (int)$r['cp_id'],
                                'status' => $r['cp_date'] ? ('Event: ' . $r['cp_date']) : null,
                                'time' => $r['created_at'] ?? null
                            ];
                        }
                        $maxId = max(array_column($rowsC, 'cp_id'));
                        if ($maxId > $resp['latest_ids']['cp']) $resp['latest_ids']['cp'] = (int)$maxId;
                    }
                } catch (Throwable $e) {}
                // Event Bookings
                try {
                    $stmt = $pdo->prepare("SELECT eb_id, eb_name, eb_date, eb_status, created_at FROM eventbookings WHERE eb_id > ? ORDER BY eb_id DESC LIMIT $limit");
                    $stmt->execute([$lastBkId]);
                    $rowsB = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($rowsB) {
                        foreach ($rowsB as $r) {
                            $resp['bookings'][] = [
                                'type' => 'booking',
                                'id' => (int)$r['eb_id'],
                                'label' => 'New Booking #' . (int)$r['eb_id'],
                                'status' => $r['eb_status'] ?? null,
                                'time' => $r['eb_date'] ?? ($r['created_at'] ?? null)
                            ];
                        }
                        $maxId = max(array_column($rowsB, 'eb_id'));
                        if ($maxId > $resp['latest_ids']['booking']) $resp['latest_ids']['booking'] = (int)$maxId;
                    }
                } catch (Throwable $e) {}
                echo json_encode($resp); exit;
            }
            echo json_encode(['ok'=>false,'error'=>'Unknown action']); // Handle unknown actions
        } catch (Throwable $e) {
            echo json_encode(['ok'=>false,'error'=>'Failed: '.$e->getMessage()]);
        }
        exit;
    }
}

// Bookings early actions (AJAX endpoints)
if ($sectionEarly === 'bookings') {
    $action = $_GET['action'] ?? '';
    $bid = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
    if (!$action) { $action = $_POST['action'] ?? ''; }
    if ($bid <= 0 && isset($_POST['booking_id'])) { $bid = (int)$_POST['booking_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    $pdo = $db->opencon();

    // Download Contract (PDF) for a booking
    if ($action === 'contract' && $bid > 0) {
        try {
            // Fetch booking with joins for human-readable fields
            $stmt = $pdo->prepare("SELECT eb.*, u.user_fn, u.user_ln, u.user_email, u.user_phone, et.name AS eb_type, pk.pax AS eb_package_pax, pk.name AS package_name
                                   FROM eventbookings eb
                                   LEFT JOIN users u ON u.user_id = eb.user_id
                                   LEFT JOIN event_types et ON et.event_type_id = eb.event_type_id
                                   LEFT JOIN packages pk ON pk.package_id = eb.package_id
                                   WHERE eb.eb_id = ? LIMIT 1");
            $stmt->execute([$bid]);
            $b = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$b) { http_response_code(404); echo 'Not found'; exit; }

            // Latest payment for this booking (bookings use payments rows with order_id and cp_id NULL)
            $pstmt = $pdo->prepare("SELECT pay_date, pay_amount, pay_method, pay_status
                                    FROM payments WHERE user_id=? AND order_id IS NULL AND cp_id IS NULL
                                    ORDER BY pay_date DESC, pay_id DESC LIMIT 1");
            $pstmt->execute([(int)($b['user_id'] ?? 0)]);
            $pay = $pstmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Build contract content lines
            $clientName = trim((string)($b['eb_name'] ?: (($b['user_fn'] ?? '') . ' ' . ($b['user_ln'] ?? ''))));
            $email = trim((string)($b['eb_email'] ?? ($b['user_email'] ?? '')));
            $phone = trim((string)($b['eb_contact'] ?? ($b['user_phone'] ?? '')));
            $etype = trim((string)($b['eb_type'] ?? ''));
            $pkgName = trim((string)($b['package_name'] ?? ''));
            $paxVal = trim((string)($b['eb_package_pax'] ?? ''));
            $pkgLabel = $pkgName !== '' ? ($pkgName . ($paxVal !== '' ? (' - ' . $paxVal) : '')) : ($paxVal !== '' ? $paxVal : '—');
            $addons = trim((string)($b['eb_addon_pax'] ?? ''));
            $venue = trim((string)($b['eb_venue'] ?? ''));
            $eventDate = isset($b['eb_date']) && $b['eb_date'] !== '' ? date('F d, Y g:i A', strtotime((string)$b['eb_date'])) : '';
            $notes = trim((string)($b['eb_notes'] ?? ''));
            $status = trim((string)($b['eb_status'] ?? 'Pending'));
            $created = isset($b['created_at']) && $b['created_at'] !== '' ? date('F d, Y', strtotime((string)$b['created_at'])) : date('F d, Y');

            $paySummary = $pay ? (sprintf('Payment: %s • %s • ₱%s',
                                $pay['pay_status'] ?? '—',
                                $pay['pay_method'] ?? '—',
                                number_format((float)($pay['pay_amount'] ?? 0), 2))) : 'Payment: —';

            // Minimal PDF generator (no external library)
            $escape = function(string $s): string { return strtr($s, ["\\"=>'\\\\', '('=>'\\(', ')'=>'\\)']); };
            $addLine = function(array &$buf, string $text, int $x, int &$y, int $leading=16) use ($escape) {
                $safe = $escape($text);
                $buf[] = sprintf("1 0 0 1 %d %d Tm (%s) Tj\n", $x, $y, $safe);
                $y -= $leading;
            };

            $content = [];
            $y = 800; // start near top (A4/Letter height ~ 842/792)
            // Header
            $content[] = "BT\n/F1 20 Tf\n";
            $addLine($content, 'Catering Service Agreement', 50, $y, 26);
            $content[] = "/F1 12 Tf\n";
            $addLine($content, 'Sandok ni Binggay Catering Services', 50, $y);
            $addLine($content, 'Date: ' . date('F d, Y'), 50, $y);
            $y -= 6;
            $addLine($content, 'This agreement outlines the details for the catering service as requested by the client.', 50, $y);
            $y -= 10;

            // Client & Event Details
            $content[] = "/F1 14 Tf\n"; $addLine($content, 'Client & Event Details', 50, $y, 20);
            $content[] = "/F1 12 Tf\n";
            $addLine($content, 'Booking No.: #' . (int)$bid, 50, $y);
            $addLine($content, 'Client Name: ' . ($clientName !== '' ? $clientName : '—'), 50, $y);
            $addLine($content, 'Contact: ' . ($phone !== '' ? $phone : '—'), 50, $y);
            $addLine($content, 'Email: ' . ($email !== '' ? $email : '—'), 50, $y);
            $addLine($content, 'Event Type: ' . ($etype !== '' ? $etype : '—'), 50, $y);
            $addLine($content, 'Package: ' . ($pkgLabel !== '' ? $pkgLabel : '—'), 50, $y);
            $addLine($content, 'Add-ons: ' . ($addons !== '' ? $addons : '—'), 50, $y);
            $addLine($content, 'Venue: ' . ($venue !== '' ? $venue : '—'), 50, $y);
            $addLine($content, 'Event Date: ' . ($eventDate !== '' ? $eventDate : '—'), 50, $y);
            $addLine($content, 'Status: ' . ($status !== '' ? $status : '—'), 50, $y);
            $addLine($content, $paySummary, 50, $y);
            if ($notes !== '') { $addLine($content, 'Notes: ' . $notes, 50, $y); }

            // Terms (brief)
            $y -= 8;
            $content[] = "/F1 14 Tf\n"; $addLine($content, 'Terms & Conditions (Summary)', 50, $y, 20);
            $content[] = "/F1 12 Tf\n";
            $addLine($content, '• Client agrees to provide accurate event details and access to the venue on the event date.', 50, $y);
            $addLine($content, '• Any changes to the order must be communicated at least 3 days prior to the event.', 50, $y);
            $addLine($content, '• Payments follow the status listed above unless otherwise agreed in writing.', 50, $y);
            $addLine($content, '• Sandok ni Binggay will deliver food and services per package details.', 50, $y);

            // Signatures
            $y -= 10;
            $addLine($content, 'Signed on: ' . $created, 50, $y);
            $y -= 24;
            $addLine($content, 'Client Signature: ___________________________', 50, $y);
            $addLine($content, 'Authorized Signature (Sandok ni Binggay): ___________________________', 50, $y);
            $content[] = "ET\n";

            // Assemble PDF objects
            $stream = implode('', $content);
            $len = strlen($stream);
            $objs = [];
            $offsets = [];
            $pdf = "%PDF-1.4\n";
            $writeObj = function(int $num, string $body) use (&$pdf, &$offsets) {
                $offsets[$num] = strlen($pdf);
                $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
            };
            // 1: Catalog
            $writeObj(1, "<< /Type /Catalog /Pages 2 0 R >>");
            // 2: Pages
            $writeObj(2, "<< /Type /Pages /Count 1 /Kids [3 0 R] >>");
            // 3: Page (A4 595x842pt; we use 612x792 Letter to match y)
            $writeObj(3, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>");
            // 4: Contents stream
            $writeObj(4, "<< /Length $len >>\nstream\n$stream\nendstream");
            // 5: Font (Helvetica)
            $writeObj(5, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");

            // xref
            $xrefPos = strlen($pdf);
            $pdf .= "xref\n0 6\n"; // objects 0..5
            $pdf .= sprintf("%010d %05d f \n", 0, 65535);
            for ($i=1; $i<=5; $i++) {
                $pdf .= sprintf("%010d %05d n \n", (int)$offsets[$i], 0);
            }
            // trailer
            $pdf .= "trailer\n";
            $pdf .= "<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

            $fname = 'Contract_Booking_' . (int)$bid . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $fname . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdf;
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'Failed to generate PDF';
        }
        exit;
    }

    if ($action === 'get_booking' && $bid > 0) {
        header('Content-Type: application/json');
        try {
            // Join event_types and packages to expose readable aliases expected by frontend (eb_type, eb_package_pax)
            $stmt = $pdo->prepare("SELECT eb.*, et.name AS eb_type, pk.pax AS eb_package_pax, pk.name AS package_name
                                   FROM eventbookings eb
                                   LEFT JOIN event_types et ON et.event_type_id = eb.event_type_id
                                   LEFT JOIN packages pk ON pk.package_id = eb.package_id
                                   WHERE eb.eb_id=?");
            $stmt->execute([$bid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
            // Latest booking payment for this user (payments rows reserved for bookings have both order_id and cp_id NULL)
            $pay = $pdo->prepare("SELECT pay_id, pay_date, pay_amount, pay_method, pay_status FROM payments WHERE user_id=? AND order_id IS NULL AND cp_id IS NULL ORDER BY pay_date DESC, pay_id DESC LIMIT 1");
            $pay->execute([(int)$row['user_id']]);
            $p = $pay->fetch(PDO::FETCH_ASSOC) ?: null;
            echo json_encode(['success'=>true,'data'=>$row,'payment'=>$p]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Fetch failed']);
        }
        exit;
    }

    if ($action === 'update' && $bid > 0) {
        header('Content-Type: application/json');
        try {
            // Read incoming fields (backward compatible names)
            $name = trim((string)($_POST['eb_name'] ?? ''));
            $contact = trim((string)($_POST['eb_contact'] ?? ''));
            $venue = trim((string)($_POST['eb_venue'] ?? ''));
            $dateStr = (string)($_POST['eb_date'] ?? '');
            $order = trim((string)($_POST['eb_order'] ?? ''));
            $status = trim((string)($_POST['eb_status'] ?? 'Pending'));
            $addon = isset($_POST['eb_addon_pax']) && $_POST['eb_addon_pax']!=='' ? (int)$_POST['eb_addon_pax'] : null;
            $notes = isset($_POST['eb_notes']) ? trim((string)$_POST['eb_notes']) : null;

            // Fetch current row to preserve IDs when not provided
            $cur = $pdo->prepare("SELECT event_type_id, package_id FROM eventbookings WHERE eb_id=?");
            $cur->execute([$bid]);
            $currRow = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
            $eventTypeId = isset($_POST['event_type_id']) ? (int)$_POST['event_type_id'] : (int)($currRow['event_type_id'] ?? 0);
            $packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : (int)($currRow['package_id'] ?? 0);

            // Map legacy eb_type (name) -> event_type_id if provided
            $legacyType = trim((string)($_POST['eb_type'] ?? ''));
            if ($legacyType !== '') {
                $gt = $pdo->prepare("SELECT event_type_id FROM event_types WHERE name=? LIMIT 1");
                $gt->execute([$legacyType]);
                $found = (int)($gt->fetchColumn() ?: 0);
                if ($found > 0) { $eventTypeId = $found; }
            }
            // Map legacy eb_package_pax (enum value) -> package_id (prefer match allowed for selected event type)
            if (isset($_POST['eb_package_pax']) && $_POST['eb_package_pax'] !== '') {
                $paxVal = (string)$_POST['eb_package_pax'];
                $foundPkg = 0;
                if ($eventTypeId > 0) {
                    $gp = $pdo->prepare("SELECT p.package_id FROM event_type_packages ep JOIN packages p ON p.package_id=ep.package_id WHERE ep.event_type_id=? AND p.pax=? ORDER BY p.package_id DESC LIMIT 1");
                    $gp->execute([$eventTypeId, $paxVal]);
                    $foundPkg = (int)($gp->fetchColumn() ?: 0);
                }
                if ($foundPkg === 0) {
                    $gp = $pdo->prepare("SELECT package_id FROM packages WHERE pax=? ORDER BY package_id DESC LIMIT 1");
                    $gp->execute([$paxVal]);
                    $foundPkg = (int)($gp->fetchColumn() ?: 0);
                }
                if ($foundPkg > 0) { $packageId = $foundPkg; }
            }

            // Minimal validation: require essential fields
            if ($name === '' || $contact === '' || $venue === '' || $dateStr === '' || $order === '') {
                echo json_encode(['success'=>false,'message'=>'All required fields must be filled']); exit;
            }
            // Coerce datetime-local to timestamp (assume local timezone)
            $dt = date('Y-m-d H:i:s', strtotime($dateStr));

            // Build update with existing columns only
            $sql = "UPDATE eventbookings
                    SET eb_name=?, eb_contact=?, eb_venue=?, eb_date=?, eb_order=?, eb_status=?, eb_addon_pax=?, eb_notes=?, event_type_id=?, package_id=?
                    WHERE eb_id=?";
            $pdo->prepare($sql)->execute([$name, $contact, $venue, $dt, $order, $status, $addon, $notes, $eventTypeId>0?$eventTypeId:null, $packageId>0?$packageId:null, $bid]);
            // Email user when certain statuses
            try {
                require_once __DIR__ . '/../classes/Mailer.php';
                $mailer = new Mailer();
                // Fetch user's email and names
                $uq = $pdo->prepare("SELECT eb.*, u.user_email, u.user_fn, u.user_ln, et.name AS et_name FROM eventbookings eb LEFT JOIN users u ON u.user_id=eb.user_id LEFT JOIN event_types et ON et.event_type_id=eb.event_type_id WHERE eb.eb_id=?");
                $uq->execute([$bid]);
                $brow = $uq->fetch(PDO::FETCH_ASSOC) ?: [];
                // Prefer account email; fallback to booking email on record
                $toEmail = (string)($brow['user_email'] ?? '');
                if ($toEmail === '' && isset($brow['eb_email']) && $brow['eb_email'] !== '') {
                    $toEmail = (string)$brow['eb_email'];
                }
                // Build recipient name from account profile; fallback to booking name
                $toName = trim(((string)($brow['user_fn'] ?? '')) . ' ' . ((string)($brow['user_ln'] ?? '')));
                if ($toName === '' && isset($brow['eb_name']) && $brow['eb_name'] !== '') { $toName = (string)$brow['eb_name']; }
                $data = [
                    'fullName'   => $name ?: $toName,
                    'event_type' => (string)($brow['et_name'] ?? 'Event Booking'),
                    'package'    => $order,
                    'event_date' => $dt,
                    'venue'      => $venue,
                    'contact'    => $contact,
                    'addons'     => is_null($addon)?'':(string)$addon,
                    'notes'      => $notes,
                ];
                if ($toEmail && in_array(strtolower($status), ['confirmed','completed','paid'])) {
                    $label = ucfirst(strtolower($status));
                    [$subject, $html] = $mailer->renderBookingEmail($data, $label);
                    $mailer->send($toEmail, $toName ?: $name, $subject, $html);
                }
            } catch (Throwable $e) { /* ignore email errors */ }
            echo json_encode(['success'=>true]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Update failed']);
        }
        exit;
    }

    if ($action === 'mark_paid' && $bid > 0) {
        header('Content-Type: application/json');
        try {
            $method = (string)($_POST['pay_method'] ?? 'Cash');
            $amount = (float)($_POST['pay_amount'] ?? 0);
            // Get booking to read user_id
            $b = $pdo->prepare("SELECT eb.*, u.user_email, u.user_fn, u.user_ln, et.name AS et_name FROM eventbookings eb LEFT JOIN users u ON u.user_id=eb.user_id LEFT JOIN event_types et ON et.event_type_id=eb.event_type_id WHERE eb.eb_id=?");
            $b->execute([$bid]);
            $brow = $b->fetch(PDO::FETCH_ASSOC) ?: [];
            $userId = (int)($brow['user_id'] ?? 0);
            if ($userId <= 0) { echo json_encode(['success'=>false,'message'=>'User not found for booking']); exit; }
            // Insert a payment row reserved for bookings (order_id and cp_id are NULL)
            $ins = $pdo->prepare("INSERT INTO payments (order_id, cp_id, user_id, pay_date, pay_amount, pay_method, pay_status) VALUES (NULL, NULL, ?, CURDATE(), ?, ?, 'Paid')");
            $ins->execute([$userId, $amount, $method]);
            // Update booking status to Paid
            $pdo->prepare("UPDATE eventbookings SET eb_status='Paid' WHERE eb_id=?")->execute([$bid]);
            // Email notification
            try {
                require_once __DIR__ . '/../classes/Mailer.php';
                $mailer = new Mailer();
                // Prefer account email; fallback to booking email on record
                $toEmail = (string)($brow['user_email'] ?? '');
                if ($toEmail === '' && isset($brow['eb_email']) && $brow['eb_email'] !== '') {
                    $toEmail = (string)$brow['eb_email'];
                }
                // Build recipient name from account profile; fallback to booking name
                $toName = trim(((string)($brow['user_fn'] ?? '')) . ' ' . ((string)($brow['user_ln'] ?? '')));
                if ($toName === '' && isset($brow['eb_name']) && $brow['eb_name'] !== '') { $toName = (string)$brow['eb_name']; }
                $data = [
                    'fullName'   => (string)($brow['eb_name'] ?? $toName),
                    'event_type' => (string)($brow['et_name'] ?? 'Event Booking'),
                    'package'    => (string)($brow['eb_order'] ?? ''),
                    'event_date' => (string)($brow['eb_date'] ?? ''),
                    'venue'      => (string)($brow['eb_venue'] ?? ''),
                    'contact'    => (string)($brow['eb_contact'] ?? ''),
                    'addons'     => (string)($brow['eb_addon_pax'] ?? ''),
                    'notes'      => (string)($brow['eb_notes'] ?? ''),
                ];
                if ($toEmail) { [$subject,$html]=$mailer->renderBookingEmail($data,'Paid'); $mailer->send($toEmail,$toName?:$data['fullName'],$subject,$html); }
            } catch (Throwable $e) {}
            echo json_encode(['success'=>true]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Mark paid failed']);
        }
        exit;
    }

    if ($action === 'mark_downpayment' && $bid > 0) {
        header('Content-Type: application/json');
        try {
            $method = (string)($_POST['pay_method'] ?? 'Cash');
            $amount = (float)($_POST['pay_amount'] ?? 0);
            // Load booking to obtain user_id and for email
            $b = $pdo->prepare("SELECT eb.*, u.user_email, u.user_fn, u.user_ln, et.name AS et_name FROM eventbookings eb LEFT JOIN users u ON u.user_id=eb.user_id LEFT JOIN event_types et ON et.event_type_id=eb.event_type_id WHERE eb.eb_id=?");
            $b->execute([$bid]);
            $brow = $b->fetch(PDO::FETCH_ASSOC) ?: [];
            $userId = (int)($brow['user_id'] ?? 0);
            if ($userId <= 0) { echo json_encode(['success'=>false,'message'=>'User not found for booking']); exit; }
            // Insert a Partial payment row reserved for bookings (order_id and cp_id are NULL)
            $ins = $pdo->prepare("INSERT INTO payments (order_id, cp_id, user_id, pay_date, pay_amount, pay_method, pay_status) VALUES (NULL, NULL, ?, CURDATE(), ?, ?, 'Partial')");
            $ins->execute([$userId, $amount, $method]);
            // Update booking status to Downpayment
            $pdo->prepare("UPDATE eventbookings SET eb_status='Downpayment' WHERE eb_id=?")->execute([$bid]);
            // Email notification
            try {
                require_once __DIR__ . '/../classes/Mailer.php';
                $mailer = new Mailer();
                // Prefer account email; fallback to booking email on record
                $toEmail = (string)($brow['user_email'] ?? '');
                if ($toEmail === '' && isset($brow['eb_email']) && $brow['eb_email'] !== '') {
                    $toEmail = (string)$brow['eb_email'];
                }
                // Build recipient name from account profile; fallback to booking name
                $toName = trim(((string)($brow['user_fn'] ?? '')) . ' ' . ((string)($brow['user_ln'] ?? '')));
                if ($toName === '' && isset($brow['eb_name']) && $brow['eb_name'] !== '') { $toName = (string)$brow['eb_name']; }
                $data = [
                    'fullName'   => (string)($brow['eb_name'] ?? $toName),
                    'event_type' => (string)($brow['et_name'] ?? 'Event Booking'),
                    'package'    => (string)($brow['eb_order'] ?? ''),
                    'event_date' => (string)($brow['eb_date'] ?? ''),
                    'venue'      => (string)($brow['eb_venue'] ?? ''),
                    'contact'    => (string)($brow['eb_contact'] ?? ''),
                    'addons'     => (string)($brow['eb_addon_pax'] ?? ''),
                    'notes'      => (string)($brow['eb_notes'] ?? ''),
                ];
                if ($toEmail) { [$subject,$html]=$mailer->renderBookingEmail($data,'Downpayment'); $mailer->send($toEmail,$toName?:$data['fullName'],$subject,$html); }
            } catch (Throwable $e) {}
            echo json_encode(['success'=>true]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Mark downpayment failed']);
        }
        exit;
    }

    if ($action === 'delete' && $bid > 0) {
        try { $pdo->prepare("DELETE FROM eventbookings WHERE eb_id=?")->execute([$bid]); } catch (Throwable $e) {}
        if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
        header('Location: ?section=bookings'); exit;
    }
}

// Catering Packages early actions (AJAX endpoints)
if ($sectionEarly === 'catering') {
    $action = $_GET['action'] ?? '';
    $cpid = isset($_GET['cp_id']) ? (int)$_GET['cp_id'] : 0;
    if (!$action) { $action = $_POST['action'] ?? ''; }
    if ($cpid <= 0 && isset($_POST['cp_id'])) { $cpid = (int)$_POST['cp_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    $pdo = $db->opencon();

    if ($action === 'get_cp' && $cpid > 0) {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("SELECT * FROM cateringpackages WHERE cp_id=?");
            $stmt->execute([$cpid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
            // Latest payment for this catering package
            $pay = $pdo->prepare("SELECT pay_id, pay_date, pay_amount, pay_method, pay_status FROM payments WHERE cp_id=? ORDER BY pay_date DESC, pay_id DESC LIMIT 1");
            $pay->execute([$cpid]);
            $p = $pay->fetch(PDO::FETCH_ASSOC) ?: null;
            echo json_encode(['success'=>true,'data'=>$row,'payment'=>$p]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'Fetch failed']); }
        exit;
    }

    if ($action === 'update' && $cpid > 0) {
        header('Content-Type: application/json');
        try {
            $name = trim((string)($_POST['cp_name'] ?? ''));
            $phone = trim((string)($_POST['cp_phone'] ?? ''));
            $place = trim((string)($_POST['cp_place'] ?? ''));
            $date = (string)($_POST['cp_date'] ?? '');
            $price = (float)($_POST['cp_price'] ?? 0);
            $addon = isset($_POST['cp_addon_pax']) && $_POST['cp_addon_pax'] !== '' ? (int)$_POST['cp_addon_pax'] : null;
            $notes = isset($_POST['cp_notes']) ? trim((string)$_POST['cp_notes']) : null;
            if ($name === '' || $phone === '' || $place === '' || $date === '' || $price < 0) {
                echo json_encode(['success'=>false,'message'=>'All required fields must be filled']); exit;
            }
            // phone 11 digits
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) !== 11) { echo json_encode(['success'=>false,'message'=>'Phone must be exactly 11 digits']); exit; }
            $phone = $digits;
            $sql = "UPDATE cateringpackages SET cp_name=?, cp_phone=?, cp_place=?, cp_date=?, cp_price=?, cp_addon_pax=?, cp_notes=? WHERE cp_id=?";
            $pdo->prepare($sql)->execute([$name,$phone,$place,$date,$price,$addon,$notes,$cpid]);

            // Update payment only when a specific pay_id is provided (avoid inserting on edit)
            $cpPayId = isset($_POST['cp_pay_id']) ? (int)$_POST['cp_pay_id'] : 0;
            $cpPayAmount = isset($_POST['cp_pay_amount']) && $_POST['cp_pay_amount'] !== '' ? (float)$_POST['cp_pay_amount'] : null;
            $cpPayMethod = isset($_POST['cp_pay_method']) ? trim((string)$_POST['cp_pay_method']) : '';
            $cpPayStatus = isset($_POST['cp_pay_status']) ? trim((string)$_POST['cp_pay_status']) : '';
            if ($cpPayId > 0 && ($cpPayAmount !== null || $cpPayMethod !== '' || $cpPayStatus !== '')) {
                $cur = $pdo->prepare("SELECT pay_amount, pay_method, pay_status FROM payments WHERE pay_id=? AND cp_id=? LIMIT 1");
                $cur->execute([$cpPayId, $cpid]);
                $prow = $cur->fetch(PDO::FETCH_ASSOC);
                if ($prow) {
                    $newAmount = ($cpPayAmount !== null) ? $cpPayAmount : (isset($prow['pay_amount']) ? (float)$prow['pay_amount'] : 0);
                    $newMethod = ($cpPayMethod !== '') ? $cpPayMethod : ($prow['pay_method'] ?? 'Cash');
                    $newStatus = ($cpPayStatus !== '') ? $cpPayStatus : ($prow['pay_status'] ?? 'Pending');
                    $upd = $pdo->prepare("UPDATE payments SET pay_amount=?, pay_method=?, pay_status=? WHERE pay_id=? AND cp_id=?");
                    $upd->execute([$newAmount, $newMethod, $newStatus, $cpPayId, $cpid]);
                }
            }
            echo json_encode(['success'=>true]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'Update failed']); }
        exit;
    }

    if ($action === 'mark_paid' && $cpid > 0) {
        header('Content-Type: application/json');
        try {
            $method = (string)($_POST['pay_method'] ?? 'Cash');
            // Get cp to read user_id and price for status computation
            $c = $pdo->prepare("SELECT c.*, u.user_email, u.user_fn, u.user_ln FROM cateringpackages c LEFT JOIN users u ON u.user_id=c.user_id WHERE c.cp_id=?");
            $c->execute([$cpid]);
            $cpRow = $c->fetch(PDO::FETCH_ASSOC);
            $userId = (int)($cpRow['user_id'] ?? 0);
            $cpPrice = (float)($cpRow['cp_price'] ?? 0);
            if ($userId <= 0) { echo json_encode(['success'=>false,'message'=>'User not found for package']); exit; }
            // Enforce amount as exactly the package price on the server side
            $amount = $cpPrice;
            // For admin 'Paid' button, we consider this fully paid regardless (sets status to 'Paid')
            $status = 'Paid';
            // Upsert payment: if a payment exists for this cp_id, update it; else insert a new one
            $sel = $pdo->prepare("SELECT pay_id FROM payments WHERE cp_id=? ORDER BY pay_date DESC, pay_id DESC LIMIT 1");
            $sel->execute([$cpid]);
            $existingPayId = (int)($sel->fetchColumn() ?: 0);
            if ($existingPayId > 0) {
                $upd = $pdo->prepare("UPDATE payments SET pay_amount=?, pay_method=?, pay_status=?, pay_date=CURDATE(), user_id=? WHERE pay_id=? AND cp_id=?");
                $upd->execute([$amount, $method, $status, $userId, $existingPayId, $cpid]);
            } else {
                $ins = $pdo->prepare("INSERT INTO payments (order_id, cp_id, user_id, pay_date, pay_amount, pay_method, pay_status) VALUES (NULL, ?, ?, CURDATE(), ?, ?, ?)");
                $ins->execute([$cpid, $userId, $amount, $method, $status]);
            }
            // Email Paid receipt
            try {
                require_once __DIR__ . '/../classes/Mailer.php';
                $mailer = new Mailer();
                $toEmail = (string)($cpRow['user_email'] ?? '');
                $toName = trim(((string)($cpRow['user_fn'] ?? '')) . ' ' . ((string)($cpRow['user_ln'] ?? '')));
                $data = [
                    'full_name'   => (string)($cpRow['cp_name'] ?? $toName),
                    'event_date'  => (string)($cpRow['cp_date'] ?? ''),
                    'place'       => (string)($cpRow['cp_place'] ?? ''),
                    'phone'       => (string)($cpRow['cp_phone'] ?? ''),
                    'total_price' => $cpPrice,
                    'deposit'     => $cpPrice, // fully paid
                    'addons'      => (string)($cpRow['cp_addon_pax'] ?? ''),
                    'notes'       => (string)($cpRow['cp_notes'] ?? ''),
                ];
                if ($toEmail) { [$subject,$html] = $mailer->renderCateringEmail($data, 'Paid'); $mailer->send($toEmail, $toName ?: $data['full_name'], $subject, $html); }
            } catch (Throwable $e) {}

            echo json_encode(['success'=>true]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'Mark paid failed']); }
        exit;
    }

    if ($action === 'delete' && $cpid > 0) {
        try {
            // Remove related payments first
            $pdo->prepare("DELETE FROM payments WHERE cp_id=?")->execute([$cpid]);
            $pdo->prepare("DELETE FROM cateringpackages WHERE cp_id=?")->execute([$cpid]);
        } catch (Throwable $e) {}
        if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
        header('Location: ?section=catering'); exit;
    }
}

// Packages early actions (AJAX endpoints)
if ($sectionEarly === 'packages') {
    $action = $_GET['action'] ?? '';
    $pkgId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
    if (!$action) { $action = $_POST['action'] ?? ''; }
    if ($pkgId <= 0 && isset($_POST['package_id'])) { $pkgId = (int)$_POST['package_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    $pdo = $db->opencon();

    if ($action === 'get_package' && $pkgId > 0) {
        header('Content-Type: application/json');
        try {
            $p = $pdo->prepare("SELECT * FROM packages WHERE package_id=?");
            $p->execute([$pkgId]);
            $pkg = $p->fetch(PDO::FETCH_ASSOC);
            if (!$pkg) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
            $it = $pdo->prepare("SELECT item_id, item_label, qty, unit, is_optional, sort_order FROM package_items WHERE package_id=? ORDER BY sort_order ASC, item_id ASC");
            $it->execute([$pkgId]);
            $items = $it->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$pkg,'items'=>$items]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'Fetch failed']); }
        exit;
    }

    if ($action === 'create') {
        header('Content-Type: application/json');
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            $pax = trim((string)($_POST['pax'] ?? ''));
            $price = isset($_POST['base_price']) && $_POST['base_price'] !== '' ? (float)$_POST['base_price'] : null;
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
            $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
            if ($name === '' || $pax === '') { echo json_encode(['success'=>false,'message'=>'Name and Pax are required']); exit; }
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO packages (name, pax, base_price, is_active, notes) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$name, $pax, $price, $isActive, $notes]);
            $newId = (int)$pdo->lastInsertId();
            // Items arrays
            $labels = isset($_POST['item_label']) ? (array)$_POST['item_label'] : [];
            $qtys = isset($_POST['qty']) ? (array)$_POST['qty'] : [];
            $units = isset($_POST['unit']) ? (array)$_POST['unit'] : [];
            $opts = isset($_POST['is_optional']) ? (array)$_POST['is_optional'] : [];
            $orders = isset($_POST['sort_order']) ? (array)$_POST['sort_order'] : [];
            if ($labels) {
                $insI = $pdo->prepare("INSERT INTO package_items (package_id, item_label, qty, unit, is_optional, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $n = count($labels);
                for ($i=0; $i<$n; $i++) {
                    $lbl = trim((string)($labels[$i] ?? '')); if ($lbl==='') continue;
                    $qv = ($qtys[$i] ?? '') !== '' ? (int)$qtys[$i] : null;
                    $uv = trim((string)($units[$i] ?? 'other'));
                    $ov = isset($opts[$i]) && ($opts[$i] === '1' || $opts[$i] === 1 || $opts[$i] === 'true') ? 1 : 0;
                    $sv = ($orders[$i] ?? '') !== '' ? (int)$orders[$i] : $i;
                    try { $insI->execute([$newId, $lbl, $qv, $uv, $ov, $sv]); } catch (Throwable $e) {}
                }
            }
            // Handle optional photo upload; saved as uploads/packages/package_{id}.<ext> and persisted to packages.package_image
            if (!empty($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                $orig = $_FILES['photo']['name'];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif','avif'];
                if (in_array($ext, $allowed)) {
                    $destDir = realpath(__DIR__ . '/../uploads/packages');
                    if ($destDir === false) { $destDir = __DIR__ . '/../uploads/packages'; }
                    @mkdir($destDir, 0775, true);
                    $fileName = 'package_' . $newId . '.' . $ext;
                    $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
                    // Remove any previous image for this id (all extensions)
                    @array_map(function($f){ @unlink($f); }, glob(rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'package_' . $newId . '.*'));
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                        $rel = 'uploads/packages/' . $fileName; // store relative path
                        try { $pdo->prepare("UPDATE packages SET package_image=? WHERE package_id=?")->execute([$rel, $newId]); } catch (Throwable $e) { /* ignore */ }
                    }
                }
            }
            $pdo->commit();
            // Build image URL (with cache-busting) if exists
            $imgUrl = null;
            try {
                $dir = __DIR__ . '/../uploads/packages';
                foreach (['jpg','jpeg','png','webp','gif','avif'] as $ext) {
                    $p = $dir . '/package_' . $newId . '.' . $ext;
                    if (file_exists($p)) { $imgUrl = '../uploads/packages/package_' . $newId . '.' . $ext; $v=@filemtime($p); if($v){ $imgUrl .= '?v=' . $v; } break; }
                }
            } catch (Throwable $e) {}
            echo json_encode(['success'=>true,'package_id'=>$newId,'image_url'=>$imgUrl]);
        } catch (Throwable $e) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
            echo json_encode(['success'=>false,'message'=>'Create failed']);
        }
        exit;
    }

    if ($action === 'toggle_active' && $pkgId > 0) {
        header('Content-Type: application/json');
        try {
            $curr = $pdo->prepare("SELECT is_active FROM packages WHERE package_id=?");
            $curr->execute([$pkgId]);
            $v = (int)($curr->fetchColumn() ?? 0);
            $nv = $v === 1 ? 0 : 1;
            $upd = $pdo->prepare("UPDATE packages SET is_active=? WHERE package_id=?");
            $upd->execute([$nv, $pkgId]);
            echo json_encode(['success'=>true,'is_active'=>$nv]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'Toggle failed']); }
        exit;
    }

    if ($action === 'update' && $pkgId > 0) {
        header('Content-Type: application/json');
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            $pax = trim((string)($_POST['pax'] ?? ''));
            $price = isset($_POST['base_price']) && $_POST['base_price'] !== '' ? (float)$_POST['base_price'] : null;
            // Treat missing checkbox as 0 (inactive)
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
            $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
            if ($name === '' || $pax === '') { echo json_encode(['success'=>false,'message'=>'Name and Pax are required']); exit; }
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE packages SET name=?, pax=?, base_price=?, is_active=?, notes=? WHERE package_id=?")
                ->execute([$name, $pax, $price, $isActive, $notes, $pkgId]);
            // Replace items
            $pdo->prepare("DELETE FROM package_items WHERE package_id=?")->execute([$pkgId]);
            $labels = isset($_POST['item_label']) ? (array)$_POST['item_label'] : [];
            $qtys = isset($_POST['qty']) ? (array)$_POST['qty'] : [];
            $units = isset($_POST['unit']) ? (array)$_POST['unit'] : [];
            $opts = isset($_POST['is_optional']) ? (array)$_POST['is_optional'] : [];
            $orders = isset($_POST['sort_order']) ? (array)$_POST['sort_order'] : [];
            if ($labels) {
                $insI = $pdo->prepare("INSERT INTO package_items (package_id, item_label, qty, unit, is_optional, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $n = count($labels);
                for ($i=0; $i<$n; $i++) {
                    $lbl = trim((string)($labels[$i] ?? '')); if ($lbl==='') continue;
                    $qv = ($qtys[$i] ?? '') !== '' ? (int)$qtys[$i] : null;
                    $uv = trim((string)($units[$i] ?? 'other'));
                    $ov = isset($opts[$i]) && ($opts[$i] === '1' || $opts[$i] === 1 || $opts[$i] === 'true') ? 1 : 0;
                    $sv = ($orders[$i] ?? '') !== '' ? (int)$orders[$i] : $i;
                    try { $insI->execute([$pkgId, $lbl, $qv, $uv, $ov, $sv]); } catch (Throwable $e) {}
                }
            }
            // Optional photo upload replace and persist to packages.package_image
            if (!empty($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                $orig = $_FILES['photo']['name'];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif','avif'];
                if (in_array($ext, $allowed)) {
                    $destDir = realpath(__DIR__ . '/../uploads/packages');
                    if ($destDir === false) { $destDir = __DIR__ . '/../uploads/packages'; }
                    @mkdir($destDir, 0775, true);
                    $fileName = 'package_' . $pkgId . '.' . $ext;
                    $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
                    // Remove any previous image for this id (all extensions)
                    @array_map(function($f){ @unlink($f); }, glob(rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'package_' . $pkgId . '.*'));
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                        $rel = 'uploads/packages/' . $fileName; // store relative path
                        try { $pdo->prepare("UPDATE packages SET package_image=? WHERE package_id=?")->execute([$rel, $pkgId]); } catch (Throwable $e) { /* ignore */ }
                    }
                }
            }
            $pdo->commit();
            // Build image URL (with cache-busting) if exists
            $imgUrl = null;
            try {
                $dir = __DIR__ . '/../uploads/packages';
                foreach (['jpg','jpeg','png','webp','gif','avif'] as $ext) {
                    $p = $dir . '/package_' . $pkgId . '.' . $ext;
                    if (file_exists($p)) { $imgUrl = '../uploads/packages/package_' . $pkgId . '.' . $ext; $v=@filemtime($p); if($v){ $imgUrl .= '?v=' . $v; } break; }
                }
            } catch (Throwable $e) {}
            echo json_encode(['success'=>true,'package_id'=>$pkgId,'image_url'=>$imgUrl]);
        } catch (Throwable $e) { try { $pdo->rollBack(); } catch (Throwable $e2) {} echo json_encode(['success'=>false,'message'=>'Update failed']); }
        exit;
    }

    if ($action === 'delete' && $pkgId > 0) {
        try { $pdo->prepare("DELETE FROM packages WHERE package_id=?")->execute([$pkgId]); } catch (Throwable $e) {}
        if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
        header('Location: ?section=packages'); exit;
    }
}

// Categories early actions (AJAX endpoints)
if ($sectionEarly === 'categories') {
    $action = $_GET['action'] ?? '';
    $cid = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    if (!$action) { $action = $_POST['action'] ?? ''; }
    if ($cid <= 0 && isset($_POST['category_id'])) { $cid = (int)$_POST['category_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    $pdo = $db->opencon();
    if ($action === 'search_menu') {
        header('Content-Type: application/json');
        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $stmt = $pdo->prepare("SELECT menu_id, menu_name FROM menu WHERE menu_name LIKE ? ORDER BY menu_name ASC LIMIT 20");
            $stmt->execute(['%'.$q.'%']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Search failed']);
        }
        exit;
    }
    if ($action === 'list_menu') {
        header('Content-Type: application/json');
        try {
            $rows = $pdo->query("SELECT menu_id, menu_name FROM menu ORDER BY menu_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'List failed']);
        }
        exit;
    }
    if ($action === 'create') {
        $name = trim((string)($_POST['category_name'] ?? ''));
        $menuIds = isset($_POST['menu_ids']) ? (array)$_POST['menu_ids'] : [];
        try {
            if ($name === '') throw new Exception('Category name is required');
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO category (category_name) VALUES (?)");
            $ins->execute([$name]);
            $newId = (int)$pdo->lastInsertId();
            if ($menuIds) {
                $insMC = $pdo->prepare("INSERT INTO menucategory (category_id, menu_id) VALUES (?, ?)");
                foreach ($menuIds as $mid) {
                    $mid = (int)$mid; if ($mid <= 0) continue;
                    try { $insMC->execute([$newId, $mid]); } catch (Throwable $e) {}
                }
            }
            $pdo->commit();
            if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
            header('Location: ?section=categories'); exit;
        } catch (Throwable $e) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
            if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Create failed']); exit; }
            header('Location: ?section=categories&error=1'); exit;
        }
    }
    if ($action === 'get_category' && $cid > 0) {
        header('Content-Type: application/json');
        try {
            $cat = $pdo->prepare("SELECT category_id, category_name FROM category WHERE category_id=?");
            $cat->execute([$cid]);
            $c = $cat->fetch(PDO::FETCH_ASSOC);
            if (!$c) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
            $m = $pdo->prepare("SELECT m.menu_id, m.menu_name FROM menucategory mc JOIN menu m ON m.menu_id=mc.menu_id WHERE mc.category_id=? ORDER BY m.menu_name");
            $m->execute([$cid]);
            $menus = $m->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>['category'=>$c,'menus'=>$menus]]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Fetch failed']);
        }
        exit;
    }
    if ($action === 'update' && $cid > 0) {
        $name = trim((string)($_POST['category_name'] ?? ''));
        $menuIds = isset($_POST['menu_ids']) ? (array)$_POST['menu_ids'] : [];
        try {
            if ($name === '') throw new Exception('Category name is required');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE category SET category_name=? WHERE category_id=?")->execute([$name, $cid]);
            // Replace menu assignments
            $pdo->prepare("DELETE FROM menucategory WHERE category_id=?")->execute([$cid]);
            if ($menuIds) {
                $insMC = $pdo->prepare("INSERT INTO menucategory (category_id, menu_id) VALUES (?, ?)");
                foreach ($menuIds as $mid) { $mid=(int)$mid; if ($mid>0) { try { $insMC->execute([$cid, $mid]); } catch (Throwable $e) {} } }
            }
            $pdo->commit();
            if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
            header('Location: ?section=categories'); exit;
        } catch (Throwable $e) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
            if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Update failed']); exit; }
            header('Location: ?section=categories&error=1'); exit;
        }
    }
    if ($action === 'delete' && $cid > 0) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM menucategory WHERE category_id=?")->execute([$cid]);
            $pdo->prepare("DELETE FROM category WHERE category_id=?")->execute([$cid]);
            $pdo->commit();
        } catch (Throwable $e) { try { $pdo->rollBack(); } catch (Throwable $e2) {} }
        if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
        header('Location: ?section=categories'); exit;
    }
}

// Employees early actions (AJAX endpoints) — removed from UI/navigation
if ($sectionEarly === 'employees') {
    $action = $_GET['action'] ?? '';
    $eid = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    if (!$action) { $action = $_POST['action'] ?? ''; }
    if ($eid <= 0 && isset($_POST['employee_id'])) { $eid = (int)$_POST['employee_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    $pdo = $db->opencon();

    if ($action === 'get_employee' && $eid > 0) {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("SELECT emp_id, emp_fn, emp_ln, emp_sex, emp_email, emp_phone, emp_role, emp_avail, emp_photo FROM employee WHERE emp_id=?");
            $stmt->execute([$eid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
            echo json_encode(['success'=>true,'data'=>$row]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Fetch failed']);
        }
        exit;
    }

    if ($action === 'create') {
        header('Content-Type: application/json');
        try {
            $fn = trim((string)($_POST['emp_fn'] ?? ''));
            $ln = trim((string)($_POST['emp_ln'] ?? ''));
            $sex = trim((string)($_POST['emp_sex'] ?? ''));
            $email = trim((string)($_POST['emp_email'] ?? ''));
            $phone = trim((string)($_POST['emp_phone'] ?? ''));
            $role = trim((string)($_POST['emp_role'] ?? ''));
            $avail = isset($_POST['emp_avail']) ? (int)($_POST['emp_avail'] ? 1 : 0) : 1;

            if ($fn === '' || $ln === '' || $sex === '' || $email === '' || $phone === '' || $role === '') {
                echo json_encode(['success'=>false,'message'=>'All fields are required']); exit;
            }

            // Phone must be 11 digits numeric
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) !== 11) {
                echo json_encode(['success'=>false,'message'=>'Phone must be exactly 11 digits']); exit;
            }
            $phone = $digits;

            $photoPath = null;
            if (!empty($_FILES['emp_photo']) && is_uploaded_file($_FILES['emp_photo']['tmp_name'])) {
                $orig = $_FILES['emp_photo']['name'];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif','avif'];
                if (!in_array($ext, $allowed)) { echo json_encode(['success'=>false,'message'=>'Unsupported image type']); exit; }
                $newName = uniqid('emp_', true) . '.' . $ext;
                $destDir = realpath(__DIR__ . '/../uploads/profile');
                if ($destDir === false) { $destDir = __DIR__ . '/../uploads/profile'; }
                @mkdir($destDir, 0775, true);
                $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
                if (!move_uploaded_file($_FILES['emp_photo']['tmp_name'], $destPath)) {
                    echo json_encode(['success'=>false,'message'=>'Failed to upload photo']); exit;
                }
                // Store relative path (same pattern as users table)
                $photoPath = '../uploads/profile/' . $newName;
            }

            $stmt = $pdo->prepare("INSERT INTO employee (emp_fn, emp_ln, emp_sex, emp_email, emp_phone, emp_role, emp_avail, emp_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fn, $ln, $sex, $email, $phone, $role, $avail, $photoPath]);
            echo json_encode(['success'=>true]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Create failed']);
        }
        exit;
    }

    if ($action === 'update' && $eid > 0) {
        header('Content-Type: application/json');
        try {
            $fn = trim((string)($_POST['emp_fn'] ?? ''));
            $ln = trim((string)($_POST['emp_ln'] ?? ''));
            $sex = trim((string)($_POST['emp_sex'] ?? ''));
            $email = trim((string)($_POST['emp_email'] ?? ''));
            $phone = trim((string)($_POST['emp_phone'] ?? ''));
            $role = trim((string)($_POST['emp_role'] ?? ''));
            $avail = isset($_POST['emp_avail']) ? (int)($_POST['emp_avail'] ? 1 : 0) : 1;

            if ($fn === '' || $ln === '' || $sex === '' || $email === '' || $phone === '' || $role === '') {
                echo json_encode(['success'=>false,'message'=>'All fields are required']); exit;
            }

            // Phone must be 11 digits numeric
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) !== 11) {
                echo json_encode(['success'=>false,'message'=>'Phone must be exactly 11 digits']); exit;
            }
            $phone = $digits;

            $photoSql = '';
            $params = [$fn, $ln, $sex, $email, $phone, $role, $avail, $eid];
            if (!empty($_FILES['emp_photo']) && is_uploaded_file($_FILES['emp_photo']['tmp_name'])) {
                $orig = $_FILES['emp_photo']['name'];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif','avif'];
                if (!in_array($ext, $allowed)) { echo json_encode(['success'=>false,'message'=>'Unsupported image type']); exit; }
                $newName = uniqid('emp_', true) . '.' . $ext;
                $destDir = realpath(__DIR__ . '/../uploads/profile');
                if ($destDir === false) { $destDir = __DIR__ . '/../uploads/profile'; }
                @mkdir($destDir, 0775, true);
                $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
                if (!move_uploaded_file($_FILES['emp_photo']['tmp_name'], $destPath)) {
                    echo json_encode(['success'=>false,'message'=>'Failed to upload photo']); exit;
                }
                $photoPath = '../uploads/profile/' . $newName;
                $photoSql = ', emp_photo = ?';
                // Insert photo param before $eid
                array_splice($params, -1, 0, [$photoPath]);
            }

            $sql = "UPDATE employee SET emp_fn=?, emp_ln=?, emp_sex=?, emp_email=?, emp_phone=?, emp_role=?, emp_avail=?" . $photoSql . " WHERE emp_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success'=>true]);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'message'=>'Update failed']);
        }
        exit;
    }

    if ($action === 'toggle' && $eid > 0) {
        // Toggle availability
        try {
            $curr = $pdo->prepare("SELECT emp_avail FROM employee WHERE emp_id=?");
            $curr->execute([$eid]);
            $v = (int)$curr->fetchColumn();
            $nv = $v === 1 ? 0 : 1;
            $pdo->prepare("UPDATE employee SET emp_avail=? WHERE emp_id=?")->execute([$nv, $eid]);
        } catch (Throwable $e) {}
        if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
        header('Location: ?section=employees'); exit;
    }

    if ($action === 'delete' && $eid > 0) {
        try { $pdo->prepare("DELETE FROM employee WHERE emp_id=?")->execute([$eid]); } catch (Throwable $e) {}
        if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
        header('Location: ?section=employees'); exit;
    }
}

// Event Types early actions (AJAX endpoints)
if ($sectionEarly === 'eventtypes') {
    $action = $_GET['action'] ?? '';
    if (!$action) { $action = $_POST['action'] ?? ''; }
    $etId = isset($_GET['event_type_id']) ? (int)$_GET['event_type_id'] : 0;
    if ($etId <= 0 && isset($_POST['event_type_id'])) { $etId = (int)$_POST['event_type_id']; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    $pdo = $db->opencon();

    // List event types with package counts
    if ($action === 'list') {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->query("SELECT et.event_type_id, et.name, et.min_package_pax, et.max_package_pax, et.notes, et.created_at, et.updated_at,
                                        COALESCE((SELECT COUNT(*) FROM event_type_packages ep WHERE ep.event_type_id=et.event_type_id),0) AS package_count
                                 FROM event_types et
                                 ORDER BY et.updated_at DESC, et.event_type_id DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'List failed']); }
        exit;
    }

    // List packages for selection
    if ($action === 'list_packages') {
        header('Content-Type: application/json');
        try {
            $rows = $pdo->query("SELECT package_id, name, pax, base_price, is_active FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'List failed']); }
        exit;
    }

    // Get a single event type with its package ids
    if ($action === 'get' && $etId > 0) {
        header('Content-Type: application/json');
        try {
            $g = $pdo->prepare("SELECT * FROM event_types WHERE event_type_id=?");
            $g->execute([$etId]);
            $row = $g->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
            $m = $pdo->prepare("SELECT package_id FROM event_type_packages WHERE event_type_id=? ORDER BY package_id");
            $m->execute([$etId]);
            $pkgIds = array_map('intval', array_column($m->fetchAll(PDO::FETCH_ASSOC) ?: [], 'package_id'));
            echo json_encode(['success'=>true,'data'=>$row,'package_ids'=>$pkgIds]);
        } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>'Fetch failed']); }
        exit;
    }

    // Create
    if ($action === 'create') {
        header('Content-Type: application/json');
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            $minP = trim((string)($_POST['min_package_pax'] ?? ''));
            $maxP = trim((string)($_POST['max_package_pax'] ?? ''));
            $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
            $pkgIds = isset($_POST['package_ids']) ? (array)$_POST['package_ids'] : [];
            $pkgIds = array_values(array_unique(array_map('intval', $pkgIds)));
            if ($name === '') { echo json_encode(['success'=>false,'message'=>'Name is required']); exit; }
            // Validate enum range if both provided
            $toInt = function($v){ return ctype_digit($v) ? (int)$v : null; };
            $mi = $minP!=='' ? $toInt($minP) : null; $ma = $maxP!=='' ? $toInt($maxP) : null;
            if ($mi !== null && $ma !== null && $mi > $ma) { echo json_encode(['success'=>false,'message'=>'Min pax cannot exceed max pax']); exit; }
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO event_types (name, min_package_pax, max_package_pax, notes) VALUES (?, ?, ?, ?)");
            $ins->execute([$name, $minP!=='' ? $minP : null, $maxP!=='' ? $maxP : null, $notes !== '' ? $notes : null]);
            $newId = (int)$pdo->lastInsertId();
            if ($pkgIds) {
                $ip = $pdo->prepare("INSERT INTO event_type_packages (event_type_id, package_id) VALUES (?, ?)");
                foreach ($pkgIds as $pid) { if ($pid > 0) { try { $ip->execute([$newId, $pid]); } catch (Throwable $e) {} } }
            }
            $pdo->commit();
            echo json_encode(['success'=>true,'event_type_id'=>$newId]);
        } catch (Throwable $e) { try { $pdo->rollBack(); } catch (Throwable $e2) {} echo json_encode(['success'=>false,'message'=>'Create failed']); }
        exit;
    }

    // Update
    if ($action === 'update' && $etId > 0) {
        header('Content-Type: application/json');
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            $minP = trim((string)($_POST['min_package_pax'] ?? ''));
            $maxP = trim((string)($_POST['max_package_pax'] ?? ''));
            $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
            $pkgIds = isset($_POST['package_ids']) ? (array)$_POST['package_ids'] : [];
            $pkgIds = array_values(array_unique(array_map('intval', $pkgIds)));
            if ($name === '') { echo json_encode(['success'=>false,'message'=>'Name is required']); exit; }
            $toInt = function($v){ return ctype_digit($v) ? (int)$v : null; };
            $mi = $minP!=='' ? $toInt($minP) : null; $ma = $maxP!=='' ? $toInt($maxP) : null;
            if ($mi !== null && $ma !== null && $mi > $ma) { echo json_encode(['success'=>false,'message'=>'Min pax cannot exceed max pax']); exit; }
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE event_types SET name=?, min_package_pax=?, max_package_pax=?, notes=? WHERE event_type_id=?")
                ->execute([$name, $minP!=='' ? $minP : null, $maxP!=='' ? $maxP : null, $notes !== '' ? $notes : null, $etId]);
            $pdo->prepare("DELETE FROM event_type_packages WHERE event_type_id=?")->execute([$etId]);
            if ($pkgIds) {
                $ip = $pdo->prepare("INSERT INTO event_type_packages (event_type_id, package_id) VALUES (?, ?)");
                foreach ($pkgIds as $pid) { if ($pid > 0) { try { $ip->execute([$etId, $pid]); } catch (Throwable $e) {} } }
            }
            $pdo->commit();
            echo json_encode(['success'=>true]);
        } catch (Throwable $e) { try { $pdo->rollBack(); } catch (Throwable $e2) {} echo json_encode(['success'=>false,'message'=>'Update failed']); }
        exit;
    }

    // Delete
    if ($action === 'delete' && $etId > 0) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM event_type_packages WHERE event_type_id=?")->execute([$etId]);
            $pdo->prepare("DELETE FROM event_types WHERE event_type_id=?")->execute([$etId]);
            $pdo->commit();
        } catch (Throwable $e) { try { $pdo->rollBack(); } catch (Throwable $e2) {} }
        if ($isAjaxAction) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
        header('Location: ?section=eventtypes'); exit;
    }
}

// Site Settings early actions (AJAX endpoints) - menu section rotating images and collections images
if ($sectionEarly === 'settings') {
    $action = $_GET['action'] ?? '';
    if (!$action) { $action = $_POST['action'] ?? ''; }
    $isAjaxAction = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    if ($action && $isAjaxAction) {
        header('Content-Type: application/json');
        try {
            $pdo = $db->opencon();
            // Ensure table exists (lightweight guard) - optional; ignore errors
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
            if ($action === 'get_menu_images') {
                $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'menu_section_images'");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                $images = [];
                if ($val) {
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) { $images = $decoded; }
                }
                echo json_encode(['success'=>true,'images'=>$images]);
                exit;
            }
            if ($action === 'update_menu_images') {
                // Accept either uploaded files or posted URLs array
                $existing = [];
                if (isset($_POST['existing']) && is_string($_POST['existing'])) {
                    $decoded = json_decode($_POST['existing'], true);
                    if (is_array($decoded)) { $existing = $decoded; }
                }
                $uploads = [];
                if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
                    $count = count($_FILES['images']['name']);
                    for ($i=0;$i<$count;$i++) {
                        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmp = $_FILES['images']['tmp_name'][$i];
                            $orig = basename($_FILES['images']['name'][$i]);
                            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png','webp','avif'])) continue;
                            $newName = 'menu_hero_'.time()."_".$i.'.'.$ext;
                            $targetDir = __DIR__.'/../uploads/menu_hero';
                            if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
                            $targetPath = $targetDir.'/'.$newName;
                            if (move_uploaded_file($tmp, $targetPath)) {
                                $rel = 'uploads/menu_hero/'.$newName; // store relative; output logic adds base URL
                                $uploads[] = $rel;
                            }
                        }
                    }
                }
                $final = array_values(array_slice(array_merge($uploads, $existing), 0, 10)); // limit 10 images
                $json = json_encode($final, JSON_UNESCAPED_SLASHES);
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('menu_section_images', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$json]);
                echo json_encode(['success'=>true,'images'=>$final]);
                exit;
            }
            if ($action === 'get_collections_images') {
                $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'collections_images'");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                $images = [];
                if ($val) {
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) { $images = $decoded; }
                }
                echo json_encode(['success'=>true,'images'=>$images]);
                exit;
            }
            if ($action === 'update_collections_images') {
                // Accept either uploaded files or posted URLs array
                $existing = [];
                if (isset($_POST['existing']) && is_string($_POST['existing'])) {
                    $decoded = json_decode($_POST['existing'], true);
                    if (is_array($decoded)) { $existing = $decoded; }
                }
                $uploads = [];
                if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
                    $count = count($_FILES['images']['name']);
                    for ($i=0;$i<$count;$i++) {
                        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmp = $_FILES['images']['tmp_name'][$i];
                            $orig = basename($_FILES['images']['name'][$i]);
                            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png','webp','avif'])) continue;
                            $newName = 'collections_'.time()."_".$i.'.'.$ext;
                            $targetDir = __DIR__.'/../uploads/collections';
                            if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
                            $targetPath = $targetDir.'/'.$newName;
                            if (move_uploaded_file($tmp, $targetPath)) {
                                $rel = 'uploads/collections/'.$newName; // store relative; output logic adds base URL
                                $uploads[] = $rel;
                            }
                        }
                    }
                }
                // We will allow up to 12 collection images (2 full rows in most screens)
                $final = array_values(array_slice(array_merge($uploads, $existing), 0, 24));
                $json = json_encode($final, JSON_UNESCAPED_SLASHES);
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('collections_images', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$json]);
                echo json_encode(['success'=>true,'images'=>$final]);
                exit;
            }
            echo json_encode(['success'=>false,'error'=>'Unknown action']);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sandok ni Binggay - Admin Dashboard</title>
    <?php
        // Load DB and prepare data for Products Management
        require_once __DIR__ . '/../classes/database.php';
        $db = new database();

    // Query params
    $section = $_GET['section'] ?? '';
    $section = is_string($section) ? strtolower($section) : '';
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $category = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : null;
    // PAX filter (optional): '6-8', '10-15', or 'per' (matches any "* pieces")
    $pax = isset($_GET['pax']) && $_GET['pax'] !== '' ? (string)$_GET['pax'] : null;
        $avail = isset($_GET['avail']) && $_GET['avail'] !== '' ? $_GET['avail'] : null; // expects '1' or '0'
        $sort = isset($_GET['sort']) && $_GET['sort'] !== '' ? $_GET['sort'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        // Reset filters on full reload (non-AJAX request)
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if (($section === 'products') && !$isAjax) {
            $q = '';
            $category = null;
            $pax = null;
            $avail = null;
            $sort = null;
            $page = 1;
        }
        $limit = 10;
        $offset = ($page - 1) * $limit;

    // Defaults; only hydrate data for the requested section to speed up initial load
    $categories = [];
    $totalCount = 0; $menus = []; $totalPages = 1;
    $projTotalCount = 0; $projMenus = []; $projTotalPages = 1; $projPage = 1; // ensure defaults to avoid undefined notices

        if ($section === 'products') {
            try { $categories = $db->viewCategories(); } catch (Throwable $e) { $categories = []; }
            try {
                // When PAX filter is active, switch to an in-PHP filtering path so counts/pagination remain correct
                if ($pax !== null && $pax !== '') {
                    $all = [];
                    if (method_exists($db, 'getFilteredMenuOOP')) {
                        // Get all records for current category/availability with desired sort
                        $all = $db->getFilteredMenuOOP($category, $avail, $sort);
                    } elseif (method_exists($db, 'getFilteredMenuPaged')) {
                        // Fallback: fetch a large page and filter client-side (works for typical dataset sizes)
                        $all = $db->getFilteredMenuPaged($category, $avail, $sort, 10000, 0, null);
                    }
                    // Apply search filter (name) if any
                    if ($q !== '') {
                        $needle = mb_strtolower($q);
                        $all = array_values(array_filter($all, function($row) use ($needle) {
                            return stripos($row['menu_name'] ?? '', $needle) !== false;
                        }));
                    }
                    // Apply PAX filter: exact for 6-8 / 10-15, substring 'pieces' for per pieces
                    $paxFilter = mb_strtolower($pax);
                    $all = array_values(array_filter($all, function($row) use ($paxFilter) {
                        $val = mb_strtolower((string)($row['menu_pax'] ?? ''));
                        if ($paxFilter === 'per') {
                            return $val !== '' && strpos($val, 'pieces') !== false; // matches 'N pieces' or 'per pieces'
                        }
                        return $val === $paxFilter; // matches '6-8' or '10-15'
                    }));
                    $totalCount = count($all);
                    $menus = array_slice($all, $offset, $limit);
                } else {
                    if (method_exists($db, 'countFilteredMenu') && method_exists($db, 'getFilteredMenuPaged')) {
                        $totalCount = $db->countFilteredMenu($category, $avail, $q);
                        $menus = $db->getFilteredMenuPaged($category, $avail, $sort, $limit, $offset, $q);
                    } else {
                        $all = $db->getFilteredMenuOOP($category, $avail, $sort);
                        if ($q !== '') {
                            $all = array_values(array_filter($all, function($row) use ($q) {
                                return stripos($row['menu_name'] ?? '', $q) !== false;
                            }));
                        }
                        $totalCount = count($all);
                        $menus = array_slice($all, $offset, $limit);
                    }
                }
            } catch (Throwable $e) {
                $totalCount = 0; $menus = [];
            }
            $totalPages = max(1, (int)ceil($totalCount / $limit));
                }
                // Packages list data (paginate) when viewing Packages section
                $pkgPage = max(1, (int)($_GET['pkg_page'] ?? 1));
                $pkgLimit = 8;
                $pkgOffset = ($pkgPage - 1) * $pkgLimit;
                $pkgRows = []; $pkgTotal = 0; $pkgPages = 1;
                if ($section === 'packages') {
                    try {
                        $pdo = $db->opencon();
                        $pkgTotal = (int)$pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
                        $stmt = $pdo->prepare("SELECT package_id, name, pax, base_price, is_active, notes, updated_at FROM packages ORDER BY updated_at DESC, package_id DESC LIMIT :lim OFFSET :off");
                        $stmt->bindValue(':lim', $pkgLimit, PDO::PARAM_INT);
                        $stmt->bindValue(':off', $pkgOffset, PDO::PARAM_INT);
                        $stmt->execute();
                        $pkgRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        $pkgPages = max(1, (int)ceil($pkgTotal / $pkgLimit));
                    } catch (Throwable $e) { $pkgRows = []; $pkgTotal = 0; $pkgPages = 1; }
                }
                // Helper to build query preserving filters
        function build_query($overrides = []) {
            $params = $_GET;
            $params['section'] = 'products';
            foreach ($overrides as $k => $v) {
                if ($v === null) {
                    unset($params[$k]);
                } else {
                    $params[$k] = $v;
                }
            }
            return http_build_query($params);
        }
        function menu_img_src($menu_pic) {
            $menu_pic = (string)$menu_pic;
            if ($menu_pic === '') return null;
            if (str_starts_with($menu_pic, 'http') || str_contains($menu_pic, '/')) return $menu_pic;
            return '../menu/' . $menu_pic;
        }
        // Package image helper: return relative URL for uploaded package image or logo fallback
        function pkg_img_src($package_id) {
            $id = (int)$package_id; if ($id <= 0) return '../images/logo.png';
            $dir = __DIR__ . '/../uploads/packages';
            $candidates = ['jpg','jpeg','png','webp','gif','avif'];
            foreach ($candidates as $ext) {
                $path = $dir . '/package_' . $id . '.' . $ext;
                if (file_exists($path)) {
                    $url = '../uploads/packages/package_' . $id . '.' . $ext;
                    $ver = @filemtime($path);
                    if ($ver) { $url .= (strpos($url,'?')!==false?'&':'?') . 'v=' . $ver; }
                    return $url;
                }
            }
            return '../images/logo.png';
        }
        // Category chip classes (bg + border) used in Categories table chips
        function category_chip_classes($name, $id) {
            $n = strtolower((string)$name);
            // Keyword-based mapping first (matches modal behavior)
            if (strpos($n, 'beef') !== false) return 'bg-red-50 border-red-300';
            if (strpos($n, 'pork') !== false) return 'bg-rose-50 border-rose-300';
            if (strpos($n, 'chicken') !== false) return 'bg-amber-50 border-amber-300';
            if (strpos($n, 'seafood') !== false || strpos($n, 'fish') !== false || strpos($n, 'shrimp') !== false) return 'bg-sky-50 border-sky-300';
            if (strpos($n, 'vegetable') !== false || strpos($n, 'veggie') !== false || strpos($n, 'vegt') !== false) return 'bg-emerald-50 border-emerald-300';
            if (strpos($n, 'pasta') !== false) return 'bg-yellow-50 border-yellow-300';
            if (strpos($n, 'dessert') !== false || strpos($n, 'sweet') !== false) return 'bg-fuchsia-50 border-fuchsia-300';
            if (strpos($n, 'best') !== false) return 'bg-indigo-50 border-indigo-300';
            // Otherwise select a deterministic color from a palette based on category id
            $palette = [
                'bg-purple-50 border-purple-300',
                'bg-teal-50 border-teal-300',
                'bg-cyan-50 border-cyan-300',
                'bg-lime-50 border-lime-300',
                'bg-blue-50 border-blue-300',
                'bg-orange-50 border-orange-300',
                'bg-pink-50 border-pink-300',
                'bg-stone-50 border-stone-300',
                'bg-emerald-50 border-emerald-300',
                'bg-sky-50 border-sky-300',
                'bg-amber-50 border-amber-300',
                'bg-rose-50 border-rose-300',
                'bg-red-50 border-red-300',
                'bg-indigo-50 border-indigo-300',
                'bg-fuchsia-50 border-fuchsia-300',
                'bg-yellow-50 border-yellow-300'
            ];
            $idx = 0;
            if ($id !== null) {
                $idx = (int)$id;
            } else {
                $idx = (int)(crc32((string)$name) & 0xffff);
            }
            return $palette[$idx % count($palette)];
        }
        function category_chip_style($name, $id) {
            $n = strtolower((string)$name);
            // If matched to a known keyword palette, prefer Tailwind classes (no inline style)
            if (strpos($n, 'beef') !== false || strpos($n, 'pork') !== false || strpos($n, 'chicken') !== false
                || strpos($n, 'seafood') !== false || strpos($n, 'fish') !== false || strpos($n, 'shrimp') !== false
                || strpos($n, 'vegetable') !== false || strpos($n, 'veggie') !== false || strpos($n, 'vegt') !== false
                || strpos($n, 'pasta') !== false || strpos($n, 'dessert') !== false || strpos($n, 'sweet') !== false
                || strpos($n, 'best') !== false) {
                return '';
            }
            // Deterministic “random” color based on category id or name
            $seed = ($id !== null) ? (int)$id : (int)(crc32((string)$name) & 0xffff);
            $h = ($seed * 137) % 360; // use a prime-ish multiplier for dispersion
            $bg = "hsla($h, 85%, 96%, 1)"; // very light background
            $bd = "hsla($h, 55%, 70%, 1)"; // mid border
            return "background-color: $bg; border-color: $bd;";
        }
        // Booking chips: base classes and deterministic color helpers for Type/Order
        function booking_chip_base_classes() {
            return 'inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium';
        }
        function booking_type_chip_style($value) {
            $n = strtolower(trim((string)$value));
            if ($n === '') return '';
            // Deterministic color based on the value (keeps unique look per type)
            $seed = (int)(crc32($n) & 0xffff);
            $h = ($seed * 137) % 360;
            $bg = "hsla($h, 85%, 96%, 1)"; // very light background
            $bd = "hsla($h, 55%, 70%, 1)"; // mid border
            return "background-color: $bg; border-color: $bd;";
        }
        function booking_order_chip_classes($value) {
            $n = strtolower(trim((string)$value));
            if ($n === 'customize' || $n === 'customised' || $n === 'customized') return 'bg-amber-50 border-amber-300';
            if ($n === 'party trays' || $n === 'party_trays' || $n === 'party-trays' || $n === 'partytray' || $n === 'party tray') return 'bg-sky-50 border-sky-300';
            // Fallback neutral
            return 'bg-stone-50 border-stone-300';
        }
        function booking_status_chip_classes($value) {
            $n = strtolower(trim((string)$value));
            if ($n === 'paid') return 'bg-emerald-50 border-emerald-300 text-emerald-800';
            if ($n === 'downpayment' || $n === 'partial') return 'bg-blue-50 border-blue-300 text-blue-800';
            if ($n === 'completed') return 'bg-blue-50 border-blue-300 text-blue-800';
            // Treat Confirmed (new) like previous In Progress for styling; keep legacy values for compatibility
            if ($n === 'confirmed' || $n === 'in progress' || $n === 'processing' || $n === 'ongoing') return 'bg-amber-50 border-amber-300 text-amber-800';
            if ($n === 'canceled' || $n === 'cancelled') return 'bg-rose-50 border-rose-300 text-rose-800';
            // Pending/default
            return 'bg-gray-50 border-gray-300 text-gray-800';
        }
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- App icons: use site logo for favicon/PWA tiles -->
    <link rel="icon" type="image/png" sizes="32x32" href="../images/logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../images/logo.png">
    <link rel="apple-touch-icon" href="../images/logo.png">
    <link rel="shortcut icon" href="../images/logo.png">
    <meta name="theme-color" content="#1B4332">
    <meta name="msapplication-TileImage" content="../images/logo.png">
    <meta name="msapplication-TileColor" content="#1B4332">
    <style>
        :root {
            --font-size: 16px;
            --background: #fefffe;
            --foreground: #1a2e1a;
            --card: #ffffff;
            --card-foreground: #1a2e1a;
            --popover: #ffffff;
            --popover-foreground: #1a2e1a;
            --primary: #1B4332;
            --primary-foreground: #ffffff;
            --secondary: #F4F3F0;
            --secondary-foreground: #1a2e1a;
            --muted: #f8f8f6;
            --muted-foreground: #6b7062;
            --accent: #D4AF37;
            --accent-foreground: #1a2e1a;
            --destructive: #d4183d;
            --destructive-foreground: #ffffff;
            --border: rgba(27, 67, 50, 0.1);
            --input: transparent;
            --input-background: #f8f8f6;
            --switch-background: #d1d5db;
            --font-weight-medium: 500;
            --font-weight-normal: 400;
            --ring: #1B4332;
            --chart-1: #1B4332;
            --chart-2: #D4AF37;
            --chart-3: #2D5A3D;
            --chart-4: #E8C547;
            --chart-5: #95A890;
            --radius: 0.625rem;
            --sidebar: #1B4332;
            --sidebar-foreground: #ffffff;
            --sidebar-primary: #D4AF37;
            --sidebar-primary-foreground: #1a2e1a;
            --sidebar-accent: #2D5A3D;
            --sidebar-accent-foreground: #ffffff;
            --sidebar-border: rgba(255, 255, 255, 0.1);
            --sidebar-ring: #D4AF37;
        }

        body {
            background-color: var(--background);
            color: var(--foreground);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .sidebar {
            background-color: var(--sidebar);
            color: var(--sidebar-foreground);
            border-right: 1px solid var(--sidebar-border);
        }

        .sidebar-collapsed {
            width: 4rem;
        }

        .sidebar-expanded {
            width: 16rem;
        }

        .nav-item {
            transition: all 200ms ease;
        }

        .nav-item:hover {
            background-color: var(--sidebar-accent);
            transform: scale(1.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .nav-item.active {
            background-color: var(--sidebar-primary);
            color: var(--sidebar-primary-foreground);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* Collapsed sidebar: center icons and make active/hover highlight a square behind icon */
        .sidebar-collapsed .nav-item {
            justify-content: center;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
            padding-top: 0.25rem;  /* tighter vertical padding */
            padding-bottom: 0.25rem;
            transform: none; /* disable scale effect for cleaner look */
        }

        /* Reduce the vertical gap between items when collapsed (override space-y-2) */
        .sidebar-collapsed nav > * + * {
            margin-top: 0.25rem !important; /* 4px gap between icons */
        }

        .sidebar-collapsed .nav-item .sidebar-text {
            display: none !important;
        }

        /* Base square size for icons in collapsed state (keeps layout from jumping) */
        .sidebar-collapsed .nav-item i {
            width: 2.25rem; /* 36px */
            height: 2.25rem;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0; /* prevent offset */
            transition: background-color 150ms ease, color 150ms ease;
        }

        /* Remove row-wide active background in collapsed state */
        .sidebar-collapsed .nav-item.active {
            background-color: transparent;
            box-shadow: none;
        }

        /* Highlight the icon itself when active in collapsed state */
        .sidebar-collapsed .nav-item.active i {
            background-color: var(--sidebar-primary);
            color: var(--sidebar-primary-foreground);
        }

        /* Optional: subtle hover state focuses the icon only */
        .sidebar-collapsed .nav-item:hover {
            background-color: transparent;
            box-shadow: none;
        }

        .sidebar-collapsed .nav-item:hover i {
            background-color: var(--sidebar-accent);
            color: var(--sidebar-accent-foreground);
        }

        /* Keyboard focus mirrors the active look for clarity */
        .sidebar-collapsed .nav-item:focus-visible i {
            background-color: var(--sidebar-primary);
            color: var(--sidebar-primary-foreground);
            outline: none;
        }

        .card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transition: box-shadow 200ms ease;
        }

        .text-primary { color: var(--primary); }
        .text-muted-foreground { color: var(--muted-foreground); }
        .bg-primary { background-color: var(--primary); }
        .bg-accent { background-color: var(--accent); }
        .border-primary { border-color: var(--primary); }
        .border-accent { border-color: var(--accent); }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }

        .hidden { display: none; }
        .block { display: block; }
        /* Smooth fade during results refresh */
        #products-results { transition: opacity 150ms ease; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1B4332',
                        'primary-foreground': '#ffffff',
                        'accent': '#D4AF37',
                        'accent-foreground': '#1a2e1a',
                        'muted': '#f8f8f6',
                        'muted-foreground': '#6b7062',
                        'sidebar': '#1B4332',
                        'sidebar-foreground': '#ffffff',
                        'sidebar-primary': '#D4AF37',
                        'sidebar-accent': '#2D5A3D',
                        'chart-3': '#2D5A3D',
                        'chart-4': '#E8C547'
                    }
                }
            }
        }
    </script>
</head>
<body class="h-screen overflow-hidden">
    <div class="flex h-full">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar sidebar-expanded flex flex-col transition-all duration-300">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-sidebar-border">
                <div id="sidebar-header" class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-sidebar-primary rounded-lg flex items-center justify-center">
                        <i class="fas fa-utensils text-sidebar-primary-foreground text-xl"></i>
                    </div>
                    <div class="sidebar-text">
                        <div class="font-semibold">Sandok ni Binggay</div>
                        <div class="text-xs opacity-80">Admin Panel</div>
                    </div>
                </div>
                <!-- Sidebar is locked: toggle hidden -->
                <button id="sidebar-toggle" class="hidden">
                    <i id="sidebar-icon" class="fas fa-bars text-sm"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 p-4 space-y-2">
                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === '' || $section === 'dashboard') ? 'active' : ''; ?>" href="?section=dashboard">
                    <i class="fas fa-chart-bar flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Dashboard</div>
                        <div class="text-xs opacity-75">Report Summary</div>
                    </div>
                    <div class="sidebar-text ml-auto w-2 h-2 bg-sidebar-primary-foreground rounded-full"></div>
                </a>

                <a id="nav-products" class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'products') ? 'active' : ''; ?>" href="?section=products">
                    <i class="fas fa-box flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Products</div>
                    </div>
                </a>

                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'orders') ? 'active' : ''; ?>" href="?section=orders">
                    <i class="fas fa-shopping-cart flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Orders</div>
                    </div>
                </a>

                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'bookings') ? 'active' : ''; ?>" href="?section=bookings">
                    <i class="fas fa-calendar flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Bookings</div>
                    </div>
                </a>

                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'catering') ? 'active' : ''; ?>" href="?section=catering">
                    <i class="fas fa-utensils flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Catering Packages</div>
                    </div>
                </a>

                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'categories') ? 'active' : ''; ?>" href="?section=categories">
                    <i class="fas fa-tags flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Food Category</div>
                    </div>
                </a>

                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'eventtypes') ? 'active' : ''; ?>" href="?section=eventtypes">
                    <i class="fas fa-clipboard-list flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Event Types</div>
                    </div>
                </a>

                <!-- New: Packages section -->
                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'packages') ? 'active' : ''; ?>" href="?section=packages">
                    <i class="fas fa-boxes-stacked flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Packages</div>
                    </div>
                </a>

                <a class="nav-item w-full flex items-center gap-3 px-3 py-3 rounded-lg <?php echo ($section === 'settings') ? 'active' : ''; ?>" href="?section=settings">
                    <i class="fas fa-cog flex-shrink-0 w-5 h-5"></i>
                    <div class="sidebar-text text-left">
                        <div class="font-medium text-sm">Settings</div>
                    </div>
                </a>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-sidebar-border">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-sidebar-primary rounded-full flex items-center justify-center text-xs font-semibold text-sidebar-primary-foreground">
                        A
                    </div>
                    <div class="sidebar-text">
                        <div class="font-medium">Admin User</div>
                        <div class="text-xs opacity-80">Administrator</div>
                    </div>
                </div>
            </div>
        </div>

    

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header Bar -->
            <header class="bg-white border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Left section -->
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-3">
                            <img src="../images/logo.png" 
                                 alt="Sandok ni Binggay Logo" 
                                 class="w-10 h-10 rounded-full object-cover border-2 border-primary">
                            <div>
                                <h2 id="page-title" class="text-primary font-semibold"><?php
                                    $titleMap = [
                                        'dashboard' => 'Dashboard',
                                        'products' => 'Products Management',
                                        'orders' => 'Orders Management',
                                        'bookings' => 'Bookings Management',
                                        'catering' => 'Catering Packages',
                                        'categories' => 'Food Categories',
                                        'eventtypes' => 'Event Types',
                                        'packages' => 'Packages',
                                        'settings' => 'Settings'
                                    ];
                                    echo $titleMap[$section] ?? 'Dashboard';
                                ?></h2>
                                <p class="text-sm text-muted-foreground">Sandok ni Binggay Admin</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right section -->
                    <div class="flex items-center gap-4">
                        <!-- Notifications -->
                        <div id="notifications" class="relative">
                            <button id="notification-bell" class="relative p-2 hover:bg-gray-100 rounded-lg transition-colors" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-bell text-gray-600"></i>
                                <span id="notification-badge" class="hidden absolute -top-1 -right-1 min-h-[20px] min-w-[20px] px-1 bg-red-500 text-white text-[10px] font-medium rounded-full flex items-center justify-center"></span>
                            </button>
                            <!-- Dropdown Panel -->
                            <div id="notification-panel" class="hidden absolute right-0 mt-2 w-80 bg-white shadow-lg border border-gray-200 rounded-lg z-50">
                                <div class="flex items-center justify-between px-4 py-2 border-b">
                                    <h3 class="text-sm font-semibold text-gray-700">Notifications</h3>
                                    <button id="notif-mark-read" class="text-xs text-primary hover:underline" type="button">Mark all read</button>
                                </div>
                                <div id="notification-list" class="max-h-80 overflow-y-auto divide-y text-sm"></div>
                                <div id="notification-empty" class="p-4 text-center text-xs text-gray-500 hidden">No notifications yet</div>
                            </div>
                        </div>

                        <!-- Admin User Dropdown -->
                        <?php
                            $adminName = trim((string)($_SESSION['user_name'] ?? ($_SESSION['user_fn'] ?? '')) . ' ' . (string)($_SESSION['user_ln'] ?? ''));
                            $adminName = trim($adminName !== '' ? $adminName : (string)($_SESSION['user_username'] ?? 'Admin'));
                            $adminEmail = trim((string)($_SESSION['user_email'] ?? ''));
                            $adminPhoto = isset($_SESSION['user_photo']) ? (string)$_SESSION['user_photo'] : '';
                            $adminInitial = strtoupper(mb_substr($adminName !== '' ? $adminName : 'A', 0, 1, 'UTF-8'));
                        ?>
                        <div id="admin-user-menu" class="relative">
                            <button id="admin-user-button" class="flex items-center gap-2 hover:bg-gray-100 px-2 py-1.5 rounded-lg transition" aria-haspopup="true" aria-expanded="false">
                                <?php if ($adminPhoto): ?>
                                    <img src="<?= htmlspecialchars($adminPhoto) ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white font-medium">
                                        <?= htmlspecialchars($adminInitial) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="hidden sm:block text-left">
                                    <div class="text-sm font-medium leading-4"><?= htmlspecialchars($adminName) ?></div>
                                   
                                </div>
                                <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
                            </button>
                            <div id="admin-user-dropdown" class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50">
                                <div class="px-3 py-2 border-b">
                                    <div class="text-sm font-medium truncate"><?= htmlspecialchars($adminName) ?></div>
                                    
                                </div>
                                <a href="../user/logout.php" class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50 text-sm text-rose-700">
                                    <i class="fas fa-sign-out-alt w-4"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto">
                <!-- Dashboard Content -->
                <div id="dashboard-content" class="section-content <?php echo ($section && $section !== 'dashboard') ? 'hidden ' : ''; ?>p-6 space-y-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-medium text-primary mb-2">Dashboard Overview</h1>
                            <p class="text-muted-foreground">Welcome back! Here's what's happening with your catering business today.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 border border-yellow-200 rounded-lg text-sm">
                                Last updated: Today 2:30 PM
                            </span>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="card p-6 border-l-4 border-l-primary hover:shadow-lg transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-medium">Total Revenue</h3>
                                <i class="fas fa-dollar-sign text-primary"></i>
                            </div>
                            <?php
                                $totalRevenuePaid = 0.0;
                                try {
                                    $pdo = $db->opencon();
                                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(pay_amount), 0)
                                                           FROM payments
                                                           WHERE pay_status = 'Paid'
                                                             AND pay_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                                                             AND pay_date <  DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')");
                                    $stmt->execute();
                                    $totalRevenuePaid = (float)$stmt->fetchColumn();
                                } catch (Throwable $e) {
                                    $totalRevenuePaid = 0.0;
                                }
                            ?>
                            <div id="total-revenue-amount" class="text-2xl font-bold text-primary">₱<?= number_format($totalRevenuePaid, 0) ?></div>
                            <div class="flex items-center text-sm text-muted-foreground">
                                <i class="fas fa-bolt text-green-700 mr-1"></i>
                                <span class="text-green-700">real-time</span>
                                <span class="ml-1">orders + catering</span>
                            </div>
                        </div>

                        <button id="card-total-orders" type="button" class="card text-left p-6 border-l-4 border-l-accent hover:shadow-lg transition-shadow w-full">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-medium">Total Orders</h3>
                                <i class="fas fa-shopping-cart text-accent"></i>
                            </div>
                            <div id="total-orders-count" class="text-2xl font-bold text-primary">—</div>
                            <div class="flex items-center text-sm text-muted-foreground">
                                <i class="fas fa-arrow-up text-green-600 mr-1"></i>
                                <span class="text-green-600">real-time</span>
                                <span class="ml-1">updated automatically</span>
                            </div>
                        </button>

                        <div class="card p-6 border-l-4 border-l-green-700 hover:shadow-lg transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-medium">Active Bookings</h3>
                                <i class="fas fa-calendar text-green-700"></i>
                            </div>
                            <?php
                                // Query for active bookings: treat bookings that are not Completed or Canceled as active
                                $activeBookingsCount = 0;
                                try {
                                    $pdo = $db->opencon();
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM eventbookings WHERE eb_status NOT IN ('Completed','Canceled')");
                                    $stmt->execute();
                                    $activeBookingsCount = (int)$stmt->fetchColumn();
                                } catch (Throwable $e) {
                                    // On error, keep the count at 0
                                    $activeBookingsCount = 0;
                                }
                            ?>
                            <div class="text-2xl font-bold text-primary"><?= (int)$activeBookingsCount ?></div>
                            <div class="flex items-center text-sm text-muted-foreground">
                                <i class="fas fa-arrow-up text-green-600 mr-1"></i>
                                <span class="text-green-600">+15.1%</span>
                                <span class="ml-1">this week</span>
                            </div>
                        </div>

                        
                    </div>

                    <!-- Charts Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Revenue Chart -->
                        <div class="card p-6 hover:shadow-lg transition-shadow">
                            <div class="mb-4">
                                <h3 class="text-lg font-medium text-primary">Monthly Revenue & Orders</h3>
                                <p class="text-sm text-muted-foreground">Revenue and order trends over the last 6 months</p>
                            </div>
                            <div class="h-96">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>

                        <!-- Best Sellers (All-Time) -->
                        <div class="card p-6 hover:shadow-lg transition-shadow" id="best-sellers-inline-card">
                            <div class="mb-4 flex items-center justify-between gap-2 flex-wrap">
                                <div>
                                    <h3 class="text-lg font-medium text-primary">Best Sellers</h3>
                                    <p class="text-sm text-muted-foreground">Top 10 items (all-time)</p>
                                </div>
                            </div>
                            <div class="h-96 overflow-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="sticky top-0 bg-white shadow">
                                        <tr class="text-left text-primary border-b">
                                            <th class="px-3 py-2 w-12">#</th>
                                            <th class="px-3 py-2">Item</th>
                                            <th class="px-3 py-2 text-right">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody id="best-sellers-inline-rows">
                                        <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Loading…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Removed Recent Orders and Upcoming Bookings section -->
                </div>

                <!-- Other Section Contents (Hidden by default) -->
                <div id="products-content" class="section-content <?php echo ($section === 'products') ? '' : 'hidden '; ?>p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-2xl font-medium text-primary">Products Management</h2>
                            <p class="text-muted-foreground">Manage your menu items, pricing, and availability</p>
                        </div>
                        <button type="button" class="open-add-menu inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-accent text-accent-foreground hover:opacity-90 transition">
                            <i class="fas fa-plus"></i>
                            Add Menu
                        </button>
                    </div>

                    <!-- Filters and search -->
                    <form id="products-filter" method="get" class="card p-4 mb-4">
                        <input type="hidden" name="section" value="products" />
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7 gap-2 items-end">
                            <div>
                                <label class="text-sm text-muted-foreground">Search</label>
                                <input id="filter-q" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by name" class="w-full mt-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                            </div>
                            <div>
                                <label class="text-sm text-muted-foreground">Category</label>
                                <select id="filter-category" name="category" class="w-full mt-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="">All</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo (int)$cat['category_id']; ?>" <?php echo ($category === (int)$cat['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-muted-foreground">PAX</label>
                                <select id="filter-pax" name="pax" class="w-full mt-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="" <?php echo ($pax === null || $pax === '') ? 'selected' : ''; ?>>All</option>
                                    <option value="6-8" <?php echo ($pax === '6-8') ? 'selected' : ''; ?>>6-8 pax</option>
                                    <option value="10-15" <?php echo ($pax === '10-15') ? 'selected' : ''; ?>>10-15 pax</option>
                                    <option value="per" <?php echo ($pax === 'per') ? 'selected' : ''; ?>>per pieces</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-muted-foreground">Availability</label>
                                <select id="filter-avail" name="avail" class="w-full mt-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="" <?php echo ($avail === null || $avail === '') ? 'selected' : ''; ?>>All</option>
                                    <option value="1" <?php echo ($avail === '1' || $avail === 1) ? 'selected' : ''; ?>>Available</option>
                                    <option value="0" <?php echo ($avail === '0' || $avail === 0) ? 'selected' : ''; ?>>Unavailable</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-muted-foreground">Sort by</label>
                                <select id="filter-sort" name="sort" class="w-full mt-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                    <?php
                                        $sortOptions = [
                                            '' => 'Default',
                                            'alpha_asc' => 'Name A → Z',
                                            'alpha_desc' => 'Name Z → A',
                                            'price_asc' => 'Price Low → High',
                                            'price_desc' => 'Price High → Low',
                                        ];
                                        foreach ($sortOptions as $val => $label) {
                                            $sel = ($sort === ($val === '' ? null : $val)) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($val) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="flex md:justify-end">
                                <button type="button" id="products-clear" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm">Clear</button>
                            </div>
                        </div>
                        <!-- Apply/Reset buttons removed in favor of real-time filtering -->
                    </form>

                    <!-- Results -->
                    <div id="products-results" class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="text-left p-3">Item</th>
                                        <th class="text-left p-3">PAX</th>
                                        <th class="text-left p-3">Price</th>
                                        <th class="text-left p-3">Availability</th>
                                        <th class="text-left p-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($menus)): ?>
                                        <tr>
                                            <td colspan="5" class="p-6 text-center text-muted-foreground">No menu items found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($menus as $m): ?>
                                            <?php
                                                $name = htmlspecialchars($m['menu_name'] ?? '');
                                                $paxv = htmlspecialchars((string)($m['menu_pax'] ?? ''));
                                                $price = isset($m['menu_price']) ? '₱' . number_format((float)$m['menu_price'], 2) : '';
                                                $isAvail = ((string)($m['menu_avail'] ?? '1') === '1');
                                                $availBadge = $isAvail
                                                    ? '<span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">Available</span>'
                                                    : '<span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800">Unavailable</span>';
                                                $img = menu_img_src($m['menu_pic'] ?? '');
                                                $mid = (int)($m['menu_id'] ?? 0);
                                            ?>
                                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                                <td class="p-3">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-12 h-12 bg-gray-100 rounded object-cover overflow-hidden flex items-center justify-center">
                                                            <?php if ($img): ?>
                                                                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $name; ?>" class="w-full h-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=Food';">
                                                            <?php else: ?>
                                                                <span class="text-xs text-gray-400">No image</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-primary"><?php echo $name; ?></div>
                                                            <?php if (!empty($m['menu_desc'])): ?>
                                                                <div class="text-xs text-muted-foreground truncate max-w-md"><?php echo htmlspecialchars($m['menu_desc']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="p-3"><?php echo $paxv; ?></td>
                                                <td class="p-3"><?php echo $price; ?></td>
                                                <td class="p-3"><?php echo $availBadge; ?></td>
                                                <td class="p-3">
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" class="p-2 rounded border border-gray-200 hover:bg-gray-50 js-edit" data-menu-id="<?php echo $mid; ?>" title="Edit" aria-label="Edit">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                        <button type="button" class="p-2 rounded border border-gray-200 hover:bg-gray-50 js-action" data-action="toggle" data-menu-id="<?php echo $mid; ?>" title="Toggle availability" aria-label="Toggle availability">
                                                            <i class="fas <?php echo $isAvail ? 'fa-toggle-on text-green-600' : 'fa-toggle-off text-gray-500'; ?>"></i>
                                                        </button>
                                                        <button type="button" class="p-2 rounded border border-red-200 text-red-700 hover:bg-red-50 js-action" data-action="delete" data-menu-id="<?php echo $mid; ?>" title="Delete" aria-label="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <div class="p-3 flex items-center justify-between border-t border-gray-100">
                            <div class="text-sm text-muted-foreground">
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?> · <?php echo $totalCount; ?> items
                            </div>
                            <div class="flex items-center gap-1">
                                <?php $prev = max(1, $page-1); $next = min($totalPages, $page+1); ?>
                                <a class="px-2 py-1 rounded border border-gray-300 text-sm <?php echo $page <= 1 ? 'opacity-50 pointer-events-none' : 'hover:bg-gray-50'; ?>" href="?<?php echo build_query(['page'=>$prev]); ?>">Prev</a>
                                <?php
                                    $start = max(1, $page-2);
                                    $end = min($totalPages, $page+2);
                                    for ($i=$start; $i<=$end; $i++):
                                ?>
                                    <a class="px-2 py-1 rounded text-sm <?php echo $i === $page ? 'bg-primary text-white' : 'border border-gray-300 hover:bg-gray-50'; ?>" href="?<?php echo build_query(['page'=>$i]); ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <a class="px-2 py-1 rounded border border-gray-300 text-sm <?php echo $page >= $totalPages ? 'opacity-50 pointer-events-none' : 'hover:bg-gray-50'; ?>" href="?<?php echo build_query(['page'=>$next]); ?>">Next</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projects Section -->
                <div id="projects-content" class="section-content <?php echo ($section === 'projects') ? '' : 'hidden '; ?>p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-2xl font-medium text-primary">Project Management</h2>
                            <p class="text-muted-foreground">View all menu records from the database</p>
                        </div>
                        <button type="button" class="open-add-menu inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-accent text-accent-foreground hover:opacity-90 transition">
                            <i class="fas fa-plus"></i>
                            Add Menu
                        </button>
                    </div>

                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="text-left p-3">ID</th>
                                        <th class="text-left p-3">Image</th>
                                        <th class="text-left p-3">Name</th>
                                        <th class="text-left p-3">Description</th>
                                        <th class="text-left p-3">PAX</th>
                                        <th class="text-left p-3">Price</th>
                                        <th class="text-left p-3">Available</th>
                                        <th class="text-left p-3">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($projMenus)): ?>
                                        <tr>
                                            <td colspan="8" class="p-6 text-center text-muted-foreground">No menu records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($projMenus as $m): ?>
                                            <?php
                                                $mid = (int)($m['menu_id'] ?? 0);
                                                $name = htmlspecialchars($m['menu_name'] ?? '');
                                                $desc = htmlspecialchars($m['menu_desc'] ?? '');
                                                $paxv = htmlspecialchars((string)($m['menu_pax'] ?? ''));
                                                $price = isset($m['menu_price']) ? '₱' . number_format((float)$m['menu_price'], 2) : '';
                                                $availBadge = ((string)($m['menu_avail'] ?? '1') === '1')
                                                    ? '<span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">Yes</span>'
                                                    : '<span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800">No</span>';
                                                $created = htmlspecialchars($m['created_at'] ?? '');
                                                $img = menu_img_src($m['menu_pic'] ?? '');
                                            ?>
                                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                                <td class="p-3 align-top">#<?php echo $mid; ?></td>
                                                <td class="p-3 align-top">
                                                    <div class="w-12 h-12 bg-gray-100 rounded overflow-hidden flex items-center justify-center">
                                                        <?php if ($img): ?>
                                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $name; ?>" class="w-full h-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=Food';">
                                                        <?php else: ?>
                                                            <span class="text-xs text-gray-400">No image</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="p-3 align-top font-medium text-primary"><?php echo $name; ?></td>
                                                <td class="p-3 align-top">
                                                    <div class="max-w-xs text-muted-foreground truncate"><?php echo $desc; ?></div>
                                                </td>
                                                <td class="p-3 align-top"><?php echo $paxv; ?></td>
                                                <td class="p-3 align-top"><?php echo $price; ?></td>
                                                <td class="p-3 align-top"><?php echo $availBadge; ?></td>
                                                <td class="p-3 align-top"><?php echo $created; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 flex items-center justify-between border-t border-gray-100">
                            <div class="text-sm text-muted-foreground">Page <?php echo $projPage; ?> of <?php echo $projTotalPages; ?> · <?php echo $projTotalCount; ?> items</div>
                            <div class="flex items-center gap-1">
                                <?php $pPrev = max(1, $projPage-1); $pNext = min($projTotalPages, $projPage+1); ?>
                                <a class="px-2 py-1 rounded border border-gray-300 text-sm <?php echo $projPage <= 1 ? 'opacity-50 pointer-events-none' : 'hover:bg-gray-50'; ?>" href="?<?php echo build_query(['section'=>'projects','proj_page'=>$pPrev]); ?>">Prev</a>
                                <?php
                                    $pStart = max(1, $projPage-2);
                                    $pEnd = min($projTotalPages, $projPage+2);
                                    for ($i=$pStart; $i<=$pEnd; $i++):
                                ?>
                                    <a class="px-2 py-1 rounded text-sm <?php echo $i === $projPage ? 'bg-primary text-white' : 'border border-gray-300 hover:bg-gray-50'; ?>" href="?<?php echo build_query(['section'=>'projects','proj_page'=>$i]); ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <a class="px-2 py-1 rounded border border-gray-300 text-sm <?php echo $projPage >= $projTotalPages ? 'opacity-50 pointer-events-none' : 'hover:bg-gray-50'; ?>" href="?<?php echo build_query(['section'=>'projects','proj_page'=>$pNext]); ?>">Next</a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Orders data for Orders Management section
                $orders = [];
                $ordersCount = 0;
                if ($section === 'orders') {
                    try {
                        $pdo = $db->opencon();
                        $opage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                        $olimit = 10;
                        $ooffset = ($opage - 1) * $olimit;
                        $osearch = trim($_GET['q'] ?? '');
                        $ostatus = trim($_GET['status'] ?? '');
                        $opayMethod = trim($_GET['pay_method'] ?? '');
                        $orderedDate = trim($_GET['ordered_date'] ?? '');
                        $neededDate = trim($_GET['needed_date'] ?? '');
                        $owhere = [];
                        $oparams = [];
                        if ($osearch !== '') {
                            $owhere[] = "(u.user_fn LIKE ? OR u.user_ln LIKE ? OR o.order_id = ? OR oa.oa_city LIKE ? OR oa.oa_province LIKE ?)";
                            $oparams[] = "%$osearch%"; $oparams[] = "%$osearch%"; $oparams[] = ctype_digit($osearch) ? (int)$osearch : 0; $oparams[] = "%$osearch%"; $oparams[] = "%$osearch%";
                        }
                        if ($ostatus !== '') { $owhere[] = "o.order_status = ?"; $oparams[] = $ostatus; }
                        if ($opayMethod !== '') { $owhere[] = "pay.pay_method = ?"; $oparams[] = $opayMethod; }
                        if ($orderedDate !== '') { $owhere[] = "DATE(o.order_date) = ?"; $oparams[] = $orderedDate; }
                        if ($neededDate !== '') { $owhere[] = "DATE(o.order_needed) = ?"; $oparams[] = $neededDate; }
            $owsql = $owhere ? ('WHERE '.implode(' AND ', $owhere)) : '';
            $joinPay = "LEFT JOIN ( SELECT p1.* FROM payments p1 JOIN (SELECT order_id, MAX(pay_id) AS max_pid FROM payments GROUP BY order_id) t ON t.max_pid = p1.pay_id ) pay ON pay.order_id=o.order_id";
            $stmtCnt = $pdo->prepare("SELECT COUNT(DISTINCT o.order_id) FROM orders o LEFT JOIN users u ON u.user_id=o.user_id LEFT JOIN orderaddress oa ON oa.order_id=o.order_id $joinPay $owsql");
                        $stmtCnt->execute($oparams);
                        $ordersCount = (int)$stmtCnt->fetchColumn();
            $sql = "SELECT o.*, u.user_fn, u.user_ln, oa.oa_street, oa.oa_city, oa.oa_province, pay.pay_method, pay.pay_status
                                FROM orders o
                                LEFT JOIN users u ON u.user_id=o.user_id
                                LEFT JOIN orderaddress oa ON oa.order_id=o.order_id
                $joinPay
                                $owsql
                                ORDER BY o.order_date DESC
                                LIMIT $olimit OFFSET $ooffset";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($oparams);
                        $orows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $ids = array_column($orows, 'order_id');
                        $itemsByOrder = [];
                        if ($ids) {
                            $in = implode(',', array_fill(0, count($ids), '?'));
                            $sti = $pdo->prepare("SELECT oi.order_id, oi.oi_quantity, oi.oi_price, m.menu_name FROM orderitems oi LEFT JOIN menu m ON m.menu_id=oi.menu_id WHERE oi.order_id IN ($in)");
                            $sti->execute($ids);
                            while ($it = $sti->fetch(PDO::FETCH_ASSOC)) {
                                $oidx = (int)$it['order_id'];
                                if (!isset($itemsByOrder[$oidx])) { $itemsByOrder[$oidx] = []; }
                                $itemsByOrder[$oidx][] = $it;
                            }
                        }
                        foreach ($orows as $r) {
                            $oidx = (int)$r['order_id'];
                            $r['items'] = $itemsByOrder[$oidx] ?? [];
                            $orders[] = $r;
                        }
                    } catch (Throwable $e) {
                        $orders = []; $ordersCount = 0;
                    }
                }
                // Orders chip helpers
                function ord_chip_base(){ return 'inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium'; }
                function ord_item_chip(){ return 'bg-blue-50 border-blue-300 text-blue-800'; }
                function ord_addr_chip(){ return 'bg-violet-50 border-violet-300 text-violet-800'; }
                function ord_method_chip($m){ $n=strtolower(trim((string)$m)); if($n==='cash')return 'bg-amber-50 border-amber-300 text-amber-800'; if($n==='online')return 'bg-sky-50 border-sky-300 text-sky-800'; if($n==='credit')return 'bg-indigo-50 border-indigo-300 text-indigo-800'; return 'bg-stone-50 border-stone-300 text-stone-800'; }
                function ord_status_chip($s){ $n=strtolower(trim((string)$s)); if($n==='completed')return 'bg-emerald-50 border-emerald-300 text-emerald-800'; if($n==='in progress'||$n==='processing'||$n==='ongoing')return 'bg-amber-50 border-amber-300 text-amber-800'; if($n==='canceled'||$n==='cancelled')return 'bg-rose-50 border-rose-300 text-rose-800'; return 'bg-gray-50 border-gray-300 text-gray-800'; }
                function ord_pay_status_chip($s){ $n=strtolower(trim((string)$s)); if($n==='paid')return 'bg-emerald-50 border-emerald-300 text-emerald-800'; if($n==='partial')return 'bg-blue-50 border-blue-300 text-blue-800'; if($n==='pending'||$n==='unpaid')return 'bg-gray-50 border-gray-300 text-gray-800'; if($n==='failed')return 'bg-rose-50 border-rose-300 text-rose-800'; if($n==='refunded')return 'bg-indigo-50 border-indigo-300 text-indigo-800'; return 'bg-stone-50 border-stone-300 text-stone-800'; }
                ?>
                <div id="orders-content" class="section-content <?php echo ($section === 'orders') ? '' : 'hidden '; ?>p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-2xl font-medium text-primary">Orders Management</h2>
                            <p class="text-muted-foreground">View orders and manage fulfillment and payments</p>
                        </div>
                    </div>
                    <form id="orders-filter" method="get" class="card p-3 mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 2xl:grid-cols-7 gap-2 items-end">
                        <input type="hidden" name="section" value="orders" />
                        <div>
                            <label class="text-xs text-muted-foreground">Search</label>
                            <input id="orders-q" type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search..." class="w-full mt-0.5 px-2 py-1 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Status</label>
                            <select id="orders-status" name="status" class="w-full mt-0.5 px-2 py-1 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">All</option>
                                <?php $statuses=['pending','in progress','completed','canceled']; $cur=$_GET['status']??''; foreach($statuses as $s){$sel=$cur===$s?'selected':''; echo "<option value=\"$s\" $sel>".ucfirst($s)."</option>";} ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Payment Method</label>
                            <select id="orders-paymethod" name="pay_method" class="w-full mt-0.5 px-2 py-1 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <?php $pm=$_GET['pay_method']??''; $opts=[''=>'All','Cash'=>'Cash','Online'=>'Online','Credit'=>'Credit']; foreach($opts as $k=>$v){$sel=(string)$pm===(string)$k?'selected':''; echo "<option value=\"".htmlspecialchars($k)."\" $sel>".htmlspecialchars($v)."</option>";} ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Date Ordered</label>
                            <input id="orders-ordered-date" type="date" name="ordered_date" value="<?= htmlspecialchars($_GET['ordered_date'] ?? '') ?>" class="w-full mt-0.5 px-2 py-1 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Date Needed</label>
                            <input id="orders-needed-date" type="date" name="needed_date" value="<?= htmlspecialchars($_GET['needed_date'] ?? '') ?>" class="w-full mt-0.5 px-2 py-1 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="flex md:justify-end">
                            <button type="button" id="orders-clear" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm">Clear</button>
                        </div>
                    </form>

                    <div id="orders-table" class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="text-left p-3">Customer</th>
                                        <th class="text-left p-3">Total Amount</th>
                                        <th class="text-left p-3">Date Needed</th>
                                        <th class="text-left p-3">Address</th>
                                        <th class="text-left p-3">Status</th>
                                        <th class="text-left p-3">Payment Method</th>
                                        <th class="text-left p-3">Payment Status</th>
                                        <th class="text-left p-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orders)): ?>
                                        <?php foreach ($orders as $o): ?>
                                            <?php
                                                $fullname = trim(($o['user_fn'] ?? '').' '.($o['user_ln'] ?? ''));
                                                $addrParts = array_filter([$o['oa_street'] ?? '', $o['oa_city'] ?? '', $o['oa_province'] ?? '']);
                                                $addr = $addrParts ? implode(', ', $addrParts) : '—';
                                                $itemsHtml = '—';
                                                if (!empty($o['items'])) {
                                                    $tmp = [];
                                                    foreach ($o['items'] as $it) {
                                                        $nm = $it['menu_name'] ?? 'Item';
                                                        $qty = (float)$it['oi_quantity'];
                                                        $price = (float)$it['oi_price'];
                                                        $label = htmlspecialchars("$nm x$qty @ ₱".number_format($price,2));
                                                        $tmp[] = '<span class="'.ord_chip_base().' '.ord_item_chip().'">'.$label.'</span>';
                                                    }
                                                    $itemsHtml = '<div class="flex flex-col gap-1">'.implode('', $tmp).'</div>';
                                                }
                                                $pay = ($o['pay_method'] ? '<span class="'.ord_chip_base().' '.ord_method_chip($o['pay_method']).'">'.htmlspecialchars($o['pay_method']).'</span>' : '—');
                                            ?>
                                            <tr class="border-t border-gray-100 hover:bg-gray-50 align-top">
                                                <td class="p-3"><?= htmlspecialchars($fullname ?: '—') ?></td>
                                                <td class="p-3">₱<?= number_format((float)($o['order_amount'] ?? 0), 2) ?></td>
                                                <td class="p-3"><?= htmlspecialchars($o['order_needed'] ?? '') ?></td>
                                                <td class="p-3"><?php if($addr && $addr!=='—'){ ?><span class="<?= ord_chip_base().' '.ord_addr_chip(); ?>" title="<?= htmlspecialchars($addr) ?>"><?= htmlspecialchars($addr) ?></span><?php } else { echo '—'; } ?></td>
                                                <td class="p-3">
                                                    <span class="<?= ord_chip_base().' '.ord_status_chip($o['order_status'] ?? ''); ?>"><?= htmlspecialchars(ucfirst($o['order_status'] ?? 'pending')) ?></span>
                                                </td>
                                                <td class="p-3 whitespace-nowrap"><?= $pay ?></td>
                                                <td class="p-3"><?php
                                                    $method = strtolower(trim((string)($o['pay_method'] ?? '')));
                                                    $ps = trim((string)($o['pay_status'] ?? ''));
                                                    // If online methods, display Paid regardless of stored value per requirement
                                                    if (in_array($method, ['gcash','paypal','paymaya'], true)) { $ps = 'Paid'; }
                                                    echo $ps!==''?('<span class="'.ord_chip_base().' '.ord_pay_status_chip($ps).'">'.htmlspecialchars($ps).'</span>'):'—';
                                                ?></td>
                                                <td class="p-3 whitespace-nowrap">
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" class="p-2 rounded border border-gray-200 hover:bg-gray-50" title="Edit" aria-label="Edit" data-edit-order="<?= (int)$o['order_id'] ?>">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                        <button type="button" class="p-2 rounded border border-gray-200 hover:bg-gray-50" title="View Order" aria-label="View Order" data-view-order="<?= (int)$o['order_id'] ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="p-2 rounded border border-amber-300 text-amber-700 hover:bg-amber-50" title="Mark In Progress" aria-label="Mark In Progress" data-inprogress-order="<?= (int)$o['order_id'] ?>">
                                                            <i class="fas fa-spinner"></i>
                                                        </button>
                                                        <button type="button" class="p-2 rounded border border-gray-200 hover:bg-gray-50 text-green-700" title="Mark Paid" aria-label="Mark Paid" data-paid-order="<?= (int)$o['order_id'] ?>">
                                                            <i class="fas fa-circle-check"></i>
                                                        </button>
                                                        <button type="button" class="p-2 rounded border border-red-200 text-red-700 hover:bg-red-50" title="Delete" aria-label="Delete" data-delete-order="<?= (int)$o['order_id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="p-6 text-center text-muted-foreground">No orders found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                        $opage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                        $olimit = 10;
                        $ototalPages = (int)ceil(($ordersCount ?: 0)/$olimit);
                        if ($ototalPages < 1) { $ototalPages = 1; }
                        $baseQ = $_GET; $baseQ['section'] = 'orders'; unset($baseQ['page']);
                        ?>
                        <div class="p-3 flex items-center justify-between border-t border-gray-100 text-sm">
                            <div>Total: <?= (int)$ordersCount ?> orders</div>
                            <div class="flex items-center gap-1">
                                <?php if ($opage > 1): $q=$baseQ; $q['page']=$opage-1; ?>
                                    <a class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" href="?<?= http_build_query($q) ?>">Prev</a>
                                <?php endif; ?>
                                <span class="px-2 py-1">Page <?= $opage ?> / <?= $ototalPages ?></span>
                                <?php if ($opage < $ototalPages): $q=$baseQ; $q['page']=$opage+1; ?>
                                    <a class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" href="?<?= http_build_query($q) ?>">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Bookings data and filters
                $bkQ = isset($_GET['bk_q']) ? trim((string)$_GET['bk_q']) : '';
                $bkType = isset($_GET['bk_type']) ? trim((string)$_GET['bk_type']) : '';
                $bkOrder = isset($_GET['bk_order']) ? trim((string)$_GET['bk_order']) : '';
                $bkStatus = isset($_GET['bk_status']) ? trim((string)$_GET['bk_status']) : '';
                $bkDate = isset($_GET['bk_date']) ? trim((string)$_GET['bk_date']) : '';
                $bkPage = max(1, (int)($_GET['bk_page'] ?? 1));
                $bkLimit = 10; $bkOffset = ($bkPage - 1) * $bkLimit; $bkTotal = 0; $bookings = [];
                $bkTypesList = [];
                if ($section === 'bookings') {
                    try {
                        $pdo = $db->opencon();
                        // Load distinct event type names from event_types
                        try { $bkTypesList = $pdo->query("SELECT name FROM event_types ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) { $bkTypesList = []; }
                        $w = []; $p = [];
                        if ($bkQ !== '') { $w[] = "(eb.eb_name LIKE ? OR eb.eb_contact LIKE ? OR eb.eb_venue LIKE ?)"; $p[] = '%'.$bkQ.'%'; $p[] = '%'.$bkQ.'%'; $p[] = '%'.$bkQ.'%'; }
                        if ($bkType !== '') { $w[] = "et.name = ?"; $p[] = $bkType; }
                        if ($bkOrder !== '') { $w[] = "eb.eb_order = ?"; $p[] = $bkOrder; }
                        if ($bkStatus !== '') {
                            if ($bkStatus === 'Confirmed') {
                                // Support legacy data stored as 'In Progress' while new flow uses 'Confirmed'
                                $w[] = "(eb.eb_status = ? OR eb.eb_status = 'In Progress')";
                                $p[] = 'Confirmed';
                            } else {
                                $w[] = "eb.eb_status = ?"; $p[] = $bkStatus;
                            }
                        }
                        if ($bkDate !== '') { $w[] = "DATE(eb.eb_date) = ?"; $p[] = $bkDate; }
                        $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';
                        // Count with join to event_types when filtering by type
                        $stmtC = $pdo->prepare("SELECT COUNT(*)
                                                 FROM eventbookings eb
                                                 LEFT JOIN event_types et ON et.event_type_id = eb.event_type_id
                                                 $where");
                        $stmtC->execute($p); $bkTotal = (int)$stmtC->fetchColumn();
            $sql = "SELECT eb.*, u.user_fn, u.user_ln, u.user_email, u.user_phone, et.name AS eb_type, pk.pax AS eb_package_pax, pk.name AS package_name,
                                (SELECT pay_date FROM payments py WHERE py.user_id=eb.user_id AND py.order_id IS NULL AND py.cp_id IS NULL ORDER BY pay_date DESC, pay_id DESC LIMIT 1) AS last_pay_date,
                                (SELECT pay_method FROM payments py WHERE py.user_id=eb.user_id AND py.order_id IS NULL AND py.cp_id IS NULL ORDER BY pay_date DESC, pay_id DESC LIMIT 1) AS last_pay_method,
                                (SELECT pay_amount FROM payments py WHERE py.user_id=eb.user_id AND py.order_id IS NULL AND py.cp_id IS NULL ORDER BY pay_date DESC, pay_id DESC LIMIT 1) AS last_pay_amount
                                FROM eventbookings eb
                                LEFT JOIN users u ON u.user_id=eb.user_id
                                LEFT JOIN event_types et ON et.event_type_id = eb.event_type_id
                                LEFT JOIN packages pk ON pk.package_id = eb.package_id
                                $where
                                ORDER BY eb.created_at DESC LIMIT $bkLimit OFFSET $bkOffset";
                        $stmt = $pdo->prepare($sql); $stmt->execute($p); $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) { $bookings = []; $bkTotal = 0; }
                }
                $bkPages = max(1, (int)ceil($bkTotal / $bkLimit));
                ?>
                <div id="bookings-content" class="section-content <?php echo ($section === 'bookings') ? '' : 'hidden '; ?>p-6">
                    <div class="bg-white border rounded-lg">
                        <div class="p-4 border-b">
                            <h2 class="text-xl font-semibold text-primary">Bookings Management</h2>
                            <p class="text-sm text-muted-foreground">Manage event bookings with live filters</p>
                        </div>
                        <form id="bookings-filter" class="p-4 grid grid-cols-1 md:grid-cols-7 gap-3">
                            <input type="hidden" name="section" value="bookings" />
                            <div class="md:col-span-2">
                                <label class="text-xs text-muted-foreground">Search</label>
                                <input id="bk-q" name="bk_q" value="<?= htmlspecialchars($bkQ) ?>" type="text" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Name, contact, venue" />
                            </div>
                            <div>
                                <label class="text-xs text-muted-foreground">Type</label>
                                <select id="bk-type" name="bk_type" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="">All</option>
                                    <?php foreach ($bkTypesList as $t): ?>
                                        <option value="<?= htmlspecialchars($t) ?>" <?= $bkType===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-muted-foreground">Order</label>
                                <select id="bk-order" name="bk_order" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="">All</option>
                                    <option value="customize" <?= $bkOrder==='customize'?'selected':'' ?>>Customize</option>
                                    <option value="party trays" <?= $bkOrder==='party trays'?'selected':'' ?>>Party trays</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-muted-foreground">Status</label>
                                <select id="bk-status" name="bk_status" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="">All</option>
                                    <option value="Pending" <?= $bkStatus==='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Confirmed" <?= $bkStatus==='Confirmed'?'selected':'' ?>>Confirmed</option>
                                    <option value="Downpayment" <?= $bkStatus==='Downpayment'?'selected':'' ?>>Downpayment</option>
                                    <option value="Completed" <?= $bkStatus==='Completed'?'selected':'' ?>>Completed</option>
                                    <option value="Paid" <?= $bkStatus==='Paid'?'selected':'' ?>>Paid</option>
                                    <option value="Canceled" <?= $bkStatus==='Canceled'?'selected':'' ?>>Canceled</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-muted-foreground">Event Date</label>
                                <input id="bk-date" name="bk_date" type="date" value="<?= htmlspecialchars($bkDate) ?>" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                            </div>
                            <div class="md:col-span-1 flex items-end justify-end">
                                <button id="bookings-clear" type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 hover:bg-gray-50"><i class="fas fa-eraser"></i> Clear</button>
                            </div>
                        </form>
                        <div id="bookings-list">
                            <div id="bookings-cards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                <?php if ($bookings): foreach ($bookings as $b): ?>
                                    <?php
                                        // Compose customer and contacts
                                        $custName = trim((string)($b['eb_name'] ?? ''));
                                        // Prefer booking-provided email/phone, fallback to user profile if available
                                        $email = trim((string)($b['eb_email'] ?? ($b['user_email'] ?? '')));
                                        $phone = trim((string)($b['eb_contact'] ?? ($b['user_phone'] ?? '')));
                                        // Package label using joined aliases when available
                                        $pkgLabel = '—';
                                        $paxVal = trim((string)($b['eb_package_pax'] ?? ''));
                                        $pkgName = trim((string)($b['package_name'] ?? ''));
                                        if ($pkgName !== '') {
                                            $pkgLabel = $pkgName . ($paxVal!=='' ? (' - ' . $paxVal) : '');
                                        } elseif ($paxVal !== '') {
                                            $pkgLabel = $paxVal;
                                        }
                                        $addons = trim((string)($b['eb_addon_pax'] ?? ''));
                                        $venue = trim((string)($b['eb_venue'] ?? ''));
                                        $evtDate = $b['eb_date'] ? date('M d, Y g:i A', strtotime($b['eb_date'])) : '';
                                        $notes = trim((string)($b['eb_notes'] ?? ''));
                                        $status = trim((string)($b['eb_status'] ?? 'Pending'));
                                        $id = (int)$b['eb_id'];
                                    ?>
                                    <div class="card border rounded-xl p-4 hover:shadow-lg transition group">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-base font-semibold text-primary"><?= htmlspecialchars($custName) ?></div>
                                                <div class="text-xs text-muted-foreground">Booking #<?= $id ?></div>
                                            </div>
                                            <span class="<?= booking_chip_base_classes().' '.booking_status_chip_classes($status); ?>" data-bk-status><?= htmlspecialchars($status) ?></span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <div class="text-xs text-muted-foreground">Contacts</div>
                                                <?php $contactParts = []; if ($phone !== '') { $contactParts[] = $phone; } if ($email !== '') { $contactParts[] = $email; } $contacts = $contactParts ? implode(' • ', $contactParts) : '—'; ?>
                                                <div><?= htmlspecialchars($contacts) ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-muted-foreground">Event type</div>
                                                <?php $etypeSafe = isset($b['eb_type']) ? (string)$b['eb_type'] : ''; ?>
                                                <div><span class="<?= booking_chip_base_classes(); ?>" style="<?= booking_type_chip_style($etypeSafe); ?>"><?= htmlspecialchars($etypeSafe !== '' ? $etypeSafe : '—') ?></span></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-muted-foreground">Package</div>
                                                <div><?= htmlspecialchars($pkgLabel) ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-muted-foreground">Add ons</div>
                                                <div><?= htmlspecialchars($addons !== '' ? $addons : '—') ?></div>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <div class="text-xs text-muted-foreground">Venue</div>
                                                <div><span class="inline-block px-2 py-1 rounded border bg-violet-50 border-violet-300 text-violet-800" title="<?= htmlspecialchars($venue) ?>"><?= htmlspecialchars($venue) ?></span></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-muted-foreground">Event Date</div>
                                                <div><?= htmlspecialchars($evtDate) ?></div>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <div class="text-xs text-muted-foreground">Notes</div>
                                                <div class="truncate" title="<?= htmlspecialchars($notes) ?>"><?= htmlspecialchars($notes !== '' ? $notes : '—') ?></div>
                                            </div>
                                        </div>
                                        <div class="mt-4 pt-3 border-t flex flex-wrap items-center justify-end gap-2">
                                            <button class="bk-edit h-9 w-9 grid place-items-center rounded border border-gray-200 hover:bg-gray-50" title="Edit" data-bk-id=<?= $id ?>><i class="fas fa-pen"></i><span class="sr-only">Edit</span></button>
                                            <button class="bk-delete h-9 w-9 grid place-items-center rounded border border-rose-300 text-rose-700 hover:bg-rose-50" title="Delete" data-bk-id=<?= $id ?>><i class="fas fa-trash"></i><span class="sr-only">Delete</span></button>
                                            <button class="bk-confirm h-9 w-9 grid place-items-center rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50" title="Mark Confirmed" data-bk-id=<?= $id ?>><i class="fa-solid fa-circle-check"></i><span class="sr-only">Confirm</span></button>
                                            <button class="bk-complete h-9 w-9 grid place-items-center rounded border border-blue-300 text-blue-700 hover:bg-blue-50" title="Mark Completed" data-bk-id=<?= $id ?>><i class="fa-solid fa-flag-checkered"></i><span class="sr-only">Complete</span></button>
                                            <button class="bk-downpay h-9 w-9 grid place-items-center rounded border border-sky-300 text-sky-700 hover:bg-sky-50" title="Record Downpayment" data-bk-id=<?= $id ?>><i class="fa-solid fa-hand-holding-dollar"></i><span class="sr-only">Downpayment</span></button>
                                            <a class="bk-contract h-9 w-9 grid place-items-center rounded border border-gray-200 hover:bg-gray-50" title="Download Contract" href="?section=bookings&action=contract&booking_id=<?= $id ?>" target="_blank" rel="noopener">
                                                <i class="fa-solid fa-file-pdf"></i><span class="sr-only">Download Contract</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="col-span-full text-center text-sm text-muted-foreground py-10">No bookings found</div>
                                <?php endif; ?>
                            </div>
                            <div class="p-3 flex items-center justify-between border-t border-gray-100 text-sm mt-4">
                                <div>Total: <?= (int)$bkTotal ?> bookings</div>
                                <div class="flex items-center gap-1">
                                    <?php if ($bkPage > 1): $q=$_GET; $q['bk_page']=$bkPage-1; ?>
                                        <a class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" href="?<?= http_build_query($q) ?>">Prev</a>
                                    <?php endif; ?>
                                    <span class="px-2 py-1">Page <?= $bkPage ?> / <?= $bkPages ?></span>
                                    <?php if ($bkPage < $bkPages): $q=$_GET; $q['bk_page']=$bkPage+1; ?>
                                        <a class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" href="?<?= http_build_query($q) ?>">Next</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Catering Packages data and filters (10 per page)
                $cpPage = max(1, (int)($_GET['cp_page'] ?? 1));
                $cpLimit = 10; $cpOffset = ($cpPage - 1) * $cpLimit; $cpTotal = 0; $packages = [];
                if ($section === 'catering') {
                    try {
                        $pdo = $db->opencon();
                        $cq = trim((string)($_GET['cp_q'] ?? ''));
                        $cdate = trim((string)($_GET['cp_date'] ?? ''));
                        $cmethod = trim((string)($_GET['cp_method'] ?? ''));
                        $cstatus = trim((string)($_GET['cp_pay_status'] ?? ''));
                        $w = []; $p = [];
                        if ($cq !== '') { $w[] = "(cp.cp_name LIKE ? OR cp.cp_phone LIKE ? OR cp.cp_place LIKE ?)"; $p[] = "%$cq%"; $p[] = "%$cq%"; $p[] = "%$cq%"; }
                        if ($cdate !== '') { $w[] = "cp.cp_date = ?"; $p[] = $cdate; }
                        // Latest payment per cp via subquery
                        $where = $w ? ('WHERE '.implode(' AND ', $w)) : '';
                        $sqlBase = "FROM cateringpackages cp LEFT JOIN (
                            SELECT t.* FROM payments t INNER JOIN (
                                SELECT cp_id, MAX(CONCAT(pay_date,' ',LPAD(pay_id,10,'0'))) as mx
                                FROM payments WHERE cp_id IS NOT NULL GROUP BY cp_id
                            ) x ON x.cp_id=t.cp_id AND CONCAT(t.pay_date,' ',LPAD(t.pay_id,10,'0'))=x.mx
                        ) pay ON pay.cp_id=cp.cp_id $where";
                        // Filters on payment require having joined rows; apply after join
                        $countBase = $sqlBase; $pCount = $p;
                        // Ensure payment filters go into WHERE (not JOIN ON), so non-matching rows are excluded entirely
                        $hasWhere = ($where !== '');
                        // Payment Method filter
                        if ($cmethod !== '') {
                            $prefix = $hasWhere ? ' AND ' : ' WHERE ';
                            $sqlBase  .= $prefix . "pay.pay_method = ?";
                            $countBase .= $prefix . "pay.pay_method = ?";
                            $p[] = $cmethod; $pCount[] = $cmethod;
                            $hasWhere = true;
                        }
                        // Payment Status filter: treat NULL (no payment) as 'Pending'
                        if ($cstatus !== '') {
                            $prefix = $hasWhere ? ' AND ' : ' WHERE ';
                            if (strcasecmp($cstatus, 'Pending') === 0) {
                                $sqlBase  .= $prefix . "(pay.pay_status IS NULL OR pay.pay_status = 'Pending')";
                                $countBase .= $prefix . "(pay.pay_status IS NULL OR pay.pay_status = 'Pending')";
                            } else {
                                $sqlBase  .= $prefix . "pay.pay_status = ?";
                                $countBase .= $prefix . "pay.pay_status = ?";
                                $p[] = $cstatus; $pCount[] = $cstatus;
                            }
                            $hasWhere = true;
                        }
                        // Count with payment filters applied
                        $stmtC = $pdo->prepare("SELECT COUNT(*) $countBase"); $stmtC->execute($pCount); $cpTotal = (int)$stmtC->fetchColumn();
                        // Order by event date (newest first), then id as tiebreaker
                        $stmt = $pdo->prepare("SELECT cp.*, pay.pay_amount, pay.pay_method, pay.pay_status $sqlBase ORDER BY cp.cp_date DESC, cp.cp_id DESC LIMIT ? OFFSET ?");
                        foreach ($p as $i=>$val) { $stmt->bindValue($i+1, $val); }
                        $stmt->bindValue(count($p)+1, $cpLimit, PDO::PARAM_INT);
                        $stmt->bindValue(count($p)+2, $cpOffset, PDO::PARAM_INT);
                        $stmt->execute();
                        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) { $packages = []; $cpTotal = 0; }
                }
                $cpPages = max(1, (int)ceil(($cpTotal ?: 0)/$cpLimit));
                function cp_chip_base(){ return 'inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium'; }
                function cp_place_chip(){ return 'bg-violet-50 border-violet-300 text-violet-800'; }
                function cp_method_chip($m){
                    $n=strtolower(trim((string)$m));
                    if($n==='cash') return 'bg-amber-50 border-amber-300 text-amber-800';
                    if($n==='online') return 'bg-sky-50 border-sky-300 text-sky-800';
                    if($n==='credit' || $n==='card') return 'bg-indigo-50 border-indigo-300 text-indigo-800';
                    if($n==='gcash') return 'bg-cyan-50 border-cyan-300 text-cyan-800';
                    if($n==='paymaya') return 'bg-emerald-50 border-emerald-300 text-emerald-800';
                    if($n==='paypal') return 'bg-blue-50 border-blue-300 text-blue-800';
                    return 'bg-stone-50 border-stone-300 text-stone-800';
                }
                function cp_status_chip($s){ $n=strtolower(trim((string)$s)); if($n==='paid')return 'bg-emerald-50 border-emerald-300 text-emerald-800'; if($n==='partial')return 'bg-blue-50 border-blue-300 text-blue-800'; if($n==='pending')return 'bg-gray-50 border-gray-300 text-gray-800'; return 'bg-stone-50 border-stone-300'; }
                ?>
                <div id="catering-content" class="section-content <?php echo ($section === 'catering') ? '' : 'hidden '; ?>p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-semibold text-primary">Catering Packages</h2>
                            <p class="text-sm text-muted-foreground">Manage catering bookings and payments</p>
                        </div>
                    </div>
                    <form id="cp-filter" class="p-4 grid grid-cols-1 md:grid-cols-7 gap-3 card mb-4">
                        <input type="hidden" name="section" value="catering" />
                        <div class="md:col-span-2">
                            <label class="text-xs text-muted-foreground">Search</label>
                            <input id="cp-q" type="text" name="cp_q" value="<?= htmlspecialchars($_GET['cp_q'] ?? '') ?>" placeholder="Search name, phone, place..." class="w-full mt-1 px-2 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Date</label>
                            <input id="cp-date" type="date" name="cp_date" value="<?= htmlspecialchars($_GET['cp_date'] ?? '') ?>" class="w-full mt-1 px-2 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Payment Method</label>
                            <select id="cp-method" name="cp_method" class="w-full mt-1 px-2 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">All</option>
                                <?php $mcur = $_GET['cp_method'] ?? ''; $methods = ['Cash','Online','Credit','Card','GCash','PayMaya','PayPal']; foreach($methods as $mopt){ $sel = ($mcur===$mopt)?'selected':''; echo "<option value=\"".htmlspecialchars($mopt)."\" $sel>".htmlspecialchars($mopt)."</option>"; } ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Payment Status</label>
                            <select id="cp-status" name="cp_pay_status" class="w-full mt-1 px-2 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">All</option>
                                <option value="Pending" <?= (($_GET['cp_pay_status'] ?? '')==='Pending')?'selected':''; ?>>Pending</option>
                                <option value="Partial" <?= (($_GET['cp_pay_status'] ?? '')==='Partial')?'selected':''; ?>>Partial</option>
                                <option value="Paid" <?= (($_GET['cp_pay_status'] ?? '')==='Paid')?'selected':''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button id="cp-clear" type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 hover:bg-gray-50"><i class="fas fa-eraser"></i> Clear</button>
                        </div>
                    </form>
                    <div id="cp-list" class="card overflow-hidden">
                        <div id="cp-cards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 p-4">
                            <?php if ($packages): foreach ($packages as $cp): ?>
                                <?php
                                    $id = (int)$cp['cp_id'];
                                    $name = trim((string)$cp['cp_name']);
                                    $phone = trim((string)$cp['cp_phone']);
                                    $email = trim((string)($cp['cp_email'] ?? ''));
                                    $place = trim((string)$cp['cp_place']);
                                    $date = trim((string)$cp['cp_date']);
                                    $price = (float)$cp['cp_price'];
                                    $addons = trim((string)($cp['cp_addon_pax'] ?? ''));
                                    $notes = trim((string)($cp['cp_notes'] ?? ''));
                                    $payAmount = isset($cp['pay_amount']) && $cp['pay_amount']!==null ? ('₱'.number_format((float)$cp['pay_amount'],2)) : '—';
                                    $payMethod = trim((string)($cp['pay_method'] ?? ''));
                                    $payStatus = trim((string)($cp['pay_status'] ?? ''));
                                ?>
                                <div class="card border rounded-xl p-4 hover:shadow-lg transition group">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-base font-semibold text-primary"><?= htmlspecialchars($name) ?></div>
                                            <div class="text-xs text-muted-foreground">Package #<?= $id ?></div>
                                        </div>
                                        <?php if ($payStatus !== ''): ?>
                                            <span class="<?= cp_chip_base().' '.cp_status_chip($payStatus); ?>"><?= htmlspecialchars($payStatus) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <div class="text-xs text-muted-foreground">Contacts</div>
                                            <?php $cpContactParts = []; if ($phone !== '') { $cpContactParts[] = $phone; } if ($email !== '') { $cpContactParts[] = $email; } $cpContacts = $cpContactParts ? implode(' • ', $cpContactParts) : '—'; ?>
                                            <div><?= htmlspecialchars($cpContacts) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">Place</div>
                                            <div><span class="<?= cp_chip_base().' '.cp_place_chip(); ?>" title="<?= htmlspecialchars($place) ?>"><?= htmlspecialchars($place) ?></span></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">Date</div>
                                            <div><?= htmlspecialchars($date) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">Price</div>
                                            <div>₱<?= number_format($price,2) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">Addons</div>
                                            <div><?= htmlspecialchars($addons !== '' ? $addons : '—') ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">Payment Amount</div>
                                            <div><?= $payAmount ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">Payment Method</div>
                                            <div><?php if ($payMethod !== '') { ?><span class="<?= cp_chip_base().' '.cp_method_chip($payMethod); ?>"><?= htmlspecialchars($payMethod) ?></span><?php } else { echo '—'; } ?></div>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <div class="text-xs text-muted-foreground">Notes</div>
                                            <div class="truncate" title="<?= htmlspecialchars($notes) ?>"><?= htmlspecialchars($notes !== '' ? $notes : '—') ?></div>
                                        </div>
                                    </div>
                                    <div class="mt-4 pt-3 border-t flex flex-wrap items-center justify-end gap-2">
                                        <button class="cp-edit h-9 px-3 rounded border border-gray-300 hover:bg-gray-50" title="Edit" data-cp-id="<?= $id ?>"><i class="fas fa-pen mr-2"></i>Edit</button>
                                        <button class="cp-delete h-9 px-3 rounded border border-rose-300 text-rose-700 hover:bg-rose-50" title="Delete" data-cp-id="<?= $id ?>"><i class="fas fa-trash mr-2"></i>Delete</button>
                                        <button class="cp-paid h-9 px-3 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50" title="Paid" data-cp-id="<?= $id ?>"><i class="fa-solid fa-circle-check mr-2"></i>Paid</button>
                                    </div>
                                </div>
                            <?php endforeach; else: ?>
                                <div class="col-span-full text-center text-sm text-muted-foreground py-10">No catering packages found</div>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 flex items-center justify-between border-t border-gray-100 text-sm">
                            <div>Total: <?= (int)$cpTotal ?> items</div>
                            <div class="flex items-center gap-1">
                                <?php if ($cpPage > 1): $q=$_GET; $q['cp_page']=$cpPage-1; ?>
                                    <a class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" href="?<?= http_build_query($q) ?>">Prev</a>
                                <?php endif; ?>
                                <span class="px-2 py-1">Page <?= $cpPage ?> / <?= $cpPages ?></span>
                                <?php if ($cpPage < $cpPages): $q=$_GET; $q['cp_page']=$cpPage+1; ?>
                                    <a class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" href="?<?= http_build_query($q) ?>">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Data for categories section with pagination (7 per page)
                $categoriesList = [];
                $catPage = 1; $catLimit = 7; $catTotalCount = 0; $catTotalPages = 1;
                if ($section === 'categories') {
                    try {
                        $pdo = $db->opencon();
                        $catPage = max(1, (int)($_GET['cat_page'] ?? 1));
                        $catLimit = 7;
                        $catOffset = ($catPage - 1) * $catLimit;
                        $catTotalCount = (int)$pdo->query("SELECT COUNT(*) FROM category")->fetchColumn();
                        $catTotalPages = max(1, (int)ceil($catTotalCount / $catLimit));
                        $stmt = $pdo->prepare("SELECT category_id, category_name FROM category ORDER BY category_name ASC LIMIT :lim OFFSET :off");
                        $stmt->bindValue(':lim', $catLimit, PDO::PARAM_INT);
                        $stmt->bindValue(':off', $catOffset, PDO::PARAM_INT);
                        $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $categoriesList = [];
                        if ($rows) {
                            $ids = array_column($rows, 'category_id');
                            if ($ids) {
                                $in = implode(',', array_fill(0, count($ids), '?'));
                                $m = $pdo->prepare("SELECT mc.category_id, m.menu_id, m.menu_name FROM menucategory mc JOIN menu m ON m.menu_id=mc.menu_id WHERE mc.category_id IN ($in) ORDER BY m.menu_name");
                                $m->execute($ids);
                                $menusByCat = [];
                                while ($r = $m->fetch(PDO::FETCH_ASSOC)) {
                                    $cid = (int)$r['category_id'];
                                    if (!isset($menusByCat[$cid])) $menusByCat[$cid] = [];
                                    $menusByCat[$cid][] = $r;
                                }
                                foreach ($rows as $r) {
                                    $r['menus'] = $menusByCat[(int)$r['category_id']] ?? [];
                                    $categoriesList[] = $r;
                                }
                            } else {
                                $categoriesList = $rows;
                            }
                        }
                    } catch (Throwable $e) { $categoriesList = []; $catTotalCount = 0; $catTotalPages = 1; }
                }
                ?>
                <div id="categories-content" class="section-content <?php echo ($section === 'categories') ? '' : 'hidden '; ?>p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-2xl font-medium text-primary">Food Categories</h2>
                            <p class="text-muted-foreground">Group menu items by category</p>
                        </div>
                        <button type="button" id="open-add-category" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-accent text-accent-foreground hover:opacity-90 transition">
                            <i class="fas fa-plus"></i>
                            Add Category
                        </button>
                    </div>
                    <div id="categories-table" class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="text-left p-3 w-28">Category</th>
                                        <th class="text-left p-3">Menus</th>
                                        <th class="text-left p-3 w-40">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categoriesList)): ?>
                                        <tr><td colspan="3" class="p-6 text-center text-muted-foreground">No categories yet.</td></tr>
                                    <?php else: foreach ($categoriesList as $c): ?>
                                        <tr class="border-t border-gray-100 hover:bg-gray-50 align-top">
                                            <td class="p-3 font-medium text-primary"><?php echo htmlspecialchars($c['category_name']); ?></td>
                                            <td class="p-3">
                                                <?php if (!empty($c['menus'])): ?>
                                                    <div class="flex flex-wrap gap-2">
                                                        <?php
                                                            $chipCls = category_chip_classes($c['category_name'] ?? '', $c['category_id'] ?? null);
                                                            $chipStyle = category_chip_style($c['category_name'] ?? '', $c['category_id'] ?? null);
                                                            foreach ($c['menus'] as $m): ?>
                                                            <div class="border rounded-lg px-2 py-1 <?php echo $chipCls; ?>" style="<?php echo htmlspecialchars($chipStyle, ENT_QUOTES); ?>">
                                                                <div class="text-sm"><?php echo htmlspecialchars($m['menu_name']); ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted-foreground">No menus</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3">
                                                <div class="flex items-center gap-2">
                                                    <button type="button" title="Edit" class="h-9 w-9 grid place-items-center rounded border border-gray-200 text-gray-700 hover:bg-gray-50" data-edit-category="<?php echo (int)$c['category_id']; ?>">
                                                        <i class="fas fa-pen"></i>
                                                        <span class="sr-only">Edit</span>
                                                    </button>
                                                    <button type="button" title="Delete" class="h-9 w-9 grid place-items-center rounded border border-red-200 text-red-700 hover:bg-red-50" data-delete-category="<?php echo (int)$c['category_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                        <span class="sr-only">Delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($section === 'categories' && $catTotalPages > 1): ?>
                        <div class="flex items-center justify-between px-3 py-2 border-t text-sm">
                            <div class="text-muted-foreground">Page <?php echo (int)$catPage; ?> of <?php echo (int)$catTotalPages; ?></div>
                            <div class="flex items-center gap-2">
                                <?php $prev = max(1, $catPage - 1); $next = min($catTotalPages, $catPage + 1); ?>
                                <a class="px-2 py-1 rounded border hover:bg-gray-50 <?php echo $catPage <= 1 ? 'pointer-events-none opacity-50' : ''; ?>" href="?section=categories&cat_page=<?php echo (int)$prev; ?>">Prev</a>
                                <?php for ($p = 1; $p <= $catTotalPages; $p++): ?>
                                    <a class="px-2 py-1 rounded border <?php echo $p === (int)$catPage ? 'bg-primary text-white border-primary' : 'hover:bg-gray-50'; ?>" href="?section=categories&cat_page=<?php echo (int)$p; ?>"><?php echo (int)$p; ?></a>
                                <?php endfor; ?>
                                <a class="px-2 py-1 rounded border hover:bg-gray-50 <?php echo $catPage >= $catTotalPages ? 'pointer-events-none opacity-50' : ''; ?>" href="?section=categories&cat_page=<?php echo (int)$next; ?>">Next</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add/Edit Category Modal -->
                <div id="cat-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
                <div id="cat-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
                    <div class="w-full max-w-xl mx-4 scale-95 transition-transform duration-200">
                        <div class="bg-white rounded-lg shadow-xl">
                            <div class="flex items-center justify-between px-4 py-3 border-b">
                                <h3 id="cat-modal-title" class="text-lg font-medium">Add Category</h3>
                                <button type="button" id="cat-close" class="h-8 w-8 grid place-items-center rounded hover:bg-gray-100"><i class="fas fa-times"></i></button>
                            </div>
                            <form id="cat-form" class="p-4 space-y-4">
                                <input type="hidden" name="section" value="categories" />
                                <input type="hidden" name="ajax" value="1" />
                                <input type="hidden" name="action" value="create" id="cat-action" />
                                <input type="hidden" name="category_id" id="cat-id" />
                                <div>
                                    <label class="text-sm text-muted-foreground">Category Name</label>
                                    <input type="text" name="category_name" id="cat-name" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" required />
                                </div>
                                <div>
                                    <label class="text-sm text-muted-foreground">Search Menu</label>
                                    <input type="text" id="cat-menu-search" placeholder="Type to search..." class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                                    <div id="cat-menu-results" class="mt-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg hidden"></div>
                                    <div class="mt-3">
                                        <div class="text-sm text-muted-foreground mb-1">All Menus</div>
                                        <div id="cat-menu-all" class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg"></div>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm text-muted-foreground">Selected Menus</label>
                                    <div id="cat-selected" class="mt-1 flex flex-wrap gap-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2"></div>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" id="cat-cancel" class="px-3 py-2 rounded border border-gray-300">Cancel</button>
                                    <button type="submit" class="px-4 py-2 rounded bg-primary text-white">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php
                // Event Types data (no pagination for now; small list expected)
                $eventTypes = [];
                if ($section === 'eventtypes') {
                    try {
                        $pdo = $db->opencon();
                        $stmt = $pdo->query("SELECT et.event_type_id, et.name, et.min_package_pax, et.max_package_pax, et.notes, et.updated_at,
                                                     COALESCE((SELECT COUNT(*) FROM event_type_packages ep WHERE ep.event_type_id=et.event_type_id),0) AS package_count
                                              FROM event_types et
                                              ORDER BY et.updated_at DESC, et.event_type_id DESC");
                        $eventTypes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Throwable $e) { $eventTypes = []; }
                }
                ?>
                <div id="eventtypes-content" class="section-content <?php echo ($section === 'eventtypes') ? '' : 'hidden '; ?>p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-2xl font-medium text-primary">Event Types</h2>
                            <p class="text-muted-foreground">Manage event types and which packages are allowed per type</p>
                        </div>
                        <button type="button" id="open-add-eventtype" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-accent text-accent-foreground hover:opacity-90 transition">
                            <i class="fas fa-plus"></i>
                            Add Event Type
                        </button>
                    </div>
                    <div id="et-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php if ($section === 'eventtypes' && !empty($eventTypes)): foreach ($eventTypes as $et): ?>
                            <div class="card p-4 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="text-lg font-semibold text-primary"><?php echo htmlspecialchars($et['name']); ?></h3>
                                        <span class="inline-flex items-center px-2 py-1 text-xs rounded-full border bg-emerald-50 border-emerald-300 text-emerald-800" title="Linked packages">
                                            <i class="fa-solid fa-boxes-stacked mr-1"></i>
                                            <?php echo (int)$et['package_count']; ?>
                                        </span>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2 text-sm">
                                        <?php if (!empty($et['min_package_pax'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full border bg-blue-50 border-blue-300 text-blue-800" title="Min pax">Min: <?php echo htmlspecialchars($et['min_package_pax']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($et['max_package_pax'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full border bg-purple-50 border-purple-300 text-purple-800" title="Max pax">Max: <?php echo htmlspecialchars($et['max_package_pax']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($et['notes'])): ?>
                                        <div class="mt-3 text-sm text-muted-foreground line-clamp-3" title="<?php echo htmlspecialchars((string)$et['notes']); ?>"><?php echo htmlspecialchars((string)$et['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-4 flex items-center justify-end gap-2">
                                    <button type="button" class="et-edit h-9 w-9 grid place-items-center rounded border border-gray-200 hover:bg-gray-50" title="Edit" data-et-id="<?php echo (int)$et['event_type_id']; ?>">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="et-delete h-9 w-9 grid place-items-center rounded border border-red-200 text-red-700 hover:bg-red-50" title="Delete" data-et-id="<?php echo (int)$et['event_type_id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <?php if ($section === 'eventtypes'): ?>
                                <div class="col-span-full text-center text-muted-foreground py-10">No event types yet.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add/Edit Event Type Modal -->
                <div id="et-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
                <div id="et-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
                    <div class="w-full max-w-2xl mx-4 scale-95 transition-transform duration-200">
                        <div class="bg-white rounded-lg shadow-xl">
                            <div class="flex items-center justify-between px-4 py-3 border-b">
                                <h3 id="et-modal-title" class="text-lg font-medium">Add Event Type</h3>
                                <button type="button" id="et-close" class="h-8 w-8 grid place-items-center rounded hover:bg-gray-100"><i class="fas fa-times"></i></button>
                            </div>
                            <form id="et-form" class="p-4 space-y-4">
                                <input type="hidden" name="section" value="eventtypes" />
                                <input type="hidden" name="ajax" value="1" />
                                <input type="hidden" name="action" value="create" id="et-action" />
                                <input type="hidden" name="event_type_id" id="et-id" />
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label class="text-sm text-muted-foreground">Event Name</label>
                                        <input type="text" name="name" id="et-name" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" required />
                                    </div>
                                    <div>
                                        <label class="text-sm text-muted-foreground">Minimum Pax</label>
                                        <select name="min_package_pax" id="et-min" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                            <option value="">None</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="150">150</option>
                                            <option value="200">200</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-sm text-muted-foreground">Maximum Pax</label>
                                        <select name="max_package_pax" id="et-max" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                            <option value="">None</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="150">150</option>
                                            <option value="200">200</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-sm text-muted-foreground">Notes</label>
                                        <textarea name="notes" id="et-notes" rows="3" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Optional"></textarea>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm text-muted-foreground">Allowed Packages</label>
                                            <input id="et-packages-search" type="text" placeholder="Search packages..." class="ml-2 flex-1 px-2 py-1 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                                        </div>
                                        <div id="et-packages-list" class="mt-2 max-h-56 overflow-y-auto border border-gray-200 rounded-lg p-2 grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" id="et-cancel" class="px-3 py-2 rounded border border-gray-300">Cancel</button>
                                    <button type="submit" class="px-4 py-2 rounded bg-primary text-white">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php
                // Employees data and filters (server-side)
                $empQ = isset($_GET['emp_q']) ? trim((string)$_GET['emp_q']) : '';
                $empRole = isset($_GET['emp_role']) ? trim((string)$_GET['emp_role']) : '';
                $empSex = isset($_GET['emp_sex']) ? trim((string)$_GET['emp_sex']) : '';
                $empAvail = isset($_GET['emp_avail']) && $_GET['emp_avail'] !== '' ? (string)$_GET['emp_avail'] : '';
                $empPage = max(1, (int)($_GET['emp_page'] ?? 1));
                $empLimit = 10; $empOffset = ($empPage - 1) * $empLimit; $empTotal = 0; $employees = [];
                if ($section === 'employees') {
                    try {
                        $pdo = $db->opencon();
                        $w = [];$p = [];
                        if ($empQ !== '') { $w[] = "(emp_fn LIKE ? OR emp_ln LIKE ? OR emp_email LIKE ? OR emp_phone LIKE ?)"; $p[]="%$empQ%"; $p[]="%$empQ%"; $p[]="%$empQ%"; $p[]="%$empQ%"; }
                        if ($empRole !== '') { $w[] = "emp_role = ?"; $p[] = $empRole; }
                        if ($empSex !== '') { $w[] = "emp_sex = ?"; $p[] = $empSex; }
                        if ($empAvail !== '') { $w[] = "emp_avail = ?"; $p[] = (int)$empAvail; }
                        $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';
                        $cnt = $pdo->prepare("SELECT COUNT(*) FROM employee $where");
                        $cnt->execute($p); $empTotal = (int)$cnt->fetchColumn();
                        $sql = $pdo->prepare("SELECT emp_id, emp_fn, emp_ln, emp_sex, emp_email, emp_phone, emp_role, emp_avail, emp_photo FROM employee $where ORDER BY created_at DESC LIMIT $empLimit OFFSET $empOffset");
                        $sql->execute($p); $employees = $sql->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) { $empTotal=0; $employees=[]; }
                }
                $empPages = max(1, (int)ceil($empTotal / $empLimit));
                ?>
                

                <div id="lock-sidebar-content" class="section-content <?php echo ($section === 'lock-sidebar') ? '' : 'hidden '; ?>p-6">
                    <div class="card max-w-2xl mx-auto p-8">
                        <h2 class="text-2xl font-medium text-primary mb-2">Lock Sidebar</h2>
                        <p class="text-muted-foreground mb-8">Configure sidebar locking and navigation preferences</p>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-lock text-2xl text-primary"></i>
                            </div>
                            <p class="text-muted-foreground mb-4">Sidebar lock settings, auto-collapse preferences, and navigation customization will be available here.</p>
                            <div class="inline-flex items-center gap-2 text-sm text-primary">
                                <div class="w-2 h-2 bg-primary rounded-full animate-pulse"></div>
                                Coming Soon
                            </div>
                        </div>
                    </div>
                </div>

                <div id="settings-content" class="section-content <?php echo ($section === 'settings') ? '' : 'hidden '; ?>p-6">
                    <h2 class="text-2xl font-medium text-primary mb-2">Settings</h2>
                    <p class="text-muted-foreground mb-8">Configure system settings and preferences</p>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="card p-6">
                            <h3 class="text-lg font-medium mb-4 flex items-center gap-2"><i class="fas fa-images text-primary"></i> Our Menu Section Images</h3>
                            <p class="text-sm text-muted-foreground mb-4">Upload up to 10 images to rotate in the public "Our Menu" section (guest & user home pages). First image appears first. Click X to remove before saving.</p>
                            <form id="menu-images-form" class="space-y-4" enctype="multipart/form-data">
                                <input type="hidden" name="ajax" value="1" />
                                <input type="hidden" name="action" value="update_menu_images" />
                                <div>
                                    <label class="block text-sm font-medium mb-2">Add New Images</label>
                                    <input type="file" name="images[]" multiple accept="image/*" class="block w-full text-sm" />
                                    <p class="text-[11px] text-gray-500 mt-1">Accepted: JPG, PNG, WEBP, AVIF. Large images will not be resized automatically.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Current Images</label>
                                    <div id="menu-images-preview" class="grid grid-cols-2 md:grid-cols-3 gap-3"></div>
                                </div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-green-700 flex items-center gap-2"><i class="fas fa-upload"></i> Save Images</button>
                                    <button type="button" id="refresh-menu-images" class="px-3 py-2 border rounded text-sm hover:bg-gray-50"><i class="fas fa-rotate"></i> Refresh</button>
                                    <div id="menu-images-status" class="text-sm text-gray-500"></div>
                                </div>
                            </form>
                        </div>
                        <div class="card p-6">
                            <h3 class="text-lg font-medium mb-4 flex items-center gap-2"><i class="fas fa-images text-primary"></i> Our Collections Images</h3>
                            <p class="text-sm text-muted-foreground mb-4">Upload images to use in the landing page "Our Collections" slider. Only the images will change; the titles, hover effects, and continuous sliding remain untouched. First image appears first.</p>
                            <form id="collections-images-form" class="space-y-4" enctype="multipart/form-data">
                                <input type="hidden" name="ajax" value="1" />
                                <input type="hidden" name="action" value="update_collections_images" />
                                <div>
                                    <label class="block text-sm font-medium mb-2">Add New Images</label>
                                    <input type="file" name="images[]" multiple accept="image/*" class="block w-full text-sm" />
                                    <p class="text-[11px] text-gray-500 mt-1">Accepted: JPG, PNG, WEBP, AVIF. Recommended landscape 16:9 or 4:3 for best results.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Current Images</label>
                                    <div id="collections-images-preview" class="grid grid-cols-2 md:grid-cols-3 gap-3"></div>
                                </div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-green-700 flex items-center gap-2"><i class="fas fa-upload"></i> Save Images</button>
                                    <button type="button" id="refresh-collections-images" class="px-3 py-2 border rounded text-sm hover:bg-gray-50"><i class="fas fa-rotate"></i> Refresh</button>
                                    <div id="collections-images-status" class="text-sm text-gray-500"></div>
                                </div>
                            </form>
                        </div>
                        <div class="card p-6">
                            <h3 class="text-lg font-medium mb-4"><i class="fas fa-cog text-primary mr-2"></i> General</h3>
                            <p class="text-muted-foreground mb-4">Additional system configuration panels will be added here.</p>
                            <div class="inline-flex items-center gap-2 text-sm text-primary">
                                <div class="w-2 h-2 bg-primary rounded-full animate-pulse"></div>
                                Coming Soon
                            </div>
                        </div>
                    </div>
                    <script>
                    (function(){
                        const BASE_URL = (function(){
                            // Compute base URL by removing trailing /admin from current path
                            let path = window.location.pathname;
                            if (path.match(/\/admin\//)) {
                                path = path.replace(/\/admin\/(.*)$/,'/');
                            }
                            return path.replace(/\/+$/, '');
                        })();
                        const preview = document.getElementById('menu-images-preview');
                        const form = document.getElementById('menu-images-form');
                        const statusEl = document.getElementById('menu-images-status');
                        const refreshBtn = document.getElementById('refresh-menu-images');
                        if(!preview) return;
                        let current = [];
                        function render(){
                            preview.innerHTML='';
                            if(!current.length){ preview.innerHTML='<div class="col-span-full text-xs text-gray-500">No images configured yet.</div>'; return; }
                            current.forEach((src,i)=>{
                                let displaySrc = src;
                                if(!/^https?:\/\//i.test(displaySrc)) {
                                    displaySrc = BASE_URL + '/' + displaySrc.replace(/^\/+/, '');
                                }
                                const wrap=document.createElement('div');
                                wrap.className='relative group border rounded overflow-hidden aspect-video bg-gray-100';
                                wrap.innerHTML='<img src="'+displaySrc+'" class="w-full h-full object-cover" alt="menu hero '+(i+1)+'"/>\n<button type="button" data-idx="'+i+'" class="absolute top-1 right-1 bg-black/60 text-white rounded px-1 text-xs opacity-0 group-hover:opacity-100 transition">&times;</button>';
                                preview.appendChild(wrap);
                            });
                        }
                        function load(){
                            fetch('?section=settings&action=get_menu_images&ajax=1')
                              .then(r=>r.json()).then(j=>{ if(j.success){ current=j.images||[]; render(); } });
                        }
                        preview.addEventListener('click', e=>{
                            const btn=e.target.closest('button[data-idx]');
                            if(!btn) return; const idx=parseInt(btn.getAttribute('data-idx')); if(isNaN(idx)) return; current.splice(idx,1); render();
                        });
                        form.addEventListener('submit', e=>{
                            e.preventDefault();
                            const fd=new FormData(form); fd.append('existing', JSON.stringify(current));
                            statusEl.textContent='Saving...';
                            fetch('?section=settings&action=update_menu_images', {method:'POST', body:fd})
                              .then(r=>r.json()).then(j=>{ if(j.success){ current=j.images||[]; render(); statusEl.textContent='Saved ('+current.length+' images).'; form.reset(); } else { statusEl.textContent='Error: '+(j.error||'Unknown'); } })
                              .catch(err=> statusEl.textContent='Error: '+err.message);
                        });
                        refreshBtn.addEventListener('click', load);
                        if(document.location.search.includes('section=settings')) { load(); }
                    })();
                    // Collections images manager
                    (function(){
                        const BASE_URL = (function(){
                            let path = window.location.pathname;
                            if (path.match(/\/admin\//)) { path = path.replace(/\/admin\/(.*)$/,'/'); }
                            return path.replace(/\/+$/, '');
                        })();
                        const preview = document.getElementById('collections-images-preview');
                        const form = document.getElementById('collections-images-form');
                        const statusEl = document.getElementById('collections-images-status');
                        const refreshBtn = document.getElementById('refresh-collections-images');
                        if(!preview) return;
                        let current = [];
                        function render(){
                            preview.innerHTML='';
                            if(!current.length){ preview.innerHTML='<div class="col-span-full text-xs text-gray-500">No images configured yet.</div>'; return; }
                            current.forEach((src,i)=>{
                                let displaySrc = src;
                                if(!/^https?:\/\//i.test(displaySrc)) { displaySrc = BASE_URL + '/' + displaySrc.replace(/^\/+/, ''); }
                                const wrap=document.createElement('div');
                                wrap.className='relative group border rounded overflow-hidden aspect-video bg-gray-100';
                                wrap.innerHTML='<img src="'+displaySrc+'" class="w-full h-full object-cover" alt="collection '+(i+1)+'"/>\n<button type="button" data-idx="'+i+'" class="absolute top-1 right-1 bg-black/60 text-white rounded px-1 text-xs opacity-0 group-hover:opacity-100 transition">&times;</button>';
                                preview.appendChild(wrap);
                            });
                        }
                        function load(){ fetch('?section=settings&action=get_collections_images&ajax=1').then(r=>r.json()).then(j=>{ if(j.success){ current=j.images||[]; render(); } }); }
                        preview.addEventListener('click', e=>{ const btn=e.target.closest('button[data-idx]'); if(!btn) return; const idx=parseInt(btn.getAttribute('data-idx')); if(isNaN(idx)) return; current.splice(idx,1); render(); });
                        form.addEventListener('submit', e=>{
                            e.preventDefault(); const fd=new FormData(form); fd.append('existing', JSON.stringify(current));
                            statusEl.textContent='Saving...';
                            fetch('?section=settings&action=update_collections_images', {method:'POST', body:fd})
                              .then(r=>r.json()).then(j=>{ if(j.success){ current=j.images||[]; render(); statusEl.textContent='Saved ('+current.length+' images).'; form.reset(); } else { statusEl.textContent='Error: '+(j.error||'Unknown'); } })
                              .catch(err=> statusEl.textContent='Error: '+err.message);
                        });
                        if(refreshBtn) refreshBtn.addEventListener('click', load);
                        if(document.location.search.includes('section=settings')) { load(); }
                    })();
                    </script>
                </div>

                <!-- Packages Section -->
                <div id="packages-content" class="section-content <?php echo ($section === 'packages') ? '' : 'hidden '; ?>p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-medium text-primary">Packages</h2>
                        <button id="add-package-btn" class="px-3 py-2 rounded-lg bg-primary text-white hover:bg-green-700">
                            <i class="fas fa-plus mr-2"></i>Add Package
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if ($section === 'packages' && !empty($pkgRows)): foreach ($pkgRows as $pr): ?>
                        <div data-package-card class="group relative rounded-2xl border border-gray-200 bg-white/90 backdrop-blur overflow-hidden shadow-sm transition-all duration-200 hover:shadow-xl hover:-translate-y-1 flex flex-col">
                            <div class="relative h-56 w-full bg-gray-100 overflow-hidden">
                                <img src="<?php echo htmlspecialchars(pkg_img_src($pr['package_id'])); ?>" alt="Package Image" class="w-full h-full object-cover transform transition-transform duration-300 group-hover:scale-105"/>
                                <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/30 to-transparent pointer-events-none"></div>
                            </div>
                            <div class="p-5 space-y-3 flex-1">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-semibold text-primary text-base"><?php echo htmlspecialchars($pr['name']); ?></div>
                                        <div class="text-sm text-muted-foreground">Pax: <?php echo htmlspecialchars($pr['pax']); ?><?php if ($pr['base_price'] !== null): ?> • ₱<?php echo number_format((float)$pr['base_price'],2); ?><?php endif; ?></div>
                                    </div>
                                    <span class="status-badge inline-block px-2 py-0.5 rounded-full text-[11px] border <?php echo ((int)$pr['is_active']===1) ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-gray-50 border-gray-300 text-gray-800'; ?> group-hover:shadow-sm"><?php echo ((int)$pr['is_active']===1) ? 'Active' : 'Inactive'; ?></span>
                                </div>
                                <?php
                                // Items preview (first 6)
                                try { $pdoPrev = $db->opencon(); $stmtPrev = $pdoPrev->prepare("SELECT item_label, qty, unit, is_optional FROM package_items WHERE package_id=? ORDER BY sort_order ASC, item_id ASC"); $stmtPrev->execute([(int)$pr['package_id']]); $preview = $stmtPrev->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $preview = []; }
                                ?>
                                <ul class="text-sm list-disc pl-5">
                                    <?php if (!empty($preview)): foreach ($preview as $it): ?>
                                        <li class="mb-1">
                                            <?php echo htmlspecialchars($it['item_label']); ?>
                                            <?php if ($it['qty'] !== null && $it['qty'] !== ''): ?>
                                                <span class="text-muted-foreground">(<?php echo (int)$it['qty']; ?> <?php echo htmlspecialchars($it['unit'] ?: ''); ?>)</span>
                                            <?php endif; ?>
                                            <?php if ((int)$it['is_optional']===1): ?><span class="ml-1 text-[10px] px-1 rounded bg-amber-50 border border-amber-300 text-amber-800">optional</span><?php endif; ?>
                                        </li>
                                    <?php endforeach; else: ?>
                                        <li class="text-muted-foreground">No items</li>
                                    <?php endif; ?>
                                </ul>
                                <div class="flex items-center justify-between gap-2 pt-2 mt-4 border-t pt-3">
                                    <button data-toggle-active="<?php echo (int)$pr['package_id']; ?>" class="px-3 py-1.5 rounded-lg text-sm border <?php echo ((int)$pr['is_active']===1) ? 'bg-emerald-50 border-emerald-300 text-emerald-800 hover:bg-emerald-100' : 'bg-gray-50 border-gray-300 text-gray-800 hover:bg-gray-100'; ?> transition-colors">
                                        <i class="fas fa-power-off me-1"></i><span class="toggle-label"><?php echo ((int)$pr['is_active']===1) ? 'Set Inactive' : 'Set Active'; ?></span>
                                    </button>
                                    <div class="flex items-center justify-end gap-2">
                                        <button data-edit-package="<?php echo (int)$pr['package_id']; ?>" class="px-3 py-1.5 border rounded-lg hover:bg-gray-50 text-sm transition-colors"><i class="fas fa-pen me-1"></i>Edit</button>
                                        <button data-delete-package="<?php echo (int)$pr['package_id']; ?>" class="px-3 py-1.5 border rounded-lg hover:bg-rose-50 text-sm text-rose-700 border-rose-300 transition-colors"><i class="fas fa-trash me-1"></i>Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; else: ?>
                            <div class="text-muted-foreground">No packages yet.</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($section === 'packages' && $pkgPages > 1): $pPrev=max(1,$pkgPage-1); $pNext=min($pkgPages,$pkgPage+1); ?>
                    <div class="flex items-center gap-2 mt-4">
                        <a class="px-2 py-1 rounded border border-gray-300 text-sm <?php echo $pkgPage<=1?'opacity-50 pointer-events-none':'hover:bg-gray-50'; ?>" href="?section=packages&pkg_page=<?php echo (int)$pPrev; ?>">Prev</a>
                        <?php for($i=1;$i<=$pkgPages;$i++): $cls=$i===$pkgPage?'bg-primary text-white border-primary':'hover:bg-gray-50 border'; ?>
                            <a class="px-2 py-1 rounded text-sm <?php echo $cls; ?>" href="?section=packages&pkg_page=<?php echo (int)$i; ?>"><?php echo (int)$i; ?></a>
                        <?php endfor; ?>
                        <a class="px-2 py-1 rounded border border-gray-300 text-sm <?php echo $pkgPage>=$pkgPages?'opacity-50 pointer-events-none':'hover:bg-gray-50'; ?>" href="?section=packages&pkg_page=<?php echo (int)$pNext; ?>">Next</a>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
    // Sidebar locked expanded
    let sidebarCollapsed = false;
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarIcon = document.getElementById('sidebar-icon');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');

        // Initialize from persisted preference (default: expanded)
        try {
            const sv = localStorage.getItem('sidebarCollapsed');
            sidebarCollapsed = (sv === 'true');
        } catch (_) { sidebarCollapsed = false; }
        if (sidebarCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('sidebar-expanded');
            sidebarTexts.forEach(text => text.style.display = 'none');
        } else {
            sidebar.classList.add('sidebar-expanded');
            sidebar.classList.remove('sidebar-collapsed');
            sidebarTexts.forEach(text => text.style.display = 'block');
        }
        if (sidebarIcon) sidebarIcon.className = 'fas fa-bars text-sm';
        if (sidebarToggle) { sidebarToggle.style.display='none'; }

        // Clicking empty space inside the sidebar toggles expand/collapse.
        // Ignore clicks on interactive elements (nav items, buttons, links, inputs).
        const setSidebarState = (collapsed) => {
            sidebarCollapsed = !!collapsed;
            if (sidebarCollapsed) {
                sidebar.classList.add('sidebar-collapsed');
                sidebar.classList.remove('sidebar-expanded');
                sidebarTexts.forEach(text => text.style.display = 'none');
            } else {
                sidebar.classList.add('sidebar-expanded');
                sidebar.classList.remove('sidebar-collapsed');
                sidebarTexts.forEach(text => text.style.display = 'block');
            }
            try { localStorage.setItem('sidebarCollapsed', sidebarCollapsed ? 'true' : 'false'); } catch(_) {}
        };
        // Initialize from stored preference if available
        try {
            const sv = localStorage.getItem('sidebarCollapsed');
            if (sv === 'true' || sv === 'false') setSidebarState(sv === 'true');
        } catch(_) {}
        sidebar.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            const interactive = e.target.closest('a,button,input,select,textarea,label');
            if (interactive) {
                // If the user clicked a navigation link, let it navigate without toggling
                return;
            }
            // Prevent toggling when clicking scrollbars
            if (e.clientX === 0 && e.clientY === 0) return;
            setSidebarState(!sidebarCollapsed);
        });

    // Initialize charts and dashboard widgets when page loads
    document.addEventListener('DOMContentLoaded', function() {
            // Admin user dropdown toggle
            (function(){
                const btn = document.getElementById('admin-user-button');
                const dd = document.getElementById('admin-user-dropdown');
                if (!btn || !dd) return;
                const close = () => { dd.classList.add('hidden'); btn.setAttribute('aria-expanded','false'); };
                const open = () => { dd.classList.remove('hidden'); btn.setAttribute('aria-expanded','true'); };
                let openState = false;
                btn.addEventListener('click', (e)=>{ e.stopPropagation(); openState ? close() : open(); openState = !openState; });
                document.addEventListener('click', (e)=>{
                    if (!dd.contains(e.target) && e.target !== btn && !btn.contains(e.target)) { close(); openState=false; }
                });
                document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') { close(); openState=false; } });
            })();

            const initialSection = '<?php echo htmlspecialchars($section); ?>';

            // Dashboard: Total Orders KPI + Best Sellers modal (non-intrusive)
            if (!initialSection || initialSection === 'dashboard') {
                const totalEl = document.getElementById('total-orders-count');
                const cardBtn = document.getElementById('card-total-orders');
                                let modal = document.getElementById('best-sellers-modal');
                                let closeBtn = document.getElementById('best-sellers-close');
                                let closeBtn2 = document.getElementById('best-sellers-close-2');
                                let rowsTbody = document.getElementById('best-sellers-rows');

                                function ensureBestSellersModal() {
                                        // If modal exists, nothing to do
                                        if (modal && rowsTbody) return true;
                                        // Create lightweight modal structure appended to body as a fallback
                                        const wrap = document.createElement('div');
                                        wrap.id = 'best-sellers-modal';
                                        wrap.className = 'fixed inset-0 z-50 items-center justify-center bg-black/50 hidden';
                                        wrap.innerHTML = (
                                                '<div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">' +
                                                    '<div class="p-4 border-b flex items-center justify-between">' +
                                                        '<h3 class="text-lg font-medium text-primary">Top 10 Best Sellers</h3>' +
                                                        '<button id="best-sellers-close" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>' +
                                                    '</div>' +
                                                    '<div class="p-4">' +
                                                        '<div id="best-sellers-body" class="overflow-x-auto">' +
                                                            '<table class="min-w-full text-sm">' +
                                                                '<thead class="bg-gray-50 text-gray-600">' +
                                                                    '<tr>' +
                                                                        '<th class="text-left px-4 py-2">Rank</th>' +
                                                                        '<th class="text-left px-4 py-2">Menu Item</th>' +
                                                                        '<th class="text-right px-4 py-2">Qty Sold</th>' +
                                                                    '</tr>' +
                                                                '</thead>' +
                                                                '<tbody id="best-sellers-rows" class="divide-y divide-gray-100">' +
                                                                    '<tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Loading…</td></tr>' +
                                                                '</tbody>' +
                                                            '</table>' +
                                                        '</div>' +
                                                    '</div>' +
                                                    '<div class="p-4 border-t text-right">' +
                                                        '<button id="best-sellers-close-2" class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50">Close</button>' +
                                                    '</div>' +
                                                '</div>'
                                        );
                                        try { document.body.appendChild(wrap); } catch(_) { return false; }
                                        // Re-acquire references
                                        modal = document.getElementById('best-sellers-modal');
                                        closeBtn = document.getElementById('best-sellers-close');
                                        closeBtn2 = document.getElementById('best-sellers-close-2');
                                        rowsTbody = document.getElementById('best-sellers-rows');
                                        // Wire close handlers for the dynamically added modal
                                        if (closeBtn) closeBtn.addEventListener('click', closeModal);
                                        if (closeBtn2) closeBtn2.addEventListener('click', closeModal);
                                        if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
                                        return !!(modal && rowsTbody);
                                }

                async function fetchTotalOrders() {
                    if (!totalEl) return;
                    try {
                        const res = await fetch('?section=dashboard&action=get_total_orders&ajax=1', { headers: { 'Accept': 'application/json', 'X-Requested-With':'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data && data.ok) {
                            totalEl.textContent = Number(data.totalOrders).toLocaleString('en-PH');
                        } else {
                            totalEl.textContent = '—';
                        }
                    } catch (_) {
                        totalEl.textContent = '—';
                    }
                }

                async function openBestSellers() {
                    if (!modal || !rowsTbody) {
                        if (!ensureBestSellersModal()) return;
                    }
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    try { modal.style.display = 'flex'; } catch(_) {}
                    if (rowsTbody) rowsTbody.innerHTML = '<tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Loading…</td></tr>';
                    try {
                        const res = await fetch('?section=dashboard&action=get_best_sellers&ajax=1', { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data && data.ok && Array.isArray(data.items) && data.items.length > 0) {
                            rowsTbody.innerHTML = data.items.map(item => `
                                <tr>
                                    <td class="px-4 py-2">${item.rank}</td>
                                    <td class="px-4 py-2">${item.name || '—'}</td>
                                    <td class="px-4 py-2 text-right">${Number(item.qty).toLocaleString('en-PH')}</td>
                                </tr>
                            `).join('');
                        } else {
                            if (rowsTbody) rowsTbody.innerHTML = '<tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No data available</td></tr>';
                        }
                    } catch (_) {
                        if (rowsTbody) rowsTbody.innerHTML = '<tr><td colspan="3" class="px-4 py-6 text-center text-red-600">Error loading data</td></tr>';
                    }
                }

                function closeModal() {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    try { modal.style.display = 'none'; } catch(_) {}
                }

                // Expose a safe global fallback for inline handler
                window.__openBestSellers = openBestSellers;

                fetchTotalOrders();
                setInterval(fetchTotalOrders, 15000);

                // Real-time Total Revenue (orders + catering; Paid or Partial)
                const revenueEl = document.getElementById('total-revenue-amount');
                async function fetchTotalRevenue(){
                    if (!revenueEl) return;
                    try {
                        const res = await fetch('?section=dashboard&action=get_total_revenue&ajax=1', { headers: { 'Accept': 'application/json', 'X-Requested-With':'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data && data.ok) {
                            const amt = Number(data.totalRevenue||0);
                            revenueEl.textContent = '₱' + amt.toLocaleString('en-PH', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
                        }
                    } catch(_) { /* ignore transient errors */ }
                }
                fetchTotalRevenue();
                setInterval(fetchTotalRevenue, 15000);
                if (cardBtn) {
                    cardBtn.addEventListener('click', function(e){ e.preventDefault(); openBestSellers(); });
                }
                // Delegated fallback in case direct listener doesn't attach for any reason
                document.addEventListener('click', function(e){
                    var trg = e.target.closest ? e.target.closest('#card-total-orders') : null;
                    if (trg) { e.preventDefault(); openBestSellers(); }
                });
                if (closeBtn) closeBtn.addEventListener('click', closeModal);
                if (closeBtn2) closeBtn2.addEventListener('click', closeModal);
                if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
            }

            // Bookings: handle Confirmed/Completed/Downpayment actions on card buttons
            if (initialSection === 'bookings') {
                const bookingsContainer = document.getElementById('bookings-content');
                bookingsContainer?.addEventListener('click', async (e) => {
                    const btnConfirm = e.target.closest('.bk-confirm');
                    const btnComplete = e.target.closest('.bk-complete');
                    const btnDownpay = e.target.closest('.bk-downpay');
                    const btnPaid = e.target.closest('.bk-paid');
                    if (btnDownpay) {
                        const id = btnDownpay.getAttribute('data-bk-id');
                        try {
                            const fd = new FormData();
                            fd.append('section','bookings');
                            fd.append('ajax','1');
                            fd.append('action','update');
                            fd.append('booking_id', id);
                            // Fetch current booking to provide required fields
                            const r0 = await fetch(`?section=bookings&action=get_booking&booking_id=${id}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
                            const j0 = await r0.json();
                            if (!j0.success) { alert(j0.message||'Failed to load booking'); return; }
                            const d = j0.data || {};
                            fd.append('eb_name', d.eb_name||'');
                            fd.append('eb_contact', d.eb_contact||'');
                            fd.append('eb_type', d.eb_type||'');
                            fd.append('eb_venue', d.eb_venue||'');
                            fd.append('eb_date', d.eb_date ? d.eb_date.replace(' ', 'T').slice(0,16) : '');
                            fd.append('eb_order', d.eb_order||'');
                            if (d.eb_package_pax!==null && d.eb_package_pax!==undefined) fd.append('eb_package_pax', d.eb_package_pax);
                            if (d.eb_addon_pax!==null && d.eb_addon_pax!==undefined) fd.append('eb_addon_pax', d.eb_addon_pax);
                            if (d.eb_notes!==null && d.eb_notes!==undefined) fd.append('eb_notes', d.eb_notes);
                            fd.append('eb_status', 'Downpayment');
                            const r = await fetch(`?section=bookings&action=update`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
                            const j = await r.json();
                            if (!j.success) { alert(j.message||'Failed to update'); return; }
                            // Update card chip in-place
                            const card = btnDownpay.closest('.card');
                            const chip = card ? card.querySelector('[data-bk-status]') : null;
                            if (chip) {
                                chip.textContent = 'Downpayment';
                                chip.className = '<?= booking_chip_base_classes(); ?> ' + 'bg-blue-50 border-blue-300 text-blue-800';
                            }
                        } catch (_) { alert('Network error'); }
                        return;
                    }
                    if (!btnConfirm && !btnComplete) return; // existing .bk-paid handled by modal below
                    const btn = btnConfirm || btnComplete;
                    const id = btn.getAttribute('data-bk-id');
                    const newStatus = btnConfirm ? 'Confirmed' : 'Completed';
                    try {
                        const fd = new FormData();
                        fd.append('section','bookings');
                        fd.append('ajax','1');
                        fd.append('action','update');
                        fd.append('booking_id', id);
                        // Minimal fields to update only status; backend requires many fields, so fetch first then submit
                        const r0 = await fetch(`?section=bookings&action=get_booking&booking_id=${id}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
                        const j0 = await r0.json();
                        if (!j0.success) { alert(j0.message||'Failed to load booking'); return; }
                        const d = j0.data || {};
                        fd.append('eb_name', d.eb_name||'');
                        fd.append('eb_contact', d.eb_contact||'');
                        fd.append('eb_type', d.eb_type||'');
                        fd.append('eb_venue', d.eb_venue||'');
                        fd.append('eb_date', d.eb_date ? d.eb_date.replace(' ', 'T').slice(0,16) : '');
                        fd.append('eb_order', d.eb_order||'');
                        if (d.eb_package_pax!==null && d.eb_package_pax!==undefined) fd.append('eb_package_pax', d.eb_package_pax);
                        if (d.eb_addon_pax!==null && d.eb_addon_pax!==undefined) fd.append('eb_addon_pax', d.eb_addon_pax);
                        if (d.eb_notes!==null && d.eb_notes!==undefined) fd.append('eb_notes', d.eb_notes);
                        fd.append('eb_status', newStatus);
                        const r = await fetch(`?section=bookings&action=update`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
                        const j = await r.json();
                        if (!j.success) { alert(j.message||'Failed to update'); return; }
                        // Update chip text and classes
                        const card = btn.closest('.card');
                        const chip = card?.querySelector('[data-bk-status]');
                        if (chip) {
                            chip.textContent = newStatus;
                            const ns = newStatus.toLowerCase();
                            chip.className = '<?= booking_chip_base_classes(); ?> ' + (ns==='completed' ? 'bg-blue-50 border-blue-300 text-blue-800' : (ns==='confirmed' || ns==='in progress' ? 'bg-amber-50 border-amber-300 text-amber-800' : 'bg-gray-50 border-gray-300 text-gray-800'));
                        }
                    } catch (_) { alert('Network error'); }
                });
            }
            // Packages: open modal to add/edit and handle delete
            if (initialSection === 'packages') {
                // Build simple modal dynamically to keep patch small
                const modalBack = document.createElement('div');
                modalBack.id = 'pkg-backdrop';
                modalBack.className = 'fixed inset-0 bg-black/30 z-40 hidden';
                const modal = document.createElement('div');
                modal.id = 'pkg-modal';
                modal.className = 'fixed inset-0 z-50 hidden items-center justify-center';
                const dialog = document.createElement('div');
                dialog.className = 'bg-white rounded-lg shadow-xl w-full max-w-2xl p-4';
                dialog.innerHTML = `
                    <div class="flex items-center justify-between mb-3">
                        <h3 id="pkg-title" class="text-lg font-semibold text-primary">Add Package</h3>
                        <button id="pkg-close" class="h-8 w-8 grid place-items-center rounded hover:bg-gray-50"><i class="fa fa-times"></i></button>
                    </div>
                    <form id="pkg-form" class="space-y-3" enctype="multipart/form-data">
                        <input type="hidden" name="section" value="packages"/>
                        <input type="hidden" name="action" value="create"/>
                        <input type="hidden" name="package_id" value=""/>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm text-muted-foreground">Name</label>
                                <input name="name" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg" required />
                            </div>
                            <div>
                                <label class="text-sm text-muted-foreground">Pax</label>
                                <select name="pax" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg" required>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="150">150</option>
                                    <option value="200">200</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-muted-foreground">Base Price (₱)</label>
                                <input type="number" step="0.01" min="0" name="base_price" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg" />
                            </div>
                            <div class="flex items-center gap-2 mt-7">
                                <!-- Hidden default 0 to ensure unchecked sends 0 -->
                                <input type="hidden" name="is_active" value="0" />
                                <input type="checkbox" name="is_active" value="1" checked />
                                <span class="text-sm">Active</span>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Notes</label>
                            <textarea name="notes" rows="2" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg"></textarea>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Image</label>
                            <div class="mt-1 grid grid-cols-3 gap-3 items-start">
                                <div class="col-span-1">
                                    <div class="aspect-square w-full rounded-lg border bg-gray-50 overflow-hidden flex items-center justify-center">
                                        <img id="pkg-photo-preview" alt="Preview" class="w-full h-full object-cover hidden" />
                                        <div id="pkg-photo-ph" class="text-xs text-gray-400">No image</div>
                                    </div>
                                </div>
                                <div class="col-span-2 space-y-2">
                                    <input type="file" id="pkg-photo" name="photo" accept="image/*" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-green-700" />
                                    <div class="flex gap-2">
                                        <button type="button" id="pkg-photo-clear" class="px-2 py-1 border rounded text-sm">Clear</button>
                                        <span class="text-xs text-muted-foreground">Supported: JPG, PNG, WEBP, AVIF, GIF</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Items</label>
                            <div id="pkg-items" class="space-y-2"></div>
                            <button type="button" id="pkg-add-item" class="mt-1 px-2 py-1 rounded border hover:bg-gray-50 text-sm"><i class="fa fa-plus mr-1"></i>Add Item</button>
                        </div>
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" id="pkg-cancel" class="px-3 py-2 rounded border">Cancel</button>
                            <button type="submit" class="px-3 py-2 rounded bg-primary text-white">Save</button>
                        </div>
                    </form>
                `;
                modal.appendChild(dialog);
                document.body.appendChild(modalBack);
                document.body.appendChild(modal);

                // Toggle scroll on items container when >= 5 rows are present
                const updateItemsScroll = () => {
                    const wrap = document.getElementById('pkg-items');
                    if (!wrap) return;
                    const count = wrap.children ? wrap.children.length : 0;
                    if (count >= 5) {
                        wrap.classList.add('max-h-60','overflow-y-auto','pr-1');
                    } else {
                        wrap.classList.remove('max-h-60','overflow-y-auto','pr-1');
                    }
                };

                const openModal = ()=>{ modalBack.classList.remove('hidden'); modal.classList.remove('hidden'); modal.classList.add('flex'); };
                const closeModal = ()=>{ modalBack.classList.add('hidden'); modal.classList.add('hidden'); modal.classList.remove('flex'); };
                document.getElementById('add-package-btn')?.addEventListener('click', ()=>{
                    const form = document.getElementById('pkg-form'); form.reset();
                    form.action.value='create'; form.package_id.value='';
                    document.getElementById('pkg-title').textContent='Add Package';
                    document.getElementById('pkg-items').innerHTML='';
                    setPreviewSrc('');
                    updateItemsScroll();
                    openModal();
                });
                document.getElementById('pkg-close')?.addEventListener('click', closeModal);
                document.getElementById('pkg-cancel')?.addEventListener('click', closeModal);
                // Image preview handlers
                const photoInput = () => document.getElementById('pkg-photo');
                const photoPreview = () => document.getElementById('pkg-photo-preview');
                const photoPh = () => document.getElementById('pkg-photo-ph');
                const setPreviewSrc = (src) => {
                    const img = photoPreview(); const ph = photoPh();
                    if (!img || !ph) return;
                    if (src) {
                        img.src = src; img.classList.remove('hidden'); ph.classList.add('hidden');
                    } else {
                        img.src = ''; img.classList.add('hidden'); ph.classList.remove('hidden');
                    }
                };
                photoInput()?.addEventListener('change', (e) => {
                    const f = e.target.files && e.target.files[0];
                    if (!f) { setPreviewSrc(''); return; }
                    const reader = new FileReader();
                    reader.onload = (ev) => setPreviewSrc(ev.target.result);
                    reader.readAsDataURL(f);
                });
                document.getElementById('pkg-photo-clear')?.addEventListener('click', () => {
                    const inp = photoInput(); if (inp) { inp.value = ''; }
                    setPreviewSrc('');
                });
                document.getElementById('pkg-add-item')?.addEventListener('click', ()=>{
                    const wrap = document.getElementById('pkg-items');
                    const row = document.createElement('div');
                    row.className='grid grid-cols-12 gap-2';
                    row.innerHTML = `
                        <input name="item_label[]" class="col-span-6 px-2 py-1 bg-gray-50 border rounded" placeholder="Item label" required />
                        <input name="qty[]" type="number" min="0" class="col-span-2 px-2 py-1 bg-gray-50 border rounded" placeholder="Qty" />
                        <select name="unit[]" class="col-span-2 px-2 py-1 bg-gray-50 border rounded">
                            <option value="other">unit</option>
                            <option value="pax">pax</option>
                            <option value="pcs">pcs</option>
                            <option value="cups">cups</option>
                            <option value="attendants">attendants</option>
                            <option value="dish">dish</option>
                        </select>
                        <label class="col-span-1 inline-flex items-center gap-1 text-xs"><input type="checkbox" name="is_optional[]" value="1"/> optional</label>
                        <input name="sort_order[]" type="number" class="col-span-1 px-2 py-1 bg-gray-50 border rounded" placeholder="#" />
                    `;
                    wrap.appendChild(row);
                    updateItemsScroll();
                });
                // Event delegation for toggle/edit/delete so dynamically added cards work
                document.getElementById('packages-content')?.addEventListener('click', async (ev)=>{
                    const tglBtn = ev.target.closest('[data-toggle-active]');
                    if (tglBtn) {
                        const id = tglBtn.getAttribute('data-toggle-active');
                        try {
                            const r = await fetch(`?section=packages&action=toggle_active&package_id=${id}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
                            const j = await r.json(); if (!j.success) return alert(j.message||'Toggle failed');
                            const card = tglBtn.closest('[data-package-card]');
                            const isActive = String(j.is_active) === '1';
                            // Update badge
                            const chip = card?.querySelector('.status-badge');
                            if (chip) {
                                chip.textContent = isActive ? 'Active' : 'Inactive';
                                chip.classList.remove('bg-emerald-50','border-emerald-300','text-emerald-800','bg-gray-50','border-gray-300','text-gray-800');
                                if (isActive) chip.classList.add('bg-emerald-50','border-emerald-300','text-emerald-800');
                                else chip.classList.add('bg-gray-50','border-gray-300','text-gray-800');
                            }
                            // Update button styles/label
                            const lbl = tglBtn.querySelector('.toggle-label');
                            if (lbl) lbl.textContent = isActive ? 'Set Inactive' : 'Set Active';
                            tglBtn.classList.remove('bg-emerald-50','border-emerald-300','text-emerald-800','hover:bg-emerald-100','bg-gray-50','border-gray-300','text-gray-800','hover:bg-gray-100');
                            if (isActive) tglBtn.classList.add('bg-emerald-50','border-emerald-300','text-emerald-800','hover:bg-emerald-100');
                            else tglBtn.classList.add('bg-gray-50','border-gray-300','text-gray-800','hover:bg-gray-100');
                        } catch (_) { alert('Network error'); }
                        return;
                    }
                    const editBtn = ev.target.closest('[data-edit-package]');
                    if (editBtn) {
                        const id = editBtn.getAttribute('data-edit-package');
                        // Preview current image from the card
                        try {
                            const card = editBtn.closest('[data-package-card]');
                            const img = card ? card.querySelector('img') : null;
                            if (img && img.getAttribute('src')) setPreviewSrc(img.getAttribute('src'));
                        } catch(_) {}
                        try {
                            const r = await fetch(`?section=packages&action=get_package&package_id=${id}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
                            const j = await r.json(); if (!j.success) return alert(j.message||'Failed');
                            const form = document.getElementById('pkg-form');
                            form.action.value='update'; form.package_id.value = id;
                            // clear file input to allow re-uploading same file name
                            const finp = photoInput(); if (finp) finp.value = '';
                            document.getElementById('pkg-title').textContent='Edit Package';
                            form.name.value = j.data.name||'';
                            form.pax.value = j.data.pax||'50';
                            form.base_price.value = j.data.base_price||'';
                            form.is_active.checked = String(j.data.is_active) === '1';
                            form.notes.value = j.data.notes||'';
                            const wrap = document.getElementById('pkg-items'); wrap.innerHTML='';
                            (j.items||[]).forEach(it=>{
                                const row = document.createElement('div'); row.className='grid grid-cols-12 gap-2';
                                row.innerHTML = `
                                    <input name="item_label[]" class="col-span-6 px-2 py-1 bg-gray-50 border rounded" placeholder="Item label" required value="${it.item_label?.replace(/"/g,'&quot;')||''}" />
                                    <input name="qty[]" type="number" min="0" class="col-span-2 px-2 py-1 bg-gray-50 border rounded" placeholder="Qty" value="${it.qty??''}" />
                                    <select name="unit[]" class="col-span-2 px-2 py-1 bg-gray-50 border rounded">
                                        <option value="other" ${it.unit==='other'?'selected':''}>unit</option>
                                        <option value="pax" ${it.unit==='pax'?'selected':''}>pax</option>
                                        <option value="pcs" ${it.unit==='pcs'?'selected':''}>pcs</option>
                                        <option value="cups" ${it.unit==='cups'?'selected':''}>cups</option>
                                        <option value="attendants" ${it.unit==='attendants'?'selected':''}>attendants</option>
                                        <option value="dish" ${it.unit==='dish'?'selected':''}>dish</option>
                                    </select>
                                    <label class="col-span-1 inline-flex items-center gap-1 text-xs"><input type="checkbox" name="is_optional[]" value="1" ${String(it.is_optional)==='1'?'checked':''}/> optional</label>
                                    <input name="sort_order[]" type="number" class="col-span-1 px-2 py-1 bg-gray-50 border rounded" placeholder="#" value="${it.sort_order??''}" />
                                `; wrap.appendChild(row);
                            });
                            updateItemsScroll();
                            openModal();
                        } catch (_) { alert('Network error'); }
                        return;
                    }
                    const delBtn = ev.target.closest('[data-delete-package]');
                    if (delBtn) {
                        const id = delBtn.getAttribute('data-delete-package');
                        if (!confirm('Delete this package?')) return;
                        try {
                            const r = await fetch(`?section=packages&action=delete&package_id=${id}&ajax=1`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
                            const j = await r.json();
                            if (!j.success) return alert('Delete failed');
                            const card = delBtn.closest('[data-package-card]');
                            card?.parentElement?.removeChild(card);
                        } catch (_) { alert('Delete failed'); }
                    }
                });
                document.getElementById('pkg-form')?.addEventListener('submit', async (e)=>{
                    e.preventDefault();
                    const form = e.target;
                    const fd = new FormData(form);
                    const readFormItems = () => {
                        const wrap = document.getElementById('pkg-items');
                        const rows = wrap ? Array.from(wrap.children) : [];
                        const items = [];
                        rows.forEach(row => {
                            const lbl = row.querySelector('input[name="item_label[]"]')?.value?.trim() || '';
                            const qtyVal = row.querySelector('input[name="qty[]"]')?.value || '';
                            const unit = row.querySelector('select[name="unit[]"]')?.value || '';
                            const opt = row.querySelector('input[name="is_optional[]"]')?.checked ? 1 : 0;
                            if (lbl) items.push({ label: lbl, qty: qtyVal, unit, optional: opt });
                        });
                        return items;
                    };
                    const updateCardFromForm = (card, imageUrl) => {
                        if (!card) return;
                        const name = form.name.value || '';
                        const pax = form.pax.value || '';
                        const price = form.base_price.value || '';
                        const active = form.is_active.checked;
                        // Texts
                        const nameEl = card.querySelector('.font-semibold.text-primary');
                        if (nameEl) nameEl.textContent = name;
                        const metaEl = card.querySelector('.text-sm.text-muted-foreground');
                        if (metaEl) metaEl.textContent = `Pax: ${pax}${price!==''?` • ₱${Number(price).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}`:''}`;
                        // Active chip
                        const chip = card.querySelector('.status-badge');
                        if (chip) {
                            chip.textContent = active ? 'Active' : 'Inactive';
                            chip.classList.remove('bg-emerald-50','border-emerald-300','text-emerald-800','bg-gray-50','border-gray-300','text-gray-800');
                            if (active) chip.classList.add('bg-emerald-50','border-emerald-300','text-emerald-800');
                            else chip.classList.add('bg-gray-50','border-gray-300','text-gray-800');
                        }
                        // Toggle button sync
                        const tbtn = card.querySelector('[data-toggle-active]');
                        if (tbtn) {
                            const lbl = tbtn.querySelector('.toggle-label');
                            if (lbl) lbl.textContent = active ? 'Set Inactive' : 'Set Active';
                            tbtn.classList.remove('bg-emerald-50','border-emerald-300','text-emerald-800','hover:bg-emerald-100','bg-gray-50','border-gray-300','text-gray-800','hover:bg-gray-100');
                            if (active) tbtn.classList.add('bg-emerald-50','border-emerald-300','text-emerald-800','hover:bg-emerald-100');
                            else tbtn.classList.add('bg-gray-50','border-gray-300','text-gray-800','hover:bg-gray-100');
                        }
                        // Image
                        if (imageUrl) {
                            const img = card.querySelector('img');
                            if (img) img.src = imageUrl;
                        }
                        // Items preview (show all)
                        const items = readFormItems();
                        const ul = card.querySelector('ul');
                        if (ul) {
                            ul.innerHTML = '';
                            if (items.length === 0) {
                                ul.innerHTML = '<li class="text-muted-foreground">No items</li>';
                            } else {
                                items.forEach(it=>{
                                    const li = document.createElement('li');
                                    li.className = 'mb-1';
                                    li.innerHTML = `${it.label.replace(/&/g,'&amp;').replace(/</g,'&lt;')} ${it.qty?`<span class=\"text-muted-foreground\">(${Number(it.qty)} ${it.unit||''})</span>`:''} ${it.optional?`<span class=\"ml-1 text-[10px] px-1 rounded bg-amber-50 border border-amber-300 text-amber-800">optional</span>`:''}`;
                                    ul.appendChild(li);
                                });
                            }
                        }
                    };
                    const appendNewCard = (pkgId, imageUrl) => {
                        const grid = document.querySelector('#packages-content .grid');
                        if (!grid) return;
                        const name = form.name.value || '';
                        const pax = form.pax.value || '';
                        const price = form.base_price.value || '';
                        const active = form.is_active.checked;
                        const items = readFormItems();
                        const priceHtml = price!=='' ? ` • ₱${Number(price).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}` : '';
                        const chipCls = active ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-gray-50 border-gray-300 text-gray-800';
                        const imgSrc = imageUrl || '../images/logo.png';
                        const itemsHtml = items.length
                            ? items.map(it=>`<li class="mb-1">${(it.label||'').replace(/&/g,'&amp;').replace(/</g,'&lt;')}${it.qty?` <span class=\"text-muted-foreground\">(${Number(it.qty)} ${it.unit||''})</span>`:''}${it.optional?`<span class=\"ml-1 text-[10px] px-1 rounded bg-amber-50 border border-amber-300 text-amber-800\">optional</span>`:''}</li>`).join('')
                            : '<li class="text-muted-foreground">No items</li>';
                        const html = `
                        <div data-package-card class="group relative rounded-2xl border border-gray-200 bg-white/90 backdrop-blur overflow-hidden shadow-sm transition-all duration-200 hover:shadow-xl hover:-translate-y-1 flex flex-col">
                            <div class="relative h-56 w-full bg-gray-100 overflow-hidden">
                                <img src="${imgSrc}" alt="Package Image" class="w-full h-full object-cover transform transition-transform duration-300 group-hover:scale-105"/>
                                <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/30 to-transparent pointer-events-none"></div>
                            </div>
                            <div class="p-5 space-y-3 flex-1">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-semibold text-primary text-base"></div>
                                        <div class="text-sm text-muted-foreground"></div>
                                    </div>
                                    <span class="status-badge inline-block px-2 py-0.5 rounded-full text-[11px] border ${chipCls} group-hover:shadow-sm">${active?'Active':'Inactive'}</span>
                                </div>
                                <ul class="text-sm list-disc pl-5">${itemsHtml}</ul>
                                <div class="flex items-center justify-between gap-2 pt-2 mt-4 border-t pt-3">
                                    <button data-toggle-active="${pkgId}" class="px-3 py-1.5 rounded-lg text-sm border ${active?'bg-emerald-50 border-emerald-300 text-emerald-800 hover:bg-emerald-100':'bg-gray-50 border-gray-300 text-gray-800 hover:bg-gray-100'} transition-colors">
                                        <i class="fas fa-power-off me-1"></i><span class="toggle-label">${active?'Set Inactive':'Set Active'}</span>
                                    </button>
                                    <div class="flex items-center justify-end gap-2">
                                        <button data-edit-package="${pkgId}" class="px-3 py-1.5 border rounded-lg hover:bg-gray-50 text-sm transition-colors"><i class="fas fa-pen me-1"></i>Edit</button>
                                        <button data-delete-package="${pkgId}" class="px-3 py-1.5 border rounded-lg hover:bg-rose-50 text-sm text-rose-700 border-rose-300 transition-colors"><i class="fas fa-trash me-1"></i>Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                        grid.insertAdjacentHTML('afterbegin', html);
                        const card = grid.firstElementChild;
                        // Fill name and meta after insert to avoid escaping complexity
                        const nameEl = card.querySelector('.font-semibold.text-primary');
                        if (nameEl) nameEl.textContent = name;
                        const metaEl = card.querySelector('.text-sm.text-muted-foreground');
                        if (metaEl) metaEl.textContent = `Pax: ${pax}${priceHtml}`;
                    };
                    try {
                        const r = await fetch('?section=packages', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
                        const j = await r.json(); if (!j.success) { alert(j.message||'Save failed'); return; }
                        const isUpdate = (form.action && form.action.value === 'update') || fd.get('action') === 'update';
                        const pkgId = (form.package_id && form.package_id.value) ? form.package_id.value : (j.package_id||'');
                        if (isUpdate) {
                            const card = document.querySelector(`[data-edit-package="${pkgId}"]`)?.closest('[data-package-card]');
                            updateCardFromForm(card, j.image_url||null);
                        } else {
                            appendNewCard(pkgId, j.image_url||null);
                        }
                        closeModal();
                    } catch (_) { alert('Network error'); }
                });
            }
            // Sections visibility already handled server-side via PHP classes above

            // Let navigation links work normally to avoid section switching glitches

            // Real-time filtering in Products
            if (initialSection === 'products') {
                const form = document.getElementById('products-filter');
                const q = document.getElementById('filter-q');
                const cat = document.getElementById('filter-category');
                const pax = document.getElementById('filter-pax');
                const avail = document.getElementById('filter-avail');
                const sort = document.getElementById('filter-sort');
                const clearBtn = document.getElementById('products-clear');
                const container = document.getElementById('products-results');
                let seq = 0;
                let controller = null;

                const submitNow = () => {
                    // Reset to page 1 on any filter change
                    const params = new URLSearchParams(new FormData(form));
                    params.set('page', '1');
                    // Fetch only the products section HTML to avoid page flicker
                    const url = '?' + params.toString();
                    if (!container) { window.location.href = url; return; }
                    const mySeq = ++seq;
                    container.style.opacity = '0.6';
                    try { controller && controller.abort(); } catch {}
                    controller = new AbortController();
                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                        .then(r => r.text())
                        .then(html => {
                            if (mySeq !== seq) return;
                            const temp = document.createElement('div');
                            temp.innerHTML = html;
                            const updated = temp.querySelector('#products-results');
                            if (updated) {
                                container.innerHTML = updated.innerHTML;
                            } else {
                                window.location.href = url;
                            }
                        })
                        .catch((err) => { if (mySeq === seq && (!err || err.name !== 'AbortError')) window.location.href = url; })
                        .finally(() => { if (mySeq === seq) container.style.opacity = '1'; });
                };
                // Debounce for search input
                let t = null;
                q && q.addEventListener('input', () => {
                    clearTimeout(t);
                    t = setTimeout(submitNow, 300);
                });
                // Immediate submit on select changes
                [cat, pax, avail, sort].forEach(el => el && el.addEventListener('change', submitNow));

                // Intercept pagination clicks to update smoothly without full page reload
                if (container) {
                    container.addEventListener('click', (e) => {
                        // Intercept action buttons (toggle/delete)
                        const btn = e.target.closest('.js-action');
                        if (btn) {
                            e.preventDefault();
                            const act = btn.getAttribute('data-action');
                            const id = btn.getAttribute('data-menu-id');
                            if (!act || !id) return;
                            if (act === 'toggle') {
                                if (!confirm('Toggle availability for this item?')) return;
                            } else if (act === 'delete') {
                                if (!confirm('Delete this item? This action cannot be undone.')) return;
                            }
                            const formData = new FormData();
                            formData.append('section', 'products');
                            formData.append('ajax', '1');
                            formData.append('action', act);
                            formData.append('menu_id', id);
                            const postUrl = '?section=products';
                            const mySeq = ++seq;
                            container.style.opacity = '0.6';
                            try { controller && controller.abort(); } catch {}
                            controller = new AbortController();
                            fetch(postUrl, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                                .then(r => r.json())
                                .then(data => {
                                    if (mySeq !== seq) return;
                                    // After success, refresh the list keeping current filters and page
                                    const params = new URLSearchParams(new FormData(form));
                                    const url = '?' + params.toString();
                                    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                                        .then(r => r.text())
                                        .then(html => {
                                            if (mySeq !== seq) return;
                                            const temp = document.createElement('div');
                                            temp.innerHTML = html;
                                            const updated = temp.querySelector('#products-results');
                                            if (updated) {
                                                container.innerHTML = updated.innerHTML;
                                            } else {
                                                window.location.href = url;
                                            }
                                        });
                                })
                                .catch(err => { if (mySeq === seq && (!err || err.name !== 'AbortError')) alert('Request failed. Please retry.'); })
                                .finally(() => { if (mySeq === seq) container.style.opacity = '1'; });
                            return;
                        }
                        const a = e.target.closest('a');
                        if (!a) return;
                        const href = a.getAttribute('href') || '';
                        if (href.includes('section=products') && (href.includes('page=') || href.includes('sort='))) {
                            e.preventDefault();
                            const url = a.href;
                            const mySeq = ++seq;
                            container.style.opacity = '0.6';
                            try { controller && controller.abort(); } catch {}
                            controller = new AbortController();
                            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                                .then(r => r.text())
                                .then(html => {
                                    if (mySeq !== seq) return;
                                    const temp = document.createElement('div');
                                    temp.innerHTML = html;
                                    const updated = temp.querySelector('#products-results');
                                    if (updated) {
                                        container.innerHTML = updated.innerHTML;
                                    } else {
                                        window.location.href = url;
                                    }
                                })
                                .catch((err) => { if (mySeq === seq && (!err || err.name !== 'AbortError')) window.location.href = url; })
                                .finally(() => { if (mySeq === seq) container.style.opacity = '1'; });
                        }
                    });
                }

                // Clear filters
                clearBtn && clearBtn.addEventListener('click', () => {
                    if (!form) return;
                    q && (q.value = '');
                    cat && (cat.value = '');
                    pax && (pax.value = '');
                    avail && (avail.value = '');
                    sort && (sort.value = '');
                    submitNow();
                });
            }

            // Real-time filtering in Orders
            if (initialSection === 'orders') {
                const form = document.getElementById('orders-filter');
                const container = document.getElementById('orders-table');
                const q = document.getElementById('orders-q');
                const status = document.getElementById('orders-status');
                const paym = document.getElementById('orders-paymethod');
                const odate = document.getElementById('orders-ordered-date');
                const ndate = document.getElementById('orders-needed-date');
                const clearBtnO = document.getElementById('orders-clear');
                let seqO = 0;
                let controllerO = null;

                const refresh = () => {
                    const params = new URLSearchParams(new FormData(form));
                    params.set('page', '1');
                    const url = '?' + params.toString();
                    if (!container) { window.location.href = url; return; }
                    const mySeq = ++seqO;
                    container.style.opacity = '0.6';
                    try { controllerO && controllerO.abort(); } catch {}
                    controllerO = new AbortController();
                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controllerO.signal })
                        .then(r => r.text())
                        .then(html => {
                            if (mySeq !== seqO) return;
                            const temp = document.createElement('div');
                            temp.innerHTML = html;
                            const updated = temp.querySelector('#orders-table');
                            if (updated) container.innerHTML = updated.innerHTML; else window.location.href = url;
                        })
                        .catch(err => { if (mySeq === seqO && (!err || err.name !== 'AbortError')) window.location.href = url; })
                        .finally(() => { if (mySeq === seqO) container.style.opacity = '1'; });
                };

                // Debounce search
                let tO = null;
                q && q.addEventListener('input', () => { clearTimeout(tO); tO = setTimeout(refresh, 300); });
                [status, paym, odate, ndate].forEach(el => el && el.addEventListener('change', refresh));

                // Intercept pagination in orders table
                container && container.addEventListener('click', (e) => {
                    const a = e.target.closest('a');
                    if (!a) return;
                    const href = a.getAttribute('href') || '';
                    if (href.includes('section=orders') && href.includes('page=')) {
                        e.preventDefault();
                        const url = a.href;
                        const mySeq = ++seqO;
                        container.style.opacity = '0.6';
                        try { controllerO && controllerO.abort(); } catch {}
                        controllerO = new AbortController();
                        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controllerO.signal })
                            .then(r => r.text())
                            .then(html => {
                                if (mySeq !== seqO) return;
                                const temp = document.createElement('div');
                                temp.innerHTML = html;
                                const updated = temp.querySelector('#orders-table');
                                if (updated) container.innerHTML = updated.innerHTML; else window.location.href = url;
                            })
                            .catch(err => { if (mySeq === seqO && (!err || err.name !== 'AbortError')) window.location.href = url; })
                            .finally(() => { if (mySeq === seqO) container.style.opacity = '1'; });
                    }
                });

                // Clear orders filters
                clearBtnO && clearBtnO.addEventListener('click', () => {
                    if (!form) return;
                    q && (q.value = '');
                    status && (status.value = '');
                    paym && (paym.value = '');
                    odate && (odate.value = '');
                    ndate && (ndate.value = '');
                    refresh();
                });
            }

            // Employees: filtering + CRUD
            if (initialSection === 'employees') {
                const eForm = document.getElementById('employees-filter');
                const eQ = document.getElementById('emp-q');
                const eRole = document.getElementById('emp-role');
                const eSex = document.getElementById('emp-sex');
                const eAvail = document.getElementById('emp-avail');
                const eClear = document.getElementById('employees-clear');
                const eTable = document.getElementById('employees-table');
                const eList = document.getElementById('employees-list');
                const openAddEmp = document.getElementById('open-add-employee');

                // Role chip styling helpers
                function baseRoleChipClasses(){ return 'inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full border'; }
                function roleChipInfo(role){
                    const r=(role||'').toString().trim();
                    const n=r.toLowerCase();
                    const map=[
                        {k:['manager','lead','supervisor'], cls:'bg-emerald-50 border-emerald-300 text-emerald-800'},
                        {k:['chef','head cook','cook i'], cls:'bg-amber-50 border-amber-300 text-amber-800'},
                        {k:['cook','kitchen'], cls:'bg-orange-50 border-orange-300 text-orange-800'},
                        {k:['server','waiter','waitress'], cls:'bg-sky-50 border-sky-300 text-sky-800'},
                        {k:['cashier'], cls:'bg-indigo-50 border-indigo-300 text-indigo-800'},
                        {k:['driver','delivery'], cls:'bg-slate-50 border-slate-300 text-slate-800'},
                        {k:['cleaner','utility','dishwasher'], cls:'bg-stone-50 border-stone-300 text-stone-800'},
                        {k:['bartender','bar'], cls:'bg-purple-50 border-purple-300 text-purple-800'},
                        {k:['planner','coordinator'], cls:'bg-pink-50 border-pink-300 text-pink-800'}
                    ];
                    for (const m of map){ if (m.k.some(s=>n.includes(s))) return {classes: baseRoleChipClasses()+' '+m.cls, style:''}; }
                    // Deterministic fallback by role string
                    let h=0; for (let i=0;i<n.length;i++){ h=(h*31+n.charCodeAt(i))>>>0; } h=h%360;
                    const bg=`hsla(${h},85%,96%,1)`; const bd=`hsla(${h},55%,70%,1)`; const tx=`hsla(${h},40%,26%,1)`;
                    return { classes: baseRoleChipClasses(), style:`background-color:${bg}; border-color:${bd}; color:${tx}` };
                }
                function styleEmployeeRoleChips(){
                    const tbody = document.getElementById('employees-table');
                    if (!tbody) return;
                    tbody.querySelectorAll('tr').forEach(tr=>{
                        const tds = tr.querySelectorAll('td');
                        if (tds.length < 6) return; // Role expected at index 5
                        const cell = tds[5];
                        if (!cell) return;
                        if (cell.querySelector('.role-chip')) return; // idempotent
                        const txt = (cell.textContent||'').trim(); if (!txt) return;
                        cell.textContent = '';
                        const span = document.createElement('span');
                        const info = roleChipInfo(txt);
                        span.className = 'role-chip ' + info.classes; if (info.style) span.setAttribute('style', info.style);
                        span.textContent = txt; cell.appendChild(span);
                    });
                }

                // Guard against race conditions on rapid changes
                let empSeq = 0;
                let empController = null;
                const empRefresh = () => {
                    const params = new URLSearchParams(new FormData(eForm));
                    params.set('section','employees');
                    params.set('emp_page','1');
                    const url = '?' + params.toString();
                    const wrap = document.getElementById('employees-list');
                    wrap && (wrap.style.opacity='0.6');
                    const mySeq = ++empSeq;
                    try { empController && empController.abort(); } catch {}
                    empController = new AbortController();
                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: empController.signal })
                      .then(r=>r.text())
                      .then(html=>{
                          if (mySeq !== empSeq) return; // stale
                          const tmp=document.createElement('div'); tmp.innerHTML=html;
                          const newList=tmp.querySelector('#employees-list');
                          const current=document.getElementById('employees-list');
                          if (newList && current) current.replaceWith(newList); else if (!newList) window.location.href = url;
                      })
                      .catch(err=>{ if (mySeq===empSeq && (!err || err.name !== 'AbortError')) { /* fallback to full load */ window.location.href = url; } })
                      .finally(()=>{
                          if (mySeq !== empSeq) return;
                          const nl=document.getElementById('employees-list');
                          if (nl) nl.style.opacity='1';
                          styleEmployeeRoleChips();
                          attachEmpHandlers();
                      });
                };
                let te=null; eQ && eQ.addEventListener('input', ()=>{ clearTimeout(te); te=setTimeout(empRefresh, 300); });
                [eRole,eSex,eAvail].forEach(el=> el && el.addEventListener('change', empRefresh));
                // No Apply button needed; prevent form submission
                eForm && eForm.addEventListener('submit', (e)=>{ e.preventDefault(); });
                eClear && eClear.addEventListener('click', ()=>{ eQ&&(eQ.value=''); eRole&&(eRole.value=''); eSex&&(eSex.value=''); eAvail&&(eAvail.value=''); empRefresh(); });

                // Initial pass on first render
                styleEmployeeRoleChips();

                // Modal
                const empBack = document.getElementById('emp-backdrop');
                const empModal = document.getElementById('emp-modal');
                const empDialog = empModal ? empModal.querySelector('.dialog') : null;
                const empTitle = document.getElementById('emp-modal-title');
                const empForm = document.getElementById('emp-form');
                const empAction = document.getElementById('emp-action');
                const empId = document.getElementById('emp-id');
                const ef = { fn: document.getElementById('emp-fn'), ln: document.getElementById('emp-ln'), sex: document.getElementById('emp-sex-input'), email: document.getElementById('emp-email'), phone: document.getElementById('emp-phone'), role: document.getElementById('emp-role-input'), avail: document.getElementById('emp-avail-input'), photo: document.getElementById('emp-photo'), preview: document.getElementById('emp-photo-preview'), clear: document.getElementById('emp-photo-clear') };
                function showEmpModal(edit=false){ if(!empBack||!empModal) return; empBack.style.display='block'; empModal.style.display='flex'; empBack.setAttribute('aria-hidden','false'); empModal.setAttribute('aria-hidden','false'); empBack.classList.remove('pointer-events-none'); empModal.classList.remove('pointer-events-none'); requestAnimationFrame(()=>{ empBack.style.opacity='1'; empModal.style.opacity='1'; if(empDialog) empDialog.style.transform='scale(1)'; }); }
                function hideEmpModal(){ if(!empBack||!empModal) return; empBack.style.opacity='0'; empModal.style.opacity='0'; if(empDialog) empDialog.style.transform='scale(0.95)'; setTimeout(()=>{ empBack.classList.add('pointer-events-none'); empModal.classList.add('pointer-events-none'); empBack.style.display='none'; empModal.style.display='none'; empBack.setAttribute('aria-hidden','true'); empModal.setAttribute('aria-hidden','true'); empForm && empForm.reset(); empId.value=''; ef.preview && (ef.preview.src=''); ef.preview && ef.preview.classList.add('hidden'); },180); }
                document.getElementById('emp-close')?.addEventListener('click', hideEmpModal);
                document.getElementById('emp-cancel')?.addEventListener('click', hideEmpModal);
                empBack && empBack.addEventListener('click', hideEmpModal);
                openAddEmp && openAddEmp.addEventListener('click', ()=>{ empTitle.textContent='Add Employee'; empAction.value='create'; empId.value=''; showEmpModal(false); });
                // Numeric-only and max 11 for phone in modal
                ef.phone && ef.phone.addEventListener('input', ()=>{ ef.phone.value = (ef.phone.value||'').replace(/\D+/g,'').slice(0,11); });
                ef.photo && ef.photo.addEventListener('change', ()=>{ const f=ef.photo.files?.[0]; if(!f) return; const url=URL.createObjectURL(f); ef.preview.src=url; ef.preview.classList.remove('hidden'); });
                ef.clear && ef.clear.addEventListener('click', ()=>{ if (!ef.photo) return; ef.photo.value=''; ef.preview.src=''; ef.preview.classList.add('hidden'); });

                function attachEmpHandlers(){
                    const table = document.getElementById('employees-table');
                    if (table) table.addEventListener('click', async (e) => {
                        const editBtn = e.target.closest('.emp-edit');
                        const togBtn = e.target.closest('.emp-toggle');
                        const delBtn = e.target.closest('.emp-delete');
                        if (editBtn){
                            const id = editBtn.getAttribute('data-emp-id');
                            try { const r = await fetch(`?section=employees&action=get_employee&employee_id=${id}`, { headers:{'X-Requested-With':'XMLHttpRequest'} }); const j = await r.json(); if(!j.success) return alert(j.message||'Failed'); const d = j.data; empTitle.textContent='Edit Employee'; empAction.value='update'; empId.value=d.emp_id; ef.fn.value=d.emp_fn||''; ef.ln.value=d.emp_ln||''; ef.sex.value=d.emp_sex||''; ef.email.value=d.emp_email||''; ef.phone.value=d.emp_phone||''; ef.role.value=d.emp_role||''; ef.avail.checked = String(d.emp_avail)==='1'; if (d.emp_photo){ ef.preview.src=d.emp_photo; ef.preview.classList.remove('hidden'); } else { ef.preview.src=''; ef.preview.classList.add('hidden'); } showEmpModal(true); } catch (_) { alert('Network error'); }
                            return;
                        }
                        if (togBtn){
                            const id = togBtn.getAttribute('data-emp-id');
                            await fetch(`?section=employees&action=toggle&employee_id=${id}&ajax=1`,{ headers:{'X-Requested-With':'XMLHttpRequest'} });
                            empRefresh();
                            return;
                        }
                        if (delBtn){
                            const id = delBtn.getAttribute('data-emp-id');
                            if (!confirm('Delete this employee?')) return; await fetch(`?section=employees&action=delete&employee_id=${id}&ajax=1`,{ headers:{'X-Requested-With':'XMLHttpRequest'} }); empRefresh(); return;
                        }
                    });
                    const listWrap = document.getElementById('employees-list');
                    if (listWrap) listWrap.addEventListener('click', (e)=>{
                        const a = e.target.closest('a');
                        if (!a) return;
                        const href = a.getAttribute('href')||'';
                        if (href.includes('section=employees') && href.includes('emp_page=')){
                            e.preventDefault();
                            const url = a.href;
                            const wrap = document.getElementById('employees-list');
                            wrap && (wrap.style.opacity='0.6');
                            const mySeq = ++empSeq;
                            try { empController && empController.abort(); } catch {}
                            empController = new AbortController();
                            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: empController.signal })
                                .then(r=>r.text())
                                .then(html=>{ if (mySeq !== empSeq) return; const tmp=document.createElement('div'); tmp.innerHTML=html; const newList=tmp.querySelector('#employees-list'); const current=document.getElementById('employees-list'); if (newList && current) current.replaceWith(newList); else if (!newList) window.location.href = url; })
                                .catch(err=>{ if (mySeq===empSeq && (!err || err.name !== 'AbortError')) window.location.href = url; })
                                .finally(()=>{ if (mySeq !== empSeq) return; const nl=document.getElementById('employees-list'); if (nl) nl.style.opacity='1'; styleEmployeeRoleChips(); attachEmpHandlers(); });
                        }
                    });
                }
                attachEmpHandlers();

                empForm && empForm.addEventListener('submit', async (e)=>{ e.preventDefault(); const fd = new FormData(empForm); try{ const r = await fetch('?section=employees', { method:'POST', body: fd, headers: { 'X-Requested-With':'XMLHttpRequest' }}); const j = await r.json(); if(!j.success){ alert(j.message||'Save failed'); return; } hideEmpModal(); empRefresh(); } catch (_){ alert('Network error'); } });
            }

            // Catering Packages: filtering + actions
            if (initialSection === 'catering') {
                const form = document.getElementById('cp-filter');
                const q = document.getElementById('cp-q');
                const date = document.getElementById('cp-date');
                const method = document.getElementById('cp-method');
                const status = document.getElementById('cp-status');
                const clearBtn = document.getElementById('cp-clear');
                const list = document.getElementById('cp-list');
                let seq = 0; let ctrl = null;

                const refresh = () => {
                    const params = new URLSearchParams(new FormData(form));
                    params.set('cp_page','1');
                    const url = '?' + params.toString();
                    if (!list) { window.location.href = url; return; }
                    list.style.opacity = '0.6';
                    const mySeq = ++seq; try { ctrl && ctrl.abort(); } catch {}
                    ctrl = new AbortController();
                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal })
                        .then(r=>r.text())
                        .then(html=>{ if (mySeq!==seq) return; const tmp=document.createElement('div'); tmp.innerHTML=html; const upd=tmp.querySelector('#cp-list'); if (upd) list.innerHTML=upd.innerHTML; else window.location.href=url; })
                        .catch(err=>{ if (mySeq===seq && (!err || err.name!=='AbortError')) window.location.href=url; })
                        .finally(()=>{ if (mySeq===seq) list.style.opacity='1'; });
                };

                let t=null; q && q.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(refresh,300); });
                [date, method, status].forEach(el=>el && el.addEventListener('change', refresh));
                clearBtn && clearBtn.addEventListener('click', ()=>{ if(!form) return; q&&(q.value=''); date&&(date.value=''); method&&(method.value=''); status&&(status.value=''); refresh(); });

                // Intercept pagination
                list && list.addEventListener('click', (e)=>{
                    const a = e.target.closest('a');
                    if (a) {
                        const href = a.getAttribute('href')||'';
                        if (href.includes('section=catering') && href.includes('cp_page=')) {
                            e.preventDefault(); const url=a.href; const mySeq=++seq; list.style.opacity='0.6'; try{ctrl&&ctrl.abort();}catch{} ctrl=new AbortController();
                            fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'}, signal: ctrl.signal })
                                .then(r=>r.text()).then(html=>{ if (mySeq!==seq) return; const tmp=document.createElement('div'); tmp.innerHTML=html; const upd=tmp.querySelector('#cp-list'); if (upd) list.innerHTML=upd.innerHTML; else window.location.href=url; })
                                .catch(err=>{ if (mySeq===seq && (!err || err.name!=='AbortError')) window.location.href=url; })
                                .finally(()=>{ if (mySeq===seq) list.style.opacity='1'; });
                            return;
                        }
                    }

                    // Action buttons
                    const editBtn = e.target.closest('.cp-edit');
                    const paidBtn = e.target.closest('.cp-paid');
                    const delBtn = e.target.closest('.cp-delete');
                    if (editBtn) {
                        e.preventDefault(); const id=editBtn.getAttribute('data-cp-id'); if(!id) return;
                        const back=document.getElementById('cp-backdrop'); const modal=document.getElementById('cp-modal');
                        const open=()=>{ back.style.display='block'; modal.style.display='flex'; setTimeout(()=>{ back.style.opacity='1'; modal.style.opacity='1'; modal.style.pointerEvents='auto'; }, 10); };
                        fetch(`?section=catering&ajax=1&action=get_cp&cp_id=${encodeURIComponent(id)}`, { headers:{'X-Requested-With':'XMLHttpRequest'} })
                                                    .then(r=>r.json()).then(j=>{ if(!j||!j.success) return alert('Failed to load');
                            document.getElementById('cp-id').value=id;
                            document.getElementById('cp-name').value=j.data.cp_name||'';
                            document.getElementById('cp-phone').value=j.data.cp_phone||'';
                            document.getElementById('cp-place').value=j.data.cp_place||'';
                            document.getElementById('cp-date-input').value=j.data.cp_date||'';
                            document.getElementById('cp-price').value=j.data.cp_price||'';
                            document.getElementById('cp-addon').value=j.data.cp_addon_pax||'';
                            document.getElementById('cp-notes').value=j.data.cp_notes||'';
                                                        const pay=j.payment||{};
                                                        const pm=document.getElementById('cp-pay-method');
                                                        const pid=document.getElementById('cp-pay-id');
                                                        const pa=document.getElementById('cp-pay-amount');
                                                        const ps=document.getElementById('cp-pay-status');
                                                        if (pid) pid.value = (pay.pay_id||'');
                                                        if (pm) pm.value = (pay.pay_method||'');
                                                        if (pa) pa.value = (pay.pay_amount!=null? pay.pay_amount : '');
                                                        if (ps) ps.value = (pay.pay_status||'');
                            open();
                          }).catch(()=>alert('Failed to load'));
                        return;
                    }
                    if (paidBtn) {
                                                e.preventDefault(); const id=paidBtn.getAttribute('data-cp-id'); if(!id) return;
                        // Auto-mark as fully paid: amount = cp_price and status to Paid
                                                fetch(`?section=catering&ajax=1&action=get_cp&cp_id=${encodeURIComponent(id)}`, { headers:{'X-Requested-With':'XMLHttpRequest'} })
                                                    .then(r=>r.json()).then(j=>{
                                                        if (!j||!j.success||!j.data) { alert('Failed to load package'); return; }
                                                        const price = parseFloat(j.data.cp_price||0) || 0;
                                                        const fd = new FormData();
                                                        fd.append('section','catering'); fd.append('ajax','1'); fd.append('action','mark_paid'); fd.append('cp_id', id);
                                                        fd.append('pay_method','Cash'); // default method
                            fd.append('pay_amount', String(price));
                                                        fetch('?section=catering', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
                                                            .then(r=>r.json()).then(()=>refresh()).catch(()=>alert('Mark paid failed'));
                                                    }).catch(()=>alert('Failed to mark paid'));
                                                return;
                                        }
                    if (delBtn) {
                        e.preventDefault(); const id=delBtn.getAttribute('data-cp-id'); if(!id) return; if(!confirm('Delete this catering package?')) return;
                        const fd=new FormData(); fd.append('section','catering'); fd.append('ajax','1'); fd.append('action','delete'); fd.append('cp_id', id);
                        fetch('?section=catering', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
                          .then(r=>r.json()).then(()=>refresh()).catch(()=>alert('Delete failed'));
                        return;
                    }
                });

                // Modal handlers
                const cpBack=document.getElementById('cp-backdrop'); const cpModal=document.getElementById('cp-modal');
                const cpClose=()=>{ cpBack.style.opacity='0'; cpModal.style.opacity='0'; cpModal.style.pointerEvents='none'; setTimeout(()=>{ cpBack.style.display='none'; cpModal.style.display='none'; }, 180); };
                document.getElementById('cp-close')?.addEventListener('click', cpClose);
                document.getElementById('cp-cancel')?.addEventListener('click', cpClose);
                document.getElementById('cp-form')?.addEventListener('submit', (e)=>{
                    e.preventDefault(); const fd=new FormData(e.currentTarget); fd.set('ajax','1'); fd.set('section','catering'); fd.set('action','update');
                    fetch('?section=catering', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
                      .then(r=>r.json()).then(j=>{ if(!j||!j.success){ alert(j&&j.message?j.message:'Save failed'); return; } cpClose(); refresh(); })
                      .catch(()=>alert('Save failed'));
                });

                const cppBack=document.getElementById('cp-paid-backdrop'); const cppModal=document.getElementById('cp-paid-modal');
                const cppClose=()=>{ cppBack.style.opacity='0'; cppModal.style.opacity='0'; cppModal.style.pointerEvents='none'; setTimeout(()=>{ cppBack.style.display='none'; cppModal.style.display='none'; }, 180); };
                document.getElementById('cp-paid-close')?.addEventListener('click', cppClose);
                document.getElementById('cp-paid-cancel')?.addEventListener('click', cppClose);
                document.getElementById('cp-paid-form')?.addEventListener('submit', (e)=>{
                    e.preventDefault(); const fd=new FormData(e.currentTarget); fd.set('ajax','1'); fd.set('section','catering'); fd.set('action','mark_paid');
                    fetch('?section=catering', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
                      .then(r=>r.json()).then(j=>{ if(!j||!j.success){ alert(j&&j.message?j.message:'Save failed'); return; } cppClose(); refresh(); })
                      .catch(()=>alert('Save failed'));
                });
            }

            // Bookings: filtering + CRUD
            if (initialSection === 'bookings') {
                const bForm = document.getElementById('bookings-filter');
                const bQ = document.getElementById('bk-q');
                const bType = document.getElementById('bk-type');
                const bOrder = document.getElementById('bk-order');
                const bStatus = document.getElementById('bk-status');
                const bDate = document.getElementById('bk-date');
                const bClear = document.getElementById('bookings-clear');
                const bList = document.getElementById('bookings-list');

                let bSeq = 0; let bController = null;
                const refreshBookings = () => {
                    const params = new URLSearchParams(new FormData(bForm));
                    params.set('section','bookings'); params.set('bk_page','1');
                    const url = '?' + params.toString();
                    bList && (bList.style.opacity='0.6');
                    const my = ++bSeq; try { bController && bController.abort(); } catch {}
                    bController = new AbortController();
                    fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }, signal: bController.signal })
                        .then(r=>r.text())
                        .then(html=>{ if (my!==bSeq) return; const tmp=document.createElement('div'); tmp.innerHTML=html; const updated = tmp.querySelector('#bookings-list'); const cur = document.getElementById('bookings-list'); if (updated && cur) cur.replaceWith(updated); else if (!updated) window.location.href = url; })
                        .catch(err=>{ if (my===bSeq && (!err || err.name!=='AbortError')) window.location.href = url; })
                        .finally(()=>{ if (my!==bSeq) return; const nl=document.getElementById('bookings-list'); if (nl) nl.style.opacity='1'; attachBookingsHandlers(); });
                };
                let tb=null; bQ && bQ.addEventListener('input', ()=>{ clearTimeout(tb); tb=setTimeout(refreshBookings, 300); });
                [bType,bOrder,bStatus,bDate].forEach(el=> el && el.addEventListener('change', refreshBookings));
                bForm && bForm.addEventListener('submit', (e)=>{ e.preventDefault(); });
                bClear && bClear.addEventListener('click', ()=>{ bQ&&(bQ.value=''); bType&&(bType.value=''); bOrder&&(bOrder.value=''); bStatus&&(bStatus.value=''); bDate&&(bDate.value=''); refreshBookings(); });

                function attachBookingsHandlers(){
                    const list = document.getElementById('bookings-list'); if (!list) return;
                    // Pagination
                    list.addEventListener('click', (e)=>{
                        const a = e.target.closest('a'); if (!a) return; const href = a.getAttribute('href')||'';
                        if (href.includes('section=bookings') && href.includes('bk_page=')){
                            e.preventDefault(); const url = a.href; const my=++bSeq; try { bController && bController.abort(); } catch {} bController = new AbortController();
                            list.style.opacity='0.6'; fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'}, signal:bController.signal })
                                .then(r=>r.text())
                                .then(html=>{ if (my!==bSeq) return; const tmp=document.createElement('div'); tmp.innerHTML=html; const updated = tmp.querySelector('#bookings-list'); const cur = document.getElementById('bookings-list'); if (updated && cur) cur.replaceWith(updated); else if (!updated) window.location.href = url; })
                                .catch(err=>{ if (my===bSeq && (!err || err.name!=='AbortError')) window.location.href = url; })
                                .finally(()=>{ if (my!==bSeq) return; const nl=document.getElementById('bookings-list'); if (nl) nl.style.opacity='1'; attachBookingsHandlers(); });
                        }
                    });

                    // Actions
                    list.addEventListener('click', async (e)=>{
                        const editBtn = e.target.closest('.bk-edit');
                        const paidBtn = e.target.closest('.bk-paid');
                        const delBtn = e.target.closest('.bk-delete');
                        if (editBtn){
                            const id = editBtn.getAttribute('data-bk-id');
                            try { const r = await fetch(`?section=bookings&action=get_booking&booking_id=${id}`, { headers:{'X-Requested-With':'XMLHttpRequest'} }); const j = await r.json(); if(!j.success) return alert(j.message||'Failed'); const d=j.data; openEditBooking(d); } catch (_){ alert('Network error'); }
                            return;
                        }
                        if (paidBtn){
                            const id = paidBtn.getAttribute('data-bk-id'); openPaidBooking(id); return;
                        }
                        if (delBtn){
                            const id = delBtn.getAttribute('data-bk-id'); if (!confirm('Delete this booking?')) return; await fetch(`?section=bookings&action=delete&booking_id=${id}&ajax=1`, { headers:{'X-Requested-With':'XMLHttpRequest'} }); refreshBookings(); return;
                        }
                    });
                }
                attachBookingsHandlers();

                // Edit booking modal
                const bkBack = document.getElementById('bk-backdrop');
                const bkModal = document.getElementById('bk-modal');
                const bkDialog = bkModal ? bkModal.querySelector('.dialog') : null;
                const bkForm = document.getElementById('bk-form');
                const bkId = document.getElementById('bk-id');
                const bFields = { name:document.getElementById('bk-name'), contact:document.getElementById('bk-contact'), type:document.getElementById('bk-type-input'), venue:document.getElementById('bk-venue'), date:document.getElementById('bk-date-input'), order:document.getElementById('bk-order-input'), pax:document.getElementById('bk-pax'), addon:document.getElementById('bk-addon'), status:document.getElementById('bk-status-input'), notes:document.getElementById('bk-notes') };
                function showBkModal(){ if(!bkBack||!bkModal) return; bkBack.style.display='block'; bkModal.style.display='flex'; bkBack.setAttribute('aria-hidden','false'); bkModal.setAttribute('aria-hidden','false'); bkBack.classList.remove('pointer-events-none'); bkModal.classList.remove('pointer-events-none'); requestAnimationFrame(()=>{ bkBack.style.opacity='1'; bkModal.style.opacity='1'; if(bkDialog) bkDialog.style.transform='scale(1)'; }); }
                function hideBkModal(){ if(!bkBack||!bkModal) return; bkBack.style.opacity='0'; bkModal.style.opacity='0'; if(bkDialog) bkDialog.style.transform='scale(0.95)'; setTimeout(()=>{ bkBack.classList.add('pointer-events-none'); bkModal.classList.add('pointer-events-none'); bkBack.style.display='none'; bkModal.style.display='none'; bkBack.setAttribute('aria-hidden','true'); bkModal.setAttribute('aria-hidden','true'); },180); }
                document.getElementById('bk-close')?.addEventListener('click', hideBkModal);
                document.getElementById('bk-cancel')?.addEventListener('click', hideBkModal);
                bkBack && bkBack.addEventListener('click', hideBkModal);

                function openEditBooking(d){
                    bkId.value = d.eb_id;
                    bFields.name.value = d.eb_name||'';
                    bFields.contact.value = d.eb_contact||'';
                    bFields.type.value = d.eb_type||'';
                    bFields.venue.value = d.eb_venue||'';
                    // Convert timestamp to datetime-local
                    const dt = new Date(d.eb_date.replace(' ','T'));
                    const pad=(n)=> String(n).padStart(2,'0');
                    const local = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                    bFields.date.value = local;
                    bFields.order.value = d.eb_order||'';
                    bFields.pax.value = d.eb_package_pax||'';
                    bFields.addon.value = d.eb_addon_pax||'';
                    bFields.status.value = d.eb_status||'Pending';
                    bFields.notes.value = d.eb_notes||'';
                    showBkModal();
                }

                bFields.contact && bFields.contact.addEventListener('input', ()=>{ bFields.contact.value = (bFields.contact.value||'').replace(/\D+/g,'').slice(0,11); });
                bkForm && bkForm.addEventListener('submit', async (e)=>{ e.preventDefault(); const fd = new FormData(bkForm); try { const r=await fetch('?section=bookings', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }); const j=await r.json(); if(!j.success){ alert(j.message||'Save failed'); return; } hideBkModal(); refreshBookings(); } catch (_){ alert('Network error'); } });

                // Paid modal
                const pdBack = document.getElementById('bk-paid-backdrop');
                const pdModal = document.getElementById('bk-paid-modal');
                const pdDialog = pdModal ? pdModal.querySelector('.dialog') : null;
                const pdForm = document.getElementById('bk-paid-form');
                const pdId = document.getElementById('bk-paid-id');
                const pdAction = pdForm ? pdForm.querySelector('input[name="action"]') : null;
                const pdTitle = document.querySelector('#bk-paid-modal h3');
                function showPd(){ if(!pdBack||!pdModal) return; pdBack.style.display='block'; pdModal.style.display='flex'; pdBack.setAttribute('aria-hidden','false'); pdModal.setAttribute('aria-hidden','false'); pdBack.classList.remove('pointer-events-none'); pdModal.classList.remove('pointer-events-none'); requestAnimationFrame(()=>{ pdBack.style.opacity='1'; pdModal.style.opacity='1'; if(pdDialog) pdDialog.style.transform='scale(1)'; }); }
                function hidePd(){ if(!pdBack||!pdModal) return; pdBack.style.opacity='0'; pdModal.style.opacity='0'; if(pdDialog) pdDialog.style.transform='scale(0.95)'; setTimeout(()=>{ pdBack.classList.add('pointer-events-none'); pdModal.classList.add('pointer-events-none'); pdBack.style.display='none'; pdModal.style.display='none'; pdBack.setAttribute('aria-hidden','true'); pdModal.setAttribute('aria-hidden','true'); },180); }
                document.getElementById('bk-paid-close')?.addEventListener('click', hidePd);
                document.getElementById('bk-paid-cancel')?.addEventListener('click', hidePd);
                pdBack && pdBack.addEventListener('click', hidePd);
                function openPaidBooking(id, mode){
                    pdId.value = id;
                    if (pdAction) pdAction.value = (mode==='downpayment') ? 'mark_downpayment' : 'mark_paid';
                    if (pdTitle) pdTitle.textContent = (mode==='downpayment') ? 'Record Downpayment' : 'Record Booking Payment';
                    showPd();
                }
                pdForm && pdForm.addEventListener('submit', async (e)=>{ e.preventDefault(); const fd = new FormData(pdForm); try { const r=await fetch('?section=bookings', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }); const j=await r.json(); if(!j.success){ alert(j.message||'Failed to save'); return; } hidePd(); refreshBookings(); } catch (_){ alert('Network error'); } });
            }
            // Categories: modal + live menu search + CRUD
            if (initialSection === 'categories') {
                const openBtn = document.getElementById('open-add-category');
                const backdrop = document.getElementById('cat-backdrop');
                const modal = document.getElementById('cat-modal');
                const title = document.getElementById('cat-modal-title');
                const closeBtn = document.getElementById('cat-close');
                const cancelBtn = document.getElementById('cat-cancel');
                const form = document.getElementById('cat-form');
                const actionEl = document.getElementById('cat-action');
                const idEl = document.getElementById('cat-id');
                const nameEl = document.getElementById('cat-name');
                const searchEl = document.getElementById('cat-menu-search');
                const resultsEl = document.getElementById('cat-menu-results');
                const allEl = document.getElementById('cat-menu-all');
                const selectedWrap = document.getElementById('cat-selected');
                const table = document.getElementById('categories-table');

                let chosen = new Map(); // menu_id => {id, name}
                function chosenChipClasses() {
                    const name = (nameEl.value || '').toLowerCase();
                    // Map category name keywords to Tailwind color classes
                    if (name.includes('beef')) return { wrap: 'bg-red-50 border-red-300', text: 'text-red-800', remove: 'text-red-600' };
                    if (name.includes('pork')) return { wrap: 'bg-rose-50 border-rose-300', text: 'text-rose-800', remove: 'text-rose-600' };
                    if (name.includes('chicken')) return { wrap: 'bg-amber-50 border-amber-300', text: 'text-amber-800', remove: 'text-amber-700' };
                    if (name.includes('seafood') || name.includes('fish') || name.includes('shrimp')) return { wrap: 'bg-sky-50 border-sky-300', text: 'text-sky-800', remove: 'text-sky-700' };
                    if (name.includes('vegetable') || name.includes('veggie') || name.includes('vegt')) return { wrap: 'bg-emerald-50 border-emerald-300', text: 'text-emerald-800', remove: 'text-emerald-700' };
                    if (name.includes('pasta')) return { wrap: 'bg-yellow-50 border-yellow-300', text: 'text-yellow-800', remove: 'text-yellow-700' };
                    if (name.includes('dessert') || name.includes('sweet')) return { wrap: 'bg-fuchsia-50 border-fuchsia-300', text: 'text-fuchsia-800', remove: 'text-fuchsia-700' };
                    if (name.includes('best') || name.includes('bestseller') || name.includes('best seller')) return { wrap: 'bg-indigo-50 border-indigo-300', text: 'text-indigo-800', remove: 'text-indigo-700' };
                    // default neutral styling
                    return { wrap: 'bg-gray-50 border-gray-300', text: 'text-gray-800', remove: 'text-red-600' };
                }
                function renderChosen() {
                    selectedWrap.innerHTML = '';
                    if (chosen.size === 0) {
                        const span = document.createElement('span');
                        span.className = 'text-sm text-muted-foreground';
                        span.textContent = 'None selected';
                        selectedWrap.appendChild(span);
                        return;
                    }
                    const cls = chosenChipClasses();
                    chosen.forEach((v, k) => {
                        const chip = document.createElement('div');
                        chip.className = `inline-flex items-center gap-2 px-2 py-1 border rounded-lg ${cls.wrap} transition-colors duration-150 hover:brightness-95`;
                        chip.innerHTML = `<span class="text-sm ${cls.text}">${v.name}</span><button type="button" class="${cls.remove} hover:underline" data-remove-menu="${k}">Remove</button>`;
                        selectedWrap.appendChild(chip);
                    });
                }
                function showModal(edit=false) {
                    backdrop.style.display = 'block';
                    modal.style.display = 'flex';
                    backdrop.setAttribute('aria-hidden', 'false');
                    modal.setAttribute('aria-hidden', 'false');
                    backdrop.classList.remove('pointer-events-none');
                    modal.classList.remove('pointer-events-none');
                    requestAnimationFrame(()=>{ backdrop.style.opacity='1'; modal.style.opacity='1'; });
                    title.textContent = edit ? 'Edit Category' : 'Add Category';
                    // Load all menus (once per open)
                    if (allEl && allEl.childElementCount === 0) {
                        fetch('?section=categories&ajax=1&action=list_menu', { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                            .then(r => r.json())
                            .then(data => {
                                allEl.innerHTML = '';
                                if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
                                    const div = document.createElement('div');
                                    div.className = 'p-2 text-sm text-muted-foreground';
                                    div.textContent = 'No menus available';
                                    allEl.appendChild(div);
                                    return;
                                }
                                data.data.forEach(row => {
                                    const item = document.createElement('div');
                                    item.className = 'flex items-center justify-between px-2 py-1 hover:bg-gray-50 border-b last:border-b-0';
                                    const name = document.createElement('div');
                                    name.className = 'text-sm';
                                    name.textContent = row.menu_name;
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'px-2 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50';
                                    btn.textContent = 'Add';
                                    btn.dataset.menuId = String(row.menu_id);
                                    if (chosen.has(row.menu_id)) { btn.disabled = true; btn.classList.add('opacity-50','cursor-not-allowed'); }
                                    btn.addEventListener('click', ()=>{
                                        if (!chosen.has(row.menu_id)) {
                                            chosen.set(row.menu_id, { id: row.menu_id, name: row.menu_name });
                                            renderChosen();
                                            btn.disabled = true; btn.classList.add('opacity-50','cursor-not-allowed');
                                        }
                                    });
                                    item.appendChild(name); item.appendChild(btn);
                                    allEl.appendChild(item);
                                });
                            })
                            .catch(()=>{
                                allEl.innerHTML = '<div class="p-2 text-sm text-red-600">Failed to load menus</div>';
                            });
                    }
                }
                function hideModal() {
                    backdrop.style.opacity = '0';
                    modal.style.opacity = '0';
                    setTimeout(()=>{
                        backdrop.classList.add('pointer-events-none');
                        modal.classList.add('pointer-events-none');
                        backdrop.style.display = 'none';
                        modal.style.display = 'none';
                        backdrop.setAttribute('aria-hidden','true');
                        modal.setAttribute('aria-hidden','true');
                        form.reset();
                        resultsEl.innerHTML='';
                        resultsEl.classList.add('hidden');
                        if (allEl) allEl.innerHTML='';
                        chosen.clear();
                        renderChosen();
                        actionEl.value = 'create';
                        idEl.value = '';
                    }, 180);
                }
                closeBtn && closeBtn.addEventListener('click', hideModal);
                cancelBtn && cancelBtn.addEventListener('click', hideModal);
                backdrop && backdrop.addEventListener('click', hideModal);
                openBtn && openBtn.addEventListener('click', ()=> showModal(false));

                // Live menu search
                let st = null; let ctrl = null; let seq = 0;
                searchEl && searchEl.addEventListener('input', ()=>{
                    clearTimeout(st);
                    const q = (searchEl.value||'').trim();
                    if (q === '') { resultsEl.innerHTML=''; resultsEl.classList.add('hidden'); return; }
                    st = setTimeout(async ()=>{
                        try { ctrl && ctrl.abort(); } catch {}
                        ctrl = new AbortController();
                        const mySeq = ++seq;
                        const res = await fetch(`?section=categories&ajax=1&action=search_menu&q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal });
                        const data = await res.json();
                        if (mySeq !== seq) return;
                        resultsEl.innerHTML = '';
                        if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
                            resultsEl.classList.remove('hidden');
                            const div = document.createElement('div');
                            div.className = 'p-2 text-sm text-muted-foreground';
                            div.textContent = 'No results';
                            resultsEl.appendChild(div);
                            return;
                        }
                        resultsEl.classList.remove('hidden');
                        // filter out already-selected items
                        const filtered = data.data.filter(row => !chosen.has(row.menu_id));
                        filtered.forEach(row => {
                            const item = document.createElement('div');
                            item.className = 'flex items-center justify-between px-2 py-1 hover:bg-gray-50 border-b last:border-b-0';
                            const name = document.createElement('div');
                            name.className = 'text-sm';
                            name.textContent = row.menu_name;
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'px-2 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50';
                            btn.textContent = 'Add';
                            btn.addEventListener('click', ()=>{
                                if (!chosen.has(row.menu_id)) {
                                    chosen.set(row.menu_id, { id: row.menu_id, name: row.menu_name });
                                    renderChosen();
                                }
                            });
                            item.appendChild(name); item.appendChild(btn);
                            resultsEl.appendChild(item);
                        });
                        if (filtered.length === 0) {
                            const div = document.createElement('div');
                            div.className = 'p-2 text-sm text-muted-foreground';
                            div.textContent = 'No results';
                            resultsEl.appendChild(div);
                        }
                    }, 250);
                });
                selectedWrap && selectedWrap.addEventListener('click', (e)=>{
                    const btn = e.target.closest('[data-remove-menu]');
                    if (!btn) return;
                    const id = parseInt(btn.getAttribute('data-remove-menu')||'0', 10);
                    if (chosen.has(id)) {
                        chosen.delete(id);
                        renderChosen();
                        // Re-enable any disabled Add button in All Menus for this id
                        if (allEl) {
                            const b = allEl.querySelector(`button[data-menu-id="${id}"]`);
                            if (b) { b.disabled = false; b.classList.remove('opacity-50','cursor-not-allowed'); }
                        }
                    }
                });
                renderChosen();
                // Re-render chosen chips on category name change to update color theme
                nameEl && nameEl.addEventListener('input', renderChosen);


            
                // Edit existing category: fetch and populate
                table && table.addEventListener('click', async (e) => {
                    const del = e.target.closest('[data-delete-category]');
                    if (del) {
                        const id = del.getAttribute('data-delete-category');
                        if (id && confirm('Delete this category?')) {
                            const params = new URLSearchParams({ section:'categories', ajax:'1', action:'delete', category_id:String(id) });
                            await fetch('?'+params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                            window.location.reload();
                        }
                        return;
                    }
                    const edit = e.target.closest('[data-edit-category]');
                    if (edit) {
                        const id = edit.getAttribute('data-edit-category');
                        if (!id) return;
                        try {
                            const res = await fetch(`?section=categories&ajax=1&action=get_category&category_id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                            const data = await res.json();
                            if (!data.success) { alert(data.message||'Failed to fetch category'); return; }
                            const c = data.data.category; const menus = data.data.menus || [];
                            actionEl.value = 'update';
                            idEl.value = c.category_id;
                            nameEl.value = c.category_name || '';
                            chosen.clear();
                            menus.forEach(m => chosen.set(m.menu_id, { id: m.menu_id, name: m.menu_name }));
                            renderChosen();
                            showModal(true);
                        } catch (_) { alert('Network error'); }
                    }
                });

                // Submit create/update
                form && form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const fd = new FormData(form);
                    // append menu_ids[]
                    chosen.forEach((v) => { fd.append('menu_ids[]', String(v.id)); });
                    try {
                        const res = await fetch('?section=categories', { method:'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                        const data = await res.json();
                        if (!data.success) { alert(data.message || 'Save failed'); return; }
                        const url = new URL(window.location.href);
                        const cp = url.searchParams.get('cat_page');
                        window.location.href = cp ? `?section=categories&cat_page=${encodeURIComponent(cp)}` : '?section=categories';
                    } catch (_) { alert('Network error'); }
                });
            }

            // Event Types: modal and CRUD (standalone initializer)
            if (initialSection === 'eventtypes') {
                const etBackdrop = document.getElementById('et-backdrop');
                const etModal = document.getElementById('et-modal');
                const etTitle = document.getElementById('et-modal-title');
                const etForm = document.getElementById('et-form');
                const etAction = document.getElementById('et-action');
                const etId = document.getElementById('et-id');
                const etName = document.getElementById('et-name');
                const etMin = document.getElementById('et-min');
                const etMax = document.getElementById('et-max');
                const etNotes = document.getElementById('et-notes');
                const etPkgSearch = document.getElementById('et-packages-search');
                const etPkgList = document.getElementById('et-packages-list');
                let etGrid = document.getElementById('et-grid');

                let allPackages = [];
                let selectedPackageIds = new Set();

                function showEt(){
                    if(!etBackdrop||!etModal) return;
                    etBackdrop.style.display='block';
                    etModal.style.display='flex';
                    etBackdrop.classList.remove('pointer-events-none');
                    etModal.classList.remove('pointer-events-none');
                    etBackdrop.setAttribute('aria-hidden','false');
                    etModal.setAttribute('aria-hidden','false');
                    requestAnimationFrame(()=>{
                        etBackdrop.style.opacity='1';
                        etModal.style.opacity='1';
                        if (etModal.firstElementChild) etModal.firstElementChild.style.transform='scale(1)';
                    });
                }
                function hideEt(){
                    if(!etBackdrop||!etModal) return;
                    etBackdrop.style.opacity='0';
                    etModal.style.opacity='0';
                    if (etModal.firstElementChild) etModal.firstElementChild.style.transform='scale(0.95)';
                    setTimeout(()=>{
                        etBackdrop.classList.add('pointer-events-none');
                        etModal.classList.add('pointer-events-none');
                        etBackdrop.style.display='none';
                        etModal.style.display='none';
                        etBackdrop.setAttribute('aria-hidden','true');
                        etModal.setAttribute('aria-hidden','true');
                    }, 180);
                }
                function resetEt(){ etForm?.reset(); if(etAction) etAction.value='create'; if(etId) etId.value=''; if(etTitle) etTitle.textContent='Add Event Type'; selectedPackageIds.clear(); renderPkgList(); }

                async function loadPackages(){
                    try { const r = await fetch('?section=eventtypes&ajax=1&action=list_packages', { headers:{'X-Requested-With':'XMLHttpRequest'} }); const j=await r.json(); if(j.success){ allPackages = j.data||[]; renderPkgList(); } } catch(_){ /* ignore */ }
                }
                function renderPkgList(){
                    if (!etPkgList) return;
                    const q = (etPkgSearch?.value||'').toLowerCase();
                    const frag = document.createDocumentFragment();
                    (allPackages||[]).forEach(p=>{
                        const txt = ((p.name||'')+' '+(p.pax||'')).toLowerCase();
                        if (q && !txt.includes(q)) return;
                        const id = Number(p.package_id);
                        const wrap = document.createElement('label');
                        wrap.className='flex items-center gap-2 p-2 rounded border border-gray-200 hover:bg-gray-50';
                        const cb = document.createElement('input');
                        cb.type='checkbox'; cb.value=String(id); cb.checked = selectedPackageIds.has(id);
                        cb.addEventListener('change', ()=>{ if (cb.checked) selectedPackageIds.add(id); else selectedPackageIds.delete(id); });
                        const name = document.createElement('div');
                        name.className='text-sm';
                        name.textContent = (p.name||'') + (p.pax?(' • '+p.pax):'');
                        const badge = document.createElement('span');
                        badge.className = 'ml-auto inline-flex items-center px-2 py-0.5 text-xs rounded-full border ' + ((String(p.is_active)==='1') ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-gray-50 border-gray-300 text-gray-700');
                        badge.textContent = (String(p.is_active)==='1') ? 'Active' : 'Inactive';
                        wrap.append(cb, name, badge);
                        frag.appendChild(wrap);
                    });
                    etPkgList.innerHTML=''; etPkgList.appendChild(frag);
                }

                async function refreshEtGrid(){
                    try {
                        const html = await fetch('?section=eventtypes', { headers:{'X-Requested-With':'XMLHttpRequest'} }).then(r=>r.text());
                        const tmp = document.createElement('div'); tmp.innerHTML = html;
                        const newGrid = tmp.querySelector('#eventtypes-content #et-grid');
                        if (newGrid && etGrid) { etGrid.replaceWith(newGrid); }
                        const fresh = document.querySelector('#eventtypes-content #et-grid');
                        if (fresh) etGrid = fresh;
                    } catch(_){ /* ignore */ }
                }

                document.getElementById('open-add-eventtype')?.addEventListener('click', async ()=>{ resetEt(); await loadPackages(); showEt(); });
                document.getElementById('et-close')?.addEventListener('click', hideEt);
                document.getElementById('et-cancel')?.addEventListener('click', hideEt);
                etBackdrop && etBackdrop.addEventListener('click', hideEt);
                etPkgSearch?.addEventListener('input', renderPkgList);

                document.addEventListener('click', async (e)=>{
                    const editBtn = e.target.closest?.('.et-edit');
                    const delBtn = e.target.closest?.('.et-delete');
                    if (editBtn) {
                        const id = editBtn.getAttribute('data-et-id');
                        try {
                            const r = await fetch(`?section=eventtypes&ajax=1&action=get&event_type_id=${encodeURIComponent(id)}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
                            const j = await r.json(); if(!j.success) return alert(j.message||'Failed');
                            resetEt(); await loadPackages();
                            const d = j.data||{}; const pkgIds = j.package_ids||[];
                            if (etTitle) etTitle.textContent='Edit Event Type'; if (etAction) etAction.value='update'; if (etId) etId.value = d.event_type_id || id;
                            if (etName) etName.value = d.name||''; if (etMin) etMin.value = d.min_package_pax||''; if (etMax) etMax.value = d.max_package_pax||''; if (etNotes) etNotes.value = d.notes||'';
                            selectedPackageIds = new Set(pkgIds.map(Number)); renderPkgList(); showEt();
                        } catch(_){ alert('Network error'); }
                        return;
                    }
                    if (delBtn) {
                        const id = delBtn.getAttribute('data-et-id');
                        if (!confirm('Delete this event type?')) return;
                        try { await fetch(`?section=eventtypes&action=delete&event_type_id=${encodeURIComponent(id)}&ajax=1`, { headers:{'X-Requested-With':'XMLHttpRequest'} }); await refreshEtGrid(); } catch(_){ alert('Network error'); }
                        return;
                    }
                });

                etForm?.addEventListener('submit', async (e)=>{
                    e.preventDefault();
                    if (!etName?.value.trim()) { alert('Name is required'); etName?.focus(); return; }
                    const minVal = etMin && etMin.value !== '' ? Number(etMin.value) : null;
                    const maxVal = etMax && etMax.value !== '' ? Number(etMax.value) : null;
                    if (minVal !== null && maxVal !== null && minVal > maxVal) { alert('Min pax cannot exceed max pax'); return; }
                    const fd = new FormData(etForm);
                    selectedPackageIds.forEach(id => fd.append('package_ids[]', String(id)));
                    try {
                        const r = await fetch('?section=eventtypes', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
                        const j = await r.json(); if(!j.success){ alert(j.message||'Save failed'); return; }
                        hideEt(); await refreshEtGrid();
                    } catch(_){ alert('Network error'); }
                });
            }

            // Revenue Chart (dynamic & real-time)
            (function(){
                const revenueCanvas = document.getElementById('revenueChart');
                if (!revenueCanvas || !revenueCanvas.getContext) return;
                const ctx = revenueCanvas.getContext('2d');
                let revenueChart = null;
                const GOLD = '#D4AF37';
                const GREEN = '#1B4332';
                async function loadMonthlyRevenue(){
                    try {
                        const res = await fetch('?section=dashboard&action=get_monthly_revenue&ajax=1', { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
                        const j = await res.json();
                        if(!j.ok) return;
                        const labels = j.months.map(m=>m.month);
                        const currentYm = j.current;
                        const data = j.months.map(m=>m.amount);
                        const bg = j.months.map(m=> m.ym === currentYm ? GOLD : GREEN);
                        if (!revenueChart){
                            revenueChart = new Chart(ctx, {
                                type: 'bar',
                                data: { labels, datasets: [{ label:'Revenue', data, backgroundColor: bg, borderRadius:4 }] },
                                options: {
                                    responsive:true,
                                    maintainAspectRatio:false,
                                    plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx)=>'₱'+Number(ctx.parsed.y||0).toLocaleString('en-PH') } } },
                                    scales:{
                                        y:{ beginAtZero:true, grid:{ color:'#f0f0f0' }, ticks:{ callback:(v)=>'₱'+Number(v).toLocaleString('en-PH') } },
                                        x:{ grid:{ color:'#f0f0f0' } }
                                    }
                                }
                            });
                        } else {
                            revenueChart.data.labels = labels;
                            revenueChart.data.datasets[0].data = data;
                            revenueChart.data.datasets[0].backgroundColor = bg;
                            revenueChart.update();
                        }
                    } catch(_) { /* silent */ }
                }
                loadMonthlyRevenue();
                setInterval(loadMonthlyRevenue, 15000); // refresh every 15s
            })();

            // Category Chart (guarded)
            const categoryCanvas = document.getElementById('categoryChart');
            if (categoryCanvas && categoryCanvas.getContext) {
            const categoryCtx = categoryCanvas.getContext('2d');
            new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: ['Party Trays', 'Packed Meals', 'Catering Events', 'Grazing Tables'],
                    datasets: [{
                        data: [35, 28, 22, 15],
                        backgroundColor: ['#1B4332', '#D4AF37', '#2D5A3D', '#E8C547']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            }

            // Inline Best Sellers (Monthly) panel - OOP style
            class BestSellersPanel {
                constructor(rowsTbodyId) {
                    this.rowsBody = document.getElementById(rowsTbodyId);
                    if (!this.rowsBody) { this.valid = false; return; }
                    this.valid = true;
                }
                setLoading(){
                    this.rowsBody.innerHTML = '<tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Loading…</td></tr>';
                }
                setError(){
                    this.rowsBody.innerHTML = '<tr><td colspan="3" class="px-3 py-6 text-center text-red-600">Error loading data</td></tr>';
                }
                setEmpty(){
                    this.rowsBody.innerHTML = '<tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">No data</td></tr>';
                }
                render(items){
                    this.rowsBody.innerHTML = items.map(r=>{
                        const img = r.image ? `<img src="${r.image}" alt="${(r.name||'').replace(/"/g,'&quot;')}" class=\"w-8 h-8 object-cover rounded mr-2 border border-gray-200\">` : '';
                        return `<tr class=\"border-b last:border-b-0\"><td class=\"px-3 py-2 align-middle\">${r.rank}</td><td class=\"px-3 py-2 flex items-center\">${img}<span>${(r.name||'')}</span></td><td class=\"px-3 py-2 text-right font-medium align-middle\">${Number(r.qty||0).toLocaleString('en-PH')}</td></tr>`;
                    }).join('');
                }
                async load(){
                    if(!this.valid) return;
                    this.setLoading();
                    try {
                        const res = await fetch(`?section=dashboard&action=get_best_sellers&ajax=1`, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
                        const data = await res.json();
                        if (data && data.ok && Array.isArray(data.items) && data.items.length){
                            this.render(data.items);
                        } else { this.setEmpty(); }
                    } catch(_){ this.setError(); }
                }
                static init(){
                    const panel = new BestSellersPanel('best-sellers-inline-rows');
                    if (panel.valid) panel.load();
                    window.BestSellersPanelInstance = panel; // optional global reference
                    return panel;
                }
            }
            // Initialize panel
            BestSellersPanel.init();

            // Real-time Notifications Manager
            class NotificationsManager {
                constructor(){
                    this.bell = document.getElementById('notification-bell');
                    this.badge = document.getElementById('notification-badge');
                    this.panel = document.getElementById('notification-panel');
                    this.list = document.getElementById('notification-list');
                    this.empty = document.getElementById('notification-empty');
                    this.markReadBtn = document.getElementById('notif-mark-read');
                    this.unread = 0;
                    // Load last seen IDs from localStorage
                    let stored = {};
                    try { stored = JSON.parse(localStorage.getItem('notif.lastIds')||'{}')||{}; } catch(_) { stored={}; }
                    this.lastIds = {
                        order: stored.order||0,
                        cp: stored.cp||0,
                        booking: stored.booking||0
                    };
                    this.items = []; // merged list
                    this.pollIntervalMs = 10000; // 10s
                    this.nextTimer = null;
                    this.active = true;
                    this.attach();
                    this.poll();
                }
                attach(){
                    if (this.bell) {
                        this.bell.addEventListener('click', (e)=>{
                            e.stopPropagation();
                            const open = !this.panel.classList.contains('hidden');
                            if (open) {
                                this.closePanel();
                            } else {
                                this.openPanel();
                            }
                        });
                    }
                    document.addEventListener('click', (e)=>{
                        if (!this.panel) return;
                        if (this.panel.classList.contains('hidden')) return;
                        if (this.panel.contains(e.target) || (this.bell && this.bell.contains(e.target))) return;
                        this.closePanel();
                    });
                    document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') this.closePanel(); });
                    if (this.markReadBtn) this.markReadBtn.addEventListener('click', ()=>{ this.markAllRead(); });
                }
                openPanel(){
                    if (!this.panel) return;
                    this.panel.classList.remove('hidden');
                    this.bell?.setAttribute('aria-expanded','true');
                    this.markAllRead();
                }
                closePanel(){
                    if (!this.panel) return;
                    this.panel.classList.add('hidden');
                    this.bell?.setAttribute('aria-expanded','false');
                }
                markAllRead(){
                    this.unread = 0;
                    this.updateBadge();
                    // Persist current lastIds as seen
                    try { localStorage.setItem('notif.lastIds', JSON.stringify(this.lastIds)); } catch(_) {}
                }
                updateBadge(){
                    if (!this.badge) return;
                    if (this.unread > 0) {
                        this.badge.textContent = this.unread > 99 ? '99+' : String(this.unread);
                        this.badge.classList.remove('hidden');
                    } else {
                        this.badge.classList.add('hidden');
                    }
                }
                mergeAndRender(newItems){
                    if (newItems && newItems.length){
                        // Prepend new items
                        this.items = [...newItems, ...this.items].slice(0,100); // keep last 100
                        if (!this.panel || this.panel.classList.contains('hidden')) {
                            this.unread += newItems.length;
                            this.updateBadge();
                        }
                    }
                    this.render();
                }
                render(){
                    if (!this.list) return;
                    if (!this.items.length){
                        this.empty?.classList.remove('hidden');
                        this.list.innerHTML='';
                        return;
                    } else { this.empty?.classList.add('hidden'); }
                    this.list.innerHTML = this.items.map(it=>{
                        const icon = it.type==='order' ? 'fa-receipt' : (it.type==='catering' ? 'fa-utensils' : 'fa-calendar-alt');
                        const time = it.time ? `<span class=\"block text-[10px] text-gray-400 mt-0.5\">${this.formatTime(it.time)}</span>` : '';
                        const status = it.status ? `<span class=\"text-xs text-gray-500 ml-1\">(${this.escapeHtml(it.status)})</span>` : '';
                        return `<div class=\"px-4 py-2 hover:bg-gray-50 text-gray-700 flex items-start gap-2\">`+
                               `<i class=\"fas ${icon} mt-0.5 text-primary text-xs\"></i>`+
                               `<div class=\"flex-1 min-w-0\"><span class=\"font-medium\">${this.escapeHtml(it.label)}</span>${status}${time}</div>`+
                               `</div>`;
                    }).join('');
                }
                escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
                formatTime(t){
                    // Attempt to parse; if already datetime string; fallback to raw
                    const d = new Date(t.replace(' ','T'));
                    if (isNaN(d.getTime())) return t;
                    const now = new Date();
                    const diffMs = now - d;
                    const diffMin = Math.floor(diffMs/60000);
                    if (diffMin < 1) return 'just now';
                    if (diffMin < 60) return diffMin + 'm ago';
                    const diffHr = Math.floor(diffMin/60);
                    if (diffHr < 24) return diffHr + 'h ago';
                    return d.toLocaleDateString();
                }
                async poll(){
                    if (!this.active) return;
                    try {
                        const url = `?section=dashboard&action=get_notifications&ajax=1&last_order_id=${this.lastIds.order}&last_cp_id=${this.lastIds.cp}&last_booking_id=${this.lastIds.booking}`;
                        const res = await fetch(url, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
                        const data = await res.json();
                        if (data && data.ok) {
                            const newItems = [];
                            if (Array.isArray(data.orders)) newItems.push(...data.orders);
                            if (Array.isArray(data.catering)) newItems.push(...data.catering);
                            if (Array.isArray(data.bookings)) newItems.push(...data.bookings);
                            // Sort newest first by id/time heuristic
                            newItems.sort((a,b)=> b.id - a.id);
                            // Update latest IDs
                            if (data.latest_ids) {
                                this.lastIds.order = data.latest_ids.order || this.lastIds.order;
                                this.lastIds.cp = data.latest_ids.cp || this.lastIds.cp;
                                this.lastIds.booking = data.latest_ids.booking || this.lastIds.booking;
                            }
                            if (newItems.length) this.mergeAndRender(newItems);
                            else this.render();
                        }
                    } catch(_) { /* ignore errors; next poll will retry */ }
                    finally {
                        this.nextTimer = setTimeout(()=>this.poll(), this.pollIntervalMs);
                    }
                }
            }
            // Initialize notifications on all sections if the bell exists
            if (document.getElementById('notification-bell')) {
                window.NotificationsManagerInstance = new NotificationsManager();
            }
        });

        // Add Menu modal + submit
        document.addEventListener('DOMContentLoaded', function() {
            // Orders: edit, mark paid, delete
            if ('<?php echo htmlspecialchars($section); ?>' === 'orders') {
                const ordersTable = document.getElementById('orders-table');
                const oback = document.getElementById('edit-order-backdrop');
                const omodal = document.getElementById('edit-order-modal');
                const oform = document.getElementById('edit-order-form');
                const oidEl = document.getElementById('edit-order-id');
                const ostatusEl = document.getElementById('edit-order-status');
                const oneededEl = document.getElementById('edit-order-needed');
                const ostreetEl = document.getElementById('edit-oa-street');
                const ocityEl = document.getElementById('edit-oa-city');
                const oprovEl = document.getElementById('edit-oa-province');
                const ocancel = document.getElementById('edit-order-cancel');
                const ocancel2 = document.getElementById('edit-order-cancel-2');

                function showOModal() {
                    if (!oback || !omodal) return;
                    oback.style.display = 'block';
                    omodal.style.display = 'flex';
                    oback.setAttribute('aria-hidden','false');
                    omodal.setAttribute('aria-hidden','false');
                    oback.classList.remove('pointer-events-none');
                    omodal.classList.remove('pointer-events-none');
                    requestAnimationFrame(()=>{
                        oback.style.opacity='1';
                        omodal.style.opacity='1';
                    });
                }
                function hideOModal() {
                    if (!oback || !omodal) return;
                    oback.style.opacity='0';
                    omodal.style.opacity='0';
                    setTimeout(()=>{
                        oback.classList.add('pointer-events-none');
                        omodal.classList.add('pointer-events-none');
                        oback.style.display='none';
                        omodal.style.display='none';
                        oback.setAttribute('aria-hidden','true');
                        omodal.setAttribute('aria-hidden','true');
                        oform && oform.reset();
                    }, 180);
                }
                ocancel && ocancel.addEventListener('click', hideOModal);
                ocancel2 && ocancel2.addEventListener('click', hideOModal);
                oback && oback.addEventListener('click', hideOModal);

                ordersTable && ordersTable.addEventListener('click', async (e) => {
                    const editBtn = e.target.closest('[data-edit-order]');
                    const viewBtn = e.target.closest('[data-view-order]');
                    const paidBtn = e.target.closest('[data-paid-order]');
                    const progBtn = e.target.closest('[data-inprogress-order]');
                    const delBtn = e.target.closest('[data-delete-order]');
                    if (progBtn) {
                        const id = progBtn.getAttribute('data-inprogress-order');
                        try {
                            const fd = new FormData();
                            fd.append('ajax','1');
                            fd.append('action','set_status');
                            fd.append('order_id', id);
                            fd.append('order_status','in progress');
                            await fetch('?section=orders', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }}).then(r=>r.json());
                            window.location.reload();
                        } catch (_) { alert('Request failed.'); }
                        return;
                    }
                    if (viewBtn) {
                        const id = viewBtn.getAttribute('data-view-order');
                        try {
                            const res = await fetch(`?section=orders&ajax=1&action=get_order&order_id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                            const data = await res.json();
                            if (!data.success) { alert(data.message || 'Failed to fetch order'); return; }
                            const itemsWrap = document.getElementById('view-order-items');
                            const vback = document.getElementById('view-order-backdrop');
                            const vmodal = document.getElementById('view-order-modal');
                            const vclose = document.getElementById('view-order-close');
                            if (itemsWrap) {
                                const items = Array.isArray(data.data?.items) ? data.data.items : [];
                                if (items.length === 0) {
                                    itemsWrap.innerHTML = '<div class="text-sm text-muted-foreground">No items for this order.</div>';
                                } else {
                                    itemsWrap.innerHTML = items.map(it => {
                                        const nm = (it.menu_name || 'Item');
                                        const qty = parseFloat(it.oi_quantity || 0);
                                        const price = parseFloat(it.oi_price || 0);
                                        return `<div class="flex items-center justify-between rounded border px-2 py-1 text-sm"><span>${nm}</span><span class="text-gray-700">x${qty} · ₱${price.toFixed(2)}</span></div>`;
                                    }).join('');
                                }
                            }
                            if (vback && vmodal) {
                                vback.style.display='block';
                                vmodal.style.display='flex';
                                vback.setAttribute('aria-hidden','false');
                                vmodal.setAttribute('aria-hidden','false');
                                vback.classList.remove('pointer-events-none');
                                vmodal.classList.remove('pointer-events-none');
                                requestAnimationFrame(()=>{ vback.style.opacity='1'; vmodal.style.opacity='1'; });
                                const hideView = ()=>{
                                    vback.style.opacity='0'; vmodal.style.opacity='0';
                                    setTimeout(()=>{
                                        vback.classList.add('pointer-events-none');
                                        vmodal.classList.add('pointer-events-none');
                                        vback.style.display='none'; vmodal.style.display='none';
                                        vback.setAttribute('aria-hidden','true'); vmodal.setAttribute('aria-hidden','true');
                                    }, 180);
                                };
                                vback.addEventListener('click', hideView, { once: true });
                                vclose && vclose.addEventListener('click', hideView, { once: true });
                            }
                        } catch (_) {
                            alert('Network error.');
                        }
                        return;
                    }
                    if (editBtn) {
                        const id = editBtn.getAttribute('data-edit-order');
                        try {
                            const res = await fetch(`?section=orders&ajax=1&action=get_order&order_id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                            const data = await res.json();
                            if (!data.success) { alert(data.message || 'Failed to fetch order'); return; }
                            const o = data.data || {};
                            if (oidEl) oidEl.value = o.order_id || id;
                            if (ostatusEl) ostatusEl.value = o.order_status || 'pending';
                            if (oneededEl) oneededEl.value = (o.order_needed || '').substring(0,10);
                            if (ostreetEl) ostreetEl.value = o.oa_street || '';
                            if (ocityEl) ocityEl.value = o.oa_city || '';
                            if (oprovEl) oprovEl.value = o.oa_province || '';
                            showOModal();
                        } catch (_) {
                            alert('Network error.');
                        }
                        return;
                    }
                    if (paidBtn) {
                        const id = paidBtn.getAttribute('data-paid-order');
                        if (!confirm('Mark this order as Paid?')) return;
                        try {
                            const fd = new FormData();
                            fd.append('ajax','1');
                            fd.append('action','mark_paid');
                            fd.append('order_id', id);
                            await fetch('?section=orders', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }}).then(r=>r.json());
                            // Refresh the page to update payment column
                            window.location.reload();
                        } catch (_) { alert('Request failed.'); }
                        return;
                    }
                    if (delBtn) {
                        const id = delBtn.getAttribute('data-delete-order');
                        if (!confirm('Delete this order? This will remove its items and address.')) return;
                        try {
                            const fd = new FormData();
                            fd.append('ajax','1');
                            fd.append('action','delete');
                            fd.append('order_id', id);
                            await fetch('?section=orders', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }}).then(r=>r.json());
                            window.location.reload();
                        } catch (_) { alert('Delete failed.'); }
                        return;
                    }
                });

                oform && oform.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    try {
                        const fd = new FormData(oform);
                        fd.append('ajax','1');
                        fd.append('action','update_order');
                        await fetch('?section=orders', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }}).then(r=>r.json());
                        hideOModal();
                        window.location.reload();
                    } catch (_) {
                        alert('Save failed.');
                    }
                });
            }
            // EDIT modal elements
            const editBackdrop = document.getElementById('edit-menu-backdrop');
            const editModal = document.getElementById('edit-menu-modal');
            const editDialog = editModal ? editModal.querySelector('.dialog') : null;
            const editClosers = document.querySelectorAll('.close-edit-menu');
            const editForm = document.getElementById('edit-menu-form');
            const editId = document.getElementById('edit-menu-id');
            const editName = document.getElementById('edit-name');
            const editDesc = document.getElementById('edit-description');
            const editPaxCat = document.getElementById('edit-pax-category');
            const editPiecesWrap = document.getElementById('edit-pieces-wrap');
            const editPiecesCount = document.getElementById('edit-pieces-count');
            const editPrice = document.getElementById('edit-price');
            const editAvail = document.getElementById('edit-availability');
            const editPhoto = document.getElementById('edit-menu-photo');
            const editPreview = document.getElementById('edit-photo-preview');
            const editPhotoClear = document.getElementById('edit-photo-clear');
            const editMsg = document.getElementById('edit-menu-message');
            const editSubmit = document.getElementById('edit-menu-submit');

            function showEditModal() {
                if (!editBackdrop || !editModal) return;
                // Ensure elements are not hidden before animating
                editBackdrop.style.display = 'block';
                editModal.style.display = 'flex';
                editBackdrop.setAttribute('aria-hidden', 'false');
                editModal.setAttribute('aria-hidden', 'false');
                editBackdrop.classList.remove('pointer-events-none');
                editModal.classList.remove('pointer-events-none');
                requestAnimationFrame(() => {
                    editBackdrop.style.opacity = '1';
                    editModal.style.opacity = '1';
                    if (editDialog) editDialog.style.transform = 'scale(1)';
                });
            }
            function hideEditModal() {
                if (!editBackdrop || !editModal) return;
                editBackdrop.style.opacity = '0';
                editModal.style.opacity = '0';
                if (editDialog) editDialog.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    editBackdrop.classList.add('pointer-events-none');
                    editModal.classList.add('pointer-events-none');
                    // Hide from layout to prevent flashes on navigation
                    editBackdrop.style.display = 'none';
                    editModal.style.display = 'none';
                    editBackdrop.setAttribute('aria-hidden', 'true');
                    editModal.setAttribute('aria-hidden', 'true');
                    if (editForm) editForm.reset();
                    if (editPreview) { editPreview.src = ''; editPreview.classList.add('hidden'); }
                    if (editMsg) { editMsg.textContent = ''; editMsg.className = 'text-sm mt-2'; }
                }, 180);
            }

            editClosers.forEach(btn => btn.addEventListener('click', hideEditModal));
            editBackdrop && editBackdrop.addEventListener('click', hideEditModal);

            function setEditPax(paxVal) {
                const val = (paxVal || '').toString().trim().toLowerCase();
                if (val === '6-8' || val === '6–8') {
                    editPaxCat.value = '6-8';
                    editPiecesWrap.classList.add('hidden');
                    editPiecesCount.value = '';
                } else if (val === '10-15' || val === '10–15') {
                    editPaxCat.value = '10-15';
                    editPiecesWrap.classList.add('hidden');
                    editPiecesCount.value = '';
                } else if (val.includes('pieces')) {
                    editPaxCat.value = 'per';
                    // Extract number before 'pieces' if present
                    const m = val.match(/(\d+)\s*pieces/);
                    editPiecesCount.value = m ? parseInt(m[1], 10) : '';
                    editPiecesWrap.classList.remove('hidden');
                } else {
                    editPaxCat.value = '6-8';
                    editPiecesWrap.classList.add('hidden');
                    editPiecesCount.value = '';
                }
            }
            function currentEditPaxString() {
                const cat = editPaxCat ? editPaxCat.value : '';
                if (cat === '6-8' || cat === '10-15') return cat;
                if (cat === 'per') {
                    const n = editPiecesCount && editPiecesCount.value ? parseInt(editPiecesCount.value, 10) : NaN;
                    return Number.isFinite(n) && n > 0 ? `${n} pieces` : 'per pieces';
                }
                return '';
            }
            editPaxCat && editPaxCat.addEventListener('change', () => {
                const show = editPaxCat.value === 'per';
                editPiecesWrap.classList.toggle('hidden', !show);
            });
            if (editPhoto && editPreview) {
                editPhoto.addEventListener('change', () => {
                    const file = editPhoto.files && editPhoto.files[0];
                    if (file) {
                        const url = URL.createObjectURL(file);
                        editPreview.src = url;
                        editPreview.classList.remove('hidden');
                    } else {
                        editPreview.src = '';
                        editPreview.classList.add('hidden');
                    }
                });
            }
            if (editPhotoClear && editPhoto) {
                editPhotoClear.addEventListener('click', () => {
                    editPhoto.value = '';
                    if (editPreview) { editPreview.src = ''; editPreview.classList.add('hidden'); }
                });
            }

            // Open edit modal on edit button click and fetch data
            document.addEventListener('click', async (e) => {
                const editBtn = e.target.closest('.js-edit');
                if (!editBtn) return;
                const id = editBtn.getAttribute('data-menu-id');
                if (!id) return;
                try {
                    const res = await fetch(`?section=products&ajax=1&action=get_menu&menu_id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                    const data = await res.json();
                    if (!data.success) { alert(data.message || 'Failed to fetch menu'); return; }
                    const m = data.data || {};
                    if (editId) editId.value = m.menu_id || id;
                    if (editName) editName.value = m.menu_name || '';
                    if (editDesc) editDesc.value = m.menu_desc || '';
                    if (editPrice) editPrice.value = m.menu_price != null ? m.menu_price : '';
                    if (editAvail) editAvail.value = String(m.menu_avail ?? '1');
                    setEditPax(m.menu_pax || '');
                    if (editPreview) {
                        const pic = m.menu_pic || '';
                        if (pic) {
                            editPreview.src = (pic.startsWith('http') || pic.includes('/')) ? pic : (`../menu/${pic}`);
                            editPreview.classList.remove('hidden');
                        } else {
                            editPreview.src = '';
                            editPreview.classList.add('hidden');
                        }
                    }
                    showEditModal();
                } catch (_) {
                    alert('Network error while fetching menu');
                }
            });

            // Submit edit form via AJAX
            if (editForm) {
                editForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    if (editMsg) { editMsg.textContent = ''; editMsg.className = 'text-sm mt-2'; }
                    editSubmit && (editSubmit.disabled = true);
                    try {
                        const fd = new FormData(editForm);
                        fd.set('section', 'products');
                        fd.set('ajax', '1');
                        fd.set('action', 'update');
                        // Rebuild pax standardized value
                        fd.delete('pax');
                        fd.delete('pax_category');
                        fd.delete('pieces_count');
                        fd.append('pax', currentEditPaxString());
                        const res = await fetch('?section=products', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                        const data = await res.json().catch(() => ({ success:false, message: 'Invalid response'}));
                        if (!data.success) {
                            if (editMsg) { editMsg.textContent = data.message || 'Please fix the errors.'; editMsg.classList.add('text-red-600'); }
                            return;
                        }
                        if (editMsg) { editMsg.textContent = 'Saved successfully.'; editMsg.classList.add('text-green-700'); }
                        // Refresh products list, preserving filters/page
                        const container = document.getElementById('products-results');
                        const filterForm = document.getElementById('products-filter');
                        if (container && filterForm) {
                            const params = new URLSearchParams(new FormData(filterForm));
                            const url = '?' + params.toString();
                            try {
                                container.style.opacity = '0.6';
                                const html = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }}).then(r => r.text());
                                const temp = document.createElement('div');
                                temp.innerHTML = html;
                                const updated = temp.querySelector('#products-results');
                                if (updated) container.innerHTML = updated.innerHTML;
                            } catch {}
                            container.style.opacity = '1';
                        }
                        setTimeout(hideEditModal, 250);
                    } catch (_) {
                        if (editMsg) { editMsg.textContent = 'Network error. Please try again.'; editMsg.classList.add('text-red-600'); }
                    } finally {
                        editSubmit && (editSubmit.disabled = false);
                    }
                });
            }

            const openers = document.querySelectorAll('.open-add-menu');
            const backdrop = document.getElementById('add-menu-backdrop');
            const modal = document.getElementById('add-menu-modal');
            const dialog = modal ? modal.querySelector('.dialog') : null;
            const closers = document.querySelectorAll('.close-add-menu');
            const form = document.getElementById('add-menu-form');
            const preview = document.getElementById('photo-preview');
            const photoInput = document.getElementById('menu-photo');
            const photoClear = document.getElementById('photo-clear');
            const msg = document.getElementById('add-menu-message');
            const submitBtn = document.getElementById('add-menu-submit');
            const paxCategory = document.getElementById('pax-category');
            const piecesWrap = document.getElementById('pieces-wrap');
            const piecesCount = document.getElementById('pieces-count');

            function showModal() {
                if (!backdrop || !modal) return;
                // Ensure elements are not hidden before animating
                backdrop.style.display = 'block';
                modal.style.display = 'flex';
                backdrop.setAttribute('aria-hidden', 'false');
                modal.setAttribute('aria-hidden', 'false');
                backdrop.classList.remove('pointer-events-none');
                modal.classList.remove('pointer-events-none');
                requestAnimationFrame(() => {
                    backdrop.style.opacity = '1';
                    modal.style.opacity = '1';
                    if (dialog) dialog.style.transform = 'scale(1)';
                });
            }
            function hideModal() {
                if (!backdrop || !modal) return;
                backdrop.style.opacity = '0';
                modal.style.opacity = '0';
                if (dialog) dialog.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    backdrop.classList.add('pointer-events-none');
                    modal.classList.add('pointer-events-none');
                    // Hide from layout to prevent flashes on navigation
                    backdrop.style.display = 'none';
                    modal.style.display = 'none';
                    backdrop.setAttribute('aria-hidden', 'true');
                    modal.setAttribute('aria-hidden', 'true');
                    if (form) form.reset();
                    if (preview) { preview.src = ''; preview.classList.add('hidden'); }
                    clearErrors();
                    if (msg) { msg.textContent = ''; msg.className = 'text-sm mt-2'; }
                }, 180);
            }

            function clearErrors() {
                document.querySelectorAll('[data-error]').forEach(el => el.textContent = '');
            }

            openers.forEach(btn => btn.addEventListener('click', showModal));
            closers.forEach(btn => btn.addEventListener('click', hideModal));
            backdrop && backdrop.addEventListener('click', hideModal);

            // PAX category toggle
            function updatePiecesVisibility() {
                if (!paxCategory || !piecesWrap) return;
                const show = paxCategory.value === 'per';
                piecesWrap.classList.toggle('hidden', !show);
            }
            paxCategory && paxCategory.addEventListener('change', updatePiecesVisibility);
            updatePiecesVisibility();

            // Image preview
            if (photoInput && preview) {
                photoInput.addEventListener('change', () => {
                    const file = photoInput.files && photoInput.files[0];
                    if (file) {
                        const url = URL.createObjectURL(file);
                        preview.src = url;
                        preview.classList.remove('hidden');
                    } else {
                        preview.src = '';
                        preview.classList.add('hidden');
                    }
                });
            }

            // Clear photo
            if (photoClear && photoInput && preview) {
                photoClear.addEventListener('click', () => {
                    photoInput.value = '';
                    preview.src = '';
                    preview.classList.add('hidden');
                });
            }

            // Submit via AJAX
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    clearErrors();
                    if (msg) { msg.textContent = ''; msg.className = 'text-sm mt-2'; }
                    submitBtn && (submitBtn.disabled = true);
                    try {
                        const formData = new FormData(form);
                        // Build pax value based on category/pieces
                        const cat = paxCategory ? paxCategory.value : '';
                        let paxValue = '';
                        if (cat === '6-8' || cat === '10-15') {
                            paxValue = cat;
                        } else if (cat === 'per') {
                            const count = piecesCount && piecesCount.value ? parseInt(piecesCount.value, 10) : NaN;
                            paxValue = Number.isFinite(count) && count > 0 ? `${count} pieces` : 'per pieces';
                        }
                        formData.delete('pax');
                        formData.delete('pax_category');
                        formData.delete('pieces_count');
                        formData.append('pax', paxValue);
                        // API expects: name, description, pax, price, availability, photo
                        const res = await fetch('api_add_menu.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
                        if (!res.ok || !data.success) {
                            const errors = data.errors || {};
                            Object.keys(errors).forEach(k => {
                                const el = document.querySelector(`[data-error="${k}"]`);
                                if (el) el.textContent = errors[k];
                            });
                            if (msg) { msg.textContent = data.message || 'Please fix the errors.'; msg.classList.add('text-red-600'); }
                            return;
                        }
                        if (msg) { msg.textContent = 'Menu added successfully.'; msg.classList.add('text-green-700'); }
                        // Refresh products list if we are on products section
                        const productsNav = document.getElementById('nav-products');
                        const isOnProducts = productsNav && productsNav.classList.contains('active');
                        if (isOnProducts) {
                            const container = document.getElementById('products-results');
                            const filterForm = document.getElementById('products-filter');
                            if (container && filterForm) {
                                const params = new URLSearchParams(new FormData(filterForm));
                                params.set('page', '1'); // show newest on first page
                                const url = '?' + params.toString();
                                container.style.opacity = '0.6';
                                try {
                                    const html = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }}).then(r => r.text());
                                    const temp = document.createElement('div');
                                    temp.innerHTML = html;
                                    const updated = temp.querySelector('#products-results');
                                    if (updated) container.innerHTML = updated.innerHTML;
                                } catch {}
                                container.style.opacity = '1';
                            }
                        }
                        // Close after a brief delay
                        setTimeout(hideModal, 300);
                    } catch (err) {
                        if (msg) { msg.textContent = 'Network error. Please try again.'; msg.classList.add('text-red-600'); }
                    } finally {
                        submitBtn && (submitBtn.disabled = false);
                    }
                });
            }
        });
    </script>
    
                    
                                <option value="per">per pieces</option>
                            </select>
                            <p class="text-xs text-red-600 mt-1" data-error="pax"></p>
                        </div>
                        <div id="pieces-wrap" class="hidden">
                            <label class="text-sm text-muted-foreground">Pieces count</label>
                            <input name="pieces_count" id="pieces-count" type="number" min="1" step="1" placeholder="e.g., 6" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Price (₱)</label>
                            <input name="price" type="number" min="0" step="0.01" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                            <p class="text-xs text-red-600 mt-1" data-error="price"></p>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Availability</label>
                            <select name="availability" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="1" selected>Available</option>
                                <option value="0">Unavailable</option>
                            </select>
                            <p class="text-xs text-red-600 mt-1" data-error="availability"></p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Description</label>
                            <textarea name="description" rows="3" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                            <p class="text-xs text-red-600 mt-1" data-error="description"></p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Photo</label>
                            <input id="menu-photo" name="photo" type="file" accept="image/*" class="block w-full mt-1 text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-green-700" />
                            <div class="mt-3 flex items-start gap-3">
                                <img id="photo-preview" src="" alt="Preview" class="hidden w-24 h-24 object-cover rounded border" />
                                <div class="flex flex-col gap-2">
                                    <p class="text-xs text-muted-foreground">Recommended: square image 512x512+. Supported formats: JPG, PNG, WebP.</p>
                                    <button type="button" id="photo-clear" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-gray-200 text-xs hover:bg-gray-50">
                                        <i class="fas fa-eraser fa-xs"></i>
                                        Clear photo
                                    </button>
                                </div>
                            </div>
                            <p class="text-xs text-red-600 mt-1" data-error="photo"></p>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" class="close-add-menu px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700 disabled:opacity-60" id="add-menu-submit">Save</button>
                    </div>
                    <p class="text-sm mt-2" id="add-menu-message"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Menu Modal -->
    <div id="edit-menu-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="edit-menu-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="dialog w-full max-w-xl mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 class="text-lg font-semibold text-primary">Edit Menu Item</h3>
                    <button type="button" class="close-edit-menu p-2 hover:bg-gray-100 rounded">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="edit-menu-form" class="p-4 space-y-4" enctype="multipart/form-data">
                    <input type="hidden" name="menu_id" id="edit-menu-id" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-muted-foreground">Name</label>
                            <input name="name" id="edit-name" type="text" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">PAX</label>
                            <select name="pax_category" id="edit-pax-category" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="6-8">6-8 pax</option>
                                <option value="10-15">10-15 pax</option>
                                <option value="per">per pieces</option>
                            </select>
                        </div>
                        <div id="edit-pieces-wrap" class="hidden">
                            <label class="text-sm text-muted-foreground">Pieces count</label>
                            <input name="pieces_count" id="edit-pieces-count" type="number" min="1" step="1" placeholder="e.g., 6" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Price (₱)</label>
                            <input name="price" id="edit-price" type="number" min="0" step="0.01" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Availability</label>
                            <select name="availability" id="edit-availability" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="1">Available</option>
                                <option value="0">Unavailable</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Description</label>
                            <textarea name="description" id="edit-description" rows="3" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Photo (optional)</label>
                            <input id="edit-menu-photo" name="photo" type="file" accept="image/*" class="block w-full mt-1 text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-green-700" />
                            <div class="mt-3 flex items-start gap-3">
                                <img id="edit-photo-preview" src="" alt="Preview" class="hidden w-24 h-24 object-cover rounded border" />
                                <div class="flex flex-col gap-2">
                                    <p class="text-xs text-muted-foreground">Leave empty to keep current photo.</p>
                                    <button type="button" id="edit-photo-clear" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-gray-200 text-xs hover:bg-gray-50">
                                        <i class="fas fa-eraser fa-xs"></i>
                                        Clear new photo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" class="close-edit-menu px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700 disabled:opacity-60" id="edit-menu-submit">Save changes</button>
                    </div>
                    <p class="text-sm mt-2" id="edit-menu-message"></p>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Order Modal -->
    <div id="edit-order-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="edit-order-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="w-full max-w-md mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 class="text-lg font-semibold text-primary">Edit Order</h3>
                    <button type="button" id="edit-order-cancel" class="p-2 hover:bg-gray-100 rounded">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="edit-order-form" class="p-4 space-y-4">
                    <input type="hidden" name="order_id" id="edit-order-id" />
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="text-sm text-muted-foreground">Order Status</label>
                            <select name="order_status" id="edit-order-status" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="pending">Pending</option>
                                <option value="in progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Date Needed</label>
                            <input type="date" name="order_needed" id="edit-order-needed" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Street</label>
                            <input type="text" name="oa_street" id="edit-oa-street" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm text-muted-foreground">City</label>
                                <input type="text" name="oa_city" id="edit-oa-city" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                            </div>
                            <div>
                                <label class="text-sm text-muted-foreground">Province</label>
                                <input type="text" name="oa_province" id="edit-oa-province" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" id="edit-order-cancel-2" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Booking Modal -->
    <div id="bk-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="bk-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="w-full max-w-2xl mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 id="bk-modal-title" class="text-lg font-semibold text-primary">Edit Booking</h3>
                    <button type="button" id="bk-close" class="p-2 hover:bg-gray-100 rounded"><i class="fas fa-times"></i></button>
                </div>
                <form id="bk-form" class="p-4 space-y-4" method="POST">
                    <input type="hidden" name="section" value="bookings" />
                    <input type="hidden" name="ajax" value="1" />
                    <input type="hidden" name="action" id="bk-action" value="update" />
                    <input type="hidden" name="booking_id" id="bk-id" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-muted-foreground">Name</label>
                            <input type="text" name="eb_name" id="bk-name" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Contact</label>
                            <input type="text" name="eb_contact" id="bk-contact" required minlength="11" maxlength="11" pattern="\d{11}" inputmode="numeric" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Type</label>
                            <input type="text" name="eb_type" id="bk-type-input" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Venue</label>
                            <input type="text" name="eb_venue" id="bk-venue" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Date & Time</label>
                            <input type="datetime-local" name="eb_date" id="bk-date-input" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Order</label>
                            <select name="eb_order" id="bk-order-input" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="customize">Customize</option>
                                <option value="party trays">Party trays</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Package Pax</label>
                            <select name="eb_package_pax" id="bk-pax" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">None</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="150">150</option>
                                <option value="200">200</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Add-on Pax</label>
                            <input type="number" name="eb_addon_pax" id="bk-addon" min="0" step="1" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Status</label>
                            <select name="eb_status" id="bk-status-input" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="Pending">Pending</option>
                                <option value="Confirmed">Confirmed</option>
                                <option value="Downpayment">Downpayment</option>
                                <option value="Completed">Completed</option>
                                <option value="Paid">Paid</option>
                                <option value="Canceled">Canceled</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Notes</label>
                            <textarea name="eb_notes" id="bk-notes" rows="3" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" id="bk-cancel" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Order Items Modal -->
    <div id="view-order-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="view-order-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="w-full max-w-md mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 border-b">
                    <h3 class="text-lg font-medium">Order Items</h3>
                </div>
                <div class="p-4">
                    <div id="view-order-items" class="space-y-2"></div>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <button id="view-order-close" type="button" class="px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50">Close</button>
                </div>
            </div>
        </div>

        <!-- Best Sellers Modal -->
        <div id="best-sellers-modal" class="fixed inset-0 z-50 items-center justify-center bg-black/50 hidden">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
                <div class="p-4 border-b flex items-center justify-between">
                    <h3 class="text-lg font-medium text-primary">Top 10 Best Sellers</h3>
                    <button id="best-sellers-close" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-4">
                    <div id="best-sellers-body" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-2">Rank</th>
                                    <th class="text-left px-4 py-2">Menu Item</th>
                                    <th class="text-right px-4 py-2">Qty Sold</th>
                                </tr>
                            </thead>
                            <tbody id="best-sellers-rows" class="divide-y divide-gray-100">
                                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-4 border-t text-right">
                    <button id="best-sellers-close-2" class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Paid Booking Modal -->
    <div id="bk-paid-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="bk-paid-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="w-full max-w-md mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 class="text-lg font-semibold text-primary">Record Booking Payment</h3>
                    <button type="button" id="bk-paid-close" class="p-2 hover:bg-gray-100 rounded"><i class="fas fa-times"></i></button>
                </div>
                <form id="bk-paid-form" class="p-4 space-y-4" method="POST">
                    <input type="hidden" name="section" value="bookings" />
                    <input type="hidden" name="ajax" value="1" />
                    <input type="hidden" name="action" value="mark_paid" />
                    <input type="hidden" name="booking_id" id="bk-paid-id" />
                    <div>
                        <label class="text-sm text-muted-foreground">Payment Method</label>
                        <select name="pay_method" id="bk-paid-method" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="Credit">Credit</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-muted-foreground">Amount</label>
                        <input type="number" name="pay_amount" id="bk-paid-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" id="bk-paid-cancel" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Catering Package Modal -->
    <div id="cp-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="cp-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="w-full max-w-2xl mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 id="cp-modal-title" class="text-lg font-semibold text-primary">Edit Catering Package</h3>
                    <button type="button" id="cp-close" class="p-2 hover:bg-gray-100 rounded"><i class="fas fa-times"></i></button>
                </div>
                <form id="cp-form" class="p-4 space-y-4" method="POST">
                    <input type="hidden" name="section" value="catering" />
                    <input type="hidden" name="ajax" value="1" />
                    <input type="hidden" name="action" id="cp-action" value="update" />
                    <input type="hidden" name="cp_id" id="cp-id" />
                    <input type="hidden" name="cp_pay_id" id="cp-pay-id" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-muted-foreground">Name</label>
                            <input type="text" name="cp_name" id="cp-name" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Phone</label>
                            <input type="text" name="cp_phone" id="cp-phone" required minlength="11" maxlength="11" pattern="\d{11}" inputmode="numeric" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Place</label>
                            <input type="text" name="cp_place" id="cp-place" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Date</label>
                            <input type="date" name="cp_date" id="cp-date-input" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Price</label>
                            <input type="number" name="cp_price" id="cp-price" step="0.01" min="0" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Add-on Pax</label>
                            <input type="number" name="cp_addon_pax" id="cp-addon" min="0" step="1" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Notes</label>
                            <textarea name="cp_notes" id="cp-notes" rows="3" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Payment Method</label>
                            <select name="cp_pay_method" id="cp-pay-method" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">None</option>
                                <option value="Cash">Cash</option>
                                <option value="Online">Online</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Payment Amount</label>
                            <input type="number" name="cp_pay_amount" id="cp-pay-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Payment Status</label>
                            <select name="cp_pay_status" id="cp-pay-status" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">None</option>
                                <option value="Pending">Pending</option>
                                <option value="Partial">Partial</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" id="cp-cancel" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Catering Payment Modal -->
    <div id="cp-paid-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="cp-paid-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="w-full max-w-md mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 class="text-lg font-semibold text-primary">Mark Paid</h3>
                    <button type="button" id="cp-paid-close" class="p-2 hover:bg-gray-100 rounded"><i class="fas fa-times"></i></button>
                </div>
                <form id="cp-paid-form" class="p-4 space-y-4" method="POST">
                    <input type="hidden" name="section" value="catering" />
                    <input type="hidden" name="ajax" value="1" />
                    <input type="hidden" name="action" value="mark_paid" />
                    <input type="hidden" name="cp_id" id="cp-paid-id" />
                    <div>
                        <label class="text-sm text-muted-foreground">Payment Method</label>
                        <select name="pay_method" id="cp-paid-method" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="Credit">Credit</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-muted-foreground">Amount</label>
                        <input type="number" name="pay_amount" id="cp-paid-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" id="cp-paid-cancel" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Add/Edit Employee Modal -->
    <div id="emp-backdrop" class="fixed inset-0 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true"></div>
    <div id="emp-modal" class="fixed inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" style="display:none" aria-hidden="true">
        <div class="dialog w-full max-w-xl mx-4 scale-95 transition-transform duration-200">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 id="emp-modal-title" class="text-lg font-semibold text-primary">Add Employee</h3>
                    <button type="button" id="emp-close" class="p-2 hover:bg-gray-100 rounded"><i class="fas fa-times"></i></button>
                </div>
                <form id="emp-form" class="p-4 space-y-4" enctype="multipart/form-data" method="POST">
                    <input type="hidden" name="section" value="employees" />
                    <input type="hidden" name="ajax" value="1" />
                    <input type="hidden" name="action" id="emp-action" value="create" />
                    <input type="hidden" name="employee_id" id="emp-id" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-muted-foreground">First Name</label>
                            <input type="text" name="emp_fn" id="emp-fn" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Last Name</label>
                            <input type="text" name="emp_ln" id="emp-ln" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Sex</label>
                            <select name="emp_sex" id="emp-sex-input" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Email</label>
                            <input type="email" name="emp_email" id="emp-email" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Phone</label>
                            <input type="text" name="emp_phone" id="emp-phone" required minlength="11" maxlength="11" pattern="\d{11}" inputmode="numeric" class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-sm text-muted-foreground">Role</label>
                            <input type="text" name="emp_role" id="emp-role-input" required class="w-full mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                <input type="checkbox" name="emp_avail" id="emp-avail-input" class="rounded border-gray-300" checked />
                                Available
                            </label>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-muted-foreground">Photo</label>
                            <input id="emp-photo" name="emp_photo" type="file" accept="image/*" class="block w-full mt-1 text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-green-700" />
                            <div class="mt-3 flex items-start gap-3">
                                <img id="emp-photo-preview" src="" alt="Preview" class="hidden w-24 h-24 object-cover rounded border" />
                                <div class="flex flex-col gap-2">
                                    <p class="text-xs text-muted-foreground">Optional. Square image 512x512+ recommended.</p>
                                    <button type="button" id="emp-photo-clear" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-gray-200 text-xs hover:bg-gray-50">
                                        <i class="fas fa-eraser fa-xs"></i>
                                        Clear photo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button type="button" id="emp-cancel" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-green-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>