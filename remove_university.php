<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$university_id = (int)$_GET['id'];

// Remove the university association
$stmt = $conn->prepare("DELETE FROM professor_university WHERE professor_ID = ? AND university_ID = ?");
$stmt->bind_param("ii", $professor_id, $university_id);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = "University removed successfully";
header("Location: profile.php");
exit();
?>