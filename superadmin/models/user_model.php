<?php
// models/user_model.php

/**
 * Fetches all system users from tbl_users.
 */
function get_all_users($pdo) {
    if (!$pdo) {
        return [];
    }
    
    $sql = "SELECT id, employee_id, email, usertype, status, created_at FROM tbl_users ORDER BY usertype ASC, email ASC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("PDO Error fetching all users: " . $e->getMessage());
        return [];
    }
}

/**
 * Inserts a new user record into tbl_users.
 * NOTE: It is assumed that $data['username'] holds the Employee ID string (VARCHAR(3)).
 */
function create_new_user($pdo, $data) {
    
    $sql_insert = "INSERT INTO tbl_users (
        employee_id, email, password, usertype, status, created_at
    ) VALUES (
        :employee_id, :email, :password, :usertype, :status, NOW()
    )";
    
    try {
        $stmt = $pdo->prepare($sql_insert);
        return $stmt->execute([
            // Input 'username' from controller is mapped to the 'employee_id' column
            ':employee_id' => $data['username'], 
            ':email'       => $data['email'],
            ':password'    => $data['password'], // Already hashed
            ':usertype'    => $data['role'],     // Maps controller 'role' to 'usertype' column
            ':status'      => $data['status'],
        ]);
    } catch (PDOException $e) {
        error_log("PDO Error creating user: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches a single user record by primary ID (for editing).
 */
function getUserById($pdo, $id) {
    if (!$pdo || (int)$id <= 0) {
        return false;
    }
    
    $sql = "SELECT * FROM tbl_users WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("PDO Error fetching single user: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates a user's settings (employee_id, email, usertype, status, and optionally password).
 */
function updateUserSettings($pdo, $id, $data) {
    // START FIX: Include employee_id in the update query
    $sql_update = "UPDATE tbl_users SET
        employee_id = :employee_id,
        email = :email,
        usertype = :usertype,
        status = :status";
        
    $params = [
        ':employee_id' => $data['employee_id'], // Requires employee_id to be passed in $data
        ':email'    => $data['email'],
        ':usertype' => $data['usertype'],
        ':status'   => $data['status'],
        ':id'       => $id
    ];
    
    // Add password update if provided
    if (!empty($data['password'])) {
        $sql_update .= ", password = :password";
        $params[':password'] = $data['password'];
    }
    
    $sql_update .= " WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql_update);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("PDO Error updating user: " . $e->getMessage());
        return false;
    }
}

/**
 * Toggles a user's status (Active/Inactive).
 */
function toggleUserStatus($pdo, $id, $new_status) {
    $sql = "UPDATE tbl_users SET status = :status WHERE id = :id";
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $new_status,
            ':id' => $id
        ]);
    } catch (PDOException $e) {
        error_log("PDO Error toggling user status: " . $e->getMessage());
        return false;
    }
}
?>