<?php
// admin/today_attendance.php
$page_title = 'Attendance Monitor';
$current_page = 'today_attendance';

require '../template/header.php'; 
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Attendance Monitor</h1>
            <p class="mb-0 text-muted">Real-time status for <span class="text-primary fw-bold"><?php echo date('F d, Y'); ?></span></p>
        </div>
        <div class="d-flex">
            <button id="btn-export-csv" class="btn btn-sm btn-outline-success shadow-sm me-2">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <?php 
        $cards = [
            ['Present', 'val-present', 'success', 'user-check', 'val-total'],
            ['Absent', 'val-absent', 'danger', 'user-times'],
            ['Late', 'val-late', 'warning', 'walking'],
            ['On Time', 'val-ontime', 'info', 'clock']
        ];
        foreach ($cards as $c): ?>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?php echo $c[2]; ?> shadow h-100 py-2 border-0">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo $c[2]; ?> text-uppercase mb-1"><?php echo $c[0]; ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="<?php echo $c[1]; ?>"><i class="fas fa-circle-notch fa-spin text-gray-300"></i></span>
                                <?php if(isset($c[4])): ?> 
                                    <small class="text-gray-400 font-weight-light" style="font-size: 0.8rem;">
                                        / <span id="<?php echo $c[4]; ?>">0</span>
                                    </small> 
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-<?php echo $c[3]; ?> fa-2x text-gray-200"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow mb-4 border-0">
        <div class="card-header py-3 d-flex align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-stream me-2"></i>Live Attendance Feed
            </h6>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search name, code, or department...">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle dataTable" id="todayTable" width="100%">
                    <thead class="bg-light text-gray-600 text-xs font-weight-bold text-uppercase">
                        <tr>
                            <th>Employee Detail</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th class="text-center">Log Status</th>
                            <th class="text-end">Work Duration</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/admin_attendance_live.js"></script>