<?php
// my_attendance.php - Employee Portal

// --- 1. SET PAGE CONFIGURATIONS ---
$page_title = 'My Attendance Logs';
$current_page = 'attendance'; 

// --- DATABASE CONNECTION & TEMPLATES ---
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['employee_id'])) {
    header('Location: login.php');
    exit();
}
$employee_id = $_SESSION['employee_id'];
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">My Attendance Logs</h1>
            <p class="mb-0 text-muted">View your clock in and out history.</p>
        </div>
            </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-gray-800">
                <i class="fas fa-filter me-2 text-secondary"></i>Filter Records
            </h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filter_start_date" class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Start Date</label>
                    <input type="date" class="form-control" id="filter_start_date">
                </div>
                <div class="col-md-3">
                    <label for="filter_end_date" class="form-label text-xs font-weight-bold text-uppercase text-gray-600">End Date</label>
                    <input type="date" class="form-control" id="filter_end_date">
                </div>
                <div class="col-md-2">
                    <button id="applyFilterBtn" class="btn btn-teal w-100 fw-bold shadow-sm">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>
                <div class="col-md-2">
                    <button id="clearFilterBtn" class="btn btn-secondary w-100 fw-bold shadow-sm">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-list-alt me-2"></i>History Log</h6>
            
                    </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="attendanceTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-label text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Date</th>
                            <th class="border-0">Time In</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0">Time Out</th>
                            <th class="border-0 text-center">Hrs</th>
                            <th class="border-0 text-center">OT</th>
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

<script>
$(document).ready(function() {
    
    // Employee ID passed from PHP
    const EMPLOYEE_ID = '<?php echo $employee_id; ?>';

    // Check if table exists
    if ($('#attendanceTable').length) {
        
        // Destroy existing if needed to prevent duplicates on reload
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
            $('#attendanceTable').DataTable().destroy();
        }

        var attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            destroy: true, 
            ordering: true, // Re-enabled ordering for employee view
            order: [[0, 'desc']], // Default sort by date descending
            searching: false, // Explicitly disabled search in front-end
            
            // Clean DOM (Hides default search 'f' and length 'l')
            dom: 'rtip', 

            ajax: {
                // NOTE: You need to create 'user/fetch/my_attendance_ssp.php'
                url: "fetch/attendance_ssp.php", 
                type: "GET",
                data: function (d) {
                    // PASS EMPLOYEE ID FOR SERVER-SIDE FILTERING
                    d.employee_id = EMPLOYEE_ID;
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },
            
            columns: [
                // Col 0: Date (Index 0)
                { 
                    data: 'date',
                    className: "text-nowrap"
                },
                
                // Col 1: Time In (Index 1)
                { 
                    data: 'time_in',
                    className: "fw-bold text-dark"
                },

                // Col 2: Status (Index 2)
                { 
                    data: 'status', 
                    className: "text-center",
                    render: function (data) {
                        return data; // Output the HTML directly
                    }
                }, 
                
                // Col 3: Time Out (Index 3)
                { 
                    data: 'time_out',
                    className: "fw-bold text-dark",
                },

                // Col 4: Hours (Index 4)
                { 
                    data: 'num_hr',
                    className: "text-center fw-bold text-gray-700",
                    render: function (data) {
                        return data > 0 ? parseFloat(data).toFixed(2) : '—';
                    }
                },

                // Col 5: Overtime (Index 5)
                { 
                    data: 'overtime_hr',
                    className: "text-center", // Added text-center class for consistency
                    render: function (data) {
                        return (data > 0) ? '+' + parseFloat(data).toFixed(2) : '—';
                    }
                }
            ],
            
            language: {
                processing: "<div class='spinner-border text-teal' role='status'><span class='visually-hidden'>Loading...</span></div>",
                emptyTable: "No attendance records found.",
                zeroRecords: "No matching records found."
            }
        });

        // --- Custom Search Binding (Removed: Not needed for single employee) ---
        // $('#customSearch').on('keyup', function() {
        //     attendanceTable.search(this.value).draw();
        // });

        // --- Filter Button Logic (Simplified) ---
        function toggleFilterButtons(isLoading) {
            $('#applyFilterBtn').prop('disabled', isLoading).html(
                isLoading ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' : '<i class="fas fa-filter me-1"></i> Apply'
            );
        }

        // Use DataTables processing flag to show loading state
        attendanceTable.on('processing.dt', function (e, settings, processing) {
            toggleFilterButtons(processing);
        });

        $('#applyFilterBtn').off('click').on('click', function() {
            attendanceTable.ajax.reload();
        });
        
        $('#clearFilterBtn').off('click').on('click', function() {
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            
            // Removed search clearing since search box is removed
            // attendanceTable.search('').draw(); 
            attendanceTable.ajax.reload();
        });
    }
});
</script>