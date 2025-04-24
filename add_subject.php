<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$professor_id = $_SESSION['user_id'];
$subject_name = $_POST['subjectName'];
$university_id = $_POST['university'];
$total_mark = $_POST['totalMark'];
$duration = $_POST['duration'];

// Validate input
if (empty($subject_name) || empty($university_id) || empty($total_mark) || empty($duration)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Check if professor is associated with the university
$stmt = $conn->prepare("SELECT * FROM professor_university WHERE professor_ID = ? AND university_ID = ?");
$stmt->bind_param("ii", $professor_id, $university_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'You are not associated with this university']);
    exit();
}

// Insert new subject
$stmt = $conn->prepare("INSERT INTO subject (university_ID, professor_ID, subject_name, total_mark, duration) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisis", $university_id, $professor_id, $subject_name, $total_mark, $duration);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>