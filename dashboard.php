<?php
session_start();
include 'config.php';

// Check for success or error messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$query = "SELECT * FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$role = $user['role'];

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $occupation = mysqli_real_escape_string($conn, $_POST['occupation']);
    $monthly_income = (float)$_POST['monthly_income'];
    
    // Handle profile picture upload
    $profile_picture = $user['profile_picture']; // Keep existing if no new upload
    
    if (!empty($_FILES['profile_picture']['name'])) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = basename($_FILES['profile_picture']['name']);
        $target_file = $upload_dir . time() . '_' . $user['id'] . '_' . $file_name;
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            if ($_FILES['profile_picture']['size'] <= 2097152) { // 2MB limit
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    // Delete old profile picture if exists
                    if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                        unlink($user['profile_picture']);
                    }
                    $profile_picture = $target_file;
                } else {
                    $_SESSION['error_message'] = "Profile picture upload failed.";
                }
            } else {
                $_SESSION['error_message'] = "Profile picture size too large. Maximum 2MB allowed.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid file type. Only JPG, JPEG, and PNG files are allowed.";
        }
    }
    
    if (empty($_SESSION['error_message'])) {
        $updateQuery = "UPDATE users SET 
                        username = ?, 
                        phone = ?, 
                        date_of_birth = ?, 
                        address = ?, 
                        occupation = ?, 
                        monthly_income = ?, 
                        profile_picture = ?,
                        updated_at = NOW()
                        WHERE id = ?";
        
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "sssssdsi", 
            $username, $phone, $date_of_birth, $address, 
            $occupation, $monthly_income, $profile_picture, $user['id']);
        
        if (mysqli_stmt_execute($updateStmt)) {
            $_SESSION['success_message'] = "Profile updated successfully!";
            // Update user array for immediate display
            $user['username'] = $username;
            $user['phone'] = $phone;
            $user['date_of_birth'] = $date_of_birth;
            $user['address'] = $address;
            $user['occupation'] = $occupation;
            $user['monthly_income'] = $monthly_income;
            $user['profile_picture'] = $profile_picture;
        } else {
            $_SESSION['error_message'] = "Error updating profile: " . mysqli_error($conn);
        }
        mysqli_stmt_close($updateStmt);
    }
    
    header("Location: dashboard.php#profile");
    exit();
}

// Handle Loan Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_loan_application'])) {
    $loan_type = mysqli_real_escape_string($conn, $_POST['loan_type']);
    $principal_amount = (float)$_POST['principal_amount'];
    $term = (int)$_POST['term'];
    $interest_rate = (float)$_POST['interest_rate'];
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $work = mysqli_real_escape_string($conn, $_POST['work']);
    $id_type = mysqli_real_escape_string($conn, $_POST['id_type']);
    $monthly_income = (float)$_POST['monthly_income'];
    $employment_status = mysqli_real_escape_string($conn, $_POST['employment_status']);
    $employer_name = mysqli_real_escape_string($conn, $_POST['employer_name']);
    $years_employed = (int)$_POST['years_employed'];
    
    // Check for existing unpaid loans
    $unpaidCheck = mysqli_prepare($conn, "SELECT id FROM loan_applications WHERE user_id = ? AND payment_status != 'Paid' AND status != 'Rejected'");
    mysqli_stmt_bind_param($unpaidCheck, "i", $user['id']);
    mysqli_stmt_execute($unpaidCheck);
    $unpaidResult = mysqli_stmt_get_result($unpaidCheck);
    
    if (mysqli_num_rows($unpaidResult) > 0) {
        $_SESSION['error_message'] = "You cannot apply for a new loan because you have existing unpaid loans.";
    } else {
        // Check first-time borrower limits
        $firstTimeBorrowerQuery = "SELECT COUNT(*) as loan_count FROM loan_applications WHERE user_id = ? AND status IN ('Approved', 'Paid')";
        $firstTimeBorrowerStmt = mysqli_prepare($conn, $firstTimeBorrowerQuery);
        mysqli_stmt_bind_param($firstTimeBorrowerStmt, "i", $user['id']);
        mysqli_stmt_execute($firstTimeBorrowerStmt);
        $firstTimeBorrowerResult = mysqli_stmt_get_result($firstTimeBorrowerStmt);
        $loanCount = mysqli_fetch_assoc($firstTimeBorrowerResult)['loan_count'];
        $isFirstTimeBorrower = ($loanCount == 0);
        mysqli_stmt_close($firstTimeBorrowerStmt);
        
        if ($isFirstTimeBorrower && $principal_amount > 1500) {
            $_SESSION['error_message'] = "As a first-time borrower, your maximum loan amount is ₱1,500.";
        } else {
            // Calculate loan details
            $interest_amount = ($principal_amount * $interest_rate / 100) * ($term / 12);
            $total_amount = $principal_amount + $interest_amount;
            $monthly_payment = $total_amount / $term;
            
            // Handle document upload
            $document_path = '';
            $upload_dir = 'uploads/loan_documents/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (!empty($_FILES['document']['name'])) {
                $fname = basename($_FILES['document']['name']);
                $target = $upload_dir . time() . '_' . $user['id'] . '_' . $fname;
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','pdf'];
                
                if (in_array($ext, $allowed)) {
                    if ($_FILES['document']['size'] <= 5242880) { // 5MB limit
                        if (move_uploaded_file($_FILES['document']['tmp_name'], $target)) {
                            $document_path = $target;
                        } else {
                            $_SESSION['error_message'] = "Document upload failed.";
                        }
                    } else {
                        $_SESSION['error_message'] = "Document size too large. Maximum 5MB allowed.";
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid file type. Only JPG, PNG, and PDF files are allowed.";
                }
            }
            
            if (empty($_SESSION['error_message'])) {
                $sql = "INSERT INTO loan_applications 
                        (user_id, loan_type, amount, principal_amount, interest_amount, term, interest_rate, 
                         purpose, document_path, work, id_type, status, monthly_payment, monthly_income, 
                         employment_status, employer_name, years_employed, created_at)
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, NOW())";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "isdddiissssdsis", 
                    $user['id'], $loan_type, $total_amount, $principal_amount, 
                    $interest_amount, $term, $interest_rate, $purpose, 
                    $document_path, $work, $id_type, $monthly_payment, 
                    $monthly_income, $employment_status, $employer_name, $years_employed);
                
                if (mysqli_stmt_execute($stmt)) {
                    $loan_id = mysqli_insert_id($conn);
                    
                    // Create notification for admin
                    $adminQuery = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
                    $adminResult = mysqli_query($conn, $adminQuery);
                    $admin = mysqli_fetch_assoc($adminResult);
                    
                    if ($admin) {
                        $notificationMessage = $user['username'] . " has submitted a new loan application (#" . $loan_id . ")";
                        $notificationQuery = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                                VALUES (?, ?, 'loan_application', 0, NOW())";
                        $notifStmt = mysqli_prepare($conn, $notificationQuery);
                        mysqli_stmt_bind_param($notifStmt, "is", $admin['id'], $notificationMessage);
                        mysqli_stmt_execute($notifStmt);
                        mysqli_stmt_close($notifStmt);
                    }
                    
                    // Create notification for user
                    $userNotificationMessage = "Your loan application (#" . $loan_id . ") has been submitted successfully and is under review.";
                    $userNotificationQuery = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                            VALUES (?, ?, 'loan_status', 0, NOW())";
                    $userNotifStmt = mysqli_prepare($conn, $userNotificationQuery);
                    mysqli_stmt_bind_param($userNotifStmt, "is", $user['id'], $userNotificationMessage);
                    mysqli_stmt_execute($userNotifStmt);
                    mysqli_stmt_close($userNotifStmt);
                    
                    $_SESSION['success_message'] = "Loan application submitted successfully! Application ID: #" . $loan_id;
                } else {
                    $_SESSION['error_message'] = "Error submitting loan application: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    mysqli_stmt_close($unpaidCheck);
    
    header("Location: dashboard.php");
    exit();
}

// Handle Loan Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_loan_id'])) {
    $loan_id = (int)$_POST['cancel_loan_id'];
    
    $verifyLoanQuery = "SELECT * FROM loan_applications WHERE id = ? AND user_id = ? AND status = 'Pending'";
    $stmt = mysqli_prepare($conn, $verifyLoanQuery);
    mysqli_stmt_bind_param($stmt, "ii", $loan_id, $user['id']);
    mysqli_stmt_execute($stmt);
    $verifyResult = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($verifyResult) > 0) {
        $updateQuery = "UPDATE loan_applications SET status = 'Cancelled', updated_at = NOW() WHERE id = ?";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "i", $loan_id);
        
        if (mysqli_stmt_execute($updateStmt)) {
            // Notify admin
            $adminQuery = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
            $adminResult = mysqli_query($conn, $adminQuery);
            $admin = mysqli_fetch_assoc($adminResult);
            
            if ($admin) {
                $notificationMessage = $user['username'] . " has cancelled loan application #" . $loan_id;
                $notificationQuery = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                        VALUES (?, ?, 'loan_cancelled', 0, NOW())";
                $notifStmt = mysqli_prepare($conn, $notificationQuery);
                mysqli_stmt_bind_param($notifStmt, "is", $admin['id'], $notificationMessage);
                mysqli_stmt_execute($notifStmt);
                mysqli_stmt_close($notifStmt);
            }
            
            $_SESSION['success_message'] = "Loan application cancelled successfully!";
        } else {
            $_SESSION['error_message'] = "Error cancelling loan application.";
        }
        mysqli_stmt_close($updateStmt);
    } else {
        $_SESSION['error_message'] = "Invalid loan application or you don't have permission to cancel it.";
    }
    mysqli_stmt_close($stmt);
    
    header("Location: dashboard.php");
    exit();
}

// Mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notification_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    
    $updateQuery = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true]);
    exit();
}

// Mark all notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_notifications_read'])) {
    $updateQuery = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "i", $user['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true]);
    exit();
}

// Get user statistics and data
$firstTimeBorrowerQuery = "SELECT COUNT(*) as loan_count FROM loan_applications WHERE user_id = ? AND status IN ('Approved', 'Paid')";
$firstTimeBorrowerStmt = mysqli_prepare($conn, $firstTimeBorrowerQuery);
mysqli_stmt_bind_param($firstTimeBorrowerStmt, "i", $user['id']);
mysqli_stmt_execute($firstTimeBorrowerStmt);
$firstTimeBorrowerResult = mysqli_stmt_get_result($firstTimeBorrowerStmt);
$loanCount = mysqli_fetch_assoc($firstTimeBorrowerResult)['loan_count'];
$isFirstTimeBorrower = ($loanCount == 0);
mysqli_stmt_close($firstTimeBorrowerStmt);

$maxLoanAmount = $isFirstTimeBorrower ? 1500 : 1000000;
$borrowerStatus = $isFirstTimeBorrower ? 'First-Time Borrower' : 'Returning Borrower';

// Function to calculate monthly payment amount
function calculateMonthlyPayment($totalAmount, $term) {
    if ($term <= 0) return 0;
    return $totalAmount / $term;
}

// Function to get current payment due for a loan
function getCurrentPaymentDue($loanId, $conn) {
    $loanQuery = "SELECT la.*, la.amount as total_amount, la.term, la.created_at,
                  IFNULL(SUM(p.amount), 0) as total_paid,
                  COUNT(CASE WHEN p.id IS NOT NULL THEN 1 END) as payments_made
                  FROM loan_applications la 
                  LEFT JOIN payments p ON la.id = p.loan_id 
                  WHERE la.id = ? 
                  GROUP BY la.id";
    
    $stmt = mysqli_prepare($conn, $loanQuery);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $loanId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $loan = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$loan) return null;
    
    $monthlyPayment = calculateMonthlyPayment($loan['total_amount'], $loan['term']);
    $paymentsMade = (int)$loan['payments_made'];
    $totalPaid = (float)$loan['total_paid'];
    $remainingBalance = $loan['total_amount'] - $totalPaid;
    
    try {
        $loanStartDate = new DateTime($loan['created_at']);
        $currentDate = new DateTime();
        $interval = $loanStartDate->diff($currentDate);
        $monthsElapsed = $interval->m + ($interval->y * 12);
    } catch (Exception $e) {
        error_log("Date calculation error: " . $e->getMessage());
        $monthsElapsed = 0;
    }
    
    $currentPaymentNumber = min($monthsElapsed + 1, $loan['term']);
    $expectedPaymentsMade = max(0, $monthsElapsed);
    $isOverdue = $paymentsMade < $expectedPaymentsMade;
    
    try {
        $nextPaymentDate = clone $loanStartDate;
        $nextPaymentDate->add(new DateInterval('P' . ($paymentsMade + 1) . 'M'));
    } catch (Exception $e) {
        error_log("Date interval error: " . $e->getMessage());
        $nextPaymentDate = new DateTime();
    }
    
    if ($currentPaymentNumber == $loan['term']) {
        $currentPaymentAmount = max(0, $remainingBalance);
    } else {
        $currentPaymentAmount = min($monthlyPayment, max(0, $remainingBalance));
    }
    
    return [
        'loan' => $loan,
        'monthly_payment' => $monthlyPayment,
        'current_payment_amount' => $currentPaymentAmount,
        'current_payment_number' => $currentPaymentNumber,
        'payments_made' => $paymentsMade,
        'remaining_balance' => $remainingBalance,
        'is_overdue' => $isOverdue,
        'next_payment_date' => $nextPaymentDate,
        'can_pay' => $remainingBalance > 0 && $paymentsMade < $loan['term']
    ];
}

// Get user statistics
$userStatsQuery = "SELECT 
    (SELECT COUNT(*) FROM loan_applications WHERE user_id = ?) as total_applications,
    (SELECT COUNT(*) FROM loan_applications WHERE user_id = ? AND status = 'Approved') as approved_loans,
    (SELECT COUNT(*) FROM loan_applications WHERE user_id = ? AND status = 'Pending') as pending_loans,
    (SELECT COUNT(*) FROM loan_applications WHERE user_id = ? AND payment_status = 'Paid') as paid_loans,
    (SELECT IFNULL(SUM(amount), 0) FROM payments WHERE user_id = ?) as total_paid,
    (SELECT COUNT(*) FROM payments WHERE user_id = ?) as total_payments";

$userStatsStmt = mysqli_prepare($conn, $userStatsQuery);
mysqli_stmt_bind_param($userStatsStmt, "iiiiii", $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']);
mysqli_stmt_execute($userStatsStmt);
$userStatsResult = mysqli_stmt_get_result($userStatsStmt);
$userStats = mysqli_fetch_assoc($userStatsResult);
mysqli_stmt_close($userStatsStmt);

// Check if user has any unpaid loans
$loan_check_query = "SELECT * FROM loan_applications WHERE user_id = ? AND payment_status != 'Paid' AND status != 'Rejected'";
$loan_check_stmt = mysqli_prepare($conn, $loan_check_query);
mysqli_stmt_bind_param($loan_check_stmt, "i", $user['id']);
mysqli_stmt_execute($loan_check_stmt);
$loan_check_result = mysqli_stmt_get_result($loan_check_stmt);
$has_unpaid_loan = mysqli_num_rows($loan_check_result) > 0;
mysqli_stmt_close($loan_check_stmt);

// Get all loan applications
$allLoansQuery = "SELECT * FROM loan_applications 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC";
$allLoansStmt = mysqli_prepare($conn, $allLoansQuery);
mysqli_stmt_bind_param($allLoansStmt, "i", $user['id']);
mysqli_stmt_execute($allLoansStmt);
$allLoansResult = mysqli_stmt_get_result($allLoansStmt);

// Get approved loans
$approvedLoansQuery = "SELECT *, 
                      DATE_ADD(created_at, INTERVAL term MONTH) as due_date,
                      DATEDIFF(DATE_ADD(created_at, INTERVAL term MONTH), NOW()) as days_remaining
                      FROM loan_applications 
                      WHERE user_id = ? AND status = 'Approved' 
                      ORDER BY created_at DESC";
$approvedLoansStmt = mysqli_prepare($conn, $approvedLoansQuery);
mysqli_stmt_bind_param($approvedLoansStmt, "i", $user['id']);
mysqli_stmt_execute($approvedLoansStmt);
$approvedLoansResult = mysqli_stmt_get_result($approvedLoansStmt);

// Get pending loans
$pendingLoansQuery = "SELECT * FROM loan_applications 
                     WHERE user_id = ? AND status = 'Pending' 
                     ORDER BY created_at DESC";
$pendingLoansStmt = mysqli_prepare($conn, $pendingLoansQuery);
mysqli_stmt_bind_param($pendingLoansStmt, "i", $user['id']);
mysqli_stmt_execute($pendingLoansStmt);
$pendingLoansResult = mysqli_stmt_get_result($pendingLoansStmt);

// Get payment history
$paymentHistoryQuery = "SELECT p.*, la.loan_type FROM payments p 
                       LEFT JOIN loan_applications la ON p.loan_id = la.id 
                       WHERE p.user_id = ? 
                       ORDER BY p.payment_date DESC";
$paymentHistoryStmt = mysqli_prepare($conn, $paymentHistoryQuery);
mysqli_stmt_bind_param($paymentHistoryStmt, "i", $user['id']);
mysqli_stmt_execute($paymentHistoryStmt);
$paymentHistoryResult = mysqli_stmt_get_result($paymentHistoryStmt);

// Get notifications
$notificationsQuery = "SELECT * FROM notifications 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT 10";
$notificationsStmt = mysqli_prepare($conn, $notificationsQuery);
mysqli_stmt_bind_param($notificationsStmt, "i", $user['id']);
mysqli_stmt_execute($notificationsStmt);
$notifications = mysqli_stmt_get_result($notificationsStmt);

// Get unread notifications count
$unreadCountQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unreadCountStmt = mysqli_prepare($conn, $unreadCountQuery);
mysqli_stmt_bind_param($unreadCountStmt, "i", $user['id']);
mysqli_stmt_execute($unreadCountStmt);
$unreadCountResult = mysqli_stmt_get_result($unreadCountStmt);
$unreadCount = mysqli_fetch_assoc($unreadCountResult)['count'];
mysqli_stmt_close($unreadCountStmt);

// Handle AJAX Payment Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['payment-proof'])) {
    $response = array('success' => false, 'error' => '');

    try {
        if (!isset($_POST['loan_id']) || !isset($_POST['payment_amount'])) {
            throw new Exception('Missing required payment information');
        }

        $loan_id = intval($_POST['loan_id']);
        $paymentAmount = floatval($_POST['payment_amount']);
        
        if ($loan_id <= 0) {
            throw new Exception('Invalid loan ID');
        }
        
        if ($paymentAmount <= 0) {
            throw new Exception('Payment amount must be greater than zero');
        }

        $paymentInfo = getCurrentPaymentDue($loan_id, $conn);
        
        if (!$paymentInfo) {
            throw new Exception('Loan not found');
        }
        
        if (!$paymentInfo['can_pay']) {
            throw new Exception('No payment is currently due for this loan');
        }
        
        if ($paymentAmount > $paymentInfo['current_payment_amount']) {
            throw new Exception('Payment amount cannot exceed the current payment due of ₱' . number_format($paymentInfo['current_payment_amount'], 2) . '. Advance payments are not allowed.');
        }
        
        $tolerance = 0.01;
        if (abs($paymentAmount - $paymentInfo['current_payment_amount']) > $tolerance) {
            throw new Exception('Please pay the exact amount due: ₱' . number_format($paymentInfo['current_payment_amount'], 2));
        }

        if (!isset($_FILES['payment-proof']) || $_FILES['payment-proof']['error'] != UPLOAD_ERR_OK) {
            $uploadError = $_FILES['payment-proof']['error'] ?? 'Unknown error';
            throw new Exception('Payment proof upload failed: ' . $uploadError);
        }

        $fileTmpPath = $_FILES['payment-proof']['tmp_name'];
        $fileName = basename($_FILES['payment-proof']['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, and PNG are allowed.');
        }

        $uploadDir = 'uploads/payment_proofs/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        $newFileName = uniqid('proof_', true) . '.' . $fileExtension;
        $filePath = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $filePath)) {
            throw new Exception('Failed to move uploaded file');
        }

        mysqli_begin_transaction($conn);

        try {
            $newRemainingBalance = $paymentInfo['remaining_balance'] - $paymentAmount;
            $paymentStatus = ($newRemainingBalance <= 0) ? 'Paid' : 'Pending';

            $updateQuery = "UPDATE loan_applications SET 
                            amount = ?, 
                            payment_status = ?, 
                            payment_proof = ?,
                            updated_at = NOW()
                            WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "dssi", $newRemainingBalance, $paymentStatus, $filePath, $loan_id);

            if (!mysqli_stmt_execute($updateStmt)) {
                throw new Exception('Error updating loan: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($updateStmt);

            $paymentNumber = $paymentInfo['payments_made'] + 1;
            $paymentRecordQuery = "INSERT INTO payments (user_id, loan_id, amount, payment_date, proof_path, payment_number) 
                                VALUES (?, ?, ?, NOW(), ?, ?)";
            $paymentStmt = mysqli_prepare($conn, $paymentRecordQuery);
            mysqli_stmt_bind_param($paymentStmt, "iidsi", $paymentInfo['loan']['user_id'], $loan_id, $paymentAmount, $filePath, $paymentNumber);

            if (!mysqli_stmt_execute($paymentStmt)) {
                throw new Exception('Error recording payment: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($paymentStmt);

            // Create notification for user
            if ($paymentStatus == 'Paid') {
                $notificationMessage = "Congratulations! Your loan #" . $loan_id . " has been fully paid.";
                $response['message'] = 'Final payment completed! Your loan is now fully paid. You may apply for a new loan.';
            } else {
                $notificationMessage = "Payment #" . $paymentNumber . " of ₱" . number_format($paymentAmount, 2) . " for loan #" . $loan_id . " has been processed.";
                $response['message'] = "Payment $paymentNumber of {$paymentInfo['loan']['term']} completed successfully! Next payment due: ₱" . number_format($paymentInfo['monthly_payment'], 2);
            }
            
            $userNotificationQuery = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                    VALUES (?, ?, 'payment_processed', 0, NOW())";
            $userNotifStmt = mysqli_prepare($conn, $userNotificationQuery);
            mysqli_stmt_bind_param($userNotifStmt, "is", $user['id'], $notificationMessage);
            mysqli_stmt_execute($userNotifStmt);
            mysqli_stmt_close($userNotifStmt);

            // Create notification for admin
            $adminQuery = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
            $adminResult = mysqli_query($conn, $adminQuery);
            $admin = mysqli_fetch_assoc($adminResult);
            
            if ($admin) {
                $adminNotificationMessage = $user['username'] . " has made payment #" . $paymentNumber . " of ₱" . number_format($paymentAmount, 2) . " for loan #" . $loan_id;
                $adminNotificationQuery = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                        VALUES (?, ?, 'payment_received', 0, NOW())";
                $adminNotifStmt = mysqli_prepare($conn, $adminNotificationQuery);
                mysqli_stmt_bind_param($adminNotifStmt, "is", $admin['id'], $adminNotificationMessage);
                mysqli_stmt_execute($adminNotifStmt);
                mysqli_stmt_close($adminNotifStmt);
            }

            mysqli_commit($conn);

            $response['success'] = true;
            $response['loan_id'] = $loan_id;
            $response['payment_number'] = $paymentNumber;
            $response['remaining_balance'] = $newRemainingBalance;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('Payment processing error: ' . $e->getMessage());
        $response['error'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoanPro Dashboard - Complete Loan Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            width: 280px;
            background: var(--primary-gradient);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            color: white;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            position: relative;
        }

        .sidebar-logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .sidebar-subtitle {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .notification-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px 0;
        }

        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu-item {
            margin-bottom: 5px;
        }

        .sidebar-menu-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }

        .sidebar-menu-link:hover, .sidebar-menu-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #ffc107;
            transform: translateX(5px);
        }

        .sidebar-menu-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .sidebar-menu-icon {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .dashboard-card {
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            background: white;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .dashboard-card .card-header {
            background: var(--primary-gradient);
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
            border: none;
            padding: 25px;
        }

        .stats-card {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(45deg);
            transition: all 0.3s ease;
        }

        .stats-card:hover::before {
            transform: rotate(45deg) scale(1.2);
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .loan-application-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }

        .loan-application-card.pending {
            border-left-color: #ffc107;
        }

        .loan-application-card.approved {
            border-left-color: #28a745;
        }

        .loan-application-card.rejected {
            border-left-color: #dc3545;
        }

        .loan-application-card.cancelled {
            border-left-color: #6c757d;
        }

        .loan-application-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: var(--warning-gradient);
            color: white;
        }

        .status-badge.approved {
            background: var(--success-gradient);
            color: white;
        }

        .status-badge.rejected {
            background: var(--danger-gradient);
            color: white;
        }

        .status-badge.cancelled {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }

        .btn-gradient-primary {
            background: var(--primary-gradient);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-gradient-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-gradient-primary:hover::before {
            left: 100%;
        }

        .btn-gradient-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-gradient-success {
            background: var(--success-gradient);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .btn-gradient-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }

        .btn-gradient-danger {
            background: var(--danger-gradient);
            border: none;
            color: white;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .btn-gradient-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: translateY(-1px);
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
            padding: 25px;
        }

        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .notification-item.unread {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f0ff 100%);
            border-left-color: #ffc107;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .loan-calculator {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .calculator-result {
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }

        .profile-picture-container {
            position: relative;
            display: inline-block;
        }

        .profile-picture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }

        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }

        .editable-field {
            position: relative;
            transition: all 0.3s ease;
        }

        .editable-field:hover {
            background-color: rgba(102, 126, 234, 0.05);
            border-radius: 8px;
            padding: 8px;
            margin: -8px;
        }

        .edit-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: opacity 0.3s ease;
            color: #667eea;
            cursor: pointer;
        }

        .editable-field:hover .edit-icon {
            opacity: 1;
        }

        .profile-form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .profile-form-group.editing {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(102, 126, 234, 0.05) 100%);
            border-radius: 12px;
            padding: 15px;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .dashboard-card {
                margin-bottom: 20px;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in-left {
            animation: slideInLeft 0.5s ease-out;
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom scrollbar */
        .sidebar-content::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">LoanPro</div>
        <div class="sidebar-subtitle">Complete Loan Management</div>
        <?php if ($unreadCount > 0): ?>
            <div class="notification-badge"><?= $unreadCount ?></div>
        <?php endif; ?>
    </div>
    <div class="sidebar-content">
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="#dashboard" class="sidebar-menu-link active" data-section="dashboard">
                    <span class="sidebar-menu-icon"><i class="bi bi-speedometer2"></i></span>
                    Dashboard
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#applications" class="sidebar-menu-link" data-section="applications">
                    <span class="sidebar-menu-icon"><i class="bi bi-file-earmark-text"></i></span>
                    My Applications
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#loans" class="sidebar-menu-link" data-section="loans">
                    <span class="sidebar-menu-icon"><i class="bi bi-cash-coin"></i></span>
                    Active Loans
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#" class="sidebar-menu-link <?= $has_unpaid_loan ? 'disabled' : '' ?>" 
                   data-bs-toggle="modal" 
                   data-bs-target="#loanApplicationModal"
                   title="<?= $has_unpaid_loan ? 'You have unpaid loans' : 'Apply for a new loan' ?>">
                    <span class="sidebar-menu-icon"><i class="bi bi-plus-circle"></i></span>
                    Apply for Loan
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#payments" class="sidebar-menu-link" data-section="payments">
                    <span class="sidebar-menu-icon"><i class="bi bi-credit-card"></i></span>
                    Payment History
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#notifications" class="sidebar-menu-link" data-section="notifications">
                    <span class="sidebar-menu-icon">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </span>
                    Notifications
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#calculator" class="sidebar-menu-link" data-section="calculator">
                    <span class="sidebar-menu-icon"><i class="bi bi-calculator"></i></span>
                    Loan Calculator
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#profile" class="sidebar-menu-link" data-section="profile">
                    <span class="sidebar-menu-icon"><i class="bi bi-person-circle"></i></span>
                    My Profile
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="#" class="sidebar-menu-link" data-bs-toggle="modal" data-bs-target="#helpModal">
                    <span class="sidebar-menu-icon"><i class="bi bi-question-circle"></i></span>
                    Help & Support
                </a>
            </li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <div class="d-flex align-items-center mb-3">
            <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://via.placeholder.com/40' ?>" 
                 alt="Profile" class="rounded-circle me-2" width="40" height="40">
            <div>
                <div class="fw-bold"><?= htmlspecialchars($user['username']) ?></div>
                <small class="opacity-75"><?= htmlspecialchars($borrowerStatus) ?></small>
            </div>
        </div>
        <form action="logout.php" method="post">
            <button type="submit" class="btn btn-outline-light w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </button>
        </form>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Mobile menu toggle -->
    <button class="btn btn-gradient-primary d-md-none mb-3" type="button" onclick="toggleSidebar()">
        <i class="bi bi-list"></i> Menu
    </button>

    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show slide-in-left" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show slide-in-left" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Dashboard Section -->
    <div id="dashboard-section" class="content-section fade-in">
        <div class="row">
            <div class="col-12 mb-4">
                <h1 class="display-6 fw-bold text-dark">Welcome back, <?= htmlspecialchars($user['username']) ?>!</h1>
                <p class="lead text-muted">Manage your loans and track your financial journey</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?= $userStats['total_applications'] ?></div>
                    <div class="stats-label">Total Applications</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: var(--success-gradient);">
                    <div class="stats-number"><?= $userStats['approved_loans'] ?></div>
                    <div class="stats-label">Approved Loans</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: var(--warning-gradient);">
                    <div class="stats-number"><?= $userStats['pending_loans'] ?></div>
                    <div class="stats-label">Pending Applications</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: var(--info-gradient);">
                    <div class="stats-number">₱<?= number_format($userStats['total_paid'], 0) ?></div>
                    <div class="stats-label">Total Paid</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (!$has_unpaid_loan): ?>
                                <button class="btn btn-gradient-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loanApplicationModal">
                                    <i class="bi bi-plus-circle me-2"></i>Apply for New Loan
                                </button>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    You have unpaid loans. Complete payments to apply for new loans.
                                </div>
                            <?php endif; ?>
                            <button class="btn btn-outline-primary" onclick="showSection('payments')">
                                <i class="bi bi-credit-card me-2"></i>View Payment History
                            </button>
                            <button class="btn btn-outline-success" onclick="showSection('calculator')">
                                <i class="bi bi-calculator me-2"></i>Loan Calculator
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Notifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($notifications) > 0): ?>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php 
                                mysqli_data_seek($notifications, 0);
                                $count = 0;
                                while (($notification = mysqli_fetch_assoc($notifications)) && $count < 3): 
                                    $count++;
                                ?>
                                    <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                                        <div class="fw-bold"><?= htmlspecialchars($notification['message']) ?></div>
                                        <div class="notification-time"><?= date('M d, Y H:i', strtotime($notification['created_at'])) ?></div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary btn-sm" onclick="showSection('notifications')">
                                    View All Notifications
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="bi bi-bell-slash" style="font-size: 2rem;"></i>
                                <p class="mt-2">No notifications yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Borrower Status -->
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Borrower Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="text-primary"><?= htmlspecialchars($borrowerStatus) ?></h4>
                                <p class="text-muted mb-0">
                                    <?php if ($isFirstTimeBorrower): ?>
                                        Welcome to LoanPro! As a first-time borrower, you have access to our starter loan program with special terms. Maximum loan amount: ₱<?= number_format($maxLoanAmount, 2) ?>
                                    <?php else: ?>
                                        Thank you for being a valued customer! You have access to our full range of loan products with higher limits and better terms.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="stats-card" style="background: var(--success-gradient); margin-bottom: 0;">
                                    <div class="stats-number">₱<?= number_format($maxLoanAmount, 0) ?></div>
                                    <div class="stats-label">Max Loan Amount</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Applications Section -->
    <div id="applications-section" class="content-section" style="display: none;">
        <div class="dashboard-card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>My Loan Applications</h4>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($allLoansResult) > 0): ?>
                    <div class="row">
                        <?php while ($loan = mysqli_fetch_assoc($allLoansResult)): ?>
                            <div class="col-md-6 mb-4">
                                <div class="loan-application-card <?= strtolower($loan['status']) ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($loan['loan_type']) ?></h5>
                                            <small class="text-muted">Application #<?= $loan['id'] ?></small>
                                        </div>
                                        <span class="status-badge <?= strtolower($loan['status']) ?>">
                                            <?= htmlspecialchars($loan['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Amount Requested:</small>
                                            <div class="fw-bold">₱<?= number_format($loan['principal_amount'], 2) ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Total Amount:</small>
                                            <div class="fw-bold">₱<?= number_format($loan['amount'], 2) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Term:</small>
                                            <div class="fw-bold"><?= $loan['term'] ?> months</div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Interest Rate:</small>
                                            <div class="fw-bold"><?= $loan['interest_rate'] ?>%</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Purpose:</small>
                                        <div><?= htmlspecialchars($loan['purpose']) ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Applied on:</small>
                                        <div><?= date('M d, Y H:i', strtotime($loan['created_at'])) ?></div>
                                    </div>
                                    
                                    <?php if ($loan['status'] == 'Pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="cancel_loan_id" value="<?= $loan['id'] ?>">
                                            <button type="submit" class="btn btn-gradient-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to cancel this loan application?')">
                                                <i class="bi bi-x-circle me-1"></i>Cancel Application
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #ccc;"></i>
                        <h4 class="mt-3 text-muted">No Applications Yet</h4>
                        <p class="text-muted">You haven't submitted any loan applications.</p>
                        <?php if (!$has_unpaid_loan): ?>
                            <button class="btn btn-gradient-primary" data-bs-toggle="modal" data-bs-target="#loanApplicationModal">
                                <i class="bi bi-plus-circle me-2"></i>Submit Your First Application
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Active Loans Section -->
    <div id="loans-section" class="content-section" style="display: none;">
        <div class="dashboard-card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Active Loans</h4>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($approvedLoansResult) > 0): ?>
                    <div class="row">
                        <?php while ($loan = mysqli_fetch_assoc($approvedLoansResult)): ?>
                            <?php 
                            $paymentInfo = getCurrentPaymentDue($loan['id'], $conn);
                            $monthlyPayment = calculateMonthlyPayment($loan['amount'], $loan['term']);
                            ?>
                            <div class="col-md-6 mb-4">
                                <div class="loan-application-card approved">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0"><?= htmlspecialchars($loan['loan_type']) ?></h5>
                                        <?php if ($loan['payment_status'] == 'Paid'): ?>
                                            <span class="status-badge" style="background: var(--success-gradient);">PAID</span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background: var(--warning-gradient);">PAYING</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Loan Summary -->
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Remaining Balance:</small>
                                            <div class="fw-bold text-primary fs-5">₱<?= number_format($loan['amount'], 2) ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Monthly Payment:</small>
                                            <div class="fw-bold text-success fs-5">₱<?= number_format($monthlyPayment, 2) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Term:</small>
                                            <div class="fw-bold"><?= $loan['term'] ?> months</div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Payments Made:</small>
                                            <div class="fw-bold"><?= $paymentInfo ? $paymentInfo['payments_made'] : 0 ?> / <?= $loan['term'] ?></div>
                                        </div>
                                    </div>

                                    <!-- Payment Progress -->
                                    <?php if ($paymentInfo): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Payment Progress:</small>
                                            <div class="progress mt-1" style="height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= ($paymentInfo['payments_made'] / $loan['term']) * 100 ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= round(($paymentInfo['payments_made'] / $loan['term']) * 100, 1) ?>% Complete</small>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Current Payment Due -->
                                    <?php if ($paymentInfo && $paymentInfo['can_pay']): ?>
                                        <div class="alert alert-warning">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>Payment <?= $paymentInfo['current_payment_number'] ?> Due:</strong><br>
                                                    <span class="fw-bold text-success fs-5">₱<?= number_format($paymentInfo['current_payment_amount'], 2) ?></span>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">Due Date:</small><br>
                                                    <strong><?= $paymentInfo['next_payment_date']->format('M d, Y') ?></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Payment Button -->
                                        <button class="btn btn-gradient-success btn-lg w-100" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#paymentModal" 
                                                data-loan-id="<?= $loan['id'] ?>" 
                                                data-payment-amount="<?= $paymentInfo['current_payment_amount'] ?>"
                                                data-payment-number="<?= $paymentInfo['current_payment_number'] ?>"
                                                data-due-date="<?= $paymentInfo['next_payment_date']->format('M d, Y') ?>">
                                            <i class="bi bi-credit-card me-2"></i> 
                                            Pay ₱<?= number_format($paymentInfo['current_payment_amount'], 2) ?> Now
                                        </button>
                                    <?php else: ?>
                                        <div class="alert alert-success text-center">
                                            <i class="bi bi-check-circle-fill me-2"></i>
                                            All payments completed! Loan fully paid.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cash-coin" style="font-size: 4rem; color: #ccc;"></i>
                        <h4 class="mt-3 text-muted">No Active Loans</h4>
                        <p class="text-muted">You don't have any active loans at the moment.</p>
                        <?php if (!$has_unpaid_loan): ?>
                            <button class="btn btn-gradient-primary" data-bs-toggle="modal" data-bs-target="#loanApplicationModal">
                                <i class="bi bi-plus-circle me-2"></i>Apply for Your First Loan
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment History Section -->
    <div id="payments-section" class="content-section" style="display: none;">
        <div class="dashboard-card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment History</h4>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($paymentHistoryResult) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Loan Type</th>
                                    <th>Payment #</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = mysqli_fetch_assoc($paymentHistoryResult)): ?>
                                    <tr>
                                        <td><?= date("M d, Y", strtotime($payment['payment_date'])) ?></td>
                                        <td><?= htmlspecialchars($payment['loan_type'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-primary">#<?= $payment['payment_number'] ?? 'N/A' ?></span></td>
                                        <td class="fw-bold text-success">₱<?= number_format($payment['amount'], 2) ?></td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                        <td>
                                            <?php if ($payment['proof_path']): ?>
                                                <a href="<?= htmlspecialchars($payment['proof_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-image me-1"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No proof</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-credit-card" style="font-size: 4rem; color: #ccc;"></i>
                        <h4 class="mt-3 text-muted">No Payment History</h4>
                        <p class="text-muted">You haven't made any payments yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notifications Section -->
    <div id="notifications-section" class="content-section" style="display: none;">
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-bell me-2"></i>Notifications</h4>
                <?php if ($unreadCount > 0): ?>
                    <button class="btn btn-outline-light btn-sm" onclick="markAllNotificationsRead()">
                        <i class="bi bi-check-all me-1"></i>Mark All Read
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($notifications) > 0): ?>
                    <div id="notifications-container">
                        <?php 
                        mysqli_data_seek($notifications, 0);
                        while ($notification = mysqli_fetch_assoc($notifications)): 
                        ?>
                            <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>" 
                                 data-notification-id="<?= $notification['id'] ?>"
                                 onclick="markNotificationRead(<?= $notification['id'] ?>)">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= htmlspecialchars($notification['message']) ?></div>
                                        <div class="notification-time"><?= date('M d, Y H:i', strtotime($notification['created_at'])) ?></div>
                                        <?php if (!empty($notification['type'])): ?>
                                            <span class="badge bg-secondary mt-1"><?= ucfirst(str_replace('_', ' ', $notification['type'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <div class="text-primary">
                                            <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash" style="font-size: 4rem; color: #ccc;"></i>
                        <h4 class="mt-3 text-muted">No Notifications</h4>
                        <p class="text-muted">You're all caught up! No new notifications.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Loan Calculator Section -->
    <div id="calculator-section" class="content-section" style="display: none;">
        <div class="row">
            <div class="col-md-6">
                <div class="loan-calculator">
                    <h4 class="mb-4"><i class="bi bi-calculator me-2"></i>Loan Calculator</h4>
                    <form id="loanCalculatorForm">
                        <div class="mb-3">
                            <label class="form-label text-white">Loan Amount (₱)</label>
                            <input type="number" class="form-control" id="calc-amount" min="1000" max="<?= $maxLoanAmount ?>" step="100" value="10000">
                            <small class="text-white-50">Maximum: ₱<?= number_format($maxLoanAmount, 2) ?></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white">Interest Rate (%)</label>
                            <input type="number" class="form-control" id="calc-rate" min="1" max="30" step="0.1" value="12">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white">Loan Term (months)</label>
                            <select class="form-select" id="calc-term">
                                <option value="6">6 months</option>
                                <option value="12" selected>12 months</option>
                                <option value="18">18 months</option>
                                <option value="24">24 months</option>
                                <option value="36">36 months</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-light w-100" onclick="calculateLoan()">
                            <i class="bi bi-calculator me-2"></i>Calculate
                        </button>
                    </form>
                    
                    <div class="calculator-result" id="calculator-result" style="display: none;">
                        <h6 class="text-white mb-3">Calculation Results:</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-white-50">Monthly Payment:</div>
                                <div class="fw-bold fs-5" id="monthly-payment">₱0.00</div>
                            </div>
                            <div class="col-6">
                                <div class="text-white-50">Total Interest:</div>
                                <div class="fw-bold fs-5" id="total-interest">₱0.00</div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <div class="text-white-50">Total Amount:</div>
                                <div class="fw-bold fs-5" id="total-amount">₱0.00</div>
                            </div>
                            <div class="col-6">
                                <div class="text-white-50">Interest Rate:</div>
                                <div class="fw-bold fs-5" id="effective-rate">0%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Loan Information</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold">Available Loan Types:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>Personal Loan:</strong> For personal expenses, medical bills, education
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>Business Loan:</strong> For business capital, equipment, expansion
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>Emergency Loan:</strong> For urgent financial needs
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>Education Loan:</strong> For tuition fees, school expenses
                            </li>
                        </ul>
                        
                        <h6 class="fw-bold mt-4">Interest Rates:</h6>
                        <ul class="list-unstyled">
                            <li><strong>Personal Loan:</strong> 12-18% per annum</li>
                            <li><strong>Business Loan:</strong> 10-15% per annum</li>
                            <li><strong>Emergency Loan:</strong> 15-20% per annum</li>
                            <li><strong>Education Loan:</strong> 8-12% per annum</li>
                        </ul>
                        
                        <h6 class="fw-bold mt-4">Requirements:</h6>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check text-primary me-2"></i>Valid Government ID</li>
                            <li><i class="bi bi-check text-primary me-2"></i>Proof of Income</li>
                            <li><i class="bi bi-check text-primary me-2"></i>Bank Statements (3 months)</li>
                            <li><i class="bi bi-check text-primary me-2"></i>Proof of Address</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Section -->
    <div id="profile-section" class="content-section" style="display: none;">
        <div class="row">
            <div class="col-md-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Profile Picture</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-picture-container mb-3">
                            <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://via.placeholder.com/120' ?>" 
                                 alt="Profile Picture" class="rounded-circle" width="120" height="120" style="object-fit: cover;" id="profile-picture-preview">
                            <div class="profile-picture-overlay" onclick="document.getElementById('profile-picture-input').click()">
                                <i class="bi bi-camera text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <h4><?= htmlspecialchars($user['username']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                        <span class="badge bg-primary"><?= htmlspecialchars($borrowerStatus) ?></span>
                        <hr>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>Click on any field below to edit your information. Changes are saved automatically.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editable Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="file" name="profile_picture" id="profile-picture-input" style="display: none;" accept=".jpg,.jpeg,.png" onchange="previewProfilePicture(this)">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="profile-form-group">
                                        <label class="form-label fw-bold">Full Name</label>
                                        <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-form-group">
                                        <label class="form-label fw-bold">Email Address</label>
                                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                        <small class="text-muted">Email cannot be changed for security reasons</small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-form-group">
                                        <label class="form-label fw-bold">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Enter your phone number">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-form-group">
                                        <label class="form-label fw-bold">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <div class="profile-form-group">
                                        <label class="form-label fw-bold">Address</label>
                                        <textarea class="form-control" name="address" rows="3" placeholder="Enter your complete address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-form-group">
                                        <label class="form-label fw-bold">Occupation</label>
                                        <input type="text" class="form-control" name="occupation" value="<?= htmlspecialchars($user['occupation'] ?? '') ?>" placeholder="Enter your occupation">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-form-group">
                                        <label class="form-label fw-bold">Monthly Income (₱)</label>
                                        <input type="number" class="form-control" name="monthly_income" value="<?= htmlspecialchars($user['monthly_income'] ?? '') ?>" min="0" step="1000" placeholder="Enter your monthly income">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-gradient-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Save Profile Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loan Application Modal -->
<div class="modal fade" id="loanApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Apply for Loan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="loanApplicationForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loan Type</label>
                            <select class="form-select" name="loan_type" required>
                                <option value="">Select loan type</option>
                                <option value="Personal Loan">Personal Loan</option>
                                <option value="Business Loan">Business Loan</option>
                                <option value="Emergency Loan">Emergency Loan</option>
                                <option value="Education Loan">Education Loan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Principal Amount (₱)</label>
                            <input type="number" class="form-control" name="principal_amount" min="1000" max="<?= $maxLoanAmount ?>" step="100" required>
                            <small class="text-muted">Maximum: ₱<?= number_format($maxLoanAmount, 2) ?></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loan Term</label>
                            <select class="form-select" name="term" required>
                                <option value="">Select term</option>
                                <option value="6">6 months</option>
                                <option value="12">12 months</option>
                                <option value="18">18 months</option>
                                <option value="24">24 months</option>
                                <option value="36">36 months</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interest Rate (%)</label>
                            <select class="form-select" name="interest_rate" required>
                                <option value="">Select rate</option>
                                <option value="8">8% (Education Loan)</option>
                                <option value="10">10% (Business Loan)</option>
                                <option value="12">12% (Personal Loan)</option>
                                <option value="15">15% (Emergency Loan)</option>
                                <option value="18">18% (High Risk)</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Purpose of Loan</label>
                            <textarea class="form-control" name="purpose" rows="3" required placeholder="Please describe the purpose of your loan application"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employment Status</label>
                            <select class="form-select" name="employment_status" required>
                                <option value="">Select status</option>
                                <option value="Employed">Employed</option>
                                <option value="Self-Employed">Self-Employed</option>
                                <option value="Business Owner">Business Owner</option>
                                <option value="Freelancer">Freelancer</option>
                                <option value="Student">Student</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employer/Company Name</label>
                            <input type="text" class="form-control" name="employer_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Years of Employment</label>
                            <input type="number" class="form-control" name="years_employed" min="0" max="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monthly Income (₱)</label>
                            <input type="number" class="form-control" name="monthly_income" min="5000" step="1000" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Work/Occupation</label>
                            <input type="text" class="form-control" name="work" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ID Type</label>
                            <select class="form-select" name="id_type" required>
                                <option value="">Select ID type</option>
                                <option value="Driver's License">Driver's License</option>
                                <option value="Passport">Passport</option>
                                <option value="SSS ID">SSS ID</option>
                                <option value="PhilHealth ID">PhilHealth ID</option>
                                <option value="Postal ID">Postal ID</option>
                                <option value="Voter's ID">Voter's ID</option>
                                <option value="TIN ID">TIN ID</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Supporting Documents</label>
                            <input type="file" class="form-control" name="document" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted">Upload your ID, proof of income, or other supporting documents (JPG, PNG, PDF - Max 5MB)</small>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Note:</strong> Your loan application will be reviewed within 24-48 hours. 
                                You will receive a notification once the review is complete.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_loan_application" class="btn btn-gradient-primary">
                        <i class="bi bi-check-circle me-2"></i>Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Monthly Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Payment Information -->
                <div class="payment-summary p-3 mb-4" style="background: linear-gradient(135deg, rgba(25, 135, 84, 0.1) 0%, rgba(25, 135, 84, 0.05) 100%); border-radius: 12px; border: 1px solid rgba(25, 135, 84, 0.2);">
                    <div class="text-center">
                        <div class="text-muted mb-1">Payment <span id="payment-number-display"></span></div>
                        <div class="fw-bold text-success" id="payment-amount-display" style="font-size: 2rem;"></div>
                        <div class="text-muted mt-1">Due: <span id="payment-due-date-display"></span></div>
                    </div>
                </div>

                <!-- Payment Restriction Notice -->
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Important:</strong> You must pay the exact amount shown above. Partial or advance payments are not allowed.
                </div>

                <p class="mb-3">Scan the QR code below to complete your payment:</p>
                <img src="gcash.jpg" alt="QR Code" style="max-width: 200px;" class="img-fluid mb-3">

                <!-- Payment Form -->
                <form id="paymentForm" method="POST" enctype="multipart/form-data">
                    <div class="form-group mb-3">
                        <label for="payment-proof" class="d-block fw-bold mb-2">Upload Payment Proof:</label>
                        <input type="file" id="payment-proof" name="payment-proof" class="form-control" required accept=".jpg,.jpeg,.png">
                        <small class="text-muted">Only JPG, JPEG, and PNG files are allowed</small>
                    </div>

                    <div class="form-group mb-3">
                        <label for="payment-amount" class="d-block fw-bold mb-2">Payment Amount (Fixed):</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" id="payment-amount" name="payment_amount" class="form-control form-control-lg" readonly>
                        </div>
                        <small class="text-muted">Amount is fixed and cannot be changed</small>
                    </div>

                    <input type="hidden" name="loan_id" id="loan-id" value="">

                    <button type="submit" class="btn btn-gradient-success btn-lg mt-4 w-100">
                        <i class="bi bi-check-circle me-2"></i> Submit Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-question-circle me-2"></i>Help & Support</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div style="background: var(--primary-gradient); color: white; border-radius: 15px; padding: 25px;">
                            <h6 class="fw-bold mb-3"><i class="bi bi-headset me-2"></i>Contact Support</h6>
                            <div class="mb-3">
                                <strong>Phone:</strong><br>
                                <a href="tel:+1234567890" class="text-white">+1 (234) 567-8900</a>
                            </div>
                            <div class="mb-3">
                                <strong>Email:</strong><br>
                                <a href="mailto:support@loanpro.com" class="text-white">support@loanpro.com</a>
                            </div>
                            <div class="mb-3">
                                <strong>Live Chat:</strong><br>
                                <button class="btn btn-outline-light btn-sm">Start Chat</button>
                            </div>
                            <div>
                                <strong>Office Hours:</strong><br>
                                Monday - Friday: 8AM - 6PM<br>
                                Saturday: 9AM - 3PM
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h6 class="fw-bold mb-3">Frequently Asked Questions</h6>
                        
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                        How do I apply for a loan?
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        To apply for a loan, click on "Apply for Loan" in the sidebar. Fill out the application form with your personal and financial information, upload required documents, and submit. You'll receive a notification once your application is reviewed.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                        What documents do I need for loan application?
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        You'll need a valid government-issued ID, proof of income (pay stubs, bank statements), and proof of address. Additional documents may be required based on the loan type and amount.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                        How do I make a payment?
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Go to "Active Loans" section, find your loan, and click "Pay Now". Scan the QR code to make payment via GCash or other digital wallets, then upload your payment proof. Payments must be made in exact monthly amounts.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                        Can I pay in advance or make partial payments?
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        No, advance payments and partial payments are not allowed. This policy ensures proper payment tracking and maintains the integrity of your payment schedule. You must pay the exact monthly amount due.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-gradient-primary">
                    <i class="bi bi-envelope me-2"></i>Contact Support
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentSection = 'dashboard';

// Sidebar navigation
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Section navigation
function showSection(sectionName) {
    // Remove active class from all links
    document.querySelectorAll('.sidebar-menu-link').forEach(l => l.classList.remove('active'));
    
    // Add active class to clicked link
    const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
    
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Show selected section
    const section = document.getElementById(`${sectionName}-section`);
    if (section) {
        section.style.display = 'block';
        section.classList.add('fade-in');
        currentSection = sectionName;
    }
}

// Section navigation event listeners
document.querySelectorAll('[data-section]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const sectionName = this.getAttribute('data-section');
        showSection(sectionName);
    });
});

// Profile picture preview
function previewProfilePicture(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profile-picture-preview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Loan Calculator
function calculateLoan() {
    const amount = parseFloat(document.getElementById('calc-amount').value);
    const rate = parseFloat(document.getElementById('calc-rate').value);
    const term = parseInt(document.getElementById('calc-term').value);
    
    if (!amount || !rate || !term) {
        alert('Please fill in all fields');
        return;
    }
    
    // Calculate interest and total amount
    const interestAmount = (amount * rate / 100) * (term / 12);
    const totalAmount = amount + interestAmount;
    const monthlyPayment = totalAmount / term;
    
    // Display results
    document.getElementById('monthly-payment').textContent = `₱${monthlyPayment.toFixed(2)}`;
    document.getElementById('total-interest').textContent = `₱${interestAmount.toFixed(2)}`;
    document.getElementById('total-amount').textContent = `₱${totalAmount.toFixed(2)}`;
    document.getElementById('effective-rate').textContent = `${rate}%`;
    
    document.getElementById('calculator-result').style.display = 'block';
}

// Auto-calculate when inputs change
document.getElementById('calc-amount').addEventListener('input', calculateLoan);
document.getElementById('calc-rate').addEventListener('input', calculateLoan);
document.getElementById('calc-term').addEventListener('change', calculateLoan);

// Payment modal functionality
var paymentModal = document.getElementById('paymentModal');
if (paymentModal) {
    paymentModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var loanId = button.getAttribute('data-loan-id');
        var paymentAmount = button.getAttribute('data-payment-amount');
        var paymentNumber = button.getAttribute('data-payment-number');
        var dueDate = button.getAttribute('data-due-date');

        document.getElementById('loan-id').value = loanId;
        document.getElementById('payment-amount').value = parseFloat(paymentAmount).toFixed(2);
        document.getElementById('payment-amount-display').textContent = `₱${parseFloat(paymentAmount).toFixed(2)}`;
        document.getElementById('payment-number-display').textContent = paymentNumber;
        document.getElementById('payment-due-date-display').textContent = dueDate;
    });
}

// Payment form submission
var paymentForm = document.getElementById('paymentForm');
if (paymentForm) {
    paymentForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<div class="loading-spinner me-2"></div> Processing...';

        var formData = new FormData(this);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;

            if (data.success) {
                // Show success message
                showNotification(data.message || 'Payment processed successfully!', 'success');
                
                // Close the payment modal
                var modal = bootstrap.Modal.getInstance(paymentModal);
                if (modal) {
                    modal.hide();
                }

                // Refresh the page to update the UI
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                showNotification('Error processing payment: ' + (data.error || 'Unknown error occurred. Please try again.'), 'error');
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            console.error('Error:', error);
            showNotification('An error occurred while processing the payment. Please check your connection and try again.', 'error');
        });
    });
}

// Notification functions
function markNotificationRead(notificationId) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `mark_notification_read=1&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.remove('unread');
                const unreadIndicator = notificationElement.querySelector('.bi-circle-fill');
                if (unreadIndicator) {
                    unreadIndicator.remove();
                }
            }
            updateNotificationCount();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllNotificationsRead() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_all_notifications_read=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const unreadIndicator = item.querySelector('.bi-circle-fill');
                if (unreadIndicator) {
                    unreadIndicator.remove();
                }
            });
            updateNotificationCount();
            showNotification('All notifications marked as read', 'success');
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateNotificationCount() {
    const badges = document.querySelectorAll('.notification-badge, .badge');
    badges.forEach(badge => {
        if (badge.textContent.trim() !== '') {
            badge.style.display = 'none';
        }
    });
}

// Show notification function
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show slide-in-left" role="alert">
            <i class="bi ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const alertContainer = document.querySelector('.main-content');
    alertContainer.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

// Form validation
document.getElementById('loanApplicationForm').addEventListener('submit', function(e) {
    const amount = parseFloat(this.querySelector('[name="principal_amount"]').value);
    const maxAmount = <?= $maxLoanAmount ?>;
    
    if (amount > maxAmount) {
        e.preventDefault();
        showNotification(`Maximum loan amount is ₱${maxAmount.toLocaleString()}`, 'error');
        return false;
    }
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Initialize calculator on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateLoan();
});

// Smooth scrolling for better UX
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

// Add loading states to buttons
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading-spinner me-2"></div> Processing...';
            
            // Re-enable after 10 seconds as fallback
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 10000);
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
