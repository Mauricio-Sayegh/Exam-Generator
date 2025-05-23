<?php
session_start();
require_once 'db_connection.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

// Get POST data
$subject_id = $_POST['subject_id'] ;
$exam_date = $_POST['exam_date'] ;
$question_ids = $_POST['questions'] ;

if (!$subject_id || !$exam_date || empty($question_ids)) {
    die("Missing required parameters.");
}

// Fetch subject details
$stmt = $conn->prepare("
    SELECT s.subject_name, u.university_name_en 
    FROM subject s
    JOIN university u ON s.university_ID = u.university_ID
    WHERE s.subject_ID = ?
");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch selected questions with answers
$placeholders = implode(',', array_fill(0, count($question_ids), '?'));
$types = str_repeat('i', count($question_ids));
$stmt = $conn->prepare("
    SELECT question_ID, question_text, mark,
           ans_A, ans_B, ans_C, ans_D, ans_E,
           is_correct_A, is_correct_B, is_correct_C, is_correct_D, is_correct_E
    FROM question 
    WHERE question_ID IN ($placeholders)
");
$stmt->bind_param($types, ...$question_ids);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build questions content
$questions_tex = '';
foreach ($questions as $index => $q) {
    // Escape special characters
    $text = htmlspecialchars_decode($q['question_text'], ENT_QUOTES);
    $text = str_replace(['%', '&', '_', '#', '{', '}'], ['\\%','\&','\_','\#','\{','\}'], $text);

    // Replace math delimiters if needed
    $text = preg_replace('/\$(.*?)\$/', '\\(\1\\)', $text);     // Inline MathJax -> LaTeX
    $text = preg_replace('/\$\$(.*?)\$\$/', '\\[\1\\]', $text); // Display math

    // Start question
    $questions_tex .= "\\noindent \\textbf{Q" . ($index + 1) . ".} $text\n\n";

    // Add choices inline with mark
    $options = ['A', 'B', 'C', 'D', 'E'];
    $choices = [];

    foreach ($options as $opt) {
        $answer = $q["ans_$opt"];
        $is_correct = $q["is_correct_$opt"];

        if (!empty($answer)) {
            // Escape special characters
            $answer = str_replace(['%', '&', '_', '#', '{', '}'], ['\\%','\&','\_','\#','\{','\}'], $answer);
            $correct_mark = $is_correct ? '(Correct)' : '';

            $choices[] = "\\textbf{" . $opt . "}. $answer $correct_mark";
        }
    }

    // Join choices into a single line with spacing
    $choices_line = implode ('\\hspace{1.5em} ', $choices);

    // Add choice command with mark
    $questions_tex .= "\\choice{" . "" . "}{" . $choices_line . "}{" . $q['mark'] . "}\n\n";
}

// Load LaTeX template
$template_path = __DIR__ . '/latex/template.tex';
$latex_content = file_get_contents($template_path);

// Replace placeholders
$latex_content = str_replace([
    '<<SUBJECT>>',
    '<<UNIVERSITY>>',
    '<<DATE>>',
    '<<QUESTIONS>>'
], [
    $subject['subject_name'],
    $subject['university_name_en'],
    date('F j, Y', strtotime($exam_date)),
    $questions_tex
], $latex_content);

// Save .tex file
$exam_dir = __DIR__ . '/exams/';
$filename_base = 'exam_' . time();
$latex_file = $exam_dir . $filename_base . '.tex';
$pdf_file = $exam_dir . $filename_base . '.pdf';

file_put_contents($latex_file, $latex_content);

// Run pdflatex
$output = [];
$return_var = null;

// Windows path example — adjust based on your MiKTeX install location
$command = '"C:\\Program Files\\MiKTeX\\miktex\\bin\\x64\\pdflatex.exe" --interaction=nonstopmode --output-directory="' . $exam_dir . '" "' . $latex_file . '"';
exec($command, $output, $return_var);

if ($return_var !== 0 || !file_exists($pdf_file)) {
    echo "<pre>Error generating PDF:\n";
    print_r($output);
    echo "</pre>";
    exit;
}

// Serve PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename_base . '.pdf"');
readfile($pdf_file);
exit;