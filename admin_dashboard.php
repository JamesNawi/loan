<?php
session_start();

// Add this code right after the session_start() at the top of admin_dashboard.php

// Check for new payment flag
$flagFile = 'new_payment_flag.txt';
if (file_exists($flagFile)) {
    $flagTime = (int)file_get_contents($flagFile);
    $currentTime = time();
    
    // If flag is less than 5 minutes old, redirect to show payments
    if (($currentTime - $flagTime) < 300) {
        // Delete the flag file
        unlink($flagFile);
        
        // Only redirect if not already showing payments
        if (!isset($_GET['show_payments'])) {
            header("Location: admin_dashboard.php?show_payments=1");
            exit();
        }
    } else {
        // Delete old flag file
        unlink($flagFile);
    }
}

include 'config.php';

// Helper function to ensure correct payment calculation
function calculateRemainingBalance($loanAmount, $totalPaid) {
    $totalPaid = $totalPaid ?? 0;
    return $loanAmount - $totalPaid;
}

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle theme preference
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}

// Set default theme if not set
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}

// Handle Approve/Reject Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan_id'], $_POST['action'])) {
    $loanId = (int)$_POST['loan_id'];
    
    if ($_POST['action'] === 'approve') {
        $action = 'Approved';
        $updateStatusQuery = "UPDATE loan_applications SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateStatusQuery);
        mysqli_stmt_bind_param($stmt, 'si', $action, $loanId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } 
    elseif ($_POST['action'] === 'reject') {
        $action = 'Rejected';
        $rejectionReason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? 'No reason provided');
        
        // Update status and store rejection reason
        $updateStatusQuery = "UPDATE loan_applications SET status = ?, rejection_reason = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateStatusQuery);
        mysqli_stmt_bind_param($stmt, 'ssi', $action, $rejectionReason, $loanId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $loanDetailsQuery = "SELECT user_id FROM loan_applications WHERE id = ?";
    $stmt = mysqli_prepare($conn, $loanDetailsQuery);
    mysqli_stmt_bind_param($stmt, 'i', $loanId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($loanDetails = mysqli_fetch_assoc($result)) {
        $userId = $loanDetails['user_id'];
        $notifMessage = "Your loan application (ID: $loanId) has been $action.";
        if ($action === 'Rejected' && !empty($rejectionReason)) {
            $notifMessage .= " Reason: $rejectionReason";
        }
        $notifQuery = "INSERT INTO notifications (user_id, message) VALUES (?, ?)";
        $notifStmt = mysqli_prepare($conn, $notifQuery);
        mysqli_stmt_bind_param($notifStmt, 'is', $userId, $notifMessage);
        mysqli_stmt_execute($notifStmt);
        mysqli_stmt_close($notifStmt);
    }
    mysqli_stmt_close($stmt);

    header("Location: admin_dashboard.php");
    exit();
}

// Handle Mark Notifications as Read
if (isset($_GET['mark_as_read'])) {
    $updateNotifQuery = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
    mysqli_query($conn, $updateNotifQuery);
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch loan applications (excluding rejected ones from the main dashboard view)
$loanApplicationsQuery = "
    SELECT la.id, u.email, u.username, la.loan_type, la.amount, la.term, la.purpose, la.work, la.status,
           la.payment_status, la.payment_proof, la.created_at, la.document_path,
           IFNULL(SUM(p.amount), 0) AS total_paid
    FROM loan_applications la
    JOIN users u ON la.user_id = u.id
    LEFT JOIN payments p ON la.id = p.loan_id
    WHERE la.status != 'Rejected' OR la.status IS NULL
    GROUP BY la.id
    ORDER BY la.created_at DESC
";
$result = mysqli_query($conn, $loanApplicationsQuery);

// Fetch Total Borrowers and Loans (excluding rejected loans from count)
$totalBorrowersQuery = "SELECT COUNT(DISTINCT user_id) AS total_borrowers FROM loan_applications WHERE status != 'Rejected'";
$totalBorrowersResult = mysqli_query($conn, $totalBorrowersQuery);
$totalBorrowers = mysqli_fetch_assoc($totalBorrowersResult)['total_borrowers'];

$totalLoansQuery = "SELECT COUNT(*) AS total_loans FROM loan_applications WHERE status != 'Rejected'";
$totalLoansResult = mysqli_query($conn, $totalLoansQuery);
$totalLoans = mysqli_fetch_assoc($totalLoansResult)['total_loans'];

// Calculate total payments
$totalPaymentsResult = mysqli_query($conn, "SELECT SUM(amount) AS total_payments FROM payments");
$totalPayments = mysqli_fetch_assoc($totalPaymentsResult)['total_payments'] ?? 0;

// Fetch recent payments (last 30 days)
$recentPaymentsQuery = "
    SELECT p.id, p.loan_id, p.amount, p.payment_date, 
           u.username, u.email, la.loan_type, la.payment_proof
    FROM payments p
    JOIN loan_applications la ON p.loan_id = la.id
    JOIN users u ON p.user_id = u.id
    WHERE p.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY p.payment_date DESC
    LIMIT 10
";
$recentPayments = mysqli_query($conn, $recentPaymentsQuery);

// Fetch Unread Notifications
$unreadNotifQuery = "SELECT COUNT(*) AS unread_notifications FROM notifications WHERE is_read = 0";
$unreadNotifResult = mysqli_query($conn, $unreadNotifQuery);
$unreadNotifCount = mysqli_fetch_assoc($unreadNotifResult)['unread_notifications'];
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo $_SESSION['theme']; ?>">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - LoanPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --body-bg-light: #f8f9fa;
            --card-bg-light: #ffffff;
            --text-light: #212529;
            --card-shadow-light: 0 2px 10px rgba(0,0,0,0.1);
            
            --body-bg-dark: #212529;
            --card-bg-dark: #2c3034;
            --text-dark: #f8f9fa;
            --card-shadow-dark: 0 2px 10px rgba(0,0,0,0.3);
        }

        body {
            background-color: var(--body-bg-light);
            color: var(--text-light);
            transition: background-color 0.3s, color 0.3s;
        }

        [data-bs-theme="dark"] body {
            background-color: var(--body-bg-dark);
            color: var(--text-dark);
        }

        .card {
            border-radius: 10px;
            box-shadow: var(--card-shadow-light);
            background-color: var(--card-bg-light);
            transition: background-color 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
        }

        [data-bs-theme="dark"] .card {
            box-shadow: var(--card-shadow-dark);
            background-color: var(--card-bg-dark);
        }

        .dashboard-header { margin: 30px 0; }
        
        .search-container { position: relative; }
        
        .search-loading {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,0.1);
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        
        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }
        
        .badge { font-weight: normal; }
        .table-responsive { overflow-x: auto; }
        
        /* Fixed modal image styles */
        .modal-img { 
            max-width: 100%; 
            height: auto; 
            max-height: 80vh;
            object-fit: contain;
            background-color: transparent !important;
        }
        
        /* Theme toggle switch */
        .theme-toggle {
            display: flex;
            align-items: center;
            margin-left: 15px;
            cursor: pointer;
        }
        
        .theme-toggle input[type="checkbox"] {
            display: none;
        }
        
        .theme-toggle .slider {
            width: 50px;
            height: 24px;
            background-color: #ccc;
            border-radius: 24px;
            position: relative;
            transition: background-color 0.3s;
            margin: 0 10px;
        }
        
        .theme-toggle .slider:before {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }
        
        .theme-toggle input:checked + .slider {
            background-color: #2196F3;
        }
        
        .theme-toggle input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .theme-icon {
            font-size: 1.2rem;
        }
        
        /* New payment indicator */
        .new-payment-badge {
            position: relative;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .payment-card {
            transition: all 0.3s ease;
        }
        
        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        /* Payment amount highlight */
        .payment-amount {
            font-weight: bold;
            font-size: 1.1rem;
            color: #198754;
        }
        
        [data-bs-theme="dark"] .payment-amount {
            color: #2ecc71;
        }
        
        /* Payment progress styles */
        .payment-progress-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        [data-bs-theme="dark"] .payment-progress-container {
            background-color: #343a40;
        }
        
        .payment-details {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }

        /* Enhanced payment amount styles */
        .payment-amount {
            font-size: 1.4rem;
            font-weight: bold;
            color: #198754;
            text-shadow: 0 0 1px rgba(0,0,0,0.1);
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            background-color: rgba(25, 135, 84, 0.1);
        }

        [data-bs-theme="dark"] .payment-amount {
            color: #2ecc71;
            background-color: rgba(46, 204, 113, 0.1);
        }

        /* Payment progress enhancements */
        .payment-progress-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        [data-bs-theme="dark"] .payment-progress-container {
            background-color: #343a40;
            border-color: rgba(255,255,255,0.1);
        }

        .progress {
            height: 12px !important;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        }

        .payment-details {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed rgba(0,0,0,0.1);
        }

        [data-bs-theme="dark"] .payment-details {
            border-color: rgba(255,255,255,0.1);
        }

        .payment-summary {
            display: flex;
            justify-content: space-between;
            background-color: #f0f8ff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid rgba(13, 110, 253, 0.2);
        }

        [data-bs-theme="dark"] .payment-summary {
            background-color: rgba(13, 110, 253, 0.1);
        }

        .remaining-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #dc3545;
        }

        [data-bs-theme="dark"] .remaining-amount {
            color: #f87171;
        }

        @keyframes bellRing {
            0% { transform: rotate(0); }
            10% { transform: rotate(15deg); }
            20% { transform: rotate(-15deg); }
            30% { transform: rotate(10deg); }
            40% { transform: rotate(-10deg); }
            50% { transform: rotate(5deg); }
            60% { transform: rotate(-5deg); }
            70% { transform: rotate(0); }
            100% { transform: rotate(0); }
        }

        .bell-animation {
            animation: bellRing 2s ease infinite;
            transform-origin: top center;
        }

        /* Fixed toast container positioning */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            max-width: 350px;
            width: 100%;
            pointer-events: none;
        }

        .toast-container .toast {
            pointer-events: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .new-payment-alert {
            animation: slideIn 0.5s ease forwards;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .highlight-row {
            background-color: rgba(25, 135, 84, 0.1);
            animation: highlightFade 3s ease;
        }

        @keyframes highlightFade {
            from { background-color: rgba(25, 135, 84, 0.3); }
            to { background-color: rgba(25, 135, 84, 0.1); }
        }

        .payment-badge-new {
            position: relative;
            overflow: hidden;
        }

        .payment-badge-new::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.3);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .payment-highlight {
            animation: payment-pulse 2s infinite;
            box-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
        }

        @keyframes payment-pulse {
            0% { box-shadow: 0 0 10px rgba(25, 135, 84, 0.5); }
            50% { box-shadow: 0 0 20px rgba(25, 135, 84, 0.8); }
            100% { box-shadow: 0 0 10px rgba(25, 135, 84, 0.5); }
        }

        .payment-card .card-header {
            border-bottom: 2px solid rgba(0,0,0,0.1);
        }

        [data-bs-theme="dark"] .payment-card .card-header {
            border-color: rgba(255,255,255,0.1);
        }

        .payment-banner {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #81c784;
        }

        [data-bs-theme="dark"] .payment-banner {
            background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
            border-color: #43a047;
        }

        /* Payment scrollbar styles */
        .payment-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .payment-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .payment-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .payment-list::-webkit-scrollbar-thumb {
            background: #198754;
            border-radius: 10px;
        }
        
        .payment-list::-webkit-scrollbar-thumb:hover {
            background: #146c43;
        }
        
        [data-bs-theme="dark"] .payment-list::-webkit-scrollbar-track {
            background: #343a40;
        }
        
        [data-bs-theme="dark"] .payment-list::-webkit-scrollbar-thumb {
            background: #2ecc71;
        }
        
        [data-bs-theme="dark"] .payment-list::-webkit-scrollbar-thumb:hover {
            background: #27ae60;
        }

        .payment-timeline {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .payment-timeline::-webkit-scrollbar {
            width: 8px;
        }
        
        .payment-timeline::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .payment-timeline::-webkit-scrollbar-thumb {
            background: #198754;
            border-radius: 10px;
        }
        
        .payment-timeline::-webkit-scrollbar-thumb:hover {
            background: #146c43;
        }
        
        [data-bs-theme="dark"] .payment-timeline::-webkit-scrollbar-track {
            background: #343a40;
        }
        
        [data-bs-theme="dark"] .payment-timeline::-webkit-scrollbar-thumb {
            background: #2ecc71;
        }
        
        [data-bs-theme="dark"] .payment-timeline::-webkit-scrollbar-thumb:hover {
            background: #27ae60;
        }

        /* ID Document Styles */
        .id-preview {
            transition: transform 0.3s ease;
            cursor: pointer;
            max-height: 200px;
            object-fit: contain;
            background-color: transparent !important;
        }

        .id-preview:hover {
            transform: scale(1.05);
        }

        /* Fixed modal image styles */
        .modal-img {
            max-height: 80vh;
            object-fit: contain;
            background-color: transparent !important;
        }

        /* Fix for dark mode modal headers */
        [data-bs-theme="dark"] .modal-header.bg-secondary {
            background-color: #495057 !important;
        }

        /* Fix for modal body images */
        .modal-body img.img-fluid.w-100 {
            background-color: transparent !important;
            border-radius: 0;
            max-height: 80vh;
            object-fit: contain;
        }

        /* Fix for document modal */
        .document-modal-content {
            background-color: transparent;
            padding: 0;
        }

        /* Fix for image container in modals */
        .image-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 300px;
            background-color: rgba(255,255,255,0.05);
            border-radius: 8px;
        }

        [data-bs-theme="dark"] .image-container {
            background-color: rgba(0,0,0,0.2);
        }

        /* Enhanced Notification dropdown styles */
        .dropdown-menu {
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown-menu::-webkit-scrollbar {
            width: 6px;
        }

        .dropdown-menu::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .dropdown-menu::-webkit-scrollbar-thumb {
            background: #0d6efd;
            border-radius: 10px;
        }

        .dropdown-menu::-webkit-scrollbar-thumb:hover {
            background: #0a58ca;
        }

        [data-bs-theme="dark"] .dropdown-menu::-webkit-scrollbar-track {
            background: #343a40;
        }

        [data-bs-theme="dark"] .dropdown-menu::-webkit-scrollbar-thumb {
            background: #0d6efd;
        }

        /* Improved notification container styles */
        .notification-dropdown {
            width: 350px !important;
            max-width: 90vw !important;
        }

        .notification-item {
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
            padding: 12px 15px;
        }

        .notification-item:hover {
            background-color: rgba(13, 110, 253, 0.1);
        }

        .notification-item.unread {
            border-left-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }

        .notification-item .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 4px;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .notification-icon.payment {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .notification-icon.system {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-message {
            white-space: normal;
            word-break: break-word;
            margin-bottom: 2px;
            line-height: 1.4;
            font-size: 0.9rem;
        }

        .notification-header {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        [data-bs-theme="dark"] .notification-header {
            border-color: rgba(255,255,255,0.1);
        }

        .notification-footer {
            padding: 10px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        [data-bs-theme="dark"] .notification-footer {
            border-color: rgba(255,255,255,0.1);
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-empty {
            padding: 30px 15px;
            text-align: center;
            color: #6c757d;
        }

        .notification-empty i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
<!-- New Payment Alert -->
<div class="toast-container">
    <?php
    // Check for recent payments (within the last 5 minutes)
    $recentPaymentCheck = "
    SELECT p.id, p.amount, p.payment_date, u.username, la.loan_type, la.id as loan_id
    FROM payments p
    JOIN loan_applications la ON p.loan_id = la.id
    JOIN users u ON p.user_id = u.id
    WHERE p.payment_date >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY p.payment_date DESC
    LIMIT 1
";
    $recentPaymentResult = mysqli_query($conn, $recentPaymentCheck);
    if (mysqli_num_rows($recentPaymentResult) > 0):
        $recentPayment = mysqli_fetch_assoc($recentPaymentResult);
    ?>
    <div class="toast new-payment-alert show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
            <i class="bi bi-cash-coin me-2"></i>
            <strong class="me-auto">New Payment Received</strong>
            <small><?= date("g:i a", strtotime($recentPayment['payment_date'])) ?></small>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            <p class="mb-1"><strong><?= htmlspecialchars($recentPayment['username']) ?></strong> has made a payment of 
            <span class="payment-amount">₱<?= number_format($recentPayment['amount'], 2) ?></span> for 
            <?= htmlspecialchars($recentPayment['loan_type']) ?> (Loan #<?= $recentPayment['loan_id'] ?>).</p>
            <div class="mt-2 pt-2 border-top">
                <a href="#recent-payments-section" class="btn btn-sm btn-success">View in Recent Payments</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="toast">Dismiss</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-body shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="bi bi-bank me-2"></i>LoanPro Admin Dashboard
        </a>
        <div class="d-flex align-items-center">
            <div class="theme-toggle">
                <i class="bi bi-sun-fill theme-icon"></i>
                <label>
                    <input type="checkbox" id="themeToggle" <?php echo $_SESSION['theme'] === 'dark' ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
                <i class="bi bi-moon-fill theme-icon"></i>
            </div>
            
            <div class="dropdown me-3">
                <button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell <?php echo $unreadNotifCount > 0 ? 'bell-animation text-primary' : ''; ?>"></i>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $unreadNotifCount ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu notification-dropdown dropdown-menu-end shadow p-0">
                    <div class="notification-header">
                        <h6 class="mb-0 fw-bold">Notifications</h6>
                        <?php if ($unreadNotifCount > 0): ?>
                            <span class="badge bg-primary"><?= $unreadNotifCount ?> new</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-list">
                        <?php
                        $notifList = mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
                        if (mysqli_num_rows($notifList) > 0): 
                            while ($notif = mysqli_fetch_assoc($notifList)): 
                                $isPaymentNotif = strpos(strtolower($notif['message']), 'payment') !== false;
                                $notifClass = !$notif['is_read'] ? 'unread' : '';
                                $iconClass = $isPaymentNotif ? 'payment' : 'system';
                                $icon = $isPaymentNotif ? 'bi-cash-coin' : 'bi-bell';
                        ?>
                            <div class="notification-item <?= $notifClass ?>">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon <?= $iconClass ?>">
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-message">
                                            <?= htmlspecialchars($notif['message']) ?>
                                        </div>
                                        <div class="notification-time">
                                            <?= date("M j, g:i a", strtotime($notif['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <div class="notification-empty">
                                <i class="bi bi-bell-slash d-block"></i>
                                <p class="mb-0">No notifications yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-footer">
                        <a href="?mark_as_read=1" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-check-all me-1"></i> Mark all as read
                        </a>
                    </div>
                </div>
            </div>
            <a href="logout.php" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<!-- Dashboard Content -->
<div class="container py-4">
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Borrowers</h5>
                            <h2 class="mb-0"><?= number_format($totalBorrowers) ?></h2>
                        </div>
                        <i class="bi bi-people-fill" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Loans</h5>
                            <h2 class="mb-0"><?= number_format($totalLoans) ?></h2>
                        </div>
                        <i class="bi bi-cash-stack" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Payments</h5>
                            <h2 class="mb-0" style="font-size: 2.2rem; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                                ₱<?= number_format($totalPayments, 2) ?>
                            </h2>
                            <div class="mt-2 small">All time payment collection</div>
                        </div>
                        <i class="bi bi-graph-up-arrow" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Payments Section -->
    <div class="card shadow-sm mb-4" id="recent-payments-section">
        <div class="card-header bg-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>Recent Payments
                    <?php if (mysqli_num_rows($recentPayments) > 0): ?>
                        <?php
                        $newPaymentsCount = 0;
                        mysqli_data_seek($recentPayments, 0);
                        while ($payment = mysqli_fetch_assoc($recentPayments)) {
                            $paymentDate = strtotime($payment['payment_date']);
                            if ((time() - $paymentDate) < 3600) { // 1 hour
                                $newPaymentsCount++;
                            }
                        }
                        mysqli_data_seek($recentPayments, 0);
                        if ($newPaymentsCount > 0):
                        ?>
                        <span class="badge bg-success ms-2"><?= $newPaymentsCount ?> new</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </h5>
                <a href="#" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye me-1"></i>View All Payments
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($recentPayments) > 0): ?>
                <!-- Payment Summary Dashboard -->
                <div class="card mb-4 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-center mb-3">
                            <i class="bi bi-cash-stack me-2"></i>Payment Summary
                        </h6>
                        <div class="row text-center">
                            <?php
                            // Calculate total payments today
                            $todayPaymentsQuery = "SELECT SUM(amount) as today_total FROM payments WHERE DATE(payment_date) = CURDATE()";
                            $todayPaymentsResult = mysqli_query($conn, $todayPaymentsQuery);
                            $todayTotal = mysqli_fetch_assoc($todayPaymentsResult)['today_total'] ?? 0;
                            
                            // Calculate total payments this week
                            $weekPaymentsQuery = "SELECT SUM(amount) as week_total FROM payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                            $weekPaymentsResult = mysqli_query($conn, $weekPaymentsQuery);
                            $weekTotal = mysqli_fetch_assoc($weekPaymentsResult)['week_total'] ?? 0;
                            
                            // Calculate total payments this month
                            $monthPaymentsQuery = "SELECT SUM(amount) as month_total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
                            $monthPaymentsResult = mysqli_query($conn, $monthPaymentsQuery);
                            $monthTotal = mysqli_fetch_assoc($monthPaymentsResult)['month_total'] ?? 0;
                            ?>
                            <div class="col-md-4">
                                <div class="p-3" style="background-color: #e8f5e9; border-radius: 10px;">
                                    <h6 class="text-muted">Today's Payments</h6>
                                    <h3 class="payment-amount mb-0">₱<?= number_format($todayTotal, 2) ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3" style="background-color: #e3f2fd; border-radius: 10px;">
                                    <h6 class="text-muted">This Week</h6>
                                    <h3 class="payment-amount mb-0" style="color: #0d6efd;">₱<?= number_format($weekTotal, 2) ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3" style="background-color: #f3e5f5; border-radius: 10px;">
                                    <h6 class="text-muted">This Month</h6>
                                    <h3 class="payment-amount mb-0" style="color: #9c27b0;">₱<?= number_format($monthTotal, 2) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // First, let's collect all payments into an array for easier handling
                $allPayments = [];
                $totalPaymentAmount = 0;
                $firstPayment = null;

                // Reset the result pointer to the beginning
                mysqli_data_seek($recentPayments, 0);

                // Loop through all payments to collect data
                while ($payment = mysqli_fetch_assoc($recentPayments)) {
                    // Store the first payment for reference data
                    if ($firstPayment === null) {
                        $firstPayment = $payment;
                    }
                    
                    // Add to total amount
                    $totalPaymentAmount += $payment['amount'];
                    
                    // Check if payment is new (within the last hour)
                    $paymentDate = strtotime($payment['payment_date']);
                    $isNew = (time() - $paymentDate) < 3600; // 1 hour
                    
                    // Get loan data for this payment
                    $loanQuery = "SELECT la.amount, IFNULL(SUM(p2.amount), 0) as total_paid 
                                FROM loan_applications la 
                                LEFT JOIN payments p2 ON la.id = p2.loan_id 
                                WHERE la.id = {$payment['loan_id']} 
                                GROUP BY la.id";
                    $loanResult = mysqli_query($conn, $loanQuery);
                    $loanData = mysqli_fetch_assoc($loanResult);
                    
                    // Calculate progress percentage
                    $totalAmount = $loanData['amount'] ?? 0;
                    $totalPaid = $loanData['total_paid'] ?? 0;
                    $progressPercentage = ($totalAmount > 0) ? min(100, ($totalPaid / $totalAmount) * 100) : 0;
                    
                    // Add to payments array
                    $allPayments[] = [
                        'id' => $payment['id'],
                        'amount' => $payment['amount'],
                        'loan_id' => $payment['loan_id'],
                        'loan_type' => $payment['loan_type'],
                        'username' => $payment['username'],
                        'email' => $payment['email'],
                        'date' => $payment['payment_date'],
                        'formatted_date' => date("M j, Y g:i A", strtotime($payment['payment_date'])),
                        'payment_proof' => $payment['payment_proof'],
                        'is_new' => $isNew,
                        'total_amount' => $totalAmount,
                        'total_paid' => $totalPaid,
                        'progress_percentage' => $progressPercentage
                    ];
                }

                // Only proceed if we have payments
                if (count($allPayments) > 0):
                    // Get common data from the first payment
                    $commonLoanId = $allPayments[0]['loan_id'];
                    $commonLoanType = $allPayments[0]['loan_type'];
                    $commonUsername = $allPayments[0]['username'];
                    $commonProgressPercentage = $allPayments[0]['progress_percentage'];
                    $commonTotalPaid = $allPayments[0]['total_paid'];
                    $commonTotalAmount = $allPayments[0]['total_amount'];
                    
                    // Check if all payments are for the same loan
                    $sameLoan = true;
                    foreach ($allPayments as $payment) {
                        if ($payment['loan_id'] != $commonLoanId) {
                            $sameLoan = false;
                            break;
                        }
                    }
                ?>

                <!-- Recent Payments List -->
                <div class="row">
                    <?php foreach ($allPayments as $payment): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card payment-card h-100 <?= $payment['is_new'] ? 'border-success' : 'border-light' ?>" 
                             style="border-width: 2px;">
                            <div class="card-header <?= $payment['is_new'] ? 'bg-success text-white' : 'bg-light' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-credit-card me-1"></i> 
                                        Payment #<?= $payment['id'] ?>
                                    </h6>
                                    <?php if ($payment['is_new']): ?>
                                        <span class="badge bg-danger ms-1 new-payment-badge">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Payment Amount Banner -->
                                <div class="bg-light p-3 mb-3 text-center" style="border-radius: 10px; border: 1px dashed #198754;">
                                    <h5 class="text-muted mb-1">Payment Amount</h5>
                                    <h3 class="payment-amount" style="font-size: 1.8rem;">₱<?= number_format($payment['amount'], 2) ?></h3>
                                </div>
                                
                                <!-- Payment Details -->
                                <p class="card-text mb-1">
                                    <small><i class="bi bi-credit-card me-1"></i>Loan ID: <strong><?= $payment['loan_id'] ?></strong> (<?= $payment['loan_type'] ?>)</small>
                                </p>
                                <p class="card-text mb-1">
                                    <small><i class="bi bi-person me-1"></i>From: <strong><?= htmlspecialchars($payment['username']) ?></strong></small>
                                </p>
                                <p class="card-text mb-1">
                                    <small><i class="bi bi-calendar me-1"></i>Date: <?= $payment['formatted_date'] ?></small>
                                </p>
                                
                                <!-- Payment Progress Information -->
                                <div class="mt-3 pt-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted">Payment Progress</small>
                                        <small class="badge bg-<?= $payment['progress_percentage'] >= 100 ? 'success' : 'primary' ?>">
                                            <?= number_format($payment['progress_percentage'], 1) ?>%
                                        </small>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                            style="width: <?= $payment['progress_percentage'] ?>%;" 
                                            aria-valuenow="<?= $payment['progress_percentage'] ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <div class="small text-muted">Paid</div>
                                        <div class="payment-amount">₱<?= number_format($payment['total_paid'], 2) ?></div>
                                    
                                        <div class="text-end">
                                            <div class="small text-muted">Total</div>
                                            <div>₱<?= number_format($payment['total_amount'], 2) ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- View Payment Proof Button -->
                                <div class="mt-3">
                                    <button class="btn btn-primary btn-sm w-100" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#paymentProofModal<?= $payment['id'] ?>">
                                        <i class="bi bi-image me-1"></i> View Payment Proof
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Proof Modal for each payment -->
                    <div class="modal fade" id="paymentProofModal<?= $payment['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Payment Proof #<?= $payment['id'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="text-center mb-4 image-container">
                                                <?php if (!empty($payment['payment_proof'])): ?>
                                                    <img src="<?= htmlspecialchars($payment['payment_proof']) ?>" 
                                                         class="img-fluid modal-img" 
                                                         alt="Payment Proof"
                                                         loading="lazy"
                                                         onload="this.style.opacity='1';"
                                                         style="opacity: 0; transition: opacity 0.3s ease;">
                                                <?php else: ?>
                                                    <div class="alert alert-warning">No payment proof available</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="bg-light p-3 mb-3 text-center" style="border-radius: 10px; border: 2px solid #198754;">
                                                <h5 class="text-muted mb-1">Payment Amount</h5>
                                                <h2 class="payment-amount" style="font-size: 2.2rem;">₱<?= number_format($payment['amount'], 2) ?></h2>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong><i class="bi bi-calendar-date me-1"></i> Date:</strong> <?= $payment['formatted_date'] ?></p>
                                                    <p><strong><i class="bi bi-person me-1"></i> User:</strong> <?= htmlspecialchars($payment['username']) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong><i class="bi bi-envelope me-1"></i> Email:</strong> <?= htmlspecialchars($payment['email']) ?></p>
                                                    <p><strong><i class="bi bi-credit-card me-1"></i> Loan ID:</strong> <?= $payment['loan_id'] ?> (<?= $payment['loan_type'] ?>)</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Payment Progress -->
                                            <div class="mt-3 pt-3 border-top">
                                                <h6>Payment Progress</h6>
                                                <div class="progress mb-2" style="height: 10px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                        style="width: <?= $payment['progress_percentage'] ?>%;" 
                                                        aria-valuenow="<?= $payment['progress_percentage'] ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100"></div>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <small class="text-muted">Total Paid</small>
                                                        <div class="payment-amount">₱<?= number_format($payment['total_paid'], 2) ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted">Loan Amount</small>
                                                        <div>₱<?= number_format($payment['total_amount'], 2) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; // End of if count($allPayments) > 0 ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-cash-coin" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3 text-muted">No Recent Payments</h4>
                    <p class="text-muted">No payments have been made in the last 30 days.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loan Applications Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>Loan Applications
                </h5>
                <div class="d-flex">
                    <div class="search-container me-2" style="width: 250px;">
                        <input type="text" id="search-bar" class="form-control form-control-sm" placeholder="Search applications...">
                        <div id="search-loading" class="search-loading"></div>
                    </div>
                    <button id="clear-search" class="btn btn-sm btn-outline-secondary" style="display: none;">
                        Clear
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info alert-dismissible fade show mb-3" id="search-results-info" style="display: none;">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <span id="search-message"></span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="loan-applications-table">
                        <?php 
                        mysqli_data_seek($result, 0); // Reset result pointer
                        while ($loan = mysqli_fetch_assoc($result)): 
                        ?>
                        <tr>
                            <td><?= $loan['id'] ?></td>
                            <td>
                                <div><?= htmlspecialchars($loan['username']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($loan['email']) ?></small>
                            </td>
                            <td><?= $loan['loan_type'] ?></td>
                            <td>₱<?= number_format($loan['amount'], 2) ?></td>
                            <td><?= $loan['term'] ?> months</td>
                            <td>
                                <span class="badge bg-<?= 
                                    $loan['status'] === 'Approved' ? 'success' : 
                                    ($loan['status'] === 'Rejected' ? 'danger' : 'warning')
                                ?>">
                                    <?= $loan['status'] ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $paid = $loan['total_paid'] ?? 0;
                                $remaining = calculateRemainingBalance($loan['amount'], $paid);
                                $paymentPercentage = ($loan['amount'] > 0) ? ($paid / $loan['amount']) * 100 : 0;
                                ?>
                                <div class="payment-progress-container">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold">Payment Progress</span>
                                        <span class="badge bg-<?= $paymentPercentage > 0 ? 'success' : 'warning' ?>"><?= number_format($paymentPercentage, 1) ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                            style="width: <?= $paymentPercentage ?>%;" 
                                            aria-valuenow="<?= $paymentPercentage ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100"></div>
                                    </div>
                                    <div class="payment-details">
                                        <div>
                                            <div class="small text-muted">Paid</div>
                                            <div class="payment-amount">₱<?= number_format($paid, 2) ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-muted">Total</div>
                                            <div>₱<?= number_format($loan['amount'], 2) ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Add payment history summary -->
                                    <?php
                                    $recentPaymentsQuery = "
                                        SELECT p.amount, p.payment_date 
                                        FROM payments p 
                                        WHERE p.loan_id = {$loan['id']} 
                                        ORDER BY p.payment_date DESC 
                                        LIMIT 1";
                                    $recentPayment = mysqli_query($conn, $recentPaymentsQuery);
                                    if (mysqli_num_rows($recentPayment) > 0):
                                        $payment = mysqli_fetch_assoc($recentPayment);
                                    ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="small text-muted">Last Payment:</div>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-success me-2">₱<?= number_format($payment['amount'], 2) ?></span>
                                                <small><?= date("M j, Y", strtotime($payment['payment_date'])) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <?php if ($loan['status'] === 'Pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                <i class="bi bi-check-circle me-1"></i>Approve
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $loan['id'] ?>">
                                            <i class="bi bi-x-circle me-1"></i>Reject
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                        data-bs-target="#detailsModal<?= $loan['id'] ?>">
                                        <i class="bi bi-eye me-1"></i>Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modals -->
<?php 
mysqli_data_seek($result, 0); // Reset result pointer
while ($loan = mysqli_fetch_assoc($result)): 
    if ($loan['status'] === 'Pending'):
?>
<div class="modal fade" id="rejectModal<?= $loan['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Loan Application #<?= $loan['id'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label for="rejectionReason<?= $loan['id'] ?>" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejectionReason<?= $loan['id'] ?>" name="rejection_reason" rows="3" required placeholder="Please provide a reason for rejecting this loan application..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php 
    endif;
endwhile; 
?>

<!-- Detail Modals -->
<?php 
mysqli_data_seek($result, 0); // Reset result pointer
while ($loan = mysqli_fetch_assoc($result)): 
?>
<div class="modal fade" id="detailsModal<?= $loan['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Loan Details (ID: <?= $loan['id'] ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>User:</strong> <?= htmlspecialchars($loan['username']) ?> (<?= htmlspecialchars($loan['email']) ?>)</p>
                        <p><strong>Loan Type:</strong> <?= $loan['loan_type'] ?></p>
                        <p><strong>Amount:</strong> ₱<?= number_format($loan['amount'], 2) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Term:</strong> <?= $loan['term'] ?> months</p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= 
                                $loan['status'] === 'Approved' ? 'success' : 
                                ($loan['status'] === 'Rejected' ? 'danger' : 'warning')
                            ?>">
                                <?= $loan['status'] ?>
                            </span>
                        </p>
                        <p><strong>Applied On:</strong> <?= date("M d, Y h:i A", strtotime($loan['created_at'])) ?></p>
                    </div>
                </div>

                <div class="mb-3">
                    <h6>Purpose</h6>
                    <p><?= htmlspecialchars($loan['purpose']) ?></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">Payment Information</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $paid = $loan['total_paid'] ?? 0;
                                $remaining = calculateRemainingBalance($loan['amount'], $paid);
                                $paymentPercentage = ($loan['amount'] > 0) ? ($paid / $loan['amount']) * 100 : 0;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold">Payment Progress</span>
                                        <span class="badge bg-<?= $paymentPercentage > 0 ? 'success' : 'warning' ?> px-3 py-2"><?= number_format($paymentPercentage, 1) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 18px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                            style="width: <?= $paymentPercentage ?>%;" 
                                            aria-valuenow="<?= $paymentPercentage ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100"></div>
                                    </div>
                                </div>

                                <div class="payment-summary p-3 mt-4 mb-3">
                                    <div>
                                        <h5 class="text-muted mb-1">Total Amount</h5>
                                        <h4>₱<?= number_format($loan['amount'], 2) ?></h4>
                                    </div>
                                    <div>
                                        <h5 class="text-muted mb-1">Amount Paid</h5>
                                        <h4 class="payment-amount">₱<?= number_format($paid, 2) ?></h4>
                                    </div>
                                </div>

                                <div class="payment-summary p-3 mb-3">
                                    <div>
                                        <h5 class="text-muted mb-1">Payment Status</h5>
                                        <?php
                                        if ($paid >= $loan['amount']) {
                                            echo '<span class="badge bg-success fs-6 px-3 py-2">Fully Paid</span>';
                                        } elseif ($paid > 0) {
                                            echo '<span class="badge bg-warning text-dark fs-6 px-3 py-2">Partially Paid</span>';
                                        } else {
                                            echo '<span class="badge bg-danger fs-6 px-3 py-2">Not Paid</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <!-- Payment Summary Card -->
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Payment Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-6 mb-3">
                                                <div style="background-color: #e8f5e9; padding: 15px; border-radius: 10px;">
                                                    <h6 class="text-muted">Total Paid</h6>
                                                    <h3 class="payment-amount mb-0" style="font-size: 1.8rem;">₱<?= number_format($paid, 2) ?></h3>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div style="background-color: #e3f2fd; padding: 15px; border-radius: 10px;">
                                                    <h6 class="text-muted">Total Amount</h6>
                                                    <h3 class="mb-0" style="font-size: 1.8rem; color: #0d6efd;">₱<?= number_format($loan['amount'], 2) ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Last Payment Information -->
                                        <?php
                                        $lastPaymentQuery = "SELECT amount, payment_date FROM payments WHERE loan_id = {$loan['id']} ORDER BY payment_date DESC LIMIT 1";
                                        $lastPaymentResult = mysqli_query($conn, $lastPaymentQuery);
                                        if (mysqli_num_rows($lastPaymentResult) > 0):
                                            $lastPayment = mysqli_fetch_assoc($lastPaymentResult);
                                        ?>
                                        <div class="mt-3 p-3" style="background-color: #f5f5f5; border-radius: 10px; border-left: 4px solid #198754;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">Last Payment</h6>
                                                    <div class="text-muted"><?= date("F j, Y, g:i a", strtotime($lastPayment['payment_date'])) ?></div>
                                                </div>
                                                <div class="payment-amount" style="font-size: 1.5rem;">₱<?= number_format($lastPayment['amount'], 2) ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Add payment timeline visualization -->
                                <?php if ($paid > 0): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Payment Timeline</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="position-relative py-3">
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                    style="width: <?= $paymentPercentage ?>%;" 
                                                    aria-valuenow="<?= $paymentPercentage ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-2">
                                                <div class="text-center">
                                                    <div class="badge bg-success">Start</div>
                                                    <div class="small">₱0</div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="badge bg-primary">Current</div>
                                                    <div class="small">₱<?= number_format($paid, 2) ?></div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="badge bg-secondary">Goal</div>
                                                    <div class="small">₱<?= number_format($loan['amount'], 2) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($loan['payment_proof'])): ?>
                                    <div class="mt-3">
                                        <button class="btn btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#proofModal<?= $loan['id'] ?>">
                                            <i class="bi bi-image me-1"></i> View Payment Proof
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">ID Document</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($loan['document_path'])): ?>
                                    <!-- ID Preview Directly in Card -->
                                    <div class="text-center mb-3 image-container">
                                        <img src="<?= htmlspecialchars($loan['document_path']) ?>" 
                                             class="id-preview" 
                                             alt="ID Document" 
                                             loading="lazy"
                                             onload="this.style.opacity='1';"
                                             style="opacity: 0; transition: opacity 0.3s ease;"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#docModal<?= $loan['id'] ?>">
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="badge bg-info">ID #<?= $loan['id'] ?></span>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#docModal<?= $loan['id'] ?>">
                                            <i class="bi bi-fullscreen me-1"></i> View Full Size
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i> No ID document uploaded
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment History Section -->
                <div class="mt-3">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Payment History</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $paymentHistoryQuery = "
                                SELECT p.id, p.amount, p.payment_date, p.payment_method
                                FROM payments p
                                WHERE p.loan_id = {$loan['id']}
                                ORDER BY p.payment_date DESC
                            ";
                            $paymentHistory = mysqli_query($conn, $paymentHistoryQuery);
                            
                            if (mysqli_num_rows($paymentHistory) > 0):
                                $totalPaid = 0;
                            ?>
                                <div class="payment-timeline">
                                    <?php while ($payment = mysqli_fetch_assoc($paymentHistory)): 
                                        $totalPaid += $payment['amount'];
                                        $paymentDate = date("M d, Y", strtotime($payment['payment_date']));
                                        $paymentTime = date("h:i A", strtotime($payment['payment_date']));
                                        
                                        // Generate a random reference number
                                        $prefix = "REF";
                                        $year = date("y", strtotime($payment['payment_date']));
                                        $month = date("m", strtotime($payment['payment_date']));
                                        $randomDigits = mt_rand(1000, 9999);
                                        $referenceNumber = $prefix . $year . $month . "-" . $randomDigits;
                                    ?>
                                        <div class="payment-item mb-3 p-3" style="border-left: 4px solid #198754; background-color: #f8f9fa; border-radius: 0 8px 8px 0;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0">
                                                        Payment #<?= $payment['id'] ?>
                                                        <span class="badge bg-primary ms-2" style="font-family: monospace;"><?= $referenceNumber ?></span>
                                                    </h6>
                                                    <div class="text-muted small">
                                                        <i class="bi bi-calendar me-1"></i><?= $paymentDate ?> at <?= $paymentTime ?>
                                                        <?php if ($payment['payment_method']): ?>
                                                            <span class="ms-2"><i class="bi bi-credit-card me-1"></i><?= $payment['payment_method'] ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="payment-amount px-3 py-2 rounded-pill" style="font-size: 1.2rem; background-color: #e8f5e9; border: 1px solid #4caf50;">
                                                    ₱<?= number_format($payment['amount'], 2) ?>
                                                </div>
                                            </div>
                                            <div class="mt-2 pt-2 border-top">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="small text-muted">Reference Number</div>
                                                    <div class="fw-bold" style="font-family: monospace;"><?= $referenceNumber ?></div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <div class="small text-muted">Running Total</div>
                                                    <div class="fw-bold">₱<?= number_format($totalPaid, 2) ?></div>
                                                </div>
                                                <div class="progress mt-1" style="height: 6px;">
                                                    <?php 
                                                    $progressPercentage = ($loan['amount'] > 0) ? min(100, ($totalPaid / $loan['amount']) * 100) : 0;
                                                    ?>
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                        style="width: <?= $progressPercentage ?>%;" 
                                                        aria-valuenow="<?= $progressPercentage ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                
                            <?php else: ?>
                                <p class="text-muted">No payment history available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Proof Modal -->
<div class="modal fade" id="proofModal<?= $loan['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Proof (Loan ID: <?= $loan['id'] ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center image-container">
                <?php if (!empty($loan['payment_proof'])): ?>
                    <img src="<?= htmlspecialchars($loan['payment_proof']) ?>" 
                         class="img-fluid modal-img" 
                         alt="Payment Proof"
                         loading="lazy"
                         onload="this.style.opacity='1';"
                         style="opacity: 0; transition: opacity 0.3s ease;">
                <?php else: ?>
                    <p class="text-muted">No payment proof available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Document Modal -->
<div class="modal fade" id="docModal<?= $loan['id'] ?>" tabindex="-1" aria-labelledby="docModalLabel<?= $loan['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="docModalLabel<?= $loan['id'] ?>">
                    <i class="bi bi-person-badge me-2"></i>
                    ID Document (Loan ID: <?= $loan['id'] ?>)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0 document-modal-content">
                <?php if (!empty($loan['document_path'])): ?>
                    <div class="position-relative image-container">
                        <img src="<?= htmlspecialchars($loan['document_path']) ?>" 
                             class="img-fluid w-100 modal-img" 
                             alt="ID Document"
                             loading="lazy"
                             onload="this.style.opacity='1';"
                             style="opacity: 0; transition: opacity 0.3s ease; max-height: 80vh; object-fit: contain;">
                        <div class="position-absolute top-0 end-0 m-3">
                            <span class="badge bg-dark px-3 py-2 fs-6">
                                <i class="bi bi-person-badge me-1"></i> ID #<?= $loan['id'] ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning m-3">
                        <i class="bi bi-exclamation-triangle me-2"></i> No ID document available
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if (!empty($loan['document_path'])): ?>
                <a href="<?= htmlspecialchars($loan['document_path']) ?>" class="btn btn-primary" download target="_blank">
                    <i class="bi bi-download me-1"></i> Download ID
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endwhile; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Theme toggle functionality
const themeToggle = document.getElementById('themeToggle');
const htmlElement = document.documentElement;

themeToggle.addEventListener('change', function() {
    const theme = this.checked ? 'dark' : 'light';
    // Update the theme immediately
    htmlElement.setAttribute('data-bs-theme', theme);
    
    // Save the preference to session and send to server
    fetch(window.location.href.split('?')[0] + `?theme=${theme}`, {
        method: 'GET'
    }).then(() => {
        // Reload to ensure all elements are properly styled
        window.location.reload();
    });
});

// Search functionality
document.getElementById('search-bar').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#loan-applications-table tr');
    const searchLoading = document.getElementById('search-loading');
    const clearBtn = document.getElementById('clear-search');
    const resultsInfo = document.getElementById('search-results-info');
    const searchMessage = document.getElementById('search-message');
    
    searchLoading.style.display = 'block';
    clearBtn.style.display = searchTerm ? 'block' : 'none';
    
    setTimeout(() => {
        let visibleCount = 0;
        
        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            if (rowText.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (searchTerm) {
            resultsInfo.style.display = 'block';
            searchMessage.textContent = `Showing ${visibleCount} results for "${searchTerm}"`;
        } else {
            resultsInfo.style.display = 'none';
        }
        
        searchLoading.style.display = 'none';
    }, 300);
});

document.getElementById('clear-search').addEventListener('click', function() {
    document.getElementById('search-bar').value = '';
    document.querySelectorAll('#loan-applications-table tr').forEach(row => {
        row.style.display = '';
    });
    this.style.display = 'none';
    document.getElementById('search-results-info').style.display = 'none';
});

// Add this to the existing script section at the bottom of the file
document.addEventListener('DOMContentLoaded', function() {
    // Check if we should scroll to recent payments section
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('show_payments')) {
        const recentPaymentsSection = document.getElementById('recent-payments-section');
        if (recentPaymentsSection) {
            recentPaymentsSection.scrollIntoView({ behavior: 'smooth' });
            recentPaymentsSection.classList.add('payment-highlight');
            setTimeout(() => {
                recentPaymentsSection.classList.remove('payment-highlight');
            }, 3000);
        }
    }
    
    // Auto-dismiss the new payment alert after 10 seconds
    const newPaymentAlert = document.querySelector('.new-payment-alert');
    if (newPaymentAlert) {
        setTimeout(() => {
            const bsToast = new bootstrap.Toast(newPaymentAlert);
            bsToast.hide();
        }, 10000);
    }
    
    // Set up auto-refresh to check for new payments (every 60 seconds)
    setInterval(() => {
        fetch('check_new_payments.php')
            .then(response => response.json())
            .then(data => {
                if (data.hasNewPayments) {
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error checking for new payments:', error));
    }, 60000);

    // Fix for image display in modals
    function fixModalImages() {
        // Fix for payment proof modals
        document.querySelectorAll('[id^="proofModal"]').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                const img = this.querySelector('img.modal-img');
                if (img) {
                    // Force image to load properly
                    img.style.opacity = '0';
                    setTimeout(() => {
                        img.style.opacity = '1';
                    }, 100);
                }
            });
        });

        // Fix for ID document modals
        document.querySelectorAll('[id^="docModal"]').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                const img = this.querySelector('img.img-fluid.w-100');
                if (img) {
                    // Force image to load properly
                    img.style.opacity = '0';
                    setTimeout(() => {
                        img.style.opacity = '1';
                    }, 100);
                }
            });
        });

        // Fix for payment proof tabs in the all payment proofs modal
        document.querySelectorAll('#paymentProofTabs button[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                const targetId = e.target.getAttribute('data-bs-target');
                const targetPane = document.querySelector(targetId);
                if (targetPane) {
                    const img = targetPane.querySelector('img.modal-img');
                    if (img) {
                        // Force image to load properly
                        img.style.opacity = '0';
                        setTimeout(() => {
                            img.style.opacity = '1';
                        }, 100);
                    }
                }
            });
        });
    }

    // Call the function to fix modal images
    fixModalImages();

    // Preload images to prevent black screen
    function preloadImages() {
        // Get all image sources
        const imageSources = [];
        
        // Payment proof images
        document.querySelectorAll('[id^="proofModal"] img').forEach(img => {
            if (img.src) imageSources.push(img.src);
        });
        
        // ID document images
        document.querySelectorAll('[id^="docModal"] img').forEach(img => {
            if (img.src) imageSources.push(img.src);
        });
        
        // ID preview images
        document.querySelectorAll('.id-preview').forEach(img => {
            if (img.src) imageSources.push(img.src);
        });
        
        // Preload all images
        imageSources.forEach(src => {
            const img = new Image();
            img.src = src;
        });
    }
    
    // Call the preload function
    preloadImages();
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add loading states to forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
            
            // Re-enable after 10 seconds as fallback
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 10000);
        }
    });
});

// Enhanced notification handling
function markNotificationAsRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the notification item
            const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
            }
            
            // Update notification count
            updateNotificationCount();
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateNotificationCount() {
    const badge = document.querySelector('.position-absolute.badge');
    const bellIcon = document.querySelector('.bi-bell');
    
    if (badge) {
        const currentCount = parseInt(badge.textContent);
        if (currentCount > 1) {
            badge.textContent = currentCount - 1;
        } else {
            badge.style.display = 'none';
            bellIcon.classList.remove('bell-animation', 'text-primary');
        }
    }
}

// Auto-refresh for real-time updates
function checkForUpdates() {
    fetch('check_updates.php')
        .then(response => response.json())
        .then(data => {
            if (data.newNotifications || data.newPayments) {
                // Show a subtle notification
                showUpdateNotification(data);
            }
        })
        .catch(error => console.error('Error checking for updates:', error));
}

function showUpdateNotification(data) {
    const toast = document.createElement('div');
    toast.className = 'toast position-fixed top-0 end-0 m-3';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="toast-header">
            <i class="bi bi-bell-fill text-primary me-2"></i>
            <strong class="me-auto">New Updates</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            ${data.newNotifications ? 'New notifications available. ' : ''}
            ${data.newPayments ? 'New payments received. ' : ''}
            <a href="#" onclick="window.location.reload()" class="btn btn-sm btn-primary">Refresh</a>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove toast element after it's hidden
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Check for updates every 2 minutes
setInterval(checkForUpdates, 120000);
</script>
</body>
</html>