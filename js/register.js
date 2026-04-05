// js/register.js - Complete unified JavaScript for register form

document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');
    const fileInput = document.getElementById('ktp');
    const fileDisplay = document.querySelector('.file-upload-display');
    const fileName = document.querySelector('.file-name');
    const fileSize = document.querySelector('.file-size');

    // Handle file upload display
    if (fileInput && fileDisplay && fileName && fileSize) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                const fileSizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                fileName.textContent = file.name;
                fileSize.textContent = `${fileSizeInMB} MB`;
                fileDisplay.classList.add('file-selected', 'has-file');
                
                // Validate file immediately
                if (!validateFileUpload(file)) {
                    clearFileInput();
                } else {
                    clearError('ktpError');
                    const ktpWrapper = fileInput.closest('.file-upload-wrapper');
                    if (ktpWrapper) {
                        ktpWrapper.classList.remove('error');
                        ktpWrapper.classList.add('success');
                    }
                }
            } else {
                clearFileInput();
            }
        });
    }

    // Handle form submission
    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Clear previous errors
            clearAllErrors();
            
            // Validate form
            if (!validateCompleteForm()) {
                scrollToFirstError();
                return;
            }
            
            // Show loading state
            setLoadingState(true);
            
            try {
                // Create FormData object
                const formData = new FormData(registerForm);
                
                // Debug: Log form data
                console.log('Submitting form data:');
                for (let pair of formData.entries()) {
                    if (pair[0] !== 'ktp') { // Don't log file
                        console.log(pair[0] + ': ' + pair[1]);
                    }
                }
                
                // Send data to server (change to register.php since it handles both form and processing)
                const response = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response');
                }
                
                const result = await response.json();
                console.log('Server response:', result);
                
                if (result.success) {
                    // Success - show success message and redirect
                    showSuccessMessage(result.message || 'Registration successful! Your account has been created.');
                    setTimeout(() => {
                        // Redirect to login page
                        window.location.href = 'login.php';
                    }, 3000);
                } else {
                    // Show errors
                    if (result.errors) {
                        displayServerErrors(result.errors);
                        scrollToFirstError();
                    } else {
                        showErrorNotification(result.message || 'Registration failed. Please try again.');
                    }
                }
            } catch (error) {
                console.error('Registration error:', error);
                showErrorNotification('Network error occurred. Please check your connection and try again.');
            } finally {
                setLoadingState(false);
            }
        });
    }

    // Real-time validation setup
    setupRealTimeValidation();

    // Phone number formatting
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 4) {
                    value = value;
                } else if (value.length <= 8) {
                    value = value.slice(0, 4) + '-' + value.slice(4);
                } else {
                    value = value.slice(0, 4) + '-' + value.slice(4, 8) + '-' + value.slice(8, 12);
                }
            }
            e.target.value = value;
        });
    }
});

// Function to toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const icon = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.textContent = '🙈';
    } else {
        input.type = 'password';
        if (icon) icon.textContent = '👁️';
    }
}

// Function to validate file upload
function validateFileUpload(file) {
    if (!file) return false;
    
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!validTypes.includes(file.type)) {
        showError('ktpError', 'Please upload a valid image (JPG, PNG) or PDF file');
        return false;
    }
    
    if (file.size > maxSize) {
        showError('ktpError', 'File size must be less than 5MB');
        return false;
    }
    
    return true;
}

// Function to clear file input
function clearFileInput() {
    const fileInput = document.getElementById('ktp');
    const fileName = document.querySelector('.file-name');
    const fileSize = document.querySelector('.file-size');
    const fileDisplay = document.querySelector('.file-upload-display');
    
    if (fileInput) fileInput.value = '';
    if (fileName) fileName.textContent = 'Click to upload KTP/ID Card';
    if (fileSize) fileSize.textContent = 'PNG, JPG, PDF up to 5MB';
    if (fileDisplay) fileDisplay.classList.remove('file-selected', 'has-file');
}

// Function to validate complete form
function validateCompleteForm() {
    let isValid = true;
    const requiredFields = ['fullName', 'email', 'phone', 'birthDate', 'address', 'password'];
    
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && !validateField(field)) {
            isValid = false;
        }
    });

    // Validate KTP file (optional but validate if present)
    const ktpInput = document.getElementById('ktp');
    if (ktpInput && ktpInput.files && ktpInput.files[0]) {
        if (!validateFileUpload(ktpInput.files[0])) {
            isValid = false;
        }
    }

    // Check terms and conditions
    const termsCheckbox = document.getElementById('terms');
    if (termsCheckbox && !termsCheckbox.checked) {
        showError('termsError', 'You must agree to the terms and conditions');
        isValid = false;
    }

    return isValid;
}

// Function to validate individual field
function validateField(field) {
    if (!field) return false;
    
    const fieldId = field.id;
    const value = field.value.trim();
    const wrapper = field.closest('.input-wrapper') || field.closest('.file-upload-wrapper');
    
    clearError(fieldId + 'Error');
    if (wrapper) {
        wrapper.classList.remove('error', 'success');
    }

    switch (fieldId) {
        case 'fullName':
            if (!value) {
                showError(fieldId + 'Error', 'Full name is required');
                if (wrapper) wrapper.classList.add('error');
                return false;
            } else if (value.length < 2) {
                showError(fieldId + 'Error', 'Full name must be at least 2 characters');
                if (wrapper) wrapper.classList.add('error');
                return false;
            }
            break;

        case 'email':
            if (!value) {
                showError(fieldId + 'Error', 'Email is required');
                if (wrapper) wrapper.classList.add('error');
                return false;
            } else if (!isValidEmail(value)) {
                showError(fieldId + 'Error', 'Please enter a valid email address');
                if (wrapper) wrapper.classList.add('error');
                return false;
            }
            break;

        case 'phone':
            if (!value) {
                showError(fieldId + 'Error', 'Phone number is required');
                if (wrapper) wrapper.classList.add('error');
                return false;
            } else if (!isValidPhone(value)) {
                showError(fieldId + 'Error', 'Invalid phone number format (example: 08123456789)');
                if (wrapper) wrapper.classList.add('error');
                return false;
            }
            break;

        case 'birthDate':
            if (!value) {
                showError(fieldId + 'Error', 'Date of birth is required');
                if (wrapper) wrapper.classList.add('error');
                return false;
            } else if (!isValidBirthDate(value)) {
                return false;
            }
            break;

        case 'address':
            if (!value) {
                showError(fieldId + 'Error', 'Address is required');
                if (wrapper) wrapper.classList.add('error');
                return false;
            } else if (value.length < 10) {
                showError(fieldId + 'Error', 'Please enter a complete address (minimum 10 characters)');
                if (wrapper) wrapper.classList.add('error');
                return false;
            }
            break;

        case 'password':
            if (!value) {
                showError(fieldId + 'Error', 'Password is required');
                if (wrapper) wrapper.classList.add('error');
                return false;
            } else if (!isValidPassword(value)) {
                return false;
            }
            break;
    }

    if (wrapper) wrapper.classList.add('success');
    return true;
}

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Phone validation helper  
function isValidPhone(phone) {
    const phoneRegex = /^(08|628|\+628)[0-9]{8,12}$/;
    const cleanPhone = phone.replace(/[^0-9+]/g, '');
    return phoneRegex.test(cleanPhone);
}

// Birth date validation helper
function isValidBirthDate(birthDate) {
    const birthDateTime = new Date(birthDate);
    const today = new Date();
    const age = today.getFullYear() - birthDateTime.getFullYear();
    const monthDiff = today.getMonth() - birthDateTime.getMonth();
    const dayDiff = today.getDate() - birthDateTime.getDate();
    
    let actualAge = age;
    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
        actualAge--;
    }
    
    const fieldId = 'birthDate';
    const wrapper = document.getElementById(fieldId)?.closest('.input-wrapper');
    
    if (actualAge < 17) {
        showError(fieldId + 'Error', 'You must be at least 17 years old');
        if (wrapper) wrapper.classList.add('error');
        return false;
    } else if (actualAge > 100) {
        showError(fieldId + 'Error', 'Please enter a valid date of birth');
        if (wrapper) wrapper.classList.add('error');
        return false;
    }
    
    return true;
}

// Password validation helper
function isValidPassword(password) {
    const fieldId = 'password';
    const wrapper = document.getElementById(fieldId)?.closest('.input-wrapper');
    
    if (password.length < 8) {
        showError(fieldId + 'Error', 'Password must be at least 8 characters');
        if (wrapper) wrapper.classList.add('error');
        return false;
    }
    
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
    if (!passwordRegex.test(password)) {
        showError(fieldId + 'Error', 'Password must contain uppercase, lowercase, and number');
        if (wrapper) wrapper.classList.add('error');
        return false;
    }
    
    return true;
}

// Function to setup real-time validation
function setupRealTimeValidation() {
    const form = document.getElementById('registerForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, textarea');
    
    inputs.forEach(input => {
        // Validation on blur
        input.addEventListener('blur', function() {
            if (this.value.trim()) {
                validateField(this);
            }
        });

        // Clear errors on input
        input.addEventListener('input', function() {
            clearError(this.id + 'Error');
            const wrapper = this.closest('.input-wrapper') || this.closest('.file-upload-wrapper');
            if (wrapper) {
                wrapper.classList.remove('error', 'success');
            }
        });
    });

    // Special handling for password field
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            if (this.value.length >= 8) {
                validateField(this);
            }
        });
    }
}

// Function to clear all error messages
function clearAllErrors() {
    const errorElements = document.querySelectorAll('.error-message');
    errorElements.forEach(element => {
        element.textContent = '';
        element.style.display = 'none';
    });
    
    const wrappers = document.querySelectorAll('.input-wrapper, .file-upload-wrapper');
    wrappers.forEach(wrapper => {
        wrapper.classList.remove('error', 'success');
    });
}

// Function to show individual field error
function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

// Function to clear individual field error
function clearError(elementId) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

// Function to display server validation errors
function displayServerErrors(errors) {
    Object.keys(errors).forEach(fieldName => {
        const errorElement = document.getElementById(fieldName + 'Error');
        const wrapper = document.querySelector(`#${fieldName}`)?.closest('.input-wrapper') || 
                       document.querySelector(`#${fieldName}`)?.closest('.file-upload-wrapper');
        
        if (errorElement) {
            errorElement.textContent = errors[fieldName];
            errorElement.style.display = 'block';
        }
        
        if (wrapper) {
            wrapper.classList.add('error');
        }
    });
}

// Function to show success message with modern overlay
function showSuccessMessage(message = 'Registration successful! Your account has been created.') {
    // Create success overlay
    const successOverlay = document.createElement('div');
    successOverlay.className = 'success-overlay';
    successOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        animation: fadeIn 0.3s ease;
    `;

    const successCard = document.createElement('div');
    successCard.style.cssText = `
        background: white;
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        max-width: 400px;
        margin: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.5s ease;
    `;

    successCard.innerHTML = `
        <div style="font-size: 48px; color: #4CAF50; margin-bottom: 20px;">✅</div>
        <h3 style="color: #1e293b; margin-bottom: 10px; font-size: 24px;">Registration Successful!</h3>
        <p style="color: #64748b; margin-bottom: 30px;">${message}</p>
        <button onclick="this.closest('.success-overlay').remove()" style="
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            Continue
        </button>
    `;

    successOverlay.appendChild(successCard);
    document.body.appendChild(successOverlay);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (successOverlay.parentNode) {
            successOverlay.remove();
        }
    }, 5000);
}

// Function to show error notification
function showErrorNotification(message) {
    // Create error notification
    const notification = document.createElement('div');
    notification.className = 'notification error';
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">❌</span>
            <span class="notification-message">${message}</span>
        </div>
    `;
    
    // Add notification styles if not already present
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                z-index: 10000;
                animation: slideInRight 0.3s ease;
            }
            .notification.error {
                border-left: 4px solid #ef4444;
            }
            .notification.success {
                border-left: 4px solid #22c55e;
            }
            .notification-content {
                display: flex;
                align-items: center;
                padding: 16px;
                gap: 12px;
            }
            .notification-icon {
                font-size: 18px;
            }
            .notification-message {
                color: #374151;
                font-weight: 500;
            }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Function to set loading state
function setLoadingState(isLoading) {
    const registerBtn = document.getElementById('registerBtn');
    if (!registerBtn) return;
    
    const btnText = registerBtn.querySelector('.btn-text') || registerBtn;
    const btnLoader = registerBtn.querySelector('.btn-loader');
    
    if (isLoading) {
        registerBtn.disabled = true;
        registerBtn.classList.add('loading');
        btnText.textContent = 'Creating Account...';
        if (btnLoader) btnLoader.style.display = 'block';
    } else {
        registerBtn.disabled = false;
        registerBtn.classList.remove('loading');
        btnText.textContent = 'Create Account';
        if (btnLoader) btnLoader.style.display = 'none';
    }
}

// Function to scroll to first error
function scrollToFirstError() {
    const firstError = document.querySelector('.error-message:not([style*="display: none"])');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Add CSS animations if not already present
if (!document.querySelector('#register-animations')) {
    const animations = document.createElement('style');
    animations.id = 'register-animations';
    animations.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    `;
    document.head.appendChild(animations);
}