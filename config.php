    <?php
    // config.php - File konfigurasi utama
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Konfigurasi Database
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'jalanyukproject');   
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');

    // Konfigurasi Upload File
    define('UPLOAD_DIR', 'uploads/ktp/');
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

    // Pastikan direktori upload ada
    if (!file_exists(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            die('Gagal membuat direktori upload');
        }
    }

    // Fungsi untuk koneksi database
    function getDBConnection() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Gagal terhubung ke database");
        }
    }

    // Inisialisasi koneksi database global (untuk kompatibilitas dengan kode lama)
    try {
        $pdo = getDBConnection();
    } catch (Exception $e) {
        error_log("Failed to initialize global database connection: " . $e->getMessage());
        // Tidak die() di sini, karena beberapa file mungkin tidak memerlukan database
        $pdo = null;
    }

    // Fungsi requireLogin() sudah didefinisikan di session_check.php
    // Jadi tidak perlu didefinisikan lagi di sini

    // Fungsi untuk cek apakah user sudah login
    function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    // Fungsi untuk logout
    function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Hapus semua data session
        $_SESSION = array();
        
        // Hapus session cookie jika ada
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        header('Location: login.php');
        exit;
    }

    // Fungsi untuk mendapatkan data user yang sedang login
    function getCurrentUser() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? ''
        ];
    }

    // Fungsi untuk sanitasi input
    function sanitizeInput($input) {
        if (is_string($input)) {
            return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
        }
        return $input;
    }

    // Fungsi validasi email
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // Fungsi validasi nomor telepon Indonesia
    function isValidPhoneNumber($phone) {
        // Hapus semua karakter non-digit
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Cek pola nomor telepon Indonesia
        // Dimulai dengan 08, 62, +62, atau 0
        $pattern = '/^(08|628|62|0)[0-9]{8,12}$/';
        
        return preg_match($pattern, $cleanPhone);
    }

    // Fungsi validasi password
    function isValidPassword($password) {
        // Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka
        if (strlen($password) < 8) {
            return false;
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return false; // Tidak ada huruf kecil
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return false; // Tidak ada huruf besar
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return false; // Tidak ada angka
        }
        
        return true;
    }

    // Fungsi untuk hash password
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // Fungsi untuk verifikasi password
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    // Fungsi untuk format nomor telepon
    function formatPhoneNumber($phone) {
        // Hapus semua karakter non-digit
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Jika dimulai dengan 0, ganti dengan 62
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }
        
        // Jika sudah dimulai dengan 62, biarkan
        if (substr($cleanPhone, 0, 2) === '62') {
            return $cleanPhone;
        }
        
        // Jika tidak ada prefiks, tambahkan 62
        return '62' . $cleanPhone;
    }

    // Fungsi untuk format currency
    function formatCurrency($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    // Fungsi untuk format tanggal Indonesia
    function formatDateIndo($date) {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        $timestamp = is_string($date) ? strtotime($date) : $date;
        $day = date('j', $timestamp);
        $month = $months[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        
        return "$day $month $year";
    }

    // Set timezone
    date_default_timezone_set('Asia/Jakarta');
    ?>