// Shared JavaScript for Login and Signup pages

// Validation functions
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

// Update field status
function updateFieldStatus(field, isValid, hintId, message) {
    const hint = document.getElementById(hintId);
    if (!hint) return;
    
    field.classList.remove('error', 'valid');
    hint.classList.remove('error', 'valid');
    
    if (field.value.length > 0) {
        if (isValid) {
            field.classList.add('valid');
            hint.classList.add('valid');
        } else {
            field.classList.add('error');
            hint.classList.add('error');
        }
    }
    
    if (message) hint.textContent = message;
}

// Password strength checker
function checkPasswordStrength(password) {
    const strengthBar = document.getElementById('strengthBar');
    if (!strengthBar) return;
    
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    strengthBar.className = 'password-strength-bar';
    if (strength <= 2) strengthBar.classList.add('strength-weak');
    else if (strength <= 4) strengthBar.classList.add('strength-medium');
    else strengthBar.classList.add('strength-strong');
}

// Setup real-time validation
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
            // Allow only numbers and limit to 10 digits
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

// Validate signup form
function validateSignupForm() {
    const name = document.getElementById('name')?.value.trim();
    const contact = document.getElementById('contact')?.value.trim();
    const address = document.getElementById('address')?.value.trim();
    const password = document.getElementById('password')?.value;
    const farmType = document.getElementById('farm_type')?.value.trim();
    
    let isValid = true;
    
    if (name !== undefined && !validateName(name)) {
        updateFieldStatus(document.getElementById('name'), false, 'nameHint', 'Invalid name format');
        isValid = false;
    }
    
    if (contact !== undefined && !validateContact(contact)) {
        updateFieldStatus(document.getElementById('contact'), false, 'contactHint', 'Must be exactly 10 digits');
        isValid = false;
    }
    
    if (address !== undefined && !validateAddress(address)) {
        updateFieldStatus(document.getElementById('address'), false, 'addressHint', 'Address too short (min 10 characters)');
        isValid = false;
    }
    
    if (farmType !== undefined && !validateFarmType(farmType)) {
        updateFieldStatus(document.getElementById('farm_type'), false, 'farmTypeHint', 'Farm type required (2-100 characters)');
        isValid = false;
    }
    
    if (password !== undefined && !validatePassword(password)) {
        updateFieldStatus(document.getElementById('password'), false, 'passwordHint', 'Password does not meet requirements');
        isValid = false;
    }
    
    return isValid;
}

// Validate login form
function validateLoginForm() {
    const name = document.getElementById('name')?.value.trim();
    const password = document.getElementById('password')?.value;
    
    if (!name || name.length < 2) {
        alert('Please enter your name');
        return false;
    }
    
    if (!password || password.length < 6) {
        alert('Please enter your password');
        return false;
    }
    
    return true;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    setupValidation();
});