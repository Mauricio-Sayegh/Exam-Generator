<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$professor_id = $_SESSION['user_id'];
$subject_id = $_POST['subjectId'];
$subject_name = $_POST['subjectName'];
$total_mark = $_POST['totalMark'];
$duration = $_POST['duration'];

// Validate input
if (empty($subject_id) || empty($subject_name) || empty($total_mark) || empty($duration)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Check if subject belongs to professor
$stmt = $conn->prepare("SELECT * FROM subject WHERE subject_ID = ? AND professor_ID = ?");
$stmt->bind_param("ii", $subject_id, $professor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Subject not found or not authorized']);
    exit();
}

// Update subject
$stmt = $conn->prepare("UPDATE subject SET subject_name = ?, total_mark = ?, duration = ? WHERE subject_ID = ?");
$stmt->bind_param("sisi", $subject_name, $total_mark, $duration, $subject_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>