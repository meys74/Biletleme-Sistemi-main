<?php
// 1. DATABASE CONFIGURATION (VARIABLES)
$host     = 'localhost';
$dbname   = 'glowupveritabanı';
$username = 'root';
$password = '';

// 2. CONNECTION & ERROR HANDLING
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
