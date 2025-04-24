<?php
session_start();
require_once 'db_connection.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists
    $stmt = $conn->prepare("SELECT prof_ID FROM professor WHERE verification_token = ? AND is_verified = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Token is valid, verify the professor
        $professor = $result->fetch_assoc();
        $professor_id = $professor['prof_ID'];
        
        $update_stmt = $conn->prepare("UPDATE professor SET is_verified = 1, verification_token = NULL WHERE prof_ID = ?");
        $update_stmt->bind_param("i", $professor_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Email verified successfully! You can now login.";
        } else {
            $_SESSION['error'] = "Verification failed. Please try again or contact support.";
        }
        
        $update_stmt->close();
    } else {
        $_SESSION['error'] = "Invalid or expired verification link.";
    }
    
    $stmt->close();
}

header("Location: index.php");
exit();
?>