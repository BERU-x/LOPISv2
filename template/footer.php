<?php
// template/footer.php
// Renders the closing HTML elements, modals, footer, and includes global scripts.
// Assumes this file is placed in a common template directory and is included relative to a page like /app/pages/audit_logs.php.
?>

</div>
    </div>
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title font-weight-bold text-dark" id="exampleModalLabel">
                    <i class="fas fa-sign-out-alt me-2"></i>Ready to Leave?
                </h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-gray-600">
                Select "Logout" below if you are ready to end your current session.
            </div>
            <div class="modal-footer border-top-0">
                <button class="btn btn-light text-secondary fw-bold" type="button" data-bs-dismiss="modal">
                    Cancel
                </button>
                <a class="btn btn-teal fw-bold shadow-sm" href="../logout.php">
                    Logout
                </a>
            </div>
        </div>
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

<script src="../../assets/js/jquery-3.7.1.min.js"></script> 
<script src="../../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script src="../../assets/js/dataTables.min.js"></script>
<script src="../../assets/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script> 

<script src="../../assets/js/main.js"></script> 

</body>
</html>