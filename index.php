<?php
session_start();
require_once 'db_connection.php';

// Initialize variables
$username = '';
$password = '';
$remember = false;
$error = '';

// Check if user is already logged in (redirect to dashboard)
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Process login form if submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        // Prepare SQL to prevent SQL injection
        $stmt = $conn->prepare("SELECT prof_ID, username, password, first_name, last_name FROM professor WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, start a new session
                $_SESSION['user_id'] = $user['prof_ID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Remember me functionality (set cookie)
                if ($remember) {
                    $cookie_value = base64_encode($user['prof_ID'] . ':' . hash('sha256', $user['password']));
                    setcookie('remember_me', $cookie_value, time() + (86400 * 30), "/"); // 30 days
                }
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
        
        $stmt->close();
    }
}

// Check for "remember me" cookie
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $cookie_data = base64_decode($_COOKIE['remember_me']);
    list($user_id, $token) = explode(':', $cookie_data);
    
    $stmt = $conn->prepare("SELECT prof_ID, username, password, first_name, last_name FROM professor WHERE prof_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $expected_token = hash('sha256', $user['password']);
        
        if (hash_equals($expected_token, $token)) {
            $_SESSION['user_id'] = $user['prof_ID'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            header("Location: dashboard.php");
            exit();
        }
    }
    
    // Invalid cookie, clear it
    setcookie('remember_me', '', time() - 3600, "/");
}
$conn->close();
?>
