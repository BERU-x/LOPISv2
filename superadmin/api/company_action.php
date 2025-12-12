<?php
// api/company_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {

    // 1. GET DETAILS
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_company_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If empty (first run), return defaults
        if (!$data) {
            $data = ['company_name' => '', 'logo_path' => 'default_logo.png']; 
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // 2. UPDATE SETTINGS
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $name = $_POST['company_name'];
        $address = $_POST['company_address'];
        $contact = $_POST['contact_number'];
        $email = $_POST['email_address'];
        $website = $_POST['website'];
        
        // --- HANDLE LOGO UPLOAD ---
        $logo_sql_part = "";
        $params = [$name, $address, $contact, $email, $website];

        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            
            $fileTmpPath = $_FILES['company_logo']['tmp_name'];
            $fileName = $_FILES['company_logo']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            // Allowed extensions
            $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'ico');
            
            if (in_array($fileExtension, $allowedfileExtensions)) {
                // Generate unique name: logo_timestamp.ext
                $newFileName = 'logo_' . time() . '.' . $fileExtension;
                
                // Directory: assets/images/ (Adjust relative path if needed)
                $uploadFileDir = __DIR__ . '/../../assets/images/';
                
                // Create dir if not exists
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $dest_path = $uploadFileDir . $newFileName;

                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Success: Add to SQL update
                    $logo_sql_part = ", logo_path = ?";
                    $params[] = $newFileName;
                }
            }
        }

        // --- EXECUTE UPDATE ---
        $params[] = 1; // WHERE id = 1

        $sql = "UPDATE tbl_company_settings 
                SET company_name=?, company_address=?, contact_number=?, email_address=?, website=? $logo_sql_part 
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            echo json_encode(['status' => 'success', 'message' => 'Company details updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>