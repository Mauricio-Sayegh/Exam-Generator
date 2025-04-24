<?php
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$question_ids = isset($data['question_ids']) ? $data['question_ids'] : array();

if (empty($question_ids)) {
    echo json_encode(['success' => false, 'message' => 'No questions selected']);
    exit();
}

// Verify all questions belong to the professor
$professor_id = $_SESSION['user_id'];
$placeholders = implode(',', array_fill(0, count($question_ids), '?'));
$types = str_repeat('i', count($question_ids));

$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM question 
    WHERE question_ID IN ($placeholders) AND professor_ID = ?
");
$stmt->bind_param($types . 'i', ...array_merge($question_ids, [$professor_id]));
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['count'] != count($question_ids)) {
    echo json_encode(['success' => false, 'message' => 'Some questions do not belong to you']);
    exit();
}

// Delete questions
$stmt = $conn->prepare("DELETE FROM question WHERE question_ID IN ($placeholders)");
$stmt->bind_param($types, ...$question_ids);
$success = $stmt->execute();
$stmt->close();

$conn->close();

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Questions deleted successfully' : 'Error deleting questions'
]);
?>