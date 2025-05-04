<?php
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

session_start();
require_once '../db_connection.php';

$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'No input received']));
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Malformed JSON']));
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Session expired', 401);
    }

    $question_ids = isset($data['question_ids']) ? $data['question_ids'] : [];
    $group_num = isset($data['question_ids']) ? (int)$data['group_num'] : 0;

    if (empty($question_ids)) {
        throw new Exception('No questions selected', 400);
    }

    if ($group_num < 0) {
        throw new Exception('Group number cannot be negative', 400);
    }

    $professor_id = $_SESSION['user_id'];
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $types = str_repeat('i', count($question_ids));

    // Verify ownership and same subject
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT subject_ID) AS subject_count 
        FROM question 
        WHERE question_ID IN ($placeholders) AND professor_ID = ?
    ");
    $stmt->bind_param($types . 'i', ...array_merge($question_ids, [$professor_id]));
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result['subject_count'] != 1) {
        throw new Exception('Questions must be from the same subject', 400);
    }

    // Update group numbers
    $stmt = $conn->prepare("
        UPDATE question 
        SET group_num = ? 
        WHERE question_ID IN ($placeholders)
    ");
    $stmt->bind_param('i' . $types, $group_num, ...$question_ids);
    
    if (!$stmt->execute()) {
        throw new Exception('Database update failed', 500);
    }
    
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Group updated successfully',
        'new_group' => $group_num,
        'affected_questions' => count($question_ids)
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}

$conn->close();
?>