<?php
// User navbar: renders with user controls and correct relative paths under /user
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

// This variant assumes logged-in user; fall back to session check
$FORCE_NAV_MODE = 'user';
$NAV_IS_LOGGED_IN = !empty($_SESSION['user_id']);

require_once __DIR__ . '/../classes/database.php';

function normalize_user_photo_path_user(?string $raw): ?string {
    if ($raw === null) return null;
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    // Normalize slashes
    $raw = str_replace('\\', '/', $raw);
    // Strip leading ./ or ../ segments
    $raw = preg_replace('~^(?:\./|\.\./)+~', '', $raw);
    // Full URL
    if (preg_match('~^https?://~i', $raw)) return $raw;

    // If it's an absolute filesystem path, try to extract after /Binggay/
    if (preg_match('~Binggay/(.+)$~i', $raw, $m)) {
        $rel = ltrim($m[1], '/');
        return '/Binggay/' . $rel;
    }

    // If already starts with a known web-rooted segment
    if (preg_match('~^(uploads(?:/profile)?|profile|profiles|images)(/|$)~i', $raw)) {
        return '/Binggay/' . ltrim($raw, '/');
    }

    // If looks like a Windows absolute path without Binggay segment, use the basename
    if (preg_match('~^[A-Za-z]:/|^/~', $raw)) {
        $base = basename($raw);
        if ($base !== '') {
            return '/Binggay/uploads/profile/' . $base;
        }
    }

    // If it's just a filename, assume it's in uploads/profile/
    if (strpos($raw, '/') === false) {
        return '/Binggay/uploads/profile/' . $raw;
    }

    // Fallback: ensure it is under site root
    return '/Binggay/' . ltrim($raw, '/');
}

function current_user_avatar_user(): string {
    $default = '/Binggay/images/logo.png';
    $id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($id <= 0) return $default;

    // Always fetch from DB to ensure the latest image is used
    try {
        $db = new database();
        $pdo = $db->opencon();
        $stmt = $pdo->prepare('SELECT user_photo FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['user_photo'])) {
            $web = normalize_user_photo_path_user((string)$row['user_photo']);
            if ($web !== null) {
                return $web;
            }
        }
    } catch (Throwable $e) {}

    // As a fallback, try session value if present
    if (!empty($_SESSION['user_photo'])) {
        $web = normalize_user_photo_path_user((string)$_SESSION['user_photo']);
        if ($web !== null) {
            return $web;
        }
    }

    return $default;
}

function current_user_display_name_user() {
    if (!empty($_SESSION['user_username'])) return (string)$_SESSION['user_username'];
    $fn = isset($_SESSION['user_fn']) ? trim((string)$_SESSION['user_fn']) : '';
    $ln = isset($_SESSION['user_ln']) ? trim((string)$_SESSION['user_ln']) : '';
    $name = trim($fn . ' ' . $ln);
    if ($name !== '') return $name;
    if (!empty($_SESSION['user_email'])) return (string)$_SESSION['user_email'];
    return 'Account';
}
?>

<header class="nav-root nav-clear fixed top-0 left-0 right-0 z-50">
    <div class="container mx-auto px-6 py-3">
        <nav class="flex items-center justify-between">
            <!-- Logo -->
            <a href="index" class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center overflow-hidden">
                    <img src="../images/logo.png" alt="Sandok ni Binggay Logo" class="w-10 h-10 object-contain" />
                </div>
                <div>
                    <h1 class="text-yellow-400 text-base md:text-sm lg:text-lg xl:text-xl font-semibold leading-tight">Sandok ni Binggay</h1>
                    <p class="text-yellow-300 text-[11px] md:text-[10px] tracking-wider">CATERING SERVICES</p>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-3 lg:space-x-8">
                <?php $current = strtolower(basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))); ?>
                <?php
                    $links = [
                        ['href' => 'index', 'label' => 'Home', 'key' => 'index'],
                        ['href' => 'menu', 'label' => 'Menu', 'key' => 'menu'],
                        ['href' => 'cateringpackages', 'label' => 'Catering', 'key' => 'cateringpackages'],
                        ['href' => 'booking', 'label' => 'Occasions', 'key' => 'booking'],
                    ];
                ?>
                <?php foreach ($links as $ln): $isActive = ($current === strtolower($ln['key'])); ?>
                    <a href="<?php echo htmlspecialchars($ln['href']); ?>"
                       class="nav-link px-1.5 py-2 text-xs lg:text-base transition-colors duration-300 relative group <?php echo $isActive ? 'text-amber-400' : ''; ?>">
                        <?php echo htmlspecialchars($ln['label']); ?>
                        <span class="absolute -bottom-1 left-0 h-0.5 bg-amber-400 transition-all duration-300 <?php echo $isActive ? 'w-10' : 'w-0 group-hover:w-full'; ?>"></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Auth / Profile -->
            <div class="hidden md:flex items-center space-x-2 lg:space-x-4">
                <?php if ($NAV_IS_LOGGED_IN): ?>
                    <!-- Notifications Button + Dropdown (moved before Cart) -->
                    <div class="relative" id="nav-notif">
                        <button id="nav-notif-btn" class="px-2 py-1.5 rounded border-2 transition-colors flex items-center gap-2 relative text-xs md:text-xs lg:text-sm">
                            <i class="fas fa-bell"></i>
                            <span class="sr-only">Notifications</span>
                            <span id="notifBadge" class="hidden absolute -top-2 -right-2 bg-rose-500 text-white rounded-full w-5 h-5 items-center justify-center text-[10px] font-bold"></span>
                        </button>
                        <div id="notifDropdown" class="absolute right-0 mt-2 w-96 max-w-[95vw] rounded-md bg-white shadow-lg ring-1 ring-black/5 hidden">
                            <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-700">Order updates</span>
                                <button id="notifRefresh" class="text-xs text-amber-600 hover:text-amber-700">Refresh</button>
                            </div>
                            <div id="notifList" class="max-h-[70vh] overflow-auto divide-y divide-gray-100">
                                <div class="p-4 text-sm text-gray-500">Loading…</div>
                            </div>
                            <div class="p-2 border-t border-gray-100 text-right">
                                <a href="profile#orders" class="text-xs text-amber-600 hover:text-amber-700">View all</a>
                            </div>
                        </div>
                    </div>
                    <!-- Global Cart Button (visible only when logged in) -->
                    <button id="nav-cart-btn" class="px-2 py-1.5 rounded border-2 transition-colors flex items-center gap-2 relative text-xs md:text-xs lg:text-sm">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="sr-only">Cart</span>
                        <span id="cartBadge" class="hidden absolute -top-2 -right-2 bg-amber-500 text-white rounded-full w-5 h-5 items-center justify-center text-[10px] font-bold"></span>
                    </button>
                    <div class="relative" id="nav-profile">
                        <button id="profile-btn" class="flex items-center gap-2 text-white hover:text-yellow-400 transition-colors">
                            <img src="<?php echo htmlspecialchars(current_user_avatar_user()); ?>" alt="Avatar" class="w-9 h-9 md:w-8 md:h-8 lg:w-9 lg:h-9 rounded-full object-cover border border-yellow-400/30" onerror="this.onerror=null;this.src='/Binggay/images/logo.png';" />
                            <span class="max-w-[160px] md:max-w-[120px] lg:max-w-[200px] truncate text-sm md:text-xs lg:text-base"><span class="hidden lg:inline">Welcome, </span><?php echo htmlspecialchars(current_user_display_name_user()); ?></span>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                        </button>
                        <div id="profile-menu" class="absolute right-0 mt-2 w-44 rounded-md bg-white shadow-lg ring-1 ring-black/5 py-1 hidden">
                            <a href="profile" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Profile</a>
                            <a href="logout.php" class="block px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a id="login-btn-nav" href="../login" class="px-4 py-2 rounded-full bg-amber-400 text-green-900 hover:bg-amber-300 transition flex items-center gap-2 font-medium text-sm md:text-sm lg:text-base">
                        <i class="fas fa-user"></i>
                        Login
                    </a>
                    <a id="signup-btn-nav" href="../registration" class="px-4 py-2 rounded-full border-2 text-white border-white hover:bg-white hover:text-green-900 transition-colors font-medium text-sm md:text-sm lg:text-base">Sign up</a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden flex items-center gap-3">
                <?php if ($NAV_IS_LOGGED_IN): ?>
                <button id="nav-notif-btn-mobile" class="p-2 rounded border-2 text-white border-white hover:bg-white hover:text-green-900 transition-colors relative">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge hidden absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-4 h-4 items-center justify-center text-[9px] font-bold"></span>
                </button>
                <button id="nav-cart-btn-mobile" class="p-2 rounded border-2 text-white border-white hover:bg-white hover:text-green-900 transition-colors relative">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-badge hidden absolute -top-2 -right-2 bg-amber-500 text-white rounded-full w-5 h-5 items-center justify-center text-[10px] font-bold"></span>
                </button>
                <?php endif; ?>
                <button id="mobile-menu-btn" class="text-white hover:text-yellow-400 transition-colors duration-300">
                <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </nav>

        <!-- Mobile Navigation -->
        <div id="mobile-menu" class="md:hidden absolute top-full left-0 right-0 bg-green-900/95 backdrop-blur-sm border-t border-green-700 hidden">
            <div class="container mx-auto px-6 py-4 space-y-4">
                <a href="index" class="block nav-link transition-colors duration-300">Home</a>
                <a href="menu" class="block nav-link transition-colors duration-300">Menu</a>
                <a href="cateringpackages" class="block nav-link transition-colors duration-300">Catering</a>
                <a href="booking" class="block nav-link transition-colors duration-300">Occasions</a>
                <?php if ($NAV_IS_LOGGED_IN): ?>
                    <a href="profile" class="block nav-link transition-colors duration-300">Profile</a>
                    <a href="logout.php" class="block nav-link transition-colors duration-300">Logout</a>
                <?php else: ?>
                    <a href="../login" class="block nav-link transition-colors duration-300">Login</a>
                    <a href="../registration" class="block nav-link transition-colors duration-300">Sign up</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mobile Notifications Panel (small, readable) -->
        <div id="notifMobileOverlay" class="fixed inset-0 z-[60] hidden">
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="relative mx-auto mt-24 w-[92vw] max-w-sm rounded-lg bg-white shadow-xl ring-1 ring-black/5">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Order updates</span>
                    <div class="flex items-center gap-2">
                        <button id="notifRefreshMobile" class="text-xs text-amber-600 hover:text-amber-700 px-2 py-1 rounded">Refresh</button>
                        <button id="notifCloseMobile" class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1 rounded" aria-label="Close notifications">Close</button>
                    </div>
                </div>
                <div id="notifListMobile" class="max-h-[70vh] overflow-auto divide-y divide-gray-100">
                    <div class="p-4 text-sm text-gray-500">Loading…</div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    (function(){
        const navRoot = document.querySelector('header.nav-root');
        const loginBtn = document.getElementById('login-btn-nav');
        const links = Array.from(document.querySelectorAll('.nav-link'));
        const profileBtn = document.getElementById('profile-btn');
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
    const cartBtns = Array.from(document.querySelectorAll('#nav-cart-btn, #nav-cart-btn-mobile'));
    const notifBtns = Array.from(document.querySelectorAll('#nav-notif-btn, #nav-notif-btn-mobile'));
    const notifBtn = document.getElementById('nav-notif-btn');
    const notifBtnMobile = document.getElementById('nav-notif-btn-mobile');
    const notifBadge = document.getElementById('notifBadge');
    const notifBadges = Array.from(document.querySelectorAll('.notif-badge'));
    const notifDropdown = document.getElementById('notifDropdown');
    const notifList = document.getElementById('notifList');
    const notifRefresh = document.getElementById('notifRefresh');
    // Mobile notif elements
    const notifMobileOverlay = document.getElementById('notifMobileOverlay');
    const notifListMobile = document.getElementById('notifListMobile');
    const notifCloseMobile = document.getElementById('notifCloseMobile');
    const notifRefreshMobile = document.getElementById('notifRefreshMobile');
    const hideNotifBadges = () => {
        if (notifBadge) notifBadge.classList.add('hidden');
        notifBadges.forEach(el => el && el.classList.add('hidden'));
    };
    // No toast UI; badge-only UX

        const setScheme = (solid) => {
            if (!navRoot) return;
            navRoot.classList.toggle('nav-solid', solid);
            if (solid) {
                navRoot.classList.add('bg-white/95','backdrop-blur','border-b','border-green-200','shadow');
                navRoot.classList.remove('bg-transparent','border-transparent','shadow-none');
            } else {
                navRoot.classList.add('bg-transparent');
                navRoot.classList.remove('bg-white/95','border-b','border-green-200','shadow');
            }
            links.forEach(a => {
                a.classList.remove('text-white','text-green-900');
                a.classList.add(solid ? 'text-green-900' : 'text-white');
            });
            if (profileBtn) {
                profileBtn.classList.remove('text-white','hover:text-yellow-400','text-green-900','hover:text-green-700');
                if (solid) {
                    profileBtn.classList.add('text-green-900','hover:text-green-700');
                } else {
                    profileBtn.classList.add('text-white','hover:text-yellow-400');
                }
            }
            if (mobileBtn) {
                mobileBtn.classList.remove('text-white');
                mobileBtn.classList.remove('text-green-900');
                mobileBtn.classList.add(solid ? 'text-green-900' : 'text-white');
            }
            const signupBtn = document.getElementById('signup-btn-nav');
            if (signupBtn) {
                signupBtn.classList.remove('border-white','text-white','hover:bg-white','hover:text-green-900');
                signupBtn.classList.remove('border-green-800','text-green-800','hover:bg-green-800','hover:text-white');
                if (solid) {
                    signupBtn.classList.add('border-green-800','text-green-800','hover:bg-green-800','hover:text-white');
                } else {
                    signupBtn.classList.add('border-white','text-white','hover:bg-white','hover:text-green-900');
                }
            }
            cartBtns.forEach(btn => {
                btn.classList.remove('border-white','text-white','hover:bg-white','hover:text-green-900');
                btn.classList.remove('border-green-800','text-green-800','hover:bg-green-800','hover:text-white');
                if (solid) {
                    btn.classList.add('border-green-800','text-green-800','hover:bg-green-800','hover:text-white');
                } else {
                    btn.classList.add('border-white','text-white','hover:bg-white','hover:text-green-900');
                }
            });
            notifBtns.forEach(btn => {
                btn.classList.remove('border-white','text-white','hover:bg-white','hover:text-green-900');
                btn.classList.remove('border-green-800','text-green-800','hover:bg-green-800','hover:text-white');
                if (solid) {
                    btn.classList.add('border-green-800','text-green-800','hover:bg-green-800','hover:text-white');
                } else {
                    btn.classList.add('border-white','text-white','hover:bg-white','hover:text-green-900');
                }
            });
        };

        const targets = Array.from(document.querySelectorAll('[data-nav-contrast="dark"]'));
        if (targets.length && 'IntersectionObserver' in window) {
            const vis = new Map();
            const recompute = () => {
                const anyVisible = Array.from(vis.values()).some(Boolean);
                setScheme(!anyVisible);
            };
            const io = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const isVisible = entry.isIntersecting && entry.intersectionRatio > 0;
                    vis.set(entry.target, isVisible);
                });
                recompute();
            }, { root: null, threshold: [0, 0.05, 0.1], rootMargin: '-80px 0px 0px 0px' });
            targets.forEach(t => {
                const r = t.getBoundingClientRect();
                const initiallyVisible = r.top <= 80 && r.bottom > 0;
                vis.set(t, initiallyVisible);
                io.observe(t);
            });
            recompute();
        } else {
            const threshold = 140;
            let ticking = false;
            const onScroll = () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        const solid = window.scrollY > threshold;
                        setScheme(solid);
                        ticking = false;
                    });
                    ticking = true;
                }
            };
            setScheme(window.scrollY > threshold);
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll);
        }

        const menu = document.getElementById('profile-menu');
        const profileBtnEl = document.getElementById('profile-btn');
        if (profileBtnEl && menu) {
            const toggle = () => menu.classList.toggle('hidden');
            const close = (e) => { if (!menu.contains(e.target) && !profileBtnEl.contains(e.target)) menu.classList.add('hidden'); };
            profileBtnEl.addEventListener('click', (e)=>{ e.stopPropagation(); toggle(); });
            document.addEventListener('click', close);
        }

        let isOpen = false;
        const setIcon = () => {
            if (!mobileBtn) return;
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                mobileBtn.innerHTML = isOpen ? '<i data-lucide="x" class="w-6 h-6"></i>' : '<i data-lucide="menu" class="w-6 h-6"></i>';
                mobileBtn.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
                window.lucide.createIcons();
            } else {
                // Fallback to icon glyphs instead of text labels
                mobileBtn.innerHTML = isOpen
                    ? '<span aria-hidden="true" class="text-2xl leading-none">✕</span>'
                    : '<span aria-hidden="true" class="text-2xl leading-none">☰</span>';
                mobileBtn.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
            }
        };
        if (mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', function(){
                isOpen = !isOpen;
                mobileMenu.classList.toggle('hidden', !isOpen);
                setIcon();
            });
            setIcon();
        }

        const handleCartClick = (e) => {
            e.preventDefault();
            try {
                const hasToggle = typeof window.toggleCart === 'function';
                const hasSidebar = document.getElementById('cartSidebar');
                if (hasToggle && hasSidebar) {
                    window.toggleCart();
                    return;
                }
            } catch (_) {}
            window.location.href = 'menu.php#cart';
        };
        cartBtns.forEach(btn => btn && btn.addEventListener('click', handleCartClick));

        // Per-user cart badge based on localStorage key
        try {
            const userId = <?php echo $NAV_IS_LOGGED_IN ? (int)$_SESSION['user_id'] : 0; ?>;
            if (userId) {
                const CART_KEY = `binggay_cart_v1_u${userId}`;
                const badgeEl = document.getElementById('cartBadge');
                const mobileBadges = Array.from(document.querySelectorAll('.cart-badge'));
                const applyCartBadge = () => {
                    try {
                        const raw = localStorage.getItem(CART_KEY);
                        const items = raw ? JSON.parse(raw) : [];
                        const count = Array.isArray(items) ? items.reduce((sum, it) => sum + (Number(it.qty||1)||1), 0) : 0;
                        const set = (el) => {
                            if (!el) return;
                            if (count > 0) {
                                el.textContent = String(count);
                                el.classList.remove('hidden');
                                el.classList.add('flex');
                            } else {
                                el.classList.add('hidden');
                            }
                        };
                        set(badgeEl);
                        mobileBadges.forEach(set);
                    } catch (_) {}
                };
                // Initial and cross-tab sync
                applyCartBadge();
                window.addEventListener('storage', (e) => {
                    if (e && e.key === CART_KEY) applyCartBadge();
                });
                document.addEventListener('visibilitychange', () => { if (!document.hidden) applyCartBadge(); });
                // Expose for cart UI to call after changes
                window.__binggay_cart_badge_refresh = applyCartBadge;
            }
        } catch(_) {}

        // Notifications dropdown behavior and fetch
        let notifOpen = false;
        let notifAutoTimer = null;
        const toggleNotif = () => {
            if (!notifDropdown) return;
            notifOpen = !notifOpen;
            notifDropdown.classList.toggle('hidden', !notifOpen);
            if (notifOpen) {
                loadNotifications(false, notifList);
                if (notifAutoTimer) clearInterval(notifAutoTimer);
                notifAutoTimer = setInterval(() => loadNotifications(true, notifList), 15000);
            }
        };
        const closeNotif = (e) => {
            if (!notifDropdown) return;
            const trigger = notifBtn?.parentElement;
            const isInside = notifDropdown.contains(e.target) || (trigger && trigger.contains(e.target));
            if (!isInside) {
                notifDropdown.classList.add('hidden');
                notifOpen = false;
                if (notifAutoTimer) { clearInterval(notifAutoTimer); notifAutoTimer = null; }
            }
        };
        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e)=>{ 
                e.preventDefault(); 
                e.stopPropagation(); 
                toggleNotif();
                if (typeof window.__binggay_mark_notif_seen === 'function') window.__binggay_mark_notif_seen();
                hideNotifBadges();
            });
            document.addEventListener('click', closeNotif);
        }
        if (notifRefresh) {
            notifRefresh.addEventListener('click', (e)=>{ e.preventDefault(); loadNotifications(true, notifList); });
        }
        // Mobile notifications panel behavior
        if (notifBtnMobile) {
            notifBtnMobile.addEventListener('click', (e)=>{
                e.preventDefault();
                if (!notifMobileOverlay || !notifListMobile) {
                    // Fallback: navigate to notifications section
                    if (typeof window.__binggay_mark_notif_seen === 'function') window.__binggay_mark_notif_seen();
                    hideNotifBadges();
                    window.location.href = 'menu.php#notifications';
                    return;
                }
                if (typeof window.__binggay_mark_notif_seen === 'function') window.__binggay_mark_notif_seen();
                hideNotifBadges();
                notifMobileOverlay.classList.remove('hidden');
                document.documentElement.classList.add('overflow-hidden');
                document.body.classList.add('overflow-hidden');
                loadNotifications(true, notifListMobile);
            });
        }
        if (notifCloseMobile && notifMobileOverlay) {
            const closeMobilePanel = () => {
                notifMobileOverlay.classList.add('hidden');
                document.documentElement.classList.remove('overflow-hidden');
                document.body.classList.remove('overflow-hidden');
            };
            notifCloseMobile.addEventListener('click', (e)=>{ e.preventDefault(); closeMobilePanel(); });
            notifMobileOverlay.addEventListener('click', (e)=>{ if (e.target === notifMobileOverlay) closeMobilePanel(); });
        }
        if (notifRefreshMobile) {
            notifRefreshMobile.addEventListener('click', (e)=>{ e.preventDefault(); loadNotifications(true, notifListMobile); });
        }

        async function loadNotifications(force = false, listEl) {
            const target = listEl || notifList;
            if (!target) return;
            target.innerHTML = '<div class="p-4 text-sm text-gray-500">Loading…</div>';
            try {
                const res = await fetch('api_notifications.php?all=1', { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                const items = (data && Array.isArray(data.items)) ? data.items : [];
                renderNotificationsTo(items, target);
            } catch (err) {
                target.innerHTML = '<div class="p-4 text-sm text-rose-600">Failed to load notifications.</div>';
            }
        }

        function renderNotifications(items) {
            // Back-compat for desktop dropdown
            renderNotificationsTo(items, notifList);
        }

        function renderNotificationsTo(items, target) {
            const listTarget = target || notifList;
            if (!listTarget) return;
            if (!Array.isArray(items) || items.length === 0) {
                listTarget.innerHTML = '<div class="p-4 text-sm text-gray-500">No recent orders yet.</div>';
                [notifBadge, ...notifBadges].filter(Boolean).forEach(el => el.classList.add('hidden'));
                return;
            }
            const frag = document.createDocumentFragment();
            let unreadCount = 0;
            let latestTs = 0;
            items.forEach(it => {
                const li = document.createElement('div');
                li.className = 'p-3 hover:bg-amber-50 transition-colors';
                const statusClass = statusBadgeClass(it.order_status);
                const pay = it.payment || {};
                // time hidden per request
                const needed = it.order_needed ? `Needed: ${fmtDate(it.order_needed)}` : '';
                li.innerHTML = `
                    <div class="flex items-start gap-3">
                        <div class="mt-1">
                            <span class="inline-block text-[10px] px-2 py-0.5 rounded-full font-semibold ${statusClass}">${escapeHtml(it.order_status_label || it.order_status)}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-sm font-medium text-gray-800 truncate">Order #${it.order_id}</div>
                                <!-- time hidden per request -->
                            </div>
                            <div class="text-xs text-gray-600 mt-0.5">Total: ₱${Number(it.order_amount || 0).toFixed(2)}</div>
                            <div class="text-xs text-gray-500">${needed}</div>
                            ${pay.status ? `<div class="text-[11px] text-gray-600 mt-0.5">Payment: <span class="font-medium">${escapeHtml(pay.status)}</span>${pay.method ? ` via ${escapeHtml(pay.method)}` : ''}</div>` : ''}
                        </div>
                        <a href="order_details.php?id=${encodeURIComponent(it.order_id)}" class="text-xs text-amber-600 hover:text-amber-700 whitespace-nowrap ml-2">View</a>
                    </div>
                `;
                const ts = safeParseDate(it.updated_at) || safeParseDate(it.order_date) || 0;
                if (ts > latestTs) latestTs = ts;
                const lastSeenLocal = (typeof window.__binggay_notif_last_seen === 'number') ? window.__binggay_notif_last_seen : 0;
                if (ts > lastSeenLocal) unreadCount++;
                frag.appendChild(li);
            });
            listTarget.innerHTML = '';
            listTarget.appendChild(frag);
            const lastSeen = (typeof window.__binggay_notif_last_seen === 'number') ? window.__binggay_notif_last_seen : 0;
            const shouldShow = latestTs > lastSeen && unreadCount > 0;
            const updateBadge = (el) => {
                if (!el) return;
                if (shouldShow) {
                    el.textContent = String(unreadCount);
                    el.classList.remove('hidden');
                    el.classList.add('flex');
                } else {
                    el.classList.add('hidden');
                }
            };
            updateBadge(notifBadge);
            notifBadges.forEach(updateBadge);
        }

        function statusBadgeClass(status) {
            switch (String(status)) {
                case 'pending': return 'bg-yellow-100 text-yellow-700';
                case 'in-progress': return 'bg-blue-100 text-blue-700';
                case 'completed': return 'bg-green-100 text-green-700';
                case 'canceled': return 'bg-rose-100 text-rose-700';
                default: return 'bg-gray-100 text-gray-700';
            }
        }
        function fmtDate(val) {
            if (!val) return '';
            try {
                const d = new Date(String(val).replace(' ', 'T'));
                if (isNaN(d.getTime())) return String(val);
                return d.toLocaleString();
            } catch(_) { return String(val); }
        }
        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
        }

        window.SNB_USER = Object.assign({}, window.SNB_USER || {}, { loggedIn: <?php echo $NAV_IS_LOGGED_IN ? 'true' : 'false'; ?> });

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        // Background badge updater (no toast)
        try {
            const userId = <?php echo $NAV_IS_LOGGED_IN ? (int)$_SESSION['user_id'] : 0; ?>;
            if (userId) {
                const LS_KEY = `binggay_notif_last_seen_u${userId}`;
                let lastSeen = safeParseDate(localStorage.getItem(LS_KEY));
                window.__binggay_notif_last_seen = lastSeen;

                const applyBadge = (unread) => {
                    const set = (el) => {
                        if (!el) return;
                        if (unread > 0) {
                            el.textContent = String(unread);
                            el.classList.remove('hidden');
                            el.classList.add('flex');
                        } else {
                            el.classList.add('hidden');
                        }
                    };
                    set(notifBadge);
                    notifBadges.forEach(set);
                };

                const checkForUpdates = async () => {
                    try {
                        const res = await fetch('api_notifications.php?all=1', { credentials: 'same-origin' });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (!data || !Array.isArray(data.items)) return;
                        const unseen = data.items.reduce((count, it) => {
                            const ts = safeParseDate(it.updated_at) || safeParseDate(it.order_date) || 0;
                            return (lastSeen && ts <= lastSeen) ? count : count + 1;
                        }, 0);
                        applyBadge(unseen);
                    } catch (_) {}
                };

                function markSeen() {
                    const nowTs = Date.now();
                    localStorage.setItem(LS_KEY, String(nowTs));
                    lastSeen = nowTs;
                    window.__binggay_notif_last_seen = nowTs;
                }
                // expose locally
                window.__binggay_mark_notif_seen = markSeen;

                setTimeout(checkForUpdates, 2000);
                setInterval(checkForUpdates, 45000);

                // Live updates via Server-Sent Events (SSE)
                let evtSource = null;
                let sseBackoff = 2000;
                const sseMaxBackoff = 15000;
                let lastEventId = 0;

                function startSSE() {
                    try {
                        const since = Math.max(lastSeen || 0, lastEventId || 0);
                        const url = `user/api_notifications_sse.php?since=${since}`;
                        evtSource = new EventSource(url, { withCredentials: true });

                        evtSource.addEventListener('open', () => {
                            sseBackoff = 2000;
                        });

                        evtSource.addEventListener('change', async (ev) => {
                            try {
                                lastEventId = Number(ev.lastEventId || 0) || lastEventId;
                                await checkForUpdates();
                                if (typeof notifOpen !== 'undefined' && notifOpen) {
                                    loadNotifications(true, notifList);
                                }
                            } catch (_) {}
                        });

                        evtSource.addEventListener('error', () => {
                            try { evtSource.close(); } catch(_) {}
                            evtSource = null;
                            setTimeout(startSSE, sseBackoff);
                            sseBackoff = Math.min(sseMaxBackoff, Math.floor(sseBackoff * 2.2));
                        });
                    } catch (_) {
                        setTimeout(startSSE, sseBackoff);
                        sseBackoff = Math.min(sseMaxBackoff, Math.floor(sseBackoff * 2.2));
                    }
                }

                if ('EventSource' in window) {
                    setTimeout(startSSE, 1500);
                    window.addEventListener('beforeunload', () => { try { evtSource && evtSource.close(); } catch(_) {} });
                }
            }
        } catch(_) {}

        function safeParseDate(v){
            if (!v) return 0;
            try {
                const t = new Date(String(v).replace(' ', 'T')).getTime();
                return isNaN(t) ? 0 : t;
            } catch(_) { return 0; }
        }
        // No toast functions; badge-only UX
    })();
</script>
