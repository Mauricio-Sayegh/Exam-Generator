<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$professor_id = $_SESSION['user_id'];
$subject_id = $_POST['subjectId'];

// Check if subject belongs to professor
$stmt = $conn->prepare("SELECT * FROM subject WHERE subject_ID = ? AND professor_ID = ?");
$stmt->bind_param("ii", $subject_id, $professor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Subject not found or not authorized']);
    exit();
}

// Delete subject (cascading deletes should handle related questions and exams)
$stmt = $conn->prepare("DELETE FROM subject WHERE subject_ID = ?");
$stmt->bind_param("i", $subject_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>