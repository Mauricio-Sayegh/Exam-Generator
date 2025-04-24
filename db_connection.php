<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); 
define('DB_PASS', '');
define('DB_NAME', 'exam_generator_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$conn->query("SET NAMES 'utf8mb4'");
$conn->query("SET CHARACTER SET utf8mb4");
?>