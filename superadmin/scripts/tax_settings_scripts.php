<script>
// GLOBAL STATE
var taxTable;
let spinnerStartTime = 0;

function updateLastSyncTime() {
    $('#last-updated-time').text(new Date().toLocaleTimeString());
}
function stopSpinnerSafely() {
    setTimeout(() => { $('#refresh-spinner').removeClass('fa-spin text-teal'); updateLastSyncTime(); }, 500);
}
window.refreshPageContent = function() {
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    taxTable.ajax.reload(null, false);
};

// MODAL LOGIC
function openModal(id = null) {
    $('#taxForm')[0].reset();
    $('#tax_id').val('');
    $('#modalTitle').text(id ? 'Edit Tax Slab' : 'Add Tax Slab');

    if(id) {
        Swal.fire({ title: 'Loading...', didOpen: () => Swal.showLoading() });
        $.post('api/tax_settings_action.php', { action: 'get_details', id: id }, function(res) {
            Swal.close();
            if(res.status === 'success') {
                let d = res.details;
                $('#tax_id').val(d.id);
                $('#tier_name').val(d.tier_name);
                $('#min_income').val(d.min_income);
                $('#max_income').val(d.max_income);
                $('#base_tax').val(d.base_tax);
                $('#excess_rate').val(d.excess_rate);
                $('#taxModal').modal('show');
            }
        }, 'json');
    } else {
        $('#taxModal').modal('show');
    }
}

function deleteSlab(id) {
    Swal.fire({
        title: 'Delete Tax Slab?', text: "This might affect payroll calculations.",
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!'
    }).then((res) => {
        if(res.isConfirmed) {
            $.post('api/tax_settings_action.php', { action: 'delete', id: id }, function(data) {
                if(data.status === 'success') {
                    Swal.fire('Deleted', data.message, 'success');
                    window.refreshPageContent();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            }, 'json');
        }
    });
}

$(document).ready(function() {
    
    // INITIALIZE DATATABLE
    taxTable = $('#taxTable').DataTable({
        ajax: { url: 'api/tax_settings_action.php', type: 'POST', data: { action: 'fetch' } },
        dom: 'rtip',
        ordering: true,
        order: [[1, 'asc']], // Order by Min Income
        drawCallback: function() { stopSpinnerSafely(); },
        columns: [
            { data: 'tier_name', className: 'fw-bold' },
            { 
                data: 'min_income', 
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            { 
                data: 'max_income', 
                render: function(data) {
                    return data ? '₱ ' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2}) : '<span class="badge bg-secondary">And Above</span>';
                }
            },
            { 
                data: 'base_tax', 
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            { 
                data: 'excess_rate', className: 'text-center fw-bold text-danger',
                render: function(data) { return data + '%'; }
            },
            {
                data: 'id', orderable: false, className: 'text-center',
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="openModal(${data})"><i class="fas fa-pen"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSlab(${data})"><i class="fas fa-trash"></i></button>
                    `;
                }
            }
        ]
    });

    // FORM SUBMIT
    $('#taxForm').on('submit', function(e) {
        e.preventDefault();
        let action = $('#tax_id').val() ? 'update' : 'create';
        let formData = $(this).serialize() + '&action=' + action;

        $.post('api/tax_settings_action.php', formData, function(res) {
            if(res.status === 'success') {
                $('#taxModal').modal('hide');
                Swal.fire('Success', res.message, 'success');
                window.refreshPageContent();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    updateLastSyncTime();
});
</script>