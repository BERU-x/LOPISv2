<?php
// superadmin/company_details.php
$page_title = 'Company Settings - LOPISv2';
$current_page = 'company_details';

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Company Profile</h1>
    </div>

    <div class="row">
        
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-label">Company Logo</h6>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <img id="logoPreview" src="../assets/images/logo.png" 
                             class="img-fluid rounded border shadow-sm p-1" 
                             style="width: 180px; height: 180px; object-fit: contain; background: #f8f9fa;"
                             alt="Company Logo">
                    </div>
                    
                    <p class="small text-muted mb-3">Preferred Size: 500x500px (Square)</p>
                    
                    <button class="btn btn-teal btn-sm" onclick="$('#logoInput').click()">
                        <i class="fas fa-upload me-1"></i> Change Logo
                    </button>
                    <input type="file" id="logoInput" name="logo" class="d-none" accept="image/*">
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-label">General Information</h6>
                    <span id="last-updated-text" class="small text-muted"></span>
                </div>
                <div class="card-body">
                    
                    <form id="companyForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update">
                        
                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">Company Name</label>
                            <input class="form-control" id="company_name" name="company_name" type="text" placeholder="Enter company name" required>
                        </div>

                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1 fw-bold">Contact Number</label>
                                <input class="form-control" id="contact_number" name="contact_number" type="text" placeholder="+63 900 000 0000">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1 fw-bold">Email Address</label>
                                <input class="form-control" id="email_address" name="email_address" type="email" placeholder="info@company.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">Website URL</label>
                            <input class="form-control" id="website" name="website" type="text" placeholder="www.yourcompany.com">
                        </div>

                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">Office Address</label>
                            <textarea class="form-control" id="company_address" name="company_address" rows="3" placeholder="Full business address"></textarea>
                        </div>

                        <hr>
                        
                        <button class="btn btn-teal" type="submit" id="saveBtn">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </form>

                </div>
            </div>
        </div>

    </div>

</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/superadmin_company_settings.js"></script>