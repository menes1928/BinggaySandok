<?php
/**
 * Partial: Rotating images for "Our Menu" section.
 * Fetches up to 10 image paths stored in site_settings (menu_section_images JSON array).
 * Falls back to existing two static Unsplash images if none configured.
 */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../classes/database.php';
$db = new database();
$images = [];
try {
    $pdo = $db->opencon();
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key='menu_section_images'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val) {
        $decoded = json_decode($val, true);
        if (is_array($decoded)) {
            $images = array_values(array_filter($decoded, function($p){ return is_string($p) && trim($p) !== ''; }));
        }
    }
} catch (Throwable $e) {
    $images = [];
}
if (empty($images)) {
    $images = [
        'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=1200&q=60',
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=60'
    ];
}
// Unique section id
$sectionId = 'menu-rotator-' . substr(md5(json_encode($images).microtime(true)),0,6);

// Detect the application base path (e.g., "/Binggay/").
// If the app is served from the web root (no leading folder), this will become "/".
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\','/', $_SERVER['SCRIPT_NAME']) : '';
$parts = array_values(array_filter(explode('/', trim($scriptName,'/'))));
$appBase = '/';
if (!empty($parts)) {
    // Assume first segment is the project folder only if a second segment exists (meaning not at true root) OR if the folder actually exists one level under document root.
    $first = $parts[0];
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
    if (count($parts) > 1 || ($docRoot && is_dir($docRoot.'/'.$first))) {
        $appBase = '/'.$first.'/';
    }
}

// Server path prefix for existence checks
$serverBasePath = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/').$appBase : null;

// Normalize stored paths (which may be in various legacy forms) into web-accessible URLs under the detected app base.
$normalizeLocal = static function(string $p, string $appBase, ?string $serverBasePath): ?string {
    $orig = $p = trim($p);
    if ($p === '') return null;
    // Strip query/hash fragments (unlikely for uploads but be safe)
    if (strpos($p,'?') !== false) { $p = explode('?', $p, 2)[0]; }
    if (strpos($p,'#') !== false) { $p = explode('#', $p, 2)[0]; }
    $p = str_replace('\\','/', $p);
    // Remove leading ../ sequences
    $p = preg_replace('~^(\.\./)+~','', $p);
    // Remove leading ./
    $p = preg_replace('~^\./+~','', $p);
    // Drop starting slashes so we can re-prefix consistently
    $p = ltrim($p,'/');
    // Remove admin/ or user/ prefixes if they precede uploads/
    if (preg_match('~^(admin|user)/(uploads/)~i',$p)) {
        $p = preg_replace('~^(admin|user)/~i','',$p);
    }
    // If somewhere inside path we have uploads/menu_hero, cut from uploads
    if (($pos = stripos($p,'uploads/')) !== false) {
        $p = substr($p,$pos);
    }
    // Ensure path begins with uploads/
    if (strpos($p,'uploads/') !== 0) {
        // Fall back: assume it's just a filename that belongs in menu_hero
        $p = 'uploads/menu_hero/'.basename($p);
    }
    // At this point we expect uploads/menu_hero/filename
    $webPath = rtrim($appBase,'/').'/'.ltrim($p,'/');
    // Collapse multiple slashes (except protocol part which we don't have here)
    $webPath = preg_replace('~/{2,}~','/', $webPath);
    // Optional: verify file exists to avoid broken backgrounds. If not found, return null to omit.
    if ($serverBasePath) {
        $candidate = rtrim($serverBasePath,'/').'/'.ltrim($p,'/');
        if (!is_file($candidate)) {
            return null; // File missing
        }
    }
    return $webPath;
};

$finalImages = [];
foreach ($images as $__im) {
    if (preg_match('~^https?://~i',$__im)) {
        $finalImages[] = $__im; // remote URL
    } else {
        $norm = $normalizeLocal($__im, $appBase, $serverBasePath);
        if ($norm !== null) { $finalImages[] = $norm; }
    }
}
// If after filtering none remain, keep the originals (without existence check) to attempt load or fallback static
if (empty($finalImages)) {
    $finalImages = $images; // possibly remote fallback already set earlier
}
$images = $finalImages;
unset($__im);
?>
<div id="<?php echo $sectionId; ?>" class="relative w-full mb-12">
    <div class="relative h-80 rounded-xl overflow-hidden group shadow-lg">
        <?php foreach ($images as $idx => $src):
            $safe = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        ?>
        <div class="absolute inset-0 bg-center bg-cover transition-opacity duration-[1500ms] <?php echo $idx===0 ? 'opacity-100' : 'opacity-0'; ?>" style="background-image:url('<?php echo $safe; ?>')" data-menu-rotator-slide></div>
        <?php endforeach; ?>
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 right-0 p-6 flex items-end justify-between">
            <div>
                <p class="text-yellow-400 text-sm tracking-widest uppercase mb-2 opacity-90">Featured Highlights</p>
                <h3 class="text-3xl md:text-4xl text-white font-serif leading-tight">Signature Creations</h3>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="flex gap-2" data-menu-rotator-dots>
                <?php foreach ($images as $i => $_): ?>
                    <button aria-label="Show image <?php echo $i+1; ?>" class="w-2.5 h-2.5 rounded-full border border-white/70 <?php echo $i===0?'bg-white':''; ?> transition" data-idx="<?php echo $i; ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function(){
    const root = document.getElementById('<?php echo $sectionId; ?>');
    if(!root) return;
    const slides = Array.from(root.querySelectorAll('[data-menu-rotator-slide]'));
    if(slides.length <= 1) return; // nothing to rotate
    const dotsWrap = root.querySelector('[data-menu-rotator-dots]');
    let idx = 0; let timer; const delay = 6000;
    function show(n){
        slides.forEach((s,i)=>{ s.style.opacity = (i===n? '1':'0'); });
        if(dotsWrap){ dotsWrap.querySelectorAll('button').forEach((b,i)=>{ b.classList.toggle('bg-white', i===n); }); }
        idx = n;
    }
    function next(){ show((idx+1)%slides.length); }
    function start(){ stop(); timer = setInterval(next, delay); }
    function stop(){ if(timer) clearInterval(timer); }
    if(dotsWrap){
        dotsWrap.addEventListener('click', e=>{
            const btn = e.target.closest('button[data-idx]'); if(!btn) return;
            const n = parseInt(btn.getAttribute('data-idx')); if(isNaN(n)) return;
            show(n); start();
        });
    }
    root.addEventListener('mouseenter', stop); root.addEventListener('mouseleave', start);
    start();
})();
</script>
<!-- End menu-hero partial -->
