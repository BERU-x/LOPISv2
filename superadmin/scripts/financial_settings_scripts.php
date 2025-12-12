<script>
$(document).ready(function() {

    // 1. FETCH DATA
    function loadDetails() {
        $.post('../api/financial_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                $('#currency_code').val(d.currency_code);
                $('#currency_symbol').val(d.currency_symbol);
                $('#fiscal_year_start_month').val(d.fiscal_year_start_month);
                $('#fiscal_year_start_day').val(d.fiscal_year_start_day);

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Updated: ' + new Date(d.updated_at).toLocaleDateString());
                }
            }
        }, 'json');
    }
    loadDetails();

    // 2. FORM SUBMISSION
    $('#financialForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'api/financial_action.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadDetails();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Configuration');
            }
        });
    });

    // Force Uppercase for Currency Code
    $('#currency_code').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });

});
</script>