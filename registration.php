<?php
session_start();
require_once __DIR__ . '/classes/database.php';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Handle file upload (store relative path like existing data)
    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/profile/';
        $publicRel = '../uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($fileExtension, $allowed, true)) {
            $errors[] = 'Invalid photo type. Allowed: jpg, jpeg, png, webp';
        } else {
            $newFileName = uniqid('', true) . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                $photo = $publicRel . $newFileName; // match existing samples in DB
            }
        }
    }
    
    $errors = $errors ?? [];
    
    // Validation
    if ($firstName === '') $errors[] = 'First name is required';
    if ($lastName === '') $errors[] = 'Last name is required';
    if ($sex === '') $errors[] = 'Please select your sex';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if ($phone === '') $errors[] = 'Phone number is required';
    if ($username === '' || strlen($username) < 4) $errors[] = 'Username must be at least 4 characters';
    // Password complexity: min 8, uppercase, lowercase, number, special char
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character';
    }
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';

    if (empty($errors)) {
        try {
            $db = new database();
            $pdo = $db->opencon();

            // Check duplicates by username and email
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE user_username = ? OR user_email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (user_fn, user_ln, user_sex, user_email, user_phone, user_username, user_password, user_photo, user_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
                $user_type = 0; // 0 for customer per sample data
                $stmt->execute([$firstName, $lastName, ucfirst(strtolower($sex)), $email, $phone, $username, $hash, $photo, $user_type]);

                $_SESSION['registration_success'] = true;
                header('Location: login');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sandok ni Binggay</title>
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
        .delay-600 { animation-delay: 0.6s; }
        .delay-700 { animation-delay: 0.7s; }
        .delay-800 { animation-delay: 0.8s; }

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

        /* Photo Upload Styles */
        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--primary);
            overflow: hidden;
            position: relative;
            background: #f3f4f6;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-preview:hover {
            border-color: var(--accent);
            transform: scale(1.05);
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .photo-preview:hover .photo-overlay {
            opacity: 1;
        }

        /* Custom Select Dropdown */
        .custom-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231B4332'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        /* Progress Steps */
        .progress-step {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-step::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 1.5rem;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: -1;
        }

        .progress-step:last-child::after {
            display: none;
        }

        .step-circle {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 3px solid #e5e7eb;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .step-circle.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .step-circle.completed {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* Password Strength Meter */
        .password-strength {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
        }

        .strength-weak { width: 33%; background: #ef4444; }
        .strength-medium { width: 66%; background: #f59e0b; }
        .strength-strong { width: 100%; background: #10b981; }
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
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
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

        // Photo upload preview
        function previewPhoto(input) {
            const preview = document.getElementById('photo-preview');
            const placeholder = document.getElementById('photo-placeholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength-bar';
                strengthText.textContent = '';
                return;
            }
            
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (strength <= 1) {
                strengthBar.className = 'password-strength-bar strength-weak';
                strengthText.textContent = 'Weak';
                strengthText.className = 'text-xs text-red-600 mt-1';
            } else if (strength <= 3) {
                strengthBar.className = 'password-strength-bar strength-medium';
                strengthText.textContent = 'Medium';
                strengthText.className = 'text-xs text-yellow-600 mt-1';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
                strengthText.textContent = 'Strong';
                strengthText.className = 'text-xs text-green-600 mt-1';
            }
        }

        // Form validation
        function validateForm(event) {
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            const sex = document.getElementById('sex').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            let isValid = true;
            let errors = [];
            
            if (!firstName) errors.push('First name is required');
            if (!lastName) errors.push('Last name is required');
            if (!sex) errors.push('Please select your sex');
            if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errors.push('Valid email is required');
            if (!phone) errors.push('Phone number is required');
            if (!username || username.length < 4) errors.push('Username must be at least 4 characters');
            if (!password || password.length < 8) errors.push('Password must be at least 8 characters');
            if (password !== confirmPassword) errors.push('Passwords do not match');
            
            if (errors.length > 0) {
                event.preventDefault();
                alert(errors.join('\n'));
                return false;
            }
            
            return true;
        }

        // Add floating label effect
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.input-field');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('label')?.classList.add('text-primary');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.querySelector('label')?.classList.remove('text-primary');
                    }
                });
            });
        });
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-3 sm:p-4">


    <!-- Background Decorative Elements -->
    <div class="decorative-circle hidden md:block" style="width: 500px; height: 500px; top: -250px; right: -250px;"></div>
    <div class="decorative-circle hidden lg:block" style="width: 400px; height: 400px; bottom: -200px; left: -200px;"></div>

    <!-- Main Container -->
    <div class="w-full max-w-6xl px-2 sm:px-4">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header with Logo -->
            <div class="gradient-bg p-6 sm:p-8 text-center text-white relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute top-0 left-0 w-full h-full" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                </div>
                
                <div class="relative z-10 animate-fade-in-up">
                    <img src="images/logo.png" 
                         alt="Sandok ni Binggay" 
                         class="w-20 sm:w-24 h-20 sm:h-24 mx-auto rounded-full border-4 border-white/30 shadow-2xl object-cover mb-4">
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Join Sandok ni Binggay</h1>
                    <p class="text-white/90 text-sm sm:text-base">Create your account and start ordering delicious home-cooked meals</p>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="p-6 sm:p-8 md:p-12">
                <div class="mb-4 -mt-2">
                    <a href="user/home" class="inline-flex items-center text-sm text-primary hover:text-primary-dark underline">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Sandok ni Binggay
                    </a>
                </div>
                <?php if (isset($errors) && !empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg animate-fade-in-up">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-circle mt-1"></i>
                        <div>
                            <p class="font-medium mb-2">Please correct the following errors:</p>
                            <ul class="list-disc list-inside text-sm">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateForm(event)" class="space-y-6">
                    <!-- Photo Upload -->
                    <div class="flex justify-center animate-fade-in-up">
                        <label for="photo" class="cursor-pointer">
                            <div class="photo-preview">
                                <img id="photo-preview" src="" alt="Preview" class="hidden">
                                <div id="photo-placeholder" class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                                    <i class="fas fa-camera text-4xl mb-2"></i>
                                    <span class="text-xs">Upload Photo</span>
                                </div>
                                <div class="photo-overlay">
                                    <i class="fas fa-upload text-white text-2xl"></i>
                                </div>
                            </div>
                            <input type="file" 
                                   id="photo" 
                                   name="photo" 
                                   accept="image/*" 
                                   class="hidden"
                                   onchange="previewPhoto(this)">
                        </label>
                    </div>

                    <!-- Name Fields -->
                    <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
                        <div class="animate-fade-in-up delay-100">
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-user mr-2 text-primary"></i>First Name *
                            </label>
                            <input type="text" 
                                   id="first_name" 
                                   name="first_name" 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Juan"
                                   required>
                        </div>

                        <div class="animate-fade-in-up delay-200">
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-user mr-2 text-primary"></i>Last Name *
                            </label>
                            <input type="text" 
                                   id="last_name" 
                                   name="last_name" 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Dela Cruz"
                                   required>
                        </div>
                    </div>

                    <!-- Sex & Email -->
                    <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
                        <div class="animate-fade-in-up delay-300">
                            <label for="sex" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-venus-mars mr-2 text-primary"></i>Sex *
                            </label>
                            <select id="sex" 
                                    name="sex" 
                                    class="custom-select input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                    required>
                                <option value="">Select your sex</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Prefer not to say</option>
                            </select>
                        </div>

                        <div class="animate-fade-in-up delay-400">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-envelope mr-2 text-primary"></i>Email Address *
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="you@example.com"
                                   required>
                        </div>
                    </div>

                    <!-- Phone & Username -->
                    <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
                        <div class="animate-fade-in-up delay-500">
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-phone mr-2 text-primary"></i>Phone Number *
                            </label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="0919-123-4567"  maxlength="11"
                                   required>
                        </div>

                        <div class="animate-fade-in-up delay-600">
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-at mr-2 text-primary"></i>Username *
                            </label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Choose a username"
                                   minlength="4"
                                   required>
                        </div>
                    </div>

                    <!-- Password Fields -->
                    <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
                        <div class="animate-fade-in-up delay-700">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-lock mr-2 text-primary"></i>Password *
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent pr-12"
                                       placeholder="Create a strong password"
                                       minlength="8"
                                       pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                                       title="Must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character"
                                       oninput="checkPasswordStrength()"
                                       required>
                                <button type="button" 
                                        onclick="togglePassword('password', 'eye-icon-1')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary transition-colors">
                                    <i id="eye-icon-1" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div id="strength-bar" class="password-strength-bar"></div>
                            </div>
                            <p id="strength-text" class="text-xs mt-1"></p>
                        </div>

                        <div class="animate-fade-in-up delay-800">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2 transition-colors">
                                <i class="fas fa-lock mr-2 text-primary"></i>Confirm Password *
                            </label>
                            <div class="relative">
                    <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent pr-12"
                                       placeholder="Confirm your password"
                        minlength="8"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                        title="Must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character"
                                       required>
                                <button type="button" 
                                        onclick="togglePassword('confirm_password', 'eye-icon-2')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary transition-colors">
                                    <i id="eye-icon-2" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="animate-fade-in-up delay-800">
                        <label class="flex items-start cursor-pointer group">
                            <input type="checkbox" 
                                   name="terms" 
                                   class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2 cursor-pointer mt-0.5"
                                   required>
                            <span class="ml-3 text-sm text-gray-600 group-hover:text-primary transition-colors">
                                I agree to the <a href="#" class="text-accent hover:text-accent-dark font-medium">Terms & Conditions</a> and <a href="#" class="text-accent hover:text-accent-dark font-medium">Privacy Policy</a>
                            </span>
                        </label>
                    </div>

                    <!-- Register Button -->
                    <button type="submit" 
                            name="register"
                            class="btn-primary w-full bg-primary hover:bg-primary-dark text-white font-medium py-3 rounded-lg transition-all animate-fade-in-up delay-800">
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>

                    <!-- Divider -->
                    <div class="relative my-6 animate-fade-in-up delay-800">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 bg-white text-gray-500">Or register with</span>
                        </div>
                    </div>

                    <!-- Social Registration Buttons -->
                    <div class="grid grid-cols-2 gap-4 animate-fade-in-up delay-800">
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

                <!-- Sign In Link -->
                <div class="mt-8 text-center animate-fade-in-up delay-800">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="login" class="text-accent hover:text-accent-dark font-medium transition-colors">
                            Sign in here
                        </a>
                    </p>
                </div>

                <!-- Contact Info -->
                <div class="mt-8 pt-6 border-t border-gray-200 text-center animate-fade-in-up delay-800">
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
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-600 text-sm animate-fade-in-up delay-800">
            <p>&copy; <?php echo date('Y'); ?> Sandok ni Binggay. All rights reserved.</p>
            <p class="mt-2">
                <a href="#" class="hover:text-primary transition-colors">Privacy Policy</a>
                <span class="mx-2">•</span>
                <a href="#" class="hover:text-primary transition-colors">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>