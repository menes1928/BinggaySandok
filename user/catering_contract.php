<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../classes/database.php';

if (empty($_SESSION['user_id'])) { http_response_code(302); header('Location: ../login'); exit; }
$uid = (int)$_SESSION['user_id'];
$cid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cid <= 0) { http_response_code(400); echo 'Invalid catering record.'; exit; }

try {
    $db = new database();
    $pdo = $db->opencon();
    // Ensure the catering record belongs to the current user
    $stmt = $pdo->prepare("SELECT cp.*, u.user_fn, u.user_ln, u.user_email, u.user_phone
                           FROM cateringpackages cp
                           LEFT JOIN users u ON u.user_id = cp.user_id
                           WHERE cp.cp_id=? AND cp.user_id=? LIMIT 1");
    $stmt->execute([$cid, $uid]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) { http_response_code(404); echo 'Catering record not found.'; exit; }

    // Latest payment specifically for this catering package
    $pstmt = $pdo->prepare("SELECT pay_date, pay_amount, pay_method, pay_status
                            FROM payments WHERE cp_id=?
                            ORDER BY pay_date DESC, pay_id DESC LIMIT 1");
    $pstmt->execute([$cid]);
    $pay = $pstmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $clientName = trim((string)(($c['user_fn'] ?? '') . ' ' . ($c['user_ln'] ?? '')));
    $email = trim((string)($c['user_email'] ?? ''));
    $phone = trim((string)($c['user_phone'] ?? ''));
    $place = trim((string)($c['cp_place'] ?? ''));
    $dateNeeded = isset($c['cp_date']) && $c['cp_date'] !== '' ? date('F d, Y g:i A', strtotime((string)$c['cp_date'])) : '';
    $addons = trim((string)($c['cp_addon_pax'] ?? ''));
    $notes = trim((string)($c['cp_notes'] ?? ''));
    $price = (float)($c['cp_price'] ?? 0);
    $created = isset($c['created_at']) && $c['created_at'] !== '' ? date('F d, Y', strtotime((string)$c['created_at'])) : date('F d, Y');

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
    $addLine($content, 'Catering Service Agreement', 50, $y, 26);
    $content[] = "/F1 12 Tf\n";
    $addLine($content, 'Sandok ni Binggay Catering Services', 50, $y);
    $addLine($content, 'Date: ' . date('F d, Y'), 50, $y);
    $y -= 6;
    $addLine($content, 'This agreement outlines your catering request details.', 50, $y);
    $y -= 10;

    $content[] = "/F1 14 Tf\n"; $addLine($content, 'Client & Catering Details', 50, $y, 20);
    $content[] = "/F1 12 Tf\n";
    $addLine($content, 'Catering No.: #' . (int)$cid, 50, $y);
    $addLine($content, 'Client Name: ' . ($clientName !== '' ? $clientName : '—'), 50, $y);
    $addLine($content, 'Contact: ' . ($phone !== '' ? $phone : '—'), 50, $y);
    $addLine($content, 'Email: ' . ($email !== '' ? $email : '—'), 50, $y);
    $addLine($content, 'Place: ' . ($place !== '' ? $place : '—'), 50, $y);
    $addLine($content, 'Date Needed: ' . ($dateNeeded !== '' ? $dateNeeded : '—'), 50, $y);
    $addLine($content, 'Add-ons: ' . ($addons !== '' ? $addons : '—'), 50, $y);
    $addLine($content, 'Notes: ' . ($notes !== '' ? $notes : '—'), 50, $y);
    $addLine($content, 'Estimated Price: ₱' . number_format($price, 2), 50, $y);
    $addLine($content, $paySummary, 50, $y);

    $y -= 8;
    $content[] = "/F1 14 Tf\n"; $addLine($content, 'Terms & Conditions (Summary)', 50, $y, 20);
    $content[] = "/F1 12 Tf\n";
    $addLine($content, '• Client to confirm details and sign prior to service.', 50, $y);
    $addLine($content, '• Changes must be communicated at least 3 days prior.', 50, $y);
    $addLine($content, '• Payments follow the status listed above unless revised.', 50, $y);

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

    $fname = 'Contract_Catering_' . (int)$cid . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename=' . $fname);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to generate PDF.';
}
