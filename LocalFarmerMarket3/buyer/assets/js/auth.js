// ================================================
// MODERN AUTHENTICATION VALIDATION
// ================================================

// Validation Functions
function validateName(name) {
    return /^[a-zA-Z\s]+$/.test(name) && name.length >= 2 && name.length <= 100;
}

function validateContact(contact) {
    return /^[0-9]{10}$/.test(contact);
}

function validateAddress(address) {
    return address.length >= 10 && address.length <= 255;
}

function validateFarmType(farmType) {
    return farmType.length >= 2 && farmType.length <= 100;
}

function validatePassword(password) {
    return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(password) && password.length >= 6 && password.length <= 50;
}

// Check Password Strength
function checkPasswordStrength(password) {
    const strengthBar = document.getElementById('strengthBar');
    if (!strengthBar) return;
    
    strengthBar.className = 'password-strength-bar';
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    if (strength <= 2) {
        strengthBar.classList.add('weak');
    } else if (strength <= 4) {
        strengthBar.classList.add('medium');
    } else {
        strengthBar.classList.add('strong');
    }
}

// Update Field Status
function updateFieldStatus(field, isValid, hintId) {
    const hint = document.getElementById(hintId);
    if (!hint) return;
    
    if (field.value.length > 0) {
        hint.classList.add('show');
    } else {
        hint.classList.remove('show');
    }
}

// Setup Real-time Validation
function setupValidation() {
    const nameField = document.getElementById('name');
    const contactField = document.getElementById('contact');
    const addressField = document.getElementById('address');
    const farmTypeField = document.getElementById('farm_type');
    const passwordField = document.getElementById('password');
    
    if (nameField) {
        nameField.addEventListener('input', function() {
            updateFieldStatus(this, validateName(this.value.trim()), 'nameHint');
        });
    }
    
    if (contactField) {
        contactField.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
            updateFieldStatus(this, validateContact(this.value), 'contactHint');
        });
    }
    
    if (addressField) {
        addressField.addEventListener('input', function() {
            updateFieldStatus(this, validateAddress(this.value.trim()), 'addressHint');
        });
    }
    
    if (farmTypeField) {
        farmTypeField.addEventListener('input', function() {
            updateFieldStatus(this, validateFarmType(this.value.trim()), 'farmTypeHint');
        });
    }
    
    if (passwordField) {
        passwordField.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            updateFieldStatus(this, validatePassword(this.value), 'passwordHint');
        });
    }
}

// Validate Signup Form
function validateSignupForm() {
    const nameField = document.getElementById('name');
    const contactField = document.getElementById('contact');
    const addressField = document.getElementById('address');
    const farmTypeField = document.getElementById('farm_type');
    const passwordField = document.getElementById('password');
    
    let isValid = true;
    
    if (nameField && !validateName(nameField.value.trim())) {
        alert('Invalid name. Use only letters and spaces.');
        isValid = false;
    }
    
    if (contactField && !validateContact(contactField.value)) {
        alert('Contact must be exactly 10 digits.');
        isValid = false;
    }
    
    if (addressField && !validateAddress(addressField.value.trim())) {
        alert('Address must be between 10 and 255 characters.');
        isValid = false;
    }
    
    if (farmTypeField && !validateFarmType(farmTypeField.value.trim())) {
        alert('Farm type is required.');
        isValid = false;
    }
    
    if (passwordField && !validatePassword(passwordField.value)) {
        alert('Password must have uppercase, lowercase, number, and be 6+ characters.');
        isValid = false;
    }
    
    return isValid;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setupValidation();
});