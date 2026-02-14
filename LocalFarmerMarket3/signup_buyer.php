<?php
require_once 'db.php';

$error_message = "";
$success_message = "";

// Handle buyer signup form submission and validation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $password = $_POST['password'];
    
    // Validation
    if (empty($name) || !preg_match("/^[a-zA-Z\s]+$/", $name) || strlen($name) < 2 || strlen($name) > 100) {
        $error_message = "Invalid name. Use only letters and spaces (2-100 characters).";
    } elseif (empty($contact) || !preg_match("/^[0-9]{10}$/", $contact)) {
        $error_message = "Contact number must be exactly 10 digits.";
    } elseif (empty($address) || strlen($address) < 10 || strlen($address) > 255) {
        $error_message = "Address must be between 10 and 255 characters.";
    } elseif (empty($password) || strlen($password) < 6 || !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/", $password)) {
        $error_message = "Password must be 6+ characters with uppercase, lowercase, and number.";
    } else {
        // Check if buyer exists
        $stmt = $conn->prepare("SELECT buyer_id FROM Buyer WHERE name = ? OR contact = ?");
        $stmt->bind_param("ss", $name, $contact);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "Name or contact number already registered.";
        } else {
            // Insert buyer
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO Buyer (name, contact, address, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $contact, $address, $hashed_password);
            
            if ($stmt->execute()) {
                $success_message = "Registration successful! Redirecting to login...";
                echo "<meta http-equiv='refresh' content='2;url=login_buyer.php'>";
            } else {
                $error_message = "Registration failed. Please try again.";
            }
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
    <title>Buyer Sign Up</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>

<div class="form-container">
    <h2 class="buyer"><span class="material-icons" aria-hidden="true">shopping_cart</span> Buyer Sign Up</h2>
    <p class="subtitle">Start shopping fresh from local farms</p>
    
    <?php if ($error_message): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <form method="POST" onsubmit="return validateSignupForm()">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" class="buyer" placeholder="Enter your full name" required 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            <div class="validation-hint" id="nameHint">Letters and spaces only (2-100 characters)</div>
        </div>
        
        <div class="form-group">
            <label for="contact">Contact Number</label>
            <input type="tel" name="contact" id="contact" class="buyer" placeholder="10-digit mobile number" required 
                   maxlength="10" value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
            <div class="validation-hint" id="contactHint">Enter exactly 10 digits</div>
        </div>
        
        <div class="form-group">
            <label for="address">Delivery Address</label>
            <textarea name="address" id="address" class="buyer" placeholder="Enter your complete delivery address" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            <div class="validation-hint" id="addressHint">Minimum 10 characters required</div>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="buyer" placeholder="Create a strong password" required>
            <div class="password-strength">
                <div class="password-strength-bar" id="strengthBar"></div>
            </div>
            <div class="validation-hint" id="passwordHint">Include uppercase, lowercase, and number</div>
        </div>
        
        <button type="submit" class="buyer">Create Account</button>
    </form>
    
    <div class="link">
        Already have an account? <a href="login_buyer.php" class="buyer">Login here</a>
    </div>
</div>

<script src="assets/js/auth.js"></script>
</body>
</html>