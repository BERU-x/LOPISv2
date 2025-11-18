<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Get user data from session
$fullname = htmlspecialchars($_SESSION['fullname']);
$usertype = $_SESSION['usertype'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Welcome, <?php echo $fullname; ?>!</h1>
        <p>You are logged in.</p>
        
        <?php
            // Show different content based on usertype
            if ($usertype == 1) {
                echo '<div class="alert alert-info">You are a Superadmin (IT).</div>';
            } elseif ($usertype == 2) {
                echo '<div class="alert alert-success">You are an Admin (Management).</div>';
            } else {
                echo '<div class="alert alert-secondary">You are a User (Employee).</div>';
            }
        ?>

        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
</body>
</html>