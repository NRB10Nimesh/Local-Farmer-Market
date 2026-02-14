<?php
session_start();
require_once 'db.php';

$error_message = "";

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: admin/dashboard.php");
    exit();
}

// Form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        // Check credentials
        $stmt = $conn->prepare("SELECT admin_id, username, password_hash, full_name FROM admin WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            if (password_verify($password, $admin['password_hash'])) {
                // Login successful
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                
                // Update last login
                $update_stmt = $conn->prepare("UPDATE admin SET last_login = NOW(), login_attempts = 0 WHERE admin_id = ?");
                $update_stmt->bind_param("i", $admin['admin_id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                header("Location: admin/dashboard.php");
                exit();
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Local Farmer Market</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .admin-theme {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .admin-theme h2 { color: #667eea; }
        .admin-theme button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .admin-theme a { color: #667eea; }
        .admin-theme input:focus, .admin-theme textarea:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body class="admin-theme">

<div class="form-container">
    <h2 class="admin"><span class="material-icons" aria-hidden="true">lock</span> Admin Login</h2>
    <p class="subtitle">Secure access to management portal</p>
    
    <?php if ($error_message): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" class="admin" placeholder="Enter admin username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="admin" placeholder="Enter your password" required>
        </div>
        
        <button type="submit" class="admin">Login to Admin Panel</button>
    </form>
    
    <div class="link">
        <a href="index.php">← Back to Home</a>
    </div>
</div>

</body>
</html>