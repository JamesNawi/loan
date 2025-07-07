<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] == 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid email or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - LoanPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary-color: #64748b;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #0f172a;
            --light-color: #f8fafc;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="400" cy="700" r="120" fill="url(%23a)"/><circle cx="900" cy="800" r="80" fill="url(%23a)"/></svg>') center/cover;
            opacity: 0.3;
            z-index: 0;
        }

        .login-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4rem;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                gap: 2rem;
                max-width: 500px;
            }
        }

        .brand-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            padding: 2rem;
        }

        @media (max-width: 992px) {
            .brand-container {
                padding: 1rem;
            }
        }

        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .brand-logo i {
            font-size: 3rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 1rem;
            backdrop-filter: blur(10px);
        }

        .brand-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .brand-subtitle {
            font-size: 1.125rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .brand-description {
            font-size: 1rem;
            opacity: 0.8;
            max-width: 400px;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .brand-features {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 350px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .feature-item i {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .feature-item span {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .login-form-container {
            flex: 1;
            max-width: 450px;
            width: 100%;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 35px 70px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--gray-200);
            padding: 2.5rem 2.5rem 1.5rem;
            text-align: center;
        }

        .card-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .card-header p {
            color: var(--gray-600);
            font-size: 1rem;
            margin-bottom: 0;
        }

        .card-body {
            padding: 2rem 2.5rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating > .form-control {
            height: 3.5rem;
            padding: 1rem 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--gray-50);
        }

        .form-floating > .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.1);
            background-color: white;
        }

        .form-floating > label {
            color: var(--gray-500);
            font-weight: 500;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            z-index: 10;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
            background-color: var(--gray-100);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border: none;
            border-radius: 0.75rem;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            position: relative;
            overflow: hidden;
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
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .forgot-password {
            display: block;
            text-align: right;
            margin-bottom: 2rem;
            font-size: 0.875rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .card-footer {
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            padding: 1.5rem 2.5rem;
            text-align: center;
        }

        .card-footer p {
            margin-bottom: 0;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .card-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .card-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .alert {
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
            font-weight: 500;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-loading .loading-spinner {
            display: inline-block;
        }

        .social-login {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .social-login p {
            text-align: center;
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .social-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn-social {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.75rem;
            background: white;
            color: var(--gray-700);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-social:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-social i {
            font-size: 1.25rem;
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
        }

        .fade-in.delay-1 {
            animation-delay: 0.2s;
        }

        .fade-in.delay-2 {
            animation-delay: 0.4s;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .brand-title {
                font-size: 2rem;
            }

            .brand-subtitle {
                font-size: 1rem;
            }

            .card-header,
            .card-body,
            .card-footer {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }

            .card-header {
                padding-top: 2rem;
            }

            .brand-features {
                display: none;
            }

            .social-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                gap: 1rem;
            }

            .brand-container {
                padding: 1rem 0;
            }

            .card-header h2 {
                font-size: 1.75rem;
            }

            .form-floating > .form-control {
                height: 3rem;
            }

            .btn-primary {
                padding: 0.75rem 1.5rem;
            }
        }

        /* Security indicators */
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 1rem;
        }

        .security-badge i {
            font-size: 0.875rem;
        }

        /* Loading overlay for form submission */
        .form-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 1.5rem;
            z-index: 100;
        }

        .form-overlay.active {
            display: flex;
        }

        .form-overlay .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--gray-200);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="brand-container fade-in">
            <div class="brand-logo">
                <i class="bi bi-bank2"></i>
                <div>
                    <div class="brand-title">LoanPro</div>
                    <div class="brand-subtitle">Management System</div>
                </div>
            </div>
            <p class="brand-description">
                The most advanced loan management platform designed for modern financial institutions. 
                Secure, scalable, and built for the future of lending.
            </p>
            <div class="brand-features">
                <div class="feature-item">
                    <i class="bi bi-shield-check"></i>
                    <span>Bank-level Security</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-lightning-charge"></i>
                    <span>Real-time Processing</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Advanced Analytics</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-headset"></i>
                    <span>24/7 Support</span>
                </div>
            </div>
        </div>
        
        <div class="login-form-container fade-in delay-1">
            <div class="login-card">
                <div class="form-overlay" id="formOverlay">
                    <div class="spinner"></div>
                </div>
                
                <div class="card-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to access your loan management dashboard</p>
                </div>
                
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <div class="form-floating">
                            <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" required>
                            <label for="email">Email address</label>
                        </div>
                        
                        <div class="form-floating position-relative">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                            <label for="password">Password</label>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                                <label class="form-check-label" for="rememberMe">
                                    Remember me
                                </label>
                            </div>
                            <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                            <span class="loading-spinner"></span>
                            <span class="btn-text">Sign In</span>
                        </button>
                        
                        <div class="text-center">
                            <div class="security-badge">
                                <i class="bi bi-shield-check"></i>
                                <span>Secured with 256-bit SSL encryption</span>
                            </div>
                        </div>
                    </form>
                    
                    <div class="social-login">
                        <p>Or continue with</p>
                        <div class="social-buttons">
                            <a href="#" class="btn-social">
                                <i class="bi bi-google"></i>
                                <span>Google</span>
                            </a>
                            <a href="#" class="btn-social">
                                <i class="bi bi-microsoft"></i>
                                <span>Microsoft</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <p>Don't have an account? <a href="register.php">Create one now</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const formOverlay = document.getElementById('formOverlay');
            const btnText = submitBtn.querySelector('.btn-text');
            
            // Show loading state
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            btnText.textContent = 'Signing in...';
            formOverlay.classList.add('active');
            
            // Simulate processing time (remove in production)
            setTimeout(() => {
                // The form will actually submit, this is just for UX
            }, 1000);
        });

        // Input validation and styling
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
            
            input.addEventListener('input', function() {
                if (this.value) {
                    this.classList.add('has-value');
                } else {
                    this.classList.remove('has-value');
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + L to focus email input
            if (e.altKey && e.key === 'l') {
                e.preventDefault();
                document.getElementById('email').focus();
            }
            
            // Alt + P to focus password input
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });

        // Auto-focus first empty input
        window.addEventListener('load', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            if (!emailInput.value) {
                emailInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
        });

        // Form validation
        function validateForm() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isEmailValid = emailRegex.test(email);
            const isPasswordValid = password.length >= 6;
            
            if (isEmailValid && isPasswordValid) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-disabled');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-disabled');
            }
        }

        // Real-time validation
        document.getElementById('email').addEventListener('input', validateForm);
        document.getElementById('password').addEventListener('input', validateForm);

        // Initial validation
        validateForm();

        // Prevent multiple form submissions
        let isSubmitting = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
        });

        // Security features
        function detectSuspiciousActivity() {
            let loginAttempts = parseInt(localStorage.getItem('loginAttempts') || '0');
            const lastAttempt = localStorage.getItem('lastLoginAttempt');
            const now = new Date().getTime();
            
            // Reset attempts after 15 minutes
            if (lastAttempt && (now - parseInt(lastAttempt)) > 900000) {
                loginAttempts = 0;
                localStorage.removeItem('loginAttempts');
                localStorage.removeItem('lastLoginAttempt');
            }
            
            // Track failed attempts
            if (window.location.search.includes('error')) {
                loginAttempts++;
                localStorage.setItem('loginAttempts', loginAttempts.toString());
                localStorage.setItem('lastLoginAttempt', now.toString());
                
                if (loginAttempts >= 5) {
                    document.getElementById('submitBtn').disabled = true;
                    document.querySelector('.card-body').innerHTML += 
                        '<div class="alert alert-warning mt-3"><i class="bi bi-exclamation-triangle me-2"></i>Too many failed attempts. Please try again in 15 minutes.</div>';
                }
            }
        }

        detectSuspiciousActivity();

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Escape key to clear form
            if (e.key === 'Escape') {
                document.getElementById('loginForm').reset();
                document.getElementById('email').focus();
            }
        });

        // Progressive enhancement for better UX
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // Service worker registration failed, but app still works
            });
        }

        // Preload next page resources
        function preloadDashboard() {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = 'dashboard.php';
            document.head.appendChild(link);
        }

        // Preload after user starts typing
        let hasStartedTyping = false;
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                if (!hasStartedTyping) {
                    hasStartedTyping = true;
                    preloadDashboard();
                }
            });
        });
    </script>
</body>
</html>
