<?php
session_start();
require_once 'db.php';

$error_message = "";

// If farmer session exists, redirect to dashboard
if (isset($_SESSION['farmer_id'])) {
    header("Location: farmer/dashboard.php");
    exit();
}

// Form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $password = $_POST['password'];
    
    if (empty($name) || empty($password)) {
        $error_message = "Please enter both name and password.";
    } else {
        // Check credentials
        $stmt = $conn->prepare("SELECT farmer_id, name, password FROM Farmer WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $farmer = $result->fetch_assoc();
            
            if (password_verify($password, $farmer['password'])) {
                // Login successful
                $_SESSION['farmer_id'] = $farmer['farmer_id'];
                $_SESSION['farmer_name'] = $farmer['name'];
                header("Location: farmer/dashboard.php");
                exit();
            } else {
                $error_message = "Invalid name or password.";
            }
        } else {
            $error_message = "Invalid name or password.";
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
    <title>Farmer Login</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>

<div class="form-container">
    <h2 class="farmer"> Farmer Login</h2>
    <p class="subtitle">Welcome back! Login to your account</p>
    
    <?php if ($error_message): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <form method="POST" onsubmit="return validateLoginForm()">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" class="farmer" placeholder="Enter your full name" required 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="farmer" placeholder="Enter your password" required>
        </div>
        
        <button type="submit" class="farmer">Login</button>
    </form>
    
    <div class="link">
        Don't have an account? <a href="signup_farmer.php" class="farmer">Sign up here</a>
    </div>
</div>

<script src="assets/js/auth.js"></script>
</body>
</html>