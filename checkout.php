<?php
session_start();
require_once 'stripe/init.php';

// Database connection
$host = 'localhost';
$dbname = 'jalanyukproject';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

\Stripe\Stripe::setApiKey(getenv('YOUR_SECRET_KEY'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Transfer
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST['jenis'] === 'transfer') {
    $email_tujuan = trim($_POST['email_tujuan']);
    $nominal = (int)$_POST['nominal'];
    $pesan = trim($_POST['pesan']) ?: 'Transfer dari user';

    if ($nominal < 1000) {
        $error = 'Nominal minimal transfer Rp1.000';
    } else {
        try {
            $pdo->beginTransaction();

            // Get sender's current balance
            $stmt = $pdo->prepare("SELECT balance, full_name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sender) {
                throw new Exception('Data pengirim tidak ditemukan');
            }

            // Check if sender has enough balance
            if ($sender['balance'] < $nominal) {
                throw new Exception('Saldo tidak mencukupi. Saldo Anda: Rp ' . number_format($sender['balance'], 0, ',', '.'));
            }

            // Check if trying to transfer to own email
            if ($sender['email'] === $email_tujuan) {
                throw new Exception('Tidak dapat mengirim transfer ke email sendiri');
            }

            // Find recipient by email
            $stmt = $pdo->prepare("SELECT id, full_name, balance FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email_tujuan]);
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$recipient) {
                throw new Exception('Email tujuan tidak ditemukan atau tidak aktif');
            }

            // Deduct from sender's balance
            $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$nominal, $user_id]);

            // Add to recipient's balance
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$nominal, $recipient['id']]);

            // Record outgoing transaction (for sender)
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, transaction_type, amount, payment_method, status, description, created_at) VALUES (?, 'transfer_out', ?, 'Transfer', 'completed', ?, NOW())");
            $stmt->execute([$user_id, $nominal, "Transfer ke " . $recipient['full_name'] . " ($email_tujuan) - " . $pesan]);

            // Record incoming transaction (for recipient)
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, transaction_type, amount, payment_method, status, description, created_at) VALUES (?, 'transfer_in', ?, 'Transfer', 'completed', ?, NOW())");
            $stmt->execute([$recipient['id'], $nominal, "Transfer dari " . $sender['full_name'] . " (" . $sender['email'] . ") - " . $pesan]);

            $pdo->commit();
            $message = 'Transfer berhasil dikirim ke ' . $recipient['full_name'] . ' sebesar Rp ' . number_format($nominal, 0, ',', '.');

        } catch (Exception $e) {
            $pdo->rollback();
            $error = $e->getMessage();
        }
    }
}

// Handle Top Up
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST['jenis'] === 'topup') {
    $amount = (int)$_POST['amount'];
    $method = $_POST['method'];

    if ($amount < 1000) {
        $error = 'Nominal minimal Rp1.000';
    } else {
        $stripeAmount = $amount * 100;

        try {
            // 1. Simpan transaksi dengan status pending
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, transaction_type, amount, status, payment_method, description) VALUES (?, 'topup', ?, 'pending', ?, ?)");
            $stmt->execute([$user_id, $amount, $method, "Top Up via $method"]);
            $transaction_id = $pdo->lastInsertId();

            // 2. Buat Stripe session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'idr',
                        'product_data' => [
                            'name' => "Top Up via $method",
                        ],
                        'unit_amount' => $stripeAmount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => 'http://localhost/projectpsi/succes.php?transaction_id=' . $transaction_id,
                'cancel_url' => 'http://localhost/projectpsi/cancel.php?transaction_id=' . $transaction_id,
                'metadata' => [
                    'transaction_id' => $transaction_id,
                    'user_id' => $user_id
                ]
            ]);

            // 3. Update transaksi dengan stripe session ID
            $stmt = $pdo->prepare("UPDATE transactions SET stripe_session_id = ? WHERE id = ?");
            $stmt->execute([$session->id, $transaction_id]);

            header("Location: " . $session->url);
            exit;

        } catch (Exception $e) {
            // Jika ada error, update status transaksi menjadi failed
            if (isset($transaction_id)) {
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed', description = CONCAT(description, ' - Error: ', ?) WHERE id = ?");
                $stmt->execute([$e->getMessage(), $transaction_id]);
            }
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get user balance
$stmt = $pdo->prepare("SELECT balance, full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $user['balance'] ?? 0;
$full_name = $user['full_name'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Transaksi</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: #f5f5f5;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    
    /* Navbar Styles */
    .navbar {
      background: #000;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: white;
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    
    .navbar .logo {
      font-size: 20px;
      font-weight: bold;
      color: white;
    }
    
    .navbar .user-section {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .navbar .notification-icon {
      position: relative;
      cursor: pointer;
    }
    
    .navbar .notification-icon::before {
      content: '🔔';
      font-size: 18px;
    }
    
    .navbar .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #ff0000;
      color: white;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
    }
    
    .navbar .user-profile {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
    }
    
    .navbar .user-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: #28a745;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 16px;
    }
    
    .navbar .user-name {
      font-size: 16px;
      font-weight: 500;
    }
    
    /* Main Content */
    .main-content {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    
    .container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.12);
      width: 100%;
      max-width: 420px;
      overflow: hidden;
    }
    
    .user-info {
      background: linear-gradient(135deg,rgb(0, 0, 0) 0%,rgb(0, 0, 0) 100%);
      color: white;
      padding: 20px;
      text-align: center;
    }
    
    .user-name {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 8px;
    }
    
    .user-balance {
      font-size: 24px;
      font-weight: 700;
      color: #fff;
    }
    
    .tabs {
      display: flex;
      background: #f8f9fa;
    }
    
    .tab {
      flex: 1;
      padding: 20px;
      text-align: center;
      font-weight: 600;
      color: #666;
      cursor: pointer;
      transition: all 0.3s ease;
      border-bottom: 3px solid transparent;
    }
    
    .tab.active {
      color: #333;
      background: white;
      border-bottom-color: #333;
    }
    
    .tab-content {
      padding: 30px;
    }
    
    .form-section {
      display: none;
    }
    
    .form-section.active {
      display: block;
    }
    
    .form-group {
      margin-bottom: 24px;
    }
    
    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #333;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .form-input, .form-select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e1e5e9;
      border-radius: 12px;
      font-size: 16px;
      transition: border-color 0.3s ease;
      background: white;
    }
    
    .form-input:focus, .form-select:focus {
      outline: none;
      border-color: #4a90e2;
    }
    
    .amount-presets {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 20px;
    }
    
    .preset-btn {
      padding: 14px 8px;
      border: 2px solid #e8e8e8;
      border-radius: 14px;
      background: white;
      color: #444;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-align: center;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.2;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 50px;
    }
    
    .preset-btn:hover {
      border-color: #666;
      background: #f8f9fa;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.15);
    }
    
    .preset-btn.selected {
      border-color: #000;
      background: #000;
      color: white;
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
      transform: translateY(-1px);
    }
    
    .preset-btn span {
      display: block;
      white-space: nowrap;
    }
    
    .submit-btn {
      width: 100%;
      padding: 16px;
      background: #000;
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }
    
    .submit-btn:hover {
      background: #333;
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0,0,0,0.3);
    }
    
    .submit-btn:active {
      transform: translateY(0);
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .amount-input-wrapper {
      position: relative;
    }
    
    .amount-input {
      padding-left: 40px;
      font-size: 16px;
      color: #333;
      font-weight: 500;
    }
    
    .currency-symbol {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #333;
      font-weight: 600;
      font-size: 16px;
      z-index: 1;
    }

    .message {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 12px;
      font-weight: 500;
      text-align: center;
    }

    .message.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .message.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 10px;
      }
      
      .container {
        max-width: 100%;
      }
      
      .amount-presets {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .tab-content {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar">
    <div class="logo">Jalanyuk</div>
    <div class="user-section">
      <div class="notification-icon">
        <div class="notification-badge">3</div>
      </div>
      <div class="user-profile">
        <div class="user-avatar">T</div>
        <div class="user-name">Test</div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="main-content">
    <div class="container">
      <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
        <div class="user-balance">Rp <?php echo number_format($balance, 0, ',', '.'); ?></div>
      </div>
      
      <div class="tabs">
        <div class="tab active" onclick="switchTab('topup')">Top Up</div>
        <div class="tab" onclick="switchTab('transfer')">Transfer</div>
      </div>
      
      <div class="tab-content">
        <?php if ($message): ?>
          <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
          <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- TOP UP SECTION -->
        <div class="form-section active" id="topup-section">
          <form method="POST" id="form-topup">
            <input type="hidden" name="jenis" value="topup" />
            
            <div class="form-group">
              <label class="form-label">Metode Top Up</label>
              <select name="method" class="form-select" required>
                <option value="Kartu Kredit/Debit">Kartu Kredit/Debit</option>
                <option value="QRIS">QRIS</option>
                <option value="Transfer Bank">Transfer Bank</option>
                <option value="E-Wallet">E-Wallet</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Nominal (Rp)</label>
              <div class="amount-input-wrapper">
                <span class="currency-symbol">Rp</span>
                <input type="text" name="amount" class="form-input amount-input" 
                       placeholder="Masukkan jumlah top up" required 
                       oninput="formatAmount(this)" />
              </div>
            </div>

            <div class="amount-presets">
              <button type="button" class="preset-btn" onclick="selectAmount(50000)">
                <span>Rp 50.000</span>
              </button>
              <button type="button" class="preset-btn" onclick="selectAmount(100000)">
                <span>Rp 100.000</span>
              </button>
              <button type="button" class="preset-btn" onclick="selectAmount(200000)">
                <span>Rp 200.000</span>
              </button>
              <button type="button" class="preset-btn" onclick="selectAmount(500000)">
                <span>Rp 500.000</span>
              </button>
              <button type="button" class="preset-btn" onclick="selectAmount(1000000)">
                <span>Rp 1.000.000</span>
              </button>
              <button type="button" class="preset-btn" onclick="selectAmount(2000000)">
                <span>Rp 2.000.000</span>
              </button>
            </div>

            <button type="submit" class="submit-btn">Top Up Sekarang</button>
          </form>
        </div>

        <!-- TRANSFER SECTION -->
        <div class="form-section" id="transfer-section">
          <form method="POST" id="form-transfer">
            <input type="hidden" name="jenis" value="transfer" />
            
            <div class="form-group">
              <label class="form-label">Email Tujuan</label>
              <input type="email" name="email_tujuan" class="form-input" 
                     placeholder="Masukkan email penerima" required />
            </div>

            <div class="form-group">
              <label class="form-label">Nominal (Rp)</label>
              <div class="amount-input-wrapper">
                <span class="currency-symbol">Rp</span>
                <input type="text" name="nominal" class="form-input amount-input" 
                       placeholder="Masukkan jumlah transfer" required 
                       oninput="formatAmount(this)" />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Pesan (Opsional)</label>
              <input type="text" name="pesan" class="form-input" 
                     placeholder="Contoh: uang makan, bayar hutang" />
            </div>

            <button type="submit" class="submit-btn">Kirim Transfer</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    function switchTab(tabName) {
      document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
      });
      event.target.classList.add('active');
      
      document.querySelectorAll('.form-section').forEach(section => {
        section.classList.remove('active');
      });
      document.getElementById(tabName + '-section').classList.add('active');
    }

    function selectAmount(amount) {
      document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.classList.remove('selected');
      });
      
      event.target.classList.add('selected');
      
      const amountInput = document.querySelector('#topup-section .amount-input');
      amountInput.value = formatNumber(amount);
    }

    function formatAmount(input) {
      let value = input.value.replace(/[^\d]/g, '');
      
      if (value) {
        value = formatNumber(parseInt(value));
      }
      
      input.value = value;
      
      // Remove selected class from preset buttons when typing
      if (input.closest('#topup-section')) {
        document.querySelectorAll('.preset-btn').forEach(btn => {
          btn.classList.remove('selected');
        });
      }
    }

    function formatNumber(num) {
      return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    document.getElementById('form-topup').addEventListener('submit', function(e) {
      const amountInput = this.querySelector('.amount-input');
      const rawAmount = amountInput.value.replace(/[^\d]/g, '');
      
      if (!rawAmount || parseInt(rawAmount) < 1000) {
        e.preventDefault();
        alert('Nominal minimal top up adalah Rp 1.000');
        return;
      }
      
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'amount';
      hiddenInput.value = rawAmount;
      this.appendChild(hiddenInput);
      
      amountInput.removeAttribute('name');
    });

    document.getElementById('form-transfer').addEventListener('submit', function(e) {
      const amountInput = this.querySelector('.amount-input');
      const rawAmount = amountInput.value.replace(/[^\d]/g, '');
      
      if (!rawAmount || parseInt(rawAmount) < 1000) {
        e.preventDefault();
        alert('Nominal minimal transfer adalah Rp 1.000');
        return;
      }
      
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'nominal';
      hiddenInput.value = rawAmount;
      this.appendChild(hiddenInput);
      
      amountInput.removeAttribute('name');
    });
  </script>
</body>
</html>