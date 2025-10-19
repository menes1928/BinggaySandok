<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../classes/database.php';
$db = new database();
$pdo = $db->opencon();

// Prefill identity fields for logged-in users
$CP_IS_LOGGED_IN = !empty($_SESSION['user_id']);
$CP_FULLNAME = '';
$CP_EMAIL = '';
$CP_PHONE = '';
if ($CP_IS_LOGGED_IN) {
    $fn = isset($_SESSION['user_fn']) ? trim((string)$_SESSION['user_fn']) : '';
    $ln = isset($_SESSION['user_ln']) ? trim((string)$_SESSION['user_ln']) : '';
    $CP_FULLNAME = trim((string)($_SESSION['user_name'] ?? (trim($fn . ' ' . $ln))));
    $CP_EMAIL = isset($_SESSION['user_email']) ? (string)$_SESSION['user_email'] : '';
    $CP_PHONE = isset($_SESSION['user_phone']) ? trim((string)$_SESSION['user_phone']) : '';
    if ($CP_PHONE === '') {
        try {
            $stmt = $pdo->prepare('SELECT user_phone FROM users WHERE user_id = ? LIMIT 1');
            $stmt->execute([ (int)($_SESSION['user_id'] ?? 0) ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['user_phone'])) {
                $CP_PHONE = trim((string)$row['user_phone']);
                $_SESSION['user_phone'] = $CP_PHONE;
            }
        } catch (Throwable $e) { /* ignore */ }
    }
    // Normalize to PH 11-digit local if possible
    if ($CP_PHONE !== '') {
        $d = preg_replace('/\D+/', '', $CP_PHONE);
        if (strlen($d) === 12 && substr($d, 0, 2) === '63') { $d = '0' . substr($d, 2); }
        if ($d !== '') { $CP_PHONE = $d; }
    }
}

function normalize_pkg_pic($raw, $fallback = '../images/logo.png') {
    $raw = trim((string)$raw);
    if ($raw === '') return $fallback;
    $raw = str_replace('\\', '/', $raw);
    if (preg_match('~^https?://~i', $raw)) return $raw;
    if (preg_match('~(?:^|/)Binggay/(.+)$~i', $raw, $m)) {
        $path = '/Binggay/' . ltrim($m[1], '/');
    } elseif (preg_match('~^(uploads|images|menu)(/|$)~i', $raw)) {
        $path = '/Binggay/' . ltrim($raw, '/');
    } elseif (strpos($raw, '/') === false) {
        // likely stored as filename; assume uploads/packages/
        $path = '/Binggay/uploads/packages/' . $raw;
    } else {
        $path = '/Binggay/' . ltrim($raw, '/');
    }
    // cache bust if file exists
    $abs = realpath(__DIR__ . '/../' . ltrim($path, '/'));
    if ($abs && file_exists($abs)) {
        $ts = @filemtime($abs);
        if ($ts) return $path . '?v=' . $ts;
    }
    return $path;
}

function badge_for_pax($paxInt) {
    if ($paxInt <= 60) return ['INTIMATE', 'fas fa-star'];
    if ($paxInt <= 110) return ['POPULAR', 'fas fa-fire'];
    if ($paxInt <= 160) return ['PREMIUM', 'fas fa-gem'];
    return ['DELUXE', 'fas fa-crown'];
}

$packages = [];
try {
    $stmt = $pdo->prepare("SELECT package_id, name, pax, base_price, is_active, package_image FROM packages ORDER BY CAST(pax AS UNSIGNED), name");
    $stmt->execute();
    $itemsStmt = $pdo->prepare("SELECT item_label, qty, unit, is_optional, item_pic FROM package_items WHERE package_id = ? ORDER BY sort_order, item_id");
    while ($row = $stmt->fetch()) {
        $pid = (int)$row['package_id'];
        $paxRaw = (string)$row['pax'];
        $paxNum = (int)preg_replace('/[^0-9]/', '', $paxRaw);
        $cover = normalize_pkg_pic($row['package_image'] ?? '');
        $itemsStmt->execute([$pid]);
        $rawItems = $itemsStmt->fetchAll();
        // de-duplicate by label+qty+unit+optional
        $seen = [];
        $items = [];
        foreach ($rawItems as $it) {
            $key = strtolower(trim(($it['item_label'] ?? '') . '|' . ($it['qty'] ?? '') . '|' . ($it['unit'] ?? '') . '|' . (int)($it['is_optional'] ?? 0)));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $items[] = [
                'label' => (string)$it['item_label'],
                'qty'   => $it['qty'] !== null && $it['qty'] !== '' ? (string)$it['qty'] : '',
                'unit'  => $it['unit'] !== null ? (string)$it['unit'] : '',
                'optional' => (int)($it['is_optional'] ?? 0) === 1,
            ];
        }
        $packages[] = [
            'id' => $pid,
            'name' => (string)$row['name'],
            'pax' => $paxNum > 0 ? $paxNum : (int)$row['pax'],
            'price' => (float)($row['base_price'] ?? 0),
            'active' => (int)($row['is_active'] ?? 1) === 1,
            'image' => $cover,
            'items' => $items,
        ];
    }
} catch (Throwable $e) {
    $packages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catering Packages - Sandok ni Binggay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a2f1f 0%, #1B4332 50%, #2d5a47 100%);
            min-height: 100vh;
            color: #fff;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
        }
        
        .gold-text {
            color: #D4AF37;
        }
        
        .bg-primary {
            background-color: #1B4332;
        }
        
        .bg-gold {
            background-color: #D4AF37;
        }
        
        .border-gold {
            border-color: #D4AF37;
        }
        
        .hover-gold:hover {
            color: #D4AF37;
        }
        
        /* Removed page-specific navbar styles in favor of shared partial */
        
        /* Hero Section */
        .hero-section {
            margin-top: 80px;
            padding: 80px 0;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                        url('https://images.unsplash.com/photo-1751651054945-882d49cbdbfc?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlbGVnYW50JTIwY2F0ZXJpbmclMjBidWZmZXR8ZW58MXx8fHwxNzYwMTk2OTkxfDA&ixlib=rb-4.1.0&q=80&w=1080');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
        }
        
        .hero-content {
            animation: fadeInUp 1s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Package Card Styles */
        .package-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .package-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.1), transparent);
            transition: all 0.6s ease;
        }
        
        .package-card:hover::before {
            left: 100%;
        }
        
        .package-card:hover {
            transform: translateY(-10px);
            border-color: #D4AF37;
            box-shadow: 0 20px 60px rgba(212, 175, 55, 0.3);
        }
        
        .package-image {
            position: relative;
            overflow: hidden;
            height: 300px;
        }
        
        .package-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.5s ease;
        }
        
        .package-card:hover .package-image img {
            transform: scale(1.1);
        }
        
        .package-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #D4AF37;
            color: #1B4332;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .package-content {
            padding: 30px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(212, 175, 55, 0.1);
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            padding-left: 10px;
            color: #D4AF37;
        }
        
        .feature-item i {
            margin-right: 12px;
            color: #D4AF37;
            min-width: 20px;
        }
        
        .price-tag {
            background: linear-gradient(135deg, #D4AF37 0%, #c9a32a 100%);
            color: #1B4332;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .price-tag::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }
        
        .btn-inquire {
            background: linear-gradient(135deg, #D4AF37 0%, #c9a32a 100%);
            color: #1B4332;
            padding: 15px 40px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-inquire::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-inquire:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-inquire:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4);
        }
        
        .btn-inquire span {
            position: relative;
            z-index: 1;
        }
        
        /* Info Section */
        .info-section {
            background: rgba(27, 67, 50, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin: 60px 0;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 15px;
            border: 1px solid rgba(212, 175, 55, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .info-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: #D4AF37;
            transform: translateY(-5px);
        }
        
        .info-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #D4AF37 0%, #c9a32a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
            color: #1B4332;
            transition: all 0.3s ease;
        }
        
        .info-card:hover .info-icon {
            transform: rotate(360deg);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1B4332 0%, #2d5a47 100%);
            margin: 5% auto;
            padding: 0;
            border: 2px solid #D4AF37;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #D4AF37 0%, #c9a32a 100%);
            color: #1B4332;
            padding: 30px;
            border-radius: 18px 18px 0 0;
            position: relative;
        }
        
        .close {
            color: #1B4332;
            float: right;
            font-size: 35px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .close:hover {
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 40px;
        }
        /* Improve dropdown readability against dark background */
        .modal select {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        .modal option {
            color: #1B4332;
            background-color: #fff;
        }
        /* Two-column includes cleanup */
        .features-grid .feature-item {
            border-bottom: none;
            padding: 6px 0;
        }
        /* Hide number input spinners to allow free typing */
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0;
            }
            
            .package-card {
                margin-bottom: 30px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
        
        /* Scroll Animations */
        .scroll-animate {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }
        
        .scroll-animate.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* Tablet tuning for package includes to avoid column overlap */
        @media (min-width: 768px) and (max-width: 1023.98px) {
            .features-grid .feature-item { padding: 4px 0; }
            .features-grid .feature-item i { margin-right: 8px; min-width: 16px; }
            .features-grid .feature-item span { line-height: 1.25; }
            .features-grid .feature-item:hover { padding-left: 6px; }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include __DIR__ . '/../partials/navbar-user.php'; ?>

    <!-- Hero Section -->
    <!-- Hero Section marked as dark backdrop for navbar contrast control -->
    <section class="hero-section" data-nav-contrast="dark">
        <div class="container mx-auto px-4 text-center hero-content">
            <h1 class="text-5xl md:text-7xl font-bold mb-6">Our Catering Packages</h1>
            <p class="text-xl md:text-2xl gold-text mb-8">Experience the taste of authentic home-cooked meals</p>
            <p class="text-lg max-w-3xl mx-auto opacity-90">
                Choose from our carefully crafted catering packages, each designed to make your celebration memorable 
                with delicious Filipino cuisine that tastes just like home.
            </p>
        </div>
    </section>

    <!-- Green spacer to maintain dark contrast just below hero edge -->
    <div class="bg-gradient-to-r from-primary to-green-800 h-4 w-full" data-nav-contrast="dark"></div>

    <!-- Information Section -->
    <section class="container mx-auto px-4 py-16">
        <div class="info-section">
            <h2 class="text-4xl font-bold text-center mb-12 gold-text">Why Choose Our Packages?</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="info-card scroll-animate active">
                    <div class="info-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center mb-3">Home-Cooked Quality</h3>
                    <p class="text-center opacity-90 text-sm">
                        Every dish is prepared with love and care, using authentic Filipino recipes passed down through generations.
                    </p>
                </div>
                
                <div class="info-card scroll-animate active">
                    <div class="info-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center mb-3">Fresh Ingredients</h3>
                    <p class="text-center opacity-90 text-sm">
                        We use only the freshest, locally-sourced ingredients to ensure the best taste and quality.
                    </p>
                </div>
                
                <div class="info-card scroll-animate active">
                    <div class="info-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center mb-3">Flexible Options</h3>
                    <p class="text-center opacity-90 text-sm">
                        All packages can be customized to suit your preferences and dietary requirements.
                    </p>
                </div>
                
                <div class="info-card scroll-animate active">
                    <div class="info-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center mb-3">Reliable Service</h3>
                    <p class="text-center opacity-90 text-sm">
                        On-time delivery and professional setup to make your event stress-free and enjoyable.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="container mx-auto px-4 py-16">
        <h2 class="text-4xl md:text-5xl font-bold text-center mb-16 gold-text">Available Packages</h2>
        
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8 items-stretch" data-reveal-group>
            <?php if (!empty($packages)): ?>
                <?php foreach ($packages as $pkg): ?>
                    <?php list($badgeLabel, $badgeIcon) = badge_for_pax((int)$pkg['pax']); ?>
                    <div class="package-card scroll-animate fade-reveal h-full flex flex-col">
                        <div class="package-image">
                            <img src="<?php echo htmlspecialchars($pkg['image']); ?>" alt="<?php echo htmlspecialchars($pkg['name']); ?>"
                                 onerror="this.onerror=null;this.src='../images/logo.png';">
                            <div class="package-badge">
                                <i class="<?php echo $badgeIcon; ?> mr-2"></i><?php echo $badgeLabel; ?>
                            </div>
                        </div>
                        <div class="package-content flex flex-col flex-1">
                            <h3 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($pkg['name']); ?></h3>
                            <p class="gold-text text-xl mb-4"><?php echo (int)$pkg['pax']; ?> Persons</p>
                            <p class="opacity-90 mb-6"><?php echo $pkg['active'] ? 'Carefully curated for your celebration.' : 'Inactive • Not available for booking currently.'; ?></p>

                            <h4 class="font-semibold mb-3 text-lg gold-text">Package Includes:</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-1 lg:gap-2 mb-4 features-grid md:text-sm lg:text-base md:leading-snug lg:leading-normal">
                                <?php if (!empty($pkg['items'])): ?>
                                    <?php foreach ($pkg['items'] as $it): ?>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span>
                                                <?php echo htmlspecialchars($it['label']); ?>
                                                <?php if (!empty($it['qty'])): ?>
                                                    — <?php echo htmlspecialchars($it['qty']); ?> <?php echo htmlspecialchars($it['unit']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($it['optional'])): ?>
                                                    <span class="ml-1 text-[10px] px-1 rounded bg-amber-50/10 border border-amber-300/30 text-amber-200">optional</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="feature-item col-span-2"><i class="fas fa-circle-info"></i><span>Items coming soon.</span></div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-auto">
                                <div class="price-tag">
                                    <p class="text-sm mb-1">Starting at</p>
                                    <p class="text-4xl font-bold">₱<?php echo number_format(max(0,(float)$pkg['price']), 2); ?></p>
                                    <?php if ((int)$pkg['pax'] > 0 && (float)$pkg['price'] > 0): ?>
                                        <p class="text-sm mt-1 opacity-80">₱<?php echo number_format(ceil($pkg['price'] / max(1,(int)$pkg['pax'])), 2); ?> per person</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($pkg['active']): ?>
                                    <button class="btn-inquire w-full mt-6"
                                            data-package-id="<?php echo (int)$pkg['id']; ?>"
                                            data-package-name="<?php echo htmlspecialchars($pkg['name']); ?>"
                                            data-package-pax="<?php echo (int)$pkg['pax']; ?>"
                                            data-package-price="<?php echo (float)$pkg['price']; ?>"
                                            onclick="openPackageModal(this)">
                                        <span><i class="fas fa-envelope mr-2"></i>Inquire Now</span>
                                    </button>
                                <?php else: ?>
                                    <button class="btn-inquire w-full mt-6" disabled style="opacity:.6;cursor:not-allowed">
                                        <span><i class="fas fa-ban mr-2"></i>Not Available</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center opacity-80">No packages found.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Additional Information -->
    <section class="container mx-auto px-4 py-16">
        <div class="info-section">
            <h2 class="text-4xl font-bold text-center mb-8 gold-text">Important Notes</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xl font-semibold mb-4 gold-text"><i class="fas fa-info-circle mr-2"></i>Booking Information</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-calendar-check gold-text mt-1 mr-3"></i>
                            <span>Book at least 2 weeks in advance for availability</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-money-bill-wave gold-text mt-1 mr-3"></i>
                            <span>50% downpayment required upon booking</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-clock gold-text mt-1 mr-3"></i>
                            <span>Remaining balance due 3 days before event</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-edit gold-text mt-1 mr-3"></i>
                            <span>Menu customization available upon request</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold mb-4 gold-text"><i class="fas fa-utensils mr-2"></i>Service Details</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-truck gold-text mt-1 mr-3"></i>
                            <span>Free delivery within 20km radius</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-concierge-bell gold-text mt-1 mr-3"></i>
                            <span>Professional staff for setup and service</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-recycle gold-text mt-1 mr-3"></i>
                            <span>Cleanup and waste disposal included</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-shield-alt gold-text mt-1 mr-3"></i>
                            <span>Food safety and hygiene certified</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <p class="text-lg mb-4">Have a custom request or need a quote for more guests?</p>
                <button class="btn-inquire" onclick="openPackageModal({dataset:{packageId:0, packageName:'Custom Package Request', packagePax:0, packagePrice:0}})">
                    <span><i class="fas fa-phone mr-2"></i>Contact Us</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Inquiry/Booking Modal (Multi-step) -->
    <div id="inquiryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2 class="text-3xl font-bold">Catering Booking</h2>
                <p class="text-sm mt-2 opacity-80">Complete the steps to reserve your package</p>
            </div>
            <div class="modal-body">
                <div id="stepPackage" class="mb-6">
                    <label class="block mb-2 font-semibold gold-text">Selected Package</label>
                    <p id="selectedPackage" class="text-xl font-bold"></p>
                </div>
                <!-- Step 1: Contact & Address -->
                <div id="step1" class="step">
                    <h3 class="text-2xl font-semibold mb-4">Your Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block mb-2 font-semibold">Full Name *</label>
                            <input id="cp_fullName" type="text" required
                                   class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all"
                                   placeholder="Juan Dela Cruz">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Phone *</label>
                            <input id="cp_phone" type="tel" required
                                   class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all"
                                   placeholder="09XX XXX XXXX" maxlength="11">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block mb-2 font-semibold">Event Date *</label>
                            <input id="cp_date" type="date" required
                                   class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Email *</label>
                            <input id="cp_email" type="email" required
                                   class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all"
                                   placeholder="you@email.com">
                        </div>
                    </div>
                    <h4 class="font-semibold mb-3 text-lg gold-text">Address</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 font-semibold">Street *</label>
                            <input id="cp_street" type="text" required class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="Street">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Barangay *</label>
                            <input id="cp_barangay" type="text" required class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="Barangay">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Municipality/City *</label>
                            <input id="cp_municipality" type="text" required class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="Municipality/City">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Province *</label>
                            <input id="cp_province" type="text" required class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="Province">
                        </div>
                    </div>
                    <button class="btn-inquire w-full mt-6" onclick="gotoStep(2)"><span>Next</span></button>
                </div>

                <!-- Step 2: Add-ons & Notes -->
                <div id="step2" class="step hidden">
                    <h3 class="text-2xl font-semibold mb-4">Add-ons</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block mb-2 font-semibold">Additional Pax</label>
                            <input id="cp_addon_pax" type="number" min="0" value="0"
                                   class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="0">
                            <p class="text-xs opacity-80 mt-1">+₱200 per added person</p>
                            <p id="cp_pax_limit" class="text-xs opacity-80 mt-1 hidden"></p>
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Chairs</label>
                            <input id="cp_chairs" type="number" min="0" value="0"
                                   class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="0">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Tables</label>
                            <input id="cp_tables" type="number" min="0" value="0"
                                   class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="0">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block mb-2 font-semibold">Notes</label>
                        <textarea id="cp_notes" rows="3" class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 focus:border-gold outline-none transition-all" placeholder="Any special requests..."></textarea>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button class="btn-inquire flex-1" onclick="gotoStep(1)"><span>Back</span></button>
                        <button class="btn-inquire flex-1" onclick="gotoStep(3)"><span>Next</span></button>
                    </div>
                </div>

                <!-- Step 3: Terms -->
                <div id="step3" class="step hidden">
                    <h3 class="text-2xl font-semibold mb-4">Terms and Agreement</h3>
                    <div class="bg-white/5 border border-gold/20 rounded p-4 text-sm leading-6">
                        By proceeding, you agree to our catering policies, including lead times, cancellation terms, and venue access requirements. A 50% downpayment is required to confirm your booking.
                    </div>
                    <label class="mt-4 flex items-center gap-2">
                        <input id="cp_terms" type="checkbox" class="w-4 h-4">
                        <span>I have read and agree to the terms and agreement.</span>
                    </label>
                    <div class="flex gap-3 mt-6">
                        <button class="btn-inquire flex-1" onclick="gotoStep(2)"><span>Back</span></button>
                        <button id="btnToSummary" class="btn-inquire flex-1 opacity-60 cursor-not-allowed" disabled onclick="gotoStep(4)"><span>Next</span></button>
                    </div>
                </div>

                <!-- Step 4: Summary -->
                <div id="step4" class="step hidden">
                    <h3 class="text-2xl font-semibold mb-4">Summary</h3>
                    <div class="bg-white/5 border border-gold/20 rounded p-4 text-sm">
                        <div class="flex justify-between py-1"><span>Package</span><span id="sum_pkg_name"></span></div>
                        <div class="flex justify-between py-1"><span>Base Price</span><span id="sum_base_price"></span></div>
                        <div class="flex justify-between py-1"><span>Additional Pax</span><span id="sum_addon"></span></div>
                        <div class="border-t border-gold/20 my-2"></div>
                        <div class="flex justify-between py-1 font-semibold"><span>Total</span><span id="sum_total"></span></div>
                        <div class="flex justify-between py-1 font-semibold text-emerald-300"><span>50% Downpayment</span><span id="sum_deposit"></span></div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button class="btn-inquire flex-1" onclick="gotoStep(3)"><span>Back</span></button>
                        <button class="btn-inquire flex-1" onclick="gotoStep(5)"><span>Book Now</span></button>
                    </div>
                </div>

                <!-- Step 5: Payment -->
                <div id="step5" class="step hidden">
                    <h3 class="text-2xl font-semibold mb-4">Payment</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 font-semibold">Amount to Pay (50%)</label>
                            <input id="cp_pay_amount" type="text" readonly class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 outline-none">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Payment Type</label>
                            <select id="cp_pay_type" class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 outline-none">
                                <option value="Card">Card</option>
                                <option value="Gcash">Gcash</option>
                                <option value="Paymaya">Paymaya</option>
                                <option value="Paypal">Paypal</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block mb-2 font-semibold" id="cp_number_label">Card/Account Number</label>
                            <input id="cp_number" type="text" class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 outline-none" placeholder="Enter number">
                        </div>
                        <div>
                            <label class="block mb-2 font-semibold">Enter Exact Amount</label>
                            <input id="cp_amount_input" type="number" step="0.01" inputmode="decimal" class="w-full px-4 py-3 rounded-lg bg-white/10 border border-gold/30 outline-none" placeholder="0.00">
                            <p id="cp_amount_hint" class="text-xs opacity-80 mt-1"></p>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button class="btn-inquire flex-1" onclick="gotoStep(4)"><span>Back</span></button>
                        <button class="btn-inquire flex-1" onclick="submitBooking(event)"><span>Pay & Confirm</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <script>
        // Expose minimal user info for modal prefill
        window.CP_USER = {
            loggedIn: <?php echo $CP_IS_LOGGED_IN ? 'true' : 'false'; ?>,
            name: <?php echo json_encode((string)$CP_FULLNAME); ?>,
            email: <?php echo json_encode((string)$CP_EMAIL); ?>,
            phone: <?php echo json_encode((string)$CP_PHONE); ?>
        };
        // Removed page-specific navbar JS; shared partial controls nav behavior

        // Scroll Animation
        const scrollElements = document.querySelectorAll('.scroll-animate');
        
        const elementInView = (el, offset = 100) => {
            const elementTop = el.getBoundingClientRect().top;
            return (
                elementTop <= 
                (window.innerHeight || document.documentElement.clientHeight) - offset
            );
        };

        const displayScrollElement = (element) => {
            element.classList.add('active');
        };

        const handleScrollAnimation = () => {
            scrollElements.forEach((el) => {
                if (elementInView(el, 100)) {
                    displayScrollElement(el);
                }
            });
        };

        window.addEventListener('scroll', handleScrollAnimation);
        handleScrollAnimation(); // Initial check

        // Booking flow state
        const bookingState = {
            pkg: { id: null, name: '', pax: 0, basePrice: 0 },
            details: { fullName: '', phone: '', email: '', date: '', street: '', barangay: '', municipality: '', province: '' },
            addons: { addonPax: 0, chairs: 0, tables: 0, notes: '' },
            termsAccepted: false,
            amounts: { total: 0, deposit: 0 }
        };

    function peso(n){ return `₱${Number(n).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}`; }

        // Venue capacity and pax limit logic
        const CP_VENUE_CAPACITY = 250;
        function computeAddonPaxMax(pkgPax){
            const p = Number(pkgPax)||0;
            if (p <= 0) return null; // unknown until package is selected
            if (p === 50) return 50; // special case
            return Math.max(0, CP_VENUE_CAPACITY - p);
        }
        function updatePaxLimitUI(){
            const max = computeAddonPaxMax(bookingState.pkg.pax);
            const limitEl = document.getElementById('cp_pax_limit');
            const inputEl = document.getElementById('cp_addon_pax');
            if (!limitEl || !inputEl) return;
            if (max == null){
                limitEl.classList.add('hidden');
                inputEl.removeAttribute('max');
                return;
            }
            limitEl.textContent = `You can add up to ${max} additional pax for this venue/package.`;
            limitEl.classList.remove('hidden');
            inputEl.setAttribute('max', String(max));
            // Clamp current value
            const cur = Number(inputEl.value||0);
            if (cur > max) { inputEl.value = String(max); }
            if (cur < 0) { inputEl.value = '0'; }
        }

        function openPackageModal(btn){
            // Require login before booking (prefer CP_USER, fallback to SNB_USER)
            const isLoggedIn = (window.CP_USER && window.CP_USER.loggedIn) || (window.SNB_USER && window.SNB_USER.loggedIn);
            if (!isLoggedIn) {
                window.location.href = '../login?next=' + encodeURIComponent('user/cateringpackages');
                return;
            }
            const modal = document.getElementById('inquiryModal');
            bookingState.pkg.id = parseInt(btn.dataset.packageId);
            bookingState.pkg.name = btn.dataset.packageName;
            bookingState.pkg.pax = parseInt(btn.dataset.packagePax);
            bookingState.pkg.basePrice = parseFloat(btn.dataset.packagePrice || '0');
            document.getElementById('selectedPackage').textContent = `${bookingState.pkg.name} - ${bookingState.pkg.pax} Persons`;
            // reset steps
            gotoStep(1, true);
            // Set event date min = today + 14 days
            const d = new Date();
            d.setDate(d.getDate() + 14);
            const y = d.getFullYear();
            const m = String(d.getMonth()+1).padStart(2,'0');
            const day = String(d.getDate()).padStart(2,'0');
            const minStr = `${y}-${m}-${day}`;
            const dateEl = document.getElementById('cp_date');
            if (dateEl) {
                dateEl.min = minStr;
                if (!dateEl.value || dateEl.value < minStr) dateEl.value = minStr;
                // attach availability check on change
                const setValidity = (msg)=>{ dateEl.setCustomValidity(msg||''); dateEl.reportValidity(); };
                dateEl.addEventListener('change', async ()=>{
                    const v = dateEl.value;
                    setValidity('');
                    if (!v) return;
                    try {
                        const r = await fetch('api_check_availability.php?date='+encodeURIComponent(v), { headers:{'X-Requested-With':'XMLHttpRequest'} });
                        const j = await r.json();
                        if (!j.ok) { setValidity('Unable to verify availability. Please try again.'); return; }
                        if (!j.available) { setValidity('That date is already booked. Please choose another.'); }
                    } catch(_) { setValidity('Unable to verify availability.'); }
                }, { once: true });
            }
            // Prefill and lock user identity fields when logged in
            try {
                const nameEl = document.getElementById('cp_fullName');
                const emailEl = document.getElementById('cp_email');
                const phoneEl = document.getElementById('cp_phone');
                if (window.CP_USER && window.CP_USER.loggedIn) {
                    if (nameEl) { nameEl.value = (window.CP_USER.name||''); nameEl.readOnly = true; }
                    if (emailEl) { emailEl.value = (window.CP_USER.email||''); emailEl.readOnly = true; }
                    if (phoneEl) { phoneEl.value = (window.CP_USER.phone||''); phoneEl.readOnly = true; }
                } else {
                    if (nameEl) nameEl.readOnly = false;
                    if (emailEl) emailEl.readOnly = false;
                    if (phoneEl) phoneEl.readOnly = false;
                }
            } catch (_) {}
            // Initialize Pax limit UI now that package is known
            updatePaxLimitUI();
            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('inquiryModal');
            modal.style.display = 'none';
        }

        async function gotoStep(step, reset=false){
            document.querySelectorAll('.step').forEach(el=>el.classList.add('hidden'));
            document.getElementById(`step${step}`).classList.remove('hidden');
            if(reset){
                // clear inputs
                ['cp_fullName','cp_phone','cp_email','cp_date','cp_street','cp_barangay','cp_municipality','cp_province','cp_addon_pax','cp_chairs','cp_tables','cp_notes','cp_number','cp_amount_input'].forEach(id=>{
                    const el = document.getElementById(id); if(!el) return; if(el.type==='number') el.value = 0; else el.value = '';
                });
                document.getElementById('cp_pay_type').value = 'Card';
                document.getElementById('cp_terms').checked = false;
                const btn = document.getElementById('btnToSummary'); btn.disabled = true; btn.classList.add('opacity-60','cursor-not-allowed');
            }
            if(step===4){
                // collect and compute
                bookingState.details.fullName = document.getElementById('cp_fullName').value.trim();
                bookingState.details.phone = document.getElementById('cp_phone').value.trim();
                bookingState.details.email = document.getElementById('cp_email').value.trim();
                bookingState.details.date = document.getElementById('cp_date').value;
                // Check server availability before proceeding
                try {
                    const r = await fetch('api_check_availability.php?date='+encodeURIComponent(bookingState.details.date), { headers:{'X-Requested-With':'XMLHttpRequest'} });
                    const j = await r.json();
                    if (!j.ok || !j.available) {
                        alert(j.ok ? 'That date is already booked. Please choose another.' : 'Unable to verify availability. Please try again.');
                        return gotoStep(1);
                    }
                } catch(_) { alert('Unable to verify availability. Please try again.'); return gotoStep(1); }
                // enforce min date (today + 14 days)
                const minStr = document.getElementById('cp_date').min || '';
                if (minStr && bookingState.details.date < minStr) {
                    alert(`Please choose an event date on or after ${minStr}.`);
                    return gotoStep(1);
                }
                bookingState.details.street = document.getElementById('cp_street').value.trim();
                bookingState.details.barangay = document.getElementById('cp_barangay').value.trim();
                bookingState.details.municipality = document.getElementById('cp_municipality').value.trim();
                bookingState.details.province = document.getElementById('cp_province').value.trim();
                bookingState.addons.addonPax = parseInt(document.getElementById('cp_addon_pax').value||'0');
                bookingState.addons.chairs = parseInt(document.getElementById('cp_chairs').value||'0');
                bookingState.addons.tables = parseInt(document.getElementById('cp_tables').value||'0');
                bookingState.addons.notes = document.getElementById('cp_notes').value.trim();
                // Enforce max additional pax based on venue capacity rule
                const maxAdd = computeAddonPaxMax(bookingState.pkg.pax);
                if (maxAdd != null && bookingState.addons.addonPax > maxAdd) {
                    alert(`Maximum additional pax allowed is ${maxAdd}. Please adjust your Additional Pax quantity.`);
                    document.getElementById('cp_addon_pax').focus();
                    return gotoStep(2);
                }
                // validations minimal for required fields
                if(!bookingState.details.fullName || !bookingState.details.phone || !bookingState.details.email || !bookingState.details.date || !bookingState.details.street || !bookingState.details.barangay || !bookingState.details.municipality || !bookingState.details.province){
                    alert('Please complete your details and address.');
                    return gotoStep(1);
                }
                // basic email format check
                if(bookingState.details.email.indexOf('@')===-1 || bookingState.details.email.indexOf('.')===-1){
                    alert('Please enter a valid email address.');
                    return gotoStep(1);
                }
                const total = (Number(bookingState.pkg.basePrice)||0) + ((Number(bookingState.addons.addonPax)||0) * 200);
                const deposit = total*0.5;
                bookingState.amounts.total = total;
                bookingState.amounts.deposit = deposit;
                document.getElementById('sum_pkg_name').textContent = `${bookingState.pkg.name} (${bookingState.pkg.pax} pax)`;
                document.getElementById('sum_base_price').textContent = peso(bookingState.pkg.basePrice);
                document.getElementById('sum_addon').textContent = `${bookingState.addons.addonPax} x ₱200.00 = ${peso((Number(bookingState.addons.addonPax)||0)*200)}`;
                document.getElementById('sum_total').textContent = peso(total);
                document.getElementById('sum_deposit').textContent = peso(deposit);
            }
            if(step===5){
                // Keep the display field fixed at 50% (deposit)
                const deposit = Number(bookingState.amounts.deposit||0);
                const total = Number(bookingState.amounts.total||0);
                const depositStr = deposit.toFixed(2);
                document.getElementById('cp_pay_amount').value = depositStr;
                // Prefill the typeable exact amount with 50% and constrain to [50%, 100%]
                const amtEl = document.getElementById('cp_amount_input');
                const hintEl = document.getElementById('cp_amount_hint');
                if (amtEl){
                    const minPay = Math.round(deposit*100)/100;
                    const maxPay = Math.round(total*100)/100;
                    amtEl.min = String(minPay);
                    amtEl.max = String(maxPay);
                    // If user hasn't typed a value yet or it's out of bounds, reset to 50%
                    const cur = parseFloat(amtEl.value||'NaN');
                    if (Number.isNaN(cur) || cur < minPay || cur > maxPay) {
                        amtEl.value = depositStr; // show 50% in the text field
                    }
                    if (hintEl) {
                        hintEl.textContent = `You can pay any amount between 50% and 100% of the total (${peso(minPay)} – ${peso(maxPay)}).`;
                    }
                }
            }
        }

        // Recompute pax limit when user edits Additional Pax
        document.addEventListener('input', (e)=>{
            if (e.target && e.target.id === 'cp_addon_pax') {
                updatePaxLimitUI();
            }
        });

        // Terms checkbox gating
        document.addEventListener('change', (e)=>{
            if(e.target && e.target.id==='cp_terms'){
                const btn = document.getElementById('btnToSummary');
                if(e.target.checked){ btn.disabled=false; btn.classList.remove('opacity-60','cursor-not-allowed'); }
                else { btn.disabled=true; btn.classList.add('opacity-60','cursor-not-allowed'); }
            }
            if(e.target && e.target.id==='cp_pay_type'){
                const type = e.target.value;
                const label = document.getElementById('cp_number_label');
                const input = document.getElementById('cp_number');
                if (type === 'Card') {
                    label.textContent = 'Card Number';
                    input.placeholder = 'XXXX-XXXX-XXXX-XXXX';
                    input.setAttribute('inputmode','numeric');
                    input.removeAttribute('maxlength');
                } else {
                    label.textContent = 'Mobile/Account Number';
                    input.placeholder = '09XXXXXXXXX or account';
                    input.setAttribute('inputmode','tel');
                    input.setAttribute('maxlength','11');
                }
            }
        });

        async function submitBooking(ev){
            ev.preventDefault();
            // validate exact amount: allow between 50% and 100% of total
            const entered = parseFloat(document.getElementById('cp_amount_input').value||'0');
            const mustPay = Math.round(bookingState.amounts.deposit*100)/100; // 50%
            const total = Math.round(bookingState.amounts.total*100)/100;
            const minPay = mustPay;
            const maxPay = total;
            if (Number.isNaN(entered)){
                alert('Please enter the amount you wish to pay now.');
                return;
            }
            const entered2 = Math.round(entered*100)/100;
            if (entered2 < minPay || entered2 > maxPay){
                alert(`Amount must be between ${peso(minPay)} and ${peso(maxPay)}.`);
                return;
            }
            const payType = document.getElementById('cp_pay_type').value;
            const number = document.getElementById('cp_number').value.trim();
            if(!number){ alert('Please enter your card/account number.'); return; }

            const payload = {
                package_id: bookingState.pkg.id,
                package_name: bookingState.pkg.name,
                package_pax: bookingState.pkg.pax,
                base_price: bookingState.pkg.basePrice,
                addon_pax: bookingState.addons.addonPax,
                chairs: bookingState.addons.chairs,
                tables: bookingState.addons.tables,
                notes: bookingState.addons.notes,
                full_name: bookingState.details.fullName,
                phone: bookingState.details.phone,
                email: bookingState.details.email,
                street: bookingState.details.street,
                barangay: bookingState.details.barangay,
                municipality: bookingState.details.municipality,
                province: bookingState.details.province,
                event_date: bookingState.details.date,
                total_price: bookingState.amounts.total,
                deposit_amount: mustPay,
                pay_now: entered2,
                pay_type: payType,
                pay_number: number
            };
            try{
                const res = await fetch('cp_checkout.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
                const data = await res.json();
                if(!data.success){
                    alert(data.message || 'Booking failed.');
                    return;
                }
                alert('Booking submitted! We will contact you shortly.');
                closeModal();
            }catch(err){
                alert('Network error. Please try again.');
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('inquiryModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
    </script>
</body>
</html>
