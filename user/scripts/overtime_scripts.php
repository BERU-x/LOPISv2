<script>
let otHistoryTable;
let spinnerStartTime = 0;

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
                // Col 0: Date
                { data: 'ot_date', className: 'text-nowrap' }, 
                
                // Col 1: Status
                { data: 'status', className: 'text-center' },

                // Col 2: Action (View Details Button)
                { 
                    data: null, // Use null because we need access to the whole row object
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold btn-view-details">
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
            // Get data from the row
            var tr = $(this).closest('tr');
            var row = otHistoryTable.row(tr);
            var data = row.data();

            // Construct HTML for SweetAlert
            var contentHtml = `
                <div class="text-start p-2">
                    <div class="mb-2"><strong>Date:</strong> ${data.ot_date}</div>
                    <div class="mb-2"><strong>Raw OT (Log):</strong> ${data.raw_ot_hr} hrs</div>
                    <div class="mb-2"><strong>Requested:</strong> ${data.hours_requested} hrs</div>
                    <div class="mb-2"><strong>Approved:</strong> ${data.hours_approved} hrs</div>
                    <div class="mb-2"><strong>Reason:</strong> <br><span class="text-muted fst-italic">"${data.reason}"</span></div>
                    <div class="mt-3 text-center border-top pt-3"><strong>Status:</strong> ${data.status}</div>
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

    // --- 2. HANDLE FORM SUBMISSION ---
    $('#otRequestForm').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        Swal.fire({
            title: 'Submitting Request...',
            text: 'Validating overtime hours...',
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
                    $('#otRequestForm')[0].reset();
                    otHistoryTable.ajax.reload(); 
                } else if (res.status === 'warning') {
                    Swal.fire('Warning', res.message, 'warning');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Server request failed. Check console.', 'error');
            }
        });
    });

    // --- 3. LINK REFRESH BUTTON ---
    window.refreshPageContent = function() {
        spinnerStartTime = new Date().getTime(); 
        $('#refresh-spinner').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');
        otHistoryTable.ajax.reload(null, false);
    };
});
</script>