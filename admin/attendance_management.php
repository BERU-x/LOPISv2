<?php
// attendance_management.php

// --- 1. SET PAGE CONFIGURATIONS ---
$page_title = 'Attendance Management';
$current_page = 'attendance'; 

// --- DATABASE CONNECTION & TEMPLATES ---
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-calendar-alt me-2"></i> <?php echo $page_title; ?>
        </h1>
        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-teal shadow-sm">
            <i class="fas fa-download fa-sm text-white-50 me-1"></i> Generate Attendance Report
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-gray-800">Filter Records</h6>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filter_start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="filter_start_date">
                </div>
                <div class="col-md-3">
                    <label for="filter_end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="filter_end_date">
                </div>
                <div class="col-md-2">
                    <button id="applyFilterBtn" class="btn btn-teal w-100">
                        <i class="fas fa-filter me-1"></i> Apply Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button id="clearFilterBtn" class="btn btn-secondary w-100">
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-teal">Daily Attendance Log</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" 
               id="attendanceTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Status In</th>
                            <th>Time Out</th>
                            <th>Status Out</th>
                            <th>Regular Hrs</th> <th>Overtime Hrs</th> <th>Status Based</th>
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