<script>
var holidayTable;

$(document).ready(function() {

    // 1. INITIALIZE DATATABLE
    holidayTable = $('#holidayTable').DataTable({
        ajax: {
            url: 'api/holiday_action.php?action=fetch',
            dataSrc: 'data'
        },
        columns: [
            { 
                data: 'formatted_date',
                className: "fw-bold text-gray-700"
            },
            { data: 'holiday_name' },
            { 
                data: 'holiday_type',
                render: function(data) {
                    return `<span class="badge bg-secondary">${data}</span>`;
                }
            },
            { 
                data: 'payroll_multiplier',
                className: "text-center fw-bold",
                render: function(data) { return data + 'x'; }
            },
            {
                data: null,
                className: "text-center",
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="editHoliday(${row.id})" title="Edit">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="deleteHoliday(${row.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[ 0, "desc" ]],
        language: { emptyTable: "No holidays configured." }
    });

    // 2. HANDLE FORM SUBMIT (Create/Update)
    $('#holidayForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'api/holiday_action.php?action=save',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#holidayModal').modal('hide');
                    Swal.fire('Success', res.message, 'success');
                    holidayTable.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server error.', 'error');
            }
        });
    });
});

// --- HELPER FUNCTIONS ---

// Open Modal for New Entry
function openModal() {
    $('#holidayForm')[0].reset();      // Clear form
    $('#holiday_id').val('');          // Clear hidden ID
    $('#modalTitle').text('Add New Holiday');
    $('#payroll_multiplier').val('1.00'); // Default
    $('#holidayModal').modal('show');
}

// Open Modal for Edit
function editHoliday(id) {
    $.ajax({
        url: 'api/holiday_action.php?action=get_one',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            if(data) {
                $('#holiday_id').val(data.id);
                $('#holiday_date').val(data.holiday_date);
                $('#holiday_name').val(data.holiday_name);
                $('#holiday_type').val(data.holiday_type);
                $('#payroll_multiplier').val(data.payroll_multiplier);
                
                $('#modalTitle').text('Edit Holiday');
                $('#holidayModal').modal('show');
            }
        }
    });
}

// Delete Logic
function deleteHoliday(id) {
    Swal.fire({
        title: 'Delete Holiday?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/holiday_action.php?action=delete',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        holidayTable.ajax.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }
            });
        }
    });
}

// Auto-update Multiplier logic
function updateMultiplier() {
    var type = document.getElementById("holiday_type").value;
    var rateInput = document.getElementById("payroll_multiplier");
    
    // Only auto-update if the user is changing the type. 
    // (Note: In Edit mode, we populate the fields first, so this won't trigger unless user changes the dropdown)
    if(type === "Regular") {
        rateInput.value = "1.00"; 
    } else if (type === "Special Non-Working") {
        rateInput.value = "0.30";
    } else if (type === "Special Working") {
        rateInput.value = "1.30"; // Usually 1.3 for Special Working, adjusted from your original code if needed
    } else {
        rateInput.value = "1.00";
    }
}
</script>