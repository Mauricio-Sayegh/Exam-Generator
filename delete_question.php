<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db_connection.php';

// Get question ID from URL
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($question_id <= 0) {
    header("Location: my_subjects.php");
    exit();
}

// Verify the question belongs to the professor and get subject ID for redirect
$professor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT subject_ID FROM question 
    WHERE question_ID = ? AND professor_ID = ?
");
$stmt->bind_param("ii", $question_id, $professor_id);
$stmt->execute();
$result = $stmt->get_result();
$question = $result->fetch_assoc();
$stmt->close();

if (!$question) {
    header("Location: my_subjects.php");
    exit();
}

// Delete the question
$stmt = $conn->prepare("DELETE FROM question WHERE question_ID = ?");
$stmt->bind_param("i", $question_id);
$stmt->execute();
$stmt->close();

// Redirect back to subject questions
header("Location: subject_questions.php?subject_id=" . $question['subject_ID']);
exit();
?>