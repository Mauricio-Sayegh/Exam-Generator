<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$error = '';
$success = '';
$questions = [];
$selected_subject = null;
$exam_date = '';

// Get professor's subjects
$subjects = [];
$stmt = $conn->prepare("SELECT subject_ID, subject_name FROM subject WHERE professor_ID = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['select_subject'])) {
        // Step 1: Subject selection
        $selected_subject = $_POST['subject_id'];
        $exam_date = $_POST['exam_date'];
        
        if (empty($exam_date)) {
            $error = "Exam date is required";
        } else {
            // Get questions for selected subject
            $stmt = $conn->prepare("SELECT question_ID, question_text FROM question WHERE professor_ID = ? AND subject_ID = ?");
            $stmt->bind_param("ii", $professor_id, $selected_subject);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $questions[] = $row;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Basic styling - you can move this to a CSS file later */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .question-item {
            margin-bottom: 15px;
            padding: 15px;
            background-color: white;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-file-alt"></i> Create New Exam</h1>
        
        <?php if ($error): ?>
            <div style="color: red; padding: 10px; margin-bottom: 20px; background-color: #ffebee; border-radius: 4px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="color: green; padding: 10px; margin-bottom: 20px; background-color: #e8f5e9; border-radius: 4px;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-section">
                <h2>1. Select Subject and Date</h2>
                
                <div class="form-group">
                    <label for="subject_id">Subject:</label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_ID']; ?>"
                                <?php if ($selected_subject == $subject['subject_ID']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="exam_date">Exam Date:</label>
                    <input type="date" id="exam_date" name="exam_date" 
                           value="<?php echo htmlspecialchars($exam_date); ?>" required>
                </div>
                
                <button type="submit" name="select_subject" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Continue
                </button>
            </div>
            
            <?php if (!empty($questions) && $selected_subject): ?>
            <div class="form-section">
                <h2>2. Select Questions</h2>
                
                <div class="question-list">
                    <?php foreach ($questions as $question): ?>
                    <div class="question-item">
                        <input type="checkbox" id="question_<?php echo $question['question_ID']; ?>" 
                               name="questions[]" value="<?php echo $question['question_ID']; ?>">
                        <label for="question_<?php echo $question['question_ID']; ?>">
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                <input type="hidden" name="exam_date" value="<?php echo htmlspecialchars($exam_date); ?>">
                
                <button type="submit" name="generate_exam" class="btn btn-primary">
                    <i class="fas fa-file-pdf"></i> Generate Exam PDF (1 Token)
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        // Basic form submission handler
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitted');
                    return true;
                });
            }
        });
    </script>
</body>
</html>