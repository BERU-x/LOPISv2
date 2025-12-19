<?php
// template/header.php
// This Global Header handles session checks, asset loading, and data fetching for ALL user types.

// --- 1. START BUFFERING ---
ob_start(); 

// --- 2. SESSION & CHECKING ---
// Adjust path: Assuming 'checking.php' is in the root directory (../checking.php from template/)
require_once __DIR__ . '/../checking.php';

// --- 3. TIMEZONE ---
date_default_timezone_set('Asia/Manila');

// --- 4. AUTHENTICATION CHECK ---
// If checking.php didn't log them in, kick them out.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php"); 
    exit;
}

// --- 5. GET USER DATA ---
$user_id       = $_SESSION['user_id'] ?? 0;
$user_type     = $_SESSION['usertype'] ?? 99; // 0=SA, 1=Admin, 2=Emp
$employee_id   = $_SESSION['employee_id'] ?? null;
$fullname      = $_SESSION['fullname'] ?? 'User';
$email         = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? 'default.png';

// Define User Type Label for display
$usertype_name = '';
switch ($user_type) {
    case 0: $usertype_name = 'Super Admin'; break;
    case 1: $usertype_name = 'Administrator'; break;
    case 2: $usertype_name = 'Employee'; break;
    default: $usertype_name = 'Guest'; break;
}

// --- 6. LOADER LOGIC ---
$show_loader = false; 
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    unset($_SESSION['show_loader']); 
    session_write_close();
    session_start();
}

// --- 7. NOTIFICATIONS (GLOBALIZED) ---
$notifications = [];
$notif_count = 0;

// Path to the new Global App Model
$global_model_path = __DIR__ . '/../app/models/global_app_model.php';

if (file_exists($global_model_path) && isset($pdo)) {
    require_once $global_model_path;
    
    if (function_exists('get_my_notifications')) {
        // Determine Target ID: Employees use EmployeeID, Admins use UserID (or null if role-based)
        $target_id = ($user_type == 2) ? $employee_id : $user_id;

        // Fetch using the Integer User Type (0, 1, 2)
        $notifications = get_my_notifications($pdo, $user_type, 10, $target_id);
        
        // Manual count of unread items
        foreach ($notifications as $n) {
            if ($n['is_read'] == 0) $notif_count++;
        }
    }
}

// --- 8. PAGE TITLE & BUFFER CLEAN ---
$page_title ??= 'LOPISv2 Portal';
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
                <img src="../assets/images/LOPISv2.png" alt="Logo" class="loader-logo">
                <div class="loader-progress mt-4 mb-2">
                    <div class="progress-bar"></div>
                </div>
                <span id="loader-percentage">0%</span>
                <p class="loader-text mt-3">Initializing workspace...</p>
            </div>
        </div>
    <?php endif; ?>

    <div id="wrapper">