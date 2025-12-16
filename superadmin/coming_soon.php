<?php
// coming_soon.php

// 1. Get the feature name from the URL, default to "This Feature" if empty
$feature_name = isset($_GET['feature']) ? htmlspecialchars($_GET['feature']) : 'This Feature';

$page_title = 'Page Under Construction';
$current_page = 'coming_soon'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="text-center" style="margin-top: 100px;">
        
        <div class="error mx-auto mb-4" data-text="Wait!" style="font-size: 5rem;">
            <i class="fas fa-person-digging text-teal fa-bounce"></i>
        </div>

        <h1 class="display-4 font-weight-bold text-gray-800 mb-3">
            <?php echo $feature_name; ?> is Coming Soon
        </h1>
        
        <p class="lead text-gray-500 mb-5">
            The <strong><?php echo $feature_name; ?></strong> module is currently being developed.
            <br>We are working hard to bring this to you!
        </p>

        </div>
</div>