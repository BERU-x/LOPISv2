<?php
require '../db_connection.php';
date_default_timezone_set('Asia/Manila');

// --- 1. Initialize Access Variables ---
$accessGranted = false;
$status_based = ''; 
$friendly_location = ''; 
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

    // --- 3. Validate Location ---
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

    // --- 4. Validate Token ---
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
    <link href="../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fa6/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/time_styles.css"> </head>
<body>

    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="col-lg-5 col-md-8 col-sm-10">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5 text-center">

                <?php if ($accessGranted): ?>
                    
                    <div class="text-center mb-4">
                        <img src="../assets/images/LOPISv2.png" alt="LOPISv2 Logo" class="img-fluid" style="max-height: 150px;">
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
                                <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="Employee ID" required autocomplete="off">
                            </div>
                        </div>

                        <div class="mb-3" id="password-group">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password">
                            </div>
                        </div>

                        <input type="hidden" id="action" name="action" value="">
                        <input type="hidden" name="status_based" value="<?php echo htmlspecialchars($status_based); ?>">

                        <div id="status-message" class="alert alert-info text-center mb-3" style="display: none;"></div>

                        <div class="d-grid gap-3 mt-4" id="action-buttons" style="display: none;">
                            
                            <button type="button" class="btn btn-success btn-lg" id="btn-time-in" style="display: none;">
                                <i class="fas fa-sign-in-alt me-2"></i> TIME IN
                            </button>
                            
                            <button type="button" class="btn btn-danger btn-lg" id="btn-time-out" style="display: none;">
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
    <script src="../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            
            // --- SweetAlert Setup ---
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                },
                customClass: { popup: 'swal2-toast-popup' }
            });

            <?php if (!$accessGranted): ?>
                // Access Denied Logic
                Toast.fire({
                    icon: '<?php echo $swal_icon; ?>',
                    title: '<?php echo $swal_title; ?>',
                    text: '<?php echo $swal_message; ?>',
                    timer: 5000 
                });
            <?php else: ?>

            // --- Clock Logic ---
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
                $('#digital-clock').html(hours + ':' + minutes + ':' + seconds + ' ' + ampm);
            }
            setInterval(updateClock, 1000);
            updateClock();

            // ============================================
            // 1. DYNAMIC CHECK (Triggered on 'Blur')
            // ============================================

            $('#employee_id').on('blur', function() {
                var empId = $(this).val().trim();
                // Grab the current location (OFB or WFH) from the hidden input
                var currentLocation = $('input[name="status_based"]').val(); 
                
                if (empId === '') {
                    resetButtonsOnly();
                    return;
                }

                $('#employee_id').removeClass('is-invalid is-valid');

                $.ajax({
                    url: '../process/check_status.php',
                    type: 'POST',
                    // SEND LOCATION DATA HERE:
                    data: { 
                        employee_id: empId, 
                        current_location: currentLocation 
                    },
                    dataType: 'json',
                    success: function(response) {
                        handleStatusResponse(response); // Pass the whole object, not just string
                    },
                    error: function() {
                        console.log('Error checking status');
                    }
                });
            });

            function handleStatusResponse(response) {
                var status = response.status; // Extract status string

                // Reset UI
                $('#action-buttons').hide();
                $('#btn-time-in').hide();
                $('#btn-time-out').hide();
                $('#status-message').hide();
                $('#employee_id').removeClass('is-invalid is-valid');

                // --- SCENARIO: LOCATION MISMATCH ---
                if (status === 'location_mismatch') {
                    $('#employee_id').addClass('is-invalid');
                    Toast.fire({ 
                        icon: 'error', 
                        title: 'Wrong Location Link',
                        text: 'You Timed In at ' + response.required_location + '. You must Time Out using the ' + response.required_location + ' link.'
                    });
                }
                // --- SCENARIO 1: ID NOT FOUND ---
                else if (status === 'invalid_id') {
                    $('#employee_id').addClass('is-invalid');
                    Toast.fire({ icon: 'error', title: 'Employee ID not found.' });
                } 
                // --- SCENARIO 2: ACCOUNT INACTIVE ---
                else if (status === 'inactive') {
                    $('#employee_id').addClass('is-invalid');
                    Toast.fire({ icon: 'error', title: 'Account is inactive.' });
                }
                // --- SCENARIO 3: NEEDS TIME IN ---
                else if (status === 'need_time_in') {
                    $('#employee_id').addClass('is-valid');
                    $('#action-buttons').fadeIn();
                    $('#btn-time-in').show();
                } 
                // --- SCENARIO 4: NEEDS TIME OUT ---
                else if (status === 'need_time_out') {
                    $('#employee_id').addClass('is-valid');
                    $('#action-buttons').fadeIn();
                    $('#btn-time-out').show();
                } 
                // --- SCENARIO 5: COMPLETED ---
                else if (status === 'completed') {
                    $('#employee_id').addClass('is-valid');
                    $('#status-message')
                        .html('<i class="fas fa-check-circle me-2"></i> You are done for today!')
                        .addClass('alert-success')
                        .removeClass('alert-info')
                        .fadeIn();
                }
            }

            function resetButtonsOnly() {
                $('#action-buttons').hide();
                $('#btn-time-in').hide();
                $('#btn-time-out').hide();
                $('#status-message').hide();
                $('#employee_id').removeClass('is-invalid is-valid');
            }

            // ============================================
            // 2. SUBMISSION LOGIC
            // ============================================

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
                
                if (employeeId.trim() === '' || password.trim() === '') {
                    Toast.fire({ icon: 'warning', title: 'Password is required.' });
                    return; 
                }

                var formData = $('#attendance-form').serialize();

                $.ajax({
                    type: 'POST',
                    url: '../process/p_attendance.php',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        
                        Toast.fire({
                            icon: response.status,
                            title: response.message
                        }).then(() => {
                            if (response.status === 'success') {
                                // Full Reset on Success
                                $('#employee_id').val('');
                                $('#password').val('');
                                resetButtonsOnly();
                            }
                        });
                    },
                    error: function() {
                        Toast.fire({ icon: 'error', title: 'Server Error', text: 'Connection failed.' });
                    }
                });
            }

            // ============================================
            // 3. ENTER KEY LISTENER
            // ============================================
            $('#password').on('keypress', function(e) {
                // Check if the key pressed is "Enter" (code 13)
                if (e.which === 13) { 
                    e.preventDefault(); // Stop the page from reloading/submitting standard form

                    // If Time In button is visible, click it
                    if ($('#btn-time-in').is(':visible')) {
                        $('#btn-time-in').click();
                    } 
                    // If Time Out button is visible, click it
                    else if ($('#btn-time-out').is(':visible')) {
                        $('#btn-time-out').click();
                    }
                }
            });

            // Optional: Prevent default form submission on the whole form just in case
            $('#attendance-form').on('submit', function(e){
                e.preventDefault();
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>