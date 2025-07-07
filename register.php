<?php
session_start();
include 'config.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

function generateOTP($length = 6) {
    return substr(str_shuffle('0123456789'), 0, $length);
}

function validatePassword($password) {
    // Check minimum 8 characters
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    
    // Check for at least one special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must contain at least one special character.";
    }
    
    return true; // Password is valid
}

$show_otp_form = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_otp'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $password = $_POST['password'];
    $role = 'client'; // Set default role to client for all registrations

    // Server-side password validation
    $passwordValidation = validatePassword($password);
    if ($passwordValidation !== true) {
        $error = $passwordValidation;
    } else {
        $check_query = "SELECT * FROM users WHERE email='$email'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $otp = generateOTP();
            $_SESSION['otp'] = $otp;
            $_SESSION['reg_data'] = [
                'username' => $username,
                'email' => $email,
                'contact_number' => $contact_number,
                'password' => $hashed_password,
                'role' => $role
            ];

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'jmscn12@gmail.com';
                $mail->Password = 'kutbhmhorqmoqlpc';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('your_email@gmail.com', 'JBNB Loan System');
                $mail->addAddress($email);
                $mail->Subject = "Your JBNB Loan Verification Code";
                $mail->isHTML(true);
                $mail->Body = "
                    <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; border-radius: 20px 20px 0 0;'>
                            <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; display: inline-block; margin-bottom: 20px;'>
                                <h1 style='color: white; margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;'>JBNB LOAN</h1>
                                <p style='color: rgba(255,255,255,0.9); margin: 5px 0 0 0; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 2px;'>Management System</p>
                            </div>
                        </div>
                        <div style='padding: 40px 30px; background: #ffffff;'>
                            <div style='text-align: center; margin-bottom: 30px;'>
                                <h2 style='color: #2d3748; margin-bottom: 10px; font-size: 28px; font-weight: 600;'>Verify Your Account</h2>
                                <p style='color: #718096; margin: 0; font-size: 16px; line-height: 1.5;'>Thank you for joining JBNB Loan Management System. Please use the verification code below to complete your account setup.</p>
                            </div>
                            <div style='background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border: 2px dashed #cbd5e0; padding: 30px; border-radius: 15px; text-align: center; margin: 30px 0;'>
                                <div style='font-size: 36px; font-weight: 800; color: #667eea; letter-spacing: 8px; font-family: \"Courier New\", monospace;'>$otp</div>
                                <p style='color: #718096; margin: 15px 0 0 0; font-size: 14px;'>Enter this code in the verification form</p>
                            </div>
                            <div style='background: #fff5f5; border-left: 4px solid #fed7d7; padding: 15px 20px; border-radius: 8px; margin: 20px 0;'>
                                <p style='color: #c53030; margin: 0; font-size: 14px; font-weight: 500;'>⏰ This code will expire in 10 minutes</p>
                            </div>
                            <p style='color: #718096; font-size: 14px; line-height: 1.6; margin: 0;'>If you didn't request this verification, please ignore this email. Your account will not be created without verification.</p>
                        </div>
                        <div style='background: #f7fafc; padding: 25px 30px; text-align: center; border-radius: 0 0 20px 20px;'>
                            <p style='color: #718096; font-size: 12px; margin: 0;'>© " . date('Y') . " JBNB Loan Management System. All rights reserved.</p>
                            <p style='color: #a0aec0; font-size: 11px; margin: 5px 0 0 0;'>This is an automated message, please do not reply.</p>
                        </div>
                    </div>
                ";

                $mail->send();
                $show_otp_form = true;
            } catch (Exception $e) {
                $error = "Failed to send OTP. Mailer Error: " . $mail->ErrorInfo;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify'])) {
    $enteredOtp = $_POST['otp'];

    if ($enteredOtp == $_SESSION['otp']) {
        $userData = $_SESSION['reg_data'];
        $stmt = $conn->prepare("INSERT INTO users (username, email, contact_number, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $userData['username'], $userData['email'], $userData['contact_number'], $userData['password'], $userData['role']);

        if ($stmt->execute()) {
            unset($_SESSION['otp']);
            unset($_SESSION['reg_data']);
            header("Location: login.php?success=1");
            exit();
        } else {
            $error = "Failed to register. Please try again.";
            $show_otp_form = true;
        }
    } else {
        $error = "Invalid OTP. Please try again.";
        $show_otp_form = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - JBNB Loan Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #718096;
            --success-color: #48bb78;
            --danger-color: #f56565;
            --warning-color: #ed8936;
            --dark-color: #2d3748;
            --light-color: #f7fafc;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f7fafc;
            --gray-200: #edf2f7;
            --gray-300: #e2e8f0;
            --gray-400: #cbd5e0;
            --gray-500: #a0aec0;
            --gray-600: #718096;
            --gray-700: #4a5568;
            --gray-800: #2d3748;
            --gray-900: #1a202c;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 119, 198, 0.2) 0%, transparent 50%);
            z-index: 0;
        }

        .register-container {
            width: 100%;
            max-width: 1400px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4rem;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 1024px) {
            .register-container {
                flex-direction: column;
                gap: 2rem;
                max-width: 600px;
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
            animation: slideInLeft 0.8s ease-out;
        }

        @media (max-width: 1024px) {
            .brand-container {
                padding: 1rem;
            }
        }

        .brand-logo-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 2rem;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .brand-logo-container:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: var(--shadow-2xl);
        }

        .brand-logo {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -2px;
        }

        .brand-tagline {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 2rem;
        }

        .brand-description {
            font-size: 1.125rem;
            opacity: 0.85;
            max-width: 450px;
            line-height: 1.7;
            margin-bottom: 2.5rem;
        }

        .brand-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            max-width: 400px;
            width: 100%;
        }

        @media (max-width: 768px) {
            .brand-features {
                grid-template-columns: 1fr;
                max-width: 300px;
            }
        }

        .feature-item {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .feature-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .feature-item i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            display: block;
            opacity: 0.9;
        }

        .feature-item span {
            font-size: 0.875rem;
            font-weight: 600;
            opacity: 0.95;
        }

        .register-form-container {
            flex: 1;
            max-width: 550px;
            width: 100%;
            animation: slideInRight 0.8s ease-out;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            box-shadow: var(--shadow-2xl);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 35px 70px -12px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--gray-200);
            padding: 2.5rem 2.5rem 1.5rem;
            text-align: center;
        }

        .card-header h2 {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
            letter-spacing: -1px;
        }

        .card-header p {
            color: var(--gray-600);
            font-size: 1.125rem;
            margin-bottom: 0;
            font-weight: 400;
        }

        .card-body {
            padding: 2rem 2.5rem;
        }

        .progress-container {
            margin-bottom: 2.5rem;
        }

        .progress {
            height: 0.75rem;
            border-radius: 1rem;
            background-color: var(--gray-200);
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            background: var(--primary-gradient);
            border-radius: 1rem;
            transition: width 0.6s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            color: var(--gray-500);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .step-indicator .active {
            color: var(--primary-color);
        }

        .user-type-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .user-type-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .user-type-info:hover::before {
            transform: translateX(100%);
        }

        .user-type-info .badge {
            background: var(--primary-gradient);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: var(--shadow-md);
        }

        .user-type-info p {
            color: var(--gray-600);
            margin: 0;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .form-floating {
            margin-bottom: 1.75rem;
            position: relative;
        }

        .form-floating > .form-control {
            height: 4rem;
            padding: 1.5rem 1rem 0.5rem;
            border: 2px solid var(--gray-300);
            border-radius: 1rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: var(--gray-50);
        }

        .form-floating > .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
            background-color: white;
            transform: translateY(-2px);
        }

        .form-floating > label {
            color: var(--gray-500);
            font-weight: 600;
            font-size: 0.875rem;
            padding: 1rem;
        }

        .input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            z-index: 10;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
            background-color: var(--gray-100);
        }

        .password-strength-meter {
            height: 0.75rem;
            border-radius: 1rem;
            margin-top: 1rem;
            background-color: var(--gray-200);
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .password-strength-meter-bar {
            height: 100%;
            border-radius: 1rem;
            transition: all 0.4s ease;
            background: linear-gradient(135deg, var(--danger-color), var(--warning-color));
            position: relative;
            overflow: hidden;
        }

        .password-strength-meter-bar.strong {
            background: var(--success-gradient);
        }

        .password-strength-meter-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }

        .password-strength-text {
            font-size: 0.8rem;
            margin-top: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

        .password-requirements {
            font-size: 0.8rem;
            margin-top: 1rem;
            color: var(--gray-500);
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 0;
            list-style: none;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        @media (max-width: 576px) {
            .password-requirements ul {
                grid-template-columns: 1fr;
            }
        }

        .password-requirements li {
            position: relative;
            padding-left: 2rem;
            transition: all 0.3s ease;
            padding: 0.5rem 0 0.5rem 2rem;
            border-radius: 0.5rem;
        }

        .password-requirements li::before {
            content: '✗';
            position: absolute;
            left: 0.5rem;
            color: var(--danger-color);
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .password-requirements li.valid {
            color: var(--success-color);
            background-color: rgba(72, 187, 120, 0.1);
        }

        .password-requirements li.valid::before {
            content: '✓';
            color: var(--success-color);
        }

        .form-check {
            margin-bottom: 2rem;
        }

        .form-check-input {
            width: 1.5rem;
            height: 1.5rem;
            border: 2px solid var(--gray-400);
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='white'%3e%3cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/%3e%3c/svg%3e");
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        .form-check-label {
            font-size: 0.95rem;
            color: var(--gray-700);
            margin-left: 0.75rem;
            font-weight: 500;
        }

        .form-check-label a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .form-check-label a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 1rem;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }

        .btn-success {
            background: var(--success-gradient);
            border: none;
            border-radius: 1rem;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-lg);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }

        .card-footer {
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            padding: 2rem 2.5rem;
            text-align: center;
        }

        .card-footer p {
            margin-bottom: 0;
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 500;
        }

        .card-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .card-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .alert {
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            border: none;
            font-weight: 600;
            box-shadow: var(--shadow-md);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(245, 101, 101, 0.1) 0%, rgba(245, 101, 101, 0.05) 100%);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.1) 0%, rgba(72, 187, 120, 0.05) 100%);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .otp-container {
            text-align: center;
        }

        .otp-container p {
            margin-bottom: 2rem;
            color: var(--gray-600);
            line-height: 1.7;
            font-size: 1.1rem;
        }

        .otp-container .form-floating > .form-control {
            text-align: center;
            letter-spacing: 1rem;
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            font-family: 'Courier New', monospace;
        }

        .otp-info {
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
            border: 2px solid rgba(79, 172, 254, 0.2);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .otp-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .otp-info:hover::before {
            transform: translateX(100%);
        }

        .otp-info i {
            font-size: 3rem;
            color: #4facfe;
            margin-bottom: 1rem;
            display: block;
        }

        .otp-info h6 {
            color: var(--gray-800);
            font-weight: 700;
            margin-bottom: 0.75rem;
            font-size: 1.25rem;
        }

        .otp-info p {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.6;
        }

        /* Form validation styles */
        .is-invalid {
            border-color: var(--danger-color) !important;
            animation: shake 0.5s ease-in-out;
        }

        .is-invalid:focus {
            box-shadow: 0 0 0 0.25rem rgba(245, 101, 101, 0.25) !important;
        }

        .is-valid {
            border-color: var(--success-color) !important;
        }

        .is-valid:focus {
            box-shadow: 0 0 0 0.25rem rgba(72, 187, 120, 0.25) !important;
        }

        .invalid-feedback,
        .valid-feedback {
            display: none;
            font-size: 0.875rem;
            margin-top: 0.75rem;
            font-weight: 600;
        }

        .invalid-feedback {
            color: var(--danger-color);
        }

        .valid-feedback {
            color: var(--success-color);
        }

        .is-invalid ~ .invalid-feedback {
            display: block;
        }

        .is-valid ~ .valid-feedback {
            display: block;
        }

        /* Modal styles */
        .modal-content {
            border-radius: 1.5rem;
            border: none;
            box-shadow: var(--shadow-2xl);
            overflow: hidden;
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-bottom: none;
            padding: 2rem;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
            color: var(--gray-700);
            line-height: 1.7;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 1.5rem 2rem;
        }

        /* Loading states */
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.75rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-loading .loading-spinner {
            display: inline-block;
        }

        /* Animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
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

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .brand-logo {
                font-size: 2.5rem;
            }

            .brand-tagline {
                font-size: 0.875rem;
                letter-spacing: 2px;
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

            .card-header h2 {
                font-size: 1.875rem;
            }

            .form-floating > .form-control {
                height: 3.5rem;
            }

            .btn-primary,
            .btn-success {
                padding: 0.875rem 1.5rem;
                font-size: 1rem;
            }

            .brand-features {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .register-container {
                gap: 1rem;
            }

            .brand-container {
                padding: 1rem 0;
            }

            .card-header h2 {
                font-size: 1.75rem;
            }

            .otp-container .form-floating > .form-control {
                font-size: 1.5rem;
                letter-spacing: 0.75rem;
            }
        }

        /* Hover effects for interactive elements */
        .form-control:hover {
            border-color: var(--gray-400);
        }

        .form-check-input:hover {
            border-color: var(--primary-color);
        }

        /* Focus visible for accessibility */
        .btn:focus-visible,
        .form-control:focus-visible,
        .form-check-input:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
    </style>
</head>

<body>
    <div class="register-container">
        <div class="brand-container">
            <div class="brand-logo-container">
                <div class="brand-logo">JBNB LOAN</div>
                <div class="brand-tagline">Management System</div>
            </div>
            <p class="brand-description">
                Join thousands of satisfied customers who trust JBNB Loan Management System for their financial needs. 
                Experience fast approvals, competitive rates, and exceptional service.
            </p>
            <div class="brand-features">
                <div class="feature-item">
                    <i class="bi bi-shield-check"></i>
                    <span>Bank-level Security</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-lightning-charge"></i>
                    <span>Instant Approvals</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Competitive Rates</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-headset"></i>
                    <span>24/7 Support</span>
                </div>
            </div>
        </div>
        
        <div class="register-form-container">
            <div class="register-card">
                <div class="card-header">
                    <h2><?= $show_otp_form ? 'Verify Your Account' : 'Create Your Account' ?></h2>
                    <p><?= $show_otp_form ? 'Enter the verification code sent to your email' : 'Join JBNB and start your financial journey' ?></p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="card-body pt-0">
                        <div class="alert alert-danger fade-in">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <?php if (!$show_otp_form): ?>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="step-indicator">
                            <span class="active">Account Details</span>
                            <span>Email Verification</span>
                        </div>
                    </div>
                    
                    <div class="user-type-info">
                        <div class="badge">Client Registration</div>
                        <p>You are creating a client account to access our loan services and manage your applications.</p>
                    </div>
                    
                    <form method="POST" id="registrationForm" novalidate>
                        <div class="form-floating">
                            <input type="text" id="username" name="username" class="form-control" placeholder="Username" required minlength="3" maxlength="20">
                            <label for="username">Username</label>
                            <div class="invalid-feedback" id="username-feedback">Username must be 3-20 characters and contain only letters, numbers, and underscores.</div>
                            <div class="valid-feedback">Username is available!</div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="email" id="email" name="email" class="form-control" placeholder="Email" required>
                            <label for="email">Email Address</label>
                            <div class="invalid-feedback" id="email-feedback">Please enter a valid email address.</div>
                            <div class="valid-feedback">Email format is valid!</div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="tel" id="contact_number" name="contact_number" class="form-control" placeholder="Phone" required>
                            <label for="contact_number">Phone Number</label>
                            <div class="invalid-feedback" id="contact-feedback">Please enter a valid phone number.</div>
                            <div class="valid-feedback">Phone number is valid!</div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password" required minlength="8">
                            <label for="password">Password</label>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                            <div class="invalid-feedback" id="password-feedback">Password must meet all requirements below.</div>
                            <div class="valid-feedback">Strong password!</div>
                        </div>
                        
                        <div class="password-strength-meter">
                            <div class="password-strength-meter-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="password-strength-text">
                            <span id="passwordStrengthText">Password strength</span>
                            <span id="passwordScore">0/4</span>
                        </div>
                        <div class="password-requirements">
                            <ul>
                                <li id="req-length">At least 8 characters</li>
                                <li id="req-uppercase">One uppercase letter</li>
                                <li id="req-lowercase">One lowercase letter</li>
                                <li id="req-special">One special character</li>
                            </ul>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                            </label>
                            <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                        </div>
                        
                        <button type="submit" name="send_otp" id="submitBtn" class="btn btn-primary w-100">
                            <span class="loading-spinner"></span>
                            <span class="btn-text">Continue to Verification</span>
                        </button>
                    </form>
                    
                    <?php else: ?>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="step-indicator">
                            <span>Account Details</span>
                            <span class="active">Email Verification</span>
                        </div>
                    </div>
                    
                    <div class="otp-info">
                        <i class="bi bi-envelope-check d-block"></i>
                        <h6>Verification Code Sent</h6>
                        <p>We've sent a 6-digit verification code to your email address. Please check your inbox and enter the code below.</p>
                    </div>
                    
                    <div class="otp-container">
                        <form method="POST" id="otpForm" novalidate>
                            <div class="form-floating">
                                <input type="text" id="otp" name="otp" class="form-control" maxlength="6" placeholder="000000" required pattern="[0-9]{6}" autocomplete="one-time-code">
                                <label for="otp">Verification Code</label>
                                <div class="invalid-feedback">Please enter the 6-digit verification code.</div>
                                <div class="valid-feedback">Code format is correct!</div>
                            </div>
                            
                            <button type="submit" name="verify" class="btn btn-success w-100">
                                <span class="loading-spinner"></span>
                                <span class="btn-text">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Verify & Create Account
                                </span>
                            </button>
                        </form>
                        
                        <div class="mt-4">
                            <p class="text-muted small">
                                Didn't receive the code? 
                                <a href="#" onclick="resendOTP()" class="text-primary">Resend Code</a>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">
                        <i class="bi bi-file-text me-2"></i>
                        Terms of Service
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By creating an account with JBNB Loan Management System, you agree to be bound by these Terms of Service and all applicable laws and regulations.</p>
                    
                    <h6>2. Loan Services</h6>
                    <p>JBNB provides loan management services including application processing, approval workflows, and payment tracking. All loan terms are subject to approval and may vary based on creditworthiness.</p>
                    
                    <h6>3. User Responsibilities</h6>
                    <p>Users are responsible for providing accurate information, maintaining account security, and making timely payments according to agreed schedules.</p>
                    
                    <h6>4. Interest Rates and Fees</h6>
                    <p>Interest rates and fees will be clearly disclosed before loan approval. Rates may vary based on loan type, amount, and borrower qualifications.</p>
                    
                    <h6>5. Privacy and Data Protection</h6>
                    <p>We are committed to protecting your personal information in accordance with our Privacy Policy and applicable data protection laws.</p>
                    
                    <h6>6. Modification of Terms</h6>
                    <p>JBNB reserves the right to modify these terms at any time. Users will be notified of significant changes via email or platform notifications.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="acceptTerms()">Accept Terms</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="privacyModalLabel">
                        <i class="bi bi-shield-check me-2"></i>
                        Privacy Policy
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Information We Collect</h6>
                    <p>We collect information you provide directly, such as when you create an account, apply for a loan, or contact customer support.</p>
                    
                    <h6>How We Use Your Information</h6>
                    <p>Your information is used to process loan applications, verify identity, assess creditworthiness, and provide customer support.</p>
                    
                    <h6>Information Sharing</h6>
                    <p>We do not sell your personal information. We may share information with service providers, credit bureaus, and as required by law.</p>
                    
                    <h6>Data Security</h6>
                    <p>We implement industry-standard security measures to protect your personal information, including encryption and secure data storage.</p>
                    
                    <h6>Your Rights</h6>
                    <p>You have the right to access, update, or delete your personal information. Contact us to exercise these rights.</p>
                    
                    <h6>Contact Information</h6>
                    <p>For privacy-related questions, contact us at privacy@jbnbloan.com or through our customer support channels.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation for registration form
            const registrationForm = document.getElementById('registrationForm');
            if (registrationForm) {
                // Input elements
                const usernameInput = document.getElementById('username');
                const emailInput = document.getElementById('email');
                const contactInput = document.getElementById('contact_number');
                const passwordInput = document.getElementById('password');
                const termsCheckbox = document.getElementById('agreeTerms');
                const submitBtn = document.getElementById('submitBtn');

                // Add event listeners
                usernameInput.addEventListener('input', validateUsername);
                emailInput.addEventListener('input', validateEmail);
                contactInput.addEventListener('input', validateContact);
                passwordInput.addEventListener('input', function() {
                    validatePassword();
                    updatePasswordStrength();
                    updatePasswordRequirements();
                });
                termsCheckbox.addEventListener('change', validateTerms);

                // Password visibility toggle
                const togglePassword = document.getElementById('togglePassword');
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('bi-eye');
                    this.querySelector('i').classList.toggle('bi-eye-slash');
                });

                // Form submission
                registrationForm.addEventListener('submit', function(event) {
                    let isValid = true;
                    
                    if (!validateUsername()) isValid = false;
                    if (!validateEmail()) isValid = false;
                    if (!validateContact()) isValid = false;
                    if (!validatePassword()) isValid = false;
                    if (!validateTerms()) isValid = false;
                    
                    if (!isValid) {
                        event.preventDefault();
                        return;
                    }

                    // Show loading state
                    submitBtn.classList.add('btn-loading');
                    submitBtn.disabled = true;
                    submitBtn.querySelector('.btn-text').textContent = 'Sending verification code...';
                });

                // Real-time form validation
                function validateUsername() {
                    const username = usernameInput.value.trim();
                    const regex = /^[a-zA-Z0-9_]{3,20}$/;
                    
                    if (!regex.test(username)) {
                        usernameInput.classList.add('is-invalid');
                        usernameInput.classList.remove('is-valid');
                        return false;
                    } else {
                        usernameInput.classList.remove('is-invalid');
                        usernameInput.classList.add('is-valid');
                        return true;
                    }
                }
                
                function validateEmail() {
                    const email = emailInput.value.trim();
                    const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                    
                    if (!regex.test(email)) {
                        emailInput.classList.add('is-invalid');
                        emailInput.classList.remove('is-valid');
                        return false;
                    } else {
                        emailInput.classList.remove('is-invalid');
                        emailInput.classList.add('is-valid');
                        return true;
                    }
                }
                
                function validateContact() {
                    const contact = contactInput.value.trim();
                    const regex = /^(\+\d{1,3}[- ]?)?\d{10,14}$/;
                    
                    if (!regex.test(contact)) {
                        contactInput.classList.add('is-invalid');
                        contactInput.classList.remove('is-valid');
                        return false;
                    } else {
                        contactInput.classList.remove('is-invalid');
                        contactInput.classList.add('is-valid');
                        return true;
                    }
                }
                
                function validatePassword() {
                    const password = passwordInput.value;
                    const hasMinLength = password.length >= 8;
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasLowercase = /[a-z]/.test(password);
                    const hasSpecialChar = /[^A-Za-z0-9]/.test(password);
                    
                    const isValid = hasMinLength && hasUppercase && hasLowercase && hasSpecialChar;
                    
                    if (!isValid) {
                        passwordInput.classList.add('is-invalid');
                        passwordInput.classList.remove('is-valid');
                        return false;
                    } else {
                        passwordInput.classList.remove('is-invalid');
                        passwordInput.classList.add('is-valid');
                        return true;
                    }
                }
                
                function validateTerms() {
                    if (!termsCheckbox.checked) {
                        termsCheckbox.classList.add('is-invalid');
                        return false;
                    } else {
                        termsCheckbox.classList.remove('is-invalid');
                        return true;
                    }
                }
                
                function updatePasswordRequirements() {
                    const password = passwordInput.value;
                    
                    const lengthReq = document.getElementById('req-length');
                    const uppercaseReq = document.getElementById('req-uppercase');
                    const lowercaseReq = document.getElementById('req-lowercase');
                    const specialReq = document.getElementById('req-special');
                    
                    lengthReq.classList.toggle('valid', password.length >= 8);
                    uppercaseReq.classList.toggle('valid', /[A-Z]/.test(password));
                    lowercaseReq.classList.toggle('valid', /[a-z]/.test(password));
                    specialReq.classList.toggle('valid', /[^A-Za-z0-9]/.test(password));
                }
                
                function updatePasswordStrength() {
                    const password = passwordInput.value;
                    const strengthBar = document.getElementById('passwordStrengthBar');
                    const strengthText = document.getElementById('passwordStrengthText');
                    const scoreText = document.getElementById('passwordScore');
                    
                    let score = 0;
                    let strength = 0;
                    
                    if (password.length >= 8) { score++; strength += 25; }
                    if (/[A-Z]/.test(password)) { score++; strength += 25; }
                    if (/[a-z]/.test(password)) { score++; strength += 25; }
                    if (/[^A-Za-z0-9]/.test(password)) { score++; strength += 25; }
                    
                    strengthBar.style.width = strength + '%';
                    scoreText.textContent = score + '/4';
                    
                    if (score === 0 || password.length === 0) {
                        strengthBar.style.background = 'var(--gray-300)';
                        strengthText.textContent = 'Password strength';
                        strengthText.style.color = 'var(--gray-500)';
                    } else if (score === 1) {
                        strengthBar.style.background = 'var(--danger-color)';
                        strengthText.textContent = 'Very Weak';
                        strengthText.style.color = 'var(--danger-color)';
                    } else if (score === 2) {
                        strengthBar.style.background = 'var(--warning-color)';
                        strengthText.textContent = 'Weak';
                        strengthText.style.color = 'var(--warning-color)';
                    } else if (score === 3) {
                        strengthBar.style.background = 'linear-gradient(135deg, var(--warning-color), var(--success-color))';
                        strengthText.textContent = 'Good';
                        strengthText.style.color = 'var(--success-color)';
                    } else if (score === 4) {
                        strengthBar.classList.add('strong');
                        strengthText.textContent = 'Strong';
                        strengthText.style.color = 'var(--success-color)';
                    }
                }
            }
            
            // OTP form validation
            const otpForm = document.getElementById('otpForm');
            if (otpForm) {
                const otpInput = document.getElementById('otp');
                const submitBtn = otpForm.querySelector('button[type="submit"]');
                
                otpInput.addEventListener('input', function() {
                    const otp = this.value.trim();
                    const regex = /^\d{6}$/;
                    
                    if (!regex.test(otp)) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    } else {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                });
                
                otpForm.addEventListener('submit', function(event) {
                    const otp = otpInput.value.trim();
                    const regex = /^\d{6}$/;
                    
                    if (!regex.test(otp)) {
                        event.preventDefault();
                        otpInput.classList.add('is-invalid');
                        return;
                    }

                    // Show loading state
                    submitBtn.classList.add('btn-loading');
                    submitBtn.disabled = true;
                    submitBtn.querySelector('.btn-text').innerHTML = '<i class="bi bi-check-circle me-2"></i>Verifying...';
                });
            }
        });

        // Accept terms function
        function acceptTerms() {
            document.getElementById('agreeTerms').checked = true;
            document.getElementById('agreeTerms').classList.remove('is-invalid');
        }

        // Resend OTP function
        function resendOTP() {
            // This would typically make an AJAX call to resend the OTP
            alert('A new verification code has been sent to your email address.');
        }

        // Auto-focus and keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                const form = e.target.closest('form');
                const inputs = Array.from(form.querySelectorAll('input[required]'));
                const currentIndex = inputs.indexOf(e.target);
                
                if (currentIndex < inputs.length - 1) {
                    e.preventDefault();
                    inputs[currentIndex + 1].focus();
                }
            }
        });

        // Auto-format OTP input
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Auto-submit when 6 digits are entered
                if (this.value.length === 6) {
                    const form = this.closest('form');
                    if (form) {
                        setTimeout(() => {
                            form.querySelector('button[type="submit"]').click();
                        }, 500);
                    }
                }
            });
        }

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Add intersection observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.feature-item, .user-type-info, .otp-info').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>
