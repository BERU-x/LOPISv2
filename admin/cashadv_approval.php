<?php
// cash_advance_approval.php (Admin/Manager Portal)

// --- 1. CONFIGURATION & INCLUDES ---
date_default_timezone_set('Asia/Manila');
$page_title = 'Cash Advance Approval';
$current_page = 'cash_advance_approval'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Cash Advance Requests</h1>
            <p class="mb-0 text-muted">Review, filter, and approve submitted employee cash advance requests.</p>
        </div>
        <a href="#" class="btn btn-teal shadow-sm fw-bold">
             <i class="fas fa-file-export me-2"></i> Export Report
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-gray-800">
                <i class="fas fa-filter me-2 text-label"></i>Filter Requests
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
            <h6 class="m-0 font-weight-bold text-label"><i class="fas fa-money-bill-wave me-2"></i>Request List</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="caTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee</th>
                            <th class="border-0 text-center">Date Requested</th>
                            <th class="border-0 text-center">Amount</th>
                            <th class="border-0">Purpose (Remarks)</th>
                            <th class="border-0 text-center">Approved</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Created At</th>
                            <th class="border-0 text-center" style="min-width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // 1. Initialize DataTables
    var caTable = $('#caTable').DataTable({
        processing: true,
        serverSide: true,
        stateSave: true, // OPTIMIZATION: Remembers page number and filters on refresh
        ordering: true, 
        dom: 'rtip', // Hide default search box to use custom one
        
        ajax: {
            url: "fetch/cash_advance_ssp.php",
            type: "GET",
            data: function (d) {
                // Pass custom filters to the server
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
            },
            error: function(xhr, error, thrown) {
                console.error("DataTables Error:", error);
                // Optional: Show a subtle error message in the table
                $(".dataTables_empty").text("Error loading data. Please check connection.");
            }
        },
        
        columns: [
            // Col 0: Employee
            { 
                data: 'employee_name',
                render: function(data, type, row) {
                    // 1. Safe Image Path Logic
                    // Check if row.photo is not null AND not just empty whitespace
                    var hasPhoto = row.photo && row.photo.trim() !== '';
                    
                    // 2. Define the image source
                    // JS paths are relative to the admin folder
                    var imgPath = hasPhoto ? '../assets/images/' + row.photo : '../assets/images/default.png';
                    
                    // 3. Define the Employee ID
                    var empId = row.employee_id ? row.employee_id : '';

                    return `
                        <div class="d-flex align-items-center">
                            <img src="${imgPath}" 
                                 class="rounded-circle me-3 border shadow-sm" 
                                 style="width: 40px; height: 40px; object-fit: cover;" 
                                 alt="User"
                                 onerror="this.onerror=null; this.src='../assets/images/default.png';">
                            <div>
                                <div class="fw-bold text-dark">${data ?? 'Unknown'}</div>
                                <div class="small text-muted">${empId}</div>
                            </div>
                        </div>
                    `;
                }
            },

            // Col 1: Date Requested (Mapped from Date Needed)
            { data: 'date_needed', className: 'text-center small' },
            
            // Col 2: Amount Requested
            { 
                data: 'amount_requested',
                className: 'text-center fw-bold text-gray-800',
                render: function(data) {
                    return '₱' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2});
                }
            },
            
            // Col 3: Remarks / Purpose
            { 
                data: 'purpose',
                className: 'small text-muted',
                orderable: false,
                render: function(data) {
                    return data && data.length > 30 ? data.substr(0, 30) + '...' : data;
                }
            },
            
            // Col 4: Amount Approved (Logic handled in SSP)
            { 
                data: 'amount_approved',
                className: 'text-center fw-bold text-muted',
                orderable: false
            },
            
            // Col 5: Status HTML (Badge)
            { data: 'status', className: 'text-center' },
            
            // Col 6: Created At (Date)
            { data: 'created_at', className: 'text-center small text-muted' },

            // Col 7: Actions
            { 
                data: 'raw_data',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data) {
                    if (data.status === 'Pending') {
                        return `
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-teal btn-approve" 
                                    data-id="${data.id}" 
                                    data-requested="${data.amount_requested}"
                                    title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-reject" 
                                    data-id="${data.id}"
                                    title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `;
                    }
                    return '<span class="text-muted text-xs"><i class="fas fa-lock me-1"></i>Locked</span>';
                }
            }
        ],
        language: {
            processing: "<div class='spinner-border text-teal' role='status'></div>",
            emptyTable: "No cash advance requests found."
        }
    });

    // 2. Debounced Search (OPTIMIZATION)
    // Prevents AJAX spam while typing
    var searchTimeout;
    $('#customSearch').on('keyup', function() {
        clearTimeout(searchTimeout);
        var value = this.value;
        searchTimeout = setTimeout(function() {
            caTable.search(value).draw();
        }, 400); // Wait 400ms after typing stops
    });

    // 3. Filter Buttons
    $('#applyFilterBtn').click(function() {
        caTable.ajax.reload();
    });
    
    $('#clearFilterBtn').click(function() {
        $('#filter_start_date, #filter_end_date, #customSearch').val(''); 
        caTable.search('').ajax.reload();
    });

    // 4. Action Logic (Approve/Reject)
    
    // Core AJAX Processor
    function processRequest(id, action, amount = 0) {
        Swal.fire({
            title: 'Processing...',
            text: 'Please wait while we update the request.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'functions/process_ca_approval.php',
            type: 'POST',
            dataType: 'json',
            data: { id: id, action: action, amount: amount },
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    caTable.ajax.reload(null, false); // Reload but keep pagination
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            }
        });
    }

    // Approve Button Click
    $('#caTable tbody').on('click', '.btn-approve', function() {
        var id = $(this).data('id');
        var requested = parseFloat($(this).data('requested'));

        Swal.fire({
            title: 'Approve Request',
            html: `Request ID: <b>${id}</b><br>Amount: <b>₱${requested.toLocaleString()}</b>`,
            input: 'text', // Using text to allow custom validation easily
            inputValue: requested,
            inputLabel: 'Confirm Approved Amount',
            showCancelButton: true,
            confirmButtonText: 'Approve',
            confirmButtonColor: '#20c997', // Teal
            preConfirm: (value) => {
                var amount = parseFloat(value);
                if (!value || isNaN(amount) || amount <= 0) {
                    Swal.showValidationMessage('Please enter a valid amount');
                } else if (amount > requested) {
                    Swal.showValidationMessage('Amount cannot exceed requested value');
                }
                return amount;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                processRequest(id, 'approve', result.value);
            }
        });
    });

    // Reject Button Click
    $('#caTable tbody').on('click', '.btn-reject', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Reject Request?',
            text: "This status will be set to Cancelled.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Reject'
        }).then((result) => {
            if (result.isConfirmed) {
                processRequest(id, 'reject');
            }
        });
    });

});
</script>