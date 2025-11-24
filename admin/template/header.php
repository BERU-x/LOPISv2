<?php
// --- 1. START BUFFERING IMMEDIATELY ---
// This catches any stray text/spaces from included files like checking.php
ob_start(); 

// This is the Admin Header
// It contains session auth, loader logic, and the HTML <head>

require_once __DIR__ . '/../../checking.php';

// --- 2. SESSION AUTHENTICATION ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php"); 
    exit;
}

// --- 3. LOADER LOGIC ---
$show_loader = false; 
if (isset($_SESSION['show_loader']) && $_SESSION['show_loader'] === true) {
    $show_loader = true;
    // Simple removal is enough. Avoid closing and restarting the session here.
    unset($_SESSION['show_loader']); 
}

// --- 4. ROLE-BASED ACCESS (Admin = 1) ---
$user_type = $_SESSION['usertype'] ?? null;
$redirect_map = [
    // 0: Super Admin
    0 => '../superadmin/dashboard.php',
    // Default User/Other Roles (assuming 2 or higher are standard users)
    2 => '../user/dashboard.php', 
];

if ($user_type !== 1) { // If the user is NOT an Admin (usertype 1)
    $redirect_url = $redirect_map[$user_type] ?? null;

    if ($redirect_url) {
        header("Location: $redirect_url");
        exit;
    }
}

// --- 5. GET SESSION VARS ---
$fullname = $_SESSION['fullname'] ?? 'Admin User';
$email = $_SESSION['email'] ?? '';

// --- 6. PAGE TITLE ---
$page_title ??= 'Admin Portal - LOPISv2';

// --- 7. CLEAN THE BUFFER (THE FIX) ---
// This deletes everything captured so far (including the &nbsp; &nbsp;)
// ensuring the HTML starts perfectly clean.
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