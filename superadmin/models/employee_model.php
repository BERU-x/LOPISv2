<?php
// models/employee_model.php

/**
 * Fetches all employee records for the main table view.
 */
function get_all_employees($pdo) {
    if (!$pdo) {
        return [];
    }
    
    // Select essential columns for the table view, ordered by employee_id ASC
    $sql = "SELECT id, employee_id, firstname, lastname, position, department, salary, employment_status, photo 
            FROM tbl_employees 
            ORDER BY employee_id ASC";
    
    try {
        $stmt = $pdo->query($sql);
        // Fetch results as associative arrays
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("PDO Error fetching all employees: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches a single employee record by primary ID (id) for the edit form.
 */
function getEmployeeById($pdo, $id) {
    if (!$pdo || (int)$id <= 0) {
        return false;
    }

    // Select ALL columns needed to populate the edit form
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

/**
 * Inserts a new employee record.
 */
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
            ':photo' => $photo 
        ]);
    } catch (PDOException $e) {
        // Log the error for unique constraint violations (e.g., duplicate employee_id)
        error_log("PDO Error creating employee: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates an existing employee record.
 */
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
        error_log("PDO Error updating employee: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches employee IDs and names for use in dropdowns (e.g., User creation).
 */
function get_employee_ids_for_dropdown($pdo) {
    if (!$pdo) {
        return [];
    }
    
    $sql = "SELECT 
                e.employee_id, 
                e.firstname, 
                e.lastname 
            FROM tbl_employees e
            LEFT JOIN tbl_users u ON e.employee_id = u.employee_id
            WHERE u.employee_id IS NULL 
            ORDER BY e.employee_id ASC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("PDO Error fetching dropdown employees: " . $e->getMessage());
        return [];
    }
}
?>