<?php
// Disable error display and ensure clean output
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_length()) ob_clean();

// Set headers first
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

session_start();
require_once '../db_connection.php';

// Get and validate input
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'No input data received']));
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]));
}

try {
    // Authentication check
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Session expired or invalid', 401);
    }

    $question_ids = isset($data['question_ids']) ? $data['question_ids'] : [];
    if (empty($question_ids)) {
        throw new Exception('No questions selected', 400);
    }

    // Security validation
    $professor_id = $_SESSION['user_id'];
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $types = str_repeat('i', count($question_ids));

    // Verify ownership
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count 
        FROM question 
        WHERE question_ID IN ($placeholders) AND professor_ID = ?
    ");
    $stmt->bind_param($types . 'i', ...array_merge($question_ids, [$professor_id]));
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result['count'] != count($question_ids)) {
        throw new Exception('Unauthorized access to one or more questions', 403);
    }

    // Perform deletion
    $stmt = $conn->prepare("DELETE FROM question WHERE question_ID IN ($placeholders)");
    $stmt->bind_param($types, ...$question_ids);
    
    if (!$stmt->execute()) {
        throw new Exception('Database deletion failed: ' . $conn->error, 500);
    }
    
    $stmt->close();

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Successfully deleted ' . count($question_ids) . ' question(s)',
        'deleted_count' => $stmt->affected_rows
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}

$conn->close();
?>