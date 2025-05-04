<?php
error_reporting(0); // Turn off all error reporting for production
ini_set('display_errors', 0);
session_start();
require_once '../db_connection.php';

// Validate inputs
$tab = isset($_GET['tab']) ? $_GET['tab'] : '';
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$difficulty = isset($_GET['difficulty']) ? (int)$_GET['difficulty'] : 0;

if (!$subject_id || !in_array($tab, ['groups', 'difficulty'])) {
    die('<div class="error">Invalid request</div>');
}

// Verify subject belongs to professor
$professor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT 1 FROM subject WHERE subject_ID = ? AND professor_ID = ?");
$stmt->bind_param("ii", $subject_id, $professor_id);
$stmt->execute();
if (!$stmt->get_result()->num_rows) {
    die('<div class="error">Unauthorized access</div>');
}

if ($tab === 'groups') {
    // Load questions grouped by group_num
    $query = "SELECT q.group_num, COUNT(*) as question_count,
              GROUP_CONCAT(q.question_ID ORDER BY q.question_ID SEPARATOR ',') as question_ids
              FROM question q
              WHERE q.subject_ID = ? AND q.group_num > 0
              GROUP BY q.group_num
              ORDER BY q.group_num DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($groups)) {
        echo '<div class="no-groups">No question groups found</div>';
        exit;
    }
    
    foreach ($groups as $group) {
        echo '<div class="group-section">';
        echo '<h3 class="group-header">Group '.$group['group_num'].' ('.$group['question_count'].' questions)</h3>';
        
        // Get questions for this group
        $question_ids = explode(',', $group['question_ids']);
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        
        $stmt = $conn->prepare("
            SELECT * FROM question 
            WHERE question_ID IN ($placeholders)
            ORDER BY question_ID
        ");
        $stmt->bind_param(str_repeat('i', count($question_ids)), ...$question_ids);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo '<div class="group-questions">';
        foreach ($questions as $question) {
            // Render your question HTML here
            echo renderQuestionHtml($question);
        }
        echo '</div></div>';
    }
} 
else if ($tab === 'difficulty') {
    // Load questions by difficulty level
    for ($d = 1; $d <= 3; $d++) {
        if ($difficulty > 0 && $d != $difficulty) continue;
        
        $query = "SELECT * FROM question 
                 WHERE subject_ID = ? AND difficulty = ?
                 ORDER BY group_num DESC, date DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $subject_id, $d);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (!empty($questions)) {
            echo '<div>';
            echo '<h3 class="difficulty-header">';
            echo $d == 1 ? 'Easy' : ($d == 2 ? 'Medium' : 'Hard');
            echo ' ('.count($questions).' questions)</h3>';
            
            echo '<div class="difficulty-questions">';
            foreach ($questions as $question) {
                echo renderQuestionHtml($question);
            }
            echo '</div></div>';
        }
    }
    
    if (empty($questions) && $difficulty > 0) {
        echo '<div class="no-questions">No questions found for selected difficulty</div>';
    }
}

function renderQuestionHtml($question) {
    ob_start(); ?>
    <div class="question-card" data-id="<?= $question['question_ID'] ?>">
        <div class="card-header">
            <input type="checkbox" class="question-checkbox" value="<?= $question['question_ID'] ?>">
            <span class="question-id">#<?= $question['question_ID'] ?></span>
            <span class="difficulty-badge difficulty-<?= $question['difficulty'] ?>">
                <?= $question['difficulty'] == 1 ? 'Easy' : ($question['difficulty'] == 2 ? 'Medium' : 'Hard') ?>
            </span>
            <?php if ($question['group_num'] > 0): ?>
                <span class="group-badge">Group <?= $question['group_num'] ?></span>
            <?php endif; ?>
        </div>
        <div class="card-preview">
            <?= htmlspecialchars(substr($question['question_text'], 0, 100)) ?>...
        </div>
        <div class="card-footer">
            <span class="mark"><?= $question['mark'] ?> pts</span>
            <div class="card-actions">
                <a href="edit_question.php?id=<?= $question['question_ID'] ?>" class="btn-action quick-edit">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="delete_question.php?id=<?= $question['question_ID'] ?>" class="btn-action quick-delete">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>