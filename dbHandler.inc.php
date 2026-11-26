<?php

$host = '127.0.0.1';
$dbname = 'glowupveritabanı'; 
$db_user = 'root';     
$db_pass = '';         

$conn = mysqli_connect($host, $db_user, $db_pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}