<?php

/**
 * OvertimeRepository.php
 * Provides functions to interact with overtime data for DataTables SSP.
 */

class OvertimeRepository {
    private $pdo;

    // 🛑 UPDATED: Include tbl_attendance (ta) to access raw calculated OT
    private $sql_details = "
        FROM tbl_overtime ot
        LEFT JOIN tbl_employees e ON ot.employee_id = e.employee_id
        LEFT JOIN tbl_attendance ta ON ot.employee_id = ta.employee_id AND ot.ot_date = ta.date
    ";

    // 🛑 UPDATED: Added ta.overtime_hr to the selection list
    private $data_fields = "
        ot.id, 
        ot.employee_id, 
        CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) AS employee_name, 
        ot.ot_date,          /* Date of the overtime */
        ot.hours_requested,  /* Hours requested by employee */
        ot.hours_approved,   /* Final hours approved by Admin */
        ot.status,           /* e.g., Pending, Approved, Rejected */
        ot.reason,           /* Reason for OT */
        ot.created_at,
        ta.overtime_hr       /* 🛑 NEW: Raw calculated OT from the attendance log */
    ";
    
    // -------------------------------------------------------------------------
    // CONSTRUCTOR
    // -------------------------------------------------------------------------

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // -------------------------------------------------------------------------
    // PUBLIC METHODS (Logic remains the same, only the queries are updated via $sql_details)
    // -------------------------------------------------------------------------

    /**
     * Get the total number of overtime records without any filtering.
     * @return int
     */
    public function getTotalRecords(): int {
        // We still count records from the primary table (tbl_overtime)
        $sql = "SELECT COUNT(ot.id) " . $this->sql_details;
        $stmt = $this->pdo->query($sql);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get the total number of records after applying WHERE clauses.
     * @param string $where_sql The compiled WHERE clause (e.g., " WHERE ... ")
     * @param array $bindings The PDO bindings for the WHERE clause
     * @return int
     */
    public function getFilteredRecords(string $where_sql, array $bindings): int {
        $sql = "SELECT COUNT(ot.id) " . $this->sql_details . $where_sql;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings); 
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get the final data set with filtering, ordering, and limiting applied.
     * @param string $where_sql The compiled WHERE clause
     * @param string $order_sql The compiled ORDER BY clause
     * @param string $limit_sql The compiled LIMIT clause
     * @param array $bindings The PDO bindings for the WHERE clause
     * @return array
     */
    public function getPaginatedData(string $where_sql, string $order_sql, string $limit_sql, array $bindings): array {
        $sql = "SELECT " . $this->data_fields . " "
              . $this->sql_details 
              . $where_sql 
              . $order_sql 
              . $limit_sql;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}