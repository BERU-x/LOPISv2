<script>
let otHistoryTable;
let spinnerStartTime = 0;

// --- GLOBAL HELPER FUNCTIONS ---

function updateLastSyncTime() {
    const now = new Date();
    $('#last-updated-time').text(now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' }));
}

function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minTime = 1000;
    const elapsed = new Date().getTime() - spinnerStartTime;
    const finish = () => { icon.removeClass('fa-spin text-teal'); updateLastSyncTime(); };
    if(elapsed < minTime) setTimeout(finish, minTime - elapsed); else finish();
}

// --- NEW: FUNCTION TO FETCH AND DISPLAY RAW OT HOURS ---
function fetchRawOTHours(otDate) {
    // Ensure these IDs match your HTML:
    const displayContainer = $('#rawOtDisplay'); 
    const hoursInput = $('#hours_requested'); 
    
    // 1. Reset Display and Input Max
    displayContainer.html('<i class="fas fa-spinner fa-spin me-2 text-teal"></i> Fetching raw log...');
    hoursInput.attr('max', 9999); // Reset max to a high number
    
    if (!otDate) {
        displayContainer.html('<span class="text-muted">Please select an overtime date to check log availability.</span>');
        hoursInput.attr('max', 0).val(''); // Prevent submission if no date is chosen
        return;
    }

    // 2. AJAX Call to Server
    $.ajax({
        url: 'api/overtime_action.php?action=validate_ot_request',
        type: 'POST',
        data: { ot_date: otDate },
        dataType: 'json',
        success: function(val_res) {
            const rawOtHours = parseFloat(val_res.raw_ot_hr || 0);

            if (val_res.status === 'success') {
                if (rawOtHours > 0) {
                    // Success: Display hours and set max attribute
                    displayContainer.html(`
                        <span class="text-success fw-bold">
                            <i class="fas fa-check-circle me-1"></i> Available Log: ${rawOtHours.toFixed(2)} hrs
                        </span>
                    `);
                    hoursInput.attr('max', rawOtHours); 
                } else {
                    // Success but Zero Hours: Biometric log found, but OT is 0
                    displayContainer.html(`
                        <span class="text-warning fw-bold">
                            <i class="fas fa-exclamation-triangle me-1"></i> No Raw OT Log Found (${rawOtHours.toFixed(2)} hrs) for ${otDate}.
                        </span>
                    `);
                    hoursInput.attr('max', 0); 
                }
            } else {
                // Server error or specific validation message
                displayContainer.html(`<span class="text-danger">Error fetching log: ${val_res.message || 'Server error'}</span>`);
                hoursInput.attr('max', 0);
            }
        },
        error: function() {
            displayContainer.html('<span class="text-danger">Failed to connect to log server. Cannot validate hours.</span>');
            hoursInput.attr('max', 0);
        }
    });
}
// -----------------------------------------------------------------

$(document).ready(function() {

    // --- 1. INITIALIZE DATATABLE ---
    if ($('#otHistoryTable').length) {
        otHistoryTable = $('#otHistoryTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip', 
            ajax: {
                url: "api/overtime_action.php?action=fetch", 
                type: "GET",
            },
            drawCallback: function(settings) {
                if ($('#refresh-spinner').hasClass('fa-spin')) stopSpinnerSafely();
                else updateLastSyncTime();
            },
            columns: [
                { data: 'ot_date', className: 'text-nowrap' }, 
                { data: 'status', className: 'text-center' },
                { 
                    data: null, 
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        let otId = row.id || 0; 
                        return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold btn-view-details" data-row-id="${otId}">
                                    <i class="fas fa-eye me-1"></i> Details
                                </button>`;
                    }
                }
            ],
            order: [[0, 'desc']],
            language: {
                processing: "<div class='spinner-border text-teal' role='status'><span class='visually-hidden'>Loading...</span></div>",
                emptyTable: "No overtime requests submitted yet."
            }
        });

        // --- HANDLE VIEW DETAILS CLICK ---
        $('#otHistoryTable tbody').on('click', '.btn-view-details', function () {
            var tr = $(this).closest('tr');
            var rowData = otHistoryTable.row(tr).data();

            var contentHtml = `
                <div class="text-start p-2">
                    <div class="mb-2"><strong>Date:</strong> ${rowData.ot_date}</div>
                    <div class="mb-2"><strong>Raw OT (Log):</strong> ${rowData.raw_ot_hr} hrs</div>
                    <div class="mb-2"><strong>Requested:</strong> ${rowData.hours_requested} hrs</div>
                    <div class="mb-2"><strong>Approved:</strong> ${rowData.hours_approved} hrs</div>
                    <div class="mb-2"><strong>Reason:</strong> <br><span class="text-muted fst-italic">"${rowData.reason || 'No reason provided.'}"</span></div>
                    <div class="mt-3 text-center border-top pt-3"><strong>Status:</strong> ${rowData.status}</div>
                </div>
            `;

            Swal.fire({
                title: 'Overtime Details',
                html: contentHtml,
                icon: 'info',
                confirmButtonText: 'Close',
                confirmButtonColor: '#0CC0DF'
            });
        });
    }

    // ------------------------------------------------------------------
    // --- 4. NEW EVENT HANDLERS FOR THE FORM ---
    // ------------------------------------------------------------------
    
    // Event 1: When the date changes, fetch and display raw OT hours
    // NOTE: Using 'change input' to ensure wide compatibility across date pickers.
    $('#ot_date').on('change input', function() {
        fetchRawOTHours($(this).val());
    });
    
    // Event 2: Real-time client-side cap check on requested hours input
    $('#hours_requested').on('input', function() {
        const requested = parseFloat($(this).val());
        const max = parseFloat($(this).attr('max'));
        
        if (!isNaN(requested) && requested > max) {
            $(this).val(max); // Cap the input value
            
            // Optional: Provide visual feedback if a value was capped
            if (max > 0 && !$('#rawOtDisplay').text().includes('(Capped!)')) {
                 // Append the message only once
                 $('#rawOtDisplay').append('<span class="text-danger small ms-2"> (Capped!)</span>');
                 // Remove the message after a short delay
                 setTimeout(() => $('#rawOtDisplay span:last-child').remove(), 2000);
            }
        }
    });

    // Optional: Trigger the check on page load if the date field is pre-filled
    fetchRawOTHours($('#ot_date').val());


    // ------------------------------------------------------------------
    // --- 2. HANDLE FORM SUBMISSION WITH PRE-VALIDATION ---
    // ------------------------------------------------------------------
    $('#otRequestForm').on('submit', function(e) {
        e.preventDefault();
        
        let form = this;
        let formData = new FormData(form);
        
        const otDate = formData.get('ot_date');
        const requestedHours = parseFloat(formData.get('hours_requested'));
        const maxHours = parseFloat($('#hours_requested').attr('max')); 

        if (isNaN(requestedHours) || requestedHours <= 0) {
            Swal.fire('Error', 'Please enter a valid amount of requested hours.', 'error');
            return;
        }

        // --- CLIENT-SIDE MAX CHECK (Initial block using max attribute) ---
        if (requestedHours > maxHours) {
            Swal.fire({
                title: 'Validation Failed',
                icon: 'error',
                html: `Requested hours (${requestedHours} hrs) cannot exceed the available log hours (${maxHours.toFixed(2)} hrs).<br>Please adjust your request.`,
                confirmButtonText: 'Got It'
            });
            return;
        }
        
        // --- STEP 1: Final Server-side validation check (The core validation)
        Swal.fire({
            title: 'Validating Request...',
            text: 'Checking against biometric logs...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'api/overtime_action.php?action=validate_ot_request',
            type: 'POST',
            data: { ot_date: otDate },
            dataType: 'json',
            success: function(val_res) {
                const serverRawOtHours = parseFloat(val_res.raw_ot_hr || 0);

                if (val_res.status === 'success') {
                    
                    // --- FINAL SERVER CAP CHECK ---
                    if (requestedHours > serverRawOtHours) {
                        Swal.fire({
                            title: 'Validation Failed (Server Check)',
                            icon: 'error',
                            html: `The server confirmed you only have <b>${serverRawOtHours} raw OT hours</b>. Please adjust your request.`,
                            confirmButtonText: 'Got It'
                        });
                        return; 
                    }
                    
                    // --- STEP 2: Proceed with final form submission
                    Swal.fire({
                        title: 'Submitting Request...',
                        text: 'Sending request to server...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.ajax({
                        url: 'api/overtime_action.php?action=create',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        processData: false, 
                        contentType: false, 
                        success: function(res) {
                            if(res.status === 'success') {
                                Swal.fire('Success', res.message, 'success');
                                $(form)[0].reset();
                                otHistoryTable.ajax.reload(); 
                                // Reset the raw OT display after successful submission
                                fetchRawOTHours($('#ot_date').val()); 
                            } else if (res.status === 'warning') {
                                Swal.fire('Warning', res.message, 'warning');
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        },
                        error: function(xhr) {
                            Swal.fire('Error', 'Server request failed during creation.', 'error');
                        }
                    });

                } else {
                    Swal.fire('Error', val_res.message || 'Validation failed due to a server error.', 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Cannot validate biometric data. Check API endpoint.', 'error');
            }
        });
    });

    // --- 3. LINK REFRESH BUTTON ---
    window.refreshPageContent = function() {
        spinnerStartTime = new Date().getTime(); 
        $('#refresh-spinner').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');
        if (otHistoryTable) {
            otHistoryTable.ajax.reload(null, false);
        } else {
             stopSpinnerSafely();
        }
    };
});
</script>