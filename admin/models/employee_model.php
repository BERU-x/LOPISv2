<?php
// models/employee_model.php

// --- 1. READ ALL (Fetch from BOTH tables) ---
function get_all_employees($pdo) {
    if (!$pdo) return [];
    
    // Updated: Added e.schedule_type to selection
    $sql = "SELECT 
                e.id, e.employee_id, e.firstname, e.middlename, e.lastname, e.suffix, 
                e.position, e.department, e.employment_status, e.schedule_type, e.photo,
                c.daily_rate, c.monthly_rate 
            FROM tbl_employees e
            LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
            ORDER BY e.employee_id ASC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("Error fetching employees: " . $e->getMessage());
        return [];
    }
}

// --- 2. READ ONE (Fetch single employee for Editing) ---
function getEmployeeById($pdo, $id) {
    if (!$pdo || (int)$id <= 0) return false;

    // Join tables to fill the Edit Form completely
    // Note: e.* automatically includes schedule_type
    $sql = "SELECT 
                e.*, 
                c.daily_rate, c.monthly_rate, c.food_allowance, c.transpo_allowance
            FROM tbl_employees e
            LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
            WHERE e.id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("Error fetching single employee: " . $e->getMessage());
        return false;
    }
}

// --- 3. CREATE (Insert into BOTH tables) ---
function create_new_employee($pdo, $data, $photo) {
    try {
        // Start Transaction: Ensure both inserts work, or neither does
        $pdo->beginTransaction();

        // A. Insert into tbl_employees (Personal Info)
        // Updated: Added schedule_type
        $sql_emp = "INSERT INTO tbl_employees (
            employee_id, firstname, middlename, lastname, suffix, 
            address, birthdate, contact_info, gender, 
            position, department, employment_status, schedule_type,
            bank_name, account_type, account_number, photo, created_on
        ) VALUES (
            :employee_id, :firstname, :middlename, :lastname, :suffix, 
            :address, :birthdate, :contact_info, :gender, 
            :position, :department, :employment_status, :schedule_type,
            :bank_name, :account_type, :account_number, :photo, NOW()
        )";

        $stmt = $pdo->prepare($sql_emp);
        $stmt->execute([
            ':employee_id'       => $data['employee_id'],
            ':firstname'         => $data['firstname'],
            ':middlename'        => $data['middlename'] ?? '',
            ':lastname'          => $data['lastname'],
            ':suffix'            => $data['suffix'],
            ':address'           => $data['address'],
            ':birthdate'         => $data['birthdate'],
            ':contact_info'      => $data['contact_info'],
            ':gender'            => $data['gender'],
            ':position'          => $data['position'],
            ':department'        => $data['department'],
            ':employment_status' => $data['employment_status'],
            ':schedule_type'     => $data['schedule_type'] ?? 'Fixed', // Default to Fixed if missing
            ':bank_name'         => $data['bank_name'],
            ':account_type'      => $data['account_type'],
            ':account_number'    => $data['account_number'],
            ':photo'             => $photo 
        ]);

        // B. Insert into tbl_compensation (Financial Info)
        $sql_comp = "INSERT INTO tbl_compensation (
            employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance
        ) VALUES (
            :employee_id, :daily_rate, :monthly_rate, :food_allowance, :transpo_allowance
        )";

        $stmt = $pdo->prepare($sql_comp);
        $stmt->execute([
            ':employee_id'       => $data['employee_id'], // Links to employee
            ':daily_rate'        => $data['daily_rate'],
            ':monthly_rate'      => $data['monthly_rate'],
            ':food_allowance'    => $data['food_allowance'],
            ':transpo_allowance' => $data['transpo_allowance']
        ]);

        // Commit changes
        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack(); // Undo changes if something failed
        error_log("Error creating employee: " . $e->getMessage());
        return false;
    }
}

// --- 4. UPDATE (Update BOTH tables) ---
function updateEmployee($pdo, $id, $data) {
    try {
        $pdo->beginTransaction();

        // 1. Get current Employee ID (Old ID) before update
        // We need this in case the user CHANGED the employee_id in the form
        $stmt = $pdo->prepare("SELECT employee_id FROM tbl_employees WHERE id = ?");
        $stmt->execute([$id]);
        $old_record = $stmt->fetch();
        $old_emp_id = $old_record['employee_id'];

        // 2. Update tbl_employees
        // Updated: Added schedule_type
        $sql_emp = "UPDATE tbl_employees SET
            employee_id = :employee_id,
            firstname = :firstname,
            middlename = :middlename,
            lastname = :lastname,
            suffix = :suffix,
            address = :address,
            birthdate = :birthdate,
            contact_info = :contact_info,
            gender = :gender,
            position = :position,
            department = :department,
            employment_status = :employment_status,
            schedule_type = :schedule_type,
            bank_name = :bank_name,
            account_type = :account_type,
            account_number = :account_number,
            photo = :photo, 
            updated_on = NOW()
            WHERE id = :id";

        $stmt = $pdo->prepare($sql_emp);
        $stmt->execute([
            ':employee_id'       => $data['employee_id'],
            ':firstname'         => $data['firstname'],
            ':middlename'        => $data['middlename'] ?? '',
            ':lastname'          => $data['lastname'],
            ':suffix'            => $data['suffix'],
            ':address'           => $data['address'],
            ':birthdate'         => $data['birthdate'],
            ':contact_info'      => $data['contact_info'],
            ':gender'            => $data['gender'],
            ':position'          => $data['position'],
            ':department'        => $data['department'],
            ':employment_status' => $data['employment_status'],
            ':schedule_type'     => $data['schedule_type'],
            ':bank_name'         => $data['bank_name'],
            ':account_type'      => $data['account_type'],
            ':account_number'    => $data['account_number'],
            ':photo'             => $data['photo'] ?? null, 
            ':id'                => $id
        ]);

        // 3. Update tbl_compensation
        // We check if a record exists for the OLD employee_id
        $check_comp = $pdo->prepare("SELECT id FROM tbl_compensation WHERE employee_id = ?");
        $check_comp->execute([$old_emp_id]);
        
        if ($check_comp->rowCount() > 0) {
            // Update existing compensation record
            $sql_comp = "UPDATE tbl_compensation SET
                employee_id = :new_employee_id, -- Update ID in case it changed
                daily_rate = :daily_rate,
                monthly_rate = :monthly_rate,
                food_allowance = :food_allowance,
                transpo_allowance = :transpo_allowance
                WHERE employee_id = :old_employee_id";
                
            $stmt = $pdo->prepare($sql_comp);
            $stmt->execute([
                ':new_employee_id'   => $data['employee_id'],
                ':daily_rate'        => $data['daily_rate'],
                ':monthly_rate'      => $data['monthly_rate'],
                ':food_allowance'    => $data['food_allowance'],
                ':transpo_allowance' => $data['transpo_allowance'],
                ':old_employee_id'   => $old_emp_id
            ]);
        } else {
            // Fallback: If no compensation record existed, create one now
            $sql_comp_insert = "INSERT INTO tbl_compensation (
                employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance
            ) VALUES (
                :employee_id, :daily_rate, :monthly_rate, :food_allowance, :transpo_allowance
            )";
            $stmt = $pdo->prepare($sql_comp_insert);
            $stmt->execute([
                ':employee_id'       => $data['employee_id'],
                ':daily_rate'        => $data['daily_rate'],
                ':monthly_rate'      => $data['monthly_rate'],
                ':food_allowance'    => $data['food_allowance'],
                ':transpo_allowance' => $data['transpo_allowance']
            ]);
        }

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating employee: " . $e->getMessage());
        return false;
    }
}
?>