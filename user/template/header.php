<?php
// This is the Employee Header
// It contains session auth, loader logic, and the HTML <head>

require_once __DIR__ . '/../../checking.php';

// --- 1. SESSION AUTHENTICATION ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php"); // Use relative path to login
    exit;
}

// --- 2. LOADER LOGIC ---
$show_loader = false; // Default to false
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    unset($_SESSION['show_loader']);
    
    // Force-save the session
    session_write_close();
    session_start();
}

// --- 3. ROLE-BASED ACCESS (Employee = 2) ---
if ($_SESSION['usertype'] != 2) {
    // Redirect other users to their own dashboards
    if ($_SESSION['usertype'] == 0) {
        header("Location: ../superadmin/dashboard.php");
    } elseif ($_SESSION['usertype'] == 1) { 
        header("Location: ../admin/dashboard.php");
    } else {
        // Fallback for unknown types
        header("Location: ../index.php");
    }
    exit;
}

// --- 4. GET SESSION VARS ---
$fullname = $_SESSION['fullname'] ?? 'Employee';
$email = $_SESSION['email'] ?? '';
$firstName = $fullname ? htmlspecialchars(explode(' ', $fullname)[0]) : 'User';


// --- 5. PAGE TITLE (Set in parent file) ---
$page_title = $page_title ?? 'Employee Portal - LOPISv2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/images/favicon.ico" type="image/ico">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome (from your assets) -->
    <link href="../assets/vendor/fa6/css/all.min.css" rel="stylesheet" type="text/css">
    
    <!-- Google Fonts (Nunito) -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">    

    <!-- NEW Employee Stylesheet (Green Theme) -->
    <link href="../assets/css/portal_styles.css" rel="stylesheet">

    <!-- Loader Styles (Green Theme) -->
    <link href="../assets/css/loader_styles.css" rel="stylesheet">
</head>
<body id="page-top">

    <!-- Loader HTML -->
    <?php if ($show_loader): ?>
        <div id="page-loader" class="page-loader-wrapper d-flex align-items-center justify-content-center">
            <div class="loader-content text-center">
                <img src="../assets/images/LOPISv2.png" alt="LOSIS Logo" class="loader-logo">
                <div class="loader-progress mt-4 mb-2">
                    <div class="progress-bar"></div>
                </div>
                <span id="loader-percentage">0%</span>
                <p class="loader-text mt-3">Initializing your workspace...</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Wrapper -->
    <div id="wrapper" style="display-flex">