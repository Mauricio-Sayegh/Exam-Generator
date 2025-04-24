<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name_en = isset($_POST['new_university_name']) ? trim($_POST['new_university_name']) : '';
    $name_ar = isset($_POST['new_university_name_ar']) ? trim($_POST['new_university_name_ar']) : '';
    $professor_id = $_SESSION['user_id'];
    
    if (!empty($name_en) && !empty($name_ar)) {
        // Add new university
        $stmt = $conn->prepare("INSERT INTO university (university_name_en, university_name_ar) VALUES (?, ?)");
        $stmt->bind_param("ss", $name_en, $name_ar);
        
        if ($stmt->execute()) {
            $university_id = $conn->insert_id;
            
            // Associate professor with new university
            $stmt2 = $conn->prepare("INSERT INTO professor_university (professor_ID, university_ID) VALUES (?, ?)");
            $stmt2->bind_param("ii", $professor_id, $university_id);
            $stmt2->execute();
            $stmt2->close();
            
            $_SESSION['success'] = "University added successfully!";
        } else {
            $_SESSION['error'] = "Error adding university: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Both English and Arabic names are required";
    }
}

header("Location: profile.php");
exit();
?>