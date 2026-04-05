// Login form functionality
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.querySelector('.login-btn');
    const btnLoader = document.getElementById('btnLoader');

    // Form validation
    function validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function validatePassword(password) {
        return password.length >= 6;
    }

    function showFieldError(input, message) {
        const wrapper = input.closest('.input-wrapper');
        const formGroup = input.closest('.form-group');
        
        // Remove existing error
        const existingError = formGroup.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Add error state with animation
        wrapper.classList.add('error');
        wrapper.classList.remove('success');
        
        // Add error message with fade-in effect
        const errorElement = document.createElement('span');
        errorElement.className = 'error-message';
        errorElement.textContent = message;
        errorElement.style.opacity = '0';
        errorElement.style.transform = 'translateY(-10px)';
        errorElement.style.transition = 'all 0.3s ease';
        
        formGroup.appendChild(errorElement);
        
        // Trigger animation
        setTimeout(() => {
            errorElement.style.opacity = '1';
            errorElement.style.transform = 'translateY(0)';
        }, 50);
    }

    function showFieldSuccess(input) {
        const wrapper = input.closest('.input-wrapper');
        const formGroup = input.closest('.form-group');
        
        // Remove existing error
        const existingError = formGroup.querySelector('.error-message');
        if (existingError) {
            existingError.style.opacity = '0';
            existingError.style.transform = 'translateY(-10px)';
            setTimeout(() => existingError.remove(), 300);
        }
        
        // Add success state with smooth transition
        wrapper.classList.add('success');
        wrapper.classList.remove('error');
    }

    function clearFieldState(input) {
        const wrapper = input.closest('.input-wrapper');
        const formGroup = input.closest('.form-group');
        
        const existingError = formGroup.querySelector('.error-message');
        if (existingError) {
            existingError.style.opacity = '0';
            existingError.style.transform = 'translateY(-10px)';
            setTimeout(() => existingError.remove(), 300);
        }
        
        wrapper.classList.remove('error', 'success');
    }

    // Real-time validation with enhanced UX
    emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email === '') {
            clearFieldState(this);
        } else if (!validateEmail(email)) {
            showFieldError(this, 'Please enter a valid email address');
        } else {
            showFieldSuccess(this);
        }
    });

    passwordInput.addEventListener('blur', function() {
        const password = this.value;
        if (password === '') {
            clearFieldState(this);
        } else if (!validatePassword(password)) {
            showFieldError(this, 'Password must be at least 6 characters long');
        } else {
            showFieldSuccess(this);
        }
    });

    // Clear errors on input with debouncing
    let emailTimeout, passwordTimeout;
    
    emailInput.addEventListener('input', function() {
        if (this.closest('.input-wrapper').classList.contains('error')) {
            clearTimeout(emailTimeout);
            emailTimeout = setTimeout(() => {
                clearFieldState(this);
            }, 500);
        }
    });

    passwordInput.addEventListener('input', function() {
        if (this.closest('.input-wrapper').classList.contains('error')) {
            clearTimeout(passwordTimeout);
            passwordTimeout = setTimeout(() => {
                clearFieldState(this);
            }, 500);
        }
    });

    // Form submission
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        let isValid = true;

        // Validate email
        if (!email) {
            showFieldError(emailInput, 'Email is required');
            isValid = false;
        } else if (!validateEmail(email)) {
            showFieldError(emailInput, 'Please enter a valid email address');
            isValid = false;
        } else {
            showFieldSuccess(emailInput);
        }

        // Validate password
        if (!password) {
            showFieldError(passwordInput, 'Password is required');
            isValid = false;
        } else if (!validatePassword(password)) {
            showFieldError(passwordInput, 'Password must be at least 6 characters long');
            isValid = false;
        } else {
            showFieldSuccess(passwordInput);
        }

        if (isValid) {
            // Show loading state with enhanced animation
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
            
            // Add ripple effect
            createRippleEffect(loginBtn, e);

            // Simulate API call
            setTimeout(() => {
                // Hide loading state
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;

                // Demo: Check for demo credentials
                if (email === 'demo@example.com' && password === 'demo123') {
                    showSuccessMessage('Login successful! Redirecting...');
                    setTimeout(() => {
                        // Redirect to dashboard (replace with your actual dashboard URL)
                        window.location.href = 'dashboard.html';
                    }, 1500);
                } else {
                    showErrorMessage('Invalid email or password. Try demo@example.com / demo123');
                }
            }, 2000);
        }
    });

    // Enhanced ripple effect for button
    function createRippleEffect(button, event) {
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        button.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Show success message
    function showSuccessMessage(message) {
        showMessage(message, 'success');
    }

    // Show error message
    function showErrorMessage(message) {
        showMessage(message, 'error');
    }

    // Enhanced message function with better animations
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessage = document.querySelector('.message-popup');
        if (existingMessage) {
            existingMessage.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => existingMessage.remove(), 300);
        }

        // Create message element
        const messageElement = document.createElement('div');
        messageElement.className = `message-popup ${type}`;
        messageElement.innerHTML = `
            <div class="message-content">
                <span class="message-icon">${type === 'success' ? '✅' : '❌'}</span>
                <span class="message-text">${message}</span>
                <button class="message-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Enhanced styles
        messageElement.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' 
                ? 'linear-gradient(135deg, rgba(76, 175, 80, 0.95), rgba(69, 160, 73, 0.95))' 
                : 'linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95))'};
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 
                0 8px 25px rgba(0, 0, 0, 0.15),
                0 4px 10px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 14px;
            font-weight: 500;
            max-width: 350px;
            min-width: 250px;
        `;

        // Style message content
        const messageContent = messageElement.querySelector('.message-content');
        messageContent.style.cssText = `
            display: flex;
            align-items: center;
            gap: 12px;
        `;

        // Style message icon
        const messageIcon = messageElement.querySelector('.message-icon');
        messageIcon.style.cssText = `
            font-size: 16px;
            flex-shrink: 0;
        `;

        // Style message text
        const messageText = messageElement.querySelector('.message-text');
        messageText.style.cssText = `
            flex: 1;
            line-height: 1.4;
        `;

        // Style close button
        const closeButton = messageElement.querySelector('.message-close');
        closeButton.style.cssText = `
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
            flex-shrink: 0;
        `;

        // Add hover effect for close button
        closeButton.addEventListener('mouseenter', function() {
            this.style.background = 'rgba(255, 255, 255, 0.2)';
            this.style.color = 'rgba(255, 255, 255, 1)';
        });

        closeButton.addEventListener('mouseleave', function() {
            this.style.background = 'none';
            this.style.color = 'rgba(255, 255, 255, 0.8)';
        });

        // Append to body
        document.body.appendChild(messageElement);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (document.body.contains(messageElement)) {
                messageElement.style.animation = 'slideOutRight 0.3s ease-in forwards';
                setTimeout(() => {
                    if (document.body.contains(messageElement)) {
                        messageElement.remove();
                    }
                }, 300);
            }
        }, 5000);
    }

    // Add CSS animations for message popup
    if (!document.querySelector('#message-animations-styles')) {
        const style = document.createElement('style');
        style.id = 'message-animations-styles';
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100%);
                }
            }

            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }

            .login-btn {
                position: relative;
                overflow: hidden;
            }

            .input-wrapper.error {
                border-color: #ef4444 !important;
                box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1) !important;
            }

            .input-wrapper.success {
                border-color: #10b981 !important;
                box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1) !important;
            }

            .error-message {
                color: #ef4444;
                font-size: 12px;
                margin-top: 4px;
                display: block;
            }

            .login-btn.loading {
                pointer-events: none;
                opacity: 0.8;
            }

            .login-btn.loading::after {
                content: '';
                position: absolute;
                width: 16px;
                height: 16px;
                margin: auto;
                border: 2px solid transparent;
                border-top-color: #ffffff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Enter key to submit form when focused on inputs
        if (e.key === 'Enter' && (document.activeElement === emailInput || document.activeElement === passwordInput)) {
            loginForm.dispatchEvent(new Event('submit'));
        }
        
        // Escape key to clear form
        if (e.key === 'Escape') {
            emailInput.value = '';
            passwordInput.value = '';
            clearFieldState(emailInput);
            clearFieldState(passwordInput);
            emailInput.focus();
        }
    });

    // Initialize form - focus on email input
    emailInput.focus();
});