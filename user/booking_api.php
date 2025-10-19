<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/Mailer.php';
$db = new database();
$pdo = $db->opencon();

// Require login
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Please login first.']); exit; }

// Accept POST only
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

function strOrNull($v){ $v = isset($v) ? trim((string)$v) : ''; return $v === '' ? null : $v; }

try {
    $fullName = strOrNull($_POST['fullName'] ?? '');
    $email = strOrNull($_POST['email'] ?? '');
    $phone1 = strOrNull($_POST['phone'] ?? '');
    $phone2 = strOrNull($_POST['altPhone'] ?? '');
    $eventTypeId = isset($_POST['eventTypeId']) ? (int)$_POST['eventTypeId'] : 0;
    $packageId = isset($_POST['packageId']) ? (int)$_POST['packageId'] : 0;
    $venueName = strOrNull($_POST['venueName'] ?? '');
    $venueStreet = strOrNull($_POST['venueStreet'] ?? '');
    $venueBarangay = strOrNull($_POST['venueBarangay'] ?? '');
    $venueCity = strOrNull($_POST['venueCity'] ?? '');
    $venueProvince = strOrNull($_POST['venueProvince'] ?? '');
    $eventDate = strOrNull($_POST['eventDate'] ?? ''); // YYYY-MM-DD
    $eventTime = strOrNull($_POST['eventTime'] ?? ''); // HH:mm
    $notes = strOrNull($_POST['notes'] ?? '');
    $agree = isset($_POST['agree']) && ($_POST['agree'] === '1' || $_POST['agree'] === 'true' || $_POST['agree'] === 'on');
    // Add-ons as labeled items, expect addons[] strings like "5 pax", "3 tables"; if not provided, build from individual counts
    $addons = isset($_POST['addons']) ? (array)$_POST['addons'] : [];
    $addons = array_values(array_filter(array_map('trim', $addons), fn($s)=>$s!==''));
    if (empty($addons)) {
        // Build from potential numeric fields
        $pax = isset($_POST['addon_pax']) ? (int)$_POST['addon_pax'] : 0;
        $tables = isset($_POST['tables']) ? (int)$_POST['tables'] : 0;
        $chairs = isset($_POST['chairs']) ? (int)$_POST['chairs'] : 0;
        $utensils = isset($_POST['utensils']) ? (int)$_POST['utensils'] : 0;
        $waiters = isset($_POST['waiters']) ? (int)$_POST['waiters'] : 0;
        if ($pax > 0) { $addons[] = $pax . ' pax'; }
        if ($tables > 0) { $addons[] = $tables . ' ' . ($tables === 1 ? 'table' : 'tables'); }
        if ($chairs > 0) { $addons[] = $chairs . ' ' . ($chairs === 1 ? 'chair' : 'chairs'); }
        if ($utensils > 0) { $addons[] = $utensils . ' ' . ($utensils === 1 ? 'utensil' : 'utensils'); }
        if ($waiters > 0) { $addons[] = $waiters . ' ' . ($waiters === 1 ? 'waiter' : 'waiters'); }
    }

    if (!$fullName || !$email || !$phone1 || !$eventDate || !$eventTime || !$agree || $eventTypeId<=0 || $packageId<=0) {
        echo json_encode(['success'=>false,'message'=>'Please complete all required fields.']); exit;
    }
    // Phone normalization: 11 digits
    $digits1 = preg_replace('/\D+/', '', $phone1); if (strlen($digits1)!==11) { echo json_encode(['success'=>false,'message'=>'Contact Number must be 11 digits.']); exit; }
    $ebContact = $digits1;
    if ($phone2) { $digits2 = preg_replace('/\D+/', '', $phone2); if ($digits2!=='') { $ebContact .= ', ' . $digits2; } }

    // Venue composed string (Name, Street, Barangay, City/Municipality, Province)
    $venueParts = array_filter([$venueName, $venueStreet, $venueBarangay, $venueCity, $venueProvince], fn($v)=>$v && $v!=='' );
    $ebVenue = implode(' , ', $venueParts);
    if (!$ebVenue) { echo json_encode(['success'=>false,'message'=>'Venue is required.']); exit; }

    // Combine date and time to timestamp; require at least 14 days lead time
    $dtStr = $eventDate . ' ' . $eventTime . ':00';
    $ts = strtotime($dtStr);
    if ($ts === false) { echo json_encode(['success'=>false,'message'=>'Invalid event date/time']); exit; }
    $minTs = strtotime('+14 days');
    if ($ts < $minTs) { echo json_encode(['success'=>false,'message'=>'Event date must be at least 14 days from now.']); exit; }
    $ebDate = date('Y-m-d H:i:s', $ts);

    // Check availability across bookings and catering for the selected day
    try {
        $day = date('Y-m-d', $ts);
        $c1 = $pdo->prepare("SELECT COUNT(*) FROM eventbookings WHERE DATE(eb_date)=? AND COALESCE(LOWER(eb_status),'') NOT IN ('completed','canceled','cancelled')");
        $c1->execute([$day]);
        $bCount = (int)$c1->fetchColumn();
        $c2 = $pdo->prepare("SELECT COUNT(*) FROM cateringpackages WHERE cp_date = ?");
        $c2->execute([$day]);
        $cCount = (int)$c2->fetchColumn();
        if (($bCount + $cCount) > 0) {
            echo json_encode(['success'=>false,'message'=>'Sorry, that date is no longer available. Please choose another date.']);
            exit;
        }
    } catch (Throwable $e) {
        // Fail closed to be safe
        echo json_encode(['success'=>false,'message'=>'Unable to verify date availability at the moment. Please try again later.']);
        exit;
    }

    // Validate event type and allowed package mapping
    $chk = $pdo->prepare('SELECT 1 FROM event_type_packages WHERE event_type_id=? AND package_id=?');
    $chk->execute([$eventTypeId, $packageId]);
    if (!$chk->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Selected package is not allowed for this event type.']); exit; }

    // Pull package info for eb_order label and optional pax
    $p = $pdo->prepare('SELECT name, pax FROM packages WHERE package_id=?');
    $p->execute([$packageId]);
    $pkg = $p->fetch(PDO::FETCH_ASSOC) ?: [];
    $pkgLabel = ($pkg['name'] ?? 'Package') . (isset($pkg['pax']) && $pkg['pax']!=='' ? (' - ' . $pkg['pax']) : '');

    // eb_addon_pax CSV in the format "5 pax, 3 tables"
    $ebAddon = $addons ? implode(', ', $addons) : null;

    // Persist
    $stmt = $pdo->prepare('INSERT INTO eventbookings (user_id, event_type_id, package_id, eb_name, eb_email, eb_contact, eb_venue, eb_date, eb_order, eb_status, eb_addon_pax, eb_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ok = $stmt->execute([
        (int)$userId,
        (int)$eventTypeId,
        (int)$packageId,
        $fullName,
        $email,
        $ebContact,
        $ebVenue,
        $ebDate,
        $pkgLabel,
        'Pending',
        $ebAddon,
        $notes
    ]);

    if ($ok) {
        // Send booking summary (Pending) to user's email
        try {
            $userEmail = (string)($_SESSION['user_email'] ?? $email);
            $userFn = trim((string)($_SESSION['user_fn'] ?? ''));
            $userLn = trim((string)($_SESSION['user_ln'] ?? ''));
            $toName = trim($userFn . ' ' . $userLn);
            $mailer = new Mailer();
            $data = [
                'fullName'   => $fullName ?: $toName,
                'event_type' => $eventTypeId,
                'package'    => $pkgLabel,
                'event_date' => $ebDate,
                'venue'      => $ebVenue,
                'contact'    => $ebContact,
                'addons'     => $ebAddon,
                'notes'      => $notes,
            ];
            // Map event_type id to name
            try { $t=$pdo->prepare('SELECT name FROM event_types WHERE event_type_id=?'); $t->execute([$eventTypeId]); $data['event_type']=(string)($t->fetchColumn()?:'Event Booking'); } catch (Throwable $e) {}
            [$subject, $html] = $mailer->renderBookingEmail($data, 'Pending');
            if ($userEmail) { $mailer->send($userEmail, $toName ?: $fullName, $subject, $html); }
        } catch (Throwable $e) { /* ignore email errors */ }
        echo json_encode(['success'=>true, 'message'=>'Booking submitted successfully']);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Failed to save booking']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
}
// end of file
