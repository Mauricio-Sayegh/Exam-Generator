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
$group_num = isset($data['group_num']) ? (int)$data['group_num'] : 0;

if (empty($question_ids) || $group_num < 0) {
    echo json_encode(array('success' => false, 'message' => 'Invalid input'));
    exit();
}

// Verify all questions belong to the professor and same subject
$professor_id = $_SESSION['user_id'];
$placeholders = implode(',', array_fill(0, count($question_ids), '?'));
$types = str_repeat('i', count($question_ids));

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT subject_ID) as subject_count 
    FROM question 
    WHERE question_ID IN ($placeholders) AND professor_ID = ?
");
$params = array_merge($question_ids, array($professor_id));
call_user_func_array(array($stmt, 'bind_param'), array_merge(array($types . 'i'), $params));
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['subject_count'] != 1) {
    echo json_encode(array('success' => false, 'message' => 'Questions must be from the same subject'));
    exit();
}

// Update group number
$stmt = $conn->prepare("
    UPDATE question 
    SET group_num = ? 
    WHERE question_ID IN ($placeholders)
");
array_unshift($question_ids, $group_num);
call_user_func_array(array($stmt, 'bind_param'), array_merge(array('i' . $types), $question_ids));
$success = $stmt->execute();
$stmt->close();

$conn->close();

echo json_encode(array(
    'success' => $success,
    'message' => $success ? 'Group updated successfully' : 'Error updating group'
));
?>