<?php
// login.php - Halaman login dengan verifikasi WhatsApp
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

session_start();

// Konfigurasi Fonnte API
define('FONNTE_API_URL', 'https://api.fonnte.com/send');
define('FONNTE_TOKEN', 'QviUn2AwKxbA3QwdLidC'); // Ganti dengan token Fonnte Anda

// Fungsi untuk mengirim pesan WhatsApp
function sendWhatsAppMessage($phone, $message) {
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => FONNTE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62',
        ),
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . FONNTE_TOKEN
        ),
    ));
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    return [
        'success' => $httpCode == 200,
        'response' => json_decode($response, true)
    ];
}

// Fungsi untuk generate kode verifikasi
function generateVerificationCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

// Proses AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        try {
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validasi input
            if (empty($email) || empty($password)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email dan password wajib diisi'
                ]);
                exit;
            }
            
            // Validasi format email
            if (!isValidEmail($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Format email tidak valid'
                ]);
                exit;
            }
            
            // Koneksi database
            $pdo = getDBConnection();
            
            // Cari user berdasarkan email
            $stmt = $pdo->prepare("SELECT id, full_name, email, phone, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email tidak terdaftar'
                ]);
                exit;
            }
            
            // Verifikasi password
            if (!password_verify($password, $user['password_hash'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Password salah'
                ]);
                exit;
            }
            
            // Generate kode verifikasi
            $verificationCode = generateVerificationCode();
            
            // Simpan kode verifikasi ke session
            $_SESSION['verification_code'] = $verificationCode;
            $_SESSION['user_data'] = [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone']
            ];
            $_SESSION['verification_time'] = time();
            
            // Format nomor telepon untuk WhatsApp
            $phone = $user['phone'];
            if (substr($phone, 0, 1) === '0') {
                $phone = '62' . substr($phone, 1);
            }
            
            // Pesan WhatsApp
            $message = "Kode verifikasi login Jalanyuk Anda: *{$verificationCode}*\n\nKode ini berlaku selama 5 menit.\nJangan bagikan kode ini kepada siapapun.";
            
            // Kirim pesan WhatsApp
            $whatsappResult = sendWhatsAppMessage($phone, $message);
            
            if ($whatsappResult['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Kode verifikasi telah dikirim ke WhatsApp Anda',
                    'phone_masked' => substr($user['phone'], 0, 4) . '****' . substr($user['phone'], -3)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal mengirim kode verifikasi. Silakan coba lagi.'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ]);
        }
        
    } elseif ($action === 'verify') {
        try {
            $code = sanitizeInput($_POST['code'] ?? '');
            
            if (empty($code)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Kode verifikasi wajib diisi'
                ]);
                exit;
            }
            
            // Cek apakah ada session verifikasi
            if (!isset($_SESSION['verification_code']) || !isset($_SESSION['user_data'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Session verifikasi tidak valid'
                ]);
                exit;
            }
            
            // Cek apakah kode masih berlaku (5 menit)
            if (time() - $_SESSION['verification_time'] > 300) {
                unset($_SESSION['verification_code']);
                unset($_SESSION['user_data']);
                unset($_SESSION['verification_time']);
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Kode verifikasi sudah kedaluwarsa'
                ]);
                exit;
            }
            
            // Verifikasi kode
            if ($code !== $_SESSION['verification_code']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Kode verifikasi salah'
                ]);
                exit;
            }
            
            // Login berhasil
            $_SESSION['user_id'] = $_SESSION['user_data']['id'];
            $_SESSION['user_name'] = $_SESSION['user_data']['full_name'];
            $_SESSION['user_email'] = $_SESSION['user_data']['email'];
            $_SESSION['logged_in'] = true;
            
            // Tentukan halaman redirect berdasarkan nama user
            $redirectUrl = 'index.php'; // Default redirect
            
            // Jika user bernama "admin", redirect ke admin.php
            if (strtolower($_SESSION['user_data']['full_name']) === 'admin') {
                $redirectUrl = 'admin.php';
                $_SESSION['is_admin'] = true; // Set flag admin untuk keamanan tambahan
            }
            
            // Hapus data verifikasi
            unset($_SESSION['verification_code']);
            unset($_SESSION['user_data']);
            unset($_SESSION['verification_time']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil!',
                'redirect_url' => $redirectUrl
            ]);
            
        } catch (Exception $e) {
            error_log("Verification error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ]);
        }
        
    } elseif ($action === 'resend') {
        try {
            // Cek apakah ada session user data
            if (!isset($_SESSION['user_data'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Session tidak valid'
                ]);
                exit;
            }
            
            // Generate kode verifikasi baru
            $verificationCode = generateVerificationCode();
            
            // Update session
            $_SESSION['verification_code'] = $verificationCode;
            $_SESSION['verification_time'] = time();
            
            // Format nomor telepon untuk WhatsApp
            $phone = $_SESSION['user_data']['phone'];
            if (substr($phone, 0, 1) === '0') {
                $phone = '62' . substr($phone, 1);
            }
            
            // Pesan WhatsApp
            $message = "Kode verifikasi login FinanceDash Anda: *{$verificationCode}*\n\nKode ini berlaku selama 5 menit.\nJangan bagikan kode ini kepada siapapun.";
            
            // Kirim pesan WhatsApp
            $whatsappResult = sendWhatsAppMessage($phone, $message);
            
            if ($whatsappResult['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Kode verifikasi baru telah dikirim'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal mengirim kode verifikasi. Silakan coba lagi.'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Resend error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ]);
        }
    }
    
    exit;
}

// Jika user sudah login, redirect ke halaman yang sesuai
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Cek apakah user adalah admin
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Financial Dashboard</title>
    <link rel="stylesheet" href="css/login1.css">
    <style>
/* Modal CSS */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .verification-modal {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.8);
            transition: transform 0.3s ease;
            position: relative;
        }

        .modal-overlay.active .verification-modal {
            transform: scale(1);
        }

        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .modal-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #000000 0%, #333333 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 30px;
    color: white;
    
    /* Tambahan untuk WhatsApp SVG */
    background-image: url('images/whatsapp.svg');
    background-size: 50px 50px; /* Ukuran SVG lebih kecil dari container */
    background-repeat: no-repeat;
    background-position: center;
}

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .modal-subtitle {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .phone-display {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 600;
            color: #333;
            display: inline-block;
            margin-top: 5px;
        }

        .verification-form {
            margin-bottom: 20px;
        }

        /* Individual Code Input Styles */
        .code-inputs {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 25px;
        }

        .code-input {
            width: 50px;
            height: 50px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 20px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            background: white;
            outline: none;
        }

        .code-input:focus {
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
            transform: scale(1.05);
        }

        .code-input.filled {
            border-color: #000000;
            background: #f5f5f5;
            color: #000000;
        }

        .code-input.error {
            border-color: #e74c3c;
            background-color: #fdf2f2;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .verify-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #000000 0%, #333333 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .verify-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .verify-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            background: #ccc;
        }

        .verify-btn .btn-loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .verify-btn.loading .btn-text {
            display: none;
        }

        .verify-btn.loading .btn-loader {
            display: inline-block;
        }

        .modal-footer {
            text-align: center;
            margin-top: 25px;
        }

        .resend-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #666;
            font-size: 14px;
        }

        .resend-btn {
            color: #000000;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .resend-btn:hover {
            color: #333333;
        }

        .resend-btn.disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .countdown {
            color: #e74c3c;
            font-weight: 600;
        }

        .error-message {
            color: #e74c3c;
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
            display: none;
            padding: 10px;
            background: #fdf2f2;
            border-radius: 8px;
            border: 1px solid #e74c3c;
        }

        .success-message {
            color: #000000;
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
            display: none;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 8px;
            border: 1px solid #000000;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: #f0f0f0;
            color: #333;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .verification-modal {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .modal-title {
                font-size: 20px;
            }
            
            .code-inputs {
                gap: 8px;
            }
            
            .code-input {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .code-inputs {
                gap: 6px;
            }
            
            .code-input {
                width: 35px;
                height: 35px;
                font-size: 16px;
            }
        }

    </style>
</head>
<body>
  <div class="login-container">
        <div class="background-pattern">
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
                <div class="shape shape-5"></div>
                <div class="shape shape-6"></div>
            </div>
            <div class="grid-overlay"></div>
        </div>
        
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <div class="logo-icon" style="
    width: 50px;
    height: 50px;
    background-image: url('images/login.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
"></div>

                    <span class="logo-text">Jalanyuk</span>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>

            <form class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" required>
                        <span class="input-icon"></span>
                    </div>
                    <span class="error-message" id="emailError"></span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" required>
                        <span class="input-icon toggle-password" onclick="togglePassword()"></span>
                    </div>
                    <span class="error-message" id="passwordError"></span>
                </div>

                <div class="form-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" id="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <div class="btn-loader" id="btnLoader"></div>
                </button>
            </form>

            <div class="login-footer">
                <p>Don't have an account? <a href="register.php" class="signup-link">Sign up</a></p>
            </div>
        </div>

        <div class="features-preview">
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Financial Insights</h3>
                <p>Track your money flow with detailed analytics</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💳</div>
                <h3>Easy Payments</h3>
                <p>Send and receive money effortlessly</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Platform</h3>
                <p>Your financial data is protected</p>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="verificationModal">
        <div class="verification-modal">
            <button class="close-modal" onclick="closeVerificationModal()">&times;</button>
            
            <div class="modal-header">
                <div class="modal-icon" style="width: 60    px; height: 60px; background-image: url('images/whatsapp.svg'); background-size: contain; background-repeat: no-repeat; background-position: center;"></div>

                <h3 class="modal-title">Verify Your Phone</h3>
                <p class="modal-subtitle">
                    We've sent a 6-digit verification code to:<br>
                    <span class="phone-display" id="phoneDisplay">+62 812-3456-7890</span>
                </p>
            </div>

            <form class="verification-form" id="verificationForm">
                <div class="code-inputs">
                    <input type="text" class="code-input" maxlength="1" data-index="0">
                    <input type="text" class="code-input" maxlength="1" data-index="1">
                    <input type="text" class="code-input" maxlength="1" data-index="2">
                    <input type="text" class="code-input" maxlength="1" data-index="3">
                    <input type="text" class="code-input" maxlength="1" data-index="4">
                    <input type="text" class="code-input" maxlength="1" data-index="5">
                </div>
                
                <button type="submit" class="verify-btn" id="verifyBtn" disabled>
                    <span class="btn-text">Verify Code</span>
                    <div class="btn-loader"></div>
                </button>
            </form>

            <div class="error-message" id="verificationError"></div>
            <div class="success-message" id="verificationSuccess"></div>

            <div class="modal-footer">
                <div class="resend-section">
                    <span>Didn't receive the code?</span>
                    <a href="#" class="resend-btn" id="resendBtn" onclick="resendCode()">Resend</a>
                    <span class="countdown" id="countdown" style="display: none;"></span>
                </div>
            </div>
        </div>
    </div>
        
    <script>
// Global variables
let countdownTimer;
let resendTimeout = 60; // 60 seconds
const codeInputs = document.querySelectorAll('.code-input');

// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.querySelector('.toggle-password');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.style.opacity = '0.7';
    } else {
        passwordInput.type = 'password';
        toggleIcon.style.opacity = '1';
    }
}

// Individual code input functionality
function initializeCodeInputs() {
    codeInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const value = e.target.value;
            
            // Only allow numbers
            if (!/^\d*$/.test(value)) {
                e.target.value = '';
                return;
            }

            // Add filled class
            if (value) {
                e.target.classList.add('filled');
                // Move to next input
                if (index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
            } else {
                e.target.classList.remove('filled');
            }

            // Remove error class when user starts typing
            e.target.classList.remove('error');
            hideMessages();
            
            // Check if all inputs are filled
            checkAllInputsFilled();
        });

        input.addEventListener('keydown', (e) => {
            // Handle backspace
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                codeInputs[index - 1].focus();
                codeInputs[index - 1].value = '';
                codeInputs[index - 1].classList.remove('filled');
                checkAllInputsFilled();
            }

            // Handle paste
            if (e.key === 'v' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                navigator.clipboard.readText().then(text => {
                    const numbers = text.replace(/\D/g, '').slice(0, 6);
                    fillCodeInputs(numbers);
                });
            }
        });

        input.addEventListener('focus', () => {
            input.select();
        });
    });
}

// Fill code inputs with provided code
function fillCodeInputs(code) {
    codeInputs.forEach((input, index) => {
        if (code[index]) {
            input.value = code[index];
            input.classList.add('filled');
        } else {
            input.value = '';
            input.classList.remove('filled');
        }
        input.classList.remove('error');
    });
    checkAllInputsFilled();
}

// Check if all inputs are filled
function checkAllInputsFilled() {
    const verifyBtn = document.getElementById('verifyBtn');
    const allFilled = Array.from(codeInputs).every(input => input.value);
    
    if (verifyBtn) {
        verifyBtn.disabled = !allFilled;
    }
}

// Get verification code from all inputs
function getVerificationCode() {
    return Array.from(codeInputs).map(input => input.value).join('');
}

// Clear all code inputs
function clearCodeInputs() {
    fillCodeInputs('');
    hideMessages();
}

// Show verification modal
function showVerificationModal(phoneNumber) {
    document.getElementById('phoneDisplay').textContent = phoneNumber;
    document.getElementById('verificationModal').classList.add('active');
    startResendCountdown();
    
    // Focus first input after modal animation
    setTimeout(() => {
        if (codeInputs.length > 0) {
            codeInputs[0].focus();
        }
    }, 300);
}

// Close verification modal
function closeVerificationModal() {
    document.getElementById('verificationModal').classList.remove('active');
    
    // Reset form after modal animation
    setTimeout(() => {
        clearCodeInputs();
        const verifyBtn = document.getElementById('verifyBtn');
        if (verifyBtn) {
            verifyBtn.classList.remove('loading');
            verifyBtn.disabled = true;
        }
    }, 300);
    
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }
}

// Start resend countdown
function startResendCountdown() {
    const resendBtn = document.getElementById('resendBtn');
    const countdown = document.getElementById('countdown');
    
    resendBtn.classList.add('disabled');
    resendBtn.style.display = 'none';
    countdown.style.display = 'inline';
    
    let timeLeft = resendTimeout;
    countdown.textContent = `(${timeLeft}s)`;
    
    countdownTimer = setInterval(() => {
        timeLeft--;
        countdown.textContent = `(${timeLeft}s)`;
        
        if (timeLeft <= 0) {
            clearInterval(countdownTimer);
            resendBtn.classList.remove('disabled');
            resendBtn.style.display = 'inline';
            countdown.style.display = 'none';
        }
    }, 1000);
}

// Resend verification code
function resendCode() {
    const resendBtn = document.getElementById('resendBtn');
    
    if (resendBtn.classList.contains('disabled')) {
        return;
    }
    
    // Show loading state
    resendBtn.textContent = 'Sending...';
    resendBtn.classList.add('disabled');
    
    fetch('login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=resend'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            startResendCountdown();
        } else {
            showErrorMessage(data.message);
            resendBtn.classList.remove('disabled');
        }
        resendBtn.textContent = 'Resend';
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Network error occurred');
        resendBtn.textContent = 'Resend';
        resendBtn.classList.remove('disabled');
    });
}

// Show error message
function showErrorMessage(message) {
    const errorDiv = document.getElementById('verificationError');
    const successDiv = document.getElementById('verificationSuccess');
    
    successDiv.style.display = 'none';
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    
    // Add error class to all inputs
    codeInputs.forEach(input => {
        input.classList.add('error');
    });
    
    // Remove error class after animation
    setTimeout(() => {
        errorDiv.style.display = 'none';
        codeInputs.forEach(input => {
            input.classList.remove('error');
        });
    }, 5000);
}

// Show success message
function showSuccessMessage(message) {
    const errorDiv = document.getElementById('verificationError');
    const successDiv = document.getElementById('verificationSuccess');
    
    errorDiv.style.display = 'none';
    successDiv.textContent = message;
    successDiv.style.display = 'block';
    
    setTimeout(() => {
        successDiv.style.display = 'none';
    }, 3000);
}

// Hide messages
function hideMessages() {
    document.getElementById('verificationError').style.display = 'none';
    document.getElementById('verificationSuccess').style.display = 'none';
}

// Handle login form submission
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const loginBtn = document.getElementById('loginBtn');
    const btnText = loginBtn.querySelector('.btn-text');
    const btnLoader = loginBtn.querySelector('.btn-loader');
    
    // Show loading state
    loginBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-block';
    
    // Clear previous errors
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    if (emailError) emailError.textContent = '';
    if (passwordError) passwordError.textContent = '';
    
    const formData = new FormData(this);
    formData.append('action', 'login');
    
    fetch('login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showVerificationModal(data.phone_masked);
        } else {
            // Show error message
            if (data.message) {
                alert(data.message);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    })
    .finally(() => {
        // Reset button state
        loginBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
    });
});

// Handle verification form submission
document.getElementById('verificationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const verifyBtn = document.getElementById('verifyBtn');
    const btnText = verifyBtn.querySelector('.btn-text');
    const btnLoader = verifyBtn.querySelector('.btn-loader');
    const code = getVerificationCode();
    
    // Validate input
    if (code.length !== 6) {
        showErrorMessage('Please enter all 6 digits');
        return;
    }
    
    // Show loading state
    verifyBtn.disabled = true;
    verifyBtn.classList.add('loading');
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-block';
    hideMessages();
    
    const formData = new FormData();
    formData.append('action', 'verify');
    formData.append('code', code);
    
    fetch('login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            setTimeout(() => {
                window.location.href = data.redirect_url;
            }, 1500);
        } else {
            showErrorMessage(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Network error occurred');
    })
    .finally(() => {
        // Reset button state
        verifyBtn.disabled = false;
        verifyBtn.classList.remove('loading');
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
    });
});

// Close modal when clicking outside
document.getElementById('verificationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeVerificationModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVerificationModal();
    }
});

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCodeInputs();
    checkAllInputsFilled();
});
    </script>
</body>
</html>