<?php
// today_attendance.php
$page_title = 'Today\'s Attendance';
$current_page = 'today_attendance'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Attendance Monitor</h1>
            <p class="mb-0 text-muted">Date: <span class="fw-bold text-gray-600"><?php echo date('F d, Y'); ?></span></p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Present Today</div>
                            <div class="h5 mb-0 font-weight-bold text-dark">
                                <span id="val-present"><i class="fas fa-spinner fa-spin"></i></span> / <span id="val-total"></span>
                            </div>
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
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Absent</div>
                            <div class="h5 mb-0 font-weight-bold text-dark" id="val-absent"><i class="fas fa-spinner fa-spin"></i></div>
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
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Late Arrivals</div>
                            <div class="h5 mb-0 font-weight-bold text-dark" id="val-late"><i class="fas fa-spinner fa-spin"></i></div>
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
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">On Time</div>
                            <div class="h5 mb-0 font-weight-bold text-dark" id="val-ontime"><i class="fas fa-spinner fa-spin"></i></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-600"><i class="fas fa-list-alt me-2"></i>Real-time Logs</h6>
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search logs..." aria-label="Search">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="todayTable" width="100%" cellspacing="0">
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
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/today_attendance_scripts.php'; ?>