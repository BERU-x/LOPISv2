<?php
// employee_management.php

// Set configuration
$page_title = 'Employee Management';
$current_page = 'employee_management';

// --- DATABASE CONNECTION ---
require 'template/header.php'; 

// --- REQUIRE MODEL ---
require 'models/employee_model.php'; 

// --- 4. HANDLE FORM SUBMISSION (CREATE NEW EMPLOYEE) ---
if (isset($_POST['add_employee'])) {
    
    // 1. Basic Validation and Sanitization
    $data = [
        'employee_id' => trim($_POST['employee_id']),
        'firstname'   => trim($_POST['firstname']),
        'middlename'  => trim($_POST['middlename']), 
        'lastname'    => trim($_POST['lastname']),
        'suffix'      => trim($_POST['suffix'] ?? ''),
        'address'     => trim($_POST['address']),
        'birthdate'   => $_POST['birthdate'],
        'contact_info'=> trim($_POST['contact_info'] ?? null),
        'gender'      => (int)$_POST['gender'],
        'position'    => trim($_POST['position']),
        'department'  => trim($_POST['department']),
        'employment_status' => (int)$_POST['employment_status'],
        
        // Financial Mapping
        'daily_rate'        => (float)$_POST['daily_rate'],       
        'monthly_rate'      => (float)$_POST['monthly_rate'],     
        'food_allowance'    => (float)($_POST['food_allowance'] ?? 0), 
        'transpo_allowance' => (float)($_POST['transpo_allowance'] ?? 0), 
        
        'bank_name'      => trim($_POST['bank_name']),
        'account_type'   => trim($_POST['account_type'] ?? 'Savings'),
        'account_number' => trim($_POST['account_number'] ?? null),
    ];
    
    $photo = null; 
    
    // 2. Photo Upload Handling
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = '../assets/images/';
        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        if(in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png'])) {
            $new_file_name = uniqid('emp_') . '.' . $file_ext;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                $photo = $new_file_name;
            }
        }
    }

    // 3. Execute CREATE function
    if (create_new_employee($pdo, $data, $photo)) {
        $_SESSION['message'] = "Employee {$data['firstname']} {$data['lastname']} added successfully!";
    } else {
        $_SESSION['error'] = "Error adding employee. Please try again.";
    }
    
    header("Location: employee_management.php");
    exit;
}

// --- LOOKUP DATA ---
$genders = [0 => 'Male', 1 => 'Female'];
$employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];

function getStatusText($statusId, $array) {
    return $array[$statusId] ?? 'N/A';
}

// --- FETCH DATA (READ OPERATION) ---
$employees = get_all_employees($pdo);

// --- INCLUDE TEMPLATES ---
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Employee Directory</h1>
            <p class="mb-0 text-muted">Total Employees: <span class="fw-bold text-gray-600"><?php echo count($employees); ?></span></p>
        </div>
        
        <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="fas fa-user-plus me-2"></i> Add New Employee
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-users me-2"></i>Masterlist</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search employees..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="employeesTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">ID</th>
                            <th class="border-0">Name</th>
                            <th class="border-0">Daily Rate</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeeTableBody">
                        <?php foreach ($employees as $emp): ?>
                        <tr class="employee-row">
                            <td class="fw-bold text-gray-700 small"><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                            
                            <?php
                                $full_name = htmlspecialchars($emp['firstname'] . ' ' . ($emp['middlename'] ? $emp['middlename'].' ' : '') . $emp['lastname']);
                                $sort_name = htmlspecialchars($emp['lastname'] ?? '') . ', ' . htmlspecialchars($emp['firstname'] ?? '');
                            ?>
                            
                            <td data-order="<?php echo $sort_name; ?>" data-search="<?php echo $full_name; ?>">
                                <div class="d-flex align-items-center">
                                    <img src="../assets/images/<?php echo htmlspecialchars($emp['photo'] ?? 'default.png'); ?>" 
                                         alt="Photo" class="rounded-circle me-3 border shadow-sm" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $full_name; ?></div>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars($emp['department']); ?> 
                                            <span class="mx-1">•</span> 
                                            <?php echo htmlspecialchars($emp['position']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="fw-bold text-gray-800">
                                ₱<?php echo number_format($emp['daily_rate'] ?? 0, 2); ?>
                                <div class="small text-muted fw-normal" style="font-size: 0.75rem;">
                                    Monthly: ₱<?php echo number_format($emp['monthly_rate'] ?? 0, 0); ?>
                                </div>
                            </td>
                            
                            <td class="text-center">
                                <?php 
                                $status_text = getStatusText((int)$emp['employment_status'], $employment_statuses);
                                // Soft Badge Styling Logic
                                $badge_class = match((int)$emp['employment_status']) {
                                    1 => 'bg-soft-success text-success border border-success', // Regular
                                    2 => 'bg-soft-warning text-warning border border-warning', // Part-time
                                    0 => 'bg-soft-info text-info border border-info',       // Probationary
                                    5 => 'bg-soft-danger text-danger border border-danger',   // Resigned
                                    6 => 'bg-soft-dark text-dark border border-dark',         // Terminated
                                    default => 'bg-soft-secondary text-secondary border border-secondary',
                                };
                                ?>
                                <span class="badge <?php echo $badge_class; ?> px-3 shadow-sm rounded-pill"><?php echo $status_text; ?></span>
                            </td>

                            <td class="text-center">
                                <?php $db_id = $emp['id'] ?? 0; ?>
                                <div class="btn-group" role="group">
                                    <a href="edit_employee.php?id=<?php echo htmlspecialchars($db_id); ?>" 
                                       class="btn btn-sm btn-light text-muted border" 
                                       title="Edit Employee Details">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="manage_financials.php?id=<?php echo htmlspecialchars($db_id); ?>" 
                                       class="btn btn-sm btn-light text-muted border" 
                                       title="Manage Financials">
                                        <i class="fas fa-coins"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php 
// SweetAlert Logic
$message_type = null;
$message_text_json = null;

if (isset($_SESSION['message'])) {
    $message_type = 'success';
    $message_text_json = json_encode($_SESSION['message']); 
    unset($_SESSION['message']);
} elseif (isset($_SESSION['error'])) {
    $message_type = 'error';
    $message_text_json = json_encode($_SESSION['error']);
    unset($_SESSION['error']);
}

if ($message_type): 
?>
<script>
    Swal.fire({
        toast: true,
        icon: '<?php echo $message_type; ?>',
        title: <?php echo $message_text_json; ?>, 
        position: 'top-end', 
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
</script>
<?php endif; ?>

<?php
// Include Add Employee Modal
require 'functions/add_employee.php';
require 'template/footer.php'; 
?>

<script>
    $(document).ready(function() {
        var table = $('#employeesTable').DataTable({
            "order": [[ 1, "asc" ]], // Sort by Name
            "pageLength": 10,
            "dom": 'rtip', // Clean interface (hides default search)
            "language": {
                "emptyTable": "No employees found."
            }
        });

        // Link Custom Search Input
        $('#customSearch').on('keyup', function() {
            table.search(this.value).draw();
        });
    });
</script>