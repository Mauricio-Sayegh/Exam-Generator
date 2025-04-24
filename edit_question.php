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

// Verify the question belongs to the professor
$professor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT q.*, s.subject_name, u.university_name_en 
    FROM question q
    JOIN subject s ON q.subject_ID = s.subject_ID
    JOIN university u ON s.university_ID = u.university_ID
    WHERE q.question_ID = ? AND q.professor_ID = ?
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

// Handle form submission for editing
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_question'])) {
    $question_text = isset($_POST['question_text']) ? trim($_POST['question_text']) : '';
    $mark = isset($_POST['mark']) ? (int)$_POST['mark'] : 1;
    $difficulty = isset($_POST['difficulty']) ? (int)$_POST['difficulty'] : 1;
    
    // Get choices from form
    $choices = [];
    $correct_answer = '';
    foreach ($_POST['choices'] as $key => $choice_text) {
        if (!empty(trim($choice_text))) {
            $choices[$key] = [
                'text' => $choice_text,
                'is_correct' => isset($_POST['correct_answer']) && $_POST['correct_answer'] == $key
            ];
            
            if ($choices[$key]['is_correct']) {
                $correct_answer = $key;
            }
        }
    }
    
    // Validate inputs
    if (empty($question_text)) {
        $error = 'Question text is required';
    } elseif (empty($choices)) {
        $error = 'At least one answer choice is required';
    } elseif (empty($correct_answer)) {
        $error = 'Please select the correct answer';
    } else {
        // Update question in database
        $stmt = $conn->prepare("
            UPDATE question SET
                question_text = ?,
                difficulty = ?,
                mark = ?,
                ans_A = ?, is_correct_A = ?,
                ans_B = ?, is_correct_B = ?,
                ans_C = ?, is_correct_C = ?,
                ans_D = ?, is_correct_D = ?,
                ans_E = ?, is_correct_E = ?
            WHERE question_ID = ? AND professor_ID = ?
        ");
        
        // Prepare answer data (up to 5 answers)
        $answers = array_slice($choices, 0, 5);
        while (count($answers) < 5) {
            $answers[] = ['text' => '', 'is_correct' => 0];
        }
        
        $stmt->bind_param(
            "siisisiisiisiii", 
            $question_text, $difficulty, $mark,
            $answers[0]['text'], $answers[0]['is_correct'],
            $answers[1]['text'], $answers[1]['is_correct'],
            $answers[2]['text'], $answers[2]['is_correct'],
            $answers[3]['text'], $answers[3]['is_correct'],
            $answers[4]['text'], $answers[4]['is_correct'],
            $question_id, $professor_id
        );
        
        if ($stmt->execute()) {
            $success = 'Question updated successfully!';
            // Refresh question data
            $stmt = $conn->prepare("SELECT * FROM question WHERE question_ID = ?");
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $question = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = 'Error updating question: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Get answer choices from question data
$choices = [];
$correct_answer = '';
for ($i = 0; $i < 5; $i++) {
    $letter = chr(65 + $i);
    $text_field = 'ans_' . $letter;
    $correct_field = 'is_correct_' . $letter;
    
    if (!empty($question[$text_field])) {
        $choices[$i] = [
            'text' => $question[$text_field],
            'is_correct' => $question[$correct_field]
        ];
        
        if ($question[$correct_field]) {
            $correct_answer = $i;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - <?php echo htmlspecialchars($question['subject_name']); ?></title>
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script src="make_a_question_js.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="make_a_question_style.css">
    <link rel="stylesheet" href="sidebar.css">
</head>
<body>
<div class="sidebar">
        <div class="sidebar-header">
            <span>Exam Generator</span>
            <button class="toggle-sidebar">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <div class="user-profile">
                <div class="avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($_SESSION['name']); ?></h3>
                    <p>Professor</p>
                </div>
            </div>
            
            <nav class="dashboard-nav">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="my_subjects.php" class="active">
                    <i class="fas fa-book"></i>
                    <span>My Subjects</span>
                </a>
                <a href="create_exam.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Create Exam</span>
                </a>
                <a href="view_exams.php">
                    <i class="fas fa-list"></i>
                    <span>View Exams</span>
                </a>
                <a href="profile.php">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile</span>
                </a>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-edit"></i> 
                Edit Question for <?php echo htmlspecialchars($question['subject_name']); ?>
                <small><?php echo htmlspecialchars($question['university_name_en']); ?></small>
            </h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="edit_question.php?id=<?php echo $question_id; ?>">
                <input type="hidden" name="update_question" value="1">
                
                <div class="question-creator">
                    <div class="form-group">
                        <label for="questionText">Question Text:</label>
                        <textarea id="questionText" name="question_text" placeholder="Enter your question here..." oninput="updatePreview()" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                        <div>
                            <button type="button" class="symbol-button" onclick="insertSymbolIntoField('questionText', '\\[ \\]')">
                                <i class="fas fa-plus"></i> Add Equation
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Multiple Choice Answers:</label>
                        <div id="choicesContainer">
                            <?php foreach ($choices as $key => $choice): ?>
                                <div class="choice-item">
                                    <input type="radio" name="correct_answer" value="<?php echo $key; ?>" <?php echo $key == $correct_answer ? 'checked' : ''; ?>>
                                    <input type="text" id="choices[<?php echo $key; ?>]" name="choices[<?php echo $key; ?>]" 
                                           placeholder="Enter choice..." value="<?php echo htmlspecialchars($choice['text']); ?>" 
                                           oninput="updatePreview()">
                                    <div style="margin-left: 10px;">
                                        <button type="button" class="symbol-button" onclick="insertSymbolIntoField('choices[<?php echo $key; ?>]', '\\\\[ \\\\]')">
                                            <i class="fas fa-plus"></i> Eq
                                        </button>
                                        <button type="button" class="symbol-button clear-btn" onclick="this.parentElement.parentElement.remove(); updatePreview()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="add-choice" onclick="addChoice()">
                            <i class="fas fa-plus"></i> Add Choice
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label for="mark">Mark:</label>
                        <input type="number" id="mark" name="mark" min="1" value="<?php echo $question['mark']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty">Difficulty:</label>
                        <select id="difficulty" name="difficulty" required>
                            <option value="1" <?php echo $question['difficulty'] == 1 ? 'selected' : ''; ?>>Easy</option>
                            <option value="2" <?php echo $question['difficulty'] == 2 ? 'selected' : ''; ?>>Medium</option>
                            <option value="3" <?php echo $question['difficulty'] == 3 ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="add-question">
                        <i class="fas fa-save"></i> Update Question
                    </button>
                    <a href="subject_questions.php?subject_id=<?php echo $question['subject_ID']; ?>" class="clear-btn" style="display:inline-block;margin-left:10px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
            
            <div class="question-preview">
                <h2>Question Preview</h2>
                <div id="livePreview">
                    <?php
                    // Initial preview with current question data
                    $previewHTML = '
                        <div class="question-text">
                            <strong>Preview:</strong> ' . nl2br(htmlspecialchars($question['question_text'])) . '
                        </div>
                        <div class="choices-list">';
                    
                    foreach ($choices as $index => $choice) {
                        if (!empty($choice['text'])) {
                            $previewHTML .= '
                                <span class="' . ($index == $correct_answer ? 'choice-correct' : '') . '">
                                    ' . chr(65 + $index) . '. ' . htmlspecialchars($choice['text']) . '
                                </span>';
                        }
                    }
                    
                    $previewHTML .= '</div>';
                    echo $previewHTML;
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>