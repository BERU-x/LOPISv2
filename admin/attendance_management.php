<?php
// attendance_management.php

// --- 1. SET PAGE CONFIGURATIONS ---
$page_title = 'Attendance Logs';
$current_page = 'attendance_management'; 

// --- DATABASE CONNECTION & TEMPLATES ---
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Attendance Logs</h1>
            <p class="mb-0 text-muted">View and filter historical attendance records.</p>
        </div>
        <a href="#" class="btn btn-teal shadow-sm fw-bold">
            <i class="fas fa-download me-2"></i> Generate Report
        </a>
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
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search records..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="attendanceTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee</th>
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
            ordering: false, // Server handles sorting
            
            // Clean DOM (Hides default search 'f' and length 'l')
            dom: 'rtip', 

            ajax: {
                url: "fetch/attendance_ssp.php", 
                type: "GET",
                data: function (d) {
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },
            
            columns: [
                // Col 0: Employee
                { 
                    data: 'employee_name',
                    render: function(data, type, row) {
                        // Check if photo exists in row data, else use default
                        var photo = row.photo ? '../assets/images/' + row.photo : '../assets/images/default.png';
                        var id = row.employee_id ? row.employee_id : '';

                        return `
                            <div class="d-flex align-items-center">
                                <img src="${photo}" class="rounded-circle me-3 border shadow-sm" 
                                     style="width: 40px; height: 40px; object-fit: cover;" 
                                     onerror="this.src='../assets/images/default.png'">
                                <div>
                                    <div class="fw-bold text-dark">${data}</div>
                                    <div class="small text-muted">${id}</div>
                                </div>
                            </div>
                        `;
                    }
                },
                
                // Col 1: Date (Server formatted)
                { 
                    data: 'date',
                    className: "text-nowrap"
                },
                
                // Col 2: Time In (Server formatted)
                { 
                    data: 'time_in',
                },

                // Col 3: Status (Server sends HTML Badge)
                { 
                    data: 'status', 
                    className: "text-center",
                    render: function (data) {
                        return data; // Output the HTML directly
                    }
                }, 
                
                // Col 4: Time Out (Server formatted)
                { data: 'time_out' },

                // Col 5: Hours
                { 
                    data: 'num_hr',
                    className: "text-center fw-bold text-gray-700",
                    render: function (data) {
                        return data > 0 ? parseFloat(data).toFixed(2) : '—';
                    }
                },

                // Col 6: Overtime
                { 
                    data: 'overtime_hr',
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

        // --- Custom Search Binding ---
        $('#customSearch').on('keyup', function() {
            attendanceTable.search(this.value).draw();
        });

        // --- Filter Button Logic ---
        function toggleFilterButtons(isLoading) {
            $('#applyFilterBtn').prop('disabled', isLoading).html(
                isLoading ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' : '<i class="fas fa-filter me-1"></i> Apply'
            );
        }

        attendanceTable.on('processing.dt', function (e, settings, processing) {
            toggleFilterButtons(processing);
        });

        $('#applyFilterBtn').off('click').on('click', function() {
            attendanceTable.ajax.reload();
        });
        
        $('#clearFilterBtn').off('click').on('click', function() {
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            $('#customSearch').val(''); 
            attendanceTable.search('').draw(); 
            attendanceTable.ajax.reload();
        });
    }
});
</script>