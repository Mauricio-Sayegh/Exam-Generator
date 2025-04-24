<?php
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array('success' => false, 'message' => 'Unauthorized'));
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$question_ids = isset($data['question_ids']) ? $data['question_ids'] : array();
$difficulty = isset($data['difficulty']) ? (int)$data['difficulty'] : 0;

if (empty($question_ids) || $difficulty < 1 || $difficulty > 3) {
    echo json_encode(array('success' => false, 'message' => 'Invalid input'));
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
$params = array_merge($question_ids, array($professor_id));
call_user_func_array(array($stmt, 'bind_param'), array_merge(array($types . 'i'), $params));
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['count'] != count($question_ids)) {
    echo json_encode(array('success' => false, 'message' => 'Some questions do not belong to you'));
    exit();
}

// Update difficulty
$stmt = $conn->prepare("
    UPDATE question 
    SET difficulty = ? 
    WHERE question_ID IN ($placeholders)
");
array_unshift($question_ids, $difficulty);
call_user_func_array(array($stmt, 'bind_param'), array_merge(array('i' . $types), $question_ids));
$success = $stmt->execute();
$stmt->close();

$conn->close();

echo json_encode(array(
    'success' => $success,
    'message' => $success ? 'Difficulty updated successfully' : 'Error updating difficulty'
));
?>