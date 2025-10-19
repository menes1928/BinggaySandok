<?php
// Inject server-side values for the logged-in user, leaving UI/UX intact
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../classes/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login');
    exit;
}

$db = new database();
$pdo = $db->opencon();

// Handle AJAX profile update (JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Attempt to parse JSON body; fallback to form fields
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }

    // Branch: photo upload via multipart/form-data
    if ((isset($_POST['action']) && $_POST['action'] === 'upload_photo') || (isset($data['action']) && $data['action'] === 'upload_photo')) {
        header('Content-Type: application/json');
        $uid = (int)$_SESSION['user_id'];

        if (!isset($_FILES['photo']) || !is_array($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'message' => 'No file uploaded.']);
            exit;
        }

        $file = $_FILES['photo'];
        // Validate size (max 5MB)
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'message' => 'File too large. Max 5MB.']);
            exit;
        }

        // Validate mime using finfo
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : mime_content_type($file['tmp_name']);
        if ($finfo) { finfo_close($finfo); }
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            echo json_encode(['ok' => false, 'message' => 'Invalid image type.']);
            exit;
        }

        // Prepare destination
        $ext = $allowed[$mime];
        $rand = bin2hex(random_bytes(6));
        $baseName = 'profile_' . $uid . '_' . time() . '_' . $rand . '.' . $ext;
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
        $destFs = $uploadDir . DIRECTORY_SEPARATOR . $baseName;
        $relPath = 'uploads/profile/' . $baseName;

        if (!move_uploaded_file($file['tmp_name'], $destFs)) {
            echo json_encode(['ok' => false, 'message' => 'Failed to save uploaded file.']);
            exit;
        }

        // Update DB path
        try {
            $st = $pdo->prepare('UPDATE users SET user_photo = ?, updated_at = NOW() WHERE user_id = ?');
            $st->execute([$relPath, $uid]);
            // Update session for immediate navbar/profile reflect
            $_SESSION['user_photo'] = $relPath;

            // Build a web URL relative to this file (../uploads/...)
            $photoUrl = '../' . $relPath;
            echo json_encode(['ok' => true, 'message' => 'Photo updated.', 'photo' => $relPath, 'photo_url' => $photoUrl]);
            exit;
        } catch (Throwable $e) {
            @unlink($destFs);
            echo json_encode(['ok' => false, 'message' => 'Database update failed.']);
            exit;
        }
    }

    // Branch: fetch order details
    if (isset($data['action']) && $data['action'] === 'get_order_details') {
        header('Content-Type: application/json');
        $uid = (int)$_SESSION['user_id'];
        $oid = isset($data['order_id']) ? (int)$data['order_id'] : 0;
        if ($oid <= 0) { echo json_encode(['ok'=>false,'message'=>'Invalid order.']); exit; }
        try {
            $o = $pdo->prepare('SELECT order_id, order_status, order_amount, order_needed, order_date, created_at FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1');
            $o->execute([$oid, $uid]);
            $order = $o->fetch(PDO::FETCH_ASSOC);
            if (!$order) { echo json_encode(['ok'=>false,'message'=>'Order not found.']); exit; }
            $it = $pdo->prepare('SELECT oi.oi_quantity AS qty, oi.oi_price AS price, m.menu_name AS name FROM orderitems oi JOIN menu m ON m.menu_id = oi.menu_id WHERE oi.order_id = ?');
            $it->execute([$oid]);
            $items = $it->fetchAll(PDO::FETCH_ASSOC);
            $ad = $pdo->prepare('SELECT oa_street, oa_city, oa_province FROM orderaddress WHERE order_id = ? LIMIT 1');
            $ad->execute([$oid]);
            $addr = $ad->fetch(PDO::FETCH_ASSOC) ?: null;
            echo json_encode(['ok'=>true,'order'=>$order,'items'=>$items,'address'=>$addr]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok'=>false,'message'=>'Failed to load order details.']);
            exit;
        }
    }

    if (isset($data['action']) && $data['action'] === 'update_profile') {
        header('Content-Type: application/json');
        $uid = (int)$_SESSION['user_id'];

        $fn = trim($data['first_name'] ?? '');
        $ln = trim($data['last_name'] ?? '');
        $un = trim($data['username'] ?? '');
        $em = trim($data['email'] ?? '');
        $ph = trim($data['phone'] ?? '');

        // Basic validations
        $errors = [];
        if ($fn === '') { $errors['first_name'] = 'First name is required'; }
        if ($ln === '') { $errors['last_name'] = 'Last name is required'; }
        if ($un === '') { $errors['username'] = 'Username is required'; }
        if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Valid email is required'; }
        if (!empty($errors)) {
            echo json_encode(['ok' => false, 'errors' => $errors, 'message' => 'Please fix the highlighted fields.']);
            exit;
        }

        try {
            // Uniqueness checks for username/email (exclude current user)
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_username = ? AND user_id <> ?');
            $st->execute([$un, $uid]);
            if ((int)$st->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'errors' => ['username' => 'Username is already taken'], 'message' => 'Username already in use.']);
                exit;
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_email = ? AND user_id <> ?');
            $st->execute([$em, $uid]);
            if ((int)$st->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'errors' => ['email' => 'Email is already taken'], 'message' => 'Email already in use.']);
                exit;
            }

            // Perform update
            $up = $pdo->prepare('UPDATE users SET user_fn = ?, user_ln = ?, user_username = ?, user_email = ?, user_phone = ?, updated_at = NOW() WHERE user_id = ?');
            $up->execute([$fn, $ln, $un, $em, $ph, $uid]);
            $affected = $up->rowCount();

            echo json_encode(['ok' => true, 'message' => 'Profile updated successfully.', 'affected' => $affected]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Update failed. Please try again later.', 'error' => $e->getMessage()]);
            exit;
        }
    }
    // Real-time counts for stats
    if (isset($data['action']) && $data['action'] === 'get_counts') {
        header('Content-Type: application/json');
        $uid = (int)$_SESSION['user_id'];
        try {
            $c1 = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?'); $c1->execute([$uid]); $orders = (int)$c1->fetchColumn();
            $c2 = $pdo->prepare('SELECT COUNT(*) FROM eventbookings WHERE user_id = ?'); $c2->execute([$uid]); $bookings = (int)$c2->fetchColumn();
            $c3 = $pdo->prepare('SELECT COUNT(*) FROM cateringpackages WHERE user_id = ?'); $c3->execute([$uid]); $catering = (int)$c3->fetchColumn();
            echo json_encode(['ok'=>true,'orders'=>$orders,'bookings'=>$bookings,'catering'=>$catering]);
        } catch (Throwable $e) { echo json_encode(['ok'=>false]); }
        exit;
    }
    // List user's catering inquiries
    if (isset($data['action']) && $data['action'] === 'list_catering') {
        header('Content-Type: application/json');
        $uid = (int)$_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare('SELECT cp_id, cp_place, cp_date, cp_price, cp_addon_pax, cp_notes FROM cateringpackages WHERE user_id = ? ORDER BY created_at DESC, cp_id DESC');
            $stmt->execute([$uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            // Latest payment per cp (if exists)
            $payStmt = $pdo->prepare('SELECT pay_amount, pay_method, pay_status, pay_date FROM payments WHERE cp_id = ? ORDER BY pay_date DESC, pay_id DESC LIMIT 1');
            $items = [];
            foreach ($rows as $r) {
                $p = null;
                if (!empty($r['cp_id'])) { $payStmt->execute([(int)$r['cp_id']]); $p = $payStmt->fetch(PDO::FETCH_ASSOC) ?: null; }
                $items[] = [
                    'id' => (int)$r['cp_id'],
                    'place' => (string)$r['cp_place'],
                    'date' => (string)$r['cp_date'],
                    'addons' => (string)($r['cp_addon_pax'] ?? ''),
                    'notes' => (string)($r['cp_notes'] ?? ''),
                    'price' => (float)$r['cp_price'],
                    'payment' => $p ? [
                        'amount' => (float)($p['pay_amount'] ?? 0),
                        'method' => (string)($p['pay_method'] ?? ''),
                        'status' => (string)($p['pay_status'] ?? ''),
                        'date' => (string)($p['pay_date'] ?? '')
                    ] : null,
                    'contract' => 'javascript:void(0)'
                ];
            }
            echo json_encode(['ok'=>true,'items'=>$items]);
        } catch (Throwable $e) { echo json_encode(['ok'=>false,'message'=>'Failed to load catering.']); }
        exit;
    }
    // List user's bookings
    if (isset($data['action']) && $data['action'] === 'list_bookings') {
        header('Content-Type: application/json');
        $uid = (int)$_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare('SELECT eb.eb_id, eb.event_type_id, eb.package_id, eb.eb_venue, eb.eb_date, eb.eb_addon_pax, eb.eb_status, eb.eb_notes, eb.eb_order, et.name AS et_name, p.name AS pkg_name, p.pax AS pkg_pax, p.base_price AS pkg_price FROM eventbookings eb LEFT JOIN event_types et ON et.event_type_id=eb.event_type_id LEFT JOIN packages p ON p.package_id = eb.package_id WHERE eb.user_id = ? ORDER BY eb.created_at DESC, eb.eb_id DESC');
            $stmt->execute([$uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];
            foreach ($rows as $r) {
                $pkg = null; if (!empty($r['pkg_name'])) { $pkg = ['name'=>(string)$r['pkg_name'],'pax'=>(string)$r['pkg_pax'],'price'=>(float)$r['pkg_price']]; }
                $items[] = [
                    'id' => (int)$r['eb_id'],
                    'event_type' => (string)($r['et_name'] ?? ''),
                    'package' => $pkg,
                    'orderLabel' => (string)($r['eb_order'] ?? ''),
                    'venue' => (string)($r['eb_venue'] ?? ''),
                    'date' => (string)($r['eb_date'] ?? ''),
                    'addons' => (string)($r['eb_addon_pax'] ?? ''),
                    'status' => (string)($r['eb_status'] ?? ''),
                    'notes' => (string)($r['eb_notes'] ?? ''),
                    'contract' => '../admin/admin.php?section=bookings&action=contract&booking_id='.(int)$r['eb_id'].'&ajax=1'
                ];
            }
            echo json_encode(['ok'=>true,'items'=>$items]);
        } catch (Throwable $e) { echo json_encode(['ok'=>false,'message'=>'Failed to load bookings.']); }
        exit;
    }
    // Cancel/Delete actions
    if (isset($data['action']) && $data['action'] === 'cancel_booking') {
        header('Content-Type: application/json');
        $id = (int)($data['id'] ?? 0); $uid=(int)$_SESSION['user_id'];
        if ($id<=0) { echo json_encode(['ok'=>false,'message'=>'Invalid.']); exit; }
        try { $pdo->prepare("UPDATE eventbookings SET eb_status='Canceled' WHERE eb_id=? AND user_id=?")->execute([$id,$uid]); echo json_encode(['ok'=>true]); } catch (Throwable $e) { echo json_encode(['ok'=>false,'message'=>'Failed to cancel.']); }
        exit;
    }
    if (isset($data['action']) && $data['action'] === 'delete_booking') {
        header('Content-Type: application/json');
        $id = (int)($data['id'] ?? 0); $uid=(int)$_SESSION['user_id'];
        if ($id<=0) { echo json_encode(['ok'=>false,'message'=>'Invalid.']); exit; }
        try { $pdo->prepare('DELETE FROM eventbookings WHERE eb_id=? AND user_id=?')->execute([$id,$uid]); echo json_encode(['ok'=>true]); } catch (Throwable $e) { echo json_encode(['ok'=>false,'message'=>'Failed to delete.']); }
        exit;
    }
    if (isset($data['action']) && $data['action'] === 'cancel_catering') {
        header('Content-Type: application/json');
        $id = (int)($data['id'] ?? 0); $uid=(int)$_SESSION['user_id'];
        if ($id<=0) { echo json_encode(['ok'=>false,'message'=>'Invalid.']); exit; }
        try {
            // Soft-cancel by appending to notes
            $pdo->prepare("UPDATE cateringpackages SET cp_notes = CONCAT(IFNULL(cp_notes,''), ' ', '(Canceled on ', DATE(NOW()), ')') WHERE cp_id=? AND user_id=?")
                ->execute([$id,$uid]);
            echo json_encode(['ok'=>true]);
        } catch (Throwable $e) { echo json_encode(['ok'=>false,'message'=>'Failed to cancel.']); }
        exit;
    }
    if (isset($data['action']) && $data['action'] === 'delete_catering') {
        header('Content-Type: application/json');
        $id = (int)($data['id'] ?? 0); $uid=(int)$_SESSION['user_id'];
        if ($id<=0) { echo json_encode(['ok'=>false,'message'=>'Invalid.']); exit; }
        try { $pdo->prepare('DELETE FROM cateringpackages WHERE cp_id=? AND user_id=?')->execute([$id,$uid]); echo json_encode(['ok'=>true]); } catch (Throwable $e) { echo json_encode(['ok'=>false,'message'=>'Failed to delete.']); }
        exit;
    }
}
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([ (int)$_SESSION['user_id'] ]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$firstName = $user['user_fn'] ?? '';
$lastName  = $user['user_ln'] ?? '';
$username  = $user['user_username'] ?? '';
$email     = $user['user_email'] ?? '';
$phone     = $user['user_phone'] ?? '';
$createdAt = $user['created_at'] ?? '';

// Normalize profile photo path for display
$photoRaw = $_SESSION['user_photo'] ?? ($user['user_photo'] ?? '');
$photoWeb = null;
if (!empty($photoRaw)) {
    $rawTrim = trim($photoRaw);
    if (preg_match('#^https?://#i', $rawTrim)) {
        $photoWeb = $rawTrim; // external URL stored
    } else {
        // Normalize path variants (absolute FS path, leading slash, './', 'Binggay/uploads/...', etc.)
        $norm = str_replace('\\', '/', $rawTrim);
        $pos = strpos($norm, 'uploads/');
        $rel = $pos !== false ? substr($norm, $pos) : ltrim($norm, '/.');

        // Check filesystem path to prefer a guaranteed relative URL
        $fsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (file_exists($fsPath)) {
            $photoWeb = '../' . $rel;
        } else {
            // Fallback to site-root with project folder
            $projectBase = basename(dirname(__DIR__)); // 'Binggay'
            $photoWeb = '/' . $projectBase . '/' . $rel;
        }
    }
}

// Dynamic stats
// Initial counts (will be kept up-to-date via polling)
$q1 = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$q1->execute([ (int)$_SESSION['user_id'] ]);
$totalOrders = (int)$q1->fetchColumn();

$q2 = $pdo->prepare('SELECT COUNT(*) FROM eventbookings WHERE user_id = ?');
$q2->execute([ (int)$_SESSION['user_id'] ]);
$totalBookings = (int)$q2->fetchColumn();

$q3 = $pdo->prepare('SELECT COUNT(*) FROM cateringpackages WHERE user_id = ?');
$q3->execute([ (int)$_SESSION['user_id'] ]);
$totalCatering = (int)$q3->fetchColumn();

// Recent orders for right-column history
$qOrders = $pdo->prepare('SELECT order_id, order_status, order_amount, order_needed, order_date, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, order_id DESC LIMIT 5');
$qOrders->execute([ (int)$_SESSION['user_id'] ]);
$recentOrders = $qOrders->fetchAll(PDO::FETCH_ASSOC);

$welcomeName = trim(($firstName . ' ' . $lastName)) ?: ($username ?: 'User');
$initials = strtoupper((($firstName !== '' ? substr($firstName,0,1) : '') . ($lastName !== '' ? substr($lastName,0,1) : '')));
if ($initials === '') { $initials = strtoupper(substr($username, 0, 2)); }

$createdYear = '';
$createdPretty = '';
if ($createdAt) {
    $ts = strtotime($createdAt);
    if ($ts) {
        $createdYear = date('Y', $ts);
        $createdPretty = date('M j, Y', $ts);
    }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Sandok ni Binggay</title>
    <!-- TailwindCSS (required for shared navbar styles) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1B4332;
            --accent: #D4AF37;
            --background: #fefffe;
            --foreground: #1a2e1a;
            --muted: #f8f8f6;
            --muted-foreground: #6b7062;
            --border: rgba(27, 67, 50, 0.1);
            --destructive: #d4183d;
            --chart-3: #2D5A3D;
            --chart-4: #E8C547;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to bottom, var(--background), var(--muted));
            color: var(--foreground);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Standalone header styles removed – navbar-user.php supplies the navigation */

        .btn {
            padding: 0.625rem 1.5rem;
            border: 2px solid var(--primary);
            background: transparent;
            color: var(--primary);
            border-radius: 0.625rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(27, 67, 50, 0.3);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--chart-3);
        }

        .btn-accent {
            background: var(--accent);
            color: var(--foreground);
            border-color: var(--accent);
        }

        .btn-accent:hover {
            background: var(--chart-4);
            border-color: var(--chart-4);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Extra small button for dense actions in modals */
        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.1;
            border-width: 1.5px;
            border-radius: 0.5rem;
        }

        .btn-destructive {
            border-color: var(--destructive);
            color: var(--destructive);
        }

        .btn-destructive:hover {
            background: var(--destructive);
            color: white;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(to right, var(--primary), var(--chart-3));
            border-radius: 1.25rem;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(27, 67, 50, 0.3);
            animation: fadeInUp 0.5s ease;
        }

        .welcome-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .welcome-text h2 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
        }

        .welcome-text p {
            color: rgba(255, 255, 255, 0.9);
        }

        .welcome-icon {
            font-size: 4rem;
            color: var(--accent);
            opacity: 0.8;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 2px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            animation: fadeInUp 0.5s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px) scale(1.05);
            border-color: rgba(27, 67, 50, 0.5);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info p:first-child {
            font-size: 0.875rem;
            color: var(--muted-foreground);
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .icon-primary { background: rgba(27, 67, 50, 0.1); color: var(--primary); }
        .icon-accent { background: rgba(212, 175, 55, 0.1); color: var(--accent); }
        .icon-chart3 { background: rgba(45, 90, 61, 0.1); color: var(--chart-3); }
        .icon-chart4 { background: rgba(232, 197, 71, 0.1); color: var(--chart-4); }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* Card */
        .card {
            background: white;
            border: 2px solid var(--border);
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: rgba(248, 248, 246, 0.3);
            border-radius: 1rem 1rem 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Profile Section */
        .profile-layout {
            display: flex;
            gap: 2rem;
        }

        .avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .avatar {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            background: linear-gradient(to bottom right, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
            box-shadow: 0 10px 30px rgba(27, 67, 50, 0.3);
            position: relative;
            transition: transform 0.3s ease;
        }

        .avatar:hover {
            transform: scale(1.05);
        }

        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .avatar-upload:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        /* Avatar image when photo exists */
        .avatar-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-fields {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--foreground);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label i {
            color: var(--primary);
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.625rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(27, 67, 50, 0.1);
        }

        .form-input:disabled {
            background: var(--muted);
            cursor: not-allowed;
        }

        .edit-actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            animation: fadeIn 0.3s ease;
        }

        /* Address Card */
        .address-item {
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
            animation: fadeInUp 0.3s ease;
        }

        .address-item:hover {
            transform: scale(1.02);
            border-color: rgba(27, 67, 50, 0.5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .address-content {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .address-info {
            flex: 1;
        }

        .address-label {
            font-weight: 500;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            background: var(--accent);
            color: var(--foreground);
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .address-details {
            font-size: 0.875rem;
            color: var(--foreground);
            margin-bottom: 0.25rem;
        }

        .address-details.muted {
            color: var(--muted-foreground);
        }

        .address-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Favorites */
        .favorite-item {
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
            animation: fadeInUp 0.3s ease;
        }

        .favorite-item:hover {
            transform: scale(1.05);
            border-color: rgba(27, 67, 50, 0.5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .favorite-content {
            display: flex;
            gap: 1rem;
        }

        .favorite-image-wrapper {
            position: relative;
        }

        .favorite-image {
            width: 80px;
            height: 80px;
            border-radius: 0.75rem;
            object-fit: cover;
            border: 2px solid var(--border);
        }

        .favorite-heart {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .favorite-info {
            flex: 1;
        }

        .favorite-name {
            font-weight: 500;
            color: var(--primary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .favorite-category {
            font-size: 0.75rem;
            color: var(--muted-foreground);
            margin-bottom: 0.5rem;
        }

        .favorite-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .favorite-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .favorite-rating i {
            color: var(--accent);
        }

        .favorite-price {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--primary);
        }

        .add-favorite {
            padding: 2rem;
            border: 2px dashed var(--accent);
            border-radius: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .add-favorite:hover {
            background: rgba(212, 175, 55, 0.05);
            transform: scale(1.02);
        }

        .add-favorite i {
            font-size: 2rem;
            color: var(--accent);
        }

        .add-favorite span {
            font-weight: 500;
            color: var(--accent);
        }

        .add-favorite small {
            font-size: 0.75rem;
            color: var(--muted-foreground);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        /* Icon action buttons fixed at bottom-right inside each record in modals */
        .modal .address-item { position: relative; padding-bottom: 3rem; }
        .modal .address-actions {
            position: absolute;
            right: 1rem;
            bottom: 0.75rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .icon-btn {
            width: 34px;
            height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            border-width: 2px;
        }
        .icon-btn i { font-size: 0.9rem; }

        /* Footer */
        footer {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
            margin-top: 4rem;
        }

        .footer-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.2);
            margin: 0 auto 1rem;
            object-fit: cover;
        }

        footer h3 {
            color: white;
            margin-bottom: 0.5rem;
        }

        footer p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .welcome-icon {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-container {
                padding: 1rem;
            }

            .profile-layout {
                flex-direction: column;
                align-items: center;
            }

            .profile-fields {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-text h2 {
                font-size: 1.5rem;
            }

            .header-text h1 {
                font-size: 1.25rem;
            }

            .header-text p {
                font-size: 0.75rem;
            }
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 0.25rem;
            cursor: pointer;
        }

        .checkbox-wrapper label {
            cursor: pointer;
            font-size: 0.875rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--muted-foreground);
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--muted-foreground);
        }

        /* Profile page forced green navbar */
        body.profile-page header.nav-root {
            background: #1B4332 !important; /* primary green */
            backdrop-filter: none !important;
            border-bottom: 1px solid rgba(255,255,255,0.12) !important;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25) !important;
        }
        body.profile-page header.nav-root .nav-link,
        body.profile-page header.nav-root #profile-btn,
        body.profile-page header.nav-root #nav-notif-btn,
        body.profile-page header.nav-root #nav-cart-btn,
        body.profile-page header.nav-root #nav-notif-btn-mobile,
        body.profile-page header.nav-root #nav-cart-btn-mobile,
        body.profile-page header.nav-root #mobile-menu-btn { color:#ffffff !important; }
        body.profile-page header.nav-root .nav-link:hover { color:#D4AF37 !important; }
        body.profile-page header.nav-root #signup-btn-nav { border-color:#ffffff !important; color:#ffffff !important; }
        body.profile-page header.nav-root #signup-btn-nav:hover { background:#ffffff !important; color:#1B4332 !important; }
        body.profile-page header.nav-root #nav-notif-btn,
        body.profile-page header.nav-root #nav-cart-btn,
        body.profile-page header.nav-root #nav-notif-btn-mobile,
        body.profile-page header.nav-root #nav-cart-btn-mobile { border-color: rgba(255,255,255,0.6) !important; }
        body.profile-page header.nav-root #nav-notif-btn:hover,
        body.profile-page header.nav-root #nav-cart-btn:hover,
        body.profile-page header.nav-root #nav-notif-btn-mobile:hover,
        body.profile-page header.nav-root #nav-cart-btn-mobile:hover { background:rgba(255,255,255,0.1) !important; }
        /* Ensure mobile expanded menu inherits green */
        body.profile-page #mobile-menu { background:#1B4332 !important; border-color:rgba(255,255,255,0.15) !important; }
    </style>
</head>
<body class="min-h-screen profile-page">
    <?php include __DIR__ . '/../partials/navbar-user.php'; ?>

    <!-- Main Content -->
    <div class="container pt-32 md:pt-36" data-nav-contrast="dark">

    
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h2>Welcome back, <span id="welcomeName"><?php echo e($welcomeName); ?></span></h2>
                    <p>Manage your profile information and taste back the flavors of home</p>
                </div>
                <i class="fas fa-heart welcome-icon"></i>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" id="stat-orders">
                <div class="stat-content">
                    <div class="stat-info">
                        <p>Total Orders</p>
                        <div class="stat-value" id="count-orders" style="color: var(--primary);"><?php echo e($totalOrders); ?></div>
                    </div>
                    <div class="stat-icon icon-primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card" id="stat-bookings">
                <div class="stat-content">
                    <div class="stat-info">
                        <p>Bookings</p>
                        <div class="stat-value" id="count-bookings" style="color: var(--accent);"><?php echo e($totalBookings); ?></div>
                    </div>
                    <div class="stat-icon icon-accent">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card" id="stat-catering">
                <div class="stat-content">
                    <div class="stat-info">
                        <p>Catering</p>
                        <div class="stat-value" id="count-catering" style="color: var(--chart-3);"><?php echo e($totalCatering); ?></div>
                    </div>
                    <div class="stat-icon icon-chart3">
                        <i class="fas fa-utensils"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <p>Member Since</p>
                        <div class="stat-value" style="color: var(--chart-4);"><?php echo e($createdYear !== '' ? $createdYear : date('Y')); ?></div>
                    </div>
                    <div class="stat-icon icon-chart4">
                        <i class="fas fa-award"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Profile Information -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-user"></i>
                            Profile Information
                        </div>
                        <button class="btn btn-sm" id="editProfileBtn">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="profile-layout">
                            <div class="avatar-section">
                                <div class="avatar">
                                    <?php if ($photoWeb): ?>
                                        <img src="<?php echo e($photoWeb); ?>" alt="Profile Photo" class="avatar-img" onerror="this.style.display='none'; var s=this.parentNode.querySelector('#avatarInitials'); if(s) s.style.display='';">
                                        <span id="avatarInitials" style="display:none;">&nbsp;</span>
                                    <?php else: ?>
                                        <span id="avatarInitials"><?php echo e($initials); ?></span>
                                    <?php endif; ?>
                                    <div class="avatar-upload">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                </div>
                                <input type="file" id="photoInput" accept="image/*" style="display:none" />
                                <button class="btn btn-sm" id="changePhotoBtn" style="margin-top: 0.5rem; border: none; color: var(--accent);">
                                    Change Photo
                                </button>
                            </div>

                            <div class="profile-fields">
                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-user"></i>
                                        First Name
                                    </label>
                                    <input type="text" class="form-input" id="firstName" value="<?php echo e($firstName); ?>" disabled>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-user"></i>
                                        Last Name
                                    </label>
                                    <input type="text" class="form-input" id="lastName" value="<?php echo e($lastName); ?>" disabled>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-user"></i>
                                        Username
                                    </label>
                                    <input type="text" class="form-input" id="username" value="<?php echo e($username); ?>" disabled>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-envelope"></i>
                                        Email Address
                                    </label>
                                    <input type="email" class="form-input" id="email" value="<?php echo e($email); ?>" disabled>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-phone"></i>
                                        Phone Number
                                    </label>
                                    <input type="tel" class="form-input" id="phone" value="<?php echo e($phone); ?>" disabled>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-clock"></i>
                                        Subscription
                                    </label>
                                    <input type="text" class="form-input" value="Active since <?php echo e($createdPretty !== '' ? $createdPretty : date('M j, Y')); ?>" disabled>
                                </div>
                            </div>
                        </div>
                        <div class="edit-actions" id="editActions" style="display: none;">
                            <button class="btn btn-primary" id="saveProfileBtn">Save Profile</button>
                        </div>
                    </div>
                </div>

                <!-- Order History (replaces Delivery Addresses section) -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-clock"></i>
                            Recent Orders
                        </div>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>No orders yet. Start ordering from the menu!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <div class="address-item" style="margin-bottom: 0.75rem;">
                                    <div class="address-content">
                                        <div class="address-info">
                                            <div class="address-label">
                                                <i class="fas fa-receipt"></i>
                                                <span>Order #<?php echo e($o['order_id']); ?></span>
                                                <span class="badge" style="margin-left: 0.5rem;"><?php echo e(ucwords($o['order_status'] ?? 'pending')); ?></span>
                                            </div>
                                            <p class="address-details">Total: ₱<?php echo number_format((float)$o['order_amount'], 2); ?></p>
                                            <p class="address-details muted">Needed: <?php echo e($o['order_needed']); ?> • Placed: <?php echo e(date('M j, Y', strtotime($o['order_date'] ?? $o['created_at']))); ?></p>
                                        </div>
                                        <div class="address-actions">
                                            <a class="btn btn-sm view-order-btn" href="#" data-order-id="<?php echo e($o['order_id']); ?>" title="View details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Order History -->
            <div class="right-column">
                <div class="card" style="position: sticky; top: 6rem;">
                    <div class="card-header">
                        <div>
                            <div class="card-title">
                                <i class="fas fa-history" style="color: var(--accent);"></i>
                                Order History
                            </div>
                            <a href="booking.php" style="color: var(--accent); text-decoration: none; font-size: 0.875rem; margin-top: 0.5rem; display: inline-block;">
                                View All Orders
                            </a>
                        </div>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>No past orders yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <div class="favorite-item" data-order-id="<?php echo e($o['order_id']); ?>">
                                    <div class="favorite-content">
                                        <div class="favorite-image-wrapper">
                                            <div class="favorite-image" style="display:flex;align-items:center;justify-content:center;background:#f6f6f4;border:2px solid var(--border);color:var(--primary);font-weight:700;">
                                                #<?php echo e($o['order_id']); ?>
                                            </div>
                                            <div class="favorite-heart" title="Status">
                                                <i class="fas fa-circle" style="font-size:8px;"></i>
                                            </div>
                                        </div>
                                        <div class="favorite-info">
                                            <h4 class="favorite-name">Order #<?php echo e($o['order_id']); ?> • ₱<?php echo number_format((float)$o['order_amount'], 2); ?></h4>
                                            <p class="favorite-category">Status: <?php echo e(ucwords($o['order_status'] ?? 'pending')); ?></p>
                                            <div class="favorite-footer">
                                                <div class="favorite-rating">
                                                    <i class="fas fa-calendar-day"></i>
                                                    <span>Needed: <?php echo e($o['order_needed']); ?></span>
                                                </div>
                                                <p class="favorite-price">Placed: <?php echo e(date('M j, Y', strtotime($o['order_date'] ?? $o['created_at']))); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <a class="btn btn-sm view-order-btn" style="width: 100%; margin-top: 0.75rem;" href="#" data-order-id="<?php echo e($o['order_id']); ?>">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-receipt"></i>
                <h3 id="odmTitle">Order Details</h3>
                <button class="btn btn-sm" id="orderDetailsClose" style="margin-left:auto;">Close</button>
            </div>
            <div class="modal-form" id="odmBody">
                <div class="address-item">
                    <div class="address-content">
                        <div class="address-info">
                            <div class="address-label">
                                <i class="fas fa-info-circle"></i>
                                <span id="odmStatus">Status: -</span>
                                <span class="badge" id="odmAmount" style="margin-left:0.5rem;">Total: -</span>
                            </div>
                            <p class="address-details muted" id="odmDates">Needed: - • Placed: -</p>
                            <p class="address-details" id="odmAddress" style="display:none"></p>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-list"></i> Items</div>
                    </div>
                    <div class="card-content" id="odmItems">
                        <div class="empty-state"><i class="fas fa-box-open"></i><p>No items found.</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Catering Modal -->
    <div class="modal" id="cateringModal">
        <div class="modal-content" style="max-width:900px;">
            <div class="modal-header">
                <i class="fas fa-utensils"></i>
                <h3 style="margin-right:auto;">My Catering Inquiries</h3>
                <button class="btn btn-sm" id="cateringClose">Close</button>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Records</div></div>
                <div class="card-content" id="cateringList">
                    <div class="empty-state"><i class="fas fa-box-open"></i><p>No catering inquiries yet.</p></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookings Modal -->
    <div class="modal" id="bookingsModal">
        <div class="modal-content" style="max-width:960px;">
            <div class="modal-header">
                <i class="fas fa-calendar"></i>
                <h3 style="margin-right:auto;">My Bookings</h3>
                <button class="btn btn-sm" id="bookingsClose">Close</button>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Records</div></div>
                <div class="card-content" id="bookingsList">
                    <div class="empty-state"><i class="fas fa-box-open"></i><p>No bookings yet.</p></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
    <img src="../images/logo.png" alt="Sandok ni Binggay" class="footer-logo">
        <h3>Sandok ni Binggay</h3>
        <p>Nothing Beats Home-Cooked Meals</p>
        <p style="margin-top: 1rem;">Party Trays • Packed Meals • Foods for Caterings • Grazing Table Setup</p>
    </footer>

    <script>
        // Profile Edit Functionality
        let isEditing = false;
        const editProfileBtn = document.getElementById('editProfileBtn');
        const saveProfileBtn = document.getElementById('saveProfileBtn');
        const editActions = document.getElementById('editActions');
        const profileInputs = ['firstName', 'lastName', 'username', 'email', 'phone'];

        editProfileBtn.addEventListener('click', () => {
            isEditing = !isEditing;

            if (isEditing) {
                profileInputs.forEach(id => {
                    document.getElementById(id).disabled = false;
                });
                editProfileBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                editActions.style.display = 'flex';
            } else {
                profileInputs.forEach(id => {
                    document.getElementById(id).disabled = true;
                });
                editProfileBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Profile';
                editActions.style.display = 'none';
            }
        });

        saveProfileBtn.addEventListener('click', () => {
            // Get values
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();

            saveProfileBtn.disabled = true;
            saveProfileBtn.textContent = 'Saving...';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'update_profile',
                    first_name: firstName,
                    last_name: lastName,
                    username: username,
                    email: email,
                    phone: phone
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    // Update welcome name
                    document.getElementById('welcomeName').textContent = `${firstName} ${lastName}`.trim() || username;
                    // Update avatar initials (if initials element exists; when a photo is present, it's an <img>)
                    const fi = firstName.charAt(0) || username.charAt(0) || 'U';
                    const li = lastName.charAt(0) || (username.length > 1 ? username.charAt(1) : '');
                    const initialsEl = document.getElementById('avatarInitials');
                    if (initialsEl) { initialsEl.textContent = `${fi}${li}`.toUpperCase(); }

                    // Disable editing
                    profileInputs.forEach(id => { document.getElementById(id).disabled = true; });
                    editProfileBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Profile';
                    editActions.style.display = 'none';
                    isEditing = false;

                    alert('Profile updated successfully!');
                } else {
                    alert(res.message || 'Could not update profile.');
                }
            })
            .catch(() => alert('Network error. Please try again.'))
            .finally(() => { saveProfileBtn.disabled = false; saveProfileBtn.textContent = 'Save Profile'; });
        });

        // Photo Upload
        const photoInput = document.getElementById('photoInput');
        const changePhotoBtn = document.getElementById('changePhotoBtn');
        const avatarUploadBtn = document.querySelector('.avatar-upload');
        function triggerPhotoPick() { photoInput && photoInput.click(); }
        if (changePhotoBtn) changePhotoBtn.addEventListener('click', (e) => { e.preventDefault(); triggerPhotoPick(); });
        if (avatarUploadBtn) avatarUploadBtn.addEventListener('click', (e) => { e.preventDefault(); triggerPhotoPick(); });

        if (photoInput) {
            photoInput.addEventListener('change', () => {
                if (!photoInput.files || !photoInput.files[0]) return;
                const file = photoInput.files[0];
                const fd = new FormData();
                fd.append('action', 'upload_photo');
                fd.append('photo', file);

                // Basic size guard on client (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image too large. Max 5MB.');
                    return;
                }

                changePhotoBtn.disabled = true;
                changePhotoBtn.textContent = 'Uploading...';

                fetch(window.location.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok && res.photo_url) {
                        const avatar = document.querySelector('.avatar');
                        let img = avatar ? avatar.querySelector('img.avatar-img') : null;
                        if (!img) {
                            img = document.createElement('img');
                            img.className = 'avatar-img';
                            avatar && avatar.insertBefore(img, avatar.firstChild);
                        }
                        img.style.display = '';
                        img.src = res.photo_url + '?v=' + Date.now(); // cache-bust
                        const initialsEl = document.getElementById('avatarInitials');
                        if (initialsEl) initialsEl.style.display = 'none';
                        alert('Profile photo updated!');
                    } else {
                        alert(res.message || 'Failed to upload photo.');
                    }
                })
                .catch(() => alert('Network error while uploading.'))
                .finally(() => {
                    changePhotoBtn.disabled = false;
                    changePhotoBtn.textContent = 'Change Photo';
                    // Reset input so selecting the same file again re-triggers change
                    photoInput.value = '';
                });
            });
        }

        // (Addresses and Favorites removed per request)

        // Real-time Stats Polling
        function loadCounts(){
            fetch(window.location.href, {
                method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin',
                body: JSON.stringify({ action: 'get_counts' })
            }).then(r=>r.json()).then(j=>{
                if(!j.ok) return;
                const o=document.getElementById('count-orders'); if(o) o.textContent = j.orders;
                const b=document.getElementById('count-bookings'); if(b) b.textContent = j.bookings;
                const c=document.getElementById('count-catering'); if(c) c.textContent = j.catering;
            }).catch(()=>{});
        }
        let countsTimer = null;
        function startCounts(){ if(countsTimer) clearInterval(countsTimer); loadCounts(); countsTimer = setInterval(loadCounts, 10000); }
        document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) startCounts(); });
        startCounts();

        // Catering + Bookings Modals
        const cateringModal = document.getElementById('cateringModal');
        const bookingsModal = document.getElementById('bookingsModal');
        const cateringList = document.getElementById('cateringList');
        const bookingsList = document.getElementById('bookingsList');
        const statCatering = document.getElementById('stat-catering');
        const statBookings = document.getElementById('stat-bookings');
        const cateringClose = document.getElementById('cateringClose');
        const bookingsClose = document.getElementById('bookingsClose');

        function openCatering(){ cateringModal.classList.add('active'); loadCatering(); }
        function openBookings(){ bookingsModal.classList.add('active'); loadBookings(); }
        if (statCatering) statCatering.addEventListener('click', openCatering);
        if (statBookings) statBookings.addEventListener('click', openBookings);
        if (cateringClose) cateringClose.addEventListener('click', ()=> cateringModal.classList.remove('active'));
        if (bookingsClose) bookingsClose.addEventListener('click', ()=> bookingsModal.classList.remove('active'));
        cateringModal.addEventListener('click', (e)=>{ if (e.target===cateringModal) cateringModal.classList.remove('active'); });
        bookingsModal.addEventListener('click', (e)=>{ if (e.target===bookingsModal) bookingsModal.classList.remove('active'); });

        function renderCatering(items){
            if(!items || !items.length){ cateringList.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><p>No catering inquiries yet.</p></div>'; return; }
            cateringList.innerHTML = items.map(it=>{
                const pay = it.payment||{};
                const amt = typeof pay.amount!=='undefined' ? ('₱'+Number(pay.amount||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})) : '—';
                return `<div class="address-item">
                    <div class="address-content">
                        <div class="address-info">
                            <div class="address-label"><i class="fas fa-map-marker-alt"></i><span>${escapeHtml(it.place||'-')}</span></div>
                            <div class="address-details"><b>Date needed:</b> ${escapeHtml(it.date||'-')}</div>
                            <div class="address-details"><b>Add ons:</b> ${escapeHtml(String(it.addons||''))}</div>
                            <div class="address-details"><b>Notes:</b> ${escapeHtml(String(it.notes||''))}</div>
                            <div class="address-details"><b>Price:</b> ₱${Number(it.price||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                            <div class="address-details"><b>Payment amount:</b> ${amt}</div>
                            <div class="address-details"><b>Payment method:</b> ${escapeHtml(pay.method||'-')}</div>
                            <div class="address-details"><b>Payment status:</b> ${escapeHtml(pay.status||'-')}</div>
                        </div>
                        <div class="address-actions">
                            <a class="btn icon-btn" href="catering_contract.php?id=${it.id}" target="_blank" title="View Contract"><i class="fas fa-file-contract"></i></a>
                            <button class="btn icon-btn btn-destructive js-cancel-catering" data-id="${it.id}" title="Cancel"><i class="fas fa-ban"></i></button>
                            <button class="btn icon-btn btn-destructive js-delete-catering" data-id="${it.id}" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }
        function renderBookings(items){
            if(!items || !items.length){ bookingsList.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><p>No bookings yet.</p></div>'; return; }
            bookingsList.innerHTML = items.map(it=>{
                const pkg = it.package||{};
                const pkgTxt = pkg.name ? `${escapeHtml(pkg.name)} - ${escapeHtml(String(pkg.pax||''))} - ₱${Number(pkg.price||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}` : escapeHtml(it.orderLabel||'-');
                return `<div class="address-item">
                    <div class="address-content">
                        <div class="address-info">
                            <div class="address-label"><i class="fas fa-tag"></i><span>Event Type: ${escapeHtml(it.event_type||'-')}</span></div>
                            <div class="address-details"><b>Package:</b> ${pkgTxt}</div>
                            <div class="address-details"><b>Venue:</b> ${escapeHtml(it.venue||'-')}</div>
                            <div class="address-details"><b>Date:</b> ${escapeHtml(it.date||'-')}</div>
                            <div class="address-details"><b>Add ons:</b> ${escapeHtml(String(it.addons||''))}</div>
                            <div class="address-details"><b>Status:</b> ${escapeHtml(it.status||'-')}</div>
                            <div class="address-details"><b>Notes:</b> ${escapeHtml(String(it.notes||''))}</div>
                        </div>
                        <div class="address-actions">
                            <a class="btn icon-btn" href="booking_contract.php?id=${it.id}" target="_blank" title="View Contract"><i class="fas fa-file-contract"></i></a>
                            <button class="btn icon-btn btn-destructive js-cancel-booking" data-id="${it.id}" title="Cancel"><i class="fas fa-ban"></i></button>
                            <button class="btn icon-btn btn-destructive js-delete-booking" data-id="${it.id}" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }
        function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }

        function loadCatering(){
            fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body: JSON.stringify({ action:'list_catering' }) })
            .then(r=>r.json()).then(j=>{ if(j.ok){ renderCatering(j.items||[]); } });
        }
        function loadBookings(){
            fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body: JSON.stringify({ action:'list_bookings' }) })
            .then(r=>r.json()).then(j=>{ if(j.ok){ renderBookings(j.items||[]); } });
        }

        // Actions: cancel/delete
        document.addEventListener('click', (e)=>{
            const btnCancelC = e.target.closest('.js-cancel-catering');
            const btnDeleteC = e.target.closest('.js-delete-catering');
            const btnCancelB = e.target.closest('.js-cancel-booking');
            const btnDeleteB = e.target.closest('.js-delete-booking');
            if (btnCancelC){ const id=+btnCancelC.getAttribute('data-id'); if(!id) return; if(!confirm('Cancel this catering inquiry?')) return;
                fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin', body: JSON.stringify({action:'cancel_catering', id})}).then(r=>r.json()).then(j=>{ if(j.ok){ loadCatering(); loadCounts(); } else { alert(j.message||'Failed to cancel.'); } });
            }
            if (btnDeleteC){ const id=+btnDeleteC.getAttribute('data-id'); if(!id) return; if(!confirm('Delete this catering inquiry? This cannot be undone.')) return;
                fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin', body: JSON.stringify({action:'delete_catering', id})}).then(r=>r.json()).then(j=>{ if(j.ok){ loadCatering(); loadCounts(); } else { alert(j.message||'Failed to delete.'); } });
            }
            if (btnCancelB){ const id=+btnCancelB.getAttribute('data-id'); if(!id) return; if(!confirm('Cancel this booking?')) return;
                fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin', body: JSON.stringify({action:'cancel_booking', id})}).then(r=>r.json()).then(j=>{ if(j.ok){ loadBookings(); loadCounts(); } else { alert(j.message||'Failed to cancel.'); } });
            }
            if (btnDeleteB){ const id=+btnDeleteB.getAttribute('data-id'); if(!id) return; if(!confirm('Delete this booking? This cannot be undone.')) return;
                fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin', body: JSON.stringify({action:'delete_booking', id})}).then(r=>r.json()).then(j=>{ if(j.ok){ loadBookings(); loadCounts(); } else { alert(j.message||'Failed to delete.'); } });
            }
        });

        // Order Details modal interactions
        const orderDetailsModal = document.getElementById('orderDetailsModal');
        const orderDetailsClose = document.getElementById('orderDetailsClose');
        const odmTitle = document.getElementById('odmTitle');
        const odmStatus = document.getElementById('odmStatus');
        const odmAmount = document.getElementById('odmAmount');
        const odmDates = document.getElementById('odmDates');
        const odmAddress = document.getElementById('odmAddress');
        const odmItems = document.getElementById('odmItems');

        function peso(n){
            const v = Number(n||0);
            return '₱' + v.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        }

        function openOrderDetails(orderId){
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'get_order_details', order_id: orderId })
            })
            .then(r=>r.json())
            .then(res=>{
                if(!res.ok){ alert(res.message || 'Unable to load order details.'); return; }
                const o = res.order;
                odmTitle.textContent = `Order #${o.order_id}`;
                odmStatus.textContent = `Status: ${String(o.order_status||'pending').replace(/\b\w/g, c=>c.toUpperCase())}`;
                odmAmount.textContent = `Total: ${peso(o.order_amount)}`;
                const placed = o.order_date ? new Date(o.order_date) : new Date(o.created_at);
                const placedStr = placed.toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'});
                odmDates.textContent = `Needed: ${o.order_needed} • Placed: ${placedStr}`;
                if (res.address && (res.address.oa_street || res.address.oa_city || res.address.oa_province)){
                    odmAddress.style.display = '';
                    const parts = [res.address.oa_street, res.address.oa_city, res.address.oa_province].filter(Boolean);
                    odmAddress.textContent = parts.join(', ');
                } else {
                    odmAddress.style.display = 'none';
                }
                // Items
                if (!res.items || res.items.length === 0){
                    odmItems.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><p>No items found.</p></div>';
                } else {
                    const html = res.items.map(it=>{
                        const qty = Number(it.qty||0);
                        const price = Number(it.price||0);
                        const subtotal = qty * price;
                        return `<div class="favorite-item" style="margin-bottom:0.5rem;">
                            <div class="favorite-content">
                                <div class="favorite-image-wrapper">
                                    <div class="favorite-image" style="display:flex;align-items:center;justify-content:center;background:#f6f6f4;border:2px solid var(--border);color:var(--primary);font-weight:700;">${qty}×</div>
                                </div>
                                <div class="favorite-info">
                                    <h4 class="favorite-name">${(it.name||'Item')}</h4>
                                    <div class="favorite-footer">
                                        <div class="favorite-rating"><i class="fas fa-tag"></i><span>${peso(price)}</span></div>
                                        <p class="favorite-price">${peso(subtotal)}</p>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    }).join('');
                    odmItems.innerHTML = html;
                }
                orderDetailsModal.classList.add('active');
            })
            .catch(()=>alert('Network error.'));
        }

        // Open via any view button
        document.addEventListener('click', (e)=>{
            const a = e.target.closest('.view-order-btn');
            if (a){
                e.preventDefault();
                const oid = a.getAttribute('data-order-id');
                if (oid) openOrderDetails(oid);
            }
        });

        // Close modal
        if (orderDetailsClose) orderDetailsClose.addEventListener('click', ()=> orderDetailsModal.classList.remove('active'));
        orderDetailsModal.addEventListener('click', (e)=>{ if (e.target === orderDetailsModal) orderDetailsModal.classList.remove('active'); });

        // Smooth scroll animations on load
        window.addEventListener('load', () => {
            const cards = document.querySelectorAll('.card, .stat-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>
