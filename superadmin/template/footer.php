</div>
        </div>
    <footer class="sticky-footer bg-white">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Copyright &copy; LOPISv2 <?php echo date('Y'); ?></span>
            </div>
        </div>
    </footer>
    </div>
</div>
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <a class="btn btn-primary" href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap5.min.js"></script> 

<script>
    $(document).ready(function() {
        // Sidebar Toggle Logic
        $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
            $("body").toggleClass("sidebar-toggled");
            $(".sidebar").toggleClass("toggled");
            if ($(".sidebar").hasClass("toggled")) {
                $('.sidebar .collapse').collapse('hide');
            };
        });
    });
</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const loader = document.getElementById("page-loader");
        if (loader) {
            const progressBar = loader.querySelector(".progress-bar");
            const percentageText = loader.querySelector("#loader-percentage");
            let progress = 0;
            const minLoaderTime = 1500; 
            const startTime = new Date().getTime();

            function updateProgress() {
                progress += 1;
                progressBar.style.width = progress + "%";
                percentageText.textContent = progress + "%";
                if (progress < 100) {
                    let delay = (progress > 70) ? 50 : 10;
                    setTimeout(updateProgress, delay);
                } else {
                    const elapsedTime = new Date().getTime() - startTime;
                    if (elapsedTime < minLoaderTime) {
                        setTimeout(finishLoading, minLoaderTime - elapsedTime);
                    } else {
                        finishLoading();
                    }
                }
            }
            function finishLoading() {
                loader.classList.add("hidden");
                setTimeout(() => { loader.remove(); }, 500);
            }
            setTimeout(updateProgress, 10);
        }
    });
</script>

<script>
$(document).ready(function() {
    
    // --- 1. INITIALIZE DATATABLES ---
    // This runs AFTER DataTables and jQuery have loaded.
    var usersTable = $('#usersTable').DataTable({
        "dom": 'rtip', 
        "pageLength": 10,
        "language": {
            "info": "Showing _START_ to _END_ of _TOTAL_ users",
            "infoEmpty": "Showing 0 to 0 of 0 users",
            "infoFiltered": "(filtered from _MAX_ total users)"
        },
        "columnDefs": [
            { "orderable": false, "targets": 4 } 
        ],
        "order": [
            [2, "asc"], // Sort by Role (Column 2) Ascending
            [0, "asc"]  // Then sort by Employee ID (Column 0) Ascending
        ]
    });

    // --- 2. CUSTOM SEARCH HANDLER ---
    $('#searchInput').on('keyup', function() {
        usersTable.search(this.value).draw();
    });

    // Adjust table width on modal/sidebar interactions
    $('#addUserModal').on('shown.bs.modal', function () {
        usersTable.columns.adjust();
    });
});
</script>

</body>
</html>