<?php
session_start();
require_once __DIR__ . '/classes/database.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    $next = isset($_POST['next']) ? trim($_POST['next']) : '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required';
    } else {
        try {
            $db = new database();
            $pdo = $db->opencon();
            $stmt = $pdo->prepare('SELECT user_id, user_fn, user_ln, user_username, user_email, user_phone, user_password, user_type, user_photo FROM users WHERE user_email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $isValid = false;
            $needsRehash = false;
            if ($user) {
                $stored = (string)$user['user_password'];
                // Primary: verify bcrypt/argon hash
                if (preg_match('/^\$2y\$/', $stored) || preg_match('/^\$argon2/', $stored)) {
                    $isValid = password_verify($password, $stored);
                    if ($isValid && password_needs_rehash($stored, PASSWORD_BCRYPT)) {
                        $needsRehash = true;
                    }
                } else {
                    // Legacy plaintext fallback: if stored equals provided password, accept and mark for rehash
                    if ($stored !== '' && hash_equals($stored, $password)) {
                        $isValid = true;
                        $needsRehash = true;
                    }
                }

                if ($isValid) {
                    // Optionally rehash to bcrypt for legacy/plaintext or weaker hashes
                    if ($needsRehash) {
                        try {
                            $newHash = password_hash($password, PASSWORD_BCRYPT);
                            $up = $pdo->prepare('UPDATE users SET user_password = ?, updated_at = NOW() WHERE user_id = ?');
                            $up->execute([$newHash, (int)$user['user_id']]);
                        } catch (Throwable $rehashErr) {
                            // Do not block login if rehash fails; optionally log
                        }
                    }

                    // Harden session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['user_id'];
                    // Store separate name parts and username for display in navbar
                    $_SESSION['user_fn'] = (string)($user['user_fn'] ?? '');
                    $_SESSION['user_ln'] = (string)($user['user_ln'] ?? '');
                    $_SESSION['user_username'] = (string)($user['user_username'] ?? '');
                    $_SESSION['user_name'] = trim(($_SESSION['user_fn'] ?? '') . ' ' . ($_SESSION['user_ln'] ?? ''));
                    $_SESSION['user_email'] = $user['user_email'];
                    $_SESSION['user_phone'] = isset($user['user_phone']) ? (string)$user['user_phone'] : '';
                    $_SESSION['user_type'] = (int)$user['user_type'];
                    $_SESSION['user_photo'] = isset($user['user_photo']) ? (string)$user['user_photo'] : null;

                    // After login: prefer safe "next" redirect if provided
                    $redirect = '';
                    if ($next !== '') {
                        // Only allow relative paths within this site to prevent open redirects
                        if (preg_match('~^/[^\n\r]*$~', $next)) {
                            // If no file extension present, append .php for hosts without extensionless routing
                            if (!preg_match('~\.[a-zA-Z0-9]+$~', $next)) {
                                $next .= '.php';
                            }
                            $redirect = $next;
                        }
                    }
                    if ($redirect === '') {
                        // Simple role-based fallback: 1 = admin
                        // Use explicit .php so it works on hosts without extensionless routing
                        $redirect = ((int)$user['user_type'] === 1)
                            ? '/Binggay/admin/admin'
                            : '/Binggay/user/index';
                    }
                    header('Location: ' . $redirect);
                    exit;
                }
            }
            $error = 'Invalid email or password';
        } catch (Throwable $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sandok ni Binggay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1B4332;
            --primary-dark: #0d2419;
            --accent: #D4AF37;
            --accent-dark: #b8941f;
            --background: #fefffe;
            --muted: #f8f8f6;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #fefffe 0%, #f8f8f6 100%);
        }

        .bg-primary { background-color: var(--primary); }
        .bg-accent { background-color: var(--accent); }
        .text-primary { color: var(--primary); }
        .text-accent { color: var(--accent); }
        .border-primary { border-color: var(--primary); }
        
        /* Gradient Background Animation */
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary) 0%, #2d5a3d 50%, #1B4332 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating Animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .float-animation {
            animation: float 6s ease-in-out infinite;
        }

        /* Fade In Animations */
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

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .animate-fade-in-left {
            animation: fadeInLeft 0.8s ease-out;
        }

        .animate-fade-in-right {
            animation: fadeInRight 0.8s ease-out;
        }

        /* Delay classes */
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }

        /* Input Focus Effects */
        .input-field {
            transition: all 0.3s ease;
        }

        .input-field:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(27, 67, 50, 0.15);
        }

        /* Button Hover Effects */
        .btn-primary {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(27, 67, 50, 0.3);
        }

        /* Decorative Elements */
        .decorative-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(212, 175, 55, 0.1);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.5;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.3;
            }
        }

        /* Checkbox Custom Style */
        .custom-checkbox:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        /* Shimmer effect for logo */
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.4), transparent);
            background-size: 1000px 100%;
            animation: shimmer 3s infinite;
        }

        /* Forgot Password Sliding Panel (desktop >= md) */
        .auth-card { position: relative; }
        .left-panel {
            position: absolute;
            inset: 0;
            transition: transform 0.6s ease;
            will-change: transform;
            z-index: 20;
        }
        .auth-card.forgot-active .left-panel { transform: translateX(100%); }
        /* Mobile modal */
        #forgot-modal { display: none; }
        #forgot-modal.show { display: flex; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1B4332',
                        'primary-dark': '#0d2419',
                        'accent': '#D4AF37',
                        'accent-dark': '#b8941f',
                        'muted': '#f8f8f6'
                    }
                }
            }
        }

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        // Form validation
        function validateForm(event) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            let isValid = true;

            // Reset error messages
            document.getElementById('email-error').classList.add('hidden');
            document.getElementById('password-error').classList.add('hidden');

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                document.getElementById('email-error').classList.remove('hidden');
                isValid = false;
            }

            // Password validation
            if (!password || password.length < 6) {
                document.getElementById('password-error').classList.remove('hidden');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
            }
        }

        // Add floating label effect
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.input-field');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('label').classList.add('text-primary');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.querySelector('label').classList.remove('text-primary');
                    }
                });
            });
        });
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-3 sm:p-4">
    <!-- Background Decorative Elements -->
    <div class="decorative-circle hidden md:block" style="width: 400px; height: 400px; top: -200px; right: -200px;"></div>
    <div class="decorative-circle hidden lg:block" style="width: 300px; height: 300px; bottom: -150px; left: -150px;"></div>

    <!-- Main Container -->
    <div class="w-full max-w-6xl px-2 sm:px-4">
        <div id="auth-card" class="auth-card bg-white rounded-2xl shadow-2xl overflow-hidden grid md:grid-cols-2 min-h-[520px] md:min-h-[600px]">
            <!-- Left Side: White forgot panel with green branding overlay that slides right (desktop) -->
            <div class="relative hidden md:block">
                <!-- White panel underneath with forgot-password fields -->
                <div class="p-6 sm:p-10 md:p-12 flex flex-col justify-center items-center bg-white min-h-[520px] md:min-h-[600px]">
                    <div class="w-full max-w-md">
                        <a href="#" onclick="closeForgot(); return false;" class="inline-flex items-center text-sm text-primary hover:text-primary-dark underline mb-4">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to sign in
                        </a>
                        <h2 class="text-2xl md:text-3xl font-bold mb-2 text-primary">Forgot Password</h2>
                        <p class="text-gray-600 mb-6">We’ll send a 6-character code to your email.</p>

                        <div id="fp-alert" class="hidden mb-4 p-3 rounded-md text-sm"></div>

                        <!-- Step 1: Email -->
                        <div id="fp-step-email" class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input id="fp-email" type="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" placeholder="you@example.com">
                            <button id="fp-send-btn" class="btn-primary w-full bg-primary hover:bg-primary-dark text-white font-semibold py-3 rounded-lg" onclick="sendOtp()">
                                <i class="fas fa-paper-plane mr-2"></i>Send OTP
                            </button>
                        </div>

                        <!-- Step 2: OTP -->
                        <div id="fp-step-otp" class="space-y-3 hidden mt-2">
                            <label class="block text-sm font-medium text-gray-700">Enter OTP</label>
                            <input id="fp-code" type="text" maxlength="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg tracking-widest text-center uppercase font-mono" placeholder="XXXXXX">
                            <div class="flex gap-2">
                                <button id="fp-verify-btn" class="btn-primary flex-1 bg-primary hover:bg-primary-dark text-white font-semibold py-3 rounded-lg" onclick="verifyOtp()">
                                    <i class="fas fa-check mr-2"></i>Verify OTP
                                </button>
                                <button id="fp-resend-btn" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg border border-gray-300" onclick="resendOtp()">
                                    Resend
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Reset Password -->
                        <div id="fp-step-reset" class="space-y-3 hidden mt-2">
                            <label class="block text-sm font-medium text-gray-700">New Password</label>
                            <div class="relative">
                                <input id="fp-pass" type="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg pr-12" placeholder="New password">
                                <button type="button" onclick="toggleVisibility('fp-pass','fp-pass-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary">
                                    <i id="fp-pass-eye" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <div class="relative">
                                <input id="fp-confirm" type="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg pr-12" placeholder="Confirm password">
                                <button type="button" onclick="toggleVisibility('fp-confirm','fp-confirm-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary">
                                    <i id="fp-confirm-eye" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500">Must be at least 8 characters and include 1 uppercase, 1 lowercase, 1 number, and 1 special character.</p>
                            <button id="fp-reset-btn" class="btn-primary w-full bg-primary hover:bg-primary-dark text-white font-semibold py-3 rounded-lg" onclick="resetPassword()">
                                <i class="fas fa-key mr-2"></i>Update Password
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Green branding overlay that slides to the right to reveal the white panel -->
                <div id="left-panel" class="left-panel gradient-bg p-8 lg:p-12 flex flex-col justify-center items-center text-white overflow-hidden">
                    <!-- Decorative Pattern -->
                    <div class="absolute inset-0 opacity-10">
                        <div class="absolute top-0 left-0 w-full h-full" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                    </div>
                    <!-- Branding Content -->
                    <div class="relative z-10 text-center animate-fade-in-left">
                        <div class="mb-8 float-animation">
                            <img src="images/logo.png" alt="Sandok ni Binggay" class="w-32 h-32 mx-auto rounded-full border-4 border-white/30 shadow-2xl object-cover">
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-bold mb-4 text-white">Sandok ni Binggay</h1>
                        <div class="h-1 w-24 bg-accent mx-auto mb-6 rounded-full"></div>
                        <p class="text-lg lg:text-xl text-white/90 mb-8">Nothing Beats Home-Cooked Meals</p>
                        <div class="space-y-4 text-left max-w-sm mx-auto">
                            <div class="flex items-center gap-3 text-white/90 animate-fade-in-left delay-100">
                                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"><i class="fas fa-utensils"></i></div>
                                <span>Authentic Filipino Cuisine</span>
                            </div>
                            <div class="flex items-center gap-3 text-white/90 animate-fade-in-left delay-200">
                                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"><i class="fas fa-star"></i></div>
                                <span>Premium Catering Services</span>
                            </div>
                            <div class="flex items-center gap-3 text-white/90 animate-fade-in-left delay-300">
                                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"><i class="fas fa-heart"></i></div>
                                <span>Made with Love & Care</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form / Mobile Forgot -->
            <div class="p-6 sm:p-8 md:p-12 flex flex-col justify-center bg-white">
                <!-- LOGIN CONTENT (shown by default) -->
                <div id="login-content" class="max-w-md mx-auto w-full animate-fade-in-right">
                        <div class="mb-4 -mt-2">
                            <a href="index" class="inline-flex items-center text-sm text-primary hover:text-primary-dark underline">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Sandok ni Binggay
                            </a>
                        </div>
                    <!-- Mobile Logo -->
                    <div class="md:hidden text-center mb-8">
                        <img src="images/logo.png" 
                             alt="Sandok ni Binggay" 
                             class="w-20 h-20 mx-auto rounded-full border-4 border-primary shadow-lg object-cover mb-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-primary">Sandok ni Binggay</h2>
                    </div>

                    <!-- Welcome Text -->
                    <div class="mb-6 md:mb-8 animate-fade-in-up">
                        <h2 class="text-2xl md:text-3xl font-bold text-primary mb-2">Welcome Back!</h2>
                        <p class="text-gray-600">Sign in to access your account</p>
                    </div>

                    <?php if (!empty($_SESSION['registration_success'])): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center gap-2 animate-fade-in-up">
                        <i class="fas fa-check-circle"></i>
                        <span>Registration successful. Please sign in.</span>
                    </div>
                    <?php unset($_SESSION['registration_success']); endif; ?>

                    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'login_required'): ?>
                    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg flex items-center gap-2 animate-fade-in-up">
                        <i class="fas fa-info-circle"></i>
                        <span>You must log in before ordering.</span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center gap-2 animate-fade-in-up">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" action="" onsubmit="validateForm(event)" class="space-y-6">
                        <input type="hidden" name="next" value="<?php echo isset($_GET['next']) ? htmlspecialchars($_GET['next']) : ''; ?>">
                        <!-- Email Input -->
                        <div class="animate-fade-in-up delay-100">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-envelope mr-2 text-primary"></i>Email Address
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="you@example.com"
                                   required>
                            <p id="email-error" class="hidden text-red-500 text-sm mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i>Please enter a valid email address
                            </p>
                        </div>

                        <!-- Password Input -->
                        <div class="animate-fade-in-up delay-200">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-lock mr-2 text-primary"></i>Password
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent pr-12"
                                       placeholder="Enter your password"
                                       required>
                                <button type="button" 
                                        onclick="togglePassword()" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary transition-colors">
                                    <i id="eye-icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="password-error" class="hidden text-red-500 text-sm mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i>Password must be at least 6 characters
                            </p>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between animate-fade-in-up delay-300">
                            <label class="flex items-center cursor-pointer group">
                                <input type="checkbox" 
                                       name="remember" 
                                       class="custom-checkbox w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2 cursor-pointer">
                                <span class="ml-2 text-sm text-gray-600 group-hover:text-primary transition-colors">Remember me</span>
                            </label>
                            <button type="button" onclick="openForgot()" class="text-sm text-accent hover:text-accent-dark transition-colors font-medium">
                                Forgot Password?
                            </button>
                        </div>

                        <!-- Login Button -->
                        <button type="submit" 
                                name="login"
                                class="btn-primary w-full bg-primary hover:bg-primary-dark text-white font-medium py-3 rounded-lg transition-all animate-fade-in-up delay-400">
                            <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                        </button>

                        <!-- Divider -->
                        <div class="relative my-6 animate-fade-in-up delay-500">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-4 bg-white text-gray-500">Or continue with</span>
                            </div>
                        </div>

                        <!-- Social Login Buttons -->
                        <div class="grid grid-cols-2 gap-3 sm:gap-4 animate-fade-in-up delay-500">
                            <button type="button" 
                                    class="flex items-center justify-center gap-2 px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all hover:shadow-md">
                                <i class="fab fa-google text-red-500"></i>
                                <span class="text-sm font-medium text-gray-700">Google</span>
                            </button>
                            <button type="button" 
                                    class="flex items-center justify-center gap-2 px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all hover:shadow-md">
                                <i class="fab fa-facebook text-blue-600"></i>
                                <span class="text-sm font-medium text-gray-700">Facebook</span>
                            </button>
                        </div>
                    </form>

                    <!-- Sign Up Link -->
                    <div class="mt-8 text-center animate-fade-in-up delay-500">
                        <p class="text-gray-600">
                            Don't have an account? 
                            <a href="registration" class="text-accent hover:text-accent-dark font-medium transition-colors">
                                Sign up now
                            </a>
                        </p>
                    </div>

                    <!-- Contact Info -->
                    <div class="mt-8 pt-6 border-t border-gray-200 text-center animate-fade-in-up delay-500">
                        <p class="text-sm text-gray-500 mb-2">Need help?</p>
                        <div class="flex justify-center gap-4 text-sm">
                            <a href="tel:0919-230-8344" class="text-primary hover:text-accent transition-colors">
                                <i class="fas fa-phone mr-1"></i>0919-230-8344
                            </a>
                            <a href="mailto:riatriumfo06@gmail.com" class="text-primary hover:text-accent transition-colors">
                                <i class="fas fa-envelope mr-1"></i>Email Us
                            </a>
                        </div>
                    </div>
                </div>

                <!-- MOBILE FORGOT CONTENT (hidden by default, mobile only) -->
                <div id="mobile-forgot" class="md:hidden hidden max-w-md mx-auto w-full animate-fade-in-right">
                    <div class="text-center mb-6">
                        <button class="inline-flex items-center text-sm text-primary hover:text-primary-dark underline" onclick="closeForgot()">
                            <i class="fas fa-arrow-left mr-2"></i>Back to sign in
                        </button>
                    </div>
                    <h2 class="text-2xl font-bold mb-2 text-primary">Forgot Password</h2>
                    <p class="text-gray-600 mb-4">We’ll send a 6-character code to your email.</p>
                    <div id="m-fp-alert" class="hidden mb-3 p-3 rounded-md text-sm"></div>
                    <!-- Step 1: Email -->
                    <div id="m-fp-step-email" class="space-y-3">
                        <label class="block text-sm text-gray-700">Email</label>
                        <input id="m-fp-email" type="email" class="w-full px-4 py-3 border rounded-lg" placeholder="you@example.com">
                        <button id="m-fp-send-btn" class="w-full bg-primary text-white rounded-lg py-3" onclick="sendOtp(true)">Send OTP</button>
                    </div>
                    <!-- Step 2: OTP -->
                    <div id="m-fp-step-otp" class="space-y-3 hidden mt-2">
                        <label class="block text-sm text-gray-700">Enter OTP</label>
                        <input id="m-fp-code" type="text" maxlength="6" class="w-full px-4 py-3 border rounded-lg tracking-widest text-center uppercase font-mono" placeholder="XXXXXX">
                        <div class="flex gap-2">
                            <button class="flex-1 bg-primary text-white rounded-lg py-3" onclick="verifyOtp(true)">Verify</button>
                            <button class="flex-1 bg-gray-100 rounded-lg py-3" onclick="resendOtp(true)">Resend</button>
                        </div>
                    </div>
                    <!-- Step 3: Reset Password -->
                    <div id="m-fp-step-reset" class="space-y-3 hidden mt-2">
                        <label class="block text-sm text-gray-700">New Password</label>
                        <div class="relative">
                            <input id="m-fp-pass" type="password" class="w-full px-4 py-3 border rounded-lg pr-12">
                            <button type="button" onclick="toggleVisibility('m-fp-pass','m-fp-pass-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary">
                                <i id="m-fp-pass-eye" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <label class="block text-sm text-gray-700">Confirm Password</label>
                        <div class="relative">
                            <input id="m-fp-confirm" type="password" class="w-full px-4 py-3 border rounded-lg pr-12">
                            <button type="button" onclick="toggleVisibility('m-fp-confirm','m-fp-confirm-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary">
                                <i id="m-fp-confirm-eye" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">Must be at least 8 characters and include 1 uppercase, 1 lowercase, 1 number, and 1 special character.</p>
                        <button class="w-full bg-primary text-white rounded-lg py-3" onclick="resetPassword(true)">Update Password</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-600 text-sm animate-fade-in-up delay-500">
            <p>&copy; <?php echo date('Y'); ?> Sandok ni Binggay. All rights reserved.</p>
            <p class="mt-2">
                <a href="#" class="hover:text-primary transition-colors">Privacy Policy</a>
                <span class="mx-2">•</span>
                <a href="#" class="hover:text-primary transition-colors">Terms of Service</a>
            </p>
        </div>
    </div>
    
    <!-- Mobile inline forgot password replaces login content; modal removed -->
<script>
    // Forgot Password Flow
    let fpEmail = '';

    function isMobile() { return window.matchMedia('(max-width: 767px)').matches; }

    function openForgot() {
        resetForgotUI();
        if (isMobile()) {
            // Swap views on mobile: show forgot, hide login
            const loginWrap = document.getElementById('login-content');
            const forgotWrap = document.getElementById('mobile-forgot');
            if (loginWrap) loginWrap.classList.add('hidden');
            if (forgotWrap) forgotWrap.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            document.getElementById('auth-card').classList.add('forgot-active');
        }
        // Prefill forgot email from login email if available
        const loginEmailEl = document.getElementById('email');
        const loginEmail = loginEmailEl && loginEmailEl.value ? loginEmailEl.value.trim() : '';
        if (loginEmail) {
            const fpEmailEl = document.getElementById('fp-email');
            if (fpEmailEl) fpEmailEl.value = loginEmail;
            const mfpEmailEl = document.getElementById('m-fp-email');
            if (mfpEmailEl) mfpEmailEl.value = loginEmail;
        }
    }
    function closeForgot() {
        if (isMobile()) {
            // Swap views back on mobile: show login, hide forgot
            const loginWrap = document.getElementById('login-content');
            const forgotWrap = document.getElementById('mobile-forgot');
            if (forgotWrap) forgotWrap.classList.add('hidden');
            if (loginWrap) loginWrap.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            document.getElementById('auth-card').classList.remove('forgot-active');
        }
        resetForgotUI();
    }

    function resetForgotUI() {
        // Desktop fields
        const alertD = document.getElementById('fp-alert');
        alertD.className = 'hidden mb-4 p-3 rounded-md text-sm';
        alertD.textContent = '';
        document.getElementById('fp-email').value = '';
        document.getElementById('fp-code').value = '';
        document.getElementById('fp-pass').value = '';
        document.getElementById('fp-confirm').value = '';
        showStep('email', false);

        // Mobile fields
        const alertM = document.getElementById('m-fp-alert');
        alertM.className = 'hidden mb-3 p-3 rounded-md text-sm';
        alertM.textContent = '';
        document.getElementById('m-fp-email').value = '';
        document.getElementById('m-fp-code').value = '';
        document.getElementById('m-fp-pass').value = '';
        document.getElementById('m-fp-confirm').value = '';
        showStep('email', true);
        fpEmail = '';
    }

    function showAlert(type, msg, mobile = false) {
        const el = document.getElementById(mobile ? 'm-fp-alert' : 'fp-alert');
        el.className = 'mb-4 p-3 rounded-md text-sm ' + (mobile ? '' : '') + (type === 'error' ? ' bg-red-50 text-red-700 border border-red-200' : (type === 'success' ? ' bg-green-50 text-green-700 border border-green-200' : ' bg-yellow-50 text-yellow-800 border border-yellow-200'));
        el.innerHTML = msg;
        el.classList.remove('hidden');
    }

    function showStep(step, mobile = false) {
        const map = mobile ? {
            email: 'm-fp-step-email', otp: 'm-fp-step-otp', reset: 'm-fp-step-reset'
        } : {
            email: 'fp-step-email', otp: 'fp-step-otp', reset: 'fp-step-reset'
        };
        Object.values(map).forEach(id => document.getElementById(id).classList.add('hidden'));
        document.getElementById(map[step]).classList.remove('hidden');
    }

    function setLoading(btn, loading) {
        if (!btn) return;
        btn.disabled = loading;
        btn.dataset.originalText = btn.dataset.originalText || btn.innerHTML;
        btn.innerHTML = loading ? '<i class="fas fa-spinner fa-spin mr-2"></i>Please wait...' : btn.dataset.originalText;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    async function sendOtp(mobile = false) {
        const emailInput = document.getElementById(mobile ? 'm-fp-email' : 'fp-email');
        const btn = document.getElementById(mobile ? 'm-fp-send-btn' : 'fp-send-btn');
        const email = (emailInput.value || '').trim();
    if (!email) { showAlert('error', 'Please enter your email address.', mobile); return; }
    if (!isValidEmail(email)) { showAlert('error', 'Please enter a valid email address.', mobile); return; }
        setLoading(btn, true);
        try {
            const fd = new FormData();
            fd.append('email', email);
            const res = await fetch('AJAX/forgot_send_otp.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                fpEmail = email;
                showAlert('success', 'OTP sent. Please check your email.', mobile);
                showStep('otp', mobile);
            } else {
                const msg = data.error || 'Failed to send OTP.';
                showAlert('error', msg, mobile);
            }
        } catch (e) {
            showAlert('error', 'Network error. Please try again.', mobile);
        } finally {
            setLoading(btn, false);
        }
    }

    async function verifyOtp(mobile = false) {
        const codeInputId = mobile ? 'm-fp-code' : 'fp-code';
        const btnId = mobile ? null : 'fp-verify-btn';
        const code = (document.getElementById(codeInputId).value || '').trim();
        if (!fpEmail) { showAlert('error', 'Please request an OTP first.', mobile); return; }
        if (!code || code.length !== 6) { showAlert('error', 'Enter the 6-character OTP.', mobile); return; }
        setLoading(btnId ? document.getElementById(btnId) : null, true);
        try {
            const fd = new FormData();
            fd.append('email', fpEmail);
            fd.append('code', code);
            const res = await fetch('AJAX/forgot_verify_otp.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                showAlert('success', 'OTP verified. You can now set a new password.', mobile);
                showStep('reset', mobile);
            } else {
                showAlert('error', data.error || 'Invalid code.', mobile);
            }
        } catch {
            showAlert('error', 'Network error. Please try again.', mobile);
        } finally {
            setLoading(btnId ? document.getElementById(btnId) : null, false);
        }
    }

    function resendOtp(mobile = false) { return sendOtp(mobile); }

    function validPassword(pw) {
        return /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(pw);
    }

    function toggleVisibility(inputId, eyeId) {
        const input = document.getElementById(inputId);
        const eye = document.getElementById(eyeId);
        if (!input || !eye) return;
        if (input.type === 'password') {
            input.type = 'text';
            eye.classList.remove('fa-eye');
            eye.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            eye.classList.remove('fa-eye-slash');
            eye.classList.add('fa-eye');
        }
    }

    async function resetPassword(mobile = false) {
        const pass = document.getElementById(mobile ? 'm-fp-pass' : 'fp-pass').value;
        const conf = document.getElementById(mobile ? 'm-fp-confirm' : 'fp-confirm').value;
        const btnId = mobile ? null : 'fp-reset-btn';
        if (!fpEmail) { showAlert('error', 'Session expired. Please request a new OTP.', mobile); return; }
        if (!validPassword(pass)) {
            showAlert('error', 'Password must be at least 8 chars and include uppercase, lowercase, number, and special character.', mobile);
            return;
        }
        if (pass !== conf) { showAlert('error', 'Passwords do not match.', mobile); return; }
        setLoading(btnId ? document.getElementById(btnId) : null, true);
        try {
            const fd = new FormData();
            fd.append('email', fpEmail);
            fd.append('password', pass);
            fd.append('confirm', conf);
            const res = await fetch('AJAX/forgot_reset_password.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                showAlert('success', 'Password updated. You can now sign in with your new password.', mobile);
                // Prefill login email and smoothly slide back
                const loginEmailEl = document.getElementById('email');
                if (loginEmailEl && fpEmail) loginEmailEl.value = fpEmail;
                setTimeout(() => {
                    closeForgot();
                    // focus the password field after slide back
                    setTimeout(() => {
                        const pw = document.getElementById('password');
                        if (pw) pw.focus();
                    }, 400);
                }, 800);
            } else {
                showAlert('error', data.error || 'Failed to update password.', mobile);
            }
        } catch {
            showAlert('error', 'Network error. Please try again.', mobile);
        } finally {
            setLoading(btnId ? document.getElementById(btnId) : null, false);
        }
    }
</script>
</body>
</html>