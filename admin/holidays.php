<?php
// admin/holidays.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);    
$page_title = 'Manage Holidays';
$current_page = 'holidays'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';

// --- 1. HANDLE FORM SUBMISSION (ADD HOLIDAY) ---
$message = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_holiday'])) {
    $h_date = $_POST['holiday_date'];
    $h_name = trim($_POST['holiday_name']);
    $h_type = $_POST['holiday_type'];
    $h_rate = $_POST['payroll_multiplier'];

    if (!empty($h_date) && !empty($h_name)) {
        try {
            $sql = "INSERT INTO tbl_holidays (holiday_date, holiday_name, holiday_type, payroll_multiplier) 
                    VALUES (:h_date, :h_name, :h_type, :h_rate)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':h_date' => $h_date, 
                ':h_name' => $h_name, 
                ':h_type' => $h_type, 
                ':h_rate' => $h_rate
            ]);
            
            $message = "Holiday added successfully!";
            $msg_type = "success";
        } catch (PDOException $e) {
            // Check for duplicate entry (Error 23000 is standard SQL state for integrity constraint violation)
            if ($e->getCode() == 23000) {
                $message = "Error: A holiday already exists on this date.";
                $msg_type = "warning";
            } else {
                $message = "Database Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    } else {
        $message = "Please fill in all required fields.";
        $msg_type = "danger";
    }
}

// --- 2. HANDLE DELETION ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_holidays WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = "Holiday deleted successfully.";
        $msg_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting record: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// --- 3. FETCH HOLIDAYS FOR TABLE ---
// Fetching newest holidays first
$stmt_list = $pdo->query("SELECT * FROM tbl_holidays ORDER BY holiday_date DESC");
$holidays = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Holiday Management</h1>
            <p class="mb-0 text-muted">Configure holidays and payroll rates</p>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white border-bottom-primary">
                    <h6 class="m-0 font-weight-bold text-primary">Add New Holiday</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="small text-gray-600 font-weight-bold">Holiday Date</label>
                            <input type="date" name="holiday_date" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="small text-gray-600 font-weight-bold">Holiday Name</label>
                            <input type="text" name="holiday_name" class="form-control" placeholder="e.g. Independence Day" required>
                        </div>

                        <div class="mb-3">
                            <label class="small text-gray-600 font-weight-bold">Holiday Type</label>
                            <select name="holiday_type" class="form-select" id="typeSelect" onchange="updateMultiplier()" required>
                                <option value="Regular">Regular Holiday</option>
                                <option value="Special Non-Working">Special Non-Working</option>
                                <option value="Special Working">Special Working</option>
                                <option value="National Local">National Local</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small text-gray-600 font-weight-bold">Payroll Multiplier</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="payroll_multiplier" id="payrollRate" class="form-control" value="1.00" required>
                                <span class="input-group-text bg-light">x Rate</span>
                            </div>
                            <small class="text-muted" id="rateHelp">Regular holidays usually pay 1.00 (100%) or 2.00 (200%) if worked.</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="save_holiday" class="btn btn-primary btn-block fw-bold">
                                <i class="fas fa-save me-2"></i> Save Holiday
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
                    <h6 class="m-0 font-weight-bold text-gray-600">Holiday List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="holidayTable" width="100%" cellspacing="0">
                            <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                                <tr>
                                    <th>Date</th>
                                    <th>Holiday Name</th>
                                    <th>Type</th>
                                    <th>Multiplier</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($holidays)): ?>
                                    <?php foreach ($holidays as $row): ?>
                                        <?php 
                                            // Determine badge color based on type
                                            $badge_color = 'secondary';
                                            if($row['holiday_type'] == 'Regular') $badge_color = 'danger'; // Red for Regular
                                            if($row['holiday_type'] == 'Special Non-Working') $badge_color = 'warning text-dark'; // Yellow
                                            if($row['holiday_type'] == 'Special Working') $badge_color = 'info text-dark'; // Blue
                                        ?>
                                        <tr>
                                            <td class="align-middle font-weight-bold text-gray-700">
                                                <?php echo date('M d, Y', strtotime($row['holiday_date'])); ?>
                                                <div class="small text-muted font-weight-normal">
                                                    <?php echo date('l', strtotime($row['holiday_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="align-middle"><?php echo htmlspecialchars($row['holiday_name']); ?></td>
                                            <td class="align-middle">
                                                <span class="badge bg-<?php echo $badge_color; ?>">
                                                    <?php echo $row['holiday_type']; ?>
                                                </span>
                                            </td>
                                            <td class="align-middle font-weight-bold text-center">
                                                <?php echo $row['payroll_multiplier']; ?>x
                                            </td>
                                            <td class="align-middle text-center">
                                                <a href="holidays.php?delete_id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-danger btn-sm btn-circle"
                                                   onclick="return confirm('Are you sure you want to delete this holiday?');"
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require 'template/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#holidayTable').DataTable({
            "order": [[ 0, "desc" ]], // Order by Date (1st column) descending
            "pageLength": 10,
            "language": { "emptyTable": "No holidays found." }
        });
    });

    // Optional: Auto-suggest multiplier based on type
    function updateMultiplier() {
        var type = document.getElementById("typeSelect").value;
        var rateInput = document.getElementById("payrollRate");
        
        // You can adjust these defaults based on your company policy
        if(type === "Regular") {
            rateInput.value = "1.00"; 
        } else if (type === "Special Non-Working") {
            rateInput.value = "0.30"; // Example: +30% premium
        } else {
            rateInput.value = "1.00";
        }
    }
</script>