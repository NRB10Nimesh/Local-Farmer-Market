<?php
session_start();
require_once 'db.php';

$error_message = "";

// If buyer session exists, redirect to dashboard
if (isset($_SESSION['buyer_id'])) {
    header("Location: buyer/dashboard.php");
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
        $stmt = $conn->prepare("SELECT buyer_id, name, password FROM Buyer WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $buyer = $result->fetch_assoc();
            
            if (password_verify($password, $buyer['password'])) {
                // Login successful
                $_SESSION['buyer_id'] = $buyer['buyer_id'];
                $_SESSION['buyer_name'] = $buyer['name'];
                header("Location: buyer/dashboard.php");
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
    <title>Buyer Login</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>

<div class="form-container">
    <h2 class="buyer"><span class="material-icons" aria-hidden="true">shopping_cart</span> Buyer Login</h2>
    <p class="subtitle">Welcome back! Login to your account</p>
    
    <?php if ($error_message): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" class="buyer" placeholder="Enter your full name" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="buyer" placeholder="Enter your password" required>
        </div>
        
        <button type="submit" class="buyer">Login</button>
    </form>
    
    <div class="link">
        Don't have an account? <a href="signup_buyer.php" class="buyer">Sign up here</a>
    </div>
</div>

</body>
</html>