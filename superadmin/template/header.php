<?php

require_once __DIR__ . '/../../checking.php';
// --- 1. SESSION AUTHENTICATION ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php"); 
    exit;
}

// --- ADD THIS LOGIC FOR THE LOADER ---
$show_loader = false; // Default to false
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    // Flag exists, so we'll show the loader
    $show_loader = true;
    
    // Unset the flag so it doesn't show on refresh
    unset($_SESSION['show_loader']);
    // --- THIS IS THE FIX ---
    // Force the session to save (with 'show_loader' removed)
    session_write_close();
    // Re-open the session so the rest of the page can use it
    session_start();
}
// --- END OF LOADER LOGIC ---

// --- 2. ROLE-BASED ACCESS ---
if ($_SESSION['usertype'] != 0) {
    if ($_SESSION['usertype'] == 1) {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit;
}

// --- 3. GET SESSION VARS ---
$fullname = $_SESSION['fullname'] ?? 'Super Admin';
$email = $_SESSION['email'] ?? '';

// --- 4. PAGE TITLE (Set in parent file) ---
$page_title = $page_title ?? 'Super Admin Portal - LOPISv2';
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

    <!-- Custom styles for this template-->
    <link href="../assets/css/portal_styles.css" rel="stylesheet">
    <link href="../assets/css/loader_styles.css" rel="stylesheet">

</head>
<body id="page-top">

            <!-- Page Loader -->
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
    <div id="wrapper">