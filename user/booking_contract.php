<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../classes/database.php';

if (empty($_SESSION['user_id'])) { http_response_code(302); header('Location: ../login.php'); exit; }
$uid = (int)$_SESSION['user_id'];
$bid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bid <= 0) { http_response_code(400); echo 'Invalid booking.'; exit; }

try {
    $db = new database();
    $pdo = $db->opencon();
    // Ensure the booking belongs to the current user and gather readable fields
    $stmt = $pdo->prepare("SELECT eb.*, et.name AS eb_type, pk.pax AS eb_package_pax, pk.name AS package_name,
                                  u.user_fn, u.user_ln, u.user_email, u.user_phone
                           FROM eventbookings eb
                           LEFT JOIN event_types et ON et.event_type_id = eb.event_type_id
                           LEFT JOIN packages pk ON pk.package_id = eb.package_id
                           LEFT JOIN users u ON u.user_id = eb.user_id
                           WHERE eb.eb_id=? AND eb.user_id=? LIMIT 1");
    $stmt->execute([$bid, $uid]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$b) { http_response_code(404); echo 'Booking not found.'; exit; }

    // Latest payment for this user & booking context (generic booking payment entry)
    $pstmt = $pdo->prepare("SELECT pay_date, pay_amount, pay_method, pay_status
                            FROM payments WHERE user_id=? AND order_id IS NULL AND cp_id IS NULL
                            ORDER BY pay_date DESC, pay_id DESC LIMIT 1");
    $pstmt->execute([$uid]);
    $pay = $pstmt->fetch(PDO::FETCH_ASSOC) ?: null;

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

    // Minimal PDF generator (Helvetica, single page, text only)
    $escape = function(string $s): string { return strtr($s, ["\\"=>'\\\\', '('=>'\\(', ')'=>'\\)']); };
    $addLine = function(array &$buf, string $text, int $x, int &$y, int $leading=16) use ($escape) {
        $safe = $escape($text);
        $buf[] = sprintf("1 0 0 1 %d %d Tm (%s) Tj\n", $x, $y, $safe);
        $y -= $leading;
    };

    $content = [];
    $y = 800;
    $content[] = "BT\n/F1 20 Tf\n";
    $addLine($content, 'Event Booking Contract', 50, $y, 26);
    $content[] = "/F1 12 Tf\n";
    $addLine($content, 'Sandok ni Binggay Catering Services', 50, $y);
    $addLine($content, 'Date: ' . date('F d, Y'), 50, $y);
    $y -= 6;
    $addLine($content, 'This contract summarizes the details of your event booking.', 50, $y);
    $y -= 10;

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

    $y -= 8;
    $content[] = "/F1 14 Tf\n"; $addLine($content, 'Terms & Conditions (Summary)', 50, $y, 20);
    $content[] = "/F1 12 Tf\n";
    $addLine($content, '• Changes must be communicated at least 3 days before the event.', 50, $y);
    $addLine($content, '• Payments follow the status listed above unless otherwise agreed.', 50, $y);
    $addLine($content, '• Services will be delivered per agreed package details.', 50, $y);

    $y -= 10;
    $addLine($content, 'Signed on: ' . $created, 50, $y);
    $y -= 24;
    $addLine($content, 'Client Signature: ___________________________', 50, $y);
    $addLine($content, 'Authorized Signature (Sandok ni Binggay): ___________________________', 50, $y);
    $content[] = "ET\n";

    $stream = implode('', $content);
    $len = strlen($stream);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $writeObj = function(int $num, string $body) use (&$pdf, &$offsets) {
        $offsets[$num] = strlen($pdf);
        $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
    };
    $writeObj(1, "<< /Type /Catalog /Pages 2 0 R >>");
    $writeObj(2, "<< /Type /Pages /Count 1 /Kids [3 0 R] >>");
    $writeObj(3, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>");
    $writeObj(4, "<< /Length $len >>\nstream\n$stream\nendstream");
    $writeObj(5, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= sprintf("%010d %05d f \n", 0, 65535);
    for ($i=1; $i<=5; $i++) { $pdf .= sprintf("%010d %05d n \n", (int)$offsets[$i], 0); }
    $pdf .= "trailer\n";
    $pdf .= "<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

    $fname = 'Contract_Booking_' . (int)$bid . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename=' . $fname);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to generate PDF.';
}
