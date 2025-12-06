<script>
// --- GLOBAL STATE VARIABLES AND HELPERS ---
var otTable;
var currentOTId;

// 1. NEW: Global variable to track when the spin started
let spinnerStartTime = 0; 

// 2. HELPER FUNCTION: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 3. HELPER FUNCTION: Stops the spinner only after the minimum time has passed (500ms)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    // Check if network fetch was faster than 500ms
    if (timeElapsed < minDisplayTime) {
        // Wait the remaining time before stopping
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        // Stop immediately
        finalizeStop();
    }
}
// ---------------------------------------------------

$(document).ready(function() {

    // 4. INITIALIZE DATATABLE
    otTable = $('#overtimeTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        dom: 'rtip',
        ajax: {
            url: "api/overtime_action.php?action=fetch",
            type: "GET",
            data: function(d) {
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
            }
        },

        // ⭐ CRITICAL MODIFICATION: Conditional drawCallback
        drawCallback: function(settings) {
            const icon = $('#refresh-spinner');
            // Check if spinning to avoid running stop logic on first load cleanup
            if (icon.hasClass('fa-spin')) {
                stopSpinnerSafely();
            } else {
                // Initial load: Just set the current time
                updateLastSyncTime(); 
            }
        },
        // --------------------------------

        columns: [
            // Col 1: Employee
            {
                data: 'employee_id',
                render: function(data, type, row) {
                    var photo = row.photo ? '../assets/images/'+row.photo : '../assets/images/default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 35px; height: 35px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark text-sm">${row.firstname} ${row.lastname}</div>
                                <div class="text-xs text-muted">${row.department || ''}</div>
                            </div>
                        </div>`;
                }
            },
            // Col 2: Date
            { data: 'ot_date', className: "text-center" },
            // Col 3: Req Hrs
            { data: 'hours_requested', className: "text-center fw-bold" },
            // Col 4: Status
            { 
                data: 'status', 
                className: "text-center",
                render: function(data) {
                    if(data == 'Approved') return '<span class="badge bg-success">Approved</span>';
                    if(data == 'Rejected') return '<span class="badge bg-danger">Rejected</span>';
                    return '<span class="badge bg-warning text-dark">Pending</span>';
                }
            },
            // Col 5: Actions
            {
                data: 'id',
                orderable: false,
                className: "text-center",
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="viewOT(${data})"><i class="fas fa-eye me-1"></i> Details </button>`;
                }
            }
        ]
    });

    // Filters - Use the hook for reloads
    $('#customSearch').on('keyup', function() { otTable.search(this.value).draw(); });
    
    $('#applyFilterBtn').on('click', function() { 
        window.refreshPageContent(); // Use the hook 
    });
    
    $('#clearFilterBtn').on('click', function() { 
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        otTable.search('').draw();
        window.refreshPageContent(); // Use the hook
    });

    // 5. MASTER REFRESHER HOOK (Modified)
    window.refreshPageContent = function() {
        // 1. Record Start Time
        spinnerStartTime = new Date().getTime(); 
        
        // 2. Start Visual feedback & Text
        $('#refresh-spinner').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');
        
        // 3. Reload Table
        otTable.ajax.reload(null, false);
    };

});

// --- VIEW DETAILS LOGIC (No change needed) ---
function viewOT(id) {
    currentOTId = id;
    
    // Reset Modal
    $('#modal_approved_input').val('').removeClass('d-none');
    $('#modal_approved_display').text('').addClass('d-none');
    $('#modal_actions').removeClass('d-none');

    $.ajax({
        url: 'api/overtime_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            // Populate Details
            var photo = data.photo ? '../assets/images/'+data.photo : '../assets/images/default.png';
            $('#modal_emp_photo').attr('src', photo);
            $('#modal_emp_name').text(data.firstname + ' ' + data.lastname);
            $('#modal_emp_dept').text(data.department);
            $('#modal_ot_date').text(data.ot_date);
            $('#modal_reason').text(data.reason || 'No reason provided.');
            $('#modal_raw_ot').text(parseFloat(data.raw_biometric_ot || 0).toFixed(2));
            $('#modal_req_hrs').text(parseFloat(data.hours_requested).toFixed(2));
            
            // Set Status Badge
            var statusHtml = '<span class="badge bg-warning text-dark">Pending</span>';
            if(data.status === 'Approved') statusHtml = '<span class="badge bg-success">Approved</span>';
            if(data.status === 'Rejected') statusHtml = '<span class="badge bg-danger">Rejected</span>';
            $('#modal_status').html(statusHtml);

            // Handle Logic based on status
            if(data.status === 'Pending') {
                // Default approved input to requested hours
                $('#modal_approved_input').val(data.hours_requested);
                $('#modal_actions').show(); // Show Approve/Reject buttons
            } else {
                // Read-only mode
                $('#modal_approved_input').addClass('d-none');
                $('#modal_approved_display').text(data.hours_approved + ' hrs').removeClass('d-none');
                $('#modal_actions').hide(); // Hide buttons
            }

            $('#viewOTModal').modal('show');
        }
    });
}

// --- APPROVE/REJECT LOGIC ---
function processOT(action) {
    var approvedHrs = $('#modal_approved_input').val();

    if(action === 'approve' && (approvedHrs === '' || parseFloat(approvedHrs) <= 0)) {
        Swal.fire('Error', 'Please enter valid approved hours.', 'error');
        return;
    }

    Swal.fire({
        title: action === 'approve' ? 'Approve Overtime?' : 'Reject Overtime?',
        text: action === 'approve' ? `Approved Hours: ${approvedHrs}` : "This cannot be undone.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'approve' ? '#1cc88a' : '#e74a3b',
        confirmButtonText: 'Yes, Confirm'
    }).then((result) => {
        if(result.isConfirmed) {
            $.ajax({
                url: 'api/overtime_action.php?action=update_status',
                type: 'POST',
                data: { 
                    id: currentOTId, 
                    status_action: action,
                    approved_hours: approvedHrs
                },
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        $('#viewOTModal').modal('hide');
                        window.refreshPageContent(); // Use the hook for refresh
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }
            });
        }
    });
}
</script>