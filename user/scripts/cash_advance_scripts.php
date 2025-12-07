<script>
// --- GLOBAL HELPER: Master Refresher ---
let caTable;
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

    // --- 1. FETCH STATS ---
    function loadStats() {
        $.ajax({
            url: 'api/cash_advance_action.php?action=stats',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#stat-pending-total').text('₱' + res.pending_total);
                }
            }
        });
    }
    loadStats(); 

    // --- 2. INITIALIZE DATATABLE ---
    if ($('#myRequestsTable').length) {
        caTable = $('#myRequestsTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip', 
            order: [[0, 'desc']],
            
            ajax: {
                url: "api/cash_advance_action.php?action=fetch", 
                type: "GET",
            },
            
            drawCallback: function(settings) {
                if ($('#refresh-spinner').hasClass('fa-spin')) stopSpinnerSafely();
                else updateLastSyncTime();
            },

            columns: [
                { data: 'date_col', className: 'fw-bold text-gray-700' },
                { data: 'amount', className: 'text-center fw-bold' },
                { data: 'status', className: 'text-center' },
                { 
                    data: null, 
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold btn-view-details">
                                    <i class="fas fa-eye me-1"></i> Details
                                </button>`;
                    }
                }
            ],
            
            language: {
                processing: "<div class='spinner-border text-teal' role='status'><span class='visually-hidden'>Loading...</span></div>",
                emptyTable: "No request history found."
            }
        });

        // --- HANDLE VIEW DETAILS CLICK ---
        $('#myRequestsTable tbody').on('click', '.btn-view-details', function () {
            var tr = $(this).closest('tr');
            var row = caTable.row(tr);
            var data = row.data();

            // Status is already formatted HTML from the API
            var contentHtml = `
                <div class="text-start p-2">
                    <div class="mb-2"><strong>Date Needed:</strong> ${data.date_needed}</div>
                    <div class="mb-2"><strong>Amount:</strong> <span class="text-teal fw-bold">${data.amount}</span></div>
                    <div class="mb-2"><strong>Purpose:</strong> <br><span class="text-muted fst-italic">"${data.remarks}"</span></div>
                    <div class="mt-3 text-center border-top pt-3"><strong>Status:</strong> ${data.status}</div>
                </div>
            `;

            Swal.fire({
                title: 'Request Details',
                html: contentHtml,
                icon: 'info',
                confirmButtonText: 'Close',
                confirmButtonColor: '#0CC0DF'
            });
        });
    }

    // --- 3. HANDLE FORM SUBMISSION ---
    $('#caRequestForm').on('submit', function(e) {
        e.preventDefault(); 
        var formData = new FormData(this);

        Swal.fire({
            title: 'Submit Request?',
            text: "Are you sure you want to submit this cash advance request?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#20c997',
            confirmButtonText: 'Yes, Submit'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/cash_advance_action.php?action=create', 
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() { Swal.showLoading(); },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Submitted!', response.message, 'success');
                            $('#caRequestForm')[0].reset();
                            $('#newRequestModal').modal('hide');
                            caTable.ajax.reload();
                            loadStats(); 
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Server connection failed.', 'error'); }
                });
            }
        });
    });

    // --- 4. LINK REFRESH BUTTON ---
    window.refreshPageContent = function() {
        spinnerStartTime = new Date().getTime(); 
        $('#refresh-spinner').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');
        loadStats();
        caTable.ajax.reload(null, false);
    };
});
</script>