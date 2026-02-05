<?php
require_once 'db.php';

$error_message = "";
$success_message = "";

// Form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $farm_type = trim($_POST['farm_type']);
    $password = $_POST['password'];
    $accept_commission = isset($_POST['accept_commission']) ? $_POST['accept_commission'] : '';
    
    // Validation
    if (empty($name) || !preg_match("/^[a-zA-Z\s]+$/", $name) || strlen($name) < 2 || strlen($name) > 100) {
        $error_message = "Invalid name. Use only letters and spaces (2-100 characters).";
    } elseif (empty($contact) || !preg_match("/^[0-9]{10}$/", $contact)) {
        $error_message = "Contact number must be exactly 10 digits.";
    } elseif (empty($address) || strlen($address) < 10 || strlen($address) > 255) {
        $error_message = "Address must be between 10 and 255 characters.";
    } elseif (empty($farm_type) || strlen($farm_type) < 2 || strlen($farm_type) > 100) {
        $error_message = "Farm type must be between 2 and 100 characters.";
    } elseif (empty($password) || strlen($password) < 6 || !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/", $password)) {
        $error_message = "Password must be 6+ characters with uppercase, lowercase, and number.";
    } elseif ($accept_commission !== 'yes') {
        $error_message = "You must accept the commission terms to register.";
    } else {
        // Check if farmer exists
        $stmt = $conn->prepare("SELECT farmer_id FROM Farmer WHERE name = ? OR contact = ?");
        $stmt->bind_param("ss", $name, $contact);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "Name or contact number already registered.";
        } else {
            // Insert farmer
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO Farmer (name, contact, address, farm_type, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $contact, $address, $farm_type, $hashed_password);
            
            if ($stmt->execute()) {
                $success_message = "Registration successful! Redirecting to login...";
                echo "<meta http-equiv='refresh' content='2;url=login_farmer.php'>";
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
    <title>Farmer Sign Up</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        .commission-section {
            background: #f0fdf4;
            border: 2px solid #86efac;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .commission-section h3 {
            color: #15803d;
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        
        .commission-info {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #22c55e;
        }
        
        .commission-info p {
            margin: 5px 0;
            color: #374151;
            font-size: 14px;
        }
        
        .commission-info strong {
            color: #15803d;
            font-size: 18px;
        }
        
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }
        
        .radio-option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .radio-option:hover {
            border-color: #86efac;
            background: #f9fafb;
        }
        
        .radio-option input[type="radio"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .radio-option.selected {
            border-color: #22c55e;
            background: #f0fdf4;
        }
        
        .radio-label {
            flex: 1;
        }
        
        .radio-label strong {
            display: block;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .radio-label span {
            color: #6b7280;
            font-size: 13px;
        }
        
        .terms-link {
            display: inline-block;
            color: #15803d;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .terms-link:hover {
            text-decoration: underline;
        }
        
        .required-notice {
            color: #dc2626;
            font-size: 13px;
            margin-top: 10px;
            display: none;
        }
        
        .required-notice.show {
            display: block;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2 class="farmer">Farmer Sign Up</h2>
    <p class="subtitle">Join our farming community today</p>
    
    <?php if ($error_message): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <form method="POST" onsubmit="return validateSignupForm()">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" class="farmer" placeholder="Enter your full name" required 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            <div class="validation-hint" id="nameHint">Letters and spaces only (2-100 characters)</div>
        </div>
        
        <div class="form-group">
            <label for="contact">Contact Number</label>
            <input type="tel" name="contact" id="contact" class="farmer" placeholder="10-digit mobile number" required 
                   maxlength="10" value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
            <div class="validation-hint" id="contactHint">Enter exactly 10 digits</div>
        </div>
        
        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" class="farmer" placeholder="Enter your complete address" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            <div class="validation-hint" id="addressHint">Minimum 10 characters required</div>
        </div>
        
        <div class="form-group">
            <label for="farm_type">Farm Type</label>
            <input type="text" name="farm_type" id="farm_type" class="farmer" placeholder="e.g., Vegetables, Fruits, Dairy" required 
                   value="<?php echo isset($_POST['farm_type']) ? htmlspecialchars($_POST['farm_type']) : ''; ?>">
            <div class="validation-hint" id="farmTypeHint">What do you grow or produce?</div>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="farmer" placeholder="Create a strong password" required>
            <div class="password-strength">
                <div class="password-strength-bar" id="strengthBar"></div>
            </div>
            <div class="validation-hint" id="passwordHint">Include uppercase, lowercase, and number</div>
        </div>
        
        <!-- Commission Terms Section -->
        <div class="commission-section">
            <h3>📋 Commission Terms & Agreement</h3>
            
            <div class="commission-info">
                <p>Our platform operates on a commission-based model:</p>
                <p><strong>10% commission</strong> on each sale made through the platform</p>
                <p style="margin-top: 10px; font-size: 13px; color: #6b7280;">
                    This helps us maintain the marketplace, provide support, handle payments securely, 
                    and market your products to customers.
                </p>
            </div>
            
            <a href="commission_terms.php" target="_blank" class="terms-link">
                📄 Read Full Commission Terms & Conditions →
            </a>
            
            <div class="radio-group">
                <label class="radio-option" onclick="selectRadio(this)">
                    <input type="radio" name="accept_commission" value="yes" id="accept_yes" 
                           <?php echo (isset($_POST['accept_commission']) && $_POST['accept_commission'] == 'yes') ? 'checked' : ''; ?>>
                    <div class="radio-label">
                        <strong>✓ I Accept</strong>
                        <span>I have read and agree to the 10% commission terms and conditions</span>
                    </div>
                </label>
                
                <label class="radio-option" onclick="selectRadio(this)">
                    <input type="radio" name="accept_commission" value="no" id="accept_no"
                           <?php echo (isset($_POST['accept_commission']) && $_POST['accept_commission'] == 'no') ? 'checked' : ''; ?>>
                    <div class="radio-label">
                        <strong>✗ I Do Not Accept</strong>
                        <span>I cannot proceed without accepting the commission terms</span>
                    </div>
                </label>
            </div>
            
            <div class="required-notice" id="commissionNotice">
                * You must accept the commission terms to complete registration
            </div>
        </div>
        
        <button type="submit" class="farmer">Create Account</button>
    </form>
    
    <div class="link">
        Already have an account? <a href="login_farmer.php" class="farmer">Login here</a>
    </div>
</div>

<script src="assets/js/auth.js"></script>
<script>
    function selectRadio(label) {
        // Remove selected class from all options
        document.querySelectorAll('.radio-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Add selected class to clicked option
        label.classList.add('selected');
        
        // Check the radio button
        const radio = label.querySelector('input[type="radio"]');
        radio.checked = true;
        
        // Show/hide notice based on selection
        const notice = document.getElementById('commissionNotice');
        if (radio.value === 'no') {
            notice.classList.add('show');
        } else {
            notice.classList.remove('show');
        }
    }
    
    // Initialize selected state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const checkedRadio = document.querySelector('input[name="accept_commission"]:checked');
        if (checkedRadio) {
            const label = checkedRadio.closest('.radio-option');
            label.classList.add('selected');
            if (checkedRadio.value === 'no') {
                document.getElementById('commissionNotice').classList.add('show');
            }
        }
    });
    
    // Override the existing form validation to include commission check
    const originalValidate = window.validateSignupForm;
    window.validateSignupForm = function() {
        const acceptRadio = document.querySelector('input[name="accept_commission"]:checked');
        
        if (!acceptRadio || acceptRadio.value !== 'yes') {
            alert('Please accept the commission terms to complete registration.');
            document.getElementById('commissionNotice').classList.add('show');
            return false;
        }
        
        // Call original validation if it exists
        if (typeof originalValidate === 'function') {
            return originalValidate();
        }
        
        return true;
    };
</script>
</body>
</html>