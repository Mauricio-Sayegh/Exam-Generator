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
    SELECT q.*, s.subject_name, s.subject_ID, u.university_name_en 
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
    $correct_answer = isset($_POST['correct_answer']) ? $_POST['correct_answer'] : '';
    foreach ($_POST['choices'] as $key => $choice_text) {
        if (!empty(trim($choice_text))) {
            $choices[$key] = [
                'text' => $choice_text,
                'is_correct' => (string)$key === (string)$correct_answer
            ];
        }
    }
    
    // Validate inputs
    if (empty($question_text)) {
        $error = 'Question text is required';
    } elseif (empty($choices)) {
        $error = 'At least one answer choice is required';
    } elseif (!isset($_POST['correct_answer'])) {
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
            $answers[] = [
                'text' => '',
                'is_correct' => 0  // Explicitly set to integer 0, not empty string
            ];
        }
        
        // Convert boolean/string values to integers for database
        foreach ($answers as &$answer) {
            $answer['is_correct'] = $answer['is_correct'] ? 1 : 0;
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
            // Refresh question data with all required fields
            $stmt2 = $conn->prepare("
                SELECT q.*, s.subject_name, s.subject_ID, u.university_name_en 
                FROM question q
                JOIN subject s ON q.subject_ID = s.subject_ID
                JOIN university u ON s.university_ID = u.university_ID
                WHERE q.question_ID = ?
            ");
            $stmt2->bind_param("i", $question_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $question = $result->fetch_assoc();
            $stmt2->close();
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
    <link rel = "icon" class="fas fa-square-root-alt"> </icon>
</head>
<body>
<div class="sidebar">
        <div class="sidebar-header">
            <span style="margin-left:8px;">Math Symbols</span>
            <button class="toggle-sidebar" onclick="toggleSidebar()">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <div class="category" onclick="toggleCategory(this)">
                <div class="category-header">
                    <i class="fas fa-calculator"></i>
                    <span class="category-name">Basic Operations</span>
                </div>
                <div class="symbol-panel">
                    <button class="symbol-button" onclick="insertSymbol('+')">+</button>
                    <button class="symbol-button" onclick="insertSymbol('-')">-</button>
                    <button class="symbol-button" onclick="insertSymbol('\\times')">×</button>
                    <button class="symbol-button" onclick="insertSymbol('\\div')">÷</button>
                    <button class="symbol-button" onclick="insertSymbol('=')">=</button>
                    <button class="symbol-button" onclick="insertSymbol('\\neq')">≠</button>
                    <button class="symbol-button" onclick="insertSymbol('\\pm')">±</button>
                    <button class="symbol-button" onclick="insertSymbol('\\cdot')">·</button>
                </div>
            </div>
            
            <div class="category" onclick="toggleCategory(this)">
                <div class="category-header">
                    <i class="fas fa-infinity"></i>
                    <span class="category-name">Calculus</span>
                </div>
                <div class="symbol-panel">
                    <button class="symbol-button" onclick="insertSymbol('\\int_{a}^{b}')">∫</button>
                    <button class="symbol-button" onclick="insertSymbol('\\iint')">∬</button>
                    <button class="symbol-button" onclick="insertSymbol('\\iiint')">∭</button>
                    <button class="symbol-button" onclick="insertSymbol('\\oint')">∮</button>
                    <button class="symbol-button" onclick="insertSymbol('\\frac{d}{dx}')">d/dx</button>
                    <button class="symbol-button" onclick="insertSymbol('\\frac{\\partial}{\\partial x}')">∂/∂x</button>
                    <button class="symbol-button" onclick="insertSymbol('\\nabla')">∇</button>
                    <button class="symbol-button" onclick="insertSymbol('\\Delta')">Δ</button>
                    <button class="symbol-button" onclick="insertSymbol('\\lim_{x \\to a}')">lim</button>
                    <button class="symbol-button" onclick="insertSymbol('\\sum_{i=1}^{n}')">Σ</button>
                    <button class="symbol-button" onclick="insertSymbol('\\prod_{i=1}^{n}')">Π</button>
                    <button class="symbol-button" onclick="insertSymbol('\\infty')">∞</button>
                </div>
            </div>
            
            <div class="category" onclick="toggleCategory(this)">
                <div class="category-header">
                    <i class="fas fa-greater-than-equal"></i>
                    <span class="category-name">Algebra</span>
                </div>
                <div class="symbol-panel">
                    <button class="symbol-button" onclick="insertSymbol('\\frac{a}{b}')">a/b</button>
                    <button class="symbol-button" onclick="insertSymbol('\\sqrt{x}')">√x</button>
                    <button class="symbol-button" onclick="insertSymbol('\\sqrt[n]{x}')">ⁿ√x</button>
                    <button class="symbol-button" onclick="insertSymbol('x^{n}')">xⁿ</button>
                    <button class="symbol-button" onclick="insertSymbol('x_{n}')">xₙ</button>
                    <button class="symbol-button" onclick="insertSymbol('\\leq')">≤</button>
                    <button class="symbol-button" onclick="insertSymbol('\\geq')">≥</button>
                    <button class="symbol-button" onclick="insertSymbol('\\approx')">≈</button>
                    <button class="symbol-button" onclick="insertSymbol('\\equiv')">≡</button>
                    <button class="symbol-button" onclick="insertSymbol('\\propto')">∝</button>
                </div>
            </div>
            
            <div class="category" onclick="toggleCategory(this)">
                <div class="category-header">
                    <i class="fas fa-shapes"></i>
                    <span class="category-name">Geometry</span>
                </div>
                <div class="symbol-panel">
                    <button class="symbol-button" onclick="insertSymbol('\\pi')">π</button>
                    <button class="symbol-button" onclick="insertSymbol('\\theta')">θ</button>
                    <button class="symbol-button" onclick="insertSymbol('\\alpha')">α</button>
                    <button class="symbol-button" onclick="insertSymbol('\\beta')">β</button>
                    <button class="symbol-button" onclick="insertSymbol('\\gamma')">γ</button>
                    <button class="symbol-button" onclick="insertSymbol('\\sin')">sin</button>
                    <button class="symbol-button" onclick="insertSymbol('\\cos')">cos</button>
                    <button class="symbol-button" onclick="insertSymbol('\\tan')">tan</button>
                    <button class="symbol-button" onclick="insertSymbol('\\angle')">∠</button>
                    <button class="symbol-button" onclick="insertSymbol('\\perp')">⊥</button>
                    <button class="symbol-button" onclick="insertSymbol('\\parallel')">∥</button>
                </div>
            </div>
            
            <div class="category" onclick="toggleCategory(this)">
                <div class="category-header">
                    <i class="fas fa-superscript"></i>
                    <span class="category-name">Advanced</span>
                </div>
                <div class="symbol-panel">
                    <button class="symbol-button" onclick="insertSymbol('\\forall')">∀</button>
                    <button class="symbol-button" onclick="insertSymbol('\\exists')">∃</button>
                    <button class="symbol-button" onclick="insertSymbol('\\emptyset')">∅</button>
                    <button class="symbol-button" onclick="insertSymbol('\\in')">∈</button>
                    <button class="symbol-button" onclick="insertSymbol('\\subset')">⊂</button>
                    <button class="symbol-button" onclick="insertSymbol('\\subseteq')">⊆</button>
                    <button class="symbol-button" onclick="insertSymbol('\\cup')">∪</button>
                    <button class="symbol-button" onclick="insertSymbol('\\cap')">∩</button>
                    <button class="symbol-button" onclick="insertSymbol('\\mathbb{R}')">ℝ</button>
                    <button class="symbol-button" onclick="insertSymbol('\\mathbb{Z}')">ℤ</button>
                </div>
            </div>
            
            <div class="category" onclick="toggleCategory(this)">
                <div class="category-header">
                    <i class="fas fa-grip-lines"></i>
                    <span class="category-name">Matrices</span>
                </div>
                <div class="symbol-panel">
                    <button class="symbol-button" onclick="insertSymbol('\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}')">2x2</button>
                    <button class="symbol-button" onclick="insertSymbol('\\begin{bmatrix} a & b \\\\ c & d \\end{bmatrix}')">[2x2]</button>
                    <button class="symbol-button" onclick="insertSymbol('\\begin{pmatrix} a & b & c \\\\ d & e & f \\\\ g & h & i  \\end{pmatrix}')">3x3</button>
                    <button class="symbol-button" onclick="insertSymbol('\\begin{bmatrix} a & b & c \\\\ d & e & f \\\\ g & h & i \\end{bmatrix}')">[3x3]</button>
                    <button class="symbol-button" onclick="insertSymbol('\\vec{v}')">v⃗</button>
                    <button class="symbol-button" onclick="insertSymbol('\\hat{i}')">î</button>
                    <button class="symbol-button" onclick="insertSymbol('\\times')">×</button>
                    <button class="symbol-button" onclick="insertSymbol('\\cdot')">·</button>
                    <button class="symbol-button" onclick="insertSymbol('\\|v\\|')">‖v‖</button>
                </div>
            </div>
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
                                    <input type="radio" name="correct_answer" value="<?php echo $key; ?>" 
                                           <?php echo (isset($_POST['correct_answer']) && $_POST['correct_answer'] == $key) || 
                                                     (!isset($_POST['correct_answer']) && $key == $correct_answer) ? 'checked' : ''; ?>>
                                    <input type="text" id="choices[<?php echo $key; ?>]" name="choices[<?php echo $key; ?>]" 
                                           placeholder="Enter choice..." value="<?php echo htmlspecialchars($choice['text']); ?>" 
                                           oninput="updatePreview()">
                                    <div style="margin-left: 10px;">
                                        <button type="button" class="symbol-button" onclick="insertSymbolIntoField('choices[<?php echo $key; ?>]', '\\[ \\]')">
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