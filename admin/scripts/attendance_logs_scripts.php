<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS (Defined Globally)
// ==============================================================================
let attendanceTable; 
let spinnerStartTime = 0; // Global variable to track when the spin started

// 1.1 HELPER FUNCTION: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.2 HELPER FUNCTION: Stops the spinner only after the minimum time has passed
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    if (timeElapsed < minDisplayTime) {
        // Wait the remaining time before stopping
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        // Stop immediately
        finalizeStop();
    }
}

// ⭐ 1.3 MASTER REFRESHER TRIGGER (Hook for Topbar/Buttons)
// Defined globally for immediate access by external scripts/templates.
window.refreshPageContent = function() {
    // 1. Record Start Time
    spinnerStartTime = new Date().getTime(); 
    
    // 2. Start Visual feedback & Text
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    // 3. Reload table (only if initialized)
    if (attendanceTable) {
        attendanceTable.ajax.reload(null, false);
    }
};


$(document).ready(function() {

    // Check if table exists
    if ($('#attendanceTable').length) {
        
        // Destroy existing if needed
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
            $('#attendanceTable').DataTable().destroy();
        }

        // Initialize DataTable
        attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            destroy: true, 
            ordering: false, 
            dom: 'rtip', 

            ajax: {
                url: "api/attendance_data.php", 
                type: "GET",
                data: function (d) {
                    // Pass filter values to the API
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },
            
            // ⭐ CRITICAL: Triggers the safe stop function after data is received and drawn
            drawCallback: function(settings) {
                const icon = $('#refresh-spinner');
                
                // Only run the time check if the icon is currently spinning.
                if (icon.hasClass('fa-spin')) { 
                    stopSpinnerSafely();
                } else {
                    // If not spinning (e.g., initial page load), just update the time immediately.
                    updateLastSyncTime(); 
                }
            },

            columns: [
                { 
                    data: 'employee_name',
                    className: 'align-middle', // Add alignment for consistency
                    render: function(data, type, row) {
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
                { data: 'date', className: "text-nowrap align-middle" },
                { data: 'time_in', className: "fw-bold text-dark align-middle" },
                // Assuming status column renders badges via API; otherwise, update render function
                { data: 'status', className: "text-center align-middle", render: function (data) { return data; } }, 
                { data: 'time_out', className: "fw-bold text-dark align-middle" },
                { 
                    data: 'num_hr',
                    className: "text-center fw-bold text-gray-700 align-middle",
                    render: function (data) {
                        return data > 0 ? parseFloat(data).toFixed(2) : '—';
                    }
                },
                { 
                    data: 'overtime_hr',
                    className: "text-center align-middle",
                    render: function (data) {
                        return (data > 0) ? '+' + parseFloat(data).toFixed(2) : '—';
                    }
                }
            ],
            
            language: {
                processing: "Loading...",
                emptyTable: "No attendance records found.",
                zeroRecords: "No matching records found."
            }
        });

        // --- Custom Search Binding ---
        $('#customSearch').on('keyup', function() {
            attendanceTable.search(this.value).draw();
        });

        // --- Buttons ---
        $('#applyFilterBtn').off('click').on('click', function() {
            // This button triggers a data fetch using the global hook
            window.refreshPageContent(); 
        });
        
        $('#clearFilterBtn').off('click').on('click', function() {
            // Reset filters first
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            $('#customSearch').val(''); 
            
            // Clear DataTables internal search then trigger refresh
            attendanceTable.search('').draw(); 
            window.refreshPageContent(); 
        });

        // No need to redefine window.refreshPageContent here, it's defined globally above.
    }
});
</script>