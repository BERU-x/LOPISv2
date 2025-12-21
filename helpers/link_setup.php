<?php
// template/link_setup.php
// CENTRALIZED PATH CONFIGURATION
// Included via header.php, so it's available on every page.

// 1. PROJECT ROOT
// Change this value if you rename your project folder in htdocs
$project_root = '/LOPISv2'; 

// 2. USER CONTEXT
// We use a unique variable name to avoid conflicts
$link_usertype = $_SESSION['usertype'] ?? 99; 

// 3. DEFINE BASE LINK (For Page Navigation)
// Logic: Project Root + Role Folder
// Example: /LOPISv2/admin/
$base_link = $project_root . '/'; 

switch ($link_usertype) {
    case 0: $base_link .= 'superadmin/'; break;
    case 1: $base_link .= 'admin/'; break;
    case 2: $base_link .= 'user/'; break;
    default: $base_link .= ''; break; // Guest or unknown
}

// 4. DEFINE WEB ROOT (For Assets like CSS, JS, Images)
// Logic: Just the Project Root
// Example: /LOPISv2/assets/css/style.css
$web_root = $project_root; 

// 5. DEFINE API ROOT (For AJAX calls)
// Example: /LOPISv2/api/global_notifications_api.php
$api_root = $project_root . '/api';

// 6. DEFINE APP ROOT (For Internal Includes)
// Example: /LOPISv2/app/some_script.php
$app_root = $project_root . '/app';
?>