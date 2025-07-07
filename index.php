<?php
session_start();
include 'config.php';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoanPro - Professional Loan Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --accent-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --light-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #4facfe;
            --success-color: #43e97b;
            --warning-color: #ffc107;
            --danger-color: #f5576c;
            --dark-color: #2c3e50;
            --light-color: #ffffff;
            --gray-color: #6c757d;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.15);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.2);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.1);
            
            --border-radius: 12px;
            --border-radius-lg: 20px;
            --border-radius-xl: 30px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            overflow-x: hidden;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-sm);
            padding: 1rem 0;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .navbar.scrolled {
            padding: 0.5rem 0;
            box-shadow: var(--shadow-md);
            background: rgba(255, 255, 255, 0.98) !important;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .nav-link {
            font-weight: 500;
            color: var(--dark-color) !important;
            margin: 0 0.5rem;
            padding: 0.5rem 1rem !important;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color) !important;
            transform: translateY(-2px);
        }

        .nav-link.active {
            background: var(--primary-gradient);
            color: white !important;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: var(--border-radius-xl);
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: var(--border-radius-xl);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-gradient);
            border-color: transparent;
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        /* Hero Section */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="400" cy="700" r="120" fill="url(%23a)"/><circle cx="900" cy="800" r="80" fill="url(%23a)"/></svg>') center/cover;
            opacity: 0.3;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .hero-subtitle {
            font-size: 1.4rem;
            font-weight: 400;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            max-width: 600px;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: var(--border-radius-xl);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .btn-hero-primary {
            background: white;
            color: var(--primary-color);
            border: none;
        }

        .btn-hero-primary:hover {
            background: #f8f9fa;
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .btn-hero-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,0.8);
        }

        .btn-hero-outline:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        /* Floating Elements */
        .floating-card {
            position: absolute;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.2);
            animation: float 6s ease-in-out infinite;
        }

        .floating-card-1 {
            top: 20%;
            right: 10%;
            animation-delay: 0s;
        }

        .floating-card-2 {
            bottom: 30%;
            left: 5%;
            animation-delay: 2s;
        }

        .floating-card-3 {
            top: 60%;
            right: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Section Styling */
        .section {
            padding: 6rem 0;
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: var(--gray-color);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Feature Cards */
        .feature-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2.5rem;
            height: 100%;
            transition: all 0.4s ease;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .feature-icon i {
            font-size: 2rem;
            color: white;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .feature-description {
            color: var(--gray-color);
            line-height: 1.6;
        }

        /* Service Cards */
        .service-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2.5rem;
            height: 100%;
            transition: all 0.4s ease;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .service-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .service-card:hover::after {
            opacity: 0.05;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }

        .service-content {
            position: relative;
            z-index: 2;
        }

        .service-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .service-icon i {
            font-size: 2.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .service-card:hover .service-icon {
            transform: scale(1.1);
            background: var(--primary-gradient);
        }

        .service-card:hover .service-icon i {
            color: white;
            -webkit-text-fill-color: white;
        }

        .service-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .service-description {
            color: var(--gray-color);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .service-features {
            list-style: none;
            padding: 0;
            margin-bottom: 1.5rem;
        }

        .service-features li {
            padding: 0.5rem 0;
            color: var(--gray-color);
            position: relative;
            padding-left: 1.5rem;
        }

        .service-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success-color);
            font-weight: bold;
        }

        /* Stats Section */
        .stats-section {
            background: var(--primary-gradient);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            position: relative;
            z-index: 2;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            position: relative;
            overflow: hidden;
        }

        .cta-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 4rem 3rem;
            text-align: center;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .cta-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .cta-description {
            font-size: 1.2rem;
            color: var(--gray-color);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Footer */
        .footer {
            background: var(--dark-gradient);
            color: white;
            padding: 4rem 0 2rem;
            position: relative;
        }

        .footer-brand {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-description {
            color: rgba(255,255,255,0.8);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .footer-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 0.5rem;
        }

        .footer-links a i {
            margin-right: 0.5rem;
            width: 16px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-link {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .social-link:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-3px);
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 3rem;
            padding-top: 2rem;
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: all 0.6s ease;
        }

        .slide-in-left.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.6s ease;
        }

        .slide-in-right.visible {
            opacity: 1;
            transform: translateX(0);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 3.5rem;
            }
            .section-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 992px) {
            .hero-title {
                font-size: 3rem;
            }
            .hero-subtitle {
                font-size: 1.2rem;
            }
            .section {
                padding: 4rem 0;
            }
            .floating-card {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-subtitle {
                font-size: 1.1rem;
            }
            .hero-buttons {
                justify-content: center;
            }
            .btn-hero {
                padding: 0.875rem 2rem;
                font-size: 1rem;
            }
            .section-title {
                font-size: 2rem;
            }
            .feature-card,
            .service-card {
                padding: 2rem;
            }
            .cta-card {
                padding: 3rem 2rem;
            }
            .cta-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 2rem;
            }
            .hero-subtitle {
                font-size: 1rem;
            }
            .section {
                padding: 3rem 0;
            }
            .feature-card,
            .service-card {
                padding: 1.5rem;
            }
            .stat-number {
                font-size: 2.5rem;
            }
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Scroll to top button */
        .scroll-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: var(--shadow-md);
        }

        .scroll-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-bank me-2"></i>LoanPro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-outline-primary me-2" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="register.php">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content fade-in">
                        <h1 class="hero-title">Transform Your Financial Future with LoanPro</h1>
                        <p class="hero-subtitle">Experience the next generation of loan management with our intelligent platform. Fast approvals, competitive rates, and personalized service.</p>
                        <div class="hero-buttons">
                            <a href="register.php" class="btn btn-hero btn-hero-primary">
                                <i class="bi bi-rocket-takeoff me-2"></i>Start Your Journey
                            </a>
                            <a href="#services" class="btn btn-hero btn-hero-outline">
                                <i class="bi bi-play-circle me-2"></i>Explore Services
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <!-- Floating Cards -->
                        <div class="floating-card floating-card-1">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Instant Approval</div>
                                    <small class="opacity-75">Get approved in minutes</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="floating-card floating-card-2">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-shield-check text-primary fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Secure & Safe</div>
                                    <small class="opacity-75">Bank-level security</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="floating-card floating-card-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-graph-up-arrow text-success fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Best Rates</div>
                                    <small class="opacity-75">Competitive interest rates</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number" data-count="50000">0</div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number" data-count="1000000000">₱0</div>
                        <div class="stat-label">Loans Disbursed</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number" data-count="99">0%</div>
                        <div class="stat-label">Approval Rate</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number" data-count="24">0/7</div>
                        <div class="stat-label">Customer Support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title fade-in">Why Choose LoanPro?</h2>
                <p class="section-subtitle fade-in">We're revolutionizing the lending industry with cutting-edge technology and customer-first approach</p>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                        <h4 class="feature-title">Lightning Fast</h4>
                        <p class="feature-description">Get loan approvals in under 5 minutes with our AI-powered assessment system. No more waiting days for decisions.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h4 class="feature-title">Bank-Level Security</h4>
                        <p class="feature-description">Your data is protected with 256-bit encryption and multi-factor authentication. We never compromise on security.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h4 class="feature-title">Competitive Rates</h4>
                        <p class="feature-description">Enjoy some of the lowest interest rates in the market with flexible repayment terms tailored to your needs.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h4 class="feature-title">24/7 Support</h4>
                        <p class="feature-description">Our dedicated support team is available round the clock to assist you with any queries or concerns.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h4 class="feature-title">Minimal Documentation</h4>
                        <p class="feature-description">Simple application process with minimal paperwork. Upload documents digitally and track your application in real-time.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h4 class="feature-title">Instant Disbursement</h4>
                        <p class="feature-description">Once approved, funds are transferred directly to your account within hours. No delays, no hassles.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title fade-in">Our Loan Services</h2>
                <p class="section-subtitle fade-in">Comprehensive financial solutions designed to meet your unique needs and goals</p>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card slide-in-left">
                        <div class="service-content">
                            <div class="service-icon">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <h4 class="service-title">Personal Loans</h4>
                            <p class="service-description">Flexible personal loans for all your needs - from medical emergencies to dream vacations.</p>
                            <ul class="service-features">
                                <li>Loan amounts up to ₱2,000,000</li>
                                <li>Interest rates starting from 8%</li>
                                <li>Flexible tenure up to 5 years</li>
                                <li>No collateral required</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary">Apply Now</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card fade-in">
                        <div class="service-content">
                            <div class="service-icon">
                                <i class="bi bi-briefcase"></i>
                            </div>
                            <h4 class="service-title">Business Loans</h4>
                            <p class="service-description">Fuel your business growth with our tailored business financing solutions.</p>
                            <ul class="service-features">
                                <li>Loan amounts up to ₱10,000,000</li>
                                <li>Competitive interest rates</li>
                                <li>Quick approval process</li>
                                <li>Flexible repayment options</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary">Apply Now</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card slide-in-right">
                        <div class="service-content">
                            <div class="service-icon">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                            <h4 class="service-title">Education Loans</h4>
                            <p class="service-description">Invest in your future with our education loans at attractive interest rates.</p>
                            <ul class="service-features">
                                <li>Cover 100% of education costs</li>
                                <li>Low interest rates from 6%</li>
                                <li>Moratorium period available</li>
                                <li>Tax benefits under Section 80E</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary">Apply Now</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card slide-in-left">
                        <div class="service-content">
                            <div class="service-icon">
                                <i class="bi bi-house"></i>
                            </div>
                            <h4 class="service-title">Home Loans</h4>
                            <p class="service-description">Make your dream home a reality with our affordable home loan options.</p>
                            <ul class="service-features">
                                <li>Up to 90% of property value</li>
                                <li>Tenure up to 30 years</li>
                                <li>Attractive interest rates</li>
                                <li>Minimal processing fees</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary">Apply Now</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card fade-in">
                        <div class="service-content">
                            <div class="service-icon">
                                <i class="bi bi-car-front"></i>
                            </div>
                            <h4 class="service-title">Vehicle Loans</h4>
                            <p class="service-description">Drive your dream car or bike with our easy vehicle financing options.</p>
                            <ul class="service-features">
                                <li>New and used vehicle financing</li>
                                <li>Up to 100% on-road price</li>
                                <li>Quick approval in 24 hours</li>
                                <li>Flexible EMI options</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary">Apply Now</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-card slide-in-right">
                        <div class="service-content">
                            <div class="service-icon">
                                <i class="bi bi-lightning-charge"></i>
                            </div>
                            <h4 class="service-title">Instant Loans</h4>
                            <p class="service-description">Get instant cash for emergencies with our quick loan approval system.</p>
                            <ul class="service-features">
                                <li>Approval in 5 minutes</li>
                                <li>Loan amounts up to ₱500,000</li>
                                <li>Minimal documentation</li>
                                <li>Digital application process</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary">Apply Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="cta-card fade-in">
                        <h2 class="cta-title">Ready to Get Started?</h2>
                        <p class="cta-description">Join thousands of satisfied customers who have transformed their financial lives with LoanPro. Apply now and get instant approval!</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <a href="register.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-rocket-takeoff me-2"></i>Apply for Loan
                            </a>
                            <a href="login.php" class="btn btn-outline-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login to Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="footer-brand">LoanPro</div>
                    <p class="footer-description">
                        Empowering financial dreams with innovative lending solutions. Your trusted partner for all loan requirements with competitive rates and exceptional service.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="social-link">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" class="social-link">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="social-link">
                            <i class="bi bi-linkedin"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home"><i class="bi bi-house"></i>Home</a></li>
                        <li><a href="#about"><i class="bi bi-info-circle"></i>About Us</a></li>
                        <li><a href="#services"><i class="bi bi-briefcase"></i>Services</a></li>
                        <li><a href="login.php"><i class="bi bi-box-arrow-in-right"></i>Login</a></li>
                        <li><a href="register.php"><i class="bi bi-person-plus"></i>Register</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-title">Loan Services</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="bi bi-person-circle"></i>Personal Loans</a></li>
                        <li><a href="#"><i class="bi bi-briefcase"></i>Business Loans</a></li>
                        <li><a href="#"><i class="bi bi-house"></i>Home Loans</a></li>
                        <li><a href="#"><i class="bi bi-car-front"></i>Vehicle Loans</a></li>
                        <li><a href="#"><i class="bi bi-mortarboard"></i>Education Loans</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-title">Contact Info</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="bi bi-geo-alt"></i>123 Finance Street, Manila</a></li>
                        <li><a href="tel:+639123456789"><i class="bi bi-telephone"></i>+63 912 345 6789</a></li>
                        <li><a href="mailto:info@loanpro.com"><i class="bi bi-envelope"></i>info@loanpro.com</a></li>
                        <li><a href="#"><i class="bi bi-clock"></i>Mon-Fri: 8AM - 6PM</a></li>
                        <li><a href="#"><i class="bi bi-headset"></i>24/7 Support Available</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> LoanPro. All rights reserved. | Privacy Policy | Terms of Service | Sitemap</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="bi bi-arrow-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Loading overlay
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.opacity = '0';
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 500);
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            const scrollToTop = document.getElementById('scrollToTop');
            
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
                scrollToTop.classList.add('visible');
            } else {
                navbar.classList.remove('scrolled');
                scrollToTop.classList.remove('visible');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    const offsetTop = targetElement.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Scroll to top functionality
        document.getElementById('scrollToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right').forEach(element => {
            observer.observe(element);
        });

        // Counter animation for stats
        function animateCounter(element, target, duration = 2000) {
            const start = 0;
            const increment = target / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                if (element.dataset.count.includes('₱')) {
                    element.textContent = '₱' + Math.floor(current).toLocaleString();
                } else if (element.dataset.count.includes('%')) {
                    element.textContent = Math.floor(current) + '%';
                } else if (element.dataset.count.includes('/')) {
                    element.textContent = Math.floor(current) + '/7';
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 16);
        }

        // Animate counters when stats section is visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counters = entry.target.querySelectorAll('[data-count]');
                    counters.forEach(counter => {
                        const target = parseInt(counter.dataset.count.replace(/[^\d]/g, ''));
                        animateCounter(counter, target);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }

        // Active nav link highlighting
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                const sectionHeight = section.clientHeight;
                if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });

        // Form validation and enhancement
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.textContent;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                    
                    // Re-enable after 5 seconds as fallback
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 5000);
                }
            });
        });

        // Parallax effect for hero section
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.hero-section');
            if (parallax) {
                const speed = scrolled * 0.5;
                parallax.style.transform = `translateY(${speed}px)`;
            }
        });

        // Add hover effects to cards
        document.querySelectorAll('.feature-card, .service-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Preload critical images
        function preloadImages() {
            const imageUrls = [
                // Add any critical images here
            ];
            
            imageUrls.forEach(url => {
                const img = new Image();
                img.src = url;
            });
        }

        // Initialize preloading
        preloadImages();

        // Add loading states to buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href && this.href.includes('#')) {
                    return; // Skip for anchor links
                }
                
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                this.disabled = true;
                
                // Reset after 3 seconds if no navigation occurs
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 3000);
            });
        });

        // Enhanced mobile menu
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', function() {
                this.classList.toggle('active');
            });
            
            // Close mobile menu when clicking on a link
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        navbarCollapse.classList.remove('show');
                        navbarToggler.classList.remove('active');
                    }
                });
            });
        }

        // Add performance monitoring
        if ('performance' in window) {
            window.addEventListener('load', function() {
                setTimeout(() => {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    console.log('Page load time:', perfData.loadEventEnd - perfData.loadEventStart, 'ms');
                }, 0);
            });
        }
    </script>
</body>
</html>
