<?php
// Function to fetch all employee records
function get_all_employees($pdo) {
    if (!$pdo) {
        return [];
    }
    
    $sql = "SELECT id, employee_id, firstname, lastname, position, department, salary, employment_status, photo FROM tbl_employees ORDER BY employee_id ASC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("PDO Error fetching employees: " . $e->getMessage());
        return [];
    }
}

// Function to fetch a single employee record by ID
function getEmployeeById($pdo, $id) {
    if (!$pdo || (int)$id <= 0) {
        return false;
    }

    $sql = "SELECT * FROM tbl_employees WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("PDO Error fetching single employee: " . $e->getMessage());
        return false;
    }
}

// Function to insert a new employee record
function create_new_employee($pdo, $data, $photo) {
    $sql_insert = "INSERT INTO tbl_employees (
        employee_id, firstname, lastname, suffix, address, birthdate, contact_info, gender, 
        position, department, employment_status, salary, food, travel, 
        bank_name, account_type, account_number, photo, created_on
    ) VALUES (
        :employee_id, :firstname, :lastname, :suffix, :address, :birthdate, :contact_info, :gender, 
        :position, :department, :employment_status, :salary, :food, :travel, 
        :bank_name, :account_type, :account_number, :photo, NOW()
    )";
    
    try {
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([
            ':employee_id' => $data['employee_id'],
            ':firstname' => $data['firstname'],
            ':lastname' => $data['lastname'],
            ':suffix' => $data['suffix'],
            ':address' => $data['address'],
            ':birthdate' => $data['birthdate'],
            ':contact_info' => $data['contact_info'],
            ':gender' => $data['gender'],
            ':position' => $data['position'],
            ':department' => $data['department'],
            ':employment_status' => $data['employment_status'],
            ':salary' => $data['salary'],
            ':food' => $data['food'],
            ':travel' => $data['travel'],
            ':bank_name' => $data['bank_name'],
            ':account_type' => $data['account_type'],
            ':account_number' => $data['account_number'],
            ':photo' => $photo 
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("PDO Error creating employee: " . $e->getMessage());
        return false;
    }
}

// Function to update an existing employee record
function updateEmployee($pdo, $id, $data) {
    $sql_update = "UPDATE tbl_employees SET
        employee_id = :employee_id,
        firstname = :firstname,
        lastname = :lastname,
        suffix = :suffix,
        address = :address,
        birthdate = :birthdate,
        contact_info = :contact_info,
        gender = :gender,
        position = :position,
        department = :department,
        employment_status = :employment_status,
        salary = :salary,
        food = :food,
        travel = :travel,
        bank_name = :bank_name,
        account_type = :account_type,
        account_number = :account_number,
        photo = :photo, 
        updated_on = NOW()
        WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql_update);
        return $stmt->execute([
            ':employee_id' => $data['employee_id'],
            ':firstname' => $data['firstname'],
            ':lastname' => $data['lastname'],
            ':suffix' => $data['suffix'],
            ':address' => $data['address'],
            ':birthdate' => $data['birthdate'],
            ':contact_info' => $data['contact_info'],
            ':gender' => $data['gender'],
            ':position' => $data['position'],
            ':department' => $data['department'],
            ':employment_status' => $data['employment_status'],
            ':salary' => $data['salary'],
            ':food' => $data['food'],
            ':travel' => $data['travel'],
            ':bank_name' => $data['bank_name'],
            ':account_type' => $data['account_type'],
            ':account_number' => $data['account_number'],
            ':photo' => $data['photo'] ?? null, 
            ':id' => $id
        ]);
    } catch (PDOException $e) {
        // PRODUCTION FIX: Change back to logging the error
        error_log("PDO Error updating employee: " . $e->getMessage());
        return false;
    }
}
?>