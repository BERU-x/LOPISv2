<?php
require '../db_connection.php';
date_default_timezone_set('Asia/Manila');

// --- 1. Initialize Access Variables ---
$accessGranted = false;
$status_based = ''; // This will hold 'OFB' or 'WFH'
$friendly_location = ''; // This will hold the display name
$swal_title = "Error";
$swal_message = "An unknown error occurred.";
$swal_icon = "error";

// --- 2. Check for Token and Location ---
if (!isset($_GET['token'])) {
    $swal_title = "Access Denied";
    $swal_message = "No access token provided. Please use a valid link.";
} elseif (!isset($_GET['location'])) {
    $swal_title = "Invalid Link";
    $swal_message = "No work location was specified in the link.";
} else {
    $token = $_GET['token'];
    $location = $_GET['location'];

    // --- 3. Validate Location FIRST (it's fast) ---
    if ($location == 'OFB') {
        $status_based = 'OFB';
        $friendly_location = 'On-site (OFB)';
    } elseif ($location == 'WFH') {
        $status_based = 'WFH';
        $friendly_location = 'Work From Home (WFH)';
    } else {
        $swal_title = "Invalid Location";
        $swal_message = "The work location in the link is not valid.";
    }

    // --- 4. Validate Token SECOND (if location was valid) ---
    if ($status_based != '') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM tbl_access_tokens WHERE token = ?");
            $stmt->execute([$token]);
            $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token_data) {
                $swal_title = "Access Denied";
                $swal_message = "This link is invalid. Please get a new link for today.";
            } elseif (strtotime($token_data['expires_at']) < time()) {
                $swal_title = "Link Expired";
                $swal_message = "This attendance link has expired. Please get a new link.";
            } else {
                // --- 5. ACCESS GRANTED ---
                // Both token and location are valid
                $accessGranted = true;
            }

        } catch (Exception $e) {
            $swal_message = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Attendance</title>
    
    <link rel="icon" href="../assets/images/favicon.ico" type="image/ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
</head>
<body>

    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="col-lg-5 col-md-8 col-sm-10">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5 text-center">

                <?php if ($accessGranted): ?>
                    
                    <div class="text-center mb-4">
                        <img src="../assets/images/LOPISv2.png" alt="LOPISv2 Logo" class="img-fluid" style="max-height: 100px;">
                    </div>
                    
                    <h1 class="display-4 fw-bold mb-3" id="digital-clock"></h1>
                    <p class="lead text-muted mb-2"><?php echo date("l, F j, Y"); ?></p>
                    
                    <p class="h5 mb-4">Location: <strong><?php echo htmlspecialchars($friendly_location); ?></strong></p>

                    <form id="attendance-form">
                        
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-address-card"></i>
                                </span>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="Employee ID" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            </div>
                        </div>
                        
                        <input type="hidden" id="action" name="action" value="">
                        <input type="hidden" name="status_based" value="<?php echo htmlspecialchars($status_based); ?>">

                        <div class="d-grid gap-3 mt-4">
                            <button type="button" class="btn btn-success btn-lg" id="btn-time-in">
                                <i class="fas fa-sign-in-alt me-2"></i> TIME IN
                            </button>
                            <button type="button" class="btn btn-danger btn-lg" id="btn-time-out">
                                <i class="fas fa-sign-out-alt me-2"></i> TIME OUT
                            </button>
                        </div>
                    </form>

                <?php else: ?>

                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>

                <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            
            // --- SweetAlert Mixin for Toastr Style ---
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end', // Upper right corner
                showConfirmButton: false,
                timer: 3000, // Closes after 3 seconds
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                },
                customClass: {
                    popup: 'swal2-toast-popup' 
                }
            });
            
            <?php if (!$accessGranted): ?>
            
            // --- 1. ACCESS DENIED SCRIPT (Now a Toast) ---
            // This handles expired token, invalid link, etc.
            Toast.fire({
                icon: '<?php echo $swal_icon; ?>',
                title: '<?php echo $swal_title; ?>',
                text: '<?php echo $swal_message; ?>',
                timer: 5000, // Give users more time to read critical access errors
                showConfirmButton: false, // Override the mixin for access errors if necessary
            });

            <?php else: ?>

            // --- ACCESS GRANTED SCRIPT ---
            
            function updateClock() {
                var now = new Date();
                var hours = now.getHours();
                var minutes = now.getMinutes();
                var seconds = now.getSeconds();
                var ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12; 
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;
                var timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
                $('#digital-clock').html(timeString);
            }
            setInterval(updateClock, 1000);
            updateClock();

            $('#btn-time-in').click(function() {
                $('#action').val('time_in');
                submitAttendance();
            });

            $('#btn-time-out').click(function() {
                $('#action').val('time_out');
                submitAttendance();
            });

            function submitAttendance() {
                var employeeId = $('#employee_id').val();
                var password = $('#password').val();
                
                // --- 2. Input Validation (Now a Toast) ---
                if (employeeId.trim() === '' || password.trim() === '') {
                    Toast.fire({
                        icon: 'warning', 
                        title: 'Employee ID and Password are required.'
                    });
                    return; 
                }

                var formData = $('#attendance-form').serialize();

                $.ajax({
                    type: 'POST',
                    url: '../process/p_attendance.php',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        
                        // --- 3. Success/Error Response (Now a Toast) ---
                        Toast.fire({
                            icon: response.status,
                            title: response.message,
                            text: '' 
                        }).then(() => {
                            if (response.status === 'success') {
                                $('#employee_id').val('');
                                $('#password').val('');
                            }
                        });
                    },
                    error: function() {
                        // --- 4. AJAX Error (Now a Toast) ---
                        Toast.fire({
                            icon: 'error',
                            title: 'Server Error',
                            text: 'An unexpected connection error occurred.'
                        });
                    }
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>