<?php
// today_attendance.php

$page_title = 'Today\'s Attendance';
$current_page = 'today_attendance'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';

// --- 1. SET DATE & FETCH DATA ---
$today = date('Y-m-d');
$display_date = date('F d, Y');

// A. Get Total Active Employees
$stmt_total = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5");
$total_employees = (int)$stmt_total->fetchColumn();

// B. Get Attendance Logs
$sql = "SELECT 
            a.*, 
            e.firstname, e.lastname, e.photo, e.department, e.position, e.employee_id as emp_code
        FROM tbl_attendance a
        LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id
        WHERE a.date = :today
        ORDER BY a.time_in DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':today' => $today]);
$attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// C. Calculate Stats
$total_present = count($attendance_logs);
$total_absent  = max(0, $total_employees - $total_present);
$total_late    = 0;
$total_ontime  = 0;

foreach ($attendance_logs as $log) {
    // Convert to lowercase to make checking easier
    $status_check = strtolower($log['attendance_status']);

    // Check for "Late" keyword
    if (strpos($status_check, 'late') !== false) {
        $total_late++;
    } 
    
    // Check for "Ontime" keyword
    // Note: If someone is "Ontime, Overtime", this counts them.
    if (strpos($status_check, 'ontime') !== false) {
        $total_ontime++;
    }
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Attendance Monitor</h1>
            <p class="mb-0 text-muted">Date: <span class="fw-bold text-gray-600"><?php echo $display_date; ?></span></p>
        </div>
        <button onclick="location.reload();" class="btn btn-sm btn-teal shadow-sm fw-bold">
            <i class="fas fa-sync-alt me-2"></i> Refresh Data 
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_present; ?> / <?php echo $total_employees; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Absent</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_absent; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-times fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Late Arrivals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_late; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-running fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">On Time</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_ontime; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-teal"><i class="fas fa-list-alt me-2"></i>Real-time Logs</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search logs..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="todayTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee</th>
                            <th class="border-0">Time In</th>
                            <th class="border-0">Time Out</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-end">Total Hrs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($attendance_logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted fst-italic py-4">No records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($attendance_logs as $log): ?>
                                <?php 
                                    // Basic Vars
                                    $photo = !empty($log['photo']) ? $log['photo'] : 'default.png';
                                    $fullname = $log['firstname'] . ' ' . $log['lastname'];
                                    $time_in = date('h:i A', strtotime($log['time_in']));
                                    
                                    // Time Out Check
                                    $is_active = ($log['time_out'] === null || $log['time_out'] === '00:00:00');
                                    $time_out = !$is_active ? date('h:i A', strtotime($log['time_out'])) : '<span class="text-muted small fst-italic">--:--</span>';
                                    
                                    // --- MULTI-STATUS BADGE GENERATOR ---
                                    $raw_status = $log['attendance_status'];
                                    $badges_html = '';

                                    // 1. Check Ontime
                                    if (stripos($raw_status, 'Ontime') !== false) {
                                        $badges_html .= '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill me-1">Ontime</span>';
                                    }
                                    // 2. Check Late
                                    if (stripos($raw_status, 'Late') !== false) {
                                        $badges_html .= '<span class="badge bg-soft-warning text-warning border border-warning px-2 rounded-pill me-1">Late</span>';
                                    }
                                    // 3. Check Undertime
                                    if (stripos($raw_status, 'Undertime') !== false) {
                                        $badges_html .= '<span class="badge bg-soft-danger text-danger border border-danger px-2 rounded-pill me-1">Undertime</span>';
                                    }
                                    // 4. Check Overtime
                                    if (stripos($raw_status, 'Overtime') !== false) {
                                        $badges_html .= '<span class="badge bg-soft-primary text-primary border border-primary px-2 rounded-pill me-1">Overtime</span>';
                                    }

                                    // 5. Check Active (If clocked in but no time out yet)
                                    if ($is_active) {
                                        $badges_html .= '<span class="badge bg-soft-secondary text-secondary border px-2 rounded-pill"><i class="fas fa-spinner fa-spin me-1"></i>Active</span>';
                                    }

                                    // Fallback if empty
                                    if(empty($badges_html)) {
                                        $badges_html = '<span class="badge bg-light text-muted border">Unknown</span>';
                                    }

                                    // Hours
                                    $hours_worked = '--';
                                    if (!$is_active) {
                                        $hours_worked = !empty($log['num_hr']) ? $log['num_hr'] . ' hrs' : number_format((strtotime($log['time_out']) - strtotime($log['time_in'])) / 3600, 2) . ' hrs';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/images/<?php echo htmlspecialchars($photo); ?>" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($fullname); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($log['department']); ?> • <?php echo htmlspecialchars($log['emp_code']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $time_in; ?></td>
                                    <td><?php echo $time_out; ?></td>
                                    <td class="text-center"><?php echo $badges_html; ?></td>
                                    <td class="fw-bold text-end text-gray-700"><?php echo $hours_worked; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>

<script>
    $(document).ready(function() {
        var table = $('#todayTable').DataTable({
            "order": [[ 1, "desc" ]],
            "pageLength": 25,
            "dom": 'rtip', 
            "language": { "emptyTable": "No attendance records found for today." }
        });
        $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
    });
</script>