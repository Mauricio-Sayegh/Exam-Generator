<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$question_ids = isset($_POST['question_ids']) ? explode(',', $_POST['question_ids']) : [];
$format = isset($_POST['format']) ? $_POST['format'] : 'pdf';
$include_answers = isset($_POST['include_answers']) && $_POST['include_answers'] == '1';

if (empty($question_ids)) {
    header("Location: my_subjects.php");
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
$stmt->bind_param($types . 'i', ...array_merge($question_ids, [$professor_id]));
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['count'] != count($question_ids)) {
    die("Some questions do not belong to you");
}

// Get questions data
$stmt = $conn->prepare("
    SELECT q.*, s.subject_name, u.university_name_en 
    FROM question q
    JOIN subject s ON q.subject_ID = s.subject_ID
    JOIN university u ON s.university_ID = u.university_ID
    WHERE q.question_ID IN ($placeholders)
    ORDER BY FIND_IN_SET(q.question_ID, ?)
");
$id_list = implode(',', $question_ids);
$stmt->bind_param($types . 's', ...array_merge($question_ids, [$id_list]));
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Generate export content based on format
switch ($format) {
    case 'pdf':
        require_once 'tcpdf/tcpdf.php';
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Exam Generator');
        $pdf->SetAuthor($_SESSION['name']);
        $pdf->SetTitle('Exported Questions');
        $pdf->SetSubject('Questions Export');
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 12);
        
        // Add title
        $pdf->Cell(0, 10, 'Exported Questions', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Add subject info
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Subject: ' . $questions[0]['subject_name'], 0, 1);
        $pdf->Cell(0, 10, 'University: ' . $questions[0]['university_name_en'], 0, 1);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Ln(10);
        
        // Add questions
        foreach ($questions as $index => $question) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Question ' . ($index + 1) . ' (' . $question['mark'] . ' points)', 0, 1);
            $pdf->SetFont('helvetica', '', 12);
            
            // Question text
            $pdf->MultiCell(0, 0, strip_tags($question['question_text']), 0, 'L');
            $pdf->Ln(5);
            
            // Choices
            if ($include_answers) {
                $pdf->SetFont('helvetica', 'I', 11);
                for ($i = 0; $i < 5; $i++) {
                    $letter = chr(65 + $i);
                    $text_field = 'ans_' . $letter;
                    $correct_field = 'is_correct_' . $letter;
                    
                    if (!empty($question[$text_field])) {
                        $prefix = $question[$correct_field] ? '✓ ' : '  ';
                        $pdf->Cell(0, 0, $prefix . $letter . '. ' . strip_tags($question[$text_field]), 0, 1);
                        $pdf->Ln(3);
                    }
                }
                $pdf->SetFont('helvetica', '', 12);
            } else {
                for ($i = 0; $i < 5; $i++) {
                    $letter = chr(65 + $i);
                    $text_field = 'ans_' . $letter;
                    
                    if (!empty($question[$text_field])) {
                        $pdf->Cell(0, 0, $letter . '. ' . strip_tags($question[$text_field]), 0, 1);
                        $pdf->Ln(3);
                    }
                }
            }
            
            $pdf->Ln(10);
        }
        
        // Output PDF
        $pdf->Output('questions_export.pdf', 'D');
        break;
        
    case 'word':
        header("Content-Type: application/vnd.ms-word");
        header("Content-Disposition: attachment; filename=questions_export.doc");
        
        echo "<html>";
        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
        echo "<body>";
        echo "<h1 style='text-align:center'>Exported Questions</h1>";
        echo "<h2>Subject: " . htmlspecialchars($questions[0]['subject_name']) . "</h2>";
        echo "<h3>University: " . htmlspecialchars($questions[0]['university_name_en']) . "</h3>";
        echo "<hr>";
        
        foreach ($questions as $index => $question) {
            echo "<h3>Question " . ($index + 1) . " (" . $question['mark'] . " points)</h3>";
            echo "<p>" . nl2br(htmlspecialchars($question['question_text'])) . "</p>";
            
            echo "<ul>";
            for ($i = 0; $i < 5; $i++) {
                $letter = chr(65 + $i);
                $text_field = 'ans_' . $letter;
                $correct_field = 'is_correct_' . $letter;
                
                if (!empty($question[$text_field])) {
                    $style = $include_answers && $question[$correct_field] ? "style='color:green;font-weight:bold'" : "";
                    echo "<li $style>" . htmlspecialchars($question[$text_field]) . "</li>";
                }
            }
            echo "</ul>";
            echo "<hr>";
        }
        
        echo "</body></html>";
        break;
        
    case 'text':
    default:
        header("Content-Type: text/plain");
        header("Content-Disposition: attachment; filename=questions_export.txt");
        
        echo "Exported Questions\n";
        echo "=================\n\n";
        echo "Subject: " . $questions[0]['subject_name'] . "\n";
        echo "University: " . $questions[0]['university_name_en'] . "\n\n";
        
        foreach ($questions as $index => $question) {
            echo "Question " . ($index + 1) . " (" . $question['mark'] . " points)\n";
            echo str_repeat("-", 30) . "\n";
            echo strip_tags($question['question_text']) . "\n\n";
            
            for ($i = 0; $i < 5; $i++) {
                $letter = chr(65 + $i);
                $text_field = 'ans_' . $letter;
                $correct_field = 'is_correct_' . $letter;
                
                if (!empty($question[$text_field])) {
                    $prefix = $include_answers && $question[$correct_field] ? "[✓] " : "[ ] ";
                    echo $prefix . $letter . ". " . strip_tags($question[$text_field]) . "\n";
                }
            }
            echo "\n";
        }
        break;
}
?>