<script>
// GLOBAL VARS
var earningsTable, deductionsTable;
let spinnerStartTime = 0;

// HELPER: Sync Logic
function updateLastSyncTime() {
    const now = new Date();
    $('#last-updated-time').text(now.toLocaleTimeString());
}
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    setTimeout(() => { icon.removeClass('fa-spin text-teal'); updateLastSyncTime(); }, 500);
}
window.refreshPageContent = function() {
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    earningsTable.ajax.reload(null, false);
    deductionsTable.ajax.reload(null, false);
};

// MODAL LOGIC
function openModal(type, id = null) {
    $('#componentForm')[0].reset();
    $('#comp_id').val('');
    $('#comp_type').val(type);
    
    // UI Updates
    let typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
    $('#modalTitle').text(id ? 'Edit ' + typeLabel : 'Add New ' + typeLabel);

    if(id) {
        Swal.fire({ title: 'Loading...', didOpen: () => Swal.showLoading() });
        $.ajax({
            url: 'api/pay_components_action.php',
            type: 'POST',
            data: { action: 'get_details', id: id },
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    let d = res.details;
                    $('#comp_id').val(d.id);
                    $('#name').val(d.name);
                    $('#is_taxable').val(d.is_taxable);
                    $('#is_recurring').val(d.is_recurring);
                    $('#componentModal').modal('show');
                }
            }
        });
    } else {
        $('#componentModal').modal('show');
    }
}

function deleteComponent(id) {
    Swal.fire({
        title: 'Delete Component?', text: "This will remove it from future calculations.",
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!'
    }).then((res) => {
        if(res.isConfirmed) {
            $.post('api/pay_components_action.php', { action: 'delete', id: id }, function(data) {
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
    // Shared Column Config
    const columnsConfig = [
        { data: 'name', className: 'fw-bold' },
        { 
            data: 'is_taxable', className: 'text-center',
            render: function(data) {
                return data == 1 
                    ? '<span class="badge bg-soft-primary text-primary">Taxable</span>' 
                    : '<span class="badge bg-soft-secondary text-secondary">Non-Taxable</span>';
            }
        },
        { 
            data: 'is_recurring', className: 'text-center',
            render: function(data) {
                return data == 1 
                    ? '<i class="fas fa-redo text-success" title="Recurring"></i>' 
                    : '<i class="fas fa-clock text-warning" title="One-Time"></i>';
            }
        },
        {
            data: 'id', orderable: false, className: 'text-center',
            render: function(data, type, row) {
                return `
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openModal('${row.type}', ${data})"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteComponent(${data})"><i class="fas fa-trash"></i></button>
                `;
            }
        }
    ];

    // Initialize Earnings Table
    earningsTable = $('#earningsTable').DataTable({
        ajax: { url: 'api/pay_components_action.php', type: 'POST', data: { action: 'fetch', type: 'earning' } },
        columns: columnsConfig,
        dom: 'rtip',
        drawCallback: function() { stopSpinnerSafely(); }
    });

    // Initialize Deductions Table
    deductionsTable = $('#deductionsTable').DataTable({
        ajax: { url: 'api/pay_components_action.php', type: 'POST', data: { action: 'fetch', type: 'deduction' } },
        columns: columnsConfig,
        dom: 'rtip'
    });

    // Form Submit
    $('#componentForm').on('submit', function(e) {
        e.preventDefault();
        let action = $('#comp_id').val() ? 'update' : 'create';
        let formData = $(this).serialize() + '&action=' + action;

        $.post('api/pay_components_action.php', formData, function(res) {
            if(res.status === 'success') {
                $('#componentModal').modal('hide');
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