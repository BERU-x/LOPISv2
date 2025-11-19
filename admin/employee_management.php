<?php
// Set configuration
$page_title = 'Employee Management';
$current_page = 'employee_management';

// --- DATABASE CONNECTION ---
require 'template/header.php'; // Assume header.php includes db_connection.php and defines $pdo

// --- REQUIRE MODEL ---
require 'models/employee_model.php'; 

// --- 4. HANDLE FORM SUBMISSION (CREATE NEW EMPLOYEE) ---
if (isset($_POST['add_employee'])) {
    
    // 1. Basic Validation and Sanitization
    $data = [
        'employee_id' => trim($_POST['employee_id']),
        'firstname' => trim($_POST['firstname']),
        'lastname' => trim($_POST['lastname']),
        'suffix' => trim($_POST['suffix'] ?? ''),
        'address' => trim($_POST['address']),
        'birthdate' => $_POST['birthdate'],
        'contact_info' => trim($_POST['contact_info'] ?? null),
        'gender' => (int)$_POST['gender'],
        'position' => trim($_POST['position']),
        'department' => trim($_POST['department']),
        'employment_status' => (int)$_POST['employment_status'],
        'salary' => (int)$_POST['salary'],
        'food' => (int)($_POST['food'] ?? 0),
        'travel' => (int)($_POST['travel'] ?? 0),
        'bank_name' => trim($_POST['bank_name']),
        'account_type' => trim($_POST['account_type']),
        'account_number' => trim($_POST['account_number'] ?? null),
    ];
    
    $photo = null; 
    
    // 2. Photo Upload Handling (Placeholder Logic)
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = '../assets/images/';
        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('emp_') . '.' . $file_ext;
        $target_file = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $photo = $new_file_name;
        }
    }

    // 3. Execute CREATE function from model
    if (create_new_employee($pdo, $data, $photo)) {
        $_SESSION['message'] = "Employee {$data['firstname']} {$data['lastname']} added successfully!";
    } else {
        $_SESSION['error'] = "Error adding employee. Please try again.";
    }
    
    header("Location: employee_management.php");
    exit;
}

// --- LOOKUP DATA (FOR DISPLAY/FORMS) ---
$genders = [0 => 'Male', 1 => 'Female'];
$employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];

// Helper to get status text
function getStatusText($statusId, $array) {
    return $array[$statusId] ?? 'N/A';
}

// --- FETCH DATA FROM tbl_employees (READ OPERATION) ---
// CALL THE MODEL FUNCTION HERE
$employees = get_all_employees($pdo);


// --- INCLUDE TEMPLATES ---
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Employee Directory (<?php echo count($employees); ?>)</h1>
        
        <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="fas fa-user-plus me-2"></i> Add New Employee
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-gray-800">Employee List</h6>
            
            <div class="input-group" style="max-width: 300px;">
                <input type="text" class="form-control small" id="searchInput" placeholder="Search by name or ID..." aria-label="Search">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="employeesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeeTableBody">
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $emp): ?>
                            <tr class="employee-row">
                                <td><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../assets/images/<?php echo htmlspecialchars($emp['photo'] ?? 'default.png'); ?>" 
                                            alt="Photo" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($emp['department'] . ' / ' . $emp['position']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>₱<?php echo number_format($emp['salary'], 2); ?></td>
                                <td>
                                    <?php 
                                    $status_text = getStatusText((int)$emp['employment_status'], $employment_statuses);
                                    $badge_class = match((int)$emp['employment_status']) {
                                        1 => 'bg-success',
                                        2 => 'bg-warning text-dark',
                                        5 => 'bg-danger', // Resigned
                                        6 => 'bg-dark',   // Terminated
                                        default => 'bg-secondary',
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    // FIX: Use the correct loop variable $emp to get the ID
                                    $employee_id = $emp['id'] ?? 0; 
                                    ?>
                                    
                                    <a href="edit_employee.php?id=<?php echo htmlspecialchars($employee_id); ?>" 
                                    class="btn btn-sm btn-outline-info me-1" 
                                    title="Edit Employee Details">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No employee records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php 
$message_type = null;
$message_text = null;

if (isset($_SESSION['message'])) {
    $message_type = 'success';
    $message_text = $_SESSION['message'];
    unset($_SESSION['message']);
} elseif (isset($_SESSION['error'])) {
    $message_type = 'error';
    $message_text = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($message_type): 
?>
<script>
    // NOTE: This assumes SweetAlert2 library is loaded.
    Swal.fire({
        toast: true,
        icon: '<?php echo $message_type; ?>',
        title: '<?php echo htmlspecialchars($message_text); ?>',
        position: 'top-end', // Place it at the top-right corner
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
require 'functions/add_employee.php';
require 'template/footer.php'; 
?>