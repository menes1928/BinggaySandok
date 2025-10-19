<?php
// user/api_notifications_sse.php
// Server-Sent Events for real-time notification updates for logged-in users

declare(strict_types=1);

// Keep the connection alive longer than default
set_time_limit(0);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
// Helpful on some proxies/servers to avoid buffering (ignored on Apache if not supported)
header('X-Accel-Buffering: no');

// Flush any output buffers
while (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

require_once __DIR__ . '/../classes/database.php';

// Helper: send an SSE message
function sse_send(string $event, array $data, ?int $id = null): void {
    if ($id !== null) {
        echo 'id: ' . $id . "\n";
    }
    if ($event !== '') {
        echo 'event: ' . $event . "\n";
    }
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n\n";
    @flush();
}

// Optional Last-Event-ID or since param to avoid sending old changes
$sinceParam = isset($_GET['since']) ? (int)$_GET['since'] : 0;
$lastEventIdHeader = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
$lastKnown = max(0, $sinceParam, $lastEventIdHeader);

$userId = (int)$_SESSION['user_id'];

try {
    $db = new database();
    $pdo = $db->opencon();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inform client about retry backoff
    echo "retry: 5000\n\n"; // 5s reconnection delay if disconnected
    @flush();

    $start = time();
    $timeout = 30; // seconds per request; client will reconnect automatically
    $keepAliveEvery = 10; // seconds
    $lastPing = time();

    // Poll for changes until timeout
    while (time() - $start < $timeout) {
        // Query the latest change across orders.updated_at and payments.created_at for this user
        // Use FROM_UNIXTIME(0) as neutral baseline
        $sql = "
            SELECT UNIX_TIMESTAMP(
                GREATEST(
                    COALESCE((SELECT MAX(o.updated_at) FROM orders o WHERE o.user_id = :uid), FROM_UNIXTIME(0)),
                    COALESCE((SELECT MAX(p.created_at) FROM payments p WHERE p.user_id = :uid), FROM_UNIXTIME(0))
                )
            ) AS latest_ts
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $latestTs = isset($row['latest_ts']) ? (int)$row['latest_ts'] : 0;

        if ($latestTs > $lastKnown) {
            // Send a change event; client can re-fetch details via existing JSON endpoint
            sse_send('change', ['latest' => $latestTs], $latestTs);
            // End stream so client reconnects with Last-Event-ID set
            break;
        }

        // Periodic keep-alive to prevent timeouts on some proxies
        if (time() - $lastPing >= $keepAliveEvery) {
            echo ": ping\n\n"; // comment line as SSE keepalive
            @flush();
            $lastPing = time();
        }

        // Sleep briefly before next check
        usleep(500000); // 0.5s
    }
} catch (Throwable $e) {
    // Send an error event then exit
    sse_send('error', ['message' => 'Server error'], null);
}

// End of request; connection will close and client will reconnect
exit;
