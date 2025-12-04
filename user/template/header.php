<?php
// This is the Employee Header
// It contains session auth, loader logic, and the HTML <head>

// 1. INCLUDE AUTHENTICATION (This starts the session and checks cookies)
require_once __DIR__ . '/../../checking.php';

// --- 2. SESSION AUTHENTICATION CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php"); // Redirect to login if not valid
    exit;
}

// --- 3. LOADER LOGIC ---
$show_loader = false; 
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    unset($_SESSION['show_loader']);
    
    // Force-save the session state so the loader flag is cleared immediately
    session_write_close();
    session_start();
}

// --- 4. ROLE-BASED ACCESS (Employee = 2) ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 2) {
    // Redirect logic for non-employees
    if (isset($_SESSION['usertype']) && $_SESSION['usertype'] == 0) {
        header("Location: ../superadmin/dashboard.php");
    } elseif (isset($_SESSION['usertype']) && $_SESSION['usertype'] == 1) { 
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}

// --- 5. GET SESSION VARS ---
$fullname = $_SESSION['fullname'] ?? 'Employee';
$email = $_SESSION['email'] ?? '';
$firstName = $fullname ? htmlspecialchars(explode(' ', $fullname)[0]) : 'User';

// ⭐ UPDATED: Fetch the photo using the key set in checking.php ('profile_picture')
// We also default to 'default.png' if it's missing to avoid NULL errors later.
$profile_picture = $_SESSION['profile_picture'] ?? 'default.png'; 

// --- 6. NOTIFICATION SETUP ---
$my_id = $_SESSION['employee_id'] ?? 0;
$my_role = 'Employee'; 

// Include Model to fetch notifications
// We check if $pdo exists (from checking.php) before using it
$global_model_path = __DIR__ . '/../models/global_model.php';

if (file_exists($global_model_path) && isset($pdo)) {
    require_once $global_model_path;
    
    // Check if the function exists to prevent fatal errors
    if (function_exists('get_my_notifications')) {
        $notifications = get_my_notifications($pdo, $my_id, $my_role);
        $notif_count = count($notifications);
    } else {
        $notifications = [];
        $notif_count = 0;
    }
} else {
    $notifications = [];
    $notif_count = 0;
}

// --- 7. PAGE TITLE ---
$page_title = $page_title ?? 'Employee Portal - LOPISv2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="icon" href="../assets/images/favicon.ico" type="image/ico">
    
    <link href="../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/fa6/css/all.min.css" rel="stylesheet" type="text/css">
    
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">    

    <link href="../assets/css/portal_styles.css" rel="stylesheet">
    <link href="../assets/css/loader_styles.css" rel="stylesheet">
</head>
<body id="page-top">

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

    <div id="wrapper" class="d-flex">