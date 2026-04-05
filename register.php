<?php
// register.php - Halaman registrasi dengan proses
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Proses registrasi jika ada POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Ambil dan sanitasi data dari form
        $fullName = sanitizeInput($_POST['fullName'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $birthDate = sanitizeInput($_POST['birthDate'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $terms = isset($_POST['terms']);

        // Debug: Log received data (tanpa password)
        error_log("Received registration data: " . json_encode([
            'fullName' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'birthDate' => $birthDate,
            'address' => substr($address, 0, 50) . '...',
            'terms' => $terms
        ]));

        // Array untuk menyimpan error validasi
        $errors = [];

        // Validasi nama lengkap
        if (empty($fullName)) {
            $errors['fullName'] = 'Nama lengkap wajib diisi';
        } elseif (strlen($fullName) < 2) {
            $errors['fullName'] = 'Nama lengkap minimal 2 karakter';
        } elseif (strlen($fullName) > 255) {
            $errors['fullName'] = 'Nama lengkap maksimal 255 karakter';
        }

        // Validasi email
        if (empty($email)) {
            $errors['email'] = 'Email wajib diisi';
        } elseif (!isValidEmail($email)) {
            $errors['email'] = 'Format email tidak valid';
        } elseif (strlen($email) > 255) {
            $errors['email'] = 'Email maksimal 255 karakter';
        }

        // Validasi nomor telepon
        if (empty($phone)) {
            $errors['phone'] = 'Nomor telepon wajib diisi';
        } elseif (!isValidPhoneNumber($phone)) {
            $errors['phone'] = 'Format nomor telepon tidak valid (contoh: 08123456789)';
        }

        // Validasi tanggal lahir
        if (empty($birthDate)) {
            $errors['birthDate'] = 'Tanggal lahir wajib diisi';
        } else {
            $birthDateTime = DateTime::createFromFormat('Y-m-d', $birthDate);
            if (!$birthDateTime || $birthDateTime->format('Y-m-d') !== $birthDate) {
                $errors['birthDate'] = 'Format tanggal tidak valid';
            } else {
                $today = new DateTime();
                $age = $today->diff($birthDateTime)->y;
                
                if ($age < 17) {
                    $errors['birthDate'] = 'Usia minimal 17 tahun';
                } elseif ($age > 100) {
                    $errors['birthDate'] = 'Usia tidak valid';
                }
            }
        }

        // Validasi alamat
        if (empty($address)) {
            $errors['address'] = 'Alamat wajib diisi';
        } elseif (strlen($address) < 10) {
            $errors['address'] = 'Alamat minimal 10 karakter';
        } elseif (strlen($address) > 1000) {
            $errors['address'] = 'Alamat maksimal 1000 karakter';
        }

        // Validasi password
        if (empty($password)) {
            $errors['password'] = 'Password wajib diisi';
        } elseif (!isValidPassword($password)) {
            $errors['password'] = 'Password minimal 8 karakter, harus mengandung huruf besar, huruf kecil, dan angka';
        }

        // Validasi terms
        if (!$terms) {
            $errors['terms'] = 'Anda harus menyetujui syarat dan ketentuan';
        }

        // Proses file upload KTP (optional)
        $ktpFileName = null;
        $ktpPath = null;
        
        if (isset($_FILES['ktp']) && $_FILES['ktp']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['ktp'];
            $originalFileName = sanitizeInput($file['name']);
            $fileSize = $file['size'];
            $fileTmpName = $file['tmp_name'];
            $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

            // Validasi ukuran file
            if ($fileSize > MAX_FILE_SIZE) {
                $errors['ktp'] = 'Ukuran file maksimal 5MB';
            }

            // Validasi ekstensi file
            if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
                $errors['ktp'] = 'Format file harus JPG, JPEG, PNG, atau PDF';
            }

            // Validasi MIME type untuk keamanan tambahan
            $allowedMimeTypes = [
                'image/jpeg',
                'image/jpg', 
                'image/png',
                'application/pdf'
            ];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpName);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                $errors['ktp'] = 'Tipe file tidak valid';
            }

            // Jika validasi file berhasil, siapkan untuk upload
            if (empty($errors['ktp'])) {
                $ktpFileName = time() . '_' . uniqid() . '.' . $fileExtension;
                $ktpPath = UPLOAD_DIR . $ktpFileName;
            }
        } elseif (isset($_FILES['ktp']) && $_FILES['ktp']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle upload errors
            switch ($_FILES['ktp']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors['ktp'] = 'File terlalu besar';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors['ktp'] = 'File tidak terupload sepenuhnya';
                    break;
                default:
                    $errors['ktp'] = 'Terjadi kesalahan saat upload file';
            }
        }

        // Jika ada error validasi, kembalikan error
        if (!empty($errors)) {
            echo json_encode([
                'success' => false, 
                'errors' => $errors
            ]);
            exit;
        }

        // Koneksi ke database
        try {
            $pdo = getDBConnection();
            error_log("Database connection established successfully");
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Gagal terhubung ke database'
            ]);
            exit;
        }

        // Cek apakah email sudah terdaftar
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false, 
                    'errors' => ['email' => 'Email sudah terdaftar']
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("Email check error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi email'
            ]);
            exit;
        }

        // Cek apakah nomor telepon sudah terdaftar
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false, 
                    'errors' => ['phone' => 'Nomor telepon sudah terdaftar']
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("Phone check error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi nomor telepon'
            ]);
            exit;
        }

        // Upload file KTP jika ada
        if ($ktpPath && !move_uploaded_file($fileTmpName, $ktpPath)) {
            error_log("Failed to move uploaded file to: " . $ktpPath);
            echo json_encode([
                'success' => false,
                'errors' => ['ktp' => 'Gagal menyimpan file KTP']
            ]);
            exit;
        }

        // Hash password
        $passwordHash = hashPassword($password);

        // Mulai transaksi database
        $pdo->beginTransaction();

        try {
            // Simpan data user ke database dengan status menunggu verifikasi
            // email_verified = 0 dan is_active = 0 (default)
            $sql = "INSERT INTO users (full_name, email, phone, birth_date, address, ktp_filename, ktp_path, password_hash, email_verified, is_active, verification_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'pending', NOW())";
            
            $stmt = $pdo->prepare($sql);
            
            // Debug: Log SQL query (tanpa password hash)
            error_log("Executing SQL: " . $sql);
            error_log("Parameters: " . json_encode([
                $fullName, $email, $phone, $birthDate, 
                substr($address, 0, 50) . '...', $ktpFileName, $ktpPath, '[HASHED]'
            ]));

            $result = $stmt->execute([
                $fullName,
                $email,
                $phone,
                $birthDate,
                $address,
                $ktpFileName,
                $ktpPath,
                $passwordHash
            ]);

            if (!$result) {
                throw new Exception('Gagal menyimpan data user');
            }

            $userId = $pdo->lastInsertId();
            
            // Commit transaksi
            $pdo->commit();
            
            error_log("User registered successfully with ID: " . $userId . " - Waiting for admin verification");
            
            // Response sukses dengan pesan bahwa akun menunggu verifikasi
            echo json_encode([
                'success' => true,
                'message' => 'Registrasi berhasil! Akun Anda sedang menunggu verifikasi dari admin. Anda akan dihubungi melalui email setelah akun diverifikasi.',
                'user_id' => $userId,
                'status' => 'pending_verification',
                'redirect_url' => 'login.php?message=pending_verification'
            ]);

        } catch (Exception $e) {
            // Rollback transaksi jika terjadi error
            $pdo->rollback();
            
            // Hapus file yang sudah diupload jika ada error
            if ($ktpPath && file_exists($ktpPath)) {
                unlink($ktpPath);
            }
            
            error_log("Database insert error: " . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ]);
        }

    } catch (Exception $e) {
        // Log error umum
        error_log("Registration error: " . $e->getMessage());
        
        // Hapus file yang sudah diupload jika ada error
        if (isset($ktpPath) && $ktpPath && file_exists($ktpPath)) {
            unlink($ktpPath);
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
        ]);
    }
    
    exit; // Penting: keluar setelah proses POST
}

// Jika bukan POST request, tampilkan form HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FinanceDash</title>
    <link rel="stylesheet" href="css/register2.css">
</head>
<body>
    <div class="background-pattern">
        <div class="grid-overlay"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>   
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
            <div class="shape shape-5"></div>
            <div class="shape shape-6"></div>
        </div>
    </div>

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo">
                    <div class="logo-icon"></div>
                    <div class="logo-text">Jalanyuk</div>
                </div>
                <h2>Create Account</h2>
                <p>Join us to manage your finances better</p>
            </div>

            <form class="register-form" id="registerForm" enctype="multipart/form-data" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <div class="input-wrapper">
                            <input type="text" id="fullName" name="fullName" placeholder="Enter your full name" required>
                            <span class="input-icon">👤</span>
                        </div>
                        <span class="error-message" id="fullNameError"></span>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" placeholder="Enter your email" required>
                            <span class="input-icon">📧</span>
                        </div>
                        <span class="error-message" id="emailError"></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <div class="input-wrapper">
                            <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
                            <span class="input-icon">📱</span>
                        </div>
                        <span class="error-message" id="phoneError"></span>
                    </div>

                    <div class="form-group">
                        <label for="birthDate">Date of Birth</label>
                        <div class="input-wrapper">
                            <input type="date" id="birthDate" name="birthDate" required>
                        </div>
                        <span class="error-message" id="birthDateError"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <div class="input-wrapper">
                        <textarea id="address" name="address" placeholder="Enter your complete address" rows="3" required></textarea>
                        <span class="input-icon textarea-icon">🏠</span>
                    </div>
                    <span class="error-message" id="addressError"></span>
                </div>

                <div class="form-group">
                    <label for="ktp">Upload KTP/ID Card (Optional)</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="ktp" name="ktp" accept="image/*,.pdf">
                        <div class="file-upload-display">
                            <div class="file-upload-icon">📄</div>
                            <div class="file-upload-text">
                                <span class="file-name">Click to upload KTP/ID Card</span>
                                <span class="file-size">PNG, JPG, PDF up to 5MB</span>
                            </div>
                        </div>
                    </div>
                    <span class="error-message" id="ktpError"></span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                        <span class="input-icon toggle-password" onclick="togglePassword('password')">👁️</span>
                    </div>
                    <span class="error-message" id="passwordError"></span>
                </div>

                <div class="form-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" id="terms" name="terms" required>
                        <span class="checkmark"></span>
                        I agree to the <a href="#" class="link">Terms & Conditions</a>
                    </label>
                    <span class="error-message" id="termsError"></span>
                </div>

                <button type="submit" class="register-btn" id="registerBtn">
                    <span class="btn-text">Create Account</span>
                    <div class="btn-loader"></div>
                </button>
            </form>

            <div class="register-footer">
                <p>Already have an account? <a href="login.php" class="login-link">Sign in</a></p>
            </div>
        </div>
    </div>

    <script src="js/register.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendaftar...';
            
            // Clear previous errors
            this.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            
            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <strong><i class="fas fa-check-circle"></i> Berhasil!</strong> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    const form = document.getElementById('registrationForm');
                    form.parentNode.insertBefore(alertDiv, form);
                    
                    // Reset form
                    this.reset();
                    
                    // Redirect after 3 seconds
                    setTimeout(() => {
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        }
                    }, 3000);
                } else {
                    // Show errors
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            const input = document.querySelector(`[name="${field}"]`);
                            if (input) {
                                input.classList.add('is-invalid');
                                const feedback = input.nextElementSibling;
                                if (feedback && feedback.classList.contains('invalid-feedback')) {
                                    feedback.textContent = data.errors[field];
                                }
                            }
                        });
                    } else if (data.message) {
                        // Show general error message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            <strong><i class="fas fa-exclamation-circle"></i> Error!</strong> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        
                        const form = document.getElementById('registrationForm');
                        form.parentNode.insertBefore(alertDiv, form);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <strong><i class="fas fa-exclamation-circle"></i> Error!</strong> Terjadi kesalahan sistem. Silakan coba lagi.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const form = document.getElementById('registrationForm');
                form.parentNode.insertBefore(alertDiv, form);
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Daftar';
            });
        });
    </script>
</body>
</html>