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
    die(json_encode(['success' => false, 'message' => 'Empty request']));
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid JSON data']));
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    $question_ids = isset($data['question_ids']) ? $data['question_ids'] : [];
    $difficulty = isset($data['difficulty']) ? (int)$data['difficulty'] : 0;

    if (empty($question_ids)) {
        throw new Exception('No questions selected', 400);
    }

    if ($difficulty < 1 || $difficulty > 3) {
        throw new Exception('Difficulty must be between 1 and 3', 400);
    }

    $professor_id = $_SESSION['user_id'];
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $types = str_repeat('i', count($question_ids));

    // Verify ownership
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS valid_count 
        FROM question 
        WHERE question_ID IN ($placeholders) AND professor_ID = ?
    ");
    $stmt->bind_param($types . 'i', ...array_merge($question_ids, [$professor_id]));
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result['valid_count'] != count($question_ids)) {
        throw new Exception('Permission denied for some questions', 403);
    }

    // Update difficulty
    $stmt = $conn->prepare("
        UPDATE question 
        SET difficulty = ? 
        WHERE question_ID IN ($placeholders)
    ");
    $stmt->bind_param('i' . $types, $difficulty, ...$question_ids);
    
    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $conn->error, 500);
    }
    
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Updated difficulty for ' . count($question_ids) . ' question(s)',
        'updated_to' => $difficulty
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>