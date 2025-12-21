<?php
// db_connection.php

$host = 'localhost';
$db_name = 'lendellp_lopisv2';
$username = 'root'; // Replace with your database username
$password = '';     // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

define('BASE_URL', 'http://localhost/LOPISv2/');
?>