<?php
// --- 1. START BUFFERING IMMEDIATELY ---
ob_start(); 

require_once __DIR__ . '/../../checking.php';

// --- 1.5 TIMEZONE SETUP ---
// Set default timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

// --- 2. SESSION AUTHENTICATION ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php"); 
    exit;
}

// --- 3. LOADER LOGIC ---
$show_loader = false; 
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    unset($_SESSION['show_loader']); 
    
    // Force-save session
    session_write_close();
    session_start();
}

// --- 4. ROLE-BASED ACCESS (Admin = 1) ---
// Note: You might want to allow Superadmin (0) here too if they share the same dashboard
$user_type = $_SESSION['usertype'] ?? null;
$redirect_map = [
    // 0: Super Admin (If they have a separate folder, keep this.)
    0 => '../superadmin/dashboard.php',
    // 2: Employee
    2 => '../user/dashboard.php', 
];

// Allow Admin (1). If Superadmin (0) should also access this, change to: ($user_type !== 1 && $user_type !== 0)
if ($user_type != 1) { 
    $redirect_url = $redirect_map[$user_type] ?? '../index.php';
    header("Location: $redirect_url");
    exit;
}

// --- 5. GET SESSION VARS ---
$fullname = $_SESSION['fullname'] ?? 'Admin User';
$email = $_SESSION['email'] ?? '';

// ⭐ ADDED: Profile Picture Logic (Matches Employee Header)
// This ensures topbar.php always has a valid filename to use.
$profile_picture = $_SESSION['profile_picture'] ?? 'default.png'; 

// --- 5.5 NOTIFICATION SETUP ---
$my_id = $_SESSION['user_id'] ?? $_SESSION['employee_id'] ?? 0;
$my_role = 'Admin'; // Admin Role

// Include Model to fetch data
$global_model_path = __DIR__ . '/../models/global_model.php';
if (file_exists($global_model_path)) {
    // Ensure $pdo exists from checking.php
    if (isset($pdo)) {
        require_once $global_model_path;
        $notifications = function_exists('get_my_notifications') ? get_my_notifications($pdo, $my_id, $my_role) : [];
        $notif_count = count($notifications);
    } else {
        $notifications = [];
        $notif_count = 0;
    }
} else {
    $notifications = [];
    $notif_count = 0;
}

// --- 6. PAGE TITLE ---
$page_title ??= 'Admin Portal - LOPISv2';

// --- 7. CLEAN THE BUFFER ---
ob_clean(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf8">
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