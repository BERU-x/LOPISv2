<?php
// daily_refresh.php
require '../db_connection.php'; // Path to your connection file (adjust as needed)
date_default_timezone_set('Asia/Manila'); 

// --- 1. Get the current valid token ---
$today = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT token FROM tbl_access_tokens WHERE expires_at > ? LIMIT 1");
$stmt->execute([$today]);
$existing_token = $stmt->fetchColumn();

$token_to_use = $existing_token;

// --- 2. If no valid token exists, generate a new one ---
if (!$existing_token) {
    
    // 2a. Clean up expired tokens (optional, but good practice)
    $pdo->exec("DELETE FROM tbl_access_tokens WHERE expires_at < NOW()");

    // 2b. Generate new token and expiration (End of Day)
    $new_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('today 23:59:59'));
    
    $stmt = $pdo->prepare("INSERT INTO tbl_access_tokens (token, expires_at) VALUES (?, ?)");
    $stmt->execute([$new_token, $expires_at]);
    
    $token_to_use = $new_token;
}


// --- 3. Build and execute the final redirect URL ---
// You only need to set the location once. If the kiosk is static, OFB is safe.
$base_url = "../link/attendance.php";
$location_code = "OFB"; // Use 'OFB' since this is the dedicated office kiosk.

$final_url = $base_url . "?token=" . $token_to_use . "&location=" . $location_code;

// Set the browser to aggressively not cache this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Redirect the user to the attendance page with the dynamic token
header("Location: " . $final_url);
exit;
?>