<?php
// --- 1. START BUFFERING ---
ob_start(); 

// Include checking.php (handles session start & 'Remember Me')
require_once __DIR__ . '/../../checking.php';

// --- 2. TIMEZONE SETUP ---
date_default_timezone_set('Asia/Manila');

// --- 3. AUTHENTICATION CHECK ---
// If checking.php didn't log them in, kick them out.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php"); 
    exit;
}

// --- 4. ROLE ENFORCEMENT (GATEKEEPER) ---

$user_type = $_SESSION['usertype'] ?? null;

if ($user_type != 0) { 
    // Redirect based on the user's actual role
    switch ($user_type) {
        case 0: // Superadmin
            header("Location: ../superadmin/dashboard.php");
            break;
        case 2: // Employee
            header("Location: ../user/dashboard.php");
            break;
        default: // Unknown/Banned
            session_destroy();
            header("Location: ../index.php");
            break;
    }
    exit; // Stop script execution immediately
}

// --- 5. LOADER LOGIC ---
$show_loader = false; 
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    unset($_SESSION['show_loader']); 
    session_write_close();
    session_start();
}

// --- 6. GET SESSION DATA ---
$fullname = $_SESSION['fullname'] ?? 'Admin User';
$email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? 'default.png'; 

// --- 7. NOTIFICATIONS ---
$my_id = $_SESSION['user_id'] ?? 0;
$notifications = [];
$notif_count = 0;

$global_model_path = __DIR__ . '/../models/global_model.php';
if (file_exists($global_model_path) && isset($pdo)) {
    require_once $global_model_path;
    if (function_exists('get_my_notifications')) {
        $notifications = get_my_notifications($pdo, $my_id, 'Admin');
        $notif_count = count($notifications);
    }
}

// --- 8. PAGE TITLE & CLEAN BUFFER ---
$page_title ??= 'Super Admin Portal - LOPISv2';
ob_clean(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="icon" href="../assets/images/favicon.ico" type="image/ico">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../assets/vendor/fa6/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/dataTables.min.css" rel="stylesheet"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css">
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
                <p class="loader-text mt-3">Initializing workspace...</p>
            </div>
        </div>
    <?php endif; ?>

    <div id="wrapper">