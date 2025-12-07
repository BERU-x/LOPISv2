<script>
// --- GLOBAL STATE VARIABLES ---
let attendanceTable; 
let spinnerStartTime = 0; // Tracks when the spin started to ensure smooth animation

// 1. HELPER: Updates the 'Last Updated' timestamp
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 2. HELPER: Stops the spinner safely (prevents flickering on fast networks)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; // Minimum spin time in ms
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
    
    // Check if table exists
    if ($('#attendanceTable').length) {
        
        // Destroy existing if needed
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
            $('#attendanceTable').DataTable().destroy();
        }

        attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            order: [[0, 'desc']], // Default sort by date descending
            searching: false, // Explicitly disabled search in front-end
            dom: 'rtip', 

            ajax: {
                url: "api/attendance_action.php?action=fetch", 
                type: "GET",
                data: function (d) {
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },

            // ⭐ CRITICAL: Triggers the safe stop function after data is received and drawn
            drawCallback: function(settings) {
                const icon = $('#refresh-spinner');
                // Only run logic if the icon is currently spinning
                if (icon.hasClass('fa-spin')) { 
                    stopSpinnerSafely();
                } else {
                    // Update time immediately on initial load
                    updateLastSyncTime(); 
                }
            },
            
            columns: [
                // Col 0: Date
                { data: 'date', className: "text-nowrap" },
                
                // Col 1: Time In
                { data: 'time_in', className: "fw-bold text-dark" },

                // Col 2: Status (Updated for Multi-Status)
                { 
                    data: 'status', 
                    className: "text-center text-wrap", // Added text-wrap for multiple badges
                    width: "15%", // Optional: give it some room
                    render: function (data) { 
                        return data; // Backend sends the full HTML badges
                    }
                }, 
                
                // Col 3: Time Out (Updated for Date Display)
                { 
                    data: 'time_out', 
                    className: "fw-bold text-dark",
                    render: function(data) {
                        return data; // Backend sends HTML (time + optional date)
                    }
                },

                // Col 4: Hours
                { 
                    data: 'num_hr',
                    className: "text-center fw-bold text-gray-700",
                    render: function (data) {
                        return data > 0 ? parseFloat(data).toFixed(2) : '—';
                    }
                },

                // Col 5: Overtime
                { 
                    data: 'overtime_hr',
                    className: "text-center", 
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
            attendanceTable.ajax.reload();
        });

        // ⭐ MASTER REFRESHER FUNCTION
        // Connects this table to the topbar 'Refresh' button
        window.refreshPageContent = function() {
            // 1. Record Start Time
            spinnerStartTime = new Date().getTime(); 
            
            // 2. Start Visual feedback & Text
            $('#refresh-spinner').addClass('fa-spin text-teal');
            $('#last-updated-time').text('Syncing...');
            
            // 3. Reload table (triggers drawCallback when done)
            attendanceTable.ajax.reload(null, false);
        };
    }
});
</script>