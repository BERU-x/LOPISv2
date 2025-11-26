<?php
// overtime_approval.php (Admin/Manager Portal)

// --- TEMPLATE INCLUDES & INITIAL SETUP ---
// NOTE: Ensure 'header.php' includes 'checking.php' and grants access based on $_SESSION['usertype']
require 'template/header.php'; 
// Assuming database connection ($pdo) is available after header inclusion.
date_default_timezone_set('Asia/Manila');

// --- 1. SET PAGE CONFIGURATIONS (Assuming Admin/Manager role) ---
$page_title = 'Overtime Approval';
$current_page = 'overtime_approval'; 

// --- 2. TEMPLATE INCLUDES (Structure) ---
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">⏰ Overtime Requests</h1>
            <p class="mb-0 text-muted">Review, filter, and approve submitted employee overtime requests.</p>
        </div>
        <a href="#" class="btn btn-teal shadow-sm fw-bold">
             <i class="fas fa-file-export me-2"></i> Export Report
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-gray-800">
                <i class="fas fa-filter me-2 text-teal"></i>Filter Requests
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
            <h6 class="m-0 font-weight-bold text-teal"><i class="fas fa-list-alt me-2"></i>Overtime Requests</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search employee or status..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="overtimeTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee</th>
                            <th class="border-0 text-center">Date</th>
                            <th class="border-0 text-center">Raw OT (Log)</th> <th class="border-0">Reason</th>
                            <th class="border-0 text-center">Requested Hrs</th>
                            <th class="border-0 text-center">Approved Hrs</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Submitted On</th>
                            <th class="border-0 text-center">Actions</th>
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
    
    // --- DataTables Initialization ---
    if ($('#overtimeTable').length) {
        // Destroy existing DataTables instance if it exists
        if ($.fn.DataTable.isDataTable('#overtimeTable')) {
            $('#overtimeTable').DataTable().destroy();
        }

        var overtimeTable = $('#overtimeTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip',
            
            ajax: {
                url: "fetch/overtime_ssp.php",
                type: "GET",
                data: function (d) {
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },
            
            columns: [
                // Col 0: Employee Name
                { 
                    data: 'employee_name',
                    render: function(data, type, row) {
                        return `<div class="fw-bold text-dark">${data}</div>
                                <div class="small text-muted">${row.employee_id}</div>`;
                    }
                },
                // Col 1: Date
                { data: 'ot_date', className: 'text-center' },
                
                // Col 2: Raw OT (Log)
                { 
                    data: 'raw_ot_hr',
                    className: 'text-center text-danger fw-bold',
                    render: function(data) {
                        return data === '0.00 hrs' || data === '0.00' ? '—' : data;
                    }
                },
                
                // Col 3: Reason
                { 
                    data: 'reason',
                    orderable: false,
                    className: 'small'
                },
                
                // Col 4: Requested Hrs
                { 
                    data: 'hours_requested',
                    className: 'text-center fw-bold text-teal' 
                },
                
                // Col 5: Approved Hrs
                { 
                    data: 'hours_approved',
                    className: 'text-center fw-bold text-success',
                    render: function(data) {
                        return data === '—' ? '—' : data;
                    }
                },
                
                // Col 6: Status
                { 
                    data: 'status',
                    className: 'text-center'
                },
                
                // Col 7: Submitted On
                { 
                    data: 'created_at',
                    className: 'text-center small text-muted'
                },

                // Col 8: Actions (Approval Buttons)
                { 
                    data: 'raw_data',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data.status === 'Pending') {
                            return `
                                <button class="btn btn-sm btn-success btn-approve mb-1" data-id="${data.id}" data-requested="${data.hours_requested}">Approve</button>
                                <button class="btn btn-sm btn-danger btn-reject" data-id="${data.id}">Reject</button>
                            `;
                        } else {
                            return `<span class="text-muted small">${data.status}</span>`;
                        }
                    }
                }
            ],
            
            language: {
                processing: "<div class='spinner-border text-teal' role='status'><span class='visually-hidden'>Loading...</span></div>",
                emptyTable: "No overtime requests found matching the criteria."
            }
        });
        
        // --- AJAX Handler Function (The core of the fix) ---
        function processApproval(otId, action, hours = 0) {
            $.ajax({
                url: 'functions/process_ot_approval.php', // Your new endpoint
                type: 'POST',
                dataType: 'json',
                data: {
                    id: otId,
                    action: action,
                    hours: hours
                },
                beforeSend: function() {
                    // Disable buttons to prevent double clicks
                    $('.btn-approve, .btn-reject').prop('disabled', true);
                },
                success: function(response) {
                    $('.btn-approve, .btn-reject').prop('disabled', false);
                    if (response.status === 'success') {
                        Swal.fire('Success!', response.message, 'success');
                        overtimeTable.ajax.reload(null, false); // Reload DataTables
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $('.btn-approve, .btn-reject').prop('disabled', false);
                    console.error("AJAX Error:", status, error);
                    Swal.fire('Error', 'Server connection failed or an internal error occurred.', 'error');
                }
            });
        }


        // --- Event Handlers for Action Buttons ---

        // 1. Handle Approve Button Click
        $('#overtimeTable tbody').on('click', '.btn-approve', function() {
            var otId = $(this).data('id');
            // Extract the numeric part of the requested hours (e.g., '5.5 hrs' -> 5.5)
            var requestedHrsString = $(this).data('requested'); 
            var requestedHrs = parseFloat(requestedHrsString);

            Swal.fire({
                title: 'Approve Overtime',
                html: `Approve request ID: <b>${otId}</b>.<br>Requested: <b>${requestedHrs} hours</b>.
                       <hr>Enter **whole** hours to approve:`,
                input: 'number',
                // Suggest rounding down to the nearest whole hour
                inputValue: Math.floor(requestedHrs), 
                inputAttributes: {
                    min: 0,
                    // Cap input at the requested hours (rounded up)
                    max: Math.ceil(requestedHrs), 
                    step: 1
                },
                showCancelButton: true,
                confirmButtonText: 'Confirm Approval',
                preConfirm: (hours) => {
                    if (hours === null || hours === undefined || isNaN(hours) || hours < 0) {
                        Swal.showValidationMessage('Please enter a valid, non-negative number of whole hours.');
                    }
                    return hours;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    processApproval(otId, 'approve', result.value);
                }
            });
        });

        // 2. Handle Reject Button Click
        $('#overtimeTable tbody').on('click', '.btn-reject', function() {
            var otId = $(this).data('id');

            Swal.fire({
                title: 'Reject Overtime Request?',
                text: "This action cannot be undone. Request ID: " + otId,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Reject it'
            }).then((result) => {
                if (result.isConfirmed) {
                    processApproval(otId, 'reject');
                }
            });
        });


        // --- Custom Search & Filter Logic (remains unchanged) ---
        $('#customSearch').on('keyup', function() {
            overtimeTable.search(this.value).draw();
        });

        $('#applyFilterBtn').off('click').on('click', function() {
            overtimeTable.ajax.reload();
        });
        
        $('#clearFilterBtn').off('click').on('click', function() {
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            $('#customSearch').val(''); 
            overtimeTable.search('').ajax.reload();
        });
    }
});
</script>