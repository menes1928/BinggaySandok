<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Bookings - Sandok ni Binggay</title>
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
        
        /* Navbar Styles */
        .navbar {
            background: rgba(27, 67, 50, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        
        .navbar.scrolled {
            background: rgba(27, 67, 50, 1);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.5);
        }
        
        .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: #D4AF37;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 80%;
        }
        
        /* Hero Section */
        .hero-section {
            margin-top: 80px;
            padding: 100px 0;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)),
                        url('https://images.unsplash.com/photo-1738669469338-801b4e9dbccf?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlbGVnYW50JTIwd2VkZGluZyUyMHJlY2VwdGlvbnxlbnwxfHx8fDE3NjAxNDEyODZ8MA&ixlib=rb-4.1.0&q=80&w=1080');
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
        
        /* Event Card Styles */
        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            padding: 60px 0;
        }
        
        .event-card {
            position: relative;
            height: 500px;
            border-radius: 25px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(212, 175, 55, 0.2);
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(27, 67, 50, 0.8) 60%, rgba(27, 67, 50, 0.95) 100%);
            z-index: 1;
            transition: all 0.5s ease;
        }
        
        .event-card:hover::before {
            background: linear-gradient(180deg, rgba(212, 175, 55, 0.2) 0%, rgba(27, 67, 50, 0.85) 50%, rgba(27, 67, 50, 1) 100%);
        }
        
        .event-card-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.5s ease;
        }
        
        .event-card:hover .event-card-image {
            transform: scale(1.15);
        }
        
        .event-card-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 40px 30px;
            z-index: 2;
            transform: translateY(0);
            transition: all 0.5s ease;
        }
        
        .event-card:hover .event-card-content {
            transform: translateY(-10px);
        }
        
        .event-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #D4AF37 0%, #c9a32a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 30px;
            color: #1B4332;
            transition: all 0.4s ease;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }
        
        .event-card:hover .event-icon {
            transform: rotate(360deg) scale(1.1);
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.5);
        }
        
        .event-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #fff;
            transition: all 0.3s ease;
        }
        
        .event-card:hover .event-title {
            color: #D4AF37;
        }
        
        .event-description {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.5s ease;
            color: #fff;
            line-height: 1.6;
        }
        
        .event-card:hover .event-description {
            opacity: 1;
            max-height: 200px;
            margin-bottom: 20px;
        }
        
        .event-features {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease 0.1s;
        }
        
        .event-card:hover .event-features {
            opacity: 1;
            transform: translateY(0);
        }
        
        .feature-badge {
            display: inline-block;
            padding: 6px 15px;
            background: rgba(212, 175, 55, 0.2);
            border: 1px solid #D4AF37;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-right: 8px;
            margin-bottom: 8px;
            color: #D4AF37;
        }
        
        /* Booking Form Section */
        .booking-form-section {
            background: rgba(27, 67, 50, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 60px;
            margin: 80px auto;
            max-width: 1200px;
            border: 2px solid rgba(212, 175, 55, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .form-group {
            margin-bottom: 30px;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #D4AF37;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 18px 24px;
            border-radius: 15px;
            border: 2px solid rgba(212, 175, 55, 0.3);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }
        
        .form-input::placeholder,
        .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: #D4AF37;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1),
                        0 10px 30px rgba(212, 175, 55, 0.2);
            transform: translateY(-2px);
        }
        
        .form-input:hover,
        .form-select:hover,
        .form-textarea:hover {
            border-color: rgba(212, 175, 55, 0.6);
            background: rgba(255, 255, 255, 0.08);
        }
        
        /* Input Icon Animation */
        .input-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(212, 175, 55, 0.5);
            transition: all 0.3s ease;
            pointer-events: none;
            margin-top: 18px;
        }
        
        .form-group:focus-within .input-icon {
            color: #D4AF37;
            transform: translateY(-50%) scale(1.2);
        }
        
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23D4AF37'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.5em 1.5em;
            padding-right: 3rem;
        }
        
        .form-select option {
            background: #1B4332;
            color: #fff;
        }
        
        /* Checkbox Styles */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 2px solid rgba(212, 175, 55, 0.2);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .checkbox-group:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(212, 175, 55, 0.4);
            transform: translateX(5px);
        }
        
        .checkbox-input {
            width: 24px;
            height: 24px;
            accent-color: #D4AF37;
            cursor: pointer;
        }
        
        /* Submit Button */
        .btn-submit {
            background: linear-gradient(135deg, #D4AF37 0%, #c9a32a 100%);
            color: #1B4332;
            padding: 20px 60px;
            border-radius: 50px;
            border: none;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }
        
        .btn-submit::before {
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
        
        .btn-submit:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 50px rgba(212, 175, 55, 0.5);
        }
        
        .btn-submit:active {
            transform: translateY(-1px);
        }
        
        .btn-submit span {
            position: relative;
            z-index: 1;
        }
        
        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin: 60px 0;
        }
        
        .stat-card {
            text-align: center;
            padding: 30px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            transition: all 0.4s ease;
        }
        
        .stat-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: #D4AF37;
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.2);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: #D4AF37;
            font-family: 'Playfair Display', serif;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0;
            }
            
            .event-grid {
                grid-template-columns: 1fr;
            }
            
            .booking-form-section {
                padding: 30px 20px;
                margin: 40px 20px;
            }
            
            .btn-submit {
                width: 100%;
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
        
        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .loading.active {
            display: flex;
        }
        
        .loader {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(212, 175, 55, 0.2);
            border-top: 5px solid #D4AF37;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <?php include __DIR__ . '/partials/navbar-guest.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container mx-auto px-4 text-center hero-content">
            <h1 class="text-5xl md:text-7xl font-bold mb-6">Book Your Event</h1>
            <p class="text-xl md:text-2xl gold-text mb-8">Let us make your celebration unforgettable</p>
            <p class="text-lg max-w-3xl mx-auto opacity-90">
                From intimate gatherings to grand celebrations, we provide exceptional catering services 
                that bring the warmth of home-cooked meals to your special occasions.
            </p>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="container mx-auto px-4">
        <div class="stats-section scroll-animate">
            <div class="stat-card">
                <div class="stat-number">500+</div>
                <div class="stat-label">Events Catered</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">5000+</div>
                <div class="stat-label">Happy Guests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">98%</div>
                <div class="stat-label">Satisfaction Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">10+</div>
                <div class="stat-label">Years Experience</div>
            </div>
        </div>
    </section>

    <!-- Event Types Section -->
    <section class="container mx-auto px-4 py-16">
        <h2 class="text-4xl md:text-5xl font-bold text-center mb-4 gold-text scroll-animate">Events We Cater</h2>
        <p class="text-center text-lg mb-12 opacity-90 scroll-animate">Choose from our wide range of event catering services</p>
        
        <div class="event-grid">
            <!-- Birthday Party -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1650584997985-e713a869ee77?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxiaXJ0aGRheSUyMHBhcnR5JTIwY2VsZWJyYXRpb258ZW58MXx8fHwxNzYwMTkyOTY5fDA&ixlib=rb-4.1.0&q=80&w=1080" 
                     alt="Birthday Party" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <h3 class="event-title">Birthday Parties</h3>
                    <p class="event-description">
                        Celebrate another year of joy with delicious food that brings everyone together. 
                        Perfect for all ages, from kids' parties to milestone birthdays.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Custom Themes</span>
                        <span class="feature-badge">Birthday Cake</span>
                        <span class="feature-badge">Party Setup</span>
                    </div>
                </div>
            </div>

            <!-- Wedding -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1738669469338-801b4e9dbccf?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlbGVnYW50JTIwd2VkZGluZyUyMHJlY2VwdGlvbnxlbnwxfHx8fDE3NjAxNDEyODZ8MA&ixlib=rb-4.1.0&q=80&w=1080" 
                     alt="Wedding" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-ring"></i>
                    </div>
                    <h3 class="event-title">Weddings</h3>
                    <p class="event-description">
                        Make your special day even more memorable with our premium wedding catering services. 
                        Elegant presentation meets authentic Filipino flavors.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Premium Menu</span>
                        <span class="feature-badge">Elegant Setup</span>
                        <span class="feature-badge">Full Service</span>
                    </div>
                </div>
            </div>

            <!-- Debut -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1759866221951-38c624319c98?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkZWJ1dCUyMHBhcnR5JTIwY2VsZWJyYXRpb258ZW58MXx8fHwxNzYwMjEwMTAxfDA&ixlib=rb-4.1.0&q=80&w=1080" 
                     alt="Debut" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <h3 class="event-title">Debut (18th Birthday)</h3>
                    <p class="event-description">
                        Celebrate this once-in-a-lifetime milestone with a grand feast. 
                        Sophisticated menus designed for this special coming-of-age celebration.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Grand Buffet</span>
                        <span class="feature-badge">Elegant Decor</span>
                        <span class="feature-badge">Photo-worthy</span>
                    </div>
                </div>
            </div>

            <!-- Corporate Events -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1752766074168-44afdbaaf390?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjb3Jwb3JhdGUlMjBldmVudCUyMGRpbmluZ3xlbnwxfHx8fDE3NjAxMTM3ODN8MA&ixlib=rb-4.1.0&q=80&w=1080" 
                     alt="Corporate Events" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <h3 class="event-title">Corporate Events</h3>
                    <p class="event-description">
                        Professional catering for company events, meetings, and team building activities. 
                        Impress your colleagues and clients with quality food service.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Professional</span>
                        <span class="feature-badge">Punctual</span>
                        <span class="feature-badge">Reliable</span>
                    </div>
                </div>
            </div>

            <!-- Family Reunions -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1753289595399-341aaf3b1ee1?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxmYW1pbHklMjByZXVuaW9uJTIwZ2F0aGVyaW5nfGVufDF8fHx8MTc2MDIxMDEwMXww&ixlib=rb-4.1.0&q=80&w=1080" 
                     alt="Family Reunions" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="event-title">Family Reunions</h3>
                    <p class="event-description">
                        Bring your family together with the comforting taste of home-cooked Filipino meals. 
                        Perfect for large gatherings and multi-generational celebrations.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Family Style</span>
                        <span class="feature-badge">Large Groups</span>
                        <span class="feature-badge">Flexible Menu</span>
                    </div>
                </div>
            </div>

            <!-- Anniversary -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1722491634411-09ed1b34eacc?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxhbm5pdmVyc2FyeSUyMGNlbGVicmF0aW9ufGVufDF8fHx8MTc2MDIwMDQ4Nnww&ixlib=rb-4.1.0&q=80&w=1080" 
                     alt="Anniversary" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3 class="event-title">Anniversaries</h3>
                    <p class="event-description">
                        Celebrate love and togetherness with a romantic or festive spread. 
                        From intimate dinners to grand celebrations with family and friends.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Romantic Setup</span>
                        <span class="feature-badge">Special Menu</span>
                        <span class="feature-badge">Intimate or Grand</span>
                    </div>
                </div>
            </div>

            <!-- Gender Reveal -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1519741497674-611481863552?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080" 
                     alt="Gender Reveal" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-baby"></i>
                    </div>
                    <h3 class="event-title">Gender Reveal</h3>
                    <p class="event-description">
                        Celebrate the big surprise with pastel décor, themed desserts, and a picture-perfect reveal moment.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Custom Theme</span>
                        <span class="feature-badge">Dessert Bar</span>
                        <span class="feature-badge">Photo Corner</span>
                    </div>
                </div>
            </div>

            <!-- And Many More -->
            <div class="event-card scroll-animate">
                <img src="https://images.unsplash.com/photo-1519681393784-d120267933ba?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080" 
                     alt="Many More Events" class="event-card-image">
                <div class="event-card-content">
                    <div class="event-icon">
                        <i class="fas fa-infinity"></i>
                    </div>
                    <h3 class="event-title">And Many More</h3>
                    <p class="event-description">
                        From baby showers and christenings to graduations and housewarmings—we’ll tailor the menu to your celebration.
                    </p>
                    <div class="event-features">
                        <span class="feature-badge">Custom Menus</span>
                        <span class="feature-badge">Flexible Setups</span>
                        <span class="feature-badge">On-site Staff</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Form Section -->
    <section class="container mx-auto px-4 pb-16">
        <div class="booking-form-section scroll-animate">
            <div class="text-center mb-12">
                <h2 class="text-4xl md:text-5xl font-bold mb-4 gold-text">Book Your Event Now</h2>
                <p class="text-lg opacity-90">Fill out the form below and we'll get back to you within 24 hours</p>
            </div>

            <form id="bookingForm">
                <!-- Personal Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="fullName" class="form-input" placeholder="Juan Dela Cruz" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-input" placeholder="juan@email.com" required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number *</label>
                        <input type="tel" name="phone" class="form-input" placeholder="09XX XXX XXXX" maxlength="11" required>
                        <i class="fas fa-phone input-icon"></i>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Alternative Contact</label>
                        <input type="tel" name="altPhone" class="form-input" placeholder="Optional" maxlength="11">
                        <i class="fas fa-phone-alt input-icon"></i>
                    </div>
                </div>

                <!-- Event Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="form-group">
                        <label class="form-label">Event Type *</label>
                        <select name="eventTypeId" id="bf-event-type" class="form-select" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Event Date *</label>
                        <input type="date" name="eventDate" class="form-input" required>
                        <i class="fas fa-calendar input-icon"></i>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Event Time *</label>
                        <input type="time" name="eventTime" class="form-input" required>
                        <i class="fas fa-clock input-icon"></i>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Preferred Package *</label>
                        <select name="packageId" id="bf-package" class="form-select" required>
                            <option value="">Select an event type first</option>
                        </select>
                        <div id="pkg-price-info" class="mt-2 text-sm opacity-80"></div>
                    </div>
                </div>

                <!-- Venue Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="form-group">
                        <label class="form-label">Venue Name *</label>
                        <input type="text" name="venueName" class="form-input" placeholder="Venue or Hall name" required>
                        <i class="fas fa-building input-icon"></i>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Street *</label>
                        <input type="text" name="venueStreet" class="form-input" placeholder="Street / Block / Lot" required>
                        <i class="fas fa-road input-icon"></i>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Barangay</label>
                        <input type="text" name="venueBarangay" class="form-input" placeholder="Optional">
                        <i class="fas fa-location-arrow input-icon"></i>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City/Municipality *</label>
                        <input type="text" name="venueCity" class="form-input" placeholder="e.g., Quezon City" required>
                        <i class="fas fa-city input-icon"></i>
                    </div>
                    <div class="form-group md:col-span-2">
                        <label class="form-label">Province *</label>
                        <input type="text" name="venueProvince" class="form-input" placeholder="e.g., Batangas" required>
                        <i class="fas fa-map input-icon"></i>
                    </div>
                </div>

                <!-- Add-ons -->
                <div class="form-group mt-6">
                    <label class="form-label">Add-ons</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="checkbox-group">
                                <input type="checkbox" class="checkbox-input bf-addon" data-addon="Pax">
                                <span><i class="fas fa-user-friends gold-text mr-2"></i>Pax (+₱200/head)</span>
                            </label>
                            <div class="mt-2 hidden bf-addon-fields" data-addon-fields="Pax">
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="addon[Pax][unit]" class="form-select">
                                        <option value="head">Per Head</option>
                                    </select>
                                    <input type="number" min="1" name="addon[Pax][qty]" class="form-input" placeholder="Qty">
                                </div>
                                <div id="pax-estimate" class="mt-2 text-sm opacity-80 hidden"></div>
                                <div id="pax-limit" class="mt-1 text-xs opacity-80 hidden"></div>
                            </div>
                        </div>
                        <div>
                            <label class="checkbox-group">
                                <input type="checkbox" class="checkbox-input bf-addon" data-addon="Table">
                                <span><i class="fas fa-th-large gold-text mr-2"></i>Table</span>
                            </label>
                            <div class="mt-2 hidden bf-addon-fields" data-addon-fields="Table">
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="addon[Table][unit]" class="form-select">
                                        <option value="table">Table</option>
                                    </select>
                                    <input type="number" min="1" name="addon[Table][qty]" class="form-input" placeholder="Qty">
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="checkbox-group">
                                <input type="checkbox" class="checkbox-input bf-addon" data-addon="Chairs">
                                <span><i class="fas fa-chair gold-text mr-2"></i>Chairs</span>
                            </label>
                            <div class="mt-2 hidden bf-addon-fields" data-addon-fields="Chairs">
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="addon[Chairs][unit]" class="form-select">
                                        <option value="chair">Chair</option>
                                    </select>
                                    <input type="number" min="1" name="addon[Chairs][qty]" class="form-input" placeholder="Qty">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inclusions (dynamic based on Event Type) -->
                <div id="inclusions-section" class="form-group mt-6 hidden">
                    <label class="form-label">Inclusions</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <label class="checkbox-group bf-inclusion-item hidden" data-inc="Clowns" id="inc-clowns">
                            <input type="checkbox" class="checkbox-input bf-inclusion" value="Clowns">
                            <span><i class="fas fa-smile gold-text mr-2"></i>Clowns</span>
                        </label>
                        <label class="checkbox-group bf-inclusion-item" data-inc="Entertainer" id="inc-entertainer">
                            <input type="checkbox" class="checkbox-input bf-inclusion" value="Entertainer">
                            <span><i class="fas fa-microphone gold-text mr-2"></i>Entertainer</span>
                        </label>
                        <label class="checkbox-group bf-inclusion-item" data-inc="Props" id="inc-props">
                            <input type="checkbox" class="checkbox-input bf-inclusion" value="Props">
                            <span><i class="fas fa-theater-masks gold-text mr-2"></i>Props</span>
                        </label>
                    </div>
                    <p class="mt-2 text-sm opacity-80">Select any applicable inclusions for your event.</p>
                </div>

                <!-- Special Requests -->
                <div class="form-group mt-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="6" placeholder="Any additional notes"></textarea>
                </div>

                <!-- Terms and Conditions -->
                <div class="form-group mt-8">
                    <label class="checkbox-group">
                        <input type="checkbox" id="bf-agree" name="agree" class="checkbox-input" required>
                        <span>I agree to the Terms and Agreement: 50% payment in person and contract signing before confirmation.</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="text-center mt-10">
                    <button type="submit" id="bf-submit" class="btn-submit" disabled>
                        <span><i class="fas fa-calendar-check mr-3"></i>Submit Booking Request</span>
                    </button>
                    <p class="mt-4 text-sm opacity-75">We'll review your request and contact you within 24 hours</p>
                </div>
            </form>
        </div>
    </section>

    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script>
        // Shared navbar partial manages contrast and interactions

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

        // Gate submit button by terms checkbox
        const agreeEl = document.getElementById('bf-agree');
        const submitBtn = document.getElementById('bf-submit');
        if (agreeEl && submitBtn) {
            const syncAgree = ()=> { submitBtn.disabled = !agreeEl.checked; submitBtn.style.opacity = agreeEl.checked ? '1' : '0.6'; };
            agreeEl.addEventListener('change', syncAgree); syncAgree();
        }

        // Populate Event Types and Packages (guest paths are from project root)
        const etSel = document.getElementById('bf-event-type');
        const pkgSel = document.getElementById('bf-package');
        async function loadEventTypes(){
            try {
                const r = await fetch('user/api_eventtypes.php?action=list', { headers:{'X-Requested-With':'XMLHttpRequest'} });
                const j = await r.json();
                if (!j.success) throw new Error();
                etSel.innerHTML = '<option value="">Select Event Type</option>' + (j.data||[]).map(et=>`<option value="${et.event_type_id}">${et.name}</option>`).join('');
            } catch(_) { etSel.innerHTML = '<option value="">Failed to load</option>'; }
        }
        async function loadPackagesFor(etId){
            pkgSel.innerHTML = '<option value="">Loading packages...</option>';
            try {
                // Filter allowed packages for this event type
                const res = await fetch('user/api_eventtypes.php?action=get&event_type_id='+encodeURIComponent(etId), { headers:{'X-Requested-With':'XMLHttpRequest'} });
                const data = await res.json();
                if (!data.success) throw new Error();
                const allowed = new Set((data.package_ids||[]).map(Number));
                const all = await fetch('user/api_eventtypes.php?action=list_packages', { headers:{'X-Requested-With':'XMLHttpRequest'} }).then(r=>r.json());
                if (!all.success) throw new Error();
                const opts = (all.data||[]).filter(p=>allowed.has(Number(p.package_id))).map(p=>{
                    const price = (p.base_price!=null && p.base_price!=='') ? Number(p.base_price) : '';
                    const label = (p.name||'') + (p.pax?(' - '+p.pax):'');
                    const paxAttr = (p.pax!=null && p.pax!=='') ? ` data-pax="${p.pax}"` : '';
                    const dp = price!=='' ? ` data-price="${price}"` : '';
                    return `<option value="${p.package_id}"${dp}${paxAttr}>${label}</option>`;
                });
                pkgSel.innerHTML = opts.length ? ('<option value="">Select Package</option>'+opts.join('')) : '<option value="">No packages available</option>';
            } catch(_) { pkgSel.innerHTML = '<option value="">Failed to load packages</option>'; }
        }
        const inclusionsSection = document.getElementById('inclusions-section');
        const incClowns = document.getElementById('inc-clowns');
        const incChecks = document.querySelectorAll('.bf-inclusion');
        const pkgPriceInfo = document.getElementById('pkg-price-info');
        const paxEstimateEl = document.getElementById('pax-estimate');
        const paxLimitEl = document.getElementById('pax-limit');
        const paxCb = document.querySelector('.bf-addon[data-addon="Pax"]');
        const paxQtyInput = document.querySelector('.bf-addon-fields[data-addon-fields="Pax"] input[name="addon[Pax][qty]"]');
        const PER_HEAD_RATE = 200;
        const VENUE_CAPACITY = 250; // Global cap per venue as requested

        function formatCurrency(num){
            const n = Number(num||0);
            return '₱' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function getSelectedPackagePrice(){
            if (!pkgSel) return null;
            const opt = pkgSel.options && pkgSel.selectedIndex >= 0 ? pkgSel.options[pkgSel.selectedIndex] : null;
            if (!opt) return null;
            const dp = opt.getAttribute('data-price');
            return (dp!==null && dp!=='') ? Number(dp) : null;
        }

        function getSelectedPackagePax(){
            if (!pkgSel) return null;
            const opt = pkgSel.options && pkgSel.selectedIndex >= 0 ? pkgSel.options[pkgSel.selectedIndex] : null;
            if (!opt) return null;
            const pax = opt.getAttribute('data-pax');
            return (pax!==null && pax!=='') ? Number(pax) : null;
        }

        function computePaxMax(){
            const pkgPax = getSelectedPackagePax();
            if (pkgPax==null) return null;
            if (pkgPax === 50) return 50; // special rule
            const max = Math.max(0, VENUE_CAPACITY - pkgPax);
            return max;
        }

        function updatePaxLimitUI(){
            if (!paxLimitEl) return;
            const max = computePaxMax();
            if (max==null){
                paxLimitEl.textContent = '';
                paxLimitEl.classList.add('hidden');
                if (paxQtyInput){ paxQtyInput.removeAttribute('max'); }
                return;
            }
            paxLimitEl.textContent = `You can add up to ${max} additional pax for this venue/package.`;
            paxLimitEl.classList.remove('hidden');
            if (paxQtyInput){
                paxQtyInput.setAttribute('max', String(max));
                const cur = Number(paxQtyInput.value||0);
                if (cur > max){ paxQtyInput.value = String(max); }
            }
        }

        function updatePackagePriceDisplay(){
            const price = getSelectedPackagePrice();
            if (pkgPriceInfo){
                if (price!=null){ pkgPriceInfo.textContent = `Package price: ${formatCurrency(price)}`; }
                else { pkgPriceInfo.textContent = ''; }
            }
            updatePaxEstimate();
            updatePaxLimitUI();
        }

        function updatePaxEstimate(){
            if (!paxEstimateEl) return;
            const price = getSelectedPackagePrice();
            const paxChecked = paxCb ? paxCb.checked : false;
            const qty = paxQtyInput ? Number(paxQtyInput.value||0) : 0;
            if (price!=null && paxChecked && qty>0){
                const estimate = price + (qty * PER_HEAD_RATE);
                paxEstimateEl.textContent = `Estimated total: ${formatCurrency(estimate)}`;
                paxEstimateEl.classList.remove('hidden');
            } else {
                paxEstimateEl.textContent = '';
                paxEstimateEl.classList.add('hidden');
            }
        }
        function syncInclusionsVisibility(){
            if (!etSel) return;
            const selectedText = etSel.options && etSel.selectedIndex >= 0 ? (etSel.options[etSel.selectedIndex].text||'') : '';
            const hasEvent = !!etSel.value;
            if (inclusionsSection) inclusionsSection.classList.toggle('hidden', !hasEvent);
            const isBirthday = /birthday/i.test(selectedText);
            if (incClowns){
                incClowns.classList.toggle('hidden', !isBirthday);
                if (!isBirthday){
                    const cb = incClowns.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = false;
                }
            }
        }
        etSel?.addEventListener('change', (e)=>{ const v = e.target.value; if (v) loadPackagesFor(v); else { pkgSel.innerHTML = '<option value="">Select an event type first</option>'; } syncInclusionsVisibility(); updatePackagePriceDisplay(); });
        // Initial sync
        syncInclusionsVisibility();
        loadEventTypes();
        // Package price change
        pkgSel?.addEventListener('change', updatePackagePriceDisplay);
        // Also recompute limit when Pax qty changes (so estimate shows after clamping)
        paxQtyInput?.addEventListener('input', ()=>{ updatePaxLimitUI(); updatePaxEstimate(); });

        // Add-ons UI: toggle fields when checked
        const addonBoxes = document.querySelectorAll('.bf-addon');
        function toggleAddonFields(cb){
            const name = cb.getAttribute('data-addon');
            const fields = document.querySelector(`.bf-addon-fields[data-addon-fields="${name}"]`);
            if (!fields) return;
            const qtyInput = fields.querySelector('input[name^="addon["]');
            if (cb.checked){
                fields.classList.remove('hidden');
                if (qtyInput){ qtyInput.required = true; qtyInput.focus({preventScroll:true}); }
            } else {
                fields.classList.add('hidden');
                if (qtyInput){ qtyInput.required = false; qtyInput.value = ''; }
            }
        }
        addonBoxes.forEach(cb=>{ cb.addEventListener('change', ()=>{ toggleAddonFields(cb); updatePaxEstimate(); }); toggleAddonFields(cb); });
        paxQtyInput?.addEventListener('input', updatePaxEstimate);
        // Run once on load
        updatePackagePriceDisplay();

        // Form Validation and Submission (guest cannot book)
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // If not logged in, send to login with safe next to user booking
            if (!(window.SNB_USER && window.SNB_USER.loggedIn)) {
                window.location.href = 'login?next=' + encodeURIComponent('/Binggay/user/booking');
                return false;
            }
            // If already logged in, take them to the user booking page
            window.location.href = '/Binggay/user/booking';
            return false;
        });

    // Require event date at least 14 days from now
    const d = new Date();
    d.setDate(d.getDate() + 14);
    const minStr = d.toISOString().split('T')[0];
    const eventEl = document.querySelector('input[name="eventDate"]');
    if (eventEl) { eventEl.setAttribute('min', minStr); }

        // Input animation effects
        const formInputs = document.querySelectorAll('.form-input, .form-select, .form-textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('.form-label').style.transform = 'translateY(-3px)';
                this.parentElement.querySelector('.form-label').style.color = '#D4AF37';
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.querySelector('.form-label').style.transform = 'translateY(0)';
                    this.parentElement.querySelector('.form-label').style.color = '#D4AF37';
                }
            });
        });

        // Removed old static event type tip handler; now dynamic based on DB.
    </script>
</body>
</html>
