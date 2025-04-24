<?php
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo '<div class="error">Unauthorized</div>';
    exit();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : '';
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($subject_id <= 0) {
    echo '<div class="error">Invalid subject</div>';
    exit();
}

$professor_id = $_SESSION['user_id'];

// Verify the subject belongs to the professor
$stmt = $conn->prepare("
    SELECT 1 FROM subject 
    WHERE subject_ID = ? AND professor_ID = ?
");
$stmt->bind_param("ii", $subject_id, $professor_id);
$stmt->execute();
$subject_exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$subject_exists) {
    echo '<div class="error">Subject not found</div>';
    exit();
}

switch ($tab) {
    case 'groups':
        // Load questions grouped by group_num
        $stmt = $conn->prepare("
            SELECT q.group_num, COUNT(*) as question_count,
                   GROUP_CONCAT(q.question_ID ORDER BY q.question_ID SEPARATOR ',') as question_ids
            FROM question q
            WHERE q.subject_ID = ? AND q.group_num > 0
            GROUP BY q.group_num
            ORDER BY q.group_num DESC
        ");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($groups)) {
            echo '<div class="no-groups">No question groups found</div>';
            exit();
        }
        
        echo '<div class="groups-container">';
        foreach ($groups as $group) {
            echo '<div class="group-section">';
            echo '<h3 class="group-header">';
            echo 'Group ' . $group['group_num'] . ' (' . $group['question_count'] . ' questions)';
            echo '</h3>';
            
            // Get questions for this group
            $question_ids = explode(',', $group['question_ids']);
            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
            $types = str_repeat('i', count($question_ids));
            
            $stmt = $conn->prepare("
                SELECT q.question_ID, q.question_text, q.difficulty, q.mark, q.date
                FROM question q
                WHERE q.question_ID IN ($placeholders)
                ORDER BY q.question_ID
            ");
            call_user_func_array(array($stmt, 'bind_param'), array_merge(array($types), $question_ids));
            $stmt->execute();
            $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            echo '<div class="group-questions">';
            foreach ($questions as $question) {
                echo '<div class="question-card compact">';
                echo '<div class="card-header">';
                echo '<span class="question-id">#' . $question['question_ID'] . '</span>';
                echo '<span class="difficulty-badge difficulty-' . $question['difficulty'] . '">';
                echo $question['difficulty'] == 1 ? 'Easy' : ($question['difficulty'] == 2 ? 'Medium' : 'Hard');
                echo '</span>';
                echo '</div>';
                echo '<div class="card-preview">' . substr(strip_tags($question['question_text']), 0, 100) . '...</div>';
                echo '<div class="card-footer">';
                echo '<span class="mark">' . $question['mark'] . ' pts</span>';
                echo '<span class="date">' . date('M d, Y', strtotime($question['date'])) . '</span>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        break;
        
    case 'difficulty':
        // Load questions grouped by difficulty
        echo '<div class="difficulty-container">';
        
        for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
            $stmt = $conn->prepare("
                SELECT q.question_ID, q.question_text, q.mark, q.date, q.group_num
                FROM question q
                WHERE q.subject_ID = ? AND q.difficulty = ?
                ORDER BY q.group_num DESC, q.date DESC
            ");
            $stmt->bind_param("ii", $subject_id, $difficulty);
            $stmt->execute();
            $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (empty($questions)) continue;
            
            echo '<div class="difficulty-section">';
            echo '<h3 class="difficulty-header difficulty-' . $difficulty . '">';
            echo $difficulty == 1 ? 'Easy' : ($difficulty == 2 ? 'Medium' : 'Hard') . ' (' . count($questions) . ' questions)';
            echo '</h3>';
            
            echo '<div class="difficulty-questions">';
            foreach ($questions as $question) {
                echo '<div class="question-card compact">';
                echo '<div class="card-header">';
                echo '<span class="question-id">#' . $question['question_ID'] . '</span>';
                if ($question['group_num'] > 0) {
                    echo '<span class="group-badge">Group ' . $question['group_num'] . '</span>';
                }
                echo '</div>';
                echo '<div class="card-preview">' . substr(strip_tags($question['question_text']), 0, 100) . '...</div>';
                echo '<div class="card-footer">';
                echo '<span class="mark">' . $question['mark'] . ' pts</span>';
                echo '<span class="date">' . date('M d, Y', strtotime($question['date'])) . '</span>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        break;
        
    default:
        echo '<div class="error">Invalid tab</div>';
}

$conn->close();
?>