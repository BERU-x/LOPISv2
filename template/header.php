<?php
// template/header.php
// Handles session checks, asset loading, and data fetching.

// --- 1. START BUFFERING ---
if (ob_get_level() == 0) ob_start();

// --- 2. SESSION & SECURITY CHECK ---
// ⭐ This calls checking.php which now contains the "Single Session Policy" 
// If the session ID doesn't match the DB, checking.php will exit and redirect here.
require_once __DIR__ . '/../checking.php';
require_once __DIR__ . '/../helpers/link_setup.php';

// --- 3. AUTHENTICATION GUARD ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Determine path back to root index
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    // Redirect to login
    header("Location: $protocol://$host/LOPISv2/index.php"); 
    exit;
}

// --- 4. USER CONTEXT DATA ---
$user_id         = $_SESSION['user_id'] ?? 0;
$user_type       = $_SESSION['usertype'] ?? 99; 
$employee_id     = $_SESSION['employee_id'] ?? null;
$fullname        = $_SESSION['fullname'] ?? 'User';
$email           = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? 'default.png';

// Define User Type Label
$usertype_name = match((int)$user_type) {
    0 => 'Super Admin',
    1 => 'Administrator',
    2 => 'Employee',
    default => 'Guest'
};

// --- 5. PAGE LOADER LOGIC ---
$show_loader = false; 
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    unset($_SESSION['show_loader']); 
}

// --- 6. NOTIFICATIONS FETCH ---
$notifications = [];
$notif_count = 0;
$notif_model_path = __DIR__ . '/../app/models/notification_model.php';

if (file_exists($notif_model_path) && isset($pdo)) {
    require_once $notif_model_path;
    if (function_exists('get_my_notifications')) {
        $notifications = get_my_notifications($pdo, $user_type, 10, $employee_id);
        foreach ($notifications as $n) {
            if (isset($n['is_read']) && $n['is_read'] == 0) $notif_count++;
        }
    }
}

// --- 7. DEFAULT METADATA ---
$page_title ??= 'LOPISv2 Portal';
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
    
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

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
                <p class="loader-text mt-3">Preparing your secure workspace...</p>
            </div>
        </div>
    <?php endif; ?>

    <div id="wrapper">