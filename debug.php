<?php
// Secure Debug Dashboard for Binggay
// Access policy:
// - Always allowed from localhost (127.0.0.1 or ::1)
// - From remote, require a token via ?token=... that matches DEBUG_TOKEN from env or globals (e.g., set in classes/db_config.php)
//
// IMPORTANT: Do not deploy with a public token. Prefer to run locally.

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Try load optional db config so $DEBUG_TOKEN can come from there if defined
@include_once __DIR__ . '/classes/db_config.php';

function dbg_is_local(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($ip, ['127.0.0.1', '::1'], true);
}

function dbg_token(): ?string {
    if (getenv('DEBUG_TOKEN') !== false) return (string)getenv('DEBUG_TOKEN');
    if (defined('DEBUG_TOKEN')) return (string)DEBUG_TOKEN;
    if (isset($GLOBALS['DEBUG_TOKEN']) && $GLOBALS['DEBUG_TOKEN']) return (string)$GLOBALS['DEBUG_TOKEN'];
    return null;
}

$tokenConfigured = dbg_token();
$passedToken = isset($_GET['token']) ? (string)$_GET['token'] : '';
$authorized = dbg_is_local() || ($tokenConfigured && hash_equals($tokenConfigured, $passedToken));

if (!$authorized) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden - Debug access denied\n";
    echo "Tip: Run locally or supply ?token=YOUR_TOKEN (set DEBUG_TOKEN in env or db_config.php).\n";
    exit;
}

$now = date('c');
$summary = [
    'time' => $now,
    'appPath' => __DIR__,
    'php' => [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'os' => PHP_OS_FAMILY,
        'display_errors' => ini_get('display_errors'),
        'error_reporting' => (string)error_reporting(),
        'extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'openssl' => extension_loaded('openssl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'curl' => extension_loaded('curl'),
        ],
    ],
    'server' => [
        'server_name' => $_SERVER['SERVER_NAME'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
    ],
    'session' => [
        'status' => session_status(),
        'id' => session_id(),
        'has_user' => isset($_SESSION['user_id']) ? true : false,
        'user_keys' => array_values(array_intersect(array_keys($_SESSION ?? []), [
            'user_id','user_username','user_email','user_fn','user_ln','user_phone','user_photo'
        ])),
        'save_path' => ini_get('session.save_path') ?: '',
    ],
    'files' => [],
    'apache' => [
        'mod_rewrite' => function_exists('apache_get_modules') ? in_array('mod_rewrite', (array)@apache_get_modules(), true) : null,
        'htaccess_present' => is_file(__DIR__ . '/.htaccess'),
    ],
    'clean_url_examples' => [
        '/index' => is_file(__DIR__ . '/index.php'),
        '/menu' => is_file(__DIR__ . '/menu.php'),
        '/cateringpackages' => is_file(__DIR__ . '/cateringpackages.php'),
        '/booking' => is_file(__DIR__ . '/booking.php'),
        '/user/booking' => is_file(__DIR__ . '/user/booking.php'),
        '/user/cateringpackages' => is_file(__DIR__ . '/user/cateringpackages.php'),
    ],
    'uploads' => [],
    'db' => [
        'connected' => false,
        'driver' => 'mysql',
        'error' => null,
        'using_remote' => false,
    ],
];

// Key files existence
$checkFiles = [
    'index.php',
    '.htaccess',
    'classes/database.php',
    'classes/db_config.php',
    'user/partials/navbar.php',
    'partials/navbar-user.php',
    'user/api_notifications.php',
    'user/api_notifications_sse.php',
    'user/api_checkout.php',
];
foreach ($checkFiles as $rel) {
    $p = __DIR__ . '/' . $rel;
    $summary['files'][$rel] = [
        'exists' => is_file($p),
        'size' => is_file($p) ? filesize($p) : null,
        'mtime' => is_file($p) ? @date('c', filemtime($p)) : null,
    ];
}

// Uploads directories
$uploadDirs = [
    'uploads',
    'uploads/profile',
    'uploads/packages',
    'images',
];
foreach ($uploadDirs as $rel) {
    $p = __DIR__ . '/' . $rel;
    $summary['uploads'][$rel] = [
        'exists' => is_dir($p),
        'writable' => is_dir($p) ? is_writable($p) : null,
        'count' => is_dir($p) ? (function($d){ $c = 0; $h = @opendir($d); if ($h){ while(($e=readdir($h))!==false){ if($e==='.'||$e==='..') continue; $c++; } closedir($h);} return $c; })($p) : null,
    ];
}

// .htaccess contents (first 500 bytes) for quick view
$ht = __DIR__ . '/.htaccess';
$htSnippet = is_file($ht) ? substr((string)@file_get_contents($ht), 0, 500) : '';

// DB connectivity test (safe)
try {
    require_once __DIR__ . '/classes/database.php';
    // Inspect whether remote override is enabled by env/globals
    $useRemote = false;
    if (isset($GLOBALS['DB_USE_REMOTE'])) $useRemote = (bool)$GLOBALS['DB_USE_REMOTE'];
    elseif (defined('DB_USE_REMOTE')) $useRemote = (bool)DB_USE_REMOTE;
    elseif (getenv('DB_USE_REMOTE') !== false) $useRemote = filter_var(getenv('DB_USE_REMOTE'), FILTER_VALIDATE_BOOLEAN);
    $summary['db']['using_remote'] = $useRemote;

    $pdo = (new database())->opencon();
    $ok = $pdo->query('SELECT 1')->fetchColumn();
    $summary['db']['connected'] = $ok == 1;
} catch (Throwable $e) {
    $summary['db']['error'] = $e->getMessage();
}

// Optional: phpinfo when explicitly requested and authorized
if (isset($_GET['phpinfo'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>PHP Info</title></head><body>';
    echo '<h1>PHP Info</h1>';
    @phpinfo();
    echo '</body></html>';
    exit;
}

// Render simple HTML dashboard
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Binggay Debug</title>
    <style>
        body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#0b1f17;color:#e6f4ef;margin:0;padding:20px}
        h1{margin:0 0 12px;font-size:22px}
        .card{background:#112821;border:1px solid #1c3b30;border-radius:8px;padding:16px;margin:12px 0}
        pre{white-space:pre-wrap;word-break:break-word;background:#0e241d;border:1px solid #1a362d;border-radius:6px;padding:10px}
        .ok{color:#7ef7c9}.bad{color:#ff9595}.muted{opacity:.8}
        .kv{display:grid;grid-template-columns:240px 1fr;gap:8px 16px}
        .kv div:nth-child(odd){color:#b8dbd0}
        a.btn{display:inline-block;background:#1e7f5a;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;margin:6px 0}
    </style>
</head>
<body>
    <h1>Binggay Debug Dashboard</h1>

    <div class="card kv">
        <div>Time</div><div><?= htmlspecialchars($summary['time']) ?></div>
        <div>App Path</div><div><?= htmlspecialchars($summary['appPath']) ?></div>
        <div>PHP</div><div><?= htmlspecialchars($summary['php']['version']) ?> (<?= htmlspecialchars($summary['php']['sapi']) ?> on <?= htmlspecialchars($summary['php']['os']) ?>)</div>
        <div>Errors</div><div>display_errors=<?= htmlspecialchars((string)$summary['php']['display_errors']) ?>, level=<?= htmlspecialchars((string)$summary['php']['error_reporting']) ?></div>
        <div>Server</div><div><?= htmlspecialchars(($summary['server']['software'] ?? '') . ' @ ' . ($summary['server']['server_name'] ?? '')) ?></div>
        <div>Request</div><div><?= htmlspecialchars($summary['server']['request_uri'] ?? '') ?></div>
    </div>

    <div class="card kv">
        <div>PDO</div><div class="<?= $summary['php']['extensions']['pdo']?'ok':'bad' ?>"><?= $summary['php']['extensions']['pdo']?'loaded':'missing' ?></div>
        <div>PDO MySQL</div><div class="<?= $summary['php']['extensions']['pdo_mysql']?'ok':'bad' ?>"><?= $summary['php']['extensions']['pdo_mysql']?'loaded':'missing' ?></div>
        <div>mod_rewrite</div><div class="<?= ($summary['apache']['mod_rewrite']===true)?'ok':(($summary['apache']['mod_rewrite']===false)?'bad':'muted') ?>"><?php if($summary['apache']['mod_rewrite']===true) echo 'enabled'; elseif($summary['apache']['mod_rewrite']===false) echo 'disabled'; else echo 'unknown'; ?></div>
        <div>.htaccess</div><div class="<?= $summary['apache']['htaccess_present']?'ok':'bad' ?>"><?= $summary['apache']['htaccess_present']?'.htaccess found':'missing' ?></div>
        <div>DB (remote override)</div><div><?= $summary['db']['using_remote'] ? 'enabled' : 'disabled' ?></div>
        <div>DB connectivity</div><div class="<?= $summary['db']['connected']?'ok':'bad' ?>"><?= $summary['db']['connected']?'ok':('error: '.htmlspecialchars((string)$summary['db']['error'])) ?></div>
    </div>

    <div class="card">
        <strong>Key files</strong>
        <pre><?php foreach($summary['files'] as $name=>$info){ echo sprintf("%-36s : %s%s\n", $name, $info['exists']?'OK':'MISSING', $info['exists']?(' ('.($info['size']??'?').' bytes, mtime '.$info['mtime'].')'):''); } ?></pre>
    </div>

    <div class="card kv">
        <div>Session status</div><div><?= (int)$summary['session']['status'] ?></div>
        <div>Session id</div><div><?= htmlspecialchars((string)$summary['session']['id']) ?></div>
        <div>Session save path</div><div><?= htmlspecialchars((string)$summary['session']['save_path']) ?></div>
        <div>User in session</div><div><?= $summary['session']['has_user']?'yes':'no' ?> (keys: <?= htmlspecialchars(implode(', ',$summary['session']['user_keys'])) ?>)</div>
    </div>

    <div class="card">
        <strong>Uploads directories</strong>
        <pre><?php foreach($summary['uploads'] as $name=>$info){ echo sprintf("%-22s : %s, writable=%s, entries=%s\n", $name, $info['exists']?'OK':'MISSING', var_export($info['writable'],true), var_export($info['count'],true)); } ?></pre>
    </div>

    <?php if ($htSnippet): ?>
    <div class="card">
        <strong>.htaccess (first 500 bytes)</strong>
        <pre><?= htmlspecialchars($htSnippet) ?></pre>
        <div class="muted">Clean URL mapping exists for: <?php foreach ($summary['clean_url_examples'] as $p=>$ok){ if($ok) echo htmlspecialchars($p).' '; } ?></div>
    </div>
    <?php endif; ?>

    <div class="card">
        <a class="btn" href="?phpinfo=1<?= $tokenConfigured?('&token='.urlencode($tokenConfigured)) : '' ?>">Show phpinfo()</a>
        <span class="muted">(authorized only)</span>
    </div>
</body>
</html>
