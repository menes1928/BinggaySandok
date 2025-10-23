<?php
// Start session early so the navbar can read login state (avatar + username)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../config/app.php';
$db = new database();


function normalize_menu_pic($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || strcasecmp($raw, 'default.jpg') === 0) {
        // Use a stable placeholder for missing or default images
        return 'https://placehold.co/800x600?text=Menu+Photo';
    }

    // Normalize slashes
    $raw = str_replace('\\', '/', $raw);

    // Absolute URL
    if (preg_match('~^https?://~i', $raw)) {
        return $raw;
    }

    // If path contains the project root once, cut to web path
    if (preg_match('~(?:^|/)(?:Binggay|BinggaySandok)/(.+)$~i', $raw, $m)) {
        return app_base_prefix() . '/' . ltrim($m[1], '/');
    }

    // Windows absolute path without Binggay segment: use basename under images/menu
    if (preg_match('~^[A-Za-z]:/|^/~', $raw)) {
        $base = basename($raw);
        if ($base === '' || strcasecmp($base, 'default.jpg') === 0) {
            return 'https://placehold.co/800x600?text=Menu+Photo';
        }
        return app_base_prefix() . '/menu/' . $base;
    }

    // If already starts with a known web-rooted segment
    if (preg_match('~^(images|uploads|FINALS)(/|$)~i', $raw)) {
        return app_base_prefix() . '/' . ltrim($raw, '/');
    }

    // Bare filename -> assume images/menu/
    if (strpos($raw, '/') === false) {
        return app_base_prefix() . '/menu/' . $raw;
    }

    // Fallback: ensure it is under site root
    return app_base_prefix() . '/' . ltrim($raw, '/');
}

// Categories from DB with an 'All' pseudo-category
$dbCategories = $db->viewCategories();
$categories = [ [ 'category_id' => 0, 'category_name' => 'All' ] ];
foreach ($dbCategories as $row) {
    $categories[] = [
        'category_id' => (int)$row['category_id'],
        'category_name' => (string)$row['category_name'],
    ];
}

// Selected category via query string (?cat=ID), 0 means All
$selectedCategoryId = isset($_GET['cat']) ? max(0, (int)$_GET['cat']) : 0;
$selectedCategoryName = 'All';
foreach ($categories as $c) { if ((int)$c['category_id'] === $selectedCategoryId) { $selectedCategoryName = $c['category_name']; break; } }

// Fetch menus (include both available and unavailable). If All, pass null to avoid join
$menuRows = $db->getFilteredMenuOOP($selectedCategoryId ?: null, null, 'alpha_asc');

// Map to UI-friendly array expected by the JS/template
$menuItems = array_map(function($m){
    $pic = isset($m['menu_pic']) ? $m['menu_pic'] : '';
    $avail = isset($m['menu_avail']) ? (int)$m['menu_avail'] : 1;
    return [
        'id' => (int)$m['menu_id'],
        'name' => (string)$m['menu_name'],
        'description' => (string)$m['menu_desc'],
        'price' => (float)$m['menu_price'],
        'image' => normalize_menu_pic($pic),
        'category' => '',
        'servings' => isset($m['menu_pax']) ? (string)$m['menu_pax'] : '',
        'prepTime' => '',
        'popular' => false,
        'rating' => 0,
        'reviews' => 0,
        'available' => ($avail === 1),
    ];
}, $menuRows);

// If requested as AJAX for menu items, return JSON and exit early
if (isset($_GET['ajax']) && $_GET['ajax'] === 'menu') {
    header('Content-Type: application/json');
    echo json_encode($menuItems);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sandok ni Binggay - Menu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --font-size: 16px;
            --background: #fefffe;
            --foreground: #1a2e1a;
            --card: #ffffff;
            --primary: #1B4332;
            --primary-foreground: #ffffff;
            --muted: #f8f8f6;
            --muted-foreground: #6b7062;
            --accent: #D4AF37;
            --accent-foreground: #1a2e1a;
            --border: rgba(27, 67, 50, 0.1);
            --radius: 0.625rem;
        }

        body {
            background: linear-gradient(to bottom, var(--background), #f8f8f6);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .text-primary { color: var(--primary); }
        .text-muted-foreground { color: var(--muted-foreground); }
        .bg-primary { background-color: var(--primary); }
        .bg-accent { background-color: var(--accent); }
        .border-primary { border-color: var(--primary); }
        
        /* Animations */
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

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
            }
            to {
                transform: translateX(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        .menu-card {
            transition: all 0.3s ease;
        }

        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .menu-card:hover .menu-image {
            transform: scale(1.1);
        }

        .menu-card:hover .menu-overlay {
            opacity: 1;
        }

        .menu-card:hover .add-btn {
            opacity: 1;
            transform: translateY(0);
        }

        .menu-image {
            transition: transform 0.4s ease;
        }

        .menu-overlay {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .add-btn {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease-out;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            animation: scaleIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        /* Hide amount row by default; JS will show it when needed */
        #co_amount_row { display: none; }

        /* Cart Sidebar */
        .cart-sidebar {
            position: fixed;
            top: 0;
            right: -100%;
            width: 100%;
            max-width: 28rem;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 6px -1px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            z-index: 1001;
        }

        .cart-sidebar.active {
            right: 0;
        }

        .cart-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .cart-backdrop.active {
            display: block;
        }

        /* Badge pulse animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Scrollbar hide */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Stagger animation for cards */
        .menu-card {
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .menu-card:nth-child(1) { animation-delay: 0.05s; }
        .menu-card:nth-child(2) { animation-delay: 0.1s; }
        .menu-card:nth-child(3) { animation-delay: 0.15s; }
        .menu-card:nth-child(4) { animation-delay: 0.2s; }
        .menu-card:nth-child(5) { animation-delay: 0.25s; }
        .menu-card:nth-child(6) { animation-delay: 0.3s; }
        .menu-card:nth-child(7) { animation-delay: 0.35s; }
        .menu-card:nth-child(8) { animation-delay: 0.4s; }
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
                        'border': 'rgba(27, 67, 50, 0.1)'
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen">
    <?php include __DIR__ . '/../partials/navbar-user.php'; ?>

    <!-- Hero Banner -->
    <div class="bg-gradient-to-r from-primary to-green-800 text-white pt-32 pb-12 md:pt-36 animate-fade-in" data-nav-contrast="dark">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-medium text-white mb-3">Delicious Home-Cooked Catering</h2>
            <p class="text-white/90 max-w-2xl mx-auto mb-6">
                Experience authentic Filipino cuisine and international favorites, perfectly prepared for your special occasions
            </p>
            <div class="flex items-center justify-center gap-6 flex-wrap text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-envelope"></i>
                    <span>riatriumfo06@gmail.com</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-phone"></i>
                    <span>0919-230-8344</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar on green background -->
    <div id="searchSection" class="bg-gradient-to-r from-primary to-green-800 border-b border-green-900/10" data-nav-contrast="dark">
        <div class="container mx-auto px-4 py-4">
            <div class="relative max-w-2xl mx-auto">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text"
                       id="searchInput"
                       placeholder="Search menu items..."
                       class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none">
            </div>
        </div>
    </div>

    <!-- Category Filter -->
    <div class="sticky top-20 z-40 bg-white/95 backdrop-blur-md border-b border-gray-200 shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                <i class="fas fa-filter text-gray-400 flex-shrink-0"></i>
                <?php foreach($categories as $cat): ?>
                    <?php $isActive = ((int)$cat['category_id'] === $selectedCategoryId); ?>
                    <a href="<?php echo (int)$cat['category_id'] === 0 ? 'menu.php' : ('menu.php?cat='.(int)$cat['category_id']); ?>"
                       class="category-chip px-4 py-2 rounded-full whitespace-nowrap transition-all hover:scale-105 <?php echo $isActive ? 'bg-primary text-white shadow-md' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'; ?>"
                       data-cat-id="<?php echo (int)$cat['category_id']; ?>">
                       <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Green spacer above first content section -->
    <div class="bg-gradient-to-r from-primary to-green-800 h-4 w-full" data-nav-contrast="dark"></div>

    <!-- Menu Grid -->
    <div class="container mx-auto px-4 py-8">
        <!-- Items -->
        <div class="animate-fade-in-up">
            <h3 id="sectionTitle" class="text-2xl font-medium text-primary mb-6"><?php echo htmlspecialchars($selectedCategoryName === 'All' ? 'All Menu Items' : $selectedCategoryName); ?></h3>
            <div id="menuGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" data-reveal-group>
                <?php foreach($menuItems as $item): ?>
                    <?php menu_card_template(); ?>
                <?php endforeach; ?>
            </div>
            <!-- Pagination -->
            <div id="pagination" class="mt-8 flex items-center justify-center gap-2 flex-wrap"></div>
        </div>

        <div id="noResults" class="hidden text-center py-16">
            <p class="text-muted-foreground">No items found matching your search.</p>
        </div>
    </div>

    <!-- Item Detail Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content bg-white rounded-lg max-w-3xl w-full mx-4">
            <div id="modalContent"></div>
        </div>
    </div>

    <!-- Cart Sidebar -->
    <div class="cart-backdrop" id="cartBackdrop" onclick="toggleCart()"></div>
    <div class="cart-sidebar" id="cartSidebar">
        <div class="flex flex-col h-full">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h2 class="text-2xl font-medium text-primary">Your Cart</h2>
                <button onclick="toggleCart()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="cartItems" class="flex-1 overflow-y-auto p-6">
                <div id="emptyCart" class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                        <p class="text-muted-foreground">Your cart is empty</p>
                    </div>
                </div>
                <div id="cartList" class="hidden space-y-4"></div>
            </div>

            <div id="cartFooter" class="hidden border-t border-gray-200 p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <span class="font-medium">Total:</span>
                    <span id="cartTotal" class="text-2xl font-bold text-primary">₱0.00</span>
                </div>
                <button id="checkoutBtn" class="w-full bg-primary hover:bg-green-800 text-white py-3 rounded-lg transition-colors font-medium">
                    Proceed to Checkout
                </button>
            </div>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content bg-white rounded-lg max-w-2xl w-full mx-4">
            <form id="checkoutForm">
                <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-primary">Checkout</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-900" onclick="toggleCheckout(false)"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-6 grid grid-cols-1 gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Full Name</label>
                            <input type="text" id="co_name" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Juan Dela Cruz" required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Email</label>
                            <input type="email" id="co_email" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="you@example.com" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Phone</label>
                            <input type="tel" id="co_phone" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="09XXXXXXXXX " maxlength="11" required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Date Needed</label>
                            <input type="date" id="co_needed" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" required>
                            <p class="text-xs text-gray-500 mt-1">Must be at least 1 day in advance.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Street</label>
                            <input type="text" id="co_street" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="123 Mabini St." required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">City</label>
                            <input type="text" id="co_city" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Quezon City" required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Province</label>
                            <input type="text" id="co_province" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Metro Manila" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Payment Method</label>
                            <select id="co_method" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" required>
                                <option value="Gcash">Gcash</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Paypal">Paypal</option>
                                <option value="Paymaya">Paymaya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Notes</label>
                            <input type="text" id="co_notes" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Optional">
                        </div>
                    </div>
                    <div id="co_amount_row" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Amount</label>
                            <input type="number" id="co_amount" step="0.01" min="0" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Enter exact total" />
                            <p id="co_amount_help" class="text-xs text-gray-500 mt-1"></p>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-600">Total: <span id="co_total" class="font-semibold text-primary">₱0.00</span></div>
                    <div class="flex items-center gap-3">
                        <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50" onclick="toggleCheckout(false)">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-lg bg-primary text-white hover:bg-green-800">Place Order</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Order Summary (Double Confirmation) Modal -->
    <div id="summaryModal" class="modal">
        <div class="modal-content bg-white rounded-lg max-w-3xl w-full mx-4">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-xl font-semibold text-primary">Order Summary</h3>
                <button type="button" class="text-gray-500 hover:text-gray-900" onclick="toggleSummary(false)"><i class="fas fa-times"></i></button>
            </div>
            <div id="summaryBody" class="p-6 space-y-5"></div>
            <div class="p-6 border-t border-gray-200 flex items-center justify-between">
                <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50" onclick="backToCheckout()">Back</button>
                <button id="summaryConfirmBtn" type="button" class="px-5 py-2 rounded-lg bg-primary text-white hover:bg-green-800" onclick="confirmOrder()">Confirm & Place Order</button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <script>
        // Menu items data as JavaScript
        let menuItems = <?php echo json_encode($menuItems); ?>;
        let cart = [];
        let selectedCategoryId = <?php echo (int)$selectedCategoryId; ?>;
        let selectedCategory = '<?php echo addslashes($selectedCategoryName); ?>';
        let currentPage = 1;
        const pageSize = 20;
        // Auth state from PHP session
        const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        const currentUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;
        const CART_KEY = currentUserId ? `binggay_cart_v1_u${currentUserId}` : 'binggay_cart_v1_guest';
    // pending payload holder for double confirmation
    let pendingOrderPayload = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Load persisted cart from localStorage using per-user key; guests have no cart
            try {
                // Remove legacy/global keys to avoid mixing carts
                try { localStorage.removeItem('binggay_cart_v1'); } catch (_) {}
                try { localStorage.removeItem('binggay_cart_v1_guest'); } catch (_) {}

                if (isLoggedIn) {
                    const raw = localStorage.getItem(CART_KEY);
                    if (raw) {
                        const parsed = JSON.parse(raw);
                        if (Array.isArray(parsed)) {
                            cart = parsed.filter(it => it && typeof it.id === 'number' && typeof it.quantity === 'number' && it.quantity > 0)
                                         .map(it => ({
                                             id: it.id,
                                             quantity: Math.max(1, Math.floor(it.quantity)),
                                             price: typeof it.price === 'number' ? it.price : 0,
                                             name: typeof it.name === 'string' ? it.name : '',
                                             image: typeof it.image === 'string' ? it.image : '',
                                             servings: typeof it.servings === 'string' ? it.servings : '',
                                             prepTime: typeof it.prepTime === 'string' ? it.prepTime : '',
                                             available: !!it.available
                                         }));
                        }
                    }
                } else {
                    cart = [];
                }
            } catch (e) { /* ignore parse errors */ }

            renderMenu();
            setupSearch();
            setupCategoryChips();
            updateCart(); // reflect loaded cart
            // Open cart if URL hash explicitly requests it (menu.php#cart)
            try {
                if (window.location && window.location.hash === '#cart' && typeof toggleCart === 'function') {
                    toggleCart();
                }
            } catch (_) {}
            // Or open only if explicitly requested via one-time flag
            try {
                const openOnce = sessionStorage.getItem('open_cart_once');
                if (openOnce === '1' && typeof toggleCart === 'function') {
                    toggleCart();
                    sessionStorage.removeItem('open_cart_once');
                }
            } catch (_) {}

            // Cross-tab sync
            window.addEventListener('storage', (ev) => {
                if (ev.key === CART_KEY) {
                    try {
                        const parsed = ev.newValue ? JSON.parse(ev.newValue) : [];
                        if (Array.isArray(parsed)) {
                            cart = parsed;
                            updateCart();
                        }
                    } catch (_) {}
                }
            });
        });

        // Filter by category
        // Category is now server-driven via links; no JS switching required

        // Render menu items
        function renderMenu() {
            const searchQuery = document.getElementById('searchInput').value.toLowerCase();
            const filteredItems = menuItems.filter(item => item.name.toLowerCase().includes(searchQuery) || item.description.toLowerCase().includes(searchQuery));

            const menuGrid = document.getElementById('menuGrid');
            const noResults = document.getElementById('noResults');
            const pagination = document.getElementById('pagination');

            if(filteredItems.length === 0) {
                menuGrid.innerHTML = '';
                noResults.classList.remove('hidden');
                if (pagination) pagination.innerHTML = '';
            } else {
                noResults.classList.add('hidden');
                // Pagination math
                const totalPages = Math.max(1, Math.ceil(filteredItems.length / pageSize));
                if (currentPage > totalPages) currentPage = totalPages;
                const start = (currentPage - 1) * pageSize;
                const pageItems = filteredItems.slice(start, start + pageSize);
                // Render current page
                menuGrid.innerHTML = pageItems.map(item => createMenuCard(item)).join('');
                if (typeof window.SNBRevealRefresh === 'function') window.SNBRevealRefresh();
                // Render pagination controls
                renderPagination(totalPages);
            }
        }

        function renderPagination(totalPages) {
            const el = document.getElementById('pagination');
            if (!el) return;
            if (totalPages <= 1) {
                el.innerHTML = '';
                return;
            }

            // Helper to build page button
            const btn = (label, page, disabled = false, active = false) => {
                const base = 'px-3 py-2 rounded-md text-sm border transition-all';
                const styles = active
                    ? ' bg-primary text-white border-primary shadow-sm'
                    : (disabled ? ' text-gray-400 border-gray-200 cursor-not-allowed'
                                : ' bg-white text-gray-700 hover:bg-gray-50 border-gray-200');
                const data = disabled ? '' : ` data-page="${page}"`;
                const aria = active ? ' aria-current="page"' : '';
                return `<a href="#" class="${base}${styles ? ' ' + styles : ''}"${data}${aria}>${label}</a>`;
            };

            // Determine range of pages to show (use window around current)
            const pages = [];
            const maxButtons = 7; // including first/last when collapsed
            let start = 1;
            let end = totalPages;
            if (totalPages > maxButtons) {
                start = Math.max(1, currentPage - 2);
                end = Math.min(totalPages, currentPage + 2);
                if (start <= 2) { start = 1; end = Math.min(totalPages, start + 4); }
                if (end >= totalPages - 1) { end = totalPages; start = Math.max(1, end - 4); }
            }

            // Build HTML
            let html = '';
            // Prev
            html += btn('Prev', currentPage - 1, currentPage === 1, false);
            // First page and ellipsis
            if (start > 1) {
                html += btn('1', 1, false, currentPage === 1);
                if (start > 2) html += `<span class="px-2 text-gray-400">…</span>`;
            }
            // Middle pages
            for (let p = start; p <= end; p++) {
                html += btn(String(p), p, false, p === currentPage);
            }
            // Last page and ellipsis
            if (end < totalPages) {
                if (end < totalPages - 1) html += `<span class="px-2 text-gray-400">…</span>`;
                html += btn(String(totalPages), totalPages, false, currentPage === totalPages);
            }
            // Next
            html += btn('Next', currentPage + 1, currentPage === totalPages, false);

            el.innerHTML = html;

            // Wire up clicks
            el.querySelectorAll('a[data-page]').forEach(a => {
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    const target = parseInt(a.getAttribute('data-page') || '1', 10);
                    if (!Number.isNaN(target) && target >= 1 && target <= totalPages && target !== currentPage) {
                        currentPage = target;
                        renderMenu();
                        // Scroll to the search bar so it sits at the top
                        scrollToSearch();
                        if (typeof window.SNBRevealRefresh === 'function') window.SNBRevealRefresh();
                    }
                });
            });
        }

        // Smoothly scroll to the search bar with an offset for the navbar
        function scrollToSearch() {
            const target = document.getElementById('searchSection') || document.getElementById('searchInput');
            if (!target) return;
            const navEl = document.querySelector('nav, header[role="banner"], header');
            const navH = navEl ? Math.ceil(navEl.getBoundingClientRect().height) : 80;
            const extra = 8; // small breathing space
            const top = target.getBoundingClientRect().top + window.pageYOffset - navH - extra;
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }

        // Fetch menus by category (AJAX)
        async function fetchMenus(catId) {
            try {
                const resp = await fetch(`menu.php?ajax=menu&cat=${encodeURIComponent(catId)}`, { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) throw new Error('Failed to fetch');
                const data = await resp.json();
                menuItems = Array.isArray(data) ? data : [];
                currentPage = 1;
                renderMenu();
            } catch (e) {
                console.error('Fetch menus error:', e);
            }
        }

        // Initialize category chips to filter without page reload
        function setupCategoryChips() {
            const chips = document.querySelectorAll('.category-chip');
            chips.forEach(chip => {
                chip.addEventListener('click', (e) => {
                    e.preventDefault();
                    const catId = parseInt(chip.dataset.catId || '0', 10) || 0;
                    selectedCategoryId = catId;
                    selectedCategory = chip.textContent.trim() || 'All';
                    // Update active styles
                    chips.forEach(c => {
                        const active = (parseInt(c.dataset.catId || '0',10) === selectedCategoryId);
                        c.className = `category-chip px-4 py-2 rounded-full whitespace-nowrap transition-all hover:scale-105 ${active ? 'bg-primary text-white shadow-md' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'}`;
                    });
                    // Update section title
                    const titleEl = document.getElementById('sectionTitle');
                    if (titleEl) titleEl.textContent = (selectedCategory.toLowerCase() === 'all') ? 'All Menu Items' : selectedCategory;
                    // Clear search on category change for clarity
                    const s = document.getElementById('searchInput');
                    if (s) { s.value = ''; }
                    currentPage = 1;
                    // Fetch and render
                    fetchMenus(selectedCategoryId);
                });
            });
        }

        // Create menu card HTML
        function createMenuCard(item) {
                return `
                    <div class="menu-card fade-reveal bg-white rounded-lg overflow-hidden border border-gray-200 cursor-pointer">
                    <div class="relative overflow-hidden h-48" onclick="openItemModal(${item.id})">
                        <img src="${item.image}" alt="${item.name}" onerror="this.onerror=null;this.src='https://placehold.co/800x600?text=Menu+Photo';" class="menu-image w-full h-full object-cover">
                        <div class="menu-overlay absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                        ${item.available ? '<span class="absolute top-3 left-3 bg-emerald-600 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg">Available</span>' : '<span class="absolute top-3 left-3 bg-red-600 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg">Unavailable</span>'}
                        <button ${item.available ? '' : 'disabled aria-disabled="true"'} onclick="event.stopPropagation(); ${item.available ? `addToCart(${item.id})` : ''}" class="add-btn absolute bottom-3 right-3 ${item.available ? 'bg-white text-primary hover:bg-primary hover:text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'} px-4 py-2 rounded-lg shadow-lg text-sm font-medium transition-colors">
                            <i class="fas fa-plus mr-1"></i>Add
                        </button>
                    </div>
                    <div class="p-4" onclick="openItemModal(${item.id})">
                        <h4 class="font-medium text-primary mb-2 hover:text-amber-500 transition-colors">${item.name}</h4>
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2">${item.description}</p>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-medium ${item.available ? 'text-emerald-700' : 'text-red-700'}">${item.available ? 'Available' : 'Unavailable'}</span>
                            <p class="text-xl font-bold text-primary">${peso(item.price)}</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <div class="flex items-center gap-1">
                                <i class="fas fa-users"></i>
                                <span>${item.servings}</span>
                            </div>
                            <span>•</span>
                            <div class="flex items-center gap-1">
                                <i class="fas fa-clock"></i>
                                <span>${item.prepTime}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Setup search
        function setupSearch() {
            document.getElementById('searchInput').addEventListener('input', () => {
                currentPage = 1;
                renderMenu();
            });
        }

        // Open item modal
        function openItemModal(itemId) {
            const item = menuItems.find(i => i.id === itemId);
            if(!item) return;

            const modalContent = `
                <div class="relative h-64 md:h-96">
                    <img src="${item.image}" alt="${item.name}" onerror="this.onerror=null;this.src='https://placehold.co/800x600?text=Menu+Photo';" class="w-full h-full object-cover">
                    ${item.available ? '<span class="absolute top-4 right-4 bg-emerald-600 text-white px-3 py-1 rounded-full text-sm font-medium">Available</span>' : '<span class="absolute top-4 right-4 bg-red-600 text-white px-3 py-1 rounded-full text-sm font-medium">Unavailable</span>'}
                </div>
                <div class="p-6">
                    <h2 class="text-2xl font-medium text-primary mb-4">${item.name}</h2>
                    <div class="flex items-center gap-4 text-sm text-gray-500 mb-6">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${item.available ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}">${item.available ? 'Available' : 'Unavailable'}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <i class="fas fa-users"></i>
                            <span>${item.servings}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <i class="fas fa-clock"></i>
                            <span>${item.prepTime}</span>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-6">${item.description}</p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <div>
                            <p class="text-sm text-gray-500">Price</p>
                            <p class="text-3xl font-bold text-primary">${peso(item.price)}</p>
                        </div>
                        <button ${item.available ? '' : 'disabled aria-disabled="true"'} onclick="${item.available ? `handleAddFromModal(${item.id})` : ''}" class="${item.available ? 'bg-primary hover:bg-green-800 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'} px-6 py-3 rounded-lg font-medium transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add to Cart
                        </button>
                    </div>
                </div>
                <button onclick="closeModal()" class="absolute top-4 right-4 bg-white text-gray-600 hover:text-gray-900 w-10 h-10 rounded-full flex items-center justify-center shadow-lg transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            `;

            document.getElementById('modalContent').innerHTML = modalContent;
            document.getElementById('itemModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close modal
        function closeModal() {
            document.getElementById('itemModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Toggle cart
        function toggleCart() {
            document.getElementById('cartSidebar').classList.toggle('active');
            document.getElementById('cartBackdrop').classList.toggle('active');
            document.body.style.overflow = document.getElementById('cartSidebar').classList.contains('active') ? 'hidden' : 'auto';
        }

        // Toggle checkout modal
        function toggleCheckout(open = true) {
            const modal = document.getElementById('checkoutModal');
            if (!modal) return;
            modal.classList.toggle('active', open);
            document.body.style.overflow = open ? 'hidden' : 'auto';
            if (open) {
                // Update total shown in modal
                const cartTotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                document.getElementById('co_total').textContent = peso(cartTotal);
                // Prepare amount field for non-cash online methods
                const methodEl = document.getElementById('co_method');
                const amtRow = document.getElementById('co_amount_row');
                const amtEl = document.getElementById('co_amount');
                const amtHelp = document.getElementById('co_amount_help');
                const m = methodEl ? (methodEl.value||'') : '';
                const requiresExact = (m === 'Paypal' || m === 'Gcash');
                if (amtRow) amtRow.style.display = requiresExact ? 'grid' : 'none';
                if (amtEl) {
                    amtEl.value = requiresExact ? String(cartTotal.toFixed(2)) : '';
                    amtEl.required = !!requiresExact;
                }
                if (amtHelp) amtHelp.textContent = requiresExact ? `Enter the exact total: ₱${cartTotal.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}` : '';
            }
        }

        // Toggle summary modal
        function toggleSummary(open = true) {
            const modal = document.getElementById('summaryModal');
            if (!modal) return;
            modal.classList.toggle('active', open);
            document.body.style.overflow = open ? 'hidden' : 'auto';
        }

        // Helper formatter
        function peso(n){
            return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Build order payload from current form fields and cart
        function buildOrderPayloadFromForm() {
            const payload = {
                items: cart.map(i => ({ id: i.id, quantity: i.quantity, price: i.price, name: i.name })),
                order_needed: document.getElementById('co_needed').value,
                address: {
                    street: document.getElementById('co_street').value,
                    city: document.getElementById('co_city').value,
                    province: document.getElementById('co_province').value,
                },
                pay_method: document.getElementById('co_method').value,
                customer: {
                    name: document.getElementById('co_name').value,
                    email: document.getElementById('co_email').value,
                    phone: document.getElementById('co_phone').value,
                    notes: document.getElementById('co_notes').value,
                }
            };
            const amtEl = document.getElementById('co_amount');
            if (amtEl && amtEl.value) { payload.pay_amount = Number(amtEl.value); }
            return payload;
        }

        // Render the summary modal body
        function renderSummary(payload){
            const sumBody = document.getElementById('summaryBody');
            if (!sumBody) return;
            const total = (payload.items || []).reduce((acc, it)=> acc + (Number(it.price)||0) * (Number(it.quantity)||0), 0);
            const addr = `${payload.address.street}, ${payload.address.city}, ${payload.address.province}`;
            const itemsHtml = (payload.items || []).map(it => `
                <tr>
                    <td class="py-2">${it.name}</td>
                    <td class="py-2 text-center">${it.quantity}</td>
                    <td class="py-2 text-right">${peso(it.price)}</td>
                    <td class="py-2 text-right font-medium">${peso((Number(it.price)||0) * (Number(it.quantity)||0))}</td>
                </tr>
            `).join('');
            sumBody.innerHTML = `
                <div>
                    <h4 class="text-lg font-semibold text-primary mb-3">Items</h4>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-2">Item</th>
                                    <th class="text-center px-4 py-2">Qty</th>
                                    <th class="text-right px-4 py-2">Price</th>
                                    <th class="text-right px-4 py-2">Line Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">${itemsHtml || '<tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No items</td></tr>'}</tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right font-medium">Total</td>
                                    <td class="px-4 py-3 text-right text-primary font-bold">${peso(total)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-lg font-semibold text-primary mb-3">Customer</h4>
                        <div class="text-sm text-gray-700 space-y-1">
                            <div><span class="text-gray-500">Name:</span> ${payload.customer.name}</div>
                            <div><span class="text-gray-500">Email:</span> ${payload.customer.email}</div>
                            <div><span class="text-gray-500">Phone:</span> ${payload.customer.phone}</div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-primary mb-3">Order Details</h4>
                        <div class="text-sm text-gray-700 space-y-1">
                            <div><span class="text-gray-500">Date Needed:</span> ${payload.order_needed}</div>
                            <div><span class="text-gray-500">Payment Method:</span> ${payload.pay_method}</div>
                            <div><span class="text-gray-500">Address:</span> ${addr}</div>
                            ${payload.customer.notes ? `<div><span class=\"text-gray-500\">Notes:</span> ${payload.customer.notes}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        // Go back from summary to checkout for edits
        function backToCheckout(){
            toggleSummary(false);
            toggleCheckout(true);
        }

        // Ensure user is logged in; otherwise redirect to login with notice and next
        function requireLoginRedirect() {
            const current = (window.location.pathname || '') + (window.location.search || '') + (window.location.hash || '');
            const base = (window.appBasePrefix || '');
            const target = base + '/login.php?msg=login_required&next=' + encodeURIComponent(current);
            window.location.href = target;
        }

        async function handleAddFromModal(itemId) {
            if (!isLoggedIn) {
                requireLoginRedirect();
                return;
            }
            await addToCart(itemId);
            closeModal();
            toggleCart();
        }

        // Add to cart
        async function addToCart(itemId) {
            if (!isLoggedIn) {
                requireLoginRedirect();
                return false;
            }
            const item = menuItems.find(i => i.id === itemId);
            if(!item) return false;
            const existing = cart.find(i => i.id === itemId);
            if (existing) {
                existing.quantity = Math.max(1, (existing.quantity||0) + 1);
            } else {
                cart.push({
                    id: item.id,
                    quantity: 1,
                    price: Number(item.price)||0,
                    name: String(item.name||''),
                    image: String(item.image||''),
                    servings: String(item.servings||''),
                    prepTime: String(item.prepTime||''),
                    available: !!item.available
                });
            }
            updateCart();
            try { if (typeof window.__binggay_cart_badge_refresh === 'function') window.__binggay_cart_badge_refresh(); } catch(_) {}
            return true;
        }

        // Update quantity
        async function updateQuantity(itemId, quantity) {
            if (quantity <= 0) {
                cart = cart.filter(item => item.id !== itemId);
            } else {
                const item = cart.find(i => i.id === itemId);
                if (item) item.quantity = Math.max(1, parseInt(quantity||1,10));
            }
            updateCart();
            try { if (typeof window.__binggay_cart_badge_refresh === 'function') window.__binggay_cart_badge_refresh(); } catch(_) {}
        }

        // Update cart display
        function updateCart() {
            const cartCount = cart.reduce((sum, item) => sum + item.quantity, 0);
            const cartTotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            // Update badge(s) across page and navbar if present
            const badges = [];
            const mainBadge = document.getElementById('cartBadge');
            if (mainBadge) badges.push(mainBadge);
            // Navbar desktop button badge reuses same id; ensure uniqueness by selecting duplicates
            document.querySelectorAll('#nav-cart-btn #cartBadge, #nav-cart-btn-mobile .cart-badge').forEach(el => badges.push(el));
            badges.forEach(badgeEl => {
                if (!badgeEl) return;
                if (cartCount > 0) {
                    badgeEl.textContent = cartCount;
                    badgeEl.classList.remove('hidden');
                    badgeEl.classList.add('flex');
                } else {
                    badgeEl.classList.add('hidden');
                    badgeEl.classList.remove('flex');
                }
            });

            // Update cart items
            if(cart.length === 0) {
                document.getElementById('emptyCart').classList.remove('hidden');
                document.getElementById('cartList').classList.add('hidden');
                document.getElementById('cartFooter').classList.add('hidden');
            } else {
                document.getElementById('emptyCart').classList.add('hidden');
                document.getElementById('cartList').classList.remove('hidden');
                document.getElementById('cartFooter').classList.remove('hidden');

                const cartList = document.getElementById('cartList');
                cartList.innerHTML = cart.map(item => `
                    <div class="flex gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <img src="${item.image}" alt="${item.name}" class="w-20 h-20 object-cover rounded-lg" onerror="this.onerror=null;this.src='https://placehold.co/160x160?text=Menu';">
                        <div class="flex-1">
                            <h4 class="font-medium text-sm mb-1">${item.name}</h4>
                            <p class="text-sm text-primary font-semibold">${peso(item.price)}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <button onclick="updateQuantity(${item.id}, ${item.quantity - 1})" class="w-7 h-7 border border-gray-300 rounded flex items-center justify-center hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-minus text-xs"></i>
                                </button>
                                <span class="text-sm font-medium w-8 text-center">${item.quantity}</span>
                                <button onclick="updateQuantity(${item.id}, ${item.quantity + 1})" class="w-7 h-7 border border-gray-300 rounded flex items-center justify-center hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');

                document.getElementById('cartTotal').textContent = peso(cartTotal);
            }

            // Persist to localStorage
            try {
                if (isLoggedIn) {
                    localStorage.setItem(CART_KEY, JSON.stringify(cart));
                }
            } catch (_) {}
            // Also inform navbar badge logic if present
            try { if (typeof window.__binggay_cart_badge_refresh === 'function') window.__binggay_cart_badge_refresh(); } catch(_) {}
        }

        // Close modal when clicking outside
        document.getElementById('itemModal').addEventListener('click', function(e) {
            if(e.target === this) closeModal();
        });

        // Helper to set min date (tomorrow) and validate date-needed
        function setupDateNeeded() {
            const input = document.getElementById('co_needed');
            if (!input) return;
            const tzOffsetMs = 8 * 60 * 60 * 1000; // approximate Asia/Manila offset for today (no DST)
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const tomorrow = new Date(today.getTime() + 24*60*60*1000);
            const yyyy = tomorrow.getFullYear();
            const mm = String(tomorrow.getMonth()+1).padStart(2,'0');
            const dd = String(tomorrow.getDate()).padStart(2,'0');
            input.min = `${yyyy}-${mm}-${dd}`;
            input.addEventListener('change', () => {
                const v = input.value;
                if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return;
                const chosen = new Date(v + 'T00:00:00');
                if (chosen.getTime() < tomorrow.getTime()) {
                    alert('Orders must be placed at least 1 day in advance.');
                    input.value = input.min;
                }
            });
        }

        // Open checkout on button click
        const checkoutBtn = document.getElementById('checkoutBtn');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', function(){
                if (cart.length === 0) return;
                toggleCheckout(true);
                setupDateNeeded();
                // Attach change handler to payment method to toggle amount field
                const methodEl = document.getElementById('co_method');
                if (methodEl && !methodEl.__wired) {
                    methodEl.addEventListener('change', function(){
                        const cartTotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                        const m = (this.value||'');
                        const amtRow = document.getElementById('co_amount_row');
                        const amtEl = document.getElementById('co_amount');
                        const amtHelp = document.getElementById('co_amount_help');
                        const requiresExact = (m === 'Paypal' || m === 'Gcash');
                        if (amtRow) amtRow.style.display = requiresExact ? 'grid' : 'none';
                        if (amtEl) {
                            amtEl.value = requiresExact ? String(cartTotal.toFixed(2)) : '';
                            amtEl.required = !!requiresExact;
                        }
                        if (amtHelp) amtHelp.textContent = requiresExact ? `Enter the exact total: ₱${cartTotal.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}` : '';
                    });
                    methodEl.__wired = true;
                }
            });
        }

        // Submit checkout (now opens summary for double confirmation)
        const checkoutForm = document.getElementById('checkoutForm');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', async function(e){
                e.preventDefault();
                // Client-side guard: ensure needed date >= tomorrow
                const neededVal = document.getElementById('co_needed').value;
                if (!/^\d{4}-\d{2}-\d{2}$/.test(neededVal)) {
                    alert('Please select a valid date.');
                    return;
                }
                const t0 = new Date();
                const tToday = new Date(t0.getFullYear(), t0.getMonth(), t0.getDate());
                const tTomorrow = new Date(tToday.getTime() + 24*60*60*1000);
                const tChosen = new Date(neededVal + 'T00:00:00');
                if (tChosen.getTime() < tTomorrow.getTime()) {
                    alert('Orders must be placed at least 1 day in advance.');
                    return;
                }
                // Validate exact amount for non-cash online methods
                const method = (document.getElementById('co_method')?.value||'');
                const requiresExact = (method === 'Paypal' || method === 'Gcash');
                if (requiresExact) {
                    const amtEl = document.getElementById('co_amount');
                    const cartTotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                    const entered = Number(amtEl && amtEl.value ? amtEl.value : '0');
                    const exact = Number(cartTotal.toFixed(2));
                    if (!Number.isFinite(entered) || Math.abs(entered - exact) > 0.009) {
                        alert(`Please enter the exact total amount: ₱${cartTotal.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}`);
                        return;
                    }
                }
                // Build payload and open summary modal for confirmation
                pendingOrderPayload = buildOrderPayloadFromForm();
                // Persist amount in payload (optional; backend may ignore if not needed)
                try { if (requiresExact) pendingOrderPayload.pay_amount = Number(document.getElementById('co_amount').value); } catch(_) {}
                renderSummary(pendingOrderPayload);
                toggleCheckout(false);
                toggleSummary(true);
            });
        }

        // Confirm and place order after summary
        async function confirmOrder(){
            if (!pendingOrderPayload) return;
            const btn = document.getElementById('summaryConfirmBtn');
            const orig = btn ? btn.textContent : '';
            try {
                if (btn) { btn.disabled = true; btn.textContent = 'Placing order...'; }
                const resp = await fetch('api_checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pendingOrderPayload)
                });
                const data = await resp.json();
                if (!resp.ok || !data.ok) throw new Error(data.error || 'Checkout failed');
                // success: clear cart, close modals, show alert
                cart = [];
                updateCart();
                try { if (isLoggedIn) { localStorage.removeItem(CART_KEY); } } catch (_) {}
                try { if (typeof window.__binggay_cart_badge_refresh === 'function') window.__binggay_cart_badge_refresh(); } catch(_) {}
                toggleSummary(false);
                toggleCheckout(false);
                try { if (document.getElementById('cartSidebar')?.classList.contains('active')) toggleCart(); } catch(_) {}
                alert('Order placed successfully! Order #' + data.order_id);
                pendingOrderPayload = null;
            } catch (err) {
                console.error(err);
                alert('Error: ' + (err?.message || 'Unknown error'));
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = orig || 'Confirm & Place Order'; }
            }
        }
    </script>
</body>
</html>

<?php
// Menu card template for PHP rendering
function menu_card_template() {
    global $item;
    ?>
    <div class="menu-card fade-reveal bg-white rounded-lg overflow-hidden border border-gray-200 cursor-pointer">
        <div class="relative overflow-hidden h-48" onclick="openItemModal(<?php echo $item['id']; ?>)">
            <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" onerror="this.onerror=null;this.src='https://placehold.co/800x600?text=Menu+Photo';" class="menu-image w-full h-full object-cover">
            <div class="menu-overlay absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
            <?php if(!empty($item['available'])): ?>
                <span class="absolute top-3 left-3 bg-emerald-600 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg">Available</span>
            <?php else: ?>
                <span class="absolute top-3 left-3 bg-red-600 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg">Unavailable</span>
            <?php endif; ?>
            <button onclick="event.stopPropagation(); <?php echo !empty($item['available']) ? 'addToCart('.$item['id'].')' : ''; ?>" <?php echo empty($item['available']) ? 'disabled aria-disabled="true"' : ''; ?> class="add-btn absolute bottom-3 right-3 <?php echo !empty($item['available']) ? 'bg-white text-primary hover:bg-primary hover:text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'; ?> px-4 py-2 rounded-lg shadow-lg text-sm font-medium transition-colors">
                <i class="fas fa-plus mr-1"></i>Add
            </button>
        </div>
        <div class="p-4" onclick="openItemModal(<?php echo $item['id']; ?>)">
            <h4 class="font-medium text-primary mb-2 hover:text-amber-500 transition-colors"><?php echo $item['name']; ?></h4>
            <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?php echo $item['description']; ?></p>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium <?php echo !empty($item['available']) ? 'text-emerald-700' : 'text-red-700'; ?>"><?php echo !empty($item['available']) ? 'Available' : 'Unavailable'; ?></span>
                <p class="text-xl font-bold text-primary">₱<?php echo number_format($item['price'], 2); ?></p>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <div class="flex items-center gap-1">
                    <i class="fas fa-users"></i>
                    <span><?php echo $item['servings']; ?></span>
                </div>
                <span>•</span>
                <div class="flex items-center gap-1">
                    <i class="fas fa-clock"></i>
                    <span><?php echo $item['prepTime']; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>