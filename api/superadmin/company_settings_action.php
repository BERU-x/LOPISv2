<?php
// api/superadmin/company_settings_action.php
// Handles Core Company Information (Super Admin Only)
header('Content-Type: application/json');
session_start();

// --- 1. AUTHENTICATION (Super Admin Only) ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DEPENDENCIES ---
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

$action = $_REQUEST['action'] ?? '';

try {

    // =========================================================================
    // ACTION 1: GET DETAILS
    // =========================================================================
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_company_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If empty (first run), return defaults
        if (!$data) {
            $data = [
                'company_name' => 'LOPISv2 System', 
                'logo_path' => 'default_logo.png',
                'company_address' => '',
                'contact_number' => '',
                'email_address' => '',
                'website' => ''
            ]; 
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // =========================================================================
    // ACTION 2: UPDATE SETTINGS
    // =========================================================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $name    = trim($_POST['company_name']);
        $address = trim($_POST['company_address']);
        $contact = trim($_POST['contact_number']);
        $email   = trim($_POST['email_address']);
        $website = trim($_POST['website']);
        
        $logo_sql_part = "";
        $params = [$name, $address, $contact, $email, $website];

        // --- HANDLE LOGO UPLOAD ---
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            
            $fileTmpPath = $_FILES['company_logo']['tmp_name'];
            $fileName = $_FILES['company_logo']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = array('jpg', 'jpeg', 'png', 'ico');
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'logo_' . time() . '.' . $fileExtension;
                $uploadFileDir = __DIR__ . '/../../assets/images/';
                
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $dest_path = $uploadFileDir . $newFileName;

                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Success: Delete old logo if it exists and isn't default
                    $oldLogoStmt = $pdo->query("SELECT logo_path FROM tbl_company_settings WHERE id = 1");
                    $oldLogo = $oldLogoStmt->fetchColumn();
                    
                    if ($oldLogo && $oldLogo !== 'default_logo.png' && file_exists($uploadFileDir . $oldLogo)) {
                        unlink($uploadFileDir . $oldLogo);
                    }

                    $logo_sql_part = ", logo_path = ?";
                    $params[] = $newFileName;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, and ICO are allowed.']);
                exit;
            }
        }

        // --- EXECUTE UPDATE ---
        $params[] = 1; // WHERE id = 1

        $sql = "UPDATE tbl_company_settings 
                SET company_name=?, company_address=?, contact_number=?, email_address=?, website=? $logo_sql_part 
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            // Log Audit
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_COMPANY', "Updated core company contact and profile details.");

            echo json_encode(['status' => 'success', 'message' => 'Company details updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>