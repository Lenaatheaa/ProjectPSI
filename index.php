<?php
// navbar.php
require_once 'session_check.php';

// Redirect jika tidak login
requireLogin();

// Pastikan user_data tersedia dan valid
if (!isset($user_data) || !is_array($user_data)) {
    // Redirect ke login jika user_data tidak valid
    header('Location: login.php');
    exit();
}

// Debug: Tampilkan user_data untuk memastikan user_id ada
// Uncomment line berikut untuk debug (hapus setelah selesai)
// error_log("User data: " . print_r($user_data, true));

// Koneksi database untuk mengambil balance terbaru
try {
    // Asumsi menggunakan PDO - sesuaikan dengan konfigurasi database Anda
    $host = '127.0.0.1';
    $dbname = 'jalanyukproject';
    $username_db = 'root'; // sesuaikan dengan username database Anda
    $password_db = ''; // sesuaikan dengan password database Anda
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ambil balance terbaru dari database
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$user_data['id']]);
    $balance_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set balance, default 0 jika tidak ditemukan
    $user_balance = $balance_data ? $balance_data['balance'] : 0;
    
} catch (PDOException $e) {
    // Jika koneksi database gagal, gunakan balance default
    error_log("Database connection failed: " . $e->getMessage());
    $user_balance = 0;
    $pdo = null; // Set PDO ke null jika gagal koneksi
}

// Format balance ke format Rupiah
function formatRupiah($amount) {
    return 'RP ' . number_format($amount, 0, ',', '.');
}

// Generate initials untuk avatar
function getInitials($fullName) {
    if (empty($fullName)) {
        return 'U'; // Default jika nama kosong
    }
    
    $fullName = trim($fullName);
    // Cek apakah $fullName kosong setelah trim
    if (empty($fullName)) {
        return 'U';
    }
    
    $fullName = htmlspecialchars($fullName);
    $name_parts = explode(' ', $fullName);
    $initials = '';
    
    foreach ($name_parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper($part[0]);
            if (strlen($initials) >= 2) break; // Maksimal 2 inisial
        }
    }
    
    return empty($initials) ? 'U' : $initials;
}

// Fungsi untuk mengambil data transaksi dan menghitung money in/out
function getTransactionInsights($pdo, $user_id, $days = 7) {
    if (!$pdo) {
        return [
            'money_in' => 0,
            'money_out' => 0,
            'money_in_percentage' => 0,
            'money_out_percentage' => 0,
            'money_in_trend' => 'neutral',
            'money_out_trend' => 'neutral'
        ];
    }
    
    try {
        // Hitung tanggal mulai (7 hari yang lalu)
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Query untuk Money In (topup dan transfer_in) dalam periode tertentu
        $stmt_money_in = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_money_in 
            FROM transactions 
            WHERE user_id = ? 
            AND transaction_type IN ('topup', 'transfer_in') 
            AND status = 'completed'
            AND created_at >= ?
        ");
        $stmt_money_in->execute([$user_id, $start_date]);
        $money_in_current = $stmt_money_in->fetch(PDO::FETCH_ASSOC)['total_money_in'];
        
        // Query untuk Money Out (transfer_out dan bill_payment) dalam periode tertentu
        $stmt_money_out = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_money_out 
            FROM transactions 
            WHERE user_id = ? 
            AND transaction_type IN ('transfer_out', 'bill_payment') 
            AND status = 'completed'
            AND created_at >= ?
        ");
        $stmt_money_out->execute([$user_id, $start_date]);
        $money_out_current = $stmt_money_out->fetch(PDO::FETCH_ASSOC)['total_money_out'];
        
        // Query untuk Money In periode sebelumnya (untuk menghitung persentase perubahan)
        $previous_start_date = date('Y-m-d H:i:s', strtotime("-" . ($days * 2) . " days"));
        $previous_end_date = $start_date;
        
        $stmt_money_in_prev = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_money_in 
            FROM transactions 
            WHERE user_id = ? 
            AND transaction_type IN ('topup', 'transfer_in') 
            AND status = 'completed'
            AND created_at >= ? AND created_at < ?
        ");
        $stmt_money_in_prev->execute([$user_id, $previous_start_date, $previous_end_date]);
        $money_in_previous = $stmt_money_in_prev->fetch(PDO::FETCH_ASSOC)['total_money_in'];
        
        // Query untuk Money Out periode sebelumnya
        $stmt_money_out_prev = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_money_out 
            FROM transactions 
            WHERE user_id = ? 
            AND transaction_type IN ('transfer_out', 'bill_payment') 
            AND status = 'completed'
            AND created_at >= ? AND created_at < ?
        ");
        $stmt_money_out_prev->execute([$user_id, $previous_start_date, $previous_end_date]);
        $money_out_previous = $stmt_money_out_prev->fetch(PDO::FETCH_ASSOC)['total_money_out'];
        
        // Hitung persentase perubahan
        $money_in_percentage = 0;
        $money_in_trend = 'neutral';
        if ($money_in_previous > 0) {
            $money_in_percentage = round((($money_in_current - $money_in_previous) / $money_in_previous) * 100);
            $money_in_trend = $money_in_percentage >= 0 ? 'up' : 'down';
        } elseif ($money_in_current > 0) {
            $money_in_percentage = 100;
            $money_in_trend = 'up';
        }
        
        $money_out_percentage = 0;
        $money_out_trend = 'neutral';
        if ($money_out_previous > 0) {
            $money_out_percentage = round((($money_out_current - $money_out_previous) / $money_out_previous) * 100);
            $money_out_trend = $money_out_percentage >= 0 ? 'up' : 'down';
        } elseif ($money_out_current > 0) {
            $money_out_percentage = 100;
            $money_out_trend = 'up';
        }
        
        return [
            'money_in' => $money_in_current,
            'money_out' => $money_out_current,
            'money_in_percentage' => abs($money_in_percentage),
            'money_out_percentage' => abs($money_out_percentage),
            'money_in_trend' => $money_in_trend,
            'money_out_trend' => $money_out_trend
        ];
        
    } catch (PDOException $e) {
        error_log("Transaction insights query failed: " . $e->getMessage());
        return [
            'money_in' => 0,
            'money_out' => 0,
            'money_in_percentage' => 0,
            'money_out_percentage' => 0,
            'money_in_trend' => 'neutral',
            'money_out_trend' => 'neutral'
        ];
    }
}

// FUNGSI: Ambil recent transactions dari database
function getRecentTransactions($pdo, $user_id, $limit = 5) {
    if (!$pdo) {
        error_log("PDO connection is null in getRecentTransactions");
        return [];
    }
    
    try {
        // Debug: Log query yang akan dijalankan
        error_log("Getting recent transactions for user_id: $user_id, limit: $limit");
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                transaction_type,
                amount,
                payment_method,
                status,
                description,
                created_at
            FROM transactions 
            WHERE user_id = ?
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        // Bind parameter dengan tipe data yang tepat
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log hasil query
        error_log("Found " . count($transactions) . " transactions for user_id: $user_id");
        if (empty($transactions)) {
            // Cek apakah ada transaksi sama sekali untuk user ini
            $check_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
            $check_stmt->execute([$user_id]);
            $total_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            error_log("Total transactions in database for user_id $user_id: $total_count");
        }
        
        return $transactions;
        
    } catch (PDOException $e) {
        error_log("Recent transactions query failed: " . $e->getMessage());
        return [];
    }
}

// FUNGSI: Format tanggal untuk display
function formatTransactionDate($datetime) {
    try {
        $date = new DateTime($datetime);
        $now = new DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days == 0) {
            return 'Today at ' . $date->format('g:i A');
        } elseif ($diff->days == 1) {
            return 'Yesterday at ' . $date->format('g:i A');
        } elseif ($diff->days < 7) {
            return $date->format('l') . ' at ' . $date->format('g:i A');
        } else {
            return $date->format('j M Y') . ' at ' . $date->format('g:i A');
        }
    } catch (Exception $e) {
        error_log("Error formatting date: " . $e->getMessage());
        return $datetime; // Return original string if formatting fails
    }
}

// FUNGSI: Get transaction icon based on type and payment method
function getTransactionIcon($transaction_type, $payment_method, $description) {
    // Default icons untuk setiap tipe transaksi
    $icons = [
        'topup' => 'images/piggy-bank.png',
        'transfer_out' => 'images/piggy-bank.png', 
        'transfer_in' => 'images/piggy-bank.png',
        'bill_payment' => ' images/piggy-bank.png'
    ];
    
    // Coba deteksi dari description untuk pembayaran tagihan grup
    if ($transaction_type === 'bill_payment' && stripos($description, 'grup') !== false) {
        return 'images/group-svgrepo-com.svg';
    }
    
    // Coba deteksi berdasarkan payment method
    if (stripos($payment_method, 'kartu') !== false || stripos($payment_method, 'kredit') !== false) {
        return 'images/card-svgrepo-com.svg';
    }
    
    return $icons[$transaction_type] ?? 'images/transaction-svgrepo-com.svg';
}

// FUNGSI: Get transaction title/name - Updated version
function getTransactionTitle($transaction_type, $payment_method, $description) {
    switch ($transaction_type) {
        case 'topup':
            return 'Top Up'; // Hanya menampilkan "Top Up" tanpa payment_method
        case 'transfer_out':
            // Extract nama penerima dari description jika ada
            if (preg_match('/Transfer ke (.+?) \(/i', $description, $matches)) {
                return 'Transfer to ' . trim($matches[1]);
            }
            return 'Transfer Out';
        case 'transfer_in':
            // Extract nama pengirim dari description jika ada
            if (preg_match('/Transfer dari (.+?) \(/i', $description, $matches)) {
                return 'Transfer from ' . trim($matches[1]);
            }
            return 'Transfer In';
        case 'bill_payment':
            // Extract nama grup dari description jika ada
            if (preg_match('/grup: (.+?) \(/i', $description, $matches)) {
                return 'Bill: ' . trim($matches[1]);
            }
            return 'Bill Payment';
        default:
            return ucfirst(str_replace('_', ' ', $transaction_type));
    }
}

// FUNGSI: Get transaction subtitle (opsional untuk menampilkan payment method)
function getTransactionSubtitle($transaction_type, $payment_method, $description) {
    switch ($transaction_type) {
        case 'topup':
            // Pastikan payment_method tidak null/empty
            return !empty($payment_method) ? htmlspecialchars($payment_method) : 'Wallet';
        case 'transfer_out':
        case 'transfer_in':
            return 'Wallet Transfer';
        case 'bill_payment':
            return 'Bill Payment';
        default:
            return '';
    }
}

// FUNGSI: Format amount dengan tanda + atau -
function formatTransactionAmount($amount, $transaction_type) {
    $formatted_amount = formatRupiah($amount);
    
    // Tentukan apakah ini money in atau money out
    if (in_array($transaction_type, ['topup', 'transfer_in'])) {
        return '+' . $formatted_amount;
    } else {
        return '-' . $formatted_amount;
    }
}

// FUNGSI: Get status color class
function getStatusColorClass($status) {
    switch ($status) {
        case 'completed':
            return 'text-color-success';
        case 'pending':
            return 'text-color-warning';
        case 'failed':
            return 'text-color-error';
        case 'cancelled':
            return 'text-color-grey';
        default:
            return '';
    }
}

// Ambil data transaksi insights jika PDO tersedia
$transaction_insights = [];
if ($pdo) {
    $transaction_insights = getTransactionInsights($pdo, $user_data['id'], 7);
}

// Ambil recent transactions
$recent_transactions = [];
if ($pdo && isset($user_data['id'])) {
    $recent_transactions = getRecentTransactions($pdo, $user_data['id'], 5);
    
    // Debug: Log hasil untuk membantu troubleshooting
    error_log("Recent transactions count: " . count($recent_transactions));
    if (!empty($recent_transactions)) {
        error_log("First transaction: " . print_r($recent_transactions[0], true));
    }
}

// Pastikan variabel yang diperlukan tersedia
$user_initial = getInitials($user_data['full_name'] ?? '');
$full_name = htmlspecialchars($user_data['full_name'] ?? 'User');

// Untuk backward compatibility jika ada file lain yang masih menggunakan $username
$username = $full_name;
?>
<!DOCTYPE html><!-- Handled by Exflow.site -->
<html data-wf-page="68532313ba0975dc639d297a" data-wf-site="68532312ba0975dc639d2929" data-wf-status="1">

<head>
  <meta charset="utf-8" />
  <title>Jalanyuk</title>
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <meta content="Webflow" name="generator" />
  <link href="css/arzals-amazing-site-29f3c2.webflow.shared.94e648c70.css" rel="stylesheet" type="text/css" />
  <link href="https://fonts.googleapis.com" rel="preconnect" />
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous" />
  <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js" type="text/javascript"></script>
  <script type="text/javascript">
    WebFont.load({
      google: {
        families: ["Outfit:100,200,300,regular,500,600,700,800,900"]
      }
    });
  </script>
  <script type="text/javascript">
    ! function (o, c) {
      var n = c.documentElement,
        t = " w-mod-";
      n.className += t + "js", ("ontouchstart" in o || o.DocumentTouch && c instanceof DocumentTouch) && (n.className +=
        t + "touch")
    }(window, document);
  </script>
  <style>
  .ms-dot {
      animation: pulse 2s infinite ease-in-out;
    }

    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(41, 98, 255, 0.4);
      }

      70% {
        box-shadow: 0 0 0 30px rgba(41, 98, 255, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(41, 98, 255, 0);
      }
    }

    /* Make text look crisper and more legible in all browsers */
    /* FORCE FULL SCREEN - Reset semua constraint */
    * {
      box-sizing: border-box;
    }

    /* PERBAIKAN UTAMA: Ubah height menjadi min-height untuk memungkinkan scroll */
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      min-height: 100vh !important; /* UBAH: dari height ke min-height */
      height: auto !important; /* TAMBAH: biarkan tinggi natural */
      width: 100vw !important;
      overflow-x: hidden !important;
      overflow-y: auto !important; /* PASTIKAN: scroll vertical aktif */
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      font-smoothing: antialiased;
      text-rendering: optimizeLegibility;
    }

    /* OVERRIDE WEBFLOW CONTAINERS - Force full width */
    .w-container,
    .container,
    .container-large,
    .container-medium,
    .container-small {
      max-width: none !important;
      width: 100vw !important;
      margin: 0 !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }

    /* MAIN PAGE WRAPPER - KUNCI PERBAIKAN */
    [data-wf-page] {
      width: 100vw !important;
      min-height: 100vh !important; /* UBAH: dari height ke min-height */
      height: auto !important; /* TAMBAH: biarkan expand sesuai content */
      margin: 0 !important;
      padding: 0 !important;
      overflow-x: hidden !important;
      overflow-y: visible !important; /* UBAH: dari auto ke visible */
    }

    /* PAGE WRAPPER */
    .page-wrapper {
      width: 100vw !important;
      min-height: 100vh !important; /* UBAH: dari height ke min-height */
      height: auto !important; /* TAMBAH: biarkan expand */
      overflow-y: visible !important; /* TAMBAH: pastikan tidak terpotong */
    }

    /* NAVIGATION BAR - Full width */
    .navbar,
    .nav,
    header {
      width: 100vw !important;
      padding-left: 1rem !important;
      padding-right: 1rem !important;
      margin: 0 !important;
      position: relative !important; /* PASTIKAN: tidak fixed yang menghalangi */
      z-index: 1000;
    }

    /* HERO/TOP SECTION - Full width with proper padding */
    .hero-section,
    .section {
      width: 100vw !important;
      margin: 0 !important;
      padding-left: 1rem !important;
      padding-right: 1rem !important;
      height: auto !important; /* TAMBAH: biarkan natural height */
      overflow-y: visible !important; /* TAMBAH: pastikan tidak terpotong */
    }

    /* DASHBOARD CONTENT WRAPPER - PERBAIKAN UTAMA */
    .dashboard-content,
    .main-content {
      width: 100vw !important;
      min-height: calc(100vh - 80px) !important; /* PERTAHANKAN min-height */
      height: auto !important; /* TAMBAH: biarkan expand sesuai content */
      max-height: none !important; /* TAMBAH: hilangkan batasan tinggi */
      padding: 1rem !important;
      margin: 0 !important;
      overflow-y: visible !important; /* UBAH: dari auto ke visible */
      overflow-x: hidden !important;
    }

    /* BALANCE AND INSIGHTS CARDS */
    .balance-section,
    .insights-section {
      width: 100% !important;
      margin-bottom: 1.5rem !important;
      height: auto !important; /* TAMBAH: biarkan natural height */
    }

  .quick-links {
  width: 100% !important;
  padding: 1.5rem !important;
  margin: 0 0 3rem 0 !important; /* UBAH: tambah margin bottom lebih besar */
  background: white;
  border-radius: 1rem;
  height: auto !important; /* TAMBAH: biarkan natural height */
}
    /* GRID LAYOUTS - Force full width and proper spacing */
.dashboard_content-grid {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: 1.5rem !important;
  width: 100% !important;
  margin: 2rem 0 4rem 0 !important; /* UBAH: tambah margin bottom 4rem */
  padding: 0 !important;
  height: auto !important;
  overflow-y: visible !important;
}

/* TAMBAHKAN: Rule khusus untuk memberikan jarak dengan container family */
.dashboard_content-grid + * {
  margin-top: 3rem !important;
}
    /* TRANSACTION AND BILLS SECTIONS - PERBAIKAN PENTING */
    .recent-transactions,
    .bills-section,
    .tagihan-section {
      width: 100% !important;
      height: auto !important; /* UBAH: dari height fixed ke auto */
      min-height: 400px !important; /* PERTAHANKAN min-height */
      max-height: none !important; /* TAMBAH: hilangkan batasan maksimal */
      padding: 1.5rem !important;
      margin: 1rem 0 !important; /* UBAH: tambah margin top dan bottom */
      background: white;
      border-radius: 1rem;
      overflow-y: visible !important; /* UBAH: dari auto ke visible */
      position: relative !important; /* TAMBAH: pastikan positioning normal */
    }

    /* SCROLL CONTAINER untuk konten yang panjang - PERBAIKAN */
    .scrollable-content {
      max-height: none !important; /* UBAH: hilangkan batasan tinggi */
      height: auto !important; /* TAMBAH: biarkan natural height */
      overflow-y: visible !important; /* UBAH: dari auto ke visible */
      overflow-x: hidden !important;
    }

    /* FORCE REMOVE WEBFLOW LIMITATIONS */
    .w-section {
      padding-left: 0 !important;
      padding-right: 0 !important;
      height: auto !important; /* TAMBAH: biarkan natural height */
      overflow-y: visible !important; /* TAMBAH: pastikan tidak terpotong */
    }

    .w-row {
      margin-left: 0 !important;
      margin-right: 0 !important;
      max-width: none !important;
      height: auto !important; /* TAMBAH: biarkan natural height */
    }

    .w-col {
      padding-left: 0.75rem !important;
      padding-right: 0.75rem !important;
      height: auto !important; /* TAMBAH: biarkan natural height */
    }

    /* Focus state style for keyboard navigation */
    *[tabindex]:focus-visible,
    input[type="file"]:focus-visible {
      outline: 0.125rem solid #4d65ff;
      outline-offset: 0.125rem;
    }

    /* Set color style to inherit */
    .inherit-color * {
      color: inherit;
    }

    /* Rich text margins */
    .w-richtext> :not(div):first-child,
    .w-richtext>div:first-child> :first-child {
      margin-top: 0 !important;
    }

    .w-richtext>:last-child,
    .w-richtext ol li:last-child,
    .w-richtext ul li:last-child {
      margin-bottom: 0 !important;
    }

    /* Text truncation utilities */
    .text-style-3lines {
      display: -webkit-box;
      overflow: hidden;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
    }

    .text-style-2lines {
      display: -webkit-box;
      overflow: hidden;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .display-inlineflex {
      display: inline-flex;
    }

    /* Hide classes */
    .hide {
      display: none !important;
    }

    /* Spacing utilities */
    .margin-0 { margin: 0rem !important; }
    .padding-0 { padding: 0rem !important; }
    .spacing-clean { padding: 0rem !important; margin: 0rem !important; }
    .margin-top { margin-right: 0rem !important; margin-bottom: 0rem !important; margin-left: 0rem !important; }
    .padding-top { padding-right: 0rem !important; padding-bottom: 0rem !important; padding-left: 0rem !important; }
    .margin-right { margin-top: 0rem !important; margin-bottom: 0rem !important; margin-left: 0rem !important; }
    .padding-right { padding-top: 0rem !important; padding-bottom: 0rem !important; padding-left: 0rem !important; }
    .margin-bottom { margin-top: 0rem !important; margin-right: 0rem !important; margin-left: 0rem !important; }
    .padding-bottom { padding-top: 0rem !important; padding-right: 0rem !important; padding-left: 0rem !important; }
    .margin-left { margin-top: 0rem !important; margin-right: 0rem !important; margin-bottom: 0rem !important; }
    .padding-left { padding-top: 0rem !important; padding-right: 0rem !important; padding-bottom: 0rem !important; }
    .margin-horizontal { margin-top: 0rem !important; margin-bottom: 0rem !important; }
    .padding-horizontal { padding-top: 0rem !important; padding-bottom: 0rem !important; }
    .margin-vertical { margin-right: 0rem !important; margin-left: 0rem !important; }
    .padding-vertical { padding-right: 0rem !important; padding-left: 0rem !important; }

    /* RESPONSIVE BREAKPOINTS */
    @media screen and (max-width: 1400px) {
      .dashboard-content {
        padding: 0.75rem !important;
      }
    }

    @media screen and (max-width: 991px) {
      .hide,
      .hide-tablet {
        display: none !important;
      }
      
      .dashboard_content-grid {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
      }
      
      .navbar {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
      }
      
      .dashboard-content {
        padding: 0.5rem !important;
      }
    }

    @media screen and (max-width: 767px) {
      .hide-mobile-landscape {
        display: none !important;
      }
      
      .quick-links {
        padding: 1rem !important;
        margin: 0 0 2rem 0 !important; /* UBAH: kurangi margin di mobile tapi tetap ada spacing */
      }
      
      .recent-transactions,
      .bills-section {
        padding: 1rem !important;
      }
    }

    @media screen and (max-width: 479px) {
      .hide-mobile {
        display: none !important;
      }
      
      .navbar {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
      }
      
      .dashboard-content {
        padding: 0.25rem !important;
      }
      
      .dashboard_content-grid {
        gap: 0.75rem !important;
      }
    }

    /* ADDITIONAL FIXES untuk Webflow */
    .w-embed {
      width: 100% !important;
    }

    /* Force scroll behavior */
    body {
      scroll-behavior: smooth !important;
    }

    /* Ensure all sections take full width */
    section,
    div[class*="section"] {
      width: 100% !important;
      height: auto !important; /* TAMBAH: biarkan natural height */
    }

    /* Override any fixed positioning that might limit width */
    .w-nav {
      width: 100vw !important;
      position: relative !important; /* TAMBAH: pastikan tidak fixed */
    }

    /* Custom scrollbar styling */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }

    /* TAMBAHAN PERBAIKAN KHUSUS UNTUK SCROLL */
    
    /* Pastikan tidak ada element yang menghalangi scroll */
    .w-container,
    .w-section,
    .w-row,
    .w-col {
      overflow-y: visible !important;
      height: auto !important;
    }

    /* Force minimum content height agar ada yang bisa di-scroll */
    body::after {
      content: "";
      display: block;
      height: 1px;
      width: 100%;
      clear: both;
    }

    /* Pastikan semua wrapper container bisa expand */
    .main-wrapper,
    .content-wrapper,
    .page-container {
      height: auto !important;
      min-height: 100vh !important;
      overflow-y: visible !important;
    }

/* CSS chatbot */
.bot-message .message-content {
  padding-left: 16px;
}

.bot-message .message-content li {
  list-style-type: disc;
  margin-left: 16px;
}

/* Overlay blur */
#chatbot-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

#chatbot-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Chatbot Container */
#chatbot-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Tombol Buka Chatbot */
#chatbot-button {
    position: relative;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: white;
    border: 2px solid #333;
    outline: none;
}

#chatbot-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
    background: linear-gradient(135deg, #333 0%, #222 100%);
}

#chatbot-button:active {
    transform: scale(0.95);
}

/* Titik notifikasi */
#notification-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 12px;
    height: 12px;
    background: #ff4757;
    border-radius: 50%;
    border: 2px solid white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

/* Jendela chatbot */
#chatbot-window {
    position: fixed;
    top: 20px;
    right: 20px;
    bottom: 20px;
    width: 400px;
    background: #1a1a1a;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    transform: translateX(100%);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    border: 1px solid #333;
    display: none;
    flex-direction: column;
    z-index: 9999;
}

/* Saat aktif, tampilkan */
#chatbot-window.active {
    transform: translateX(0);
    opacity: 1;
    visibility: visible;
    display: flex;
}

/* Header */
.chat-header {
    background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    border-bottom: 1px solid #333;
    flex-shrink: 0;
}

.chat-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 40px;
    height: 40px;
    background: #333;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #444;
}

.chat-title h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: white;
}

.status {
    font-size: 12px;
    color: #4ade80;
    font-weight: 500;
}

/* Tombol close */
#close-chat {
    background: none;
    border: none;
    color: #ccc;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

#close-chat:hover {
    background: #333;
    color: white;
}

/* Chat body */
.chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #0f0f0f;
    min-height: 0;
}

.chat-body::-webkit-scrollbar {
    width: 6px;
}

.chat-body::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.chat-body::-webkit-scrollbar-thumb {
    background: #333;
    border-radius: 3px;
}

/* Pesan */
.message {
    margin-bottom: 16px;
    display: flex;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.bot-message {
    justify-content: flex-start;
}

.user-message {
    justify-content: flex-end;
}

.message-content {
    max-width: 80%;
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;
}

.bot-message .message-content {
    background: #2c2c2c;
    color: #f0f0f0;
    border-bottom-left-radius: 6px;
    border: 1px solid #333;
}

.user-message .message-content {
    background: linear-gradient(135deg, #333 0%, #222 100%);
    color: white;
    border-bottom-right-radius: 6px;
    border: 1px solid #444;
}

/* Input pesan */
.chat-input {
    display: flex;
    padding: 20px;
    background: #1a1a1a;
    border-top: 1px solid #333;
    gap: 12px;
    align-items: center;
    flex-shrink: 0;
}

#user-input {
    flex: 1;
    border: 1px solid #333;
    border-radius: 20px;
    padding: 12px 16px;
    font-size: 14px;
    outline: none;
    transition: all 0.2s;
    background: #0f0f0f;
    color: white;
}

#user-input::placeholder {
    color: #666;
}

#user-input:focus {
    border-color: #555;
    background: #1a1a1a;
}

#send-btn {
    background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
    border: 1px solid #333;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: white;
}

#send-btn:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #333 0%, #222 100%);
    border-color: #444;
}

#send-btn:active {
    transform: scale(0.95);
}

#send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Loading indicator */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 12px 16px;
    background: #2c2c2c;
    border-radius: 18px;
    border-bottom-left-radius: 6px;
    border: 1px solid #333;
    max-width: 60px;
}

.typing-dot {
    width: 6px;
    height: 6px;
    background: #666;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { opacity: 0.3; }
    30% { opacity: 1; }
}

/* Responsive */
@media (max-width: 480px) {
    #chatbot-window {
        top: 10px;
        right: 10px;
        bottom: 10px;
        left: 10px;
        width: auto;
    }

    #chatbot-container {
        bottom: 15px;
        right: 15px;
    }

    .chat-input {
        padding: 16px;
    }
}

@media (max-width: 320px) {
    #chatbot-window {
        top: 5px;
        right: 5px;
        bottom: 5px;
        left: 5px;
    }
}


        /* Demo content styles */
        .demo-content {
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .demo-content h1 {
            color: #333;
            margin-bottom: 20px;
        }

        .demo-content p {
            color: #666;
            margin-bottom: 15px;
        }
/* logo slide css*/
   .logo-slider {
    width: 100%;
    height: 80px;
    background: #f8f9fa;
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 40px; /* Added margin to create space below Quick Links */
}

.logo-track {
    display: flex;
    align-items: center;
    gap: 80px;
    animation: slideLeftToRight 12s linear infinite;
    padding: 0 40px;
    width: calc(200% + 160px);
    transform: translateX(-25%); /* Removed translateY since we're using margin-top instead */
}

.logo-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
    font-weight: 600;
    color: #666;
    white-space: nowrap;
    min-width: max-content;
}

.logo-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Hourglass Icon */
.hourglass-icon {
    background: #7c6f64;
    border-radius: 4px;
    position: relative;
    transform: rotate(0deg);
}

.hourglass-icon::before {
    content: '';
    position: absolute;
    width: 16px;
    height: 20px;
    background: 
        linear-gradient(45deg, transparent 30%, #7c6f64 30%, #7c6f64 70%, transparent 70%),
        linear-gradient(-45deg, transparent 30%, #7c6f64 30%, #7c6f64 70%, transparent 70%);
    background-size: 100% 50%;
    background-position: 0 0, 0 100%;
    background-repeat: no-repeat;
}

/* Lightbox Icon */
.lightbox-icon {
    background: #4a5568;
    border-radius: 6px;
    position: relative;
}

.lightbox-icon::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 14px;
    border: 2px solid white;
    border-radius: 2px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

/* Spherule Icon */
.spherule-icon {
    background: #2d3748;
    border-radius: 50%;
    position: relative;
}

.spherule-icon::before {
    content: '';
    position: absolute;
    width: 14px;
    height: 14px;
    border: 2px solid white;
    border-radius: 50%;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

/* Capsule Icon */
.capsule-icon {
    background: #4a5568;
    border-radius: 14px;
    position: relative;
}

.capsule-icon::before {
    content: '';
    position: absolute;
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    top: 6px;
    left: 6px;
}

.capsule-icon::after {
    content: '';
    position: absolute;
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    bottom: 6px;
    right: 6px;
}

/* Luminous Icon */
.luminous-icon {
    background: #2d3748;
    border-radius: 4px;
    position: relative;
}

.luminous-icon::before {
    content: '';
    position: absolute;
    width: 16px;
    height: 3px;
    background: white;
    top: 6px;
    left: 6px;
}

.luminous-icon::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 3px;
    background: white;
    bottom: 6px;
    left: 6px;
}

@keyframes slideLeftToRight {
    0% {
        transform: translateX(-75%); /* Removed translateY since we're using margin-top */
    }
    100% {
        transform: translateX(-25%); /* Removed translateY since we're using margin-top */
    }
}

/* Hover effects */
.logo-item:hover {
    color: #333;
    transform: scale(1.05);
    transition: all 0.3s ease;
}

.logo-item:hover .logo-icon {
    transform: scale(1.1);
    transition: all 0.3s ease;
}

/* family */
.work-performance-dashboard {
    width: 100%;
    max-width: 1200px;
    margin: -30px auto 0 auto;
    padding: 0 20px 20px 20px;
}

.performance-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.card-title {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
    text-align: center;
}

.task-table-container {
    overflow-x: auto;
}

.task-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.table-header {
    border-bottom: 2px solid rgba(0, 0, 0, 0.1);
}

.table-header th {
    text-align: center;
    padding: 15px 20px;
    font-weight: 500;
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    vertical-align: middle;
}

.table-header th:nth-child(1) { width: 25%; }
.table-header th:nth-child(2) { width: 25%; }
.table-header th:nth-child(3) { width: 25%; }
.table-header th:nth-child(4) { width: 25%; }

.task-row {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.task-row:hover {
    background: rgba(0, 0, 0, 0.03);
    transform: translateY(-2px);
}

.task-row td {
    padding: 20px;
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
    text-align: center;
}

.task-name {
    font-weight: 500;
    font-size: 16px;
    color: #333;
    line-height: 1.4;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    min-width: fit-content;
}

.status-badge::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-done {
    background: rgba(46, 204, 113, 0.2);
    color: #2ecc71;
    border: 1px solid rgba(46, 204, 113, 0.3);
}

.status-done::before {
    background: #2ecc71;
}

.status-progress {
    background: rgba(230, 126, 34, 0.2);
    color: #e67e22;
    border: 1px solid rgba(230, 126, 34, 0.3);
}

.status-progress::before {
    background: #e67e22;
}

.status-next {
    background: rgba(155, 89, 182, 0.2);
    color: #9b59b6;
    border: 1px solid rgba(155, 89, 182, 0.3);
}

.status-next::before {
    background: #9b59b6;
}

.team-avatars {
    display: flex;
    align-items: center;
    gap: -8px;
    justify-content: center;
    height: 32px;
}

.team-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.8);
    margin-left: -8px;
    position: relative;
    z-index: 1;
    transition: transform 0.2s ease;
    object-fit: cover;
    flex-shrink: 0;
}

.team-avatar:hover {
    transform: scale(1.1);
    z-index: 2;
}

.team-avatar:first-child {
    margin-left: 0;
}

.date-text {
    color: #666;
    font-size: 14px;
    line-height: 1.4;
    white-space: nowrap;
}

.table-footer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}

.footer-text {
    color: #999;
    font-size: 14px;
    text-align: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .work-performance-dashboard {
        padding: 10px;
    }
    
    .performance-card {
        padding: 20px;
        border-radius: 15px;
    }
    
    .card-title {
        font-size: 24px;
        margin-bottom: 20px;
    }
    
    .task-table {
        font-size: 14px;
        table-layout: auto;
    }
    
    .table-header th {
        padding: 12px 10px;
        font-size: 12px;
    }
    
    .table-header th:nth-child(1) { width: auto; }
    .table-header th:nth-child(2) { width: auto; }
    .table-header th:nth-child(3) { width: auto; }
    .table-header th:nth-child(4) { width: auto; }
    
    .task-row td {
        padding: 15px 10px;
    }
    
    .team-avatar {
        width: 28px;
        height: 28px;
    }
    
    .status-badge {
        font-size: 11px;
        padding: 6px 12px;
    }
    
    .date-text {
        font-size: 13px;
        white-space: normal;
    }
}

@media (max-width: 480px) {
    .task-table-container {
        overflow-x: scroll;
    }
    
    .task-table {
        min-width: 600px;
    }
    
    .date-text {
        white-space: nowrap;
    }
}

/* Animation for loading effect */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.task-row {
    animation: fadeIn 0.5s ease forwards;
}

.task-row:nth-child(1) { animation-delay: 0.1s; }
.task-row:nth-child(2) { animation-delay: 0.2s; }
.task-row:nth-child(3) { animation-delay: 0.3s; }
.task-row:nth-child(4) { animation-delay: 0.4s; }
.task-row:nth-child(5) { animation-delay: 0.5s; }
.task-row:nth-child(6) { animation-delay: 0.6s; }
        /* Fixed Navbar Styles */
        .navbar {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 0.8rem 0rem 0.8rem 0.3rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 10;
        }
        
        .navbar_container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0;
        }
        
        .navbar_logo {
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
            margin-left: 0.1rem;
        }
        
        .navbar_logo:hover {
            color: #4CAF50;
        }
        
        .navbar_profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-right: 0rem;
        }
        
        .navbar_notification {
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            padding: 0.6rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .navbar_notification:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .notification_badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .navbar_user {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.6rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }
        
        .navbar_user:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .navbar_avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            color: white;
        }
        
        .navbar_username {
            font-weight: 500;
            color: white;
            font-size: 0.9rem;
        }
        
        /* Dropdown Menu */
        .user_dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.5rem 0;
            min-width: 150px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .user_dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown_item {
            display: block;
            padding: 0.8rem 1rem;
            color: white;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .dropdown_item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #4CAF50;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.6rem 0rem 0.6rem 0.25rem;
                margin-bottom: 1.5rem;
            }
            
            .navbar_logo {
                font-size: 1.1rem;
                margin-left: 0.05rem;
            }
            
            .navbar_profile {
                gap: 0.8rem;
                margin-right: 0rem;
            }
            
            .navbar_username {
                display: none;
            }
            
            .navbar_user {
                padding: 0.6rem;
                border-radius: 50%;
            }
            
            .navbar_notification {
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .navbar {
                padding: 0.5rem 0rem 0.5rem 0.2rem;
            }
            
            .navbar_logo {
                margin-left: 0.03rem;
                font-size: 1rem;
            }
            
            .navbar_profile {
                margin-right: 0rem;
                gap: 0.6rem;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="upper_wrap">
        <!-- Fixed Navbar -->
        <div class="navbar">
            <div class="navbar_container">
                <div class="navbar_logo">Jalanyuk</div>
                
                <div class="navbar_profile">
                    <div class="navbar_notification">
                        🔔
                        <span class="notification_badge">3</span>
                    </div>
                    
                    <div class="navbar_user" onclick="toggleUserDropdown()">
                        <div class="navbar_avatar"><?php echo $user_initial; ?></div>
                        <span class="navbar_username"><?php echo $full_name; ?></span>
                        
                        <!-- Dropdown Menu -->
                        <div class="user_dropdown" id="userDropdown">
                            <a href="profile.php" class="dropdown_item">👤 Profile</a>
                            <a href="settings.php" class="dropdown_item">⚙️ Settings</a>
                            <a href="logout.php" class="dropdown_item">🚪 Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
   <!-- Dashboard Content -->
            <div class="dashboard_container">
                <div class="user_details-wrap">
                    <div class="user_left-wrap">
                        <div class="user_name-text">
                            <h1 data-w-id="dcdf084c-de56-a23e-6dab-41e9b66e897b" style="opacity:0" class="user_heading">
                                Hi, <?php echo htmlspecialchars($username); ?>. Glad<br />you&#x27;ve joined us
                            </h1>
                        </div>
                        <div data-w-id="a082e75d-76e8-55aa-761a-dd0f6641c033" style="opacity:0" class="user_card-wrap">
                            <div class="card_head">
                                <div>Balance</div>
                                <img src="images/mastercard-svgrepo-com.svg" loading="lazy" alt="" class="card_icon" />
                            </div>
                            <div class="text-size-large"><?php echo formatRupiah($user_balance); ?></div>
                            <div class="user_button-wrap">
                                <a href="#" class="user_button w-inline-block">
                                    <img src="images/down-arrow-svgrepo-com-20-1-.svg" loading="lazy" alt="" class="down_icon" />
                                    <div>Top Up</div>
                                </a>
                                <a href="#" class="user_button w-inline-block">
                                    <img src="images/down-arrow-svgrepo-com-20-1-.svg" loading="lazy" alt="" class="up_icon" />
                                    <div>Withdraw</div>
                                </a>
                            </div>
                        </div>
                    </div>
<div class="divider"></div>
          <div class="user_t\ransction-wrap">
            <div class="user_transction">
              <div data-w-id="3bb9abf8-e651-6093-6ea2-747441405f93" style="opacity:0">Insight</div>
              <div data-w-id="689d49bf-997a-c077-b672-5873f56c72cf" style="opacity:0" class="transction_details">
                <div class="moneyin_icon"><img src="images/dollar-low-svgrepo-com-201-20-3-.svg" loading="lazy" alt=""
                    class="dollar_icon" /></div>
                <div class="transction_text-details">
                  <div>Money In</div>
                  <div class="text-size-large text-weight-normal"><?php echo formatRupiah($transaction_insights['money_in']); ?></div>
                  <div class="flex-horizontal gap-10px overflow-hidden">
                    <?php if ($transaction_insights['money_in_trend'] == 'up'): ?>
                      <img src="images/trending_up_fill0_wght400_grad0_opsz24.svg" loading="lazy"
                           style="opacity:0" data-w-id="95de96a2-183c-c62f-39c5-12e8cf4edb10" alt="" />
                      <div class="text-color-green text-weight-semibold"><?php echo $transaction_insights['money_in_percentage']; ?>%</div>
                    <?php elseif ($transaction_insights['money_in_trend'] == 'down'): ?>
                      <img src="images/trending_down_fill0_wght400_grad0_opsz24-201-20-2-.svg" loading="lazy"
                           style="opacity:0" data-w-id="95de96a2-183c-c62f-39c5-12e8cf4edb10" alt="" />
                      <div class="text-color-red text-weight-semibold"><?php echo $transaction_insights['money_in_percentage']; ?>%</div>
                    <?php else: ?>
                      <img src="images/trending_flat_fill0_wght400_grad0_opsz24.svg" loading="lazy"
                           style="opacity:0" data-w-id="95de96a2-183c-c62f-39c5-12e8cf4edb10" alt="" />
                      <div class="text-color-gray text-weight-semibold">0%</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="user_transction">
              <div data-w-id="67e32c34-4db4-786b-a19a-be518b96f7f0" style="opacity:0" class="text-align-center">Last 7
                days</div>
              <div data-w-id="67e32c34-4db4-786b-a19a-be518b96f7f2" style="opacity:0" class="transction_details">
                <div class="moneyout_icon"><img src="images/dollar-low-svgrepo-com-201-20-2-.svg" loading="lazy" alt=""
                    class="dollar_icon" /></div>
                <div class="transction_text-details">
                  <div>Money Out</div>
                  <div class="text-size-large text-weight-normal"><?php echo formatRupiah($transaction_insights['money_out']); ?></div>
                  <div class="flex-horizontal gap-10px overflow-hidden">
                    <?php if ($transaction_insights['money_out_trend'] == 'up'): ?>
                      <img src="images/trending_up_fill0_wght400_grad0_opsz24.svg" loading="lazy"
                           style="opacity:0" data-w-id="67e32c34-4db4-786b-a19a-be518b96f7fb" alt="" />
                      <div class="text-color-red text-weight-semibold"><?php echo $transaction_insights['money_out_percentage']; ?>%</div>
                    <?php elseif ($transaction_insights['money_out_trend'] == 'down'): ?>
                      <img src="images/trending_down_fill0_wght400_grad0_opsz24-201-20-1-.svg" loading="lazy"
                           style="opacity:0" data-w-id="67e32c34-4db4-786b-a19a-be518b96f7fb" alt="" />
                      <div class="text-color-green text-weight-semibold"><?php echo $transaction_insights['money_out_percentage']; ?>%</div>
                    <?php else: ?>
                      <img src="images/trending_flat_fill0_wght400_grad0_opsz24.svg" loading="lazy"
                           style="opacity:0" data-w-id="67e32c34-4db4-786b-a19a-be518b96f7fb" alt="" />
                      <div class="text-color-gray text-weight-semibold">0%</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <main class="main-wrapper background_color">
      <div class="dashboard_container move_upside">
        <div data-w-id="ab735fd1-bed1-db35-78a3-e14e1018ed9f" style="opacity:0" class="quick_link-banner">
          <h3 class="dashboard_heading">Quick Links</h3>
          <div class="quick_link-wrap">
            <div id="w-node-c3d48d90-5ade-2f24-08b3-12d68da6aed7-639d297a" class="quick_links">
              <div class="quick_icon-wrap"><img
                  src="images/660e56d1cab5259c9183866f_computer-device-electronic-svgrepo-com-201.svg" loading="lazy"
                  alt="" /></div>
              <div>Customer Service</div>
            </div>
            <div id="w-node-fee89774-f41a-a1c3-0a59-da0f2ce02b86-639d297a" class="quick_divider"></div><a href="history.php"
              class="quick_links w-inline-block">
              <div class="quick_icon-wrap"><img src="images/transaction-history.png" loading="lazy" alt="" /></div>
              <div class="text-align-center">Histori Transaksi</div>
            </a><a href="checkout.php" class="quick_links w-inline-block">
              <div class="quick_icon-wrap"><img src="images/top-up.png" loading="lazy" alt="" /></div>  
              <div class="text-align-center">Topup Bank & E-wallet</div>
            </a><a href="checkout.php" class="quick_links w-inline-block">
              <div class="quick_icon-wrap"><img src="images/transference.png" loading="lazy" alt="" /></div>
              <div class="text-align-center">Transfer Bank & E-wallet</div>
            </a><a href="payment.php" class="quick_links w-inline-block">
              <div class="quick_icon-wrap"><img src="images/money.png" loading="lazy" alt="" /></div>
              <div class="text-align-center">Pembayaran Tagihan</div>
            </a>
            </a>
            </a><a href="family.php" class="quick_links w-inline-block">
              <div class="quick_icon-wrap"><img src="images/people-together.png" loading="lazy" alt="" /></div>
              <div class="text-align-center">Group family</div>
            </a>
          </div>    
        </div>
<!-- HTML logo slide-->
  <div class="logo-slider">
        <div class="logo-track">
            <div class="logo-item">
                <div class="logo-icon hourglass-icon"></div>
                <span>Hourglass</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon lightbox-icon"></div>
                <span>Lightbox</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon spherule-icon"></div>
                <span>Spherule</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon capsule-icon"></div>
                <span>Capsule</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon luminous-icon"></div>
                <span>Luminous</span>
            </div>
            <!-- Duplicate untuk seamless loop -->
            <div class="logo-item">
                <div class="logo-icon hourglass-icon"></div>
                <span>Hourglass</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon lightbox-icon"></div>
                <span>Lightbox</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon spherule-icon"></div>
                <span>Spherule</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon capsule-icon"></div>
                <span>Capsule</span>
            </div>
            <div class="logo-item">
                <div class="logo-icon luminous-icon"></div>
                <span>Luminous</span>
            </div>
        </div>
    </div>
        <!-- Grid container untuk Recent Transaction dan Tagihan berdampingan -->
 <!-- Grid container untuk Recent Transaction dan Tagihan berdampingan -->
<div class="dashboard_content-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1000px;">
  <!-- Recent Transaction -->
  <div data-w-id="00994659-5280-2b69-a7ca-6f75b27c9d89" style="opacity:1" class="dashboard_content">
    <div class="dashboard_heading-wrap">
      <h3 class="dashboard_heading">Recent Transaction</h3>
      <div data-w-id="7d33992b-6873-226e-8887-4defb33fcdf8">
        <div data-w-id="bf794478-0dfa-582b-4d75-4d793a5a7305" class="text-size-medium">See All</div>
        <div style="width:0%" class="under_line"></div>
      </div>
      <div class="ms-dot alignment"></div>
    </div>
    
    <?php if (!empty($recent_transactions)): ?>
      <?php foreach ($recent_transactions as $index => $transaction): ?>
        <?php 
          // Safely get transaction data with defaults
          $transaction_type = $transaction['transaction_type'] ?? 'unknown';
          $payment_method = $transaction['payment_method'] ?? '';
          $description = $transaction['description'] ?? '';
          $amount = $transaction['amount'] ?? 0;
          $status = $transaction['status'] ?? 'unknown';
          $created_at = $transaction['created_at'] ?? date('Y-m-d H:i:s');
          
          // Get icon with error handling
          $icon_path = getTransactionIcon($transaction_type, $payment_method, $description);
          $transaction_title = getTransactionTitle($transaction_type, $payment_method, $description);
        ?>
        <div class="dashboard_social-payment">
          <div class="social_icon-wrap">
            <img src="<?php echo htmlspecialchars($icon_path); ?>" 
                 loading="lazy" 
                 alt="<?php echo htmlspecialchars($transaction_type); ?>" 
                 onerror="this.src='images/piggy-bank.png';" />
          </div>
          <div class="social_text">
            <div><?php echo htmlspecialchars($transaction_title); ?></div>
            <div class="text-size-small text-color-grey">
              <?php echo formatTransactionDate($created_at); ?>
              <?php if ($status !== 'completed'): ?>
                <span class="<?php echo getStatusColorClass($status); ?>">
                  • <?php echo ucfirst($status); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="social_text align-right">
            <div class="text-weight-medium <?php echo in_array($transaction_type, ['topup', 'transfer_in']) ? 'text-color-success' : ''; ?>">
              <?php echo formatTransactionAmount($amount, $transaction_type); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      
    <?php elseif ($pdo === null): ?>
      <!-- Error state jika database tidak terhubung -->
      <div class="dashboard_social-payment">
        <div class="social_icon-wrap">
          <img src="images/piggy-bank.png" 
               loading="lazy" 
               alt="Database Error" />
        </div>
        <div class="social_text">
          <div class="text-color-error">Database Connection Error</div>
          <div class="text-size-small text-color-grey">Unable to load transactions</div>
        </div>
        <div class="social_text align-right">
          <div class="text-weight-medium text-color-grey">-</div>
        </div>
      </div>
      
    <?php else: ?>
      <!-- Fallback jika tidak ada transaksi -->
      <div class="dashboard_social-payment">
        <div class="social_icon-wrap">
          <img src="images/piggy-bank.png" 
               loading="lazy" 
               alt="No transactions" />
        </div>
        <div class="social_text">
          <div>No Recent Transactions</div>
          <div class="text-size-small text-color-grey">Your transaction history will appear here</div>
        </div>
        <div class="social_text align-right">
          <div class="text-weight-medium text-color-grey">-</div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Tagihan -->
  <div data-w-id="67f251de-3fd5-c092-8a15-71ab7063e2a5" style="opacity:1" class="dashboard_content">
    <div class="dashboard_heading-wrap">
      <h3 class="dashboard_heading">Tagihan Family</h3>
    </div>
    
    <div class="history_wrap">
      <a data-w-id="3a0cdacb-7c84-078c-e13a-039449c42c3e" href="#" class="dashboard_button w-inline-block">
        <label class="radio_button-wrap w-radio">
          <div class="w-form-formradioinput w-form-formradioinput--inputType-custom radio_button w-radio-input"></div>
          <input type="radio" data-name="Radio 3" id="radio-3" name="radio" style="opacity:0;position:absolute;z-index:-1" value="Radio" />
          <div class="radio_text-content">
            <div>invoice@memberstack.com</div>
            <div id="w-node-_3a0cdacb-7c84-078c-e13a-039449c42c44-639d297a" class="text-color-grey">6 Aug 2023</div>
            <div class="payment_status">
              <div class="payment_due">Payment Due</div>
            </div>
            <div id="w-node-_3a0cdacb-7c84-078c-e13a-039449c42c49-639d297a">$186.85 USD</div>
            <img src="images/menu-vertical-svgrepo-com.svg" loading="lazy" id="w-node-_3a0cdacb-7c84-078c-e13a-039449c42c4b-639d297a" alt="" class="menu_icon" />
          </div>
          <span class="radio-button-label w-form-label" for="radio-3">Radio</span>
        </label>
        <div class="ms-dot"></div>
      </a>
      
      <a data-w-id="4410ab3b-4713-d9ce-2a30-f513feec98ea" href="#" class="dashboard_button w-inline-block">
        <label class="radio_button-wrap w-radio">
          <div class="w-form-formradioinput w-form-formradioinput--inputType-custom radio_button w-radio-input"></div>
          <input type="radio" data-name="Radio 2" id="radio-2" name="radio" style="opacity:0;position:absolute;z-index:-1" value="Radio" />
          <div class="radio_text-content">
            <div class="text-block">invoice@memberstack.com</div>
            <div class="text-color-grey">3 Aug 2023</div>
            <div class="payment_status">
              <div class="payment_paid">Paid</div>
            </div>
            <div id="w-node-_4410ab3b-4713-d9ce-2a30-f513feec98f4-639d297a">$365.23 USD</div>
            <img src="images/menu-vertical-svgrepo-com.svg" loading="lazy" id="w-node-_4410ab3b-4713-d9ce-2a30-f513feec98f6-639d297a" alt="" class="menu_icon" />
          </div>
          <span class="radio-button-label w-form-label" for="radio-2">Radio</span>
        </label>
        <div class="ms-dot"></div>
      </a>
      
      <a data-w-id="6ca50410-5099-6536-76a4-e6d67f9fb90d" href="#" class="dashboard_button w-inline-block">
        <label class="radio_button-wrap w-radio">
          <div class="w-form-formradioinput w-form-formradioinput--inputType-custom radio_button w-radio-input"></div>
          <input type="radio" data-name="Radio" id="radio" name="radio" style="opacity:0;position:absolute;z-index:-1" value="Radio" />
          <div class="radio_text-content">
            <div>invoice@memberstack.com</div>
            <div class="text-color-grey">1 Aug 2023</div>
            <div class="payment_status">
              <div class="payment_due">Payment Due</div>
            </div>
            <div>$177.95 USD</div>
            <img src="images/menu-vertical-svgrepo-com.svg" loading="lazy" id="w-node-b0af1b1d-319b-abe3-0cef-a3f43b135ed5-639d297a" alt="" class="menu_icon" />
          </div>
          <span class="radio-button-label w-form-label" for="radio">Radio</span>
        </label>
        <div class="ms-dot"></div>
      </a>
    </div>
    
    <div class="w-form-done">
      <div>Thank you! Your submission has been received!</div>
    </div>
    <div class="w-form-fail">
      <div>Oops! Something went wrong while submitting the form.</div>
    </div>
  </div>
</div>
      
<!-- Overlay blur -->
<div id="chatbot-overlay"></div>

<!-- Chatbot -->
<div id="chatbot-container">
  <!-- Tombol mengambang -->
  <div id="chatbot-button">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
      <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="currentColor"/>
      <circle cx="8" cy="10" r="1.5" fill="white"/>
      <circle cx="16" cy="10" r="1.5" fill="white"/>
      <path d="M8 13C8 13 9.5 14.5 12 14.5S16 13 16 13" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <div id="notification-dot"></div>
  </div>

  <!-- Jendela chatbot -->
  <div id="chatbot-window">
    <div class="chat-header">
      <div class="chat-title">
        <div class="avatar">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" fill="white"/>
            <circle cx="8" cy="10" r="1.5" fill="#333"/>
            <circle cx="16" cy="10" r="1.5" fill="#333"/>
            <path d="M8 14C8 14 10 16 12 16C14 16 16 14 16 14" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <div>
          <h4>Assistant</h4>
          <span class="status">Online</span>
        </div>
      </div>
      <button id="close-chat">×</button>
    </div>

    <div class="chat-body" id="chat-body">
      <div class="message bot-message">
        <div class="message-content">
          Halo! Ada yang bisa saya bantu?
        </div>
      </div>
    </div>

    <div class="chat-input">
      <input type="text" id="user-input" placeholder="Ketik pesan Anda...">
      <button id="send-btn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M2 21L23 12L2 3V10L17 12L2 14V21Z" fill="currentColor"/>
        </svg>
      </button>
    </div>
  </div>
</div>

    
     <!-- Family -->

<div class="work-performance-dashboard">
    <div class="performance-card">
        <h2 class="card-title">Family
        
        <div class="task-table-container">
            <table class="task-table">
                <thead class="table-header">
                    <tr>
                        <th>TASK</th>
                        <th>STATUS</th>
                        <th>TEAM</th>
                        <th>ESTIMATED DATE</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="task-row">
                        <td>
                            <div class="task-name">Onboarding Process</div>
                        </td>
                        <td>
                            <span class="status-badge status-done">Done</span>
                        </td>
                        <td>
                            <div class="team-avatars">
                                <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                            </div>
                        </td>
                        <td>
                            <div class="date-text">February 10, 2020</div>
                        </td>
                    </tr>
                    
                    <tr class="task-row">
                        <td>
                            <div class="task-name">SEO Integrations</div>
                        </td>
                        <td>
                            <span class="status-badge status-done">Done</span>
                        </td>
                        <td>
                            <div class="team-avatars">
                                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                            </div>
                        </td>
                        <td>
                            <div class="date-text">February 10, 2020</div>
                        </td>
                    </tr>
                    
                    <tr class="task-row">
                        <td>
                            <div class="task-name">Hosting Configuration</div>
                        </td>
                        <td>
                            <span class="status-badge status-progress">In progress</span>
                        </td>
                        <td>
                            <div class="team-avatars">
                                <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1463453091185-61582044d556?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                            </div>
                        </td>
                        <td>
                            <div class="date-text">February 10, 2020</div>
                        </td>
                    </tr>
                    
                    <tr class="task-row">
                        <td>
                            <div class="task-name">CMS Handover</div>
                        </td>
                        <td>
                            <span class="status-badge status-progress">In progress</span>
                        </td>
                        <td>
                            <div class="team-avatars">
                                <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                            </div>
                        </td>
                        <td>
                            <div class="date-text">February 10, 2020</div>
                        </td>
                    </tr>
                    
                    <tr class="task-row">
                        <td>
                            <div class="task-name">Content Review</div>
                        </td>
                        <td>
                            <span class="status-badge status-progress">In progress</span>
                        </td>
                        <td>
                            <div class="team-avatars">
                                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                            </div>
                        </td>
                        <td>
                            <div class="date-text">February 10, 2020</div>
                        </td>
                    </tr>
                    
                    <tr class="task-row">
                        <td>
                            <div class="task-name">Integrate Analytics</div>
                        </td>
                        <td>
                            <span class="status-badge status-next">Next up</span>
                        </td>
                        <td>
                            <div class="team-avatars">
                                <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face" alt="Team member" class="team-avatar">
                            </div>
                        </td>
                        <td>
                            <div class="date-text">February 10, 2020</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <p class="footer-text">Showing all items from Work Performance dataset</p>
        </div>
    </div>
</div>

  <script src="js/jquery.js?site=68532312ba0975dc639d2929" type="text/javascript"></script>
  <script src="js/w.schunk.36b8fb49256177c8.js" type="text/javascript"></script>
  <script src="js/w.schunk.d97efad01808426c.js" type="text/javascript"></script>
  <script src="js/w.schunk.366d7c21cd7fb47e.js" type="text/javascript"></script>
  <script src="js/wscript.js" type="text/javascript"></script>
 <!-- Script: Buka Tutup Chat -->
<script>
// Fungsi: Buka/Tutup Chat
document.addEventListener("DOMContentLoaded", function () {
  const chatbotButton = document.getElementById("chatbot-button");
  const chatbotWindow = document.getElementById("chatbot-window");
  const chatbotOverlay = document.getElementById("chatbot-overlay");
  const closeChatBtn = document.getElementById("close-chat");

  function toggleChat() {
    chatbotWindow.classList.toggle("active");
    chatbotOverlay.classList.toggle("active");
  }

  if (chatbotButton && closeChatBtn && chatbotOverlay) {
    chatbotButton.addEventListener("click", toggleChat);
    closeChatBtn.addEventListener("click", toggleChat);
    chatbotOverlay.addEventListener("click", toggleChat);
  }
});
</script>

<script>
// Fungsi: Kirim Pesan ke PHP
document.addEventListener("DOMContentLoaded", function () {
  const userInput = document.getElementById('user-input');
  const sendBtn = document.getElementById('send-btn');
  const chatBody = document.getElementById('chat-body');

  let isTyping = false;

  function showTypingIndicator() {
    isTyping = true;
    const typingIndicator = document.createElement('div');
    typingIndicator.className = 'message bot-message typing';
    typingIndicator.id = 'typing-indicator';
    typingIndicator.innerHTML = `
      <div class="message-content typing-indicator">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      </div>`;
    chatBody.appendChild(typingIndicator);
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  function hideTypingIndicator() {
    isTyping = false;
    const indicator = document.getElementById('typing-indicator');
    if (indicator) indicator.remove();
  }

  function addMessage(text, sender) {
    const messageElement = document.createElement('div');
    messageElement.className = `message ${sender}-message`;

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';

    if (sender === 'bot') {
      contentDiv.innerHTML = text; // HTML aman dari backend
    } else {
      contentDiv.textContent = text; // Hindari XSS dari user
    }

    messageElement.appendChild(contentDiv);
    chatBody.appendChild(messageElement);
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  function sendMessage() {
    const message = userInput.value.trim();
    if (!message || isTyping) return;

    addMessage(message, 'user');
    userInput.value = '';
    sendBtn.disabled = true;
    showTypingIndicator();

    fetch('chatbot-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ message })
    })
    .then(response => response.json())
    .then(data => {
      hideTypingIndicator();
      addMessage(data.reply || "Maaf, tidak ada balasan dari server.", 'bot');
      sendBtn.disabled = false;
    })
    .catch(error => {
      console.error('Error:', error);
      hideTypingIndicator();
      addMessage("Terjadi kesalahan saat menghubungi server.", 'bot');
      sendBtn.disabled = false;
    });
  }

  if (sendBtn && userInput) {
    sendBtn.addEventListener('click', sendMessage);
    userInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') sendMessage();
    });
  }
});
</script>


  <script>
// Simple Navbar JavaScript - No Errors
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const userDiv = document.querySelector('.navbar_user');
        const dropdown = document.getElementById('userDropdown');
        
        if (dropdown && userDiv && !userDiv.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Add basic styles
    const style = document.createElement('style');
    style.textContent = `
        .navbar_user {
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.3s ease;
        }

        .navbar_user:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar_avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
        }

        .navbar_username {
            color: white;
            font-size: 14px;
            font-weight: 500;
        }

        .user_dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(0, 0, 0, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            min-width: 200px;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin-top: 5px;
        }

        .user_dropdown.show {
            display: block;
        }

        .dropdown_item {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            transition: background 0.3s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dropdown_item:last-child {
            border-bottom: none;
        }

        .dropdown_item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .navbar_notification {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background 0.3s ease;
            font-size: 20px;
        }

        .navbar_notification:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .notification_badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .navbar_profile {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        @media (max-width: 768px) {
            .navbar_username {
                display: none;
            }
            
            .user_dropdown {
                right: -10px;
                min-width: 180px;
            }
        }
    `;
    document.head.appendChild(style);
});

// Simple notification function
function showNotification(message) {
    alert(message);
}
</script>


</body>
</html>