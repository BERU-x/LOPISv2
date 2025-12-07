<script>
// --- GLOBAL STATE VARIABLES ---
let payslipTable; 
let spinnerStartTime = 0; // Global variable to track when the spin started

// 1. HELPER FUNCTION: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 2. HELPER FUNCTION: Stops the spinner only after the minimum time has passed
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    if (timeElapsed < minDisplayTime) {
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        finalizeStop();
    }
}

$(document).ready(function() {

    // --- A. FETCH STATISTICS ---
    function loadStats() {
        $.ajax({
            url: 'api/payslip_action.php?action=stats',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#stat-last-pay').text(data.last_net_pay);
                $('#stat-count').text(data.count);
            }
        });
    }
    loadStats(); // Call immediately

    // --- B. INITIALIZE DATATABLE ---
    if ($('#payslipTable').length) {
        
        if ($.fn.DataTable.isDataTable('#payslipTable')) {
            $('#payslipTable').DataTable().destroy();
        }

        payslipTable = $('#payslipTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            searching: false, // Employees usually don't need to search their own small history
            dom: 'rtip', 
            order: [[0, 'desc']], // Default sort by cut-off date descending
            
            ajax: {
                url: "api/payslip_action.php?action=fetch", 
                type: "GET",
                // Note: The backend API gets the session ID, so passing it explicitly is optional but harmless
                // data: function(d) { ... } 
            },

            // ⭐ CRITICAL: Triggers the safe stop function after data is received and drawn
            drawCallback: function(settings) {
                const icon = $('#refresh-spinner');
                if (icon.hasClass('fa-spin')) { 
                    stopSpinnerSafely();
                } else {
                    updateLastSyncTime(); 
                }
            },

            columns: [
                // Col 0: Cut-Off Period
                { 
                    data: 'cut_off_start',
                    render: function(data, type, row) {
                        return `<span class="fw-bold text-gray-700 small">${row.cut_off_start}</span> 
                                <i class="fas fa-arrow-right mx-1 text-xs text-muted"></i> 
                                <span class="fw-bold text-gray-700 small">${row.cut_off_end}</span>`;
                    }
                },
                // Col 1: Net Pay
                { 
                    data: 'net_pay', 
                    className: 'text-end fw-bolder', 
                    render: function(data) {
                        return '₱ ' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2});
                    }
                },
                // Col 2: Status
                { 
                    data: 'status',
                    className: 'text-center',
                    render: function(data) {
                        // Status 1 is usually 'Paid' or 'Approved' in this context
                        if(data == 1) {
                            return '<span class="badge bg-success shadow-sm px-2">Paid</span>';
                        }
                        return '<span class="badge bg-secondary">Pending</span>';
                    }
                },
                // Col 3: Action (Download)
                {
                    data: 'id',
                    orderable: false, 
                    className: 'text-center',
                    render: function(data) {
                        return `<a href="functions/print_payslip.php?id=${data}" target="_blank" class="btn btn-sm btn-outline-teal shadow-sm fw-bold">
                                    <i class="fas fa-download me-1"></i> Download PDF 
                                </a>`;
                    }
                }
            ],
            language: {
                processing: "Loading payslips...",
                emptyTable: "No payslip records found."
            }
        });

        // ⭐ MODIFIED: The Hard Link (Master Refresher Trigger)
        // This function connects the topbar 'refresh' button to this specific table
        window.refreshPageContent = function() {
            // 1. Reload Stats
            loadStats();

            // 2. Record Start Time
            spinnerStartTime = new Date().getTime(); 
            
            // 3. Start Visual feedback & Text
            $('#refresh-spinner').addClass('fa-spin text-teal');
            $('#last-updated-time').text('Syncing...');
            
            // 4. Reload table
            payslipTable.ajax.reload(null, false);
        };
    }
});
</script>