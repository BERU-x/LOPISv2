<?php
// connect.php

require 'db_connection.php'; // Your database connection file

// Set the base URL of your application
$base_url = "https://lendell.ph/LOPISv2/link/attendance.php";

try {
    // 1. Generate one secure, random token string
    $token = bin2hex(random_bytes(32)); 
    
    // 2. Set an expiration time for the end of the current day
    $expires_at = date('Y-m-d H:i:s', strtotime('today 23:59:59'));

    // 3. Store the single token in the database
    $stmt = $pdo->prepare(
        "INSERT INTO tbl_access_tokens (token, expires_at) 
         VALUES (?, ?)"
    );
    $stmt->execute([$token, $expires_at]);

    // 4. Build the final URLs
    $final_url_onsite = $base_url . "?token=" . $token . "&location=OFB";
    $final_url_wfh = $base_url . "?token=" . $token . "&location=WFH";
    $final_url_fld = $base_url . "?token=" . $token . "&location=FLD";   

    // 5. Display the links
    echo '<h3>Here are your daily attendance links:</h3>';
    
    echo '<h4 class="mt-4">On-site (OFB) Link:</h4>';
    echo '<a href="' . htmlspecialchars($final_url_onsite) . '">' . htmlspecialchars($final_url_onsite) . '</a>';
    echo '<br><br>';
    echo '<h4>Work From Home (WFH) Link:</h4>';
    echo '<a href="' . htmlspecialchars($final_url_wfh) . '">' . htmlspecialchars($final_url_wfh) . '</a>';    
    echo '<br><br>';
    echo '<h4>Field (FLD) Link:</h4>';
    echo '<a href="' . htmlspecialchars($final_url_fld) . '">' . htmlspecialchars($final_url_fld) . '</a>';


} catch (Exception $e) {
    die("Error generating access link: " . $e->getMessage());
}
?>