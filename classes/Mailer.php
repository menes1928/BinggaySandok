<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\SMTP;

class Mailer
{
    private array $cfg;

    public function __construct()
    {
        $configPath = __DIR__ . '/../config/mail.php';
        $this->cfg = file_exists($configPath) ? (require $configPath) : [];
    }

    private function baseMailer(): PHPMailer
    {
        require_once __DIR__ . '/../PHPMailer/vendor/autoload.php';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->cfg['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $this->cfg['username'] ?? '';
        $mail->Password = $this->cfg['password'] ?? '';
        $enc = strtolower((string)($this->cfg['encryption'] ?? 'tls'));
        if ($enc === 'ssl') { $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; $mail->Port = (int)($this->cfg['port'] ?? 465); }
        else { $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = (int)($this->cfg['port'] ?? 587); }

        $fromEmail = $this->cfg['from_email'] ?? ($this->cfg['username'] ?? 'no-reply@example.com');
        $fromName  = $this->cfg['from_name'] ?? 'Sandok ni Binggay';
        $mail->setFrom($fromEmail, $fromName);
        if (!empty($this->cfg['reply_to'])) {
            $mail->addReplyTo($this->cfg['reply_to'], $fromName);
        }
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        // Optional debug output controlled by MAIL_DEBUG constant (used by test scripts)
        if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = 'html';
        }
        return $mail;
    }

    public function send(string $toEmail, string $toName, string $subject, string $html, ?string $plainText = null): bool
    {
        if (!$toEmail) return false;
        try {
            $mail = $this->baseMailer();
            $mail->addAddress($toEmail, $toName ?: $toEmail);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $plainText ?: strip_tags($html);
            return $mail->send();
        } catch (PHPMailerException $e) {
            // Log error in production
            return false;
        }
    }

    /**
     * Attempt to establish an SMTP connection without sending an email.
     * Returns an array with success flag, message and debug/details.
     */
    public function testConnection(): array
    {
        $debugBuffer = '';
        try {
            $mail = $this->baseMailer();
            // Force debug capture even if MAIL_DEBUG is not defined
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function ($str, $level) use (&$debugBuffer) {
                $debugBuffer .= '[' . $level . '] ' . $str . "\n";
            };
            $connected = $mail->smtpConnect();
            if ($connected) {
                $mail->smtpClose();
                return [
                    'success' => true,
                    'message' => 'SMTP connection established successfully',
                    'details' => [
                        'host' => $this->cfg['host'] ?? '',
                        'port' => (string)($this->cfg['port'] ?? ''),
                        'encryption' => (string)($this->cfg['encryption'] ?? ''),
                        'username' => $this->cfg['username'] ?? '',
                        'debug' => $debugBuffer,
                    ],
                ];
            }
            return [
                'success' => false,
                'message' => 'Unable to establish SMTP connection',
                'details' => [ 'debug' => $debugBuffer ],
            ];
        } catch (PHPMailerException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => [ 'debug' => $debugBuffer ],
            ];
        }
    }

    /**
     * Send a minimal test email to verify end-to-end delivery.
     */
    public function sendTestEmail(string $toEmail): array
    {
        $debugBuffer = '';
        try {
            $mail = $this->baseMailer();
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function ($str, $level) use (&$debugBuffer) {
                $debugBuffer .= '[' . $level . '] ' . $str . "\n";
            };
            $mail->addAddress($toEmail);
            $mail->Subject = 'Test Email - Sandok ni Binggay';
            $mail->Body = '<h1>Test Email</h1><p>This is a verification email from Sandok ni Binggay.</p>';
            $mail->AltBody = 'This is a verification email from Sandok ni Binggay.';
            $mail->send();
            return [
                'success' => true,
                'message' => 'Test email sent successfully',
                'details' => [ 'debug' => $debugBuffer ],
            ];
        } catch (PHPMailerException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => [ 'debug' => $debugBuffer ],
            ];
        }
    }

    // Booking templates
    public function renderBookingEmail(array $data, string $statusLabel): array
    {
        $userName = trim(($data['fullName'] ?? '') ?: (($data['user_fn'] ?? '') . ' ' . ($data['user_ln'] ?? '')));
        $eventType = (string)($data['event_type'] ?? $data['eb_type'] ?? 'Event Booking');
        $package = (string)($data['package'] ?? $data['eb_order'] ?? 'Selected Package');
        $date = (string)($data['event_date'] ?? $data['eb_date'] ?? '');
        if ($date) { $date = date('M d, Y g:i A', strtotime($date)); }
        $venue = (string)($data['venue'] ?? $data['eb_venue'] ?? '');
        $contact = (string)($data['contact'] ?? $data['eb_contact'] ?? '');
        $addons = (string)($data['addons'] ?? $data['eb_addon_pax'] ?? 'None');
        $notes = (string)($data['notes'] ?? $data['eb_notes'] ?? '');

        $subject = "Your Booking - {$statusLabel}";
        $brand = 'Sandok ni Binggay';
        $primary = '#1B4332'; $gold = '#D4AF37'; $text = '#0b2016';
        $html = "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width'>
        <title>{$subject}</title></head>
        <body style=\"margin:0;padding:0;background:#f5f7f9;font-family:Segoe UI,Roboto,Arial,sans-serif;color:{$text}\">
          <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background:#f5f7f9;padding:24px'>
            <tr><td align='center'>
              <table role='presentation' width='640' cellspacing='0' cellpadding='0' style='max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.08)'>
                <tr><td style='background:{$primary};padding:24px 28px'>
                  <h1 style='margin:0;font-size:22px;line-height:1.3;color:#fff'>{$brand}</h1>
                  <div style='margin-top:4px;font-size:12px;color:#e5f2ec;letter-spacing:.3px'>Booking Update</div>
                </td></tr>
                <tr><td style='padding:24px 28px'>
                  <p style='margin:0 0 12px'>Hi <strong>" . htmlspecialchars($userName ?: 'Guest') . "</strong>,</p>
                  <p style='margin:0 0 16px'>Here is the summary of your booking. Current status: <span style='display:inline-block;padding:4px 10px;border-radius:999px;background:{$gold};color:{$primary};font-weight:700;font-size:12px'>{$statusLabel}</span></p>
                  <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='margin:12px 0 8px'>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Event Type:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($eventType) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Package:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($package) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Date & Time:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($date) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Venue:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($venue) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Contact:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($contact) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Add-ons:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($addons ?: 'None') . "</td></tr>
                  </table>
                  " . ($notes ? ("<div style='margin-top:8px;padding:12px;border-radius:8px;background:#fbf7eb;border:1px solid #f1e3b6'><div style='font-weight:600;color:{$primary};margin-bottom:6px'>Notes</div><div>" . nl2br(htmlspecialchars($notes)) . "</div></div>") : '') . "
                  <p style='margin:16px 0 0;font-size:12px;color:#5b7268'>This is an automated message regarding your booking.</p>
                </td></tr>
                <tr><td style='background:#f0f6f3;padding:16px 28px;font-size:12px;color:#5b7268'>
                  © " . date('Y') . " {$brand}
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body></html>";
        return [$subject, $html];
    }

    // Catering templates
    public function renderCateringEmail(array $data, string $statusLabel): array
    {
        $userName = (string)($data['full_name'] ?? $data['cp_name'] ?? 'Valued Customer');
        $date = (string)($data['event_date'] ?? $data['cp_date'] ?? '');
        if ($date) { $date = date('M d, Y', strtotime($date)); }
        $place = (string)($data['place'] ?? $data['cp_place'] ?? '');
        $phone = (string)($data['phone'] ?? $data['cp_phone'] ?? '');
        $price = isset($data['total_price']) ? (float)$data['total_price'] : (float)($data['cp_price'] ?? 0);
        $addons = (string)($data['addons'] ?? $data['cp_addon_pax'] ?? 'None');
        $notes = (string)($data['notes'] ?? $data['cp_notes'] ?? '');
        $deposit = isset($data['deposit']) ? (float)$data['deposit'] : round($price * 0.5, 2);

        $subject = "Your Catering Package - {$statusLabel}";
        $brand = 'Sandok ni Binggay';
        $primary = '#1B4332'; $gold = '#D4AF37'; $text = '#0b2016';
        $fmt = fn($n) => '₱' . number_format((float)$n, 2);
        $html = "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width'>
        <title>{$subject}</title></head>
        <body style=\"margin:0;padding:0;background:#f5f7f9;font-family:Segoe UI,Roboto,Arial,sans-serif;color:{$text}\">
          <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background:#f5f7f9;padding:24px'>
            <tr><td align='center'>
              <table role='presentation' width='640' cellspacing='0' cellpadding='0' style='max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.08)'>
                <tr><td style='background:{$primary};padding:24px 28px'>
                  <h1 style='margin:0;font-size:22px;line-height:1.3;color:#fff'>{$brand}</h1>
                  <div style='margin-top:4px;font-size:12px;color:#e5f2ec;letter-spacing:.3px'>Catering Package</div>
                </td></tr>
                <tr><td style='padding:24px 28px'>
                  <p style='margin:0 0 12px'>Hi <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                  <p style='margin:0 0 16px'>Here are your catering details. Current payment status: <span style='display:inline-block;padding:4px 10px;border-radius:999px;background:{$gold};color:{$primary};font-weight:700;font-size:12px'>{$statusLabel}</span></p>
                  <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='margin:12px 0 8px'>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Event Date:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($date) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Location:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($place) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Contact:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($phone) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Total Price:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . $fmt($price) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Deposit (50%):</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . $fmt($deposit) . "</td></tr>
                    <tr><td style='padding:10px 0;border-top:1px solid #eee'><strong>Add-ons:</strong></td><td style='padding:10px 0;border-top:1px solid #eee' align='right'>" . htmlspecialchars($addons ?: 'None') . "</td></tr>
                  </table>
                  " . ($notes ? ("<div style='margin-top:8px;padding:12px;border-radius:8px;background:#fbf7eb;border:1px solid #f1e3b6'><div style='font-weight:600;color:{$primary};margin-bottom:6px'>Notes</div><div>" . nl2br(htmlspecialchars($notes)) . "</div></div>") : '') . "
                  <p style='margin:16px 0 0;font-size:12px;color:#5b7268'>This is an automated message regarding your catering package.</p>
                </td></tr>
                <tr><td style='background:#f0f6f3;padding:16px 28px;font-size:12px;color:#5b7268'>
                  © " . date('Y') . " {$brand}
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body></html>";
        return [$subject, $html];
    }
}
