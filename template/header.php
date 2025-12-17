<?php
// template/header.php
// This is the starting portion of the HTML page structure, including the top navigation.

// --- 1. START BUFFERING ---
if (ob_get_level() == 0) ob_start(); 

// Include checking.php (handles session start & 'Remember Me')
// NOTE: Adjust path if moving this template to a different directory.
require_once __DIR__ . '/../checking.php';

// --- 2. TIMEZONE SETUP ---
try {
    // Fetch timezone from DB. Assumes system_timezone column exists in tbl_general_settings.
    $stmt_tz = $pdo->query("SELECT system_timezone FROM tbl_general_settings WHERE id = 1");
    $timezone = $stmt_tz->fetchColumn() ?? 'Asia/Manila'; // Default to a safe zone if fetch fails
    date_default_timezone_set($timezone); 
} catch (PDOException $e) {
    date_default_timezone_set('Asia/Manila'); // Default fallback
    error_log("Failed to fetch system_timezone: " . $e->getMessage());
}
// -----------------------------

// --- 3. AUTHENTICATION & ROLE CHECK ---
$user_type = $_SESSION['usertype'] ?? null;
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$logged_in) {
    header("Location: ../index.php"); 
    exit;
}

// Ensure user is authorized for THIS directory (e.g., Superadmin directory access check)
// Assuming this template is included in the ADMIN context (usertype 1 or 0)
$current_role_check = true;

// If the page is in the 'superadmin' folder (usertype 0), but the user is not 0, redirect.
// This block implements the "GATEKEEPER" logic globally for the ADMIN/SUPERADMIN view.
if ($user_type != 0 && strpos($_SERVER['REQUEST_URI'], '/superadmin/') !== false) {
    $current_role_check = false;
}
// If the page is in the 'admin' folder (usertype 1), but the user is not 0 or 1, redirect.
// Add similar logic for the Admin portal if you have one.

if (!$current_role_check) {
    switch ($user_type) {
        case 2:
            header("Location: ../user/dashboard.php");
            break;
        default:
            session_destroy();
            header("Location: ../index.php");
            break;
    }
    exit;
}


// --- 4. LOADER LOGIC ---
$show_loader = false; 
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    unset($_SESSION['show_loader']); 
    // Do not close/restart session here unless necessary, rely on checks.php starting it.
}

// --- 5. GET SESSION DATA ---
$user_id = $_SESSION['user_id'] ?? 0;
$fullname = $_SESSION['fullname'] ?? 'Portal User';
$email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? 'default.png'; 
$usertype_name = ($user_type == 0) ? 'Super Admin' : (($user_type == 1) ? 'Administrator' : 'Employee');

// --- 6. NOTIFICATIONS ---
$notifications = [];
$notif_count = 0;
// NOTE: Adjust path to global_app_model.php if necessary
$global_model_path = __DIR__ . '/../app/models/global_app_model.php'; 
if (file_exists($global_model_path) && isset($pdo)) {
    require_once $global_model_path;
    if (function_exists('get_my_notifications')) {
        // Assume 'Admin' role for Superadmin/Admin contexts
        $role_context = ($user_type == 2) ? 'Employee' : 'Admin'; 
        // Note: get_my_notifications handles the logic for fetching based on role/ID
        $notifications = get_my_notifications($pdo, $role_context, 10);
        $notif_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));
    }
}


// --- 7. PAGE TITLE & CLEAN BUFFER ---
$page_title ??= $usertype_name . ' Portal - LOPISv2';
ob_clean(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="icon" href="../../assets/images/favicon.ico" type="image/ico">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../../assets/vendor/fa6/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="../../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/dataTables.min.css" rel="stylesheet"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css">
    <link href="../../assets/css/portal_styles.css" rel="stylesheet">
    <link href="../../assets/css/loader_styles.css" rel="stylesheet">
</head>

<body id="page-top">

    <?php if ($show_loader): ?>
        <div id="page-loader" class="page-loader-wrapper d-flex align-items-center justify-content-center">
            <div class="loader-content text-center">
                <img src="../../assets/images/LOPISv2.png" alt="LOSIS Logo" class="loader-logo">
                <div class="loader-progress mt-4 mb-2">
                    <div class="progress-bar"></div>
                </div>
                <span id="loader-percentage">0%</span>
                <p class="loader-text mt-3">Initializing workspace...</p>
            </div>
        </div>
    <?php endif; ?>

    <div id="wrapper"></div>